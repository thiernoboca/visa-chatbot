<?php
/**
 * Configuration - Chatbot Visa CI
 * Charge la configuration du module OCR parent et ajoute les constantes spécifiques au chatbot
 * 
 * Triple Layer Architecture:
 * - Layer 1: Google Vision (OCR)
 * - Layer 2: Gemini Flash (Conversation)
 * - Layer 3: Claude Sonnet (Supervisor)
 * 
 * @package VisaChatbot
 * @author Ambassade de Côte d'Ivoire
 */

// Définir le chemin racine du chatbot
if (!defined('CHATBOT_ROOT')) {
    define('CHATBOT_ROOT', dirname(__DIR__));
}

// === Toujours charger le .env local pour les clés API ===
$envPath = CHATBOT_ROOT . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim(trim($value), '"\'');
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Charger la configuration du module OCR parent (pour les clients Vision/Claude)
$parentConfig = dirname(dirname(__DIR__)) . '/passport-ocr-module/php/config.php';
if (file_exists($parentConfig)) {
    require_once $parentConfig;
}

// === Définir les constantes API si pas déjà définies ===

// Claude API (Layer 3 - Supervisor)
if (!defined('CLAUDE_API_KEY')) {
    define('CLAUDE_API_KEY', getenv('CLAUDE_API_KEY') ?: '');
}
if (!defined('CLAUDE_MODEL')) {
    define('CLAUDE_MODEL', getenv('CLAUDE_MODEL') ?: 'claude-3-5-haiku-20241022');
}

// Gemini API (Layer 2 - Structuration intelligente)
// Gemini 3 Flash = Meilleure qualité de raisonnement et extraction
if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
}
if (!defined('GEMINI_MODEL')) {
    // Modèle: gemini-3-flash-preview (le plus récent et performant)
    define('GEMINI_MODEL', getenv('GEMINI_MODEL') ?: 'gemini-3-flash-preview');
}

// Debug mode
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', getenv('DEBUG_MODE') === 'true');
}

// QR Code secret key
if (!defined('QR_SECRET_KEY')) {
    define('QR_SECRET_KEY', getenv('QR_SECRET_KEY') ?: 'visa-ci-secret-' . date('Y'));
}

// ===========================================
// CONSTANTES SPECIFIQUES AU CHATBOT
// ===========================================

// Étapes du workflow (14 étapes - avec geolocation et upload documents conversationnel)
// Ordre: welcome -> geolocation -> passport -> residence -> ticket -> hotel -> vaccination -> invitation -> eligibility -> photo -> contact -> trip -> customs -> confirm
if (!defined('WORKFLOW_STEPS')) {
    define('WORKFLOW_STEPS', [
        'welcome',      // 0 - Accueil & langue
        'geolocation',  // 1 - Vérification pays de résidence via IP (juridiction ambassade)
        'passport',     // 2 - Scan passeport (nationalité extraite ici)
        'residence',    // 3 - Confirmation pays de résidence (si geolocation incertaine)
        'ticket',       // 3 - *** NOUVEAU: Billet d'avion + extraction OCR ***
        'hotel',        // 4 - *** NOUVEAU: Réservation hôtel + extraction OCR ***
        'vaccination',  // 5 - *** NOUVEAU: Carnet vaccination + extraction OCR ***
        'invitation',   // 6 - *** NOUVEAU: Lettre d'invitation + extraction OCR ***
        'eligibility',  // 7 - Éligibilité (vérification globale des documents)
        'photo',        // 8 - Photo d'identité
        'contact',      // 9 - Coordonnées
        'trip',         // 10 - Informations voyage (dates pré-remplies depuis ticket)
        'customs',      // 11 - Déclaration douanes
        'confirm'       // 12 - Confirmation & paiement
    ]);
}

// Durée de session (24 heures)
if (!defined('SESSION_DURATION')) {
    define('SESSION_DURATION', 86400);
}

// Langue par défaut
if (!defined('DEFAULT_LANGUAGE')) {
    define('DEFAULT_LANGUAGE', 'fr');
}

// Chemin vers les clients API du module parent
if (!defined('CLAUDE_CLIENT_PATH')) {
    define('CLAUDE_CLIENT_PATH', dirname(dirname(__DIR__)) . '/passport-ocr-module/php/claude-client.php');
}
if (!defined('VISION_CLIENT_PATH')) {
    define('VISION_CLIENT_PATH', dirname(dirname(__DIR__)) . '/passport-ocr-module/php/google-vision-client.php');
}
if (!defined('API_HANDLER_PATH')) {
    define('API_HANDLER_PATH', dirname(dirname(__DIR__)) . '/passport-ocr-module/php/api-handler.php');
}

// ===========================================
// HELPER FUNCTIONS
// ===========================================

/**
 * Charge le client Claude du module parent
 */
function loadClaudeClient(): void {
    if (file_exists(CLAUDE_CLIENT_PATH)) {
        require_once CLAUDE_CLIENT_PATH;
    } else {
        throw new Exception('Client Claude non trouvé: ' . CLAUDE_CLIENT_PATH);
    }
}

/**
 * Charge le client Google Vision du module parent
 */
function loadVisionClient(): void {
    if (file_exists(VISION_CLIENT_PATH)) {
        require_once VISION_CLIENT_PATH;
    } else {
        throw new Exception('Client Vision non trouvé: ' . VISION_CLIENT_PATH);
    }
}

/**
 * Retourne l'index d'une étape
 */
function getStepIndex(string $step): int {
    $index = array_search($step, WORKFLOW_STEPS);
    return $index !== false ? $index : 0;
}

/**
 * Retourne l'étape suivante
 */
function getNextStep(string $currentStep): ?string {
    $index = getStepIndex($currentStep);
    return WORKFLOW_STEPS[$index + 1] ?? null;
}

/**
 * Retourne l'étape précédente
 */
function getPreviousStep(string $currentStep): ?string {
    $index = getStepIndex($currentStep);
    return $index > 0 ? WORKFLOW_STEPS[$index - 1] : null;
}

