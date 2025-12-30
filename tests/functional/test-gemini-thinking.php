<?php
/**
 * Test Gemini 3 Flash avec Thinking Mode
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/php/config.php';
require_once __DIR__ . '/php/gemini-client.php';

echo "=== Test Gemini 3 Flash Thinking Mode ===\n\n";

// Créer le client
$gemini = new GeminiClient(['debug' => true]);

// Afficher la configuration
$info = $gemini->getInfo();
echo "Configuration:\n";
echo json_encode($info, JSON_PRETTY_PRINT) . "\n\n";

// Test 1: Thinking minimal (conversation simple)
echo "=== Test 1: Thinking MINIMAL (greeting) ===\n";
$start = microtime(true);
$result = $gemini->chat('Bonjour', ['step' => 'welcome', 'language' => 'fr']);
$duration = round(microtime(true) - $start, 2);
echo "Durée: {$duration}s\n";
echo "Thinking Level: " . ($result['_metadata']['thinking_level'] ?? 'N/A') . "\n";
echo "Réponse: " . substr($result['message'], 0, 200) . "...\n\n";

// Test 2: Thinking HIGH (passport analysis)
echo "=== Test 2: Thinking HIGH (passport detection) ===\n";
$start = microtime(true);
$result = $gemini->chat('Mon passeport est diplomatique, numéro D1234567', [
    'step' => 'passport',
    'action' => 'detect_passport_type',
    'language' => 'fr'
]);
$duration = round(microtime(true) - $start, 2);
echo "Durée: {$duration}s\n";
echo "Thinking Level: " . ($result['_metadata']['thinking_level'] ?? 'N/A') . "\n";
echo "Réponse: " . substr($result['message'], 0, 300) . "...\n\n";

// Test 3: Structure document avec thinking HIGH
echo "=== Test 3: OCR Passport avec Thinking HIGH ===\n";
$sampleMRZ = <<<MRZ
P<ETHMOGES<<GEZAHEGN<<<<<<<<<<<<<<<<<<<<<<<<<
EP12345678ETH8501011M3012319<<<<<<<<<<<<<<02
MRZ;

$start = microtime(true);
$result = $gemini->structureDocument($sampleMRZ, 'passport');
$duration = round(microtime(true) - $start, 2);
echo "Durée: {$duration}s\n";
echo "Thinking Level: " . ($result['_metadata']['thinking_level'] ?? 'N/A') . "\n";
echo "Thinking Enabled: " . ($result['_metadata']['thinking_enabled'] ? 'Oui' : 'Non') . "\n";
echo "Résultat:\n";
echo json_encode($result['fields'] ?? $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== Tests terminés ===\n";
