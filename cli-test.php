<?php
/**
 * CLI Test for all documents
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(300);

require_once __DIR__ . '/php/config.php';
require_once __DIR__ . '/php/document-extractor.php';

$testDir = '/Users/cheickmouhamedelhadykane/Downloads/test/';

$documents = [
    'passport' => 'passportpassport-scan.pdf',
    'ticket' => 'billetelectronic-ticket-receipt-december-28-for-mr-gezahegn-mogesejigu.pdf',
    'hotel' => 'hotelgmail-thanks-your-booking-is-confirmed-at-appartement-1-a-3-pieces-equipe-cosy-calme-aigle.pdf',
    'vaccination' => 'vaccinationyellow-faver-certificate-gezahegn-moges.pdf',
    'invitation' => 'ordremissioninvitation-letter-gezahegn-moges-ejigu.pdf'
];

// Test specific document or all
$testType = $argv[1] ?? 'ticket';

echo "=== Configuration APIs ===\n";
echo "GEMINI_API_KEY: " . (strlen(getenv('GEMINI_API_KEY')) > 10 ? "OK" : "MISSING") . "\n";
echo "CLAUDE_API_KEY: " . (strlen(getenv('CLAUDE_API_KEY')) > 10 ? "OK" : "MISSING") . "\n";
echo "GOOGLE_CREDENTIALS: " . (file_exists(getenv('GOOGLE_CREDENTIALS_PATH')) ? "OK" : "MISSING") . "\n\n";

$extractor = new DocumentExtractor(['debug' => true]);
echo "Gemini disponible: " . ($extractor->isGeminiAvailable() ? "OUI ✓" : "NON ✗") . "\n\n";

$typesToTest = ($testType === 'all') ? array_keys($documents) : [$testType];

foreach ($typesToTest as $type) {
    if (!isset($documents[$type])) {
        echo "Type inconnu: $type\n";
        continue;
    }

    $file = $testDir . $documents[$type];

    if (!file_exists($file)) {
        echo "❌ $type: Fichier non trouvé\n";
        continue;
    }

    echo "=== Test: $type ===\n";
    echo "Fichier: " . basename($file) . "\n";

    try {
        $content = file_get_contents($file);
        $base64 = base64_encode($content);
        $mimeType = mime_content_type($file);

        $startTime = microtime(true);
        $result = $extractor->extract($type, $base64, $mimeType, false);
        $duration = round(microtime(true) - $startTime, 2);

        $layer2 = $result['_metadata']['triple_layer']['layer2'] ?? 'N/A';

        echo "✅ Succès en {$duration}s\n";
        echo "Layer 2: $layer2\n";

        // Show ALL key fields
        if (isset($result['fields'])) {
            echo "Champs extraits:\n";
            foreach ($result['fields'] as $key => $field) {
                $value = is_array($field) ? ($field['value'] ?? null) : $field;
                if (!empty($value)) {
                    $display = is_string($value) ? substr($value, 0, 60) : json_encode($value);
                    echo "  - $key: $display\n";
                }
            }
        }

        // Show classification for passport
        if ($type === 'passport' && isset($result['document_classification'])) {
            echo "Classification: " . $result['document_classification']['category'] . " - " .
                 $result['document_classification']['subcategory'] . "\n";
        }

    } catch (Exception $e) {
        echo "❌ Erreur: " . $e->getMessage() . "\n";
    }

    echo "\n";
}
