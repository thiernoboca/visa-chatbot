<?php
/**
 * Tests pour le module d'extraction multi-documents
 * 
 * @package VisaChatbot
 */

// Charger les dépendances
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__) . '/php/config.php';
require_once dirname(__DIR__) . '/php/document-extractor.php';
require_once dirname(__DIR__) . '/php/cross-validator.php';

echo "=== Tests Module Multi-Documents ===\n\n";

$passed = 0;
$failed = 0;

// Test 1: DocumentExtractor - Types supportés
echo "Test 1: DocumentExtractor types supportés... ";
$types = DocumentExtractor::getSupportedTypes();
if (
    isset($types['passport']) &&
    isset($types['ticket']) &&
    isset($types['hotel']) &&
    isset($types['vaccination']) &&
    isset($types['invitation']) &&
    isset($types['verbal_note'])
) {
    echo "✅ PASS\n";
    $passed++;
} else {
    echo "❌ FAIL - Types manquants\n";
    $failed++;
}

// Test 2: DocumentExtractor - Type supporté check
echo "Test 2: isTypeSupported()... ";
if (
    DocumentExtractor::isTypeSupported('passport') &&
    DocumentExtractor::isTypeSupported('ticket') &&
    !DocumentExtractor::isTypeSupported('invalid_type')
) {
    echo "✅ PASS\n";
    $passed++;
} else {
    echo "❌ FAIL\n";
    $failed++;
}

// Test 3: Documents requis pour passeport ordinaire
echo "Test 3: Documents requis pour ORDINAIRE... ";
$required = DocumentExtractor::getRequiredDocuments('ORDINAIRE');
if (
    in_array('passport', $required) &&
    in_array('ticket', $required) &&
    in_array('hotel', $required) &&
    in_array('vaccination', $required) &&
    !in_array('verbal_note', $required)
) {
    echo "✅ PASS\n";
    $passed++;
} else {
    echo "❌ FAIL - Required: " . implode(', ', $required) . "\n";
    $failed++;
}

// Test 4: Documents requis pour passeport diplomatique
echo "Test 4: Documents requis pour DIPLOMATIQUE... ";
$required = DocumentExtractor::getRequiredDocuments('DIPLOMATIQUE');
if (in_array('verbal_note', $required)) {
    echo "✅ PASS\n";
    $passed++;
} else {
    echo "❌ FAIL - verbal_note devrait être requis\n";
    $failed++;
}

// Test 5: CrossValidator - Initialisation
echo "Test 5: CrossValidator initialisation... ";
$documents = [
    'passport' => [
        'fields' => [
            'surname' => ['value' => 'DUPONT', 'confidence' => 0.95],
            'given_names' => ['value' => 'JEAN', 'confidence' => 0.95],
            'date_of_expiry' => ['value' => '31/12/2028', 'confidence' => 0.90]
        ]
    ]
];
try {
    $validator = new CrossValidator($documents);
    echo "✅ PASS\n";
    $passed++;
} catch (Exception $e) {
    echo "❌ FAIL - " . $e->getMessage() . "\n";
    $failed++;
}

// Test 6: CrossValidator - validateAll()
echo "Test 6: CrossValidator validateAll()... ";
try {
    $result = $validator->validateAll();
    if (
        isset($result['validations']) &&
        isset($result['coherence_score']) &&
        isset($result['summary'])
    ) {
        echo "✅ PASS\n";
        $passed++;
    } else {
        echo "❌ FAIL - Structure invalide\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "❌ FAIL - " . $e->getMessage() . "\n";
    $failed++;
}

// Test 7: CrossValidator - Cohérence noms
echo "Test 7: CrossValidator cohérence noms... ";
$documentsWithMismatch = [
    'passport' => [
        'fields' => [
            'surname' => ['value' => 'DUPONT', 'confidence' => 0.95],
            'given_names' => ['value' => 'JEAN', 'confidence' => 0.95]
        ]
    ],
    'ticket' => [
        'passenger' => [
            'surname' => 'DURAND',
            'given_names' => 'PIERRE'
        ]
    ]
];
$validator2 = new CrossValidator($documentsWithMismatch);
$result2 = $validator2->validateAll();
$hasWarning = false;
foreach ($result2['validations'] as $v) {
    if ($v['key'] === 'name_match' && $v['type'] === 'warning') {
        $hasWarning = true;
        break;
    }
}
if ($hasWarning) {
    echo "✅ PASS - Warning détecté pour noms différents\n";
    $passed++;
} else {
    echo "❌ FAIL - Pas de warning pour noms différents\n";
    $failed++;
}

// Test 8: CrossValidator - Vaccination manquante
echo "Test 8: CrossValidator vaccination manquante... ";
$docsNoVaccine = [
    'passport' => [
        'fields' => [
            'surname' => ['value' => 'DUPONT', 'confidence' => 0.95]
        ]
    ]
];
$validator3 = new CrossValidator($docsNoVaccine);
$result3 = $validator3->validateAll();
$hasVaccineWarning = false;
foreach ($result3['validations'] as $v) {
    if ($v['key'] === 'yellow_fever' && $v['type'] === 'warning') {
        $hasVaccineWarning = true;
        break;
    }
}
if ($hasVaccineWarning) {
    echo "✅ PASS - Warning vaccination manquante\n";
    $passed++;
} else {
    echo "❌ FAIL - Pas de warning vaccination\n";
    $failed++;
}

// Test 9: Prompts - FlightTicketPrompt
echo "Test 9: FlightTicketPrompt... ";
require_once dirname(__DIR__) . '/php/prompts/flight-ticket-prompt.php';
$prompt = FlightTicketPrompt::build("Test ticket text");
if (strpos($prompt, 'Test ticket text') !== false && strpos($prompt, 'JSON') !== false) {
    echo "✅ PASS\n";
    $passed++;
} else {
    echo "❌ FAIL\n";
    $failed++;
}

// Test 10: Prompts - HotelReservationPrompt
echo "Test 10: HotelReservationPrompt... ";
require_once dirname(__DIR__) . '/php/prompts/hotel-reservation-prompt.php';
$prompt = HotelReservationPrompt::build("Test hotel text");
if (strpos($prompt, 'Test hotel text') !== false) {
    echo "✅ PASS\n";
    $passed++;
} else {
    echo "❌ FAIL\n";
    $failed++;
}

// Test 11: Prompts - VaccinationCardPrompt
echo "Test 11: VaccinationCardPrompt... ";
require_once dirname(__DIR__) . '/php/prompts/vaccination-card-prompt.php';
$prompt = VaccinationCardPrompt::build("Test vaccination text");
if (strpos($prompt, 'fièvre jaune') !== false) {
    echo "✅ PASS\n";
    $passed++;
} else {
    echo "❌ FAIL\n";
    $failed++;
}

// Test 12: Prompts - InvitationLetterPrompt
echo "Test 12: InvitationLetterPrompt... ";
require_once dirname(__DIR__) . '/php/prompts/invitation-letter-prompt.php';
$prompt = InvitationLetterPrompt::build("Test invitation text");
if (strpos($prompt, 'Test invitation text') !== false) {
    echo "✅ PASS\n";
    $passed++;
} else {
    echo "❌ FAIL\n";
    $failed++;
}

// Test 13: WORKFLOW_STEPS includes documents
echo "Test 13: WORKFLOW_STEPS inclut documents... ";
if (in_array('documents', WORKFLOW_STEPS)) {
    echo "✅ PASS\n";
    $passed++;
} else {
    echo "❌ FAIL - 'documents' absent de WORKFLOW_STEPS\n";
    $failed++;
}

// Test 14: Ordre des étapes correct
echo "Test 14: Ordre des étapes correct... ";
$docsIndex = array_search('documents', WORKFLOW_STEPS);
$residenceIndex = array_search('residence', WORKFLOW_STEPS);
$passportIndex = array_search('passport', WORKFLOW_STEPS);
if ($docsIndex === 2 && $docsIndex > $residenceIndex && $docsIndex < $passportIndex) {
    echo "✅ PASS\n";
    $passed++;
} else {
    echo "❌ FAIL - documents devrait être après residence et avant passport\n";
    $failed++;
}

// Test 15: Chat messages pour documents
echo "Test 15: Messages chat pour documents... ";
require_once dirname(__DIR__) . '/php/data/chat-messages.php';
if (isset(CHAT_MESSAGES['documents_intro']) && isset(CHAT_MESSAGES['documents_analysis_complete'])) {
    echo "✅ PASS\n";
    $passed++;
} else {
    echo "❌ FAIL - Messages documents manquants\n";
    $failed++;
}

// Résumé
echo "\n===========================================\n";
echo "RÉSULTATS: $passed tests passés, $failed tests échoués\n";
echo "===========================================\n";

exit($failed > 0 ? 1 : 0);

