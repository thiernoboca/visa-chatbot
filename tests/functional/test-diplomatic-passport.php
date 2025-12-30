<?php
/**
 * Test Passeport Diplomatique CIV
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(120);

require_once __DIR__ . '/php/config.php';
require_once __DIR__ . '/php/document-extractor.php';

// Image path from user
$imagePath = '/Users/cheickmouhamedelhadykane/Downloads/test/passport-diplomatique-civ.jpg';

// Check if we need to save the image first
if (!file_exists($imagePath)) {
    die("Image not found: $imagePath\nPlease save the passport image to this location first.\n");
}

echo "=== Test Passeport Diplomatique CIV ===\n\n";

$content = file_get_contents($imagePath);
$base64 = base64_encode($content);
$mimeType = mime_content_type($imagePath);

echo "Fichier: " . basename($imagePath) . "\n";
echo "Taille: " . round(strlen($content)/1024, 1) . " KB\n";
echo "Type: $mimeType\n\n";

$extractor = new DocumentExtractor(['debug' => true]);

$startTime = microtime(true);
$result = $extractor->extract('passport', $base64, $mimeType, false);
$duration = round(microtime(true) - $startTime, 2);

echo "\n=== Résultat ($duration s) ===\n";
echo "Layer 2: " . ($result['_metadata']['triple_layer']['layer2'] ?? 'N/A') . "\n\n";

// Classification
if (isset($result['document_classification'])) {
    $class = $result['document_classification'];
    echo "📋 CLASSIFICATION:\n";
    echo "   Catégorie: " . ($class['category'] ?? 'N/A') . "\n";
    echo "   Sous-type: " . ($class['subcategory'] ?? 'N/A') . "\n";
    echo "   Pays: " . ($class['issuing_country'] ?? 'N/A') . "\n";
    echo "   Confiance: " . (($class['confidence'] ?? 0) * 100) . "%\n\n";
}

// Eligibility
if (isset($result['visa_eligibility'])) {
    $elig = $result['visa_eligibility'];
    echo "🎫 ÉLIGIBILITÉ VISA:\n";
    echo "   Valide: " . ($elig['is_valid'] ? '✅ OUI' : '❌ NON') . "\n";
    echo "   Workflow: " . ($elig['workflow'] ?? 'N/A') . "\n";
    echo "   Raison: " . ($elig['reason_fr'] ?? 'N/A') . "\n\n";
}

// Fields
echo "📝 CHAMPS EXTRAITS:\n";
$fields = $result['fields'] ?? [];
foreach ($fields as $key => $field) {
    $value = is_array($field) ? ($field['value'] ?? json_encode($field)) : $field;
    if (!empty($value) && $value !== 'null') {
        $conf = is_array($field) ? ($field['confidence'] ?? '') : '';
        $src = is_array($field) ? ($field['source'] ?? '') : '';
        echo "   - $key: $value";
        if ($conf) echo " (conf: " . round($conf * 100) . "%, src: $src)";
        echo "\n";
    }
}

// MRZ
if (isset($result['mrz_data'])) {
    echo "\n📊 MRZ:\n";
    echo "   Line 1: " . ($result['mrz_data']['line1'] ?? 'N/A') . "\n";
    echo "   Line 2: " . ($result['mrz_data']['line2'] ?? 'N/A') . "\n";
}

echo "\n";
