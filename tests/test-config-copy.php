<?php
/**
 * Configuration - Module OCR Passeport
 * Charge les variables d'environnement depuis .env
 * 
 * @package PassportOCR
 * @author  Ambassade de Côte d'Ivoire
 */

// Empêcher l'accès direct
if (!defined('PASSPORT_OCR_INIT')) {
    define('PASSPORT_OCR_INIT', true);
}

/**
 * Charge les variables d'environnement depuis un fichier .env
 */
function loadEnv(string $path): void {
    if (!file_exists($path)) {
        throw new Exception("Fichier de configuration .env introuvable: $path");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Ignorer les commentaires
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parser la ligne KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Retirer les guillemets si présents
            $value = trim($value, '"\'');
            
            // Définir la variable d'environnement
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Charger le fichier .env
$envPath = dirname(__DIR__) . '/.env';
loadEnv($envPath);

// ===========================================
// CONSTANTES - GOOGLE VISION API
// ===========================================
// Supporte deux modes d'authentification:
// 1. Service Account (recommandé) via GOOGLE_CREDENTIALS_PATH
// 2. API Key via GOOGLE_VISION_API_KEY

define('GOOGLE_CREDENTIALS_PATH', getenv('GOOGLE_CREDENTIALS_PATH') ?: '');
define('GOOGLE_PROJECT_ID', getenv('GOOGLE_PROJECT_ID') ?: '');
define('GOOGLE_VISION_API_KEY', getenv('GOOGLE_VISION_API_KEY') ?: '');

// ===========================================
// CONSTANTES - CLAUDE API
// ===========================================
define('CLAUDE_API_KEY', getenv('CLAUDE_API_KEY') ?: '');
define('CLAUDE_MODEL', getenv('CLAUDE_MODEL') ?: 'claude-3-5-haiku-20241022');

// ===========================================
// CONSTANTES - MODE OCR
// ===========================================
// Modes disponibles: 'hybrid', 'claude_only', 'google_only'
define('OCR_MODE', getenv('OCR_MODE') ?: 'hybrid');

// ===========================================
// CONSTANTES - CONFIGURATION GENERALE
// ===========================================
define('OCR_MAX_FILE_SIZE', (int)(getenv('OCR_MAX_FILE_SIZE') ?: 10485760));
define('OCR_RATE_LIMIT_REQUESTS', (int)(getenv('OCR_RATE_LIMIT_REQUESTS') ?: 10));
define('OCR_RATE_LIMIT_WINDOW', (int)(getenv('OCR_RATE_LIMIT_WINDOW') ?: 60));
define('DEBUG_MODE', getenv('DEBUG_MODE') === 'true');

// ===========================================
// VALIDATION DES CLES API
// ===========================================

/**
 * Vérifie si Google Vision est configuré (soit Service Account, soit API Key)
 */
function isGoogleVisionConfigured(): bool {
    // Service Account configuré ?
    if (!empty(GOOGLE_CREDENTIALS_PATH) && file_exists(GOOGLE_CREDENTIALS_PATH)) {
        return true;
    }
    // API Key configurée ?
    if (!empty(GOOGLE_VISION_API_KEY) && strpos(GOOGLE_VISION_API_KEY, 'your-') !== 0) {
        return true;
    }
    return false;
}

/**
 * Vérifie si Claude est configuré
 */
function isClaudeConfigured(): bool {
    return !empty(CLAUDE_API_KEY) && strpos(CLAUDE_API_KEY, 'your-') !== 0;
}

// Vérification Google Vision (requis pour mode hybrid et google_only)
if (in_array(OCR_MODE, ['hybrid', 'google_only'])) {
    if (!isGoogleVisionConfigured()) {
        if (DEBUG_MODE) {
            error_log('[PassportOCR] ATTENTION: Google Vision non configuré (ni Service Account ni API Key)');
        }
    }
}

// Vérification Claude (requis pour mode hybrid et claude_only)
if (in_array(OCR_MODE, ['hybrid', 'claude_only'])) {
    if (!isClaudeConfigured()) {
        if (DEBUG_MODE) {
            error_log('[PassportOCR] ATTENTION: Clé API Claude non configurée (requise pour mode ' . OCR_MODE . ')');
        }
    }
}

// ===========================================
// CONFIGURATION PHP
// ===========================================

// Configuration des erreurs selon le mode
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Timezone
date_default_timezone_set('Africa/Abidjan');

// Session (si pas déjà démarrée)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===========================================
// HELPER FUNCTIONS
// ===========================================

/**
 * Vérifie si le mode hybride est disponible
 */
function isHybridModeAvailable(): bool {
    return isGoogleVisionConfigured() && isClaudeConfigured();
}

/**
 * Retourne le mode OCR effectif (avec fallback)
 */
function getEffectiveOcrMode(): string {
    $mode = OCR_MODE;
    
    // Fallback si configuration incomplète
    if ($mode === 'hybrid' && !isHybridModeAvailable()) {
        if (isClaudeConfigured()) {
            return 'claude_only';
        }
        if (isGoogleVisionConfigured()) {
            return 'google_only';
        }
    }
    
    if ($mode === 'claude_only' && !isClaudeConfigured()) {
        if (isGoogleVisionConfigured()) {
            return 'google_only';
        }
    }
    
    if ($mode === 'google_only' && !isGoogleVisionConfigured()) {
        if (isClaudeConfigured()) {
            return 'claude_only';
        }
    }
    
    return $mode;
}

/**
 * Affiche le statut de configuration (pour debug)
 */
function getConfigStatus(): array {
    return [
        'google_vision' => [
            'configured' => isGoogleVisionConfigured(),
            'mode' => !empty(GOOGLE_CREDENTIALS_PATH) && file_exists(GOOGLE_CREDENTIALS_PATH) 
                ? 'service_account' 
                : (!empty(GOOGLE_VISION_API_KEY) ? 'api_key' : 'none'),
            'credentials_path' => GOOGLE_CREDENTIALS_PATH,
            'project_id' => GOOGLE_PROJECT_ID
        ],
        'claude' => [
            'configured' => isClaudeConfigured(),
            'model' => CLAUDE_MODEL
        ],
        'ocr_mode' => [
            'configured' => OCR_MODE,
            'effective' => getEffectiveOcrMode()
        ],
        'debug_mode' => DEBUG_MODE
    ];
}
