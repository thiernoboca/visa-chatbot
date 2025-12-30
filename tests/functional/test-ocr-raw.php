<?php
/**
 * Script pour voir le texte OCR brut
 * Utilise le DocumentExtractor avec debug pour capturer le texte
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(120);

require_once __DIR__ . '/php/config.php';
require_once __DIR__ . '/php/document-extractor.php';

$testDir = '/Users/cheickmouhamedelhadykane/Downloads/test/';

$documents = [
    'hotel' => 'hotelgmail-thanks-your-booking-is-confirmed-at-appartement-1-a-3-pieces-equipe-cosy-calme-aigle.pdf',
    'vaccination' => 'vaccinationyellow-faver-certificate-gezahegn-moges.pdf'
];

// On va capturer l'output de debug
ob_start();
$extractor = new DocumentExtractor(['debug' => true]);
ob_end_clean();

foreach ($documents as $type => $filename) {
    echo "\n\033[1m\033[36m════════════════════════════════════════════════════════════════\033[0m\n";
    echo "\033[1mDOCUMENT: " . strtoupper($type) . "\033[0m\n";
    echo "\033[1m\033[36m════════════════════════════════════════════════════════════════\033[0m\n\n";

    $filePath = $testDir . $filename;

    if (!file_exists($filePath)) {
        echo "Fichier non trouvé: $filename\n";
        continue;
    }

    $content = file_get_contents($filePath);
    $base64 = base64_encode($content);
    $mimeType = mime_content_type($filePath);

    // Capture l'output de debug
    ob_start();

    try {
        $result = $extractor->extract($type, $base64, $mimeType, false);

        // Récupérer le texte OCR des métadonnées du workflow
        $traces = $result['_metadata']['workflow_trace'] ?? [];

        // Chercher la trace OCR
        $ocrChars = null;
        foreach ($traces as $trace) {
            if (isset($trace['data']['chars_extracted'])) {
                $ocrChars = $trace['data']['chars_extracted'];
                break;
            }
        }

        $debugOutput = ob_get_clean();

        echo "Confiance: " . round(($result['_metadata']['ocr_confidence'] ?? 0) * 100, 1) . "%\n";
        echo "Caractères extraits: " . ($ocrChars ?? 'N/A') . "\n\n";

        // Afficher le résultat JSON (sans métadonnées)
        $displayResult = $result;
        unset($displayResult['_metadata']);

        echo "\033[1mRÉSULTAT EXTRACTION:\033[0m\n";
        echo "─────────────────────────────────────────────────────────────────\n";
        echo json_encode($displayResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        echo "─────────────────────────────────────────────────────────────────\n";

    } catch (Exception $e) {
        ob_end_clean();
        echo "Erreur: " . $e->getMessage() . "\n";
    }
}

echo "\n\033[1m\033[32m═══ FIN ═══\033[0m\n";
