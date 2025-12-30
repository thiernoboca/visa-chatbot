<?php
/**
 * Point d'entrée principal de l'application Chatbot e-Visa
 * Architecture MVC - Côte d'Ivoire
 * 
 * @version 2.0.0
 * @author Ambassade de Côte d'Ivoire
 */

// Activer le rapport d'erreurs en développement
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Charger la configuration
require_once __DIR__ . '/config/app.php';

// Charger les helpers
require_once __DIR__ . '/includes/helpers.php';

// Charger le contrôleur
require_once __DIR__ . '/controllers/ChatbotController.php';

// Router simple - pour l'instant, une seule route
$action = $_GET['action'] ?? 'index';

// Instancier le contrôleur
$controller = new ChatbotController();

// Exécuter l'action
switch ($action) {
    case 'index':
    default:
        $controller->index();
        break;
}

