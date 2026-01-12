<?php
/**
 * Point d'entrée principal de l'application Chatbot e-Visa
 * Architecture MVC - Côte d'Ivoire
 *
 * @version 2.0.0
 * @author Ambassade de Côte d'Ivoire
 */

// 1. Charger l'autoloader Composer (CRITIQUE pour les dépendances)
require_once __DIR__ . '/vendor/autoload.php';

// 2. Charger les variables d'environnement (CRITIQUE pour les clés API)
use Dotenv\Dotenv;

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    try {
        $dotenv->load();
    } catch (Exception $e) {
        // En cas d'erreur de chargement .env, on continue (peut être défini via serveur)
        error_log("Erreur chargement .env: " . $e->getMessage());
    }
}

// 3. Gestion sécurisée des erreurs
// N'afficher les erreurs que si explicitement en mode développement
if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    // Production: cacher les erreurs, les logger seulement
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/error.log');
}

// ========================================
// RESET HANDLER - Must be first (before any output)
// ========================================
if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    // Destroy session file if exists
    $sessionId = $_GET['session_id'] ?? $_COOKIE['visa_session_id'] ?? null;
    if ($sessionId) {
        $sessionFile = __DIR__ . '/data/sessions/' . basename($sessionId) . '.json';
        if (file_exists($sessionFile)) {
            @unlink($sessionFile);
        }
    }
    // Clear session cookie
    setcookie('visa_session_id', '', time() - 3600, '/');
    // Redirect to clean URL
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Charger la configuration et les helpers
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/helpers.php';

// Charger le contrôleur
require_once __DIR__ . '/controllers/ChatbotController.php';

// Router simple
$action = $_GET['action'] ?? 'index';

try {
    // Instancier le contrôleur
    $controller = new ChatbotController();
    
    // Whitelist des actions autorisées pour sécurité
    $allowedActions = ['index', 'api', 'upload', 'validate', 'geolocation'];
    
    if (in_array($action, $allowedActions) && method_exists($controller, $action)) {
        $controller->$action();
    } else {
        // Fallback sécurisé
        $controller->index();
    }
} catch (Throwable $e) {
    // Gestion globale des exceptions non attrapées
    error_log("Exception fatale: " . $e->getMessage());
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'xmlhttprequest') {
        // Réponse JSON pour AJAX
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Erreur serveur interne']);
    } else {
        // Page d'erreur générique pour utilisateur
        echo "Une erreur est survenue. Veuillez réessayer plus tard.";
    }
}

