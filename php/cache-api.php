<?php
/**
 * Cache Management API - Chatbot Visa CI
 * Gestion unifiée du cache OCR
 *
 * @package VisaChatbot
 * @version 1.0.0
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Répertoire de cache OCR principal
$ocrCacheDir = dirname(__DIR__) . '/cache/ocr';

/**
 * Récupère les statistiques du cache
 */
function getCacheStats(string $cacheDir): array {
    $files = glob($cacheDir . '/*.json');
    $stats = [
        'total_entries' => 0,
        'total_size' => 0,
        'by_type' => [],
        'oldest' => null,
        'newest' => null,
        'entries' => []
    ];

    foreach ($files as $file) {
        $filename = basename($file, '.json');
        $parts = explode('_', $filename, 2);
        $type = $parts[0] ?? 'unknown';

        $size = filesize($file);
        $mtime = filemtime($file);

        $stats['total_entries']++;
        $stats['total_size'] += $size;

        if (!isset($stats['by_type'][$type])) {
            $stats['by_type'][$type] = ['count' => 0, 'size' => 0];
        }
        $stats['by_type'][$type]['count']++;
        $stats['by_type'][$type]['size'] += $size;

        if ($stats['oldest'] === null || $mtime < $stats['oldest']['timestamp']) {
            $stats['oldest'] = ['file' => $filename, 'timestamp' => $mtime];
        }
        if ($stats['newest'] === null || $mtime > $stats['newest']['timestamp']) {
            $stats['newest'] = ['file' => $filename, 'timestamp' => $mtime];
        }

        $stats['entries'][] = [
            'key' => $filename,
            'type' => $type,
            'size' => $size,
            'size_human' => formatBytes($size),
            'created' => date('Y-m-d H:i:s', $mtime),
            'age_minutes' => round((time() - $mtime) / 60, 1)
        ];
    }

    // Trier par date (plus récent en premier)
    usort($stats['entries'], fn($a, $b) => $b['age_minutes'] <=> $a['age_minutes']);

    $stats['total_size_human'] = formatBytes($stats['total_size']);

    return $stats;
}

/**
 * Formate les octets en unités lisibles
 */
function formatBytes(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $factor = floor((strlen((string)max(1, $bytes)) - 1) / 3);
    return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor] ?? 'B');
}

/**
 * Purge le cache
 */
function purgeCache(string $cacheDir, ?string $type = null, ?int $olderThanMinutes = null): array {
    $files = glob($cacheDir . '/*.json');
    $deleted = 0;
    $preserved = 0;
    $errors = [];

    foreach ($files as $file) {
        $filename = basename($file, '.json');
        $parts = explode('_', $filename, 2);
        $fileType = $parts[0] ?? 'unknown';

        // Filtrer par type si spécifié
        if ($type !== null && $fileType !== $type) {
            $preserved++;
            continue;
        }

        // Filtrer par âge si spécifié
        if ($olderThanMinutes !== null) {
            $age = (time() - filemtime($file)) / 60;
            if ($age < $olderThanMinutes) {
                $preserved++;
                continue;
            }
        }

        if (@unlink($file)) {
            $deleted++;
        } else {
            $errors[] = $filename;
        }
    }

    return [
        'deleted' => $deleted,
        'preserved' => $preserved,
        'errors' => $errors
    ];
}

/**
 * Supprime une entrée spécifique
 */
function deleteEntry(string $cacheDir, string $key): bool {
    $file = $cacheDir . '/' . $key . '.json';
    if (file_exists($file)) {
        return @unlink($file);
    }
    return false;
}

// Router API
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'stats';

try {
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'stats':
                    $stats = getCacheStats($ocrCacheDir);
                    echo json_encode([
                        'success' => true,
                        'cache_dir' => $ocrCacheDir,
                        'stats' => $stats
                    ], JSON_PRETTY_PRINT);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Unknown action: ' . $action]);
            }
            break;

        case 'DELETE':
            switch ($action) {
                case 'purge':
                    $type = $_GET['type'] ?? null;
                    $olderThan = isset($_GET['older_than']) ? (int)$_GET['older_than'] : null;

                    $result = purgeCache($ocrCacheDir, $type, $olderThan);
                    echo json_encode([
                        'success' => true,
                        'action' => 'purge',
                        'filters' => [
                            'type' => $type,
                            'older_than_minutes' => $olderThan
                        ],
                        'result' => $result
                    ], JSON_PRETTY_PRINT);
                    break;

                case 'entry':
                    $key = $_GET['key'] ?? null;
                    if (!$key) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Missing key parameter']);
                        break;
                    }

                    $deleted = deleteEntry($ocrCacheDir, $key);
                    echo json_encode([
                        'success' => $deleted,
                        'action' => 'delete_entry',
                        'key' => $key
                    ], JSON_PRETTY_PRINT);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Unknown delete action: ' . $action]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
