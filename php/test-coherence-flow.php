<?php
/**
 * Test Script: Coherence Flow Validation
 * Tests the complete coherence validation flow with test documents
 *
 * @version 6.0.0
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load required files
require_once __DIR__ . '/services/DocumentCoherenceValidator.php';
require_once __DIR__ . '/services/CrossDocumentSync.php';
require_once __DIR__ . '/services/DocumentAnalysisSuggestions.php';
require_once __DIR__ . '/extractors/InvitationLetterExtractor.php';

// Classes WITH namespace
use VisaChatbot\Extractors\InvitationLetterExtractor;
use VisaChatbot\Services\DocumentCoherenceValidator;

// ANSI colors for terminal output
define('GREEN', "\033[32m");
define('RED', "\033[31m");
define('YELLOW', "\033[33m");
define('BLUE', "\033[34m");
define('RESET', "\033[0m");
define('BOLD', "\033[1m");

echo BOLD . "\n========================================\n";
echo "  TEST: Coherence Flow Validation v6.0\n";
echo "========================================\n" . RESET;

// Simulated extracted data from test documents
$testData = [
    'passport' => [
        'full_name' => 'GEZAHEGN MOGES EJIGU',
        'surname' => 'EJIGU',
        'given_names' => 'GEZAHEGN MOGES',
        'nationality' => 'Ethiopian',
        'nationality_code' => 'ETH',
        'date_of_birth' => '1985-03-15',
        'passport_number' => 'EP1234567',
        'expiry_date' => '2028-06-20',
        'sex' => 'M'
    ],
    'ticket' => [
        'passenger_name' => 'GEZAHEGN MOGES EJIGU',
        'departure_date' => '2025-12-28',
        'return_date' => '2026-01-25',
        'outbound_flight' => 'ET908',
        'inbound_flight' => 'ET909',
        'departure_city' => 'Addis Ababa',
        'arrival_city' => 'Abidjan'
    ],
    'hotel' => [
        'guest_name' => 'GEZAHEGN MOGES',
        'check_in' => '2025-12-28',
        'check_out' => '2025-12-29',
        'check_in_date' => '2025-12-28',  // Also include date variants
        'check_out_date' => '2025-12-29',
        'hotel_name' => 'Appartement Cosy Calme Aigle',
        'city' => 'Abidjan'
    ],
    'invitation' => [
        'invitee_name' => 'GEZAHEGN MOGES EJIGU',
        'inviter_name' => 'AMADOU KONE',
        'inviter_organization' => 'SOTRA',
        'visit_from' => '2025-12-27',
        'visit_to' => '2026-02-10',
        'duration_days' => 45,
        'purpose' => 'Mission professionnelle',
        'accommodation_provided' => false
    ],
    'vaccination' => [
        'patient_name' => 'GEZAHEGN MOGES EJIGU',
        'vaccine_type' => 'Yellow Fever',
        'vaccination_date' => '2024-01-15',
        'valid_from' => '2024-01-25',
        'certificate_number' => 'YF-2024-12345'
    ]
];

$testsRun = 0;
$testsPassed = 0;
$testsFailed = 0;

function runTest(string $name, callable $test): void {
    global $testsRun, $testsPassed, $testsFailed;
    $testsRun++;

    echo "\n" . BLUE . "TEST: " . RESET . $name . "\n";

    try {
        $result = $test();
        if ($result === true) {
            echo GREEN . "  ✓ PASSED" . RESET . "\n";
            $testsPassed++;
        } else {
            echo RED . "  ✗ FAILED: " . $result . RESET . "\n";
            $testsFailed++;
        }
    } catch (Exception $e) {
        echo RED . "  ✗ ERROR: " . $e->getMessage() . RESET . "\n";
        $testsFailed++;
    }
}

// =====================================================
// TEST 1: InvitationLetterExtractor - Full Extraction
// =====================================================
echo BOLD . "\n--- InvitationLetterExtractor Tests ---" . RESET;

runTest("Full invitation letter extraction with French dates", function() {
    $extractor = new InvitationLetterExtractor();

    // Simulated OCR text from a French invitation letter
    $testText = <<<EOT
LETTRE D'INVITATION

Je soussigné, M. AMADOU KONE, résidant à Abidjan, Côte d'Ivoire,
ai l'honneur d'inviter M. GEZAHEGN MOGES EJIGU, de nationalité ETHIOPIENNE,
titulaire du passeport N° EP1234567.

Le visiteur arrivera le 27 Décembre 2025 pour un séjour de 45 jours
jusqu'au 10 Février 2026 pour une mission professionnelle.

L'hébergement sera assuré à mon domicile.

Fait à Abidjan, le 15 Décembre 2025
EOT;

    $result = $extractor->extract($testText);

    echo "    → Extracted data:\n";
    echo "      - Duration: " . ($result['extracted']['duration_days'] ?? 'null') . " days\n";
    echo "      - Arrival: " . ($result['extracted']['arrival_date'] ?? 'null') . "\n";
    echo "      - Departure: " . ($result['extracted']['departure_date'] ?? 'null') . "\n";
    echo "      - Purpose: " . ($result['extracted']['purpose'] ?? 'null') . "\n";
    echo "      - Accommodation provided: " . ($result['extracted']['accommodation_provided'] ? 'YES' : 'NO') . "\n";

    // Check duration extraction
    if (($result['extracted']['duration_days'] ?? 0) != 45) {
        return "Duration not extracted: expected 45, got " . ($result['extracted']['duration_days'] ?? 'null');
    }

    return true;
});

runTest("Duration extraction standalone", function() {
    $extractor = new InvitationLetterExtractor();

    $testText = "Séjour prévu pour 45 jours à partir du 27/12/2025";
    $result = $extractor->extract($testText);

    if (($result['extracted']['duration_days'] ?? 0) != 45) {
        return "Duration extraction failed: expected 45, got " . ($result['extracted']['duration_days'] ?? 'null');
    }

    return true;
});

// =====================================================
// TEST 2: CrossDocumentSync - Invitation vs Ticket
// =====================================================
echo BOLD . "\n--- CrossDocumentSync Tests ---" . RESET;

runTest("Detect date mismatches with tolerance", function() use ($testData) {
    $sync = new CrossDocumentSync();
    $result = $sync->validateInvitationVsTicket($testData['invitation'], $testData['ticket']);

    echo "    → Coherent: " . ($result['is_coherent'] ? 'YES' : 'NO') . "\n";
    echo "    → Score: " . ($result['coherence_score'] ?? 'N/A') . "\n";
    echo "    → Discrepancies: " . count($result['discrepancies'] ?? []) . "\n";

    // Note: 1-day arrival gap is within tolerance (>1 day threshold)
    // But should detect duration mismatch
    if ($result['is_coherent']) {
        return "Should have detected discrepancies (duration mismatch)";
    }

    // List all discrepancies found
    foreach ($result['discrepancies'] as $d) {
        echo "    → [{$d['type']}] " . ($d['message_fr'] ?? $d['message_en']) . "\n";
    }

    return true;
});

runTest("Detect duration mismatch (45 vs 28 days)", function() use ($testData) {
    $sync = new CrossDocumentSync();
    $result = $sync->validateInvitationVsTicket($testData['invitation'], $testData['ticket']);

    $hasDurationMismatch = false;
    foreach ($result['discrepancies'] as $d) {
        if ($d['type'] === 'duration_mismatch') {
            $hasDurationMismatch = true;
            echo "    → Found: " . ($d['message_fr'] ?? $d['message_en']) . "\n";
        }
    }

    if (!$hasDurationMismatch) {
        return "duration_mismatch not detected";
    }

    return true;
});

runTest("Hotel vs Ticket coherence", function() use ($testData) {
    $sync = new CrossDocumentSync();
    $result = $sync->validateHotelVsTicket($testData['hotel'], $testData['ticket']);

    echo "    → Coherent: " . ($result['is_coherent'] ? 'YES' : 'NO') . "\n";
    echo "    → Coverage: " . ($result['summary']['coverage_percentage'] ?? 'N/A') . "%\n";
    echo "    → Gap: " . ($result['summary']['gap_days'] ?? 'N/A') . " days\n";

    // Hotel is 1 night for a 28-day trip - should detect gap
    if ($result['is_coherent']) {
        return "Should have detected hotel coverage gap";
    }

    return true;
});

// =====================================================
// TEST 3: DocumentCoherenceValidator - Full Dossier
// =====================================================
echo BOLD . "\n--- DocumentCoherenceValidator Tests ---" . RESET;

runTest("Full dossier validation", function() use ($testData) {
    $validator = new DocumentCoherenceValidator();
    $result = $validator->validateDossier($testData);

    echo "    → Overall coherent: " . ($result['is_coherent'] ? 'YES' : 'NO') . "\n";
    echo "    → Has warnings: " . ($result['has_warnings'] ? 'YES' : 'NO') . "\n";
    echo "    → Is blocked: " . ($result['is_blocked'] ? 'YES' : 'NO') . "\n";
    echo "    → Issues found: " . count($result['issues']) . "\n";

    foreach ($result['issues'] as $issue) {
        $severity = $issue['severity'] ?? 'info';
        $color = $severity === 'blocking' ? RED : ($severity === 'warning' ? YELLOW : RESET);
        echo "    → " . $color . "[{$severity}] " . ($issue['message_fr'] ?? $issue['rule']) . RESET . "\n";
    }

    // Should have warnings at minimum due to accommodation gap
    if (empty($result['issues'])) {
        return "Should have detected at least accommodation gap";
    }

    return true;
});

runTest("Accommodation coverage detection", function() use ($testData) {
    $validator = new DocumentCoherenceValidator();
    $result = $validator->validateDossier($testData);

    $hasAccommodationIssue = false;
    foreach ($result['issues'] as $issue) {
        $message = strtolower($issue['message_fr'] ?? $issue['message'] ?? '');
        $rule = strtolower($issue['rule'] ?? '');

        if (strpos($rule, 'accommodation') !== false ||
            strpos($message, 'hébergement') !== false ||
            strpos($message, 'nuit') !== false) {
            $hasAccommodationIssue = true;
            echo "    → Found: " . ($issue['message_fr'] ?? $issue['message'] ?? $issue['rule']) . "\n";
        }
    }

    if (!$hasAccommodationIssue) {
        return "Accommodation gap not detected";
    }

    return true;
});

// =====================================================
// TEST 4: ProactiveSuggestions
// =====================================================
echo BOLD . "\n--- ProactiveSuggestions Tests ---" . RESET;

runTest("Detect invitation-ticket mismatch", function() use ($testData) {
    $suggestions = new DocumentAnalysisSuggestions();

    $session = [
        'extracted_data' => $testData
    ];

    $result = $suggestions->detectInvitationTicketMismatch($session);

    if (empty($result)) {
        return "Should have detected invitation-ticket mismatch";
    }

    foreach ($result as $issue) {
        echo "    → Type: " . $issue['type'] . " - " . $issue['subtype'] . "\n";
        echo "    → Severity: " . $issue['severity'] . "\n";
    }

    return true;
});

runTest("Detect accommodation gap", function() use ($testData) {
    $suggestions = new DocumentAnalysisSuggestions();

    $session = [
        'extracted_data' => $testData
    ];

    $result = $suggestions->detectAccommodationGap($session);

    if (empty($result)) {
        return "Should have detected accommodation gap";
    }

    echo "    → Type: " . $result['type'] . "\n";
    echo "    → Severity: " . $result['severity'] . "\n";

    return true;
});

// =====================================================
// TEST 5: Workflow Integration Simulation
// =====================================================
echo BOLD . "\n--- Workflow Integration Tests ---" . RESET;

runTest("performCoherenceValidation simulation", function() use ($testData) {
    // Simulate the workflow-engine normalization
    $dossier = [];

    // Passport
    if (!empty($testData['passport'])) {
        $dossier['passport'] = $testData['passport'];
    }

    // Ticket - normalize keys
    if (!empty($testData['ticket'])) {
        $ticket = $testData['ticket'];
        $dossier['ticket'] = [
            'departure_date' => $ticket['departure_date'] ?? $ticket['outbound_date'] ?? null,
            'return_date' => $ticket['return_date'] ?? $ticket['inbound_date'] ?? null,
            'return_flight_number' => $ticket['return_flight_number'] ?? $ticket['inbound_flight'] ?? null,
            'passenger_name' => $ticket['passenger_name'] ?? $ticket['full_name'] ?? null,
            'arrival_city' => $ticket['arrival_city'] ?? $ticket['destination'] ?? 'Abidjan'
        ];
    }

    // Hotel - normalize keys (use check_in_date/check_out_date for DocumentCoherenceValidator)
    if (!empty($testData['hotel'])) {
        $hotel = $testData['hotel'];
        $dossier['hotel'] = [
            'check_in_date' => $hotel['check_in_date'] ?? $hotel['check_in'] ?? null,
            'check_out_date' => $hotel['check_out_date'] ?? $hotel['check_out'] ?? null,
            'guest_name' => $hotel['guest_name'] ?? $hotel['name'] ?? null,
            'city' => $hotel['city'] ?? $hotel['location'] ?? null
        ];
    }

    // Vaccination
    if (!empty($testData['vaccination'])) {
        $dossier['vaccination'] = $testData['vaccination'];
    }

    // Invitation - normalize to 'dates' structure for DocumentCoherenceValidator
    if (!empty($testData['invitation'])) {
        $invitation = $testData['invitation'];
        $dossier['invitation'] = [
            'invitee_name' => $invitation['invitee_name'] ?? null,
            'dates' => [
                'from' => $invitation['visit_from'] ?? $invitation['arrival_date'] ?? null,
                'to' => $invitation['visit_to'] ?? $invitation['departure_date'] ?? null
            ],
            'duration_days' => $invitation['duration_days'] ?? null,
            'accommodation_provided' => $invitation['accommodation_provided'] ?? false
        ];
    }

    echo "    → Documents normalized: " . count($dossier) . "\n";

    $validator = new DocumentCoherenceValidator();
    $result = $validator->validateDossier($dossier);

    echo "    → Coherent: " . ($result['is_coherent'] ? 'YES' : 'NO') . "\n";
    echo "    → Blocked: " . ($result['is_blocked'] ? 'YES' : 'NO') . "\n";
    echo "    → Warnings: " . ($result['has_warnings'] ? 'YES' : 'NO') . "\n";

    // Test should show validation working
    if (empty($result)) {
        return "Validation returned empty result";
    }

    return true;
});

// =====================================================
// SUMMARY
// =====================================================
echo BOLD . "\n========================================\n";
echo "  RESULTS\n";
echo "========================================\n" . RESET;

echo "Total tests: {$testsRun}\n";
echo GREEN . "Passed: {$testsPassed}" . RESET . "\n";
if ($testsFailed > 0) {
    echo RED . "Failed: {$testsFailed}" . RESET . "\n";
} else {
    echo "Failed: 0\n";
}

$percentage = round(($testsPassed / $testsRun) * 100);
$statusColor = $percentage === 100 ? GREEN : ($percentage >= 70 ? YELLOW : RED);
echo "\n" . $statusColor . BOLD . "Success rate: {$percentage}%" . RESET . "\n\n";

exit($testsFailed > 0 ? 1 : 0);
