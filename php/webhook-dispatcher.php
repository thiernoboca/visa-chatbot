<?php
/**
 * Webhook Dispatcher - Chatbot Visa CI
 * Système de webhooks génériques avec retry et HMAC
 * 
 * @package VisaChatbot
 * @version 1.0.0
 */

require_once __DIR__ . '/config.php';

class WebhookDispatcher {
    
    /**
     * Événements supportés
     */
    public const EVENT_SESSION_CREATED = 'session.created';
    public const EVENT_SESSION_RESUMED = 'session.resumed';
    public const EVENT_STEP_STARTED = 'step.started';
    public const EVENT_STEP_COMPLETED = 'step.completed';
    public const EVENT_PASSPORT_SCANNED = 'passport.scanned';
    public const EVENT_DOCUMENT_UPLOADED = 'document.uploaded';
    public const EVENT_VALIDATION_ERROR = 'validation.error';
    public const EVENT_APPLICATION_SUBMITTED = 'application.submitted';
    public const EVENT_SESSION_ABANDONED = 'session.abandoned';
    public const EVENT_SESSION_COMPLETED = 'session.completed';
    
    /**
     * Répertoire des logs webhook
     */
    private const LOG_DIR = __DIR__ . '/../logs/';
    
    /**
     * Fichier de queue pour retry
     */
    private const QUEUE_FILE = __DIR__ . '/../data/webhooks_queue.json';
    
    /**
     * Configuration des webhooks
     */
    private array $config = [];
    
    /**
     * Singleton instance
     */
    private static ?WebhookDispatcher $instance = null;
    
    /**
     * Constructeur privé (singleton)
     */
    private function __construct() {
        $this->loadConfig();
        $this->ensureDirectories();
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
     * Charge la configuration des webhooks
     */
    private function loadConfig(): void {
        $configFile = __DIR__ . '/data/webhooks-config.php';
        
        if (file_exists($configFile)) {
            $this->config = require $configFile;
        } else {
            $this->config = [
                'webhooks' => [],
                'retry' => [
                    'max_attempts' => 3,
                    'delay_seconds' => 60,
                    'backoff_multiplier' => 2
                ],
                'timeout' => 10,
                'enabled' => true
            ];
        }
    }
    
    /**
     * S'assure que les répertoires existent
     */
    private function ensureDirectories(): void {
        if (!is_dir(self::LOG_DIR)) {
            mkdir(self::LOG_DIR, 0755, true);
        }
        
        $queueDir = dirname(self::QUEUE_FILE);
        if (!is_dir($queueDir)) {
            mkdir($queueDir, 0755, true);
        }
    }
    
    /**
     * Enregistre un nouveau webhook
     * 
     * @param string $url URL du webhook
     * @param array $events Événements à écouter
     * @param string|null $secret Clé secrète pour HMAC
     * @return string ID du webhook
     */
    public function registerWebhook(string $url, array $events, ?string $secret = null): string {
        $webhookId = 'wh_' . bin2hex(random_bytes(8));
        
        $this->config['webhooks'][] = [
            'id' => $webhookId,
            'url' => $url,
            'events' => $events,
            'secret' => $secret ?? bin2hex(random_bytes(16)),
            'active' => true,
            'created_at' => time()
        ];
        
        $this->saveConfig();
        
        return $webhookId;
    }
    
    /**
     * Désactive un webhook
     */
    public function deactivateWebhook(string $webhookId): bool {
        foreach ($this->config['webhooks'] as &$webhook) {
            if ($webhook['id'] === $webhookId) {
                $webhook['active'] = false;
                $this->saveConfig();
                return true;
            }
        }
        return false;
    }
    
    /**
     * Supprime un webhook
     */
    public function removeWebhook(string $webhookId): bool {
        $initial = count($this->config['webhooks']);
        
        $this->config['webhooks'] = array_filter(
            $this->config['webhooks'],
            fn($wh) => $wh['id'] !== $webhookId
        );
        
        if (count($this->config['webhooks']) < $initial) {
            $this->saveConfig();
            return true;
        }
        
        return false;
    }
    
    /**
     * Sauvegarde la configuration
     */
    private function saveConfig(): void {
        $configFile = __DIR__ . '/data/webhooks-config.php';
        $content = "<?php\n// Auto-generated webhook configuration\nreturn " . var_export($this->config, true) . ";\n";
        file_put_contents($configFile, $content);
    }
    
    /**
     * Dispatch un événement à tous les webhooks abonnés
     * 
     * @param string $event Nom de l'événement
     * @param array $payload Données de l'événement
     * @return array Résultats des appels
     */
    public function dispatch(string $event, array $payload): array {
        if (!($this->config['enabled'] ?? true)) {
            return ['skipped' => true, 'reason' => 'Webhooks disabled'];
        }
        
        $results = [];
        $timestamp = time();
        
        // Ajouter les métadonnées à la payload
        $payload = [
            'event' => $event,
            'timestamp' => $timestamp,
            'timestamp_iso' => date('c', $timestamp),
            'data' => $payload
        ];
        
        foreach ($this->config['webhooks'] as $webhook) {
            // Vérifier si le webhook est actif et écoute cet événement
            if (!($webhook['active'] ?? true)) {
                continue;
            }
            
            if (!empty($webhook['events']) && !in_array($event, $webhook['events'])) {
                continue;
            }
            
            $result = $this->sendWebhook($webhook, $payload);
            $results[$webhook['id']] = $result;
            
            // Si échec, ajouter à la queue de retry
            if (!$result['success']) {
                $this->queueForRetry($webhook, $payload, 1);
            }
            
            // Logger le résultat
            $this->logWebhook($webhook['id'], $event, $result);
        }
        
        return $results;
    }
    
    /**
     * Envoie un webhook
     * 
     * @param array $webhook Configuration du webhook
     * @param array $payload Données à envoyer
     * @return array Résultat de l'appel
     */
    private function sendWebhook(array $webhook, array $payload): array {
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        
        // Calculer la signature HMAC
        $signature = $this->generateSignature($jsonPayload, $webhook['secret'] ?? '');
        
        // Préparer les headers
        $headers = [
            'Content-Type: application/json',
            'X-Webhook-Signature: ' . $signature,
            'X-Webhook-Id: ' . $webhook['id'],
            'X-Webhook-Event: ' . $payload['event'],
            'X-Webhook-Timestamp: ' . $payload['timestamp'],
            'User-Agent: VisaChatbot-Webhook/1.0'
        ];
        
        // Envoyer la requête
        $ch = curl_init($webhook['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['timeout'] ?? 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);
        
        $success = $httpCode >= 200 && $httpCode < 300;
        
        return [
            'success' => $success,
            'http_code' => $httpCode,
            'response' => $success ? substr($response, 0, 500) : null,
            'error' => $error ?: null,
            'duration_ms' => round($totalTime * 1000)
        ];
    }
    
    /**
     * Génère la signature HMAC
     * 
     * @param string $payload Payload JSON
     * @param string $secret Clé secrète
     * @return string Signature
     */
    private function generateSignature(string $payload, string $secret): string {
        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }
    
    /**
     * Ajoute un webhook à la queue de retry
     */
    private function queueForRetry(array $webhook, array $payload, int $attempt): void {
        $maxAttempts = $this->config['retry']['max_attempts'] ?? 3;
        
        if ($attempt >= $maxAttempts) {
            return; // Abandon après max tentatives
        }
        
        $queue = $this->loadQueue();
        
        // Calculer le délai avec backoff exponentiel
        $baseDelay = $this->config['retry']['delay_seconds'] ?? 60;
        $multiplier = $this->config['retry']['backoff_multiplier'] ?? 2;
        $delay = $baseDelay * pow($multiplier, $attempt - 1);
        
        $queue[] = [
            'id' => 'retry_' . bin2hex(random_bytes(8)),
            'webhook' => $webhook,
            'payload' => $payload,
            'attempt' => $attempt + 1,
            'scheduled_at' => time() + $delay,
            'created_at' => time()
        ];
        
        $this->saveQueue($queue);
    }
    
    /**
     * Charge la queue de retry
     */
    private function loadQueue(): array {
        if (!file_exists(self::QUEUE_FILE)) {
            return [];
        }
        
        $content = file_get_contents(self::QUEUE_FILE);
        return json_decode($content, true) ?? [];
    }
    
    /**
     * Sauvegarde la queue de retry
     */
    private function saveQueue(array $queue): void {
        file_put_contents(self::QUEUE_FILE, json_encode($queue, JSON_PRETTY_PRINT));
    }
    
    /**
     * Traite les webhooks en attente de retry
     * 
     * @return int Nombre de webhooks traités
     */
    public function retryFailed(): int {
        $queue = $this->loadQueue();
        $now = time();
        $processed = 0;
        $remaining = [];
        
        foreach ($queue as $item) {
            if ($item['scheduled_at'] > $now) {
                // Pas encore le moment
                $remaining[] = $item;
                continue;
            }
            
            $result = $this->sendWebhook($item['webhook'], $item['payload']);
            $processed++;
            
            if (!$result['success']) {
                // Re-queue si encore des tentatives disponibles
                $this->queueForRetry(
                    $item['webhook'],
                    $item['payload'],
                    $item['attempt']
                );
            }
            
            $this->logWebhook(
                $item['webhook']['id'],
                $item['payload']['event'] . ' (retry #' . $item['attempt'] . ')',
                $result
            );
        }
        
        $this->saveQueue($remaining);
        
        return $processed;
    }
    
    /**
     * Log un appel webhook
     */
    private function logWebhook(string $webhookId, string $event, array $result): void {
        $logFile = self::LOG_DIR . 'webhooks.log';
        
        $entry = json_encode([
            'timestamp' => date('c'),
            'webhook_id' => $webhookId,
            'event' => $event,
            'success' => $result['success'],
            'http_code' => $result['http_code'] ?? null,
            'duration_ms' => $result['duration_ms'] ?? null,
            'error' => $result['error'] ?? null
        ]) . "\n";
        
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Retourne les statistiques des webhooks
     */
    public function getStats(): array {
        $logFile = self::LOG_DIR . 'webhooks.log';
        $stats = [
            'total_webhooks' => count($this->config['webhooks']),
            'active_webhooks' => 0,
            'queue_size' => count($this->loadQueue()),
            'events' => []
        ];
        
        foreach ($this->config['webhooks'] as $webhook) {
            if ($webhook['active'] ?? true) {
                $stats['active_webhooks']++;
            }
        }
        
        // Analyser les logs (dernières 24h)
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $cutoff = time() - 86400;
            
            $success = 0;
            $failed = 0;
            
            foreach (array_slice($lines, -1000) as $line) {
                $entry = json_decode($line, true);
                if (!$entry) continue;
                
                $timestamp = strtotime($entry['timestamp'] ?? '');
                if ($timestamp < $cutoff) continue;
                
                if ($entry['success'] ?? false) {
                    $success++;
                } else {
                    $failed++;
                }
                
                $event = $entry['event'] ?? 'unknown';
                $stats['events'][$event] = ($stats['events'][$event] ?? 0) + 1;
            }
            
            $stats['last_24h'] = [
                'success' => $success,
                'failed' => $failed,
                'total' => $success + $failed,
                'success_rate' => $success + $failed > 0 
                    ? round($success / ($success + $failed) * 100, 2) 
                    : 0
            ];
        }
        
        return $stats;
    }
    
    /**
     * Liste tous les webhooks configurés
     */
    public function listWebhooks(): array {
        return array_map(function($wh) {
            return [
                'id' => $wh['id'],
                'url' => $wh['url'],
                'events' => $wh['events'],
                'active' => $wh['active'] ?? true,
                'created_at' => $wh['created_at'] ?? null
            ];
        }, $this->config['webhooks']);
    }
    
    /**
     * Vérifie une signature de webhook (pour les endpoints qui reçoivent des webhooks)
     * 
     * @param string $payload Payload brut
     * @param string $signature Signature reçue
     * @param string $secret Clé secrète
     * @return bool Signature valide
     */
    public static function verifySignature(string $payload, string $signature, string $secret): bool {
        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }
}

/**
 * Helper function pour dispatch rapide
 */
function dispatchWebhook(string $event, array $payload): array {
    return WebhookDispatcher::getInstance()->dispatch($event, $payload);
}

