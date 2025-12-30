<?php
/**
 * API de Validation de Cohérence Cross-Documents
 * Ambassade de Côte d'Ivoire en Éthiopie
 *
 * Endpoint pour valider la cohérence entre tous les documents d'un dossier visa.
 * Appelé automatiquement après extraction de tous les documents ou manuellement.
 *
 * @package VisaChatbot\API
 * @version 1.0.0
 *
 * Usage:
 * POST /php/coherence-validator-api.php
 * {
 *   "session_id": "xxx",  // ID de session pour récupérer les documents du cache
 *   "documents": {...}    // OU documents directement
 * }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Uniquement POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed',
        'message' => 'Only POST method is accepted'
    ]);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/services/DocumentCoherenceValidator.php';

use VisaChatbot\Services\DocumentCoherenceValidator;

try {
    // Parser le JSON d'entrée
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input');
    }

    $documents = [];

    // Option 1: Documents fournis directement
    if (!empty($input['documents'])) {
        $documents = $input['documents'];
    }
    // Option 2: Charger depuis le cache avec session_id
    elseif (!empty($input['session_id'])) {
        $documents = loadDocumentsFromCache($input['session_id']);
    }
    // Option 3: Charger les derniers documents du cache
    else {
        $documents = loadLatestDocumentsFromCache();
    }

    if (empty($documents)) {
        throw new Exception('No documents to validate');
    }

    // Valider la cohérence
    $validator = new DocumentCoherenceValidator();
    $result = $validator->validateDossier($documents);

    // Ajouter des métadonnées
    $result['api_version'] = '1.0.0';
    $result['documents_validated'] = array_keys($documents);

    // Retourner le résultat
    echo json_encode([
        'success' => true,
        'data' => $result
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Charge les documents depuis le cache par session_id
 */
function loadDocumentsFromCache(string $sessionId): array
{
    $cacheDir = dirname(__DIR__) . '/cache/sessions/' . $sessionId . '/';

    if (!is_dir($cacheDir)) {
        return loadLatestDocumentsFromCache();
    }

    return loadDocumentsFromDirectory($cacheDir);
}

/**
 * Charge les derniers documents extraits du cache global
 */
function loadLatestDocumentsFromCache(): array
{
    $cacheDir = dirname(__DIR__) . '/cache/ocr/';

    if (!is_dir($cacheDir)) {
        return [];
    }

    return loadDocumentsFromDirectory($cacheDir);
}

/**
 * Charge les documents JSON d'un répertoire
 */
function loadDocumentsFromDirectory(string $dir): array
{
    $documents = [];
    $docTypes = ['passport', 'ticket', 'hotel', 'invitation', 'vaccination', 'residence_card', 'verbal_note', 'payment'];

    foreach ($docTypes as $type) {
        $pattern = $dir . $type . '_*.json';
        $files = glob($pattern);

        if (!empty($files)) {
            // Prendre le plus récent
            usort($files, function ($a, $b) {
                return filemtime($b) - filemtime($a);
            });

            $content = file_get_contents($files[0]);
            $data = json_decode($content, true);

            if ($data && json_last_error() === JSON_ERROR_NONE) {
                $documents[$type] = $data;
            }
        }
    }

    return $documents;
}
