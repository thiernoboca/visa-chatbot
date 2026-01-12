<?php
/**
 * Cron Job - Traitement des validations diplomatiques en attente
 * 
 * Ce script traite les tâches de validation Claude Layer 3 en mode asynchrone.
 * À exécuter via cron toutes les minutes ou selon la charge.
 * 
 * Usage: php process-diplomatic-validations.php [--limit=10] [--debug]
 * 
 * @package VisaChatbot
 * @version 1.0.0
 */

// Configuration CLI
$options = getopt('', ['limit:', 'debug', 'dry-run']);
$limit = isset($options['limit']) ? (int)$options['limit'] : 10;
$debug = isset($options['debug']);
$dryRun = isset($options['dry-run']);

// Charger les dépendances
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/claude-diplomatic-validator.php';
require_once dirname(__DIR__) . '/session-manager.php';

/**
 * Classe de traitement des validations en attente
 */
class DiplomaticValidationProcessor {
    
    private string $pendingDir;
    private string $processedDir;
    private string $reportsDir;
    private ClaudeDiplomaticValidator $validator;
    private bool $debug;
    private bool $dryRun;
    
    public function __construct(bool $debug = false, bool $dryRun = false) {
        $dataDir = dirname(dirname(__DIR__)) . '/data';
        
        $this->pendingDir = $dataDir . '/pending_validations';
        $this->processedDir = $dataDir . '/processed_validations';
        $this->reportsDir = $dataDir . '/claude_audit_reports';
        $this->debug = $debug;
        $this->dryRun = $dryRun;
        
        // Créer les répertoires si nécessaire
        foreach ([$this->pendingDir, $this->processedDir, $this->reportsDir] as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
        
        // Initialiser le validateur
        $this->validator = new ClaudeDiplomaticValidator([
            'debug' => $debug
        ]);
    }
    
    /**
     * Traite les validations en attente
     */
    public function process(int $limit = 10): array {
        $this->log("Démarrage du traitement (limit: {$limit}, dry-run: " . ($this->dryRun ? 'oui' : 'non') . ")");
        
        // Lister les fichiers en attente
        $pendingFiles = glob($this->pendingDir . '/*.json');
        
        if (empty($pendingFiles)) {
            $this->log("Aucune validation en attente");
            return ['processed' => 0, 'errors' => 0, 'skipped' => 0];
        }
        
        // Trier par date (plus ancien en premier)
        usort($pendingFiles, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        // Limiter le nombre de fichiers à traiter
        $pendingFiles = array_slice($pendingFiles, 0, $limit);
        
        $results = [
            'processed' => 0,
            'errors' => 0,
            'skipped' => 0,
            'details' => []
        ];
        
        foreach ($pendingFiles as $file) {
            try {
                $result = $this->processFile($file);
                $results['details'][] = $result;
                
                if ($result['status'] === 'processed') {
                    $results['processed']++;
                } elseif ($result['status'] === 'error') {
                    $results['errors']++;
                } else {
                    $results['skipped']++;
                }
            } catch (Exception $e) {
                $this->log("Erreur traitement {$file}: " . $e->getMessage(), 'error');
                $results['errors']++;
                $results['details'][] = [
                    'file' => basename($file),
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        $this->log("Traitement terminé: {$results['processed']} OK, {$results['errors']} erreurs, {$results['skipped']} ignorés");
        
        return $results;
    }
    
    /**
     * Traite un fichier de validation
     */
    private function processFile(string $filePath): array {
        $filename = basename($filePath);
        $this->log("Traitement: {$filename}");
        
        // Lire le contenu
        $content = file_get_contents($filePath);
        $task = json_decode($content, true);
        
        if (!$task) {
            return [
                'file' => $filename,
                'status' => 'error',
                'error' => 'JSON invalide'
            ];
        }
        
        $type = $task['type'] ?? 'unknown';
        $sessionId = $task['session_id'] ?? 'unknown';
        $data = $task['data'] ?? [];
        
        // Vérifier si c'est une validation diplomatique
        $diplomaticTypes = [
            'diplomatic_passport_verification',
            'passport_type_detection',
            'verbal_note_validation',
            'batch_coherence'
        ];
        
        if (!in_array($type, $diplomaticTypes)) {
            $this->log("Type non diplomatique ignoré: {$type}");
            return [
                'file' => $filename,
                'status' => 'skipped',
                'reason' => 'Non-diplomatic validation type'
            ];
        }
        
        // Mode dry-run
        if ($this->dryRun) {
            $this->log("[DRY-RUN] Aurait traité: {$type} pour session {$sessionId}");
            return [
                'file' => $filename,
                'status' => 'dry-run',
                'type' => $type
            ];
        }
        
        // Exécuter la validation selon le type
        $validation = null;
        
        switch ($type) {
            case 'diplomatic_passport_verification':
            case 'passport_type_detection':
                $passportData = $data['passport_data'] ?? $data;
                $verbalNote = $data['verbal_note'] ?? null;
                $validation = $this->validator->validateDiplomaticPassport($passportData, $verbalNote);
                break;
                
            case 'verbal_note_validation':
                $validation = $this->validator->validateVerbalNote($data);
                break;
                
            case 'batch_coherence':
                $documents = $data['documents'] ?? $data;
                $validation = $this->validator->validateCompleteDiplomaticDossier($documents);
                break;
        }
        
        if (!$validation) {
            return [
                'file' => $filename,
                'status' => 'error',
                'error' => 'Validation non exécutée'
            ];
        }
        
        // Créer le rapport d'audit
        $report = [
            'task_id' => pathinfo($filename, PATHINFO_FILENAME),
            'session_id' => $sessionId,
            'type' => $type,
            'original_task' => $task,
            'validation_result' => $validation,
            'processed_at' => date('c'),
            'recommendation' => $validation['recommendation'] ?? 'unknown',
            'needs_manual_review' => $validation['needs_manual_review'] ?? 
                                     $validation['requires_manual_review'] ?? false
        ];
        
        // Sauvegarder le rapport
        $reportFile = $this->reportsDir . '/' . date('Y-m-d') . '_' . $sessionId . '_' . time() . '.json';
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // Déplacer le fichier traité
        $processedFile = $this->processedDir . '/' . $filename;
        rename($filePath, $processedFile);
        
        // Mettre à jour la session si possible
        $this->updateSessionWithValidation($sessionId, $validation);
        
        $recommendation = $validation['recommendation'] ?? 'N/A';
        $this->log("Validation terminée: {$recommendation}");
        
        return [
            'file' => $filename,
            'status' => 'processed',
            'type' => $type,
            'session_id' => $sessionId,
            'recommendation' => $validation['recommendation'] ?? 'unknown',
            'report_file' => basename($reportFile)
        ];
    }
    
    /**
     * Met à jour la session avec le résultat de validation
     */
    private function updateSessionWithValidation(string $sessionId, array $validation): void {
        try {
            // Ne pas recréer de session, juste mettre à jour le fichier
            $sessionsDir = dirname(dirname(__DIR__)) . '/data/sessions';
            $sessionFile = $sessionsDir . '/' . $sessionId . '.json';
            
            if (!file_exists($sessionFile)) {
                $this->log("Session non trouvée: {$sessionId}", 'warning');
                return;
            }
            
            $sessionData = json_decode(file_get_contents($sessionFile), true);
            
            if (!$sessionData) {
                return;
            }
            
            // Ajouter la validation au workflow_traces
            if (!isset($sessionData['workflow_traces'])) {
                $sessionData['workflow_traces'] = [];
            }
            
            $sessionData['workflow_traces'][] = [
                'timestamp' => date('c'),
                'layer' => 'layer3',
                'action' => 'claude_diplomatic_validation',
                'details' => [
                    'recommendation' => $validation['recommendation'] ?? 'unknown',
                    'confidence_score' => $validation['confidence_score'] ?? $validation['overall_score'] ?? 0,
                    'valid' => $validation['valid'] ?? $validation['overall_valid'] ?? false
                ]
            ];
            
            // Sauvegarder la session mise à jour
            file_put_contents($sessionFile, json_encode($sessionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            $this->log("Session {$sessionId} mise à jour avec validation Layer 3");
            
        } catch (Exception $e) {
            $this->log("Erreur mise à jour session: " . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Nettoie les anciens fichiers traités
     */
    public function cleanup(int $daysToKeep = 30): int {
        $this->log("Nettoyage des fichiers > {$daysToKeep} jours");
        
        $cutoffTime = time() - ($daysToKeep * 86400);
        $deleted = 0;
        
        $files = glob($this->processedDir . '/*.json');
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                if (!$this->dryRun) {
                    unlink($file);
                }
                $deleted++;
            }
        }
        
        $this->log("Fichiers supprimés: {$deleted}");
        
        return $deleted;
    }
    
    /**
     * Retourne les statistiques
     */
    public function getStats(): array {
        $pending = count(glob($this->pendingDir . '/*.json'));
        $processed = count(glob($this->processedDir . '/*.json'));
        $reports = count(glob($this->reportsDir . '/*.json'));
        
        // Analyser les rapports du jour
        $todayReports = glob($this->reportsDir . '/' . date('Y-m-d') . '_*.json');
        $todayStats = [
            'total' => count($todayReports),
            'approved' => 0,
            'manual_review' => 0,
            'rejected' => 0
        ];
        
        foreach ($todayReports as $file) {
            $report = json_decode(file_get_contents($file), true);
            $recommendation = $report['recommendation'] ?? 'unknown';
            if (isset($todayStats[$recommendation])) {
                $todayStats[$recommendation]++;
            }
        }
        
        return [
            'pending' => $pending,
            'processed_total' => $processed,
            'reports_total' => $reports,
            'today' => $todayStats,
            'timestamp' => date('c')
        ];
    }
    
    /**
     * Log
     */
    private function log(string $message, string $level = 'info'): void {
        $timestamp = date('Y-m-d H:i:s');
        $prefix = "[{$timestamp}] [DiplomaticValidation] [{$level}]";
        
        if ($this->debug || php_sapi_name() === 'cli') {
            echo "{$prefix} {$message}\n";
        }
        
        error_log("{$prefix} {$message}");
    }
}

// Exécution CLI
if (php_sapi_name() === 'cli') {
    echo "=== Diplomatic Validation Processor ===\n";
    echo "Mode: " . ($dryRun ? "DRY-RUN" : "PRODUCTION") . "\n";
    echo "Limit: {$limit}\n";
    echo "Debug: " . ($debug ? "ON" : "OFF") . "\n";
    echo "=====================================\n\n";
    
    $processor = new DiplomaticValidationProcessor($debug, $dryRun);
    
    // Afficher les stats avant traitement
    $stats = $processor->getStats();
    echo "Stats avant traitement:\n";
    echo "  - En attente: {$stats['pending']}\n";
    echo "  - Traités (total): {$stats['processed_total']}\n";
    echo "  - Rapports aujourd'hui: {$stats['today']['total']}\n\n";
    
    // Traiter les validations
    $results = $processor->process($limit);
    
    echo "\nRésultats:\n";
    echo "  - Traités: {$results['processed']}\n";
    echo "  - Erreurs: {$results['errors']}\n";
    echo "  - Ignorés: {$results['skipped']}\n";
    
    // Détails si debug
    if ($debug && !empty($results['details'])) {
        echo "\nDétails:\n";
        foreach ($results['details'] as $detail) {
            echo "  - {$detail['file']}: {$detail['status']}";
            if (isset($detail['recommendation'])) {
                echo " ({$detail['recommendation']})";
            }
            echo "\n";
        }
    }
    
    echo "\nTerminé.\n";
}

