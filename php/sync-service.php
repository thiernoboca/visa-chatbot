<?php
/**
 * Sync Service - Chatbot Visa CI
 * Service de synchronisation multi-device
 * Permet de reprendre une demande sur un autre appareil
 * 
 * @package VisaChatbot
 * @version 1.0.0
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session-manager.php';

class SyncService {
    
    /**
     * Répertoire des tokens de synchronisation
     */
    private const TOKENS_DIR = __DIR__ . '/../data/sync-tokens/';
    
    /**
     * Durée de validité d'un token (24 heures)
     */
    private const TOKEN_DURATION = 86400;
    
    /**
     * Longueur du token (en bytes, le token final sera 2x plus long en hex)
     */
    private const TOKEN_LENGTH = 16;
    
    /**
     * Singleton instance
     */
    private static ?SyncService $instance = null;
    
    /**
     * Constructeur privé (singleton)
     */
    private function __construct() {
        $this->ensureTokensDirectory();
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
     * S'assure que le répertoire de tokens existe
     */
    private function ensureTokensDirectory(): void {
        if (!is_dir(self::TOKENS_DIR)) {
            mkdir(self::TOKENS_DIR, 0755, true);
        }
    }
    
    /**
     * Chemin du fichier token
     */
    private function getTokenFilePath(string $token): string {
        // Hash le token pour le nom de fichier (sécurité)
        $hash = hash('sha256', $token);
        return self::TOKENS_DIR . $hash . '.json';
    }
    
    /**
     * Génère un token de synchronisation pour une session
     * 
     * @param string $sessionId ID de la session
     * @return array Token et informations d'expiration
     */
    public function generateSyncToken(string $sessionId): array {
        // Générer un token sécurisé
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        
        // Créer les données du token
        $tokenData = [
            'token' => $token,
            'session_id' => $sessionId,
            'created_at' => time(),
            'expires_at' => time() + self::TOKEN_DURATION,
            'used' => false,
            'used_at' => null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'ip_hash' => hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown')
        ];
        
        // Sauvegarder le token
        $filePath = $this->getTokenFilePath($token);
        file_put_contents($filePath, json_encode($tokenData, JSON_PRETTY_PRINT));
        
        // Retourner les informations
        return [
            'token' => $token,
            'expires_at' => $tokenData['expires_at'],
            'expires_at_iso' => date('c', $tokenData['expires_at']),
            'expires_in_seconds' => self::TOKEN_DURATION,
            'expires_in_human' => '24 heures',
            'sync_url' => $this->getSyncUrl($token)
        ];
    }
    
    /**
     * Valide un token et retourne l'ID de session associé
     * 
     * @param string $token Token à valider
     * @return string|null ID de session ou null si invalide/expiré
     */
    public function validateToken(string $token): ?string {
        // Vérifier le format du token
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        
        $filePath = $this->getTokenFilePath($token);
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        $tokenData = json_decode(file_get_contents($filePath), true);
        
        if (!$tokenData) {
            return null;
        }
        
        // Vérifier l'expiration
        if ($tokenData['expires_at'] < time()) {
            @unlink($filePath);
            return null;
        }
        
        // Vérifier si le token a déjà été utilisé
        if ($tokenData['used']) {
            // On permet la réutilisation dans un délai de 5 minutes après la première utilisation
            // pour gérer les rafraîchissements de page
            if ($tokenData['used_at'] && (time() - $tokenData['used_at']) > 300) {
                return null; // Token déjà utilisé et délai dépassé
            }
        }
        
        return $tokenData['session_id'];
    }
    
    /**
     * Marque un token comme utilisé
     * 
     * @param string $token Token à marquer
     * @return bool Succès
     */
    public function markTokenUsed(string $token): bool {
        $filePath = $this->getTokenFilePath($token);
        
        if (!file_exists($filePath)) {
            return false;
        }
        
        $tokenData = json_decode(file_get_contents($filePath), true);
        
        if (!$tokenData) {
            return false;
        }
        
        $tokenData['used'] = true;
        $tokenData['used_at'] = time();
        $tokenData['used_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $tokenData['used_ip_hash'] = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        
        file_put_contents($filePath, json_encode($tokenData, JSON_PRETTY_PRINT));
        
        return true;
    }
    
    /**
     * Invalide un token (révocation)
     * 
     * @param string $token Token à invalider
     */
    public function invalidateToken(string $token): void {
        $filePath = $this->getTokenFilePath($token);
        
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }
    
    /**
     * Génère l'URL de synchronisation
     * 
     * @param string $token Token de sync
     * @return string URL complète
     */
    public function getSyncUrl(string $token): string {
        $baseUrl = $this->getBaseUrl();
        return $baseUrl . '/index.html?sync=' . urlencode($token);
    }
    
    /**
     * Génère les données pour un QR code
     * 
     * @param string $token Token de sync
     * @return array Données pour QR code
     */
    public function getQRCodeData(string $token): array {
        $url = $this->getSyncUrl($token);
        
        return [
            'url' => $url,
            'qr_api_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($url),
            'token' => $token
        ];
    }
    
    /**
     * Récupère l'URL de base du site
     */
    private function getBaseUrl(): string {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Déterminer le chemin de base
        $scriptPath = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        $basePath = dirname($scriptPath); // Remonter d'un niveau depuis /php
        
        return $protocol . '://' . $host . $basePath;
    }
    
    /**
     * Nettoie les tokens expirés
     * 
     * @return int Nombre de tokens supprimés
     */
    public function cleanupExpiredTokens(): int {
        $deleted = 0;
        $now = time();
        
        foreach (glob(self::TOKENS_DIR . '*.json') as $file) {
            $data = json_decode(file_get_contents($file), true);
            
            if ($data && isset($data['expires_at'])) {
                if ($data['expires_at'] < $now) {
                    @unlink($file);
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
    
    /**
     * Compte le nombre de tokens actifs pour une session
     * 
     * @param string $sessionId ID de session
     * @return int Nombre de tokens actifs
     */
    public function countActiveTokens(string $sessionId): int {
        $count = 0;
        $now = time();
        
        foreach (glob(self::TOKENS_DIR . '*.json') as $file) {
            $data = json_decode(file_get_contents($file), true);
            
            if ($data && 
                $data['session_id'] === $sessionId && 
                $data['expires_at'] > $now &&
                !$data['used']) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Révoque tous les tokens d'une session
     * 
     * @param string $sessionId ID de session
     * @return int Nombre de tokens révoqués
     */
    public function revokeAllTokens(string $sessionId): int {
        $revoked = 0;
        
        foreach (glob(self::TOKENS_DIR . '*.json') as $file) {
            $data = json_decode(file_get_contents($file), true);
            
            if ($data && $data['session_id'] === $sessionId) {
                @unlink($file);
                $revoked++;
            }
        }
        
        return $revoked;
    }
    
    /**
     * Retourne les statistiques des tokens
     */
    public function getStats(): array {
        $stats = [
            'total' => 0,
            'active' => 0,
            'expired' => 0,
            'used' => 0
        ];
        
        $now = time();
        
        foreach (glob(self::TOKENS_DIR . '*.json') as $file) {
            $data = json_decode(file_get_contents($file), true);
            
            if (!$data) continue;
            
            $stats['total']++;
            
            if ($data['expires_at'] < $now) {
                $stats['expired']++;
            } elseif ($data['used']) {
                $stats['used']++;
            } else {
                $stats['active']++;
            }
        }
        
        return $stats;
    }
}

/**
 * API endpoint
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'sync-service.php') {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    $syncService = SyncService::getInstance();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    $response = ['success' => false, 'error' => 'Action non reconnue'];
    
    switch ($action) {
        case 'generate':
            // Générer un token de sync
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $sessionId = $input['session_id'] ?? $_GET['session_id'] ?? '';
            
            if (empty($sessionId)) {
                $response = ['success' => false, 'error' => 'Session ID requis'];
            } else {
                $tokenData = $syncService->generateSyncToken($sessionId);
                $qrData = $syncService->getQRCodeData($tokenData['token']);
                
                $response = [
                    'success' => true,
                    'data' => array_merge($tokenData, ['qr' => $qrData])
                ];
            }
            break;
            
        case 'validate':
            // Valider un token
            $token = $_GET['token'] ?? '';
            
            if (empty($token)) {
                $response = ['success' => false, 'error' => 'Token requis'];
            } else {
                $sessionId = $syncService->validateToken($token);
                
                if ($sessionId) {
                    $syncService->markTokenUsed($token);
                    $response = [
                        'success' => true,
                        'session_id' => $sessionId
                    ];
                } else {
                    $response = [
                        'success' => false,
                        'error' => 'Token invalide ou expiré'
                    ];
                }
            }
            break;
            
        case 'revoke':
            // Révoquer un token
            $token = $_GET['token'] ?? '';
            $syncService->invalidateToken($token);
            $response = ['success' => true];
            break;
            
        case 'stats':
            $response = [
                'success' => true,
                'data' => $syncService->getStats()
            ];
            break;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

