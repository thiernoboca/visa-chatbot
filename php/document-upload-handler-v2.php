<?php
/**
 * Endpoint AMÉLIORÉ pour l'upload et l'extraction de documents
 * Utilise le nouveau système Triple Layer avec extracteurs modulaires
 *
 * NOUVEAU: Intègre OCRIntegrationService, DocumentValidator et tous les extracteurs
 *
 * @package VisaChatbot
 * @version 2.0.0
 */

// Configuration erreurs
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Autoloader pour les namespaces
spl_autoload_register(function ($class) {
    $prefix = 'VisaChatbot\\';
    $base_dir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Charger les dépendances
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/services/OCRIntegrationService.php';

use VisaChatbot\Services\OCRIntegrationService;

try {
    // Vérifier qu'un fichier a été uploadé
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = 'No file uploaded';
        if (isset($_FILES['file']['error'])) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];
            $errorMsg = $uploadErrors[$_FILES['file']['error']] ?? 'Unknown upload error';
        }
        throw new Exception($errorMsg);
    }

    $file = $_FILES['file'];
    $documentType = $_POST['document_type'] ?? 'passport';
    $sessionId = $_POST['session_id'] ?? null;
    $validateWithClaude = filter_var($_POST['validate_with_claude'] ?? false, FILTER_VALIDATE_BOOLEAN);

    // Mapping des types de documents
    $typeMapping = [
        'passport' => 'passport',
        'ticket' => 'ticket',
        'hotel' => 'hotel',
        'vaccination' => 'vaccination',
        'invitation' => 'invitation',
        'verbal_note' => 'verbal_note',
        'residence_card' => 'residence_card',
        'payment' => 'payment',
        'payment_proof' => 'payment'  // Alias
    ];

    $normalizedType = $typeMapping[$documentType] ?? $documentType;

    // Valider la taille du fichier (10MB max)
    $maxSize = 10485760;
    if ($file['size'] > $maxSize) {
        throw new Exception("Fichier trop volumineux. Taille max: " . round($maxSize / 1048576, 1) . "MB");
    }

    // Lire le contenu du fichier
    $fileContent = file_get_contents($file['tmp_name']);
    if ($fileContent === false) {
        throw new Exception('Impossible de lire le fichier');
    }

    // Convertir en base64
    $base64Content = base64_encode($fileContent);

    // Déterminer le type MIME
    $mimeType = $file['type'];
    if (empty($mimeType) || $mimeType === 'application/octet-stream') {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf'
        ];
        $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    // Créer le service d'intégration OCR
    $integrationService = new OCRIntegrationService([
        'debug' => defined('DEBUG_MODE') && DEBUG_MODE,
        'use_claude_validation' => $validateWithClaude,
        'cross_validation' => true
    ]);

    // Traiter le document avec le nouveau système
    $result = $integrationService->processDocument($normalizedType, $base64Content, $mimeType, [
        'validate_with_claude' => $validateWithClaude
    ]);

    // Construire la réponse
    $response = [
        'success' => $result['success'] ?? false,
        'document_type' => $documentType,
        'fields' => $result['fields'] ?? [],
        'validations' => $result['validations'] ?? [],
        'confidence' => $result['confidence'] ?? 0,
        'session_id' => $sessionId,
        '_metadata' => [
            'processor' => 'OCRIntegrationService v2',
            'triple_layer' => true,
            'extractors_used' => true,
            'validators_used' => true,
            'processing' => $result['_processing'] ?? []
        ]
    ];

    // Ajouter les données spécifiques selon le type de document
    switch ($normalizedType) {
        case 'passport':
            $response['mrz'] = $result['mrz'] ?? null;
            $response['passport_type'] = $result['passport_type'] ?? 'ORDINAIRE';
            $response['is_standard_passport'] = true;
            break;

        case 'payment':
            $response['amount_analysis'] = $result['amount_analysis'] ?? [];
            $response['payment_validated'] = $result['validations']['amount_matches_expected'] ?? false;
            break;

        case 'ticket':
            $response['is_round_trip'] = $result['fields']['return_flight']['value'] ?? false;
            break;

        case 'vaccination':
            $response['yellow_fever_valid'] = $result['validations']['yellow_fever_valid'] ?? false;
            break;
    }

    // Retourner le résultat
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode() ?: 400
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Error $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur: ' . $e->getMessage(),
        'code' => 500
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
