<?php
/**
 * Cron Job: Process Pending Claude Validations
 * 
 * Ce script doit être exécuté périodiquement (ex: toutes les 5 minutes)
 * pour traiter les validations Claude en attente (Layer 3 async)
 * 
 * Usage:
 *   php /path/to/visa-chatbot/php/cron/process-claude-validations.php
 * 
 * Crontab:
 *   */5 * * * * php /var/www/visa-chatbot/php/cron/process-claude-validations.php >> /var/log/claude-validations.log 2>&1
 * 
 * @package VisaChatbot
 * @version 1.0.0
 */

// Empêcher l'exécution via navigateur
if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

// Configuration
define('MAX_VALIDATIONS_PER_RUN', 20);
define('LOCK_FILE', '/tmp/claude-validations.lock');

// Vérifier le lock file pour éviter les exécutions concurrentes
if (file_exists(LOCK_FILE)) {
    $lockAge = time() - filemtime(LOCK_FILE);
    if ($lockAge < 300) { // 5 minutes
        echo "[" . date('Y-m-d H:i:s') . "] Another process is running. Exiting.\n";
        exit(0);
    }
    // Lock file trop ancien, le supprimer
    @unlink(LOCK_FILE);
}

// Créer le lock file
file_put_contents(LOCK_FILE, getmypid());

try {
    echo "[" . date('Y-m-d H:i:s') . "] Starting Claude validation processing...\n";
    
    // Charger les dépendances
    require_once dirname(__DIR__) . '/config.php';
    require_once dirname(__DIR__) . '/claude-audit-manager.php';
    
    // Initialiser le manager
    $manager = new ClaudeAuditManager(['debug' => true]);
    
    // Traiter les validations en attente
    $results = $manager->processAllPending(MAX_VALIDATIONS_PER_RUN);
    
    // Afficher les résultats
    echo "[" . date('Y-m-d H:i:s') . "] Processing complete:\n";
    echo "  - Processed: {$results['processed']}\n";
    echo "  - Errors: {$results['errors']}\n";
    echo "  - Alerts generated: {$results['alerts_generated']}\n";
    
    // Afficher les détails en mode verbose
    if (!empty($results['details'])) {
        echo "\nDetails:\n";
        foreach ($results['details'] as $detail) {
            $status = isset($detail['error']) ? 'ERROR' : ($detail['result']['valid'] ? 'OK' : 'INVALID');
            echo "  - {$detail['file']}: {$status}\n";
        }
    }
    
    // Récupérer les stats
    $stats = $manager->getAuditStats();
    echo "\nCurrent stats:\n";
    echo "  - Total audits: {$stats['total_audits']}\n";
    echo "  - Pending validations: {$stats['pending_validations']}\n";
    echo "  - Unacknowledged alerts: {$stats['unacknowledged_alerts']}\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    // Supprimer le lock file
    @unlink(LOCK_FILE);
}

echo "[" . date('Y-m-d H:i:s') . "] Done.\n";
exit(0);

