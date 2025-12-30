<?php
/**
 * Claude Audit Manager - Layer 3 Superviseur
 * Ambassade de Côte d'Ivoire en Éthiopie
 * 
 * Gère les validations asynchrones Claude pour:
 * - Détection de type de passeport
 * - Cohérence des documents
 * - Détection d'anomalies/fraude
 * - Audit des demandes complètes
 * 
 * @package VisaChatbot
 * @version 1.0.0
 */

require_once __DIR__ . '/config.php';

class ClaudeAuditManager {
    
    /**
     * Répertoire des validations en attente
     */
    private string $pendingDir;
    
    /**
     * Répertoire des rapports d'audit
     */
    private string $reportsDir;
    
    /**
     * Répertoire des alertes
     */
    private string $alertsDir;
    
    /**
     * Clé API Claude
     */
    private string $claudeApiKey;
    
    /**
     * Modèle Claude à utiliser
     */
    private string $claudeModel;
    
    /**
     * Mode debug
     */
    private bool $debug;
    
    /**
     * Constructeur
     */
    public function __construct(array $options = []) {
        $dataDir = dirname(__DIR__) . '/data';
        
        $this->pendingDir = $options['pending_dir'] ?? $dataDir . '/pending_validations';
        $this->reportsDir = $options['reports_dir'] ?? $dataDir . '/audit_reports';
        $this->alertsDir = $options['alerts_dir'] ?? $dataDir . '/alerts';
        
        $this->claudeApiKey = $options['claude_api_key'] ?? (defined('CLAUDE_API_KEY') ? CLAUDE_API_KEY : getenv('CLAUDE_API_KEY'));
        $this->claudeModel = $options['claude_model'] ?? (defined('CLAUDE_MODEL') ? CLAUDE_MODEL : 'claude-3-5-haiku-20241022');
        $this->debug = $options['debug'] ?? false;
        
        // Créer les répertoires si nécessaire
        $this->ensureDirectories();
    }
    
    /**
     * Crée les répertoires nécessaires
     */
    private function ensureDirectories(): void {
        $dirs = [$this->pendingDir, $this->reportsDir, $this->alertsDir];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
    }
    
    /**
     * Traite toutes les validations en attente
     * 
     * @param int $limit Nombre max de validations à traiter
     * @return array Résultats du traitement
     */
    public function processAllPending(int $limit = 10): array {
        $pendingFiles = glob($this->pendingDir . '/*.json');
        $results = [
            'processed' => 0,
            'errors' => 0,
            'alerts_generated' => 0,
            'details' => []
        ];
        
        $count = 0;
        foreach ($pendingFiles as $file) {
            if ($count >= $limit) break;
            
            try {
                $task = json_decode(file_get_contents($file), true);
                if (!$task) {
                    throw new Exception('Invalid JSON in pending file');
                }
                
                $result = $this->processValidationTask($task);
                $results['details'][] = [
                    'file' => basename($file),
                    'type' => $task['type'],
                    'result' => $result
                ];
                
                if ($result['has_alert']) {
                    $results['alerts_generated']++;
                }
                
                // Supprimer le fichier traité
                @unlink($file);
                $results['processed']++;
                
            } catch (Exception $e) {
                $results['errors']++;
                $results['details'][] = [
                    'file' => basename($file),
                    'error' => $e->getMessage()
                ];
                $this->log("Error processing {$file}: " . $e->getMessage(), 'error');
            }
            
            $count++;
        }
        
        return $results;
    }
    
    /**
     * Traite une tâche de validation spécifique
     */
    private function processValidationTask(array $task): array {
        $type = $task['type'];
        $data = $task['data'];
        $sessionId = $task['session_id'];
        
        // Router vers le validateur approprié
        $validationResult = match($type) {
            'passport_type_detection' => $this->validatePassportType($data),
            'diplomatic_passport_verification' => $this->validateDiplomaticPassport($data),
            'batch_coherence' => $this->validateBatchCoherence($data),
            'nlu_low_confidence' => $this->validateLowConfidenceNLU($data),
            'application_complete' => $this->validateCompleteApplication($data),
            default => $this->genericValidation($type, $data)
        };
        
        // Stocker le rapport d'audit
        $report = [
            'session_id' => $sessionId,
            'validation_type' => $type,
            'timestamp' => date('c'),
            'input_data' => $data,
            'result' => $validationResult,
            'model' => $this->claudeModel
        ];
        
        $this->storeAuditReport($sessionId, $type, $report);
        
        // Générer une alerte si nécessaire
        $hasAlert = $this->checkAndCreateAlert($sessionId, $type, $validationResult);
        
        return [
            'valid' => $validationResult['valid'] ?? true,
            'confidence' => $validationResult['confidence_score'] ?? 0,
            'has_alert' => $hasAlert
        ];
    }
    
    /**
     * Valide la détection du type de passeport
     */
    private function validatePassportType(array $data): array {
        $prompt = $this->buildValidationPrompt('passport_type', $data);
        return $this->callClaudeForValidation($prompt);
    }
    
    /**
     * Valide un passeport diplomatique
     */
    private function validateDiplomaticPassport(array $data): array {
        $dataJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $prompt = <<<PROMPT
Tu es un superviseur de sécurité pour les demandes de visa Côte d'Ivoire.
Un passeport DIPLOMATIQUE a été détecté. C'est un cas CRITIQUE car il détermine:
- Workflow PRIORITY (gratuit, 24-48h)
- Besoin de note verbale

DONNÉES DU PASSEPORT:
{$dataJson}

Vérifie:
1. Le type détecté est-il cohérent avec les indices MRZ et visuels?
2. Y a-t-il des signes de fraude ou de manipulation?
3. La confiance OCR est-elle suffisante?

Retourne UNIQUEMENT un JSON:
{
  "valid": true,
  "confidence_score": 0.95,
  "detected_type_confirmed": true,
  "fraud_indicators": [],
  "warnings": [],
  "recommendation": "approved|manual_review|rejected",
  "explanation": "Brève explication"
}
PROMPT;
        
        return $this->callClaudeForValidation($prompt);
    }
    
    /**
     * Valide la cohérence d'un batch de documents
     */
    private function validateBatchCoherence(array $data): array {
        $dataJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $prompt = <<<PROMPT
Tu es un superviseur qualité pour les demandes de visa Côte d'Ivoire.
Vérifie la cohérence globale entre les documents extraits.

DONNÉES:
{$dataJson}

Vérifie:
1. Les noms correspondent-ils entre les documents?
2. Les dates sont-elles cohérentes (billet, hôtel, vaccination)?
3. Y a-t-il des anomalies suspectes?

Retourne UNIQUEMENT un JSON:
{
  "valid": true,
  "coherence_score": 0.95,
  "name_consistency": true,
  "date_consistency": true,
  "anomalies": [],
  "warnings": [],
  "recommendation": "approved|manual_review|rejected"
}
PROMPT;
        
        return $this->callClaudeForValidation($prompt);
    }
    
    /**
     * Valide un NLU à faible confiance
     */
    private function validateLowConfidenceNLU(array $data): array {
        $dataJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $prompt = <<<PROMPT
Le système NLU a eu une faible confiance dans sa compréhension du message utilisateur.

DONNÉES:
{$dataJson}

Analyse:
1. Quelle était l'intention probable de l'utilisateur?
2. Y a-t-il une ambiguïté dans le message?
3. Quelle aurait été la meilleure interprétation?

Retourne UNIQUEMENT un JSON:
{
  "valid": true,
  "likely_intent": "provide_info|confirm|deny|ask_help|other",
  "confidence_adjustment": 0.8,
  "suggested_clarification": "Question à poser si nécessaire",
  "analysis": "Brève analyse"
}
PROMPT;
        
        return $this->callClaudeForValidation($prompt);
    }
    
    /**
     * Valide une demande complète avant soumission
     */
    private function validateCompleteApplication(array $data): array {
        $dataJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $prompt = <<<PROMPT
Tu es un superviseur final pour les demandes de visa Côte d'Ivoire.
Vérifie la complétude et la cohérence de cette demande avant soumission.

DONNÉES COMPLÈTES:
{$dataJson}

Vérifie:
1. Tous les champs obligatoires sont-ils remplis?
2. Les documents requis sont-ils présents?
3. Y a-t-il des incohérences entre les informations?
4. Y a-t-il des signes de fraude?

Retourne UNIQUEMENT un JSON:
{
  "valid": true,
  "completeness_score": 0.95,
  "missing_required_fields": [],
  "missing_documents": [],
  "inconsistencies": [],
  "fraud_indicators": [],
  "recommendation": "approved|manual_review|rejected",
  "notes_for_agent": "Notes pour l'agent consulaire"
}
PROMPT;
        
        return $this->callClaudeForValidation($prompt);
    }
    
    /**
     * Validation générique
     */
    private function genericValidation(string $type, array $data): array {
        $dataJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $prompt = <<<PROMPT
Validation de type: {$type}

DONNÉES:
{$dataJson}

Analyse ces données et identifie les problèmes potentiels.

Retourne UNIQUEMENT un JSON:
{
  "valid": true,
  "confidence_score": 0.8,
  "warnings": [],
  "recommendation": "approved|manual_review|rejected"
}
PROMPT;
        
        return $this->callClaudeForValidation($prompt);
    }
    
    /**
     * Construit un prompt de validation standard
     */
    private function buildValidationPrompt(string $type, array $data): string {
        $dataJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        return <<<PROMPT
Tu es un superviseur qualité pour les demandes de visa Côte d'Ivoire.
Type de validation: {$type}

DONNÉES À VALIDER:
{$dataJson}

Analyse ces données et détecte les anomalies potentielles.

Retourne UNIQUEMENT un JSON avec:
{
  "valid": true,
  "confidence_score": 0.95,
  "warnings": [],
  "anomalies": [],
  "recommendation": "approved|manual_review|rejected"
}
PROMPT;
    }
    
    /**
     * Appelle Claude pour validation
     */
    private function callClaudeForValidation(string $prompt): array {
        if (empty($this->claudeApiKey)) {
            return [
                'valid' => true,
                'confidence_score' => 0.5,
                'warnings' => ['Claude API key not configured'],
                'validated_by' => 'fallback'
            ];
        }
        
        $payload = [
            'model' => $this->claudeModel,
            'max_tokens' => 1024,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ]
        ];
        
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->claudeApiKey,
                'anthropic-version: 2023-06-01'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $this->log("Claude API error: HTTP {$httpCode}", 'error');
            return [
                'valid' => true,
                'confidence_score' => 0.5,
                'warnings' => ["Claude API error: HTTP {$httpCode}"],
                'validated_by' => 'error_fallback'
            ];
        }
        
        $data = json_decode($response, true);
        $content = $data['content'][0]['text'] ?? '';
        
        // Parser le JSON de la réponse
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $result = json_decode($matches[0], true);
            if ($result) {
                $result['validated_by'] = 'claude_layer3';
                return $result;
            }
        }
        
        return [
            'valid' => true,
            'confidence_score' => 0.7,
            'warnings' => ['Could not parse Claude response'],
            'raw_response' => substr($content, 0, 500),
            'validated_by' => 'parse_fallback'
        ];
    }
    
    /**
     * Stocke un rapport d'audit
     */
    private function storeAuditReport(string $sessionId, string $type, array $report): void {
        $filename = $this->reportsDir . '/' . $sessionId . '_' . $type . '_' . time() . '.json';
        file_put_contents($filename, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->log("Audit report stored: {$filename}");
    }
    
    /**
     * Vérifie si une alerte doit être créée et la crée si nécessaire
     */
    private function checkAndCreateAlert(string $sessionId, string $type, array $validationResult): bool {
        $shouldAlert = false;
        $alertLevel = 'info';
        $alertMessage = '';
        
        // Vérifier si une alerte est nécessaire
        if (isset($validationResult['recommendation'])) {
            if ($validationResult['recommendation'] === 'rejected') {
                $shouldAlert = true;
                $alertLevel = 'critical';
                $alertMessage = "Validation {$type} rejetée";
            } elseif ($validationResult['recommendation'] === 'manual_review') {
                $shouldAlert = true;
                $alertLevel = 'warning';
                $alertMessage = "Validation {$type} nécessite révision manuelle";
            }
        }
        
        if (!empty($validationResult['fraud_indicators'])) {
            $shouldAlert = true;
            $alertLevel = 'critical';
            $alertMessage = "Indicateurs de fraude détectés";
        }
        
        if (!empty($validationResult['anomalies'])) {
            $shouldAlert = true;
            $alertLevel = max($alertLevel, 'warning');
            $alertMessage = "Anomalies détectées: " . count($validationResult['anomalies']);
        }
        
        if (isset($validationResult['confidence_score']) && $validationResult['confidence_score'] < 0.7) {
            $shouldAlert = true;
            $alertLevel = $alertLevel === 'critical' ? 'critical' : 'warning';
            $alertMessage = "Confiance faible: {$validationResult['confidence_score']}";
        }
        
        if ($shouldAlert) {
            $this->createAlert($sessionId, $type, $alertLevel, $alertMessage, $validationResult);
        }
        
        return $shouldAlert;
    }
    
    /**
     * Crée une alerte pour le dashboard admin
     */
    private function createAlert(string $sessionId, string $type, string $level, string $message, array $details): void {
        $alert = [
            'id' => uniqid('alert_'),
            'session_id' => $sessionId,
            'type' => $type,
            'level' => $level,
            'message' => $message,
            'details' => $details,
            'created_at' => date('c'),
            'acknowledged' => false
        ];
        
        $filename = $this->alertsDir . '/' . $alert['id'] . '.json';
        file_put_contents($filename, json_encode($alert, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $this->log("Alert created [{$level}]: {$message}");
    }
    
    /**
     * Récupère toutes les alertes non acquittées
     */
    public function getUnacknowledgedAlerts(): array {
        $alerts = [];
        $files = glob($this->alertsDir . '/*.json');
        
        foreach ($files as $file) {
            $alert = json_decode(file_get_contents($file), true);
            if ($alert && !($alert['acknowledged'] ?? false)) {
                $alerts[] = $alert;
            }
        }
        
        // Trier par date (plus récent en premier)
        usort($alerts, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return $alerts;
    }
    
    /**
     * Acquitte une alerte
     */
    public function acknowledgeAlert(string $alertId, string $acknowledgedBy): bool {
        $filename = $this->alertsDir . '/' . $alertId . '.json';
        
        if (!file_exists($filename)) {
            return false;
        }
        
        $alert = json_decode(file_get_contents($filename), true);
        $alert['acknowledged'] = true;
        $alert['acknowledged_at'] = date('c');
        $alert['acknowledged_by'] = $acknowledgedBy;
        
        file_put_contents($filename, json_encode($alert, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return true;
    }
    
    /**
     * Récupère les rapports d'audit pour une session
     */
    public function getSessionAuditReports(string $sessionId): array {
        $reports = [];
        $pattern = $this->reportsDir . '/' . $sessionId . '_*.json';
        $files = glob($pattern);
        
        foreach ($files as $file) {
            $report = json_decode(file_get_contents($file), true);
            if ($report) {
                $reports[] = $report;
            }
        }
        
        return $reports;
    }
    
    /**
     * Récupère les statistiques d'audit
     */
    public function getAuditStats(): array {
        $reportFiles = glob($this->reportsDir . '/*.json');
        $alertFiles = glob($this->alertsDir . '/*.json');
        $pendingFiles = glob($this->pendingDir . '/*.json');
        
        $stats = [
            'total_audits' => count($reportFiles),
            'pending_validations' => count($pendingFiles),
            'total_alerts' => count($alertFiles),
            'unacknowledged_alerts' => 0,
            'by_type' => [],
            'by_recommendation' => [
                'approved' => 0,
                'manual_review' => 0,
                'rejected' => 0
            ]
        ];
        
        // Compter les alertes non acquittées
        foreach ($alertFiles as $file) {
            $alert = json_decode(file_get_contents($file), true);
            if ($alert && !($alert['acknowledged'] ?? false)) {
                $stats['unacknowledged_alerts']++;
            }
        }
        
        // Analyser les rapports
        foreach ($reportFiles as $file) {
            $report = json_decode(file_get_contents($file), true);
            if ($report) {
                $type = $report['validation_type'] ?? 'unknown';
                $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
                
                $recommendation = $report['result']['recommendation'] ?? 'approved';
                if (isset($stats['by_recommendation'][$recommendation])) {
                    $stats['by_recommendation'][$recommendation]++;
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Nettoie les anciens fichiers
     */
    public function cleanup(int $maxAgeDays = 30): array {
        $cutoffTime = time() - ($maxAgeDays * 24 * 60 * 60);
        $deleted = ['reports' => 0, 'alerts' => 0];
        
        // Nettoyer les rapports
        foreach (glob($this->reportsDir . '/*.json') as $file) {
            if (filemtime($file) < $cutoffTime) {
                @unlink($file);
                $deleted['reports']++;
            }
        }
        
        // Nettoyer les alertes acquittées anciennes
        foreach (glob($this->alertsDir . '/*.json') as $file) {
            $alert = json_decode(file_get_contents($file), true);
            if ($alert && ($alert['acknowledged'] ?? false) && filemtime($file) < $cutoffTime) {
                @unlink($file);
                $deleted['alerts']++;
            }
        }
        
        $this->log("Cleanup completed: {$deleted['reports']} reports, {$deleted['alerts']} alerts deleted");
        return $deleted;
    }
    
    /**
     * Log conditionnel
     */
    private function log(string $message, string $level = 'info'): void {
        if ($this->debug) {
            error_log("[ClaudeAuditManager] " . strtoupper($level) . ": {$message}");
        }
    }
}

