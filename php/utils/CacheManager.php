<?php
/**
 * Cache Manager
 * Gestion du cache pour les extractions de documents
 *
 * @package VisaChatbot\Utils
 * @version 1.0.0
 */

namespace VisaChatbot\Utils;

class CacheManager {

    /**
     * Répertoire de cache
     */
    private string $cacheDir;

    /**
     * TTL par défaut (24h)
     */
    private int $ttl = 86400;

    /**
     * Cache activé
     */
    private bool $enabled = true;

    /**
     * Constructeur
     *
     * @param string|null $cacheDir Répertoire de cache
     * @param int $ttl Durée de vie du cache en secondes
     */
    public function __construct(?string $cacheDir = null, int $ttl = 86400) {
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir() . '/visa-chatbot-cache';
        $this->ttl = $ttl;

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Active/désactive le cache
     *
     * @param bool $enabled
     * @return self
     */
    public function setEnabled(bool $enabled): self {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * Définit le TTL
     *
     * @param int $ttl
     * @return self
     */
    public function setTtl(int $ttl): self {
        $this->ttl = $ttl;
        return $this;
    }

    /**
     * Génère une clé de cache
     *
     * @param string $type Type de document
     * @param string $content Contenu du document
     * @return string
     */
    public function generateKey(string $type, string $content): string {
        return $type . '_' . hash('sha256', $content);
    }

    /**
     * Récupère une entrée du cache
     *
     * @param string $type Type de document
     * @param string $content Contenu du document
     * @return array|null
     */
    public function get(string $type, string $content): ?array {
        if (!$this->enabled) {
            return null;
        }

        $cacheKey = $this->generateKey($type, $content);
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.cache';

        if (!file_exists($cacheFile)) {
            return null;
        }

        $data = file_get_contents($cacheFile);
        if ($data === false) {
            return null;
        }

        $cached = @unserialize($data);
        if (!is_array($cached)) {
            return null;
        }

        // Vérifier l'expiration
        if (isset($cached['expires_at']) && $cached['expires_at'] < time()) {
            @unlink($cacheFile);
            return null;
        }

        return $cached['data'] ?? null;
    }

    /**
     * Enregistre une entrée dans le cache
     *
     * @param string $type Type de document
     * @param string $content Contenu du document
     * @param array $result Résultat à cacher
     * @param int|null $ttl TTL personnalisé
     * @return bool
     */
    public function set(string $type, string $content, array $result, ?int $ttl = null): bool {
        if (!$this->enabled) {
            return false;
        }

        $cacheKey = $this->generateKey($type, $content);
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.cache';

        $ttl = $ttl ?? $this->ttl;

        $data = [
            'key' => $cacheKey,
            'type' => $type,
            'created_at' => time(),
            'expires_at' => time() + $ttl,
            'data' => $result
        ];

        $written = file_put_contents($cacheFile, serialize($data), LOCK_EX);
        return $written !== false;
    }

    /**
     * Supprime une entrée du cache
     *
     * @param string $type
     * @param string $content
     * @return bool
     */
    public function delete(string $type, string $content): bool {
        $cacheKey = $this->generateKey($type, $content);
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.cache';

        if (file_exists($cacheFile)) {
            return @unlink($cacheFile);
        }

        return true;
    }

    /**
     * Vide tout le cache
     *
     * @return int Nombre de fichiers supprimés
     */
    public function clear(): int {
        $files = glob($this->cacheDir . '/*.cache');
        $deleted = 0;

        if ($files) {
            foreach ($files as $file) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Nettoie les entrées expirées
     *
     * @return int Nombre d'entrées supprimées
     */
    public function cleanup(): int {
        $files = glob($this->cacheDir . '/*.cache');
        $deleted = 0;

        if ($files) {
            foreach ($files as $file) {
                $data = @file_get_contents($file);
                if ($data === false) {
                    continue;
                }

                $cached = @unserialize($data);
                if (!is_array($cached)) {
                    @unlink($file);
                    $deleted++;
                    continue;
                }

                if (isset($cached['expires_at']) && $cached['expires_at'] < time()) {
                    @unlink($file);
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Retourne les statistiques du cache
     *
     * @return array
     */
    public function getStats(): array {
        $files = glob($this->cacheDir . '/*.cache');
        $totalSize = 0;
        $expired = 0;
        $valid = 0;

        if ($files) {
            foreach ($files as $file) {
                $totalSize += filesize($file);

                $data = @file_get_contents($file);
                if ($data !== false) {
                    $cached = @unserialize($data);
                    if (is_array($cached)) {
                        if (isset($cached['expires_at']) && $cached['expires_at'] < time()) {
                            $expired++;
                        } else {
                            $valid++;
                        }
                    }
                }
            }
        }

        return [
            'directory' => $this->cacheDir,
            'total_files' => count($files),
            'valid_entries' => $valid,
            'expired_entries' => $expired,
            'total_size_bytes' => $totalSize,
            'total_size_formatted' => $this->formatBytes($totalSize),
            'enabled' => $this->enabled,
            'ttl' => $this->ttl
        ];
    }

    /**
     * Formate les octets en unité lisible
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
