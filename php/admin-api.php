<?php
/**
 * API Admin - Chatbot Visa CI
 * Endpoints REST pour le dashboard d'administration
 * 
 * Inclut les endpoints Layer 3 (Claude Audit) pour:
 * - Visualiser les rapports d'audit
 * - Gérer les alertes de sécurité
 * - Statistiques Triple Layer
 * 
 * @package VisaChatbot
 * @version 2.0.0 - Triple Layer Architecture
 */

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');

// Configuration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/claude-audit-manager.php';

// Dossiers de données
define('SESSIONS_DIR', __DIR__ . '/../data/sessions');
define('DRAFTS_DIR', __DIR__ . '/../data/drafts');
define('DEMANDS_DIR', __DIR__ . '/../data/demands');
define('ALERTS_DIR', __DIR__ . '/../data/alerts');
define('AUDIT_REPORTS_DIR', __DIR__ . '/../data/audit_reports');

// Créer les dossiers si nécessaire
foreach ([SESSIONS_DIR, DRAFTS_DIR, DEMANDS_DIR, ALERTS_DIR, AUDIT_REPORTS_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

/**
 * Classe principale de l'API Admin
 */
class AdminAPI {
    
    /**
     * Router principal
     */
    public function handleRequest(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
        try {
            switch ($method) {
                case 'GET':
                    $this->handleGet($action);
                    break;
                case 'POST':
                    $this->handlePost($action);
                    break;
                default:
                    $this->error('Method not allowed', 405);
            }
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Gestion des requêtes GET
     */
    private function handleGet(string $action): void {
        switch ($action) {
            case 'stats':
                $this->getStats();
                break;
            case 'list':
                $this->listApplications();
                break;
            case 'detail':
                $this->getApplicationDetail();
                break;
            case 'alerts':
                $this->getAlerts();
                break;
            case 'download_receipt':
                $this->downloadReceipt();
                break;
            // === Layer 3 Audit Endpoints ===
            case 'audit_stats':
                $this->getAuditStats();
                break;
            case 'audit_reports':
                $this->getAuditReports();
                break;
            case 'session_audits':
                $this->getSessionAudits();
                break;
            case 'claude_alerts':
                $this->getClaudeAlerts();
                break;
            case 'triple_layer_status':
                $this->getTripleLayerStatus();
                break;
            default:
                $this->error('Unknown action', 400);
        }
    }
    
    /**
     * Gestion des requêtes POST
     */
    private function handlePost(string $action): void {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $action = $action ?: ($input['action'] ?? '');
        
        switch ($action) {
            case 'update_status':
                $this->updateApplicationStatus($input);
                break;
            case 'dismiss_alert':
                $this->dismissAlert($input);
                break;
            case 'add_note':
                $this->addNote($input);
                break;
            // === Layer 3 Audit Endpoints ===
            case 'acknowledge_claude_alert':
                $this->acknowledgeClaudeAlert($input);
                break;
            case 'process_pending_validations':
                $this->processPendingValidations($input);
                break;
            case 'cleanup_audits':
                $this->cleanupAudits($input);
                break;
            default:
                $this->error('Unknown action', 400);
        }
    }
    
    /**
     * Statistiques du dashboard (enrichies avec Triple Layer)
     */
    private function getStats(): void {
        $stats = [
            'total' => 0,
            'pending' => 0,
            'processing' => 0,
            'approved' => 0,
            'rejected' => 0,
            'priority' => 0,
            'alerts' => 0,
            'today' => 0,
            'this_week' => 0,
            'triple_layer' => [
                'pending_validations' => 0,
                'claude_alerts' => 0,
                'total_audits' => 0
            ]
        ];
        
        $applications = $this->loadAllApplications();
        $stats['total'] = count($applications);
        
        $today = date('Y-m-d');
        $weekAgo = date('Y-m-d', strtotime('-7 days'));
        
        foreach ($applications as $app) {
            // Count by status
            $status = $app['status'] ?? 'pending';
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
            
            // Count priority
            if (($app['workflow_type'] ?? $app['workflow'] ?? '') === 'PRIORITY') {
                $stats['priority']++;
            }
            
            // Count today
            $createdAt = $app['created_at'] ?? '';
            if (strpos($createdAt, $today) === 0) {
                $stats['today']++;
            }
            
            // Count this week
            if ($createdAt >= $weekAgo) {
                $stats['this_week']++;
            }
        }
        
        // Count alerts
        $stats['alerts'] = count($this->loadAlerts());
        
        // Triple Layer stats
        try {
            $auditManager = new ClaudeAuditManager();
            $auditStats = $auditManager->getAuditStats();
            $stats['triple_layer'] = [
                'pending_validations' => $auditStats['pending_validations'] ?? 0,
                'claude_alerts' => $auditStats['unacknowledged_alerts'] ?? 0,
                'total_audits' => $auditStats['total_audits'] ?? 0,
                'by_recommendation' => $auditStats['by_recommendation'] ?? []
            ];
        } catch (Exception $e) {
            // Keep default values if audit manager fails
        }
        
        $this->success($stats);
    }
    
    /**
     * Liste des demandes avec filtres et pagination
     */
    private function listApplications(): void {
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(1, intval($_GET['limit'] ?? 10)));
        $status = $_GET['status'] ?? '';
        $workflow = $_GET['workflow'] ?? '';
        $search = $_GET['search'] ?? '';
        
        $applications = $this->loadAllApplications();
        
        // Filter by status
        if ($status) {
            $applications = array_filter($applications, fn($app) => ($app['status'] ?? 'pending') === $status);
        }
        
        // Filter by workflow
        if ($workflow) {
            $applications = array_filter($applications, fn($app) => ($app['workflow_type'] ?? $app['workflow'] ?? 'STANDARD') === $workflow);
        }
        
        // Filter by search
        if ($search) {
            $search = strtolower($search);
            $applications = array_filter($applications, function($app) use ($search) {
                $searchable = strtolower(implode(' ', [
                    $app['reference'] ?? $app['id'] ?? '',
                    $app['name'] ?? '',
                    $app['passport_number'] ?? '',
                    $app['email'] ?? ''
                ]));
                return strpos($searchable, $search) !== false;
            });
        }
        
        // Sort by date (newest first)
        usort($applications, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        
        // Paginate
        $total = count($applications);
        $totalPages = ceil($total / $limit);
        $offset = ($page - 1) * $limit;
        $applications = array_slice(array_values($applications), $offset, $limit);
        
        // Format for frontend
        $formatted = array_map(function($app) {
            return $this->formatApplicationForList($app);
        }, $applications);
        
        $this->success($formatted, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => $totalPages
        ]);
    }
    
    /**
     * Détail d'une demande
     */
    private function getApplicationDetail(): void {
        $id = $_GET['id'] ?? '';
        
        if (empty($id)) {
            $this->error('ID required', 400);
            return;
        }
        
        $application = $this->loadApplication($id);
        
        if (!$application) {
            $this->error('Application not found', 404);
            return;
        }
        
        $this->success($this->formatApplicationForDetail($application));
    }
    
    /**
     * Liste des alertes Claude
     */
    private function getAlerts(): void {
        $alerts = $this->loadAlerts();
        
        // Sort by severity and date
        usort($alerts, function($a, $b) {
            $severityOrder = ['high' => 0, 'medium' => 1, 'low' => 2];
            $sa = $severityOrder[$a['severity'] ?? 'low'] ?? 2;
            $sb = $severityOrder[$b['severity'] ?? 'low'] ?? 2;
            
            if ($sa !== $sb) return $sa - $sb;
            return strcmp($b['detected_at'] ?? '', $a['detected_at'] ?? '');
        });
        
        $this->success($alerts);
    }
    
    /**
     * Mise à jour du statut d'une demande
     */
    private function updateApplicationStatus(array $input): void {
        $id = $input['id'] ?? '';
        $status = $input['status'] ?? '';
        $reason = $input['reason'] ?? '';
        
        if (empty($id) || empty($status)) {
            $this->error('ID and status required', 400);
            return;
        }
        
        $validStatuses = ['pending', 'processing', 'approved', 'rejected'];
        if (!in_array($status, $validStatuses)) {
            $this->error('Invalid status', 400);
            return;
        }
        
        $application = $this->loadApplication($id);
        
        if (!$application) {
            $this->error('Application not found', 404);
            return;
        }
        
        // Update status
        $application['status'] = $status;
        $application['status_updated_at'] = date('c');
        $application['status_updated_by'] = 'admin'; // TODO: Get actual admin user
        
        if ($status === 'rejected' && $reason) {
            $application['rejection_reason'] = $reason;
        }
        
        // Add to history
        $application['status_history'][] = [
            'status' => $status,
            'timestamp' => date('c'),
            'by' => 'admin',
            'reason' => $reason
        ];
        
        // Save
        $this->saveApplication($id, $application);
        
        $this->success(['message' => 'Status updated successfully']);
    }
    
    /**
     * Ignorer une alerte
     */
    private function dismissAlert(array $input): void {
        $id = $input['id'] ?? '';
        
        if (empty($id)) {
            $this->error('ID required', 400);
            return;
        }
        
        $alertFile = ALERTS_DIR . "/{$id}.json";
        
        if (file_exists($alertFile)) {
            // Mark as dismissed instead of deleting
            $alert = json_decode(file_get_contents($alertFile), true);
            $alert['dismissed'] = true;
            $alert['dismissed_at'] = date('c');
            file_put_contents($alertFile, json_encode($alert, JSON_PRETTY_PRINT));
        }
        
        $this->success(['message' => 'Alert dismissed']);
    }
    
    /**
     * Ajouter une note à une demande
     */
    private function addNote(array $input): void {
        $id = $input['id'] ?? '';
        $note = $input['note'] ?? '';
        
        if (empty($id) || empty($note)) {
            $this->error('ID and note required', 400);
            return;
        }
        
        $application = $this->loadApplication($id);
        
        if (!$application) {
            $this->error('Application not found', 404);
            return;
        }
        
        // Add note
        $application['notes'][] = [
            'content' => $note,
            'timestamp' => date('c'),
            'by' => 'admin'
        ];
        
        $this->saveApplication($id, $application);
        
        $this->success(['message' => 'Note added']);
    }
    
    /**
     * Télécharger un récépissé
     */
    private function downloadReceipt(): void {
        $id = $_GET['id'] ?? '';
        
        if (empty($id)) {
            $this->error('ID required', 400);
            return;
        }
        
        require_once __DIR__ . '/pdf-generator.php';
        require_once __DIR__ . '/qr-generator.php';
        
        $application = $this->loadApplication($id);
        
        if (!$application) {
            $this->error('Application not found', 404);
            return;
        }
        
        // Generate QR code
        $qrGenerator = new QRGenerator();
        $qrResult = $qrGenerator->generateVisaQR(
            $application['reference'] ?? $id,
            ['passport_number' => $application['passport_number'] ?? '', 'workflow_type' => $application['workflow_type'] ?? 'STANDARD']
        );
        
        // Generate PDF
        $pdfGenerator = new PDFGenerator();
        $pdfResult = $pdfGenerator->generateReceipt($application, $qrResult['filepath'] ?? null);
        
        if ($pdfResult['success'] && file_exists($pdfResult['filepath'])) {
            // Redirect to the file
            header('Location: ../data/receipts/' . basename($pdfResult['filepath']));
            exit;
        }
        
        $this->error('Failed to generate receipt', 500);
    }
    
    // === Layer 3 Audit Methods ===
    
    /**
     * Statistiques d'audit Claude (Layer 3)
     */
    private function getAuditStats(): void {
        try {
            $auditManager = new ClaudeAuditManager();
            $stats = $auditManager->getAuditStats();
            $this->success($stats);
        } catch (Exception $e) {
            $this->error('Failed to get audit stats: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Liste des rapports d'audit récents
     */
    private function getAuditReports(): void {
        $limit = intval($_GET['limit'] ?? 50);
        $reports = [];
        
        $files = glob(AUDIT_REPORTS_DIR . '/*.json');
        // Sort by modification time (newest first)
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
        
        foreach (array_slice($files, 0, $limit) as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $reports[] = [
                    'id' => basename($file, '.json'),
                    'session_id' => $data['session_id'] ?? '',
                    'validation_type' => $data['validation_type'] ?? '',
                    'timestamp' => $data['timestamp'] ?? '',
                    'valid' => $data['result']['valid'] ?? true,
                    'confidence' => $data['result']['confidence_score'] ?? 0,
                    'recommendation' => $data['result']['recommendation'] ?? 'approved',
                    'warnings_count' => count($data['result']['warnings'] ?? [])
                ];
            }
        }
        
        $this->success($reports);
    }
    
    /**
     * Rapports d'audit pour une session spécifique
     */
    private function getSessionAudits(): void {
        $sessionId = $_GET['session_id'] ?? '';
        
        if (empty($sessionId)) {
            $this->error('Session ID required', 400);
            return;
        }
        
        try {
            $auditManager = new ClaudeAuditManager();
            $reports = $auditManager->getSessionAuditReports($sessionId);
            $this->success($reports);
        } catch (Exception $e) {
            $this->error('Failed to get session audits: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Alertes Claude non acquittées
     */
    private function getClaudeAlerts(): void {
        try {
            $auditManager = new ClaudeAuditManager();
            $alerts = $auditManager->getUnacknowledgedAlerts();
            $this->success($alerts);
        } catch (Exception $e) {
            $this->error('Failed to get Claude alerts: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Status du système Triple Layer
     */
    private function getTripleLayerStatus(): void {
        // Check Gemini availability
        $geminiAvailable = false;
        $geminiApiKey = getenv('GEMINI_API_KEY');
        if (!empty($geminiApiKey)) {
            $geminiAvailable = true;
        }
        
        // Check Claude availability
        $claudeAvailable = false;
        $claudeApiKey = defined('CLAUDE_API_KEY') ? CLAUDE_API_KEY : getenv('CLAUDE_API_KEY');
        if (!empty($claudeApiKey)) {
            $claudeAvailable = true;
        }
        
        // Check Google Vision
        $visionAvailable = defined('GOOGLE_APPLICATION_CREDENTIALS') || getenv('GOOGLE_APPLICATION_CREDENTIALS');
        
        // Count pending validations
        $pendingDir = dirname(__DIR__) . '/data/pending_validations';
        $pendingCount = is_dir($pendingDir) ? count(glob($pendingDir . '/*.json')) : 0;
        
        // Get audit stats
        $auditStats = [];
        try {
            $auditManager = new ClaudeAuditManager();
            $auditStats = $auditManager->getAuditStats();
        } catch (Exception $e) {
            $auditStats = ['error' => $e->getMessage()];
        }
        
        $this->success([
            'layers' => [
                'layer1' => [
                    'name' => 'Google Vision (OCR)',
                    'status' => $visionAvailable ? 'active' : 'unconfigured',
                    'type' => 'synchronous'
                ],
                'layer2' => [
                    'name' => 'Gemini Flash (Conversation)',
                    'status' => $geminiAvailable ? 'active' : 'unconfigured',
                    'fallback' => 'Claude',
                    'type' => 'synchronous'
                ],
                'layer3' => [
                    'name' => 'Claude Sonnet (Supervisor)',
                    'status' => $claudeAvailable ? 'active' : 'unconfigured',
                    'type' => 'asynchronous'
                ]
            ],
            'pending_validations' => $pendingCount,
            'audit_stats' => $auditStats,
            'timestamp' => date('c')
        ]);
    }
    
    /**
     * Acquitte une alerte Claude
     */
    private function acknowledgeClaudeAlert(array $input): void {
        $alertId = $input['alert_id'] ?? '';
        $acknowledgedBy = $input['acknowledged_by'] ?? 'admin';
        
        if (empty($alertId)) {
            $this->error('Alert ID required', 400);
            return;
        }
        
        try {
            $auditManager = new ClaudeAuditManager();
            $result = $auditManager->acknowledgeAlert($alertId, $acknowledgedBy);
            
            if ($result) {
                $this->success(['message' => 'Alert acknowledged']);
            } else {
                $this->error('Alert not found', 404);
            }
        } catch (Exception $e) {
            $this->error('Failed to acknowledge alert: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Traite les validations Claude en attente (manuellement)
     */
    private function processPendingValidations(array $input): void {
        $limit = intval($input['limit'] ?? 10);
        
        try {
            $auditManager = new ClaudeAuditManager(['debug' => true]);
            $results = $auditManager->processAllPending($limit);
            $this->success($results);
        } catch (Exception $e) {
            $this->error('Failed to process validations: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Nettoie les anciens audits
     */
    private function cleanupAudits(array $input): void {
        $maxAgeDays = intval($input['max_age_days'] ?? 30);
        
        try {
            $auditManager = new ClaudeAuditManager();
            $results = $auditManager->cleanup($maxAgeDays);
            $this->success($results);
        } catch (Exception $e) {
            $this->error('Failed to cleanup audits: ' . $e->getMessage(), 500);
        }
    }
    
    // === Data Loading ===
    
    /**
     * Charge toutes les demandes
     */
    private function loadAllApplications(): array {
        $applications = [];
        
        // Load from sessions
        foreach (glob(SESSIONS_DIR . '/*.json') as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && !empty($data['collected_data'])) {
                $applications[] = $this->normalizeApplicationData($data, basename($file, '.json'));
            }
        }
        
        // Load from drafts
        foreach (glob(DRAFTS_DIR . '/*.json') as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $applications[] = $this->normalizeApplicationData($data, basename($file, '.json'));
            }
        }
        
        // Load from demands (completed applications)
        foreach (glob(DEMANDS_DIR . '/*.json') as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $applications[] = $this->normalizeApplicationData($data, basename($file, '.json'));
            }
        }
        
        return $applications;
    }
    
    /**
     * Charge une demande spécifique
     */
    private function loadApplication(string $id): ?array {
        // Try demands first
        $demandFile = DEMANDS_DIR . "/{$id}.json";
        if (file_exists($demandFile)) {
            $data = json_decode(file_get_contents($demandFile), true);
            return $data ? $this->normalizeApplicationData($data, $id) : null;
        }
        
        // Try drafts
        $draftFile = DRAFTS_DIR . "/{$id}.json";
        if (file_exists($draftFile)) {
            $data = json_decode(file_get_contents($draftFile), true);
            return $data ? $this->normalizeApplicationData($data, $id) : null;
        }
        
        // Try sessions
        $sessionFile = SESSIONS_DIR . "/{$id}.json";
        if (file_exists($sessionFile)) {
            $data = json_decode(file_get_contents($sessionFile), true);
            return $data ? $this->normalizeApplicationData($data, $id) : null;
        }
        
        // Search in all files
        foreach (glob(DEMANDS_DIR . '/*.json') as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && ($data['reference'] ?? $data['id'] ?? '') === $id) {
                return $this->normalizeApplicationData($data, basename($file, '.json'));
            }
        }
        
        return null;
    }
    
    /**
     * Sauvegarde une demande
     */
    private function saveApplication(string $id, array $data): void {
        // Save to demands folder
        $filepath = DEMANDS_DIR . "/{$id}.json";
        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * Charge les alertes
     */
    private function loadAlerts(): array {
        $alerts = [];
        
        foreach (glob(ALERTS_DIR . '/*.json') as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && empty($data['dismissed'])) {
                $alerts[] = $data;
            }
        }
        
        return $alerts;
    }
    
    // === Data Formatting ===
    
    /**
     * Normalise les données d'une demande
     */
    private function normalizeApplicationData(array $data, string $id): array {
        $collected = $data['collected_data'] ?? $data;
        $passport = $collected['passport'] ?? [];
        $contact = $collected['contact'] ?? [];
        $trip = $collected['trip'] ?? [];
        
        return [
            'id' => $id,
            'reference' => $data['reference'] ?? $data['reference_number'] ?? 'CIV-' . strtoupper(substr($id, 0, 10)),
            'name' => trim(($passport['given_names'] ?? $collected['given_names'] ?? '') . ' ' . ($passport['surname'] ?? $collected['surname'] ?? '')),
            'nationality' => $passport['nationality'] ?? $collected['nationality'] ?? $collected['country_name'] ?? '',
            'passport_number' => $passport['passport_number'] ?? $collected['passport_number'] ?? '',
            'passport_type' => $data['passport_type'] ?? $collected['passport_type'] ?? 'ORDINAIRE',
            'email' => $contact['email'] ?? $collected['email'] ?? '',
            'phone' => $contact['phone'] ?? $collected['phone'] ?? '',
            'arrival_date' => $trip['arrival_date'] ?? $collected['arrival_date'] ?? '',
            'departure_date' => $trip['departure_date'] ?? $collected['departure_date'] ?? '',
            'purpose' => $trip['purpose'] ?? $collected['purpose'] ?? 'TOURISME',
            'residence_country' => $collected['country_name'] ?? '',
            'workflow' => $data['workflow_type'] ?? $data['workflow_category'] ?? $collected['workflow_type'] ?? 'STANDARD',
            'workflow_type' => $data['workflow_type'] ?? $data['workflow_category'] ?? $collected['workflow_type'] ?? 'STANDARD',
            'status' => $data['status'] ?? 'pending',
            'created_at' => $data['created_at'] ?? $data['timestamp'] ?? date('c'),
            'status_history' => $data['status_history'] ?? [],
            'documents' => $data['documents'] ?? [],
            'notes' => $data['notes'] ?? [],
            'claude_validation' => $data['claude_validation'] ?? null
        ];
    }
    
    /**
     * Formate pour la liste
     */
    private function formatApplicationForList(array $app): array {
        return [
            'id' => $app['id'],
            'reference' => $app['reference'],
            'name' => $app['name'] ?: 'N/A',
            'nationality' => $app['nationality'] ?: '-',
            'workflow' => $app['workflow'],
            'status' => $app['status'],
            'created_at' => $app['created_at']
        ];
    }
    
    /**
     * Formate pour le détail
     */
    private function formatApplicationForDetail(array $app): array {
        return $app; // Return full data
    }
    
    // === Response Helpers ===
    
    private function success($data, ?array $pagination = null): void {
        $response = ['success' => true, 'data' => $data];
        if ($pagination) {
            $response['pagination'] = $pagination;
        }
        echo json_encode($response);
        exit;
    }
    
    private function error(string $message, int $code = 400): void {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }
}

// Execute
$api = new AdminAPI();
$api->handleRequest();

