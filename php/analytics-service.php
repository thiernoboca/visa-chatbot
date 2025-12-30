<?php
/**
 * Analytics Service - Chatbot Visa CI
 * Système de tracking et métriques
 * 
 * @package VisaChatbot
 * @version 1.0.0
 */

require_once __DIR__ . '/config.php';

class AnalyticsService {
    
    /**
     * Répertoire des données analytics
     */
    private const DATA_DIR = __DIR__ . '/../data/analytics/';
    
    /**
     * Types d'événements
     */
    public const EVENT_SESSION_START = 'session.start';
    public const EVENT_SESSION_END = 'session.end';
    public const EVENT_STEP_START = 'step.start';
    public const EVENT_STEP_COMPLETE = 'step.complete';
    public const EVENT_STEP_ABANDON = 'step.abandon';
    public const EVENT_PASSPORT_SCAN = 'passport.scan';
    public const EVENT_PASSPORT_SCAN_SUCCESS = 'passport.scan.success';
    public const EVENT_PASSPORT_SCAN_FAILURE = 'passport.scan.failure';
    public const EVENT_VALIDATION_ERROR = 'validation.error';
    public const EVENT_USER_INTERACTION = 'user.interaction';
    public const EVENT_PAGE_VIEW = 'page.view';
    
    /**
     * Singleton instance
     */
    private static ?AnalyticsService $instance = null;
    
    /**
     * Date courante (pour partitionnement des fichiers)
     */
    private string $currentDate;
    
    /**
     * Constructeur privé (singleton)
     */
    private function __construct() {
        $this->ensureDataDirectory();
        $this->currentDate = date('Y-m-d');
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
     * S'assure que le répertoire de données existe
     */
    private function ensureDataDirectory(): void {
        if (!is_dir(self::DATA_DIR)) {
            mkdir(self::DATA_DIR, 0755, true);
        }
    }
    
    /**
     * Chemin du fichier d'événements du jour
     */
    private function getEventsFilePath(?string $date = null): string {
        $date = $date ?? $this->currentDate;
        return self::DATA_DIR . "events_{$date}.jsonl";
    }
    
    /**
     * Chemin du fichier de métriques agrégées
     */
    private function getMetricsFilePath(): string {
        return self::DATA_DIR . 'metrics_aggregate.json';
    }
    
    /**
     * Track un événement
     * 
     * @param string $sessionId ID de session
     * @param string $event Type d'événement
     * @param array $data Données additionnelles
     */
    public function trackEvent(string $sessionId, string $event, array $data = []): void {
        $entry = [
            'timestamp' => microtime(true),
            'timestamp_iso' => date('c'),
            'session_id' => $sessionId,
            'event' => $event,
            'data' => $data,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'ip_hash' => hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown') // Anonymisé
        ];
        
        $filePath = $this->getEventsFilePath();
        file_put_contents($filePath, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
        
        // Mettre à jour les métriques agrégées en temps réel
        $this->updateAggregateMetrics($event, $data);
    }
    
    /**
     * Track le début d'une étape
     */
    public function trackStepStart(string $sessionId, string $step): void {
        $this->trackEvent($sessionId, self::EVENT_STEP_START, [
            'step' => $step,
            'start_time' => microtime(true)
        ]);
    }
    
    /**
     * Track la complétion d'une étape
     */
    public function trackStepComplete(string $sessionId, string $step, float $durationSeconds): void {
        $this->trackEvent($sessionId, self::EVENT_STEP_COMPLETE, [
            'step' => $step,
            'duration_seconds' => round($durationSeconds, 2)
        ]);
    }
    
    /**
     * Track un abandon d'étape
     */
    public function trackStepAbandon(string $sessionId, string $step, string $reason = 'unknown'): void {
        $this->trackEvent($sessionId, self::EVENT_STEP_ABANDON, [
            'step' => $step,
            'reason' => $reason
        ]);
    }
    
    /**
     * Track une erreur de validation
     */
    public function trackValidationError(string $sessionId, string $field, string $errorType, string $value = ''): void {
        $this->trackEvent($sessionId, self::EVENT_VALIDATION_ERROR, [
            'field' => $field,
            'error_type' => $errorType,
            'value_length' => strlen($value) // Ne pas stocker la valeur pour la confidentialité
        ]);
    }
    
    /**
     * Track un scan de passeport
     */
    public function trackPassportScan(string $sessionId, bool $success, ?string $passportType = null, float $processingTime = 0): void {
        $event = $success ? self::EVENT_PASSPORT_SCAN_SUCCESS : self::EVENT_PASSPORT_SCAN_FAILURE;
        
        $this->trackEvent($sessionId, $event, [
            'passport_type' => $passportType,
            'processing_time_ms' => round($processingTime * 1000)
        ]);
    }
    
    /**
     * Track une interaction utilisateur
     */
    public function trackInteraction(string $sessionId, string $element, string $action): void {
        $this->trackEvent($sessionId, self::EVENT_USER_INTERACTION, [
            'element' => $element,
            'action' => $action
        ]);
    }
    
    /**
     * Met à jour les métriques agrégées
     */
    private function updateAggregateMetrics(string $event, array $data): void {
        $metricsPath = $this->getMetricsFilePath();
        
        // Charger les métriques existantes
        $metrics = [];
        if (file_exists($metricsPath)) {
            $metrics = json_decode(file_get_contents($metricsPath), true) ?? [];
        }
        
        // Initialiser la structure si nécessaire
        if (empty($metrics)) {
            $metrics = [
                'total_sessions' => 0,
                'completed_sessions' => 0,
                'abandoned_sessions' => 0,
                'steps' => [],
                'passport_scans' => ['success' => 0, 'failure' => 0],
                'validation_errors' => [],
                'updated_at' => null
            ];
        }
        
        // Mettre à jour selon l'événement
        switch ($event) {
            case self::EVENT_SESSION_START:
                $metrics['total_sessions']++;
                break;
                
            case self::EVENT_SESSION_END:
                if ($data['completed'] ?? false) {
                    $metrics['completed_sessions']++;
                }
                break;
                
            case self::EVENT_STEP_COMPLETE:
                $step = $data['step'] ?? 'unknown';
                if (!isset($metrics['steps'][$step])) {
                    $metrics['steps'][$step] = [
                        'completions' => 0,
                        'abandons' => 0,
                        'total_duration' => 0,
                        'count_duration' => 0
                    ];
                }
                $metrics['steps'][$step]['completions']++;
                
                if (isset($data['duration_seconds'])) {
                    $metrics['steps'][$step]['total_duration'] += $data['duration_seconds'];
                    $metrics['steps'][$step]['count_duration']++;
                }
                break;
                
            case self::EVENT_STEP_ABANDON:
                $step = $data['step'] ?? 'unknown';
                if (!isset($metrics['steps'][$step])) {
                    $metrics['steps'][$step] = [
                        'completions' => 0,
                        'abandons' => 0,
                        'total_duration' => 0,
                        'count_duration' => 0
                    ];
                }
                $metrics['steps'][$step]['abandons']++;
                $metrics['abandoned_sessions']++;
                break;
                
            case self::EVENT_PASSPORT_SCAN_SUCCESS:
                $metrics['passport_scans']['success']++;
                break;
                
            case self::EVENT_PASSPORT_SCAN_FAILURE:
                $metrics['passport_scans']['failure']++;
                break;
                
            case self::EVENT_VALIDATION_ERROR:
                $field = $data['field'] ?? 'unknown';
                $errorType = $data['error_type'] ?? 'unknown';
                $key = "{$field}:{$errorType}";
                $metrics['validation_errors'][$key] = ($metrics['validation_errors'][$key] ?? 0) + 1;
                break;
        }
        
        $metrics['updated_at'] = date('c');
        
        // Sauvegarder
        file_put_contents($metricsPath, json_encode($metrics, JSON_PRETTY_PRINT), LOCK_EX);
    }
    
    /**
     * Retourne les métriques d'une étape
     */
    public function getStepMetrics(string $step): array {
        $metrics = $this->getAggregateMetrics();
        
        $stepData = $metrics['steps'][$step] ?? [
            'completions' => 0,
            'abandons' => 0,
            'total_duration' => 0,
            'count_duration' => 0
        ];
        
        $total = $stepData['completions'] + $stepData['abandons'];
        
        return [
            'step' => $step,
            'completions' => $stepData['completions'],
            'abandons' => $stepData['abandons'],
            'total_attempts' => $total,
            'completion_rate' => $total > 0 ? round($stepData['completions'] / $total * 100, 2) : 0,
            'abandonment_rate' => $total > 0 ? round($stepData['abandons'] / $total * 100, 2) : 0,
            'avg_duration_seconds' => $stepData['count_duration'] > 0 
                ? round($stepData['total_duration'] / $stepData['count_duration'], 2) 
                : 0
        ];
    }
    
    /**
     * Retourne le rapport du funnel
     */
    public function getFunnelReport(?string $startDate = null, ?string $endDate = null): array {
        $startDate = $startDate ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $endDate ?? date('Y-m-d');
        
        $events = $this->getEventsInRange($startDate, $endDate);
        
        // Compter les sessions par étape atteinte
        $sessionSteps = [];
        
        foreach ($events as $event) {
            $sessionId = $event['session_id'] ?? '';
            $eventType = $event['event'] ?? '';
            
            if ($eventType === self::EVENT_STEP_COMPLETE) {
                $step = $event['data']['step'] ?? '';
                if (!isset($sessionSteps[$sessionId])) {
                    $sessionSteps[$sessionId] = [];
                }
                $sessionSteps[$sessionId][$step] = true;
            }
        }
        
        // Calculer le funnel
        $funnel = [];
        foreach (WORKFLOW_STEPS as $index => $step) {
            $count = 0;
            foreach ($sessionSteps as $steps) {
                if (isset($steps[$step])) {
                    $count++;
                }
            }
            $funnel[$step] = [
                'step' => $step,
                'index' => $index,
                'sessions_reached' => $count,
                'percentage' => count($sessionSteps) > 0 
                    ? round($count / count($sessionSteps) * 100, 2) 
                    : 0
            ];
        }
        
        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ],
            'total_sessions' => count($sessionSteps),
            'funnel' => $funnel
        ];
    }
    
    /**
     * Retourne le taux d'abandon global
     */
    public function getAbandonmentRate(): float {
        $metrics = $this->getAggregateMetrics();
        
        $total = $metrics['total_sessions'] ?? 0;
        $completed = $metrics['completed_sessions'] ?? 0;
        
        if ($total === 0) {
            return 0.0;
        }
        
        return round(($total - $completed) / $total * 100, 2);
    }
    
    /**
     * Retourne les métriques agrégées
     */
    public function getAggregateMetrics(): array {
        $metricsPath = $this->getMetricsFilePath();
        
        if (!file_exists($metricsPath)) {
            return [
                'total_sessions' => 0,
                'completed_sessions' => 0,
                'abandoned_sessions' => 0,
                'steps' => [],
                'passport_scans' => ['success' => 0, 'failure' => 0],
                'validation_errors' => [],
                'updated_at' => null
            ];
        }
        
        return json_decode(file_get_contents($metricsPath), true) ?? [];
    }
    
    /**
     * Retourne les événements dans une plage de dates
     */
    private function getEventsInRange(string $startDate, string $endDate): array {
        $events = [];
        $current = new DateTime($startDate);
        $end = new DateTime($endDate);
        
        while ($current <= $end) {
            $date = $current->format('Y-m-d');
            $filePath = $this->getEventsFilePath($date);
            
            if (file_exists($filePath)) {
                $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $event = json_decode($line, true);
                    if ($event) {
                        $events[] = $event;
                    }
                }
            }
            
            $current->modify('+1 day');
        }
        
        return $events;
    }
    
    /**
     * Retourne le taux de succès des scans de passeport
     */
    public function getPassportScanSuccessRate(): float {
        $metrics = $this->getAggregateMetrics();
        $scans = $metrics['passport_scans'] ?? ['success' => 0, 'failure' => 0];
        
        $total = $scans['success'] + $scans['failure'];
        
        if ($total === 0) {
            return 0.0;
        }
        
        return round($scans['success'] / $total * 100, 2);
    }
    
    /**
     * Retourne les erreurs de validation les plus fréquentes
     */
    public function getTopValidationErrors(int $limit = 10): array {
        $metrics = $this->getAggregateMetrics();
        $errors = $metrics['validation_errors'] ?? [];
        
        arsort($errors);
        
        $result = [];
        $i = 0;
        foreach ($errors as $key => $count) {
            if ($i >= $limit) break;
            
            list($field, $errorType) = explode(':', $key, 2) + [1 => 'unknown'];
            $result[] = [
                'field' => $field,
                'error_type' => $errorType,
                'count' => $count
            ];
            $i++;
        }
        
        return $result;
    }
    
    /**
     * Retourne un résumé complet pour le dashboard
     */
    public function getDashboardSummary(): array {
        $metrics = $this->getAggregateMetrics();
        
        $totalSessions = $metrics['total_sessions'] ?? 0;
        $completedSessions = $metrics['completed_sessions'] ?? 0;
        $abandonedSessions = $metrics['abandoned_sessions'] ?? 0;
        
        return [
            'overview' => [
                'total_sessions' => $totalSessions,
                'completed_sessions' => $completedSessions,
                'abandoned_sessions' => $abandonedSessions,
                'completion_rate' => $totalSessions > 0 
                    ? round($completedSessions / $totalSessions * 100, 2) 
                    : 0,
                'abandonment_rate' => $this->getAbandonmentRate()
            ],
            'passport_scans' => [
                'total' => ($metrics['passport_scans']['success'] ?? 0) + ($metrics['passport_scans']['failure'] ?? 0),
                'success' => $metrics['passport_scans']['success'] ?? 0,
                'failure' => $metrics['passport_scans']['failure'] ?? 0,
                'success_rate' => $this->getPassportScanSuccessRate()
            ],
            'top_validation_errors' => $this->getTopValidationErrors(5),
            'steps_performance' => array_map(
                fn($step) => $this->getStepMetrics($step),
                WORKFLOW_STEPS
            ),
            'updated_at' => $metrics['updated_at'] ?? null
        ];
    }
    
    /**
     * Nettoie les anciennes données (rétention 90 jours)
     */
    public function cleanupOldData(int $retentionDays = 90): int {
        $deleted = 0;
        $cutoffDate = date('Y-m-d', strtotime("-{$retentionDays} days"));
        
        foreach (glob(self::DATA_DIR . 'events_*.jsonl') as $file) {
            preg_match('/events_(\d{4}-\d{2}-\d{2})\.jsonl$/', $file, $matches);
            if (isset($matches[1]) && $matches[1] < $cutoffDate) {
                @unlink($file);
                $deleted++;
            }
        }
        
        return $deleted;
    }
}

/**
 * API endpoint
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'analytics-service.php') {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    $analytics = AnalyticsService::getInstance();
    $action = $_GET['action'] ?? $_POST['action'] ?? 'summary';
    
    $response = ['success' => false, 'error' => 'Action non reconnue'];
    
    switch ($action) {
        case 'summary':
            $response = ['success' => true, 'data' => $analytics->getDashboardSummary()];
            break;
            
        case 'funnel':
            $startDate = $_GET['start'] ?? null;
            $endDate = $_GET['end'] ?? null;
            $response = ['success' => true, 'data' => $analytics->getFunnelReport($startDate, $endDate)];
            break;
            
        case 'step':
            $step = $_GET['step'] ?? 'welcome';
            $response = ['success' => true, 'data' => $analytics->getStepMetrics($step)];
            break;
            
        case 'track':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true) ?? [];
                $analytics->trackEvent(
                    $input['session_id'] ?? 'unknown',
                    $input['event'] ?? 'unknown',
                    $input['data'] ?? []
                );
                $response = ['success' => true];
            }
            break;
            
        case 'errors':
            $limit = (int)($_GET['limit'] ?? 10);
            $response = ['success' => true, 'data' => $analytics->getTopValidationErrors($limit)];
            break;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

