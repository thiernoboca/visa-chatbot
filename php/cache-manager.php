<?php
/**
 * Cache Manager - Chatbot Visa CI
 * Système de cache fichier optimisé avec index JSON
 * 
 * @package VisaChatbot
 * @version 1.0.0
 */

class CacheManager {
    
    /**
     * Répertoire de cache
     */
    private const CACHE_DIR = __DIR__ . '/../data/cache/';
    
    /**
     * Fichier d'index pour accès O(1)
     */
    private const INDEX_FILE = 'cache_index.json';
    
    /**
     * TTL par défaut (1 heure)
     */
    private const DEFAULT_TTL = 3600;
    
    /**
     * Seuil de compression (10 KB)
     */
    private const COMPRESSION_THRESHOLD = 10240;
    
    /**
     * Index en mémoire
     */
    private array $index = [];
    
    /**
     * Singleton instance
     */
    private static ?CacheManager $instance = null;
    
    /**
     * Constructeur privé (singleton)
     */
    private function __construct() {
        $this->ensureCacheDirectory();
        $this->loadIndex();
    }
    
    /**
     * Obtenir l'instance singleton
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Crée le répertoire de cache si nécessaire
     */
    private function ensureCacheDirectory(): void {
        if (!is_dir(self::CACHE_DIR)) {
            mkdir(self::CACHE_DIR, 0755, true);
        }
    }
    
    /**
     * Chemin du fichier d'index
     */
    private function getIndexPath(): string {
        return self::CACHE_DIR . self::INDEX_FILE;
    }
    
    /**
     * Chemin d'un fichier cache
     */
    private function getCachePath(string $key): string {
        // Hash le clé pour éviter les problèmes de caractères spéciaux
        $hash = md5($key);
        return self::CACHE_DIR . $hash . '.cache';
    }
    
    /**
     * Charge l'index depuis le fichier
     */
    private function loadIndex(): void {
        $indexPath = $this->getIndexPath();
        
        if (file_exists($indexPath)) {
            $content = file_get_contents($indexPath);
            $this->index = json_decode($content, true) ?? [];
        } else {
            $this->index = [];
        }
    }
    
    /**
     * Sauvegarde l'index
     */
    private function saveIndex(): void {
        $indexPath = $this->getIndexPath();
        file_put_contents($indexPath, json_encode($this->index, JSON_PRETTY_PRINT), LOCK_EX);
    }
    
    /**
     * Récupère une valeur du cache
     * 
     * @param string $key Clé de cache
     * @return mixed Valeur ou null si expirée/inexistante
     */
    public function get(string $key): mixed {
        // Vérifier l'index
        if (!isset($this->index[$key])) {
            return null;
        }
        
        $entry = $this->index[$key];
        
        // Vérifier l'expiration
        if ($entry['expires_at'] < time()) {
            $this->delete($key);
            return null;
        }
        
        // Charger le fichier cache
        $cachePath = $this->getCachePath($key);
        
        if (!file_exists($cachePath)) {
            unset($this->index[$key]);
            $this->saveIndex();
            return null;
        }
        
        $content = file_get_contents($cachePath);
        
        // Décompresser si nécessaire
        if ($entry['compressed'] ?? false) {
            $content = gzuncompress($content);
        }
        
        // Mettre à jour le compteur d'accès
        $this->index[$key]['hits'] = ($this->index[$key]['hits'] ?? 0) + 1;
        $this->index[$key]['last_access'] = time();
        $this->saveIndex();
        
        return unserialize($content);
    }
    
    /**
     * Stocke une valeur dans le cache
     * 
     * @param string $key Clé de cache
     * @param mixed $value Valeur à stocker
     * @param int $ttl Durée de vie en secondes
     * @return bool Succès
     */
    public function set(string $key, mixed $value, int $ttl = self::DEFAULT_TTL): bool {
        $cachePath = $this->getCachePath($key);
        $content = serialize($value);
        $compressed = false;
        
        // Compresser si le contenu est volumineux
        if (strlen($content) > self::COMPRESSION_THRESHOLD) {
            $compressedContent = gzcompress($content, 6);
            if ($compressedContent !== false && strlen($compressedContent) < strlen($content)) {
                $content = $compressedContent;
                $compressed = true;
            }
        }
        
        // Écrire le fichier cache
        $result = file_put_contents($cachePath, $content, LOCK_EX);
        
        if ($result === false) {
            return false;
        }
        
        // Mettre à jour l'index
        $this->index[$key] = [
            'hash' => md5($key),
            'created_at' => time(),
            'expires_at' => time() + $ttl,
            'ttl' => $ttl,
            'size' => strlen($content),
            'compressed' => $compressed,
            'hits' => 0,
            'last_access' => time()
        ];
        
        $this->saveIndex();
        
        return true;
    }
    
    /**
     * Vérifie si une clé existe et n'est pas expirée
     * 
     * @param string $key Clé de cache
     * @return bool Existe et valide
     */
    public function has(string $key): bool {
        if (!isset($this->index[$key])) {
            return false;
        }
        
        if ($this->index[$key]['expires_at'] < time()) {
            $this->delete($key);
            return false;
        }
        
        return file_exists($this->getCachePath($key));
    }
    
    /**
     * Supprime une entrée du cache
     * 
     * @param string $key Clé de cache
     * @return bool Succès
     */
    public function delete(string $key): bool {
        $cachePath = $this->getCachePath($key);
        
        // Supprimer le fichier
        if (file_exists($cachePath)) {
            @unlink($cachePath);
        }
        
        // Supprimer de l'index
        unset($this->index[$key]);
        $this->saveIndex();
        
        return true;
    }
    
    /**
     * Prolonge le TTL d'une entrée
     * 
     * @param string $key Clé de cache
     * @param int $ttl Nouveau TTL
     * @return bool Succès
     */
    public function touch(string $key, int $ttl = self::DEFAULT_TTL): bool {
        if (!isset($this->index[$key])) {
            return false;
        }
        
        $this->index[$key]['expires_at'] = time() + $ttl;
        $this->index[$key]['ttl'] = $ttl;
        $this->saveIndex();
        
        return true;
    }
    
    /**
     * Nettoie les entrées expirées
     * 
     * @return int Nombre d'entrées supprimées
     */
    public function cleanup(): int {
        $deleted = 0;
        $now = time();
        
        foreach ($this->index as $key => $entry) {
            if ($entry['expires_at'] < $now) {
                $this->delete($key);
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    /**
     * Vide tout le cache
     * 
     * @return int Nombre d'entrées supprimées
     */
    public function flush(): int {
        $count = count($this->index);
        
        // Supprimer tous les fichiers cache
        foreach (glob(self::CACHE_DIR . '*.cache') as $file) {
            @unlink($file);
        }
        
        // Réinitialiser l'index
        $this->index = [];
        $this->saveIndex();
        
        return $count;
    }
    
    /**
     * Retourne les statistiques du cache
     * 
     * @return array Statistiques
     */
    public function getStats(): array {
        $totalSize = 0;
        $totalHits = 0;
        $expired = 0;
        $now = time();
        
        foreach ($this->index as $entry) {
            $totalSize += $entry['size'] ?? 0;
            $totalHits += $entry['hits'] ?? 0;
            
            if ($entry['expires_at'] < $now) {
                $expired++;
            }
        }
        
        return [
            'entries' => count($this->index),
            'total_size' => $totalSize,
            'total_size_human' => $this->formatBytes($totalSize),
            'total_hits' => $totalHits,
            'expired_pending' => $expired,
            'cache_dir' => self::CACHE_DIR
        ];
    }
    
    /**
     * Formate les octets en unités lisibles
     */
    private function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen((string)$bytes) - 1) / 3);
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor] ?? 'B');
    }
    
    /**
     * Récupère ou crée une valeur (cache-aside pattern)
     * 
     * @param string $key Clé de cache
     * @param callable $callback Fonction pour créer la valeur
     * @param int $ttl Durée de vie
     * @return mixed Valeur du cache ou générée
     */
    public function remember(string $key, callable $callback, int $ttl = self::DEFAULT_TTL): mixed {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Incrémente une valeur numérique
     * 
     * @param string $key Clé de cache
     * @param int $step Incrément
     * @return int Nouvelle valeur
     */
    public function increment(string $key, int $step = 1): int {
        $value = $this->get($key) ?? 0;
        $newValue = (int)$value + $step;
        
        // Préserver le TTL existant
        $ttl = $this->index[$key]['ttl'] ?? self::DEFAULT_TTL;
        $this->set($key, $newValue, $ttl);
        
        return $newValue;
    }
    
    /**
     * Décrémente une valeur numérique
     * 
     * @param string $key Clé de cache
     * @param int $step Décrément
     * @return int Nouvelle valeur
     */
    public function decrement(string $key, int $step = 1): int {
        return $this->increment($key, -$step);
    }
    
    /**
     * Cache avec tags pour invalidation groupée
     * 
     * @param string $key Clé de cache
     * @param mixed $value Valeur
     * @param array $tags Tags pour grouper
     * @param int $ttl Durée de vie
     * @return bool Succès
     */
    public function setWithTags(string $key, mixed $value, array $tags, int $ttl = self::DEFAULT_TTL): bool {
        $result = $this->set($key, $value, $ttl);
        
        if ($result) {
            $this->index[$key]['tags'] = $tags;
            $this->saveIndex();
        }
        
        return $result;
    }
    
    /**
     * Invalide toutes les entrées avec un tag spécifique
     * 
     * @param string $tag Tag à invalider
     * @return int Nombre d'entrées invalidées
     */
    public function invalidateTag(string $tag): int {
        $deleted = 0;
        
        foreach ($this->index as $key => $entry) {
            if (in_array($tag, $entry['tags'] ?? [])) {
                $this->delete($key);
                $deleted++;
            }
        }
        
        return $deleted;
    }
}

