<?php
/**
 * Test Complet Multi-Scénarios - Visa Chatbot OCR
 *
 * Scénarios testés:
 * 1. Extraction individuelle de chaque document
 * 2. Performance avec/sans cache
 * 3. Cross-validation des données extraites
 * 4. Cohérence des noms entre documents
 * 5. Validation des dates de voyage
 * 6. Test de résilience (fichiers invalides)
 * 7. Test batch (tous documents en une fois)
 *
 * @author Claude Code
 * @version 1.0.0
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(600);

require_once __DIR__ . '/php/config.php';
require_once __DIR__ . '/php/document-extractor.php';

// Configuration
$testDir = '/Users/cheickmouhamedelhadykane/Downloads/test/';
$cacheDir = __DIR__ . '/cache/ocr';

// Documents de test
$documents = [
    'passport' => 'passportpassport-scan.pdf',
    'ticket' => 'billetelectronic-ticket-receipt-december-28-for-mr-gezahegn-mogesejigu.pdf',
    'hotel' => 'hotelgmail-thanks-your-booking-is-confirmed-at-appartement-1-a-3-pieces-equipe-cosy-calme-aigle.pdf',
    'vaccination' => 'vaccinationyellow-faver-certificate-gezahegn-moges.pdf',
    'invitation' => 'ordremissioninvitation-letter-gezahegn-moges-ejigu.pdf'
];

// Couleurs terminal
define('C_RESET', "\033[0m");
define('C_BOLD', "\033[1m");
define('C_RED', "\033[31m");
define('C_GREEN', "\033[32m");
define('C_YELLOW', "\033[33m");
define('C_BLUE', "\033[34m");
define('C_MAGENTA', "\033[35m");
define('C_CYAN', "\033[36m");
define('C_WHITE', "\033[37m");
define('C_BG_GREEN', "\033[42m");
define('C_BG_RED', "\033[41m");

// Résultats globaux
$globalResults = [
    'scenarios' => [],
    'total_tests' => 0,
    'passed' => 0,
    'failed' => 0,
    'warnings' => 0,
    'start_time' => microtime(true)
];

/**
 * Affiche un header de section
 */
function printHeader(string $title, string $color = C_CYAN): void {
    $line = str_repeat('═', 70);
    echo "\n{$color}{$line}" . C_RESET . "\n";
    echo C_BOLD . "  $title" . C_RESET . "\n";
    echo "{$color}{$line}" . C_RESET . "\n\n";
}

/**
 * Affiche un sous-header
 */
function printSubHeader(string $title): void {
    echo "\n" . C_YELLOW . "─── " . C_BOLD . $title . C_RESET . C_YELLOW . " " . str_repeat('─', 50 - strlen($title)) . C_RESET . "\n\n";
}

/**
 * Affiche le résultat d'un test
 */
function printTestResult(string $test, bool $passed, string $details = '', float $time = 0): void {
    global $globalResults;
    $globalResults['total_tests']++;

    if ($passed) {
        $globalResults['passed']++;
        $icon = C_GREEN . "✓" . C_RESET;
        $status = C_GREEN . "PASS" . C_RESET;
    } else {
        $globalResults['failed']++;
        $icon = C_RED . "✗" . C_RESET;
        $status = C_RED . "FAIL" . C_RESET;
    }

    $timeStr = $time > 0 ? C_CYAN . " ({$time}s)" . C_RESET : '';
    echo "  {$icon} [{$status}] {$test}{$timeStr}\n";

    if ($details) {
        echo "     " . C_WHITE . $details . C_RESET . "\n";
    }
}

/**
 * Affiche un warning
 */
function printWarning(string $message): void {
    global $globalResults;
    $globalResults['warnings']++;
    echo "  " . C_YELLOW . "⚠ WARNING: " . $message . C_RESET . "\n";
}

/**
 * Accède aux valeurs imbriquées
 */
function getNestedValue(array $array, string $path) {
    $keys = explode('.', $path);
    $value = $array;
    foreach ($keys as $key) {
        if (!isset($value[$key])) return null;
        $value = $value[$key];
    }
    return $value;
}

/**
 * Normalise un nom pour comparaison
 */
function normalizeName(string $name): string {
    $name = strtoupper($name);
    $name = preg_replace('/[^A-Z\s]/', '', $name);
    $name = preg_replace('/\s+/', ' ', trim($name));
    return $name;
}

/**
 * Calcule la similarité entre deux noms
 */
function nameSimilarity(string $name1, string $name2): float {
    $n1 = normalizeName($name1);
    $n2 = normalizeName($name2);

    if ($n1 === $n2) return 1.0;

    similar_text($n1, $n2, $percent);
    return $percent / 100;
}

// ============================================================================
// SCÉNARIO 1: Test Extraction Individuelle
// ============================================================================
function scenario1_individualExtraction(array $documents, string $testDir): array {
    global $globalResults;
    printHeader("SCÉNARIO 1: Extraction Individuelle", C_MAGENTA);

    $results = [];
    $extractor = new DocumentExtractor(['debug' => false, 'use_cache' => false]);

    foreach ($documents as $type => $filename) {
        $filePath = $testDir . $filename;

        if (!file_exists($filePath)) {
            printTestResult("Extraction $type", false, "Fichier non trouvé: $filename");
            continue;
        }

        $content = file_get_contents($filePath);
        $base64 = base64_encode($content);
        $mimeType = mime_content_type($filePath);

        $startTime = microtime(true);

        try {
            $result = $extractor->extract($type, $base64, $mimeType, false);
            $duration = round(microtime(true) - $startTime, 2);

            $hasData = !empty($result) && !isset($result['error']);
            $confidence = $result['confidence'] ?? $result['overall_confidence'] ??
                         $result['_metadata']['confidence'] ?? 0;

            printTestResult(
                "Extraction $type",
                $hasData,
                "Confiance: " . round($confidence * 100) . "%",
                $duration
            );

            $results[$type] = $result;

        } catch (Exception $e) {
            printTestResult("Extraction $type", false, "Erreur: " . $e->getMessage());
        }
    }

    $globalResults['scenarios']['individual_extraction'] = count($results) === count($documents);
    return $results;
}

// ============================================================================
// SCÉNARIO 2: Test Performance Cache
// ============================================================================
function scenario2_cachePerformance(array $documents, string $testDir, string $cacheDir): void {
    global $globalResults;
    printHeader("SCÉNARIO 2: Performance Cache", C_MAGENTA);

    // Vider le cache
    $files = glob($cacheDir . '/*.json');
    foreach ($files as $file) @unlink($file);
    echo "  Cache vidé: " . count($files) . " fichiers supprimés\n\n";

    printSubHeader("Run 1: Sans Cache (Fresh)");

    $extractor = new DocumentExtractor(['debug' => false, 'use_cache' => true]);
    $timesWithoutCache = [];

    foreach ($documents as $type => $filename) {
        $filePath = $testDir . $filename;
        if (!file_exists($filePath)) continue;

        $content = file_get_contents($filePath);
        $base64 = base64_encode($content);
        $mimeType = mime_content_type($filePath);

        $startTime = microtime(true);
        $extractor->extract($type, $base64, $mimeType, false);
        $timesWithoutCache[$type] = round(microtime(true) - $startTime, 2);

        echo "  • $type: {$timesWithoutCache[$type]}s\n";
    }

    $totalWithoutCache = array_sum($timesWithoutCache);
    echo "\n  " . C_BOLD . "Total sans cache: {$totalWithoutCache}s" . C_RESET . "\n";

    printSubHeader("Run 2: Avec Cache");

    $timesWithCache = [];

    foreach ($documents as $type => $filename) {
        $filePath = $testDir . $filename;
        if (!file_exists($filePath)) continue;

        $content = file_get_contents($filePath);
        $base64 = base64_encode($content);
        $mimeType = mime_content_type($filePath);

        $startTime = microtime(true);
        $result = $extractor->extract($type, $base64, $mimeType, false);
        $timesWithCache[$type] = round(microtime(true) - $startTime, 3);

        $fromCache = $result['_metadata']['from_cache'] ?? false;
        $cacheIcon = $fromCache ? C_GREEN . "CACHE" . C_RESET : C_YELLOW . "FRESH" . C_RESET;
        echo "  • $type: {$timesWithCache[$type]}s [$cacheIcon]\n";
    }

    $totalWithCache = array_sum($timesWithCache);
    echo "\n  " . C_BOLD . "Total avec cache: {$totalWithCache}s" . C_RESET . "\n";

    // Calcul amélioration
    $improvement = $totalWithoutCache > 0
        ? round((1 - $totalWithCache / $totalWithoutCache) * 100, 1)
        : 0;

    printSubHeader("Résultats Cache");

    printTestResult(
        "Cache créé correctement",
        count(glob($cacheDir . '/*.json')) === count($documents),
        count(glob($cacheDir . '/*.json')) . " fichiers en cache"
    );

    printTestResult(
        "Amélioration performance",
        $improvement > 90,
        "Gain: {$improvement}% (attendu: >90%)"
    );

    $globalResults['scenarios']['cache_performance'] = $improvement > 90;
}

// ============================================================================
// SCÉNARIO 3: Cross-Validation des Noms
// ============================================================================
function scenario3_nameConsistency(array $extractedData): void {
    global $globalResults;
    printHeader("SCÉNARIO 3: Cohérence des Noms", C_MAGENTA);

    // Extraire les noms de chaque document
    $names = [];

    // Passport
    if (isset($extractedData['passport'])) {
        $surname = getNestedValue($extractedData['passport'], 'fields.surname.value') ?? '';
        $givenNames = getNestedValue($extractedData['passport'], 'fields.given_names.value') ?? '';
        $names['passport'] = trim("$surname $givenNames");
    }

    // Ticket
    if (isset($extractedData['ticket'])) {
        $names['ticket'] = $extractedData['ticket']['passenger_name'] ?? '';
    }

    // Hotel
    if (isset($extractedData['hotel'])) {
        $names['hotel'] = $extractedData['hotel']['guest_name'] ?? '';
    }

    // Vaccination
    if (isset($extractedData['vaccination'])) {
        $names['vaccination'] = $extractedData['vaccination']['holder_name'] ?? '';
    }

    // Invitation
    if (isset($extractedData['invitation'])) {
        $inviteeName = getNestedValue($extractedData['invitation'], 'invitee.name')
                      ?? $extractedData['invitation']['invitee']['name'] ?? '';
        $names['invitation'] = $inviteeName;
    }

    echo "  Noms extraits:\n";
    foreach ($names as $doc => $name) {
        echo "    • " . ucfirst($doc) . ": " . C_CYAN . ($name ?: 'N/A') . C_RESET . "\n";
    }
    echo "\n";

    // Comparer avec le passeport comme référence
    $reference = $names['passport'] ?? '';
    if (empty($reference)) {
        printWarning("Pas de nom de référence (passeport)");
        return;
    }

    $allMatch = true;
    foreach ($names as $doc => $name) {
        if ($doc === 'passport' || empty($name)) continue;

        $similarity = nameSimilarity($reference, $name);
        $matches = $similarity >= 0.7;

        printTestResult(
            "Nom $doc vs passeport",
            $matches,
            sprintf("Similarité: %.0f%% - %s", $similarity * 100, $name)
        );

        if (!$matches) $allMatch = false;
    }

    $globalResults['scenarios']['name_consistency'] = $allMatch;
}

// ============================================================================
// SCÉNARIO 4: Validation des Dates de Voyage
// ============================================================================
function scenario4_dateValidation(array $extractedData): void {
    global $globalResults;
    printHeader("SCÉNARIO 4: Cohérence des Dates", C_MAGENTA);

    $dates = [];

    // Passeport - Expiration
    if (isset($extractedData['passport'])) {
        $expiry = getNestedValue($extractedData['passport'], 'fields.date_of_expiry.value');
        $dates['passport_expiry'] = $expiry;
    }

    // Ticket - Départ
    if (isset($extractedData['ticket'])) {
        $dates['flight_departure'] = $extractedData['ticket']['departure_date'] ?? null;
        $dates['flight_arrival'] = $extractedData['ticket']['arrival_date'] ?? null;
    }

    // Hotel - Check-in/out
    if (isset($extractedData['hotel'])) {
        $dates['hotel_checkin'] = $extractedData['hotel']['check_in_date'] ?? null;
        $dates['hotel_checkout'] = $extractedData['hotel']['check_out_date'] ?? null;
    }

    // Vaccination
    if (isset($extractedData['vaccination'])) {
        $dates['vaccination_date'] = $extractedData['vaccination']['vaccination_date'] ?? null;
    }

    // Invitation
    if (isset($extractedData['invitation'])) {
        $inviteDates = $extractedData['invitation']['dates'] ?? null;
        if (is_array($inviteDates)) {
            $dates['invitation_from'] = $inviteDates['from'] ?? null;
            $dates['invitation_to'] = $inviteDates['to'] ?? null;
        }
    }

    echo "  Dates extraites:\n";
    foreach ($dates as $key => $date) {
        $displayDate = is_string($date) ? $date : json_encode($date);
        echo "    • " . str_replace('_', ' ', ucfirst($key)) . ": " . C_CYAN . ($displayDate ?: 'N/A') . C_RESET . "\n";
    }
    echo "\n";

    $allValid = true;

    // Test 1: Passeport valide 6 mois après voyage
    if (!empty($dates['passport_expiry']) && !empty($dates['flight_departure'])) {
        $expiry = strtotime($dates['passport_expiry']);
        $departure = strtotime($dates['flight_departure']);
        $sixMonthsAfter = $departure + (6 * 30 * 24 * 3600);

        $valid = $expiry > $sixMonthsAfter;
        printTestResult(
            "Passeport valide +6 mois après voyage",
            $valid,
            "Expiration: {$dates['passport_expiry']}, Départ: {$dates['flight_departure']}"
        );
        if (!$valid) $allValid = false;
    }

    // Test 2: Check-in hôtel = date arrivée vol (±1 jour)
    if (!empty($dates['flight_departure']) && !empty($dates['hotel_checkin'])) {
        $flight = strtotime($dates['flight_departure']);
        $checkin = strtotime($dates['hotel_checkin']);
        $diff = abs($flight - $checkin) / 86400;

        $valid = $diff <= 1;
        printTestResult(
            "Check-in hôtel cohérent avec vol",
            $valid,
            "Vol: {$dates['flight_departure']}, Check-in: {$dates['hotel_checkin']} (écart: {$diff}j)"
        );
        if (!$valid) $allValid = false;
    }

    // Test 3: Vaccination avant départ
    if (!empty($dates['vaccination_date']) && !empty($dates['flight_departure'])) {
        $vacc = strtotime($dates['vaccination_date']);
        $departure = strtotime($dates['flight_departure']);
        $daysBefore = ($departure - $vacc) / 86400;

        $valid = $daysBefore >= 10; // Fièvre jaune: 10 jours minimum
        printTestResult(
            "Vaccination 10+ jours avant départ",
            $valid,
            "Vaccination: {$dates['vaccination_date']} ({$daysBefore}j avant départ)"
        );
        if (!$valid) $allValid = false;
    }

    // Test 4: Dates invitation cohérentes
    if (!empty($dates['invitation_from']) && !empty($dates['flight_departure'])) {
        $inviteStart = strtotime($dates['invitation_from']);
        $departure = strtotime($dates['flight_departure']);
        $diff = abs($departure - $inviteStart) / 86400;

        $valid = $diff <= 3;
        printTestResult(
            "Dates invitation cohérentes avec vol",
            $valid,
            "Invitation: {$dates['invitation_from']}, Vol: {$dates['flight_departure']} (écart: {$diff}j)"
        );
        if (!$valid) $allValid = false;
    }

    $globalResults['scenarios']['date_validation'] = $allValid;
}

// ============================================================================
// SCÉNARIO 5: Test Fichiers Invalides (Résilience)
// ============================================================================
function scenario5_resilience(): void {
    global $globalResults;
    printHeader("SCÉNARIO 5: Résilience (Erreurs)", C_MAGENTA);

    $extractor = new DocumentExtractor(['debug' => false, 'use_cache' => false]);
    $allPassed = true;

    // Test 1: Type de document invalide
    try {
        $extractor->extract('invalid_type', base64_encode('test'), 'application/pdf', false);
        printTestResult("Rejet type invalide", false, "Devrait lever une exception");
        $allPassed = false;
    } catch (Exception $e) {
        printTestResult("Rejet type invalide", true, "Exception: " . substr($e->getMessage(), 0, 50));
    }

    // Test 2: MIME type non supporté
    try {
        $extractor->extract('passport', base64_encode('test'), 'text/plain', false);
        printTestResult("Rejet MIME invalide", false, "Devrait lever une exception");
        $allPassed = false;
    } catch (Exception $e) {
        printTestResult("Rejet MIME invalide", true, "Exception: " . substr($e->getMessage(), 0, 50));
    }

    // Test 3: Contenu vide
    try {
        $extractor->extract('passport', '', 'application/pdf', false);
        printTestResult("Rejet contenu vide", false, "Devrait lever une exception");
        $allPassed = false;
    } catch (Exception $e) {
        printTestResult("Rejet contenu vide", true, "Exception levée correctement");
    }

    // Test 4: Base64 invalide (devrait être géré gracieusement)
    try {
        $result = $extractor->extract('passport', 'not-valid-base64!!!', 'image/jpeg', false);
        $hasError = isset($result['error']) || empty($result);
        printTestResult("Gestion base64 invalide", true, "Géré sans crash");
    } catch (Exception $e) {
        printTestResult("Gestion base64 invalide", true, "Exception gérée: " . substr($e->getMessage(), 0, 40));
    }

    $globalResults['scenarios']['resilience'] = $allPassed;
}

// ============================================================================
// SCÉNARIO 6: Test Batch Extraction
// ============================================================================
function scenario6_batchExtraction(array $documents, string $testDir): void {
    global $globalResults;
    printHeader("SCÉNARIO 6: Extraction Batch", C_MAGENTA);

    $extractor = new DocumentExtractor(['debug' => false, 'use_cache' => true]);

    // Préparer les documents pour le batch
    $batch = [];
    foreach ($documents as $type => $filename) {
        $filePath = $testDir . $filename;
        if (!file_exists($filePath)) continue;

        $content = file_get_contents($filePath);
        $batch[] = [
            'type' => $type,
            'content' => base64_encode($content),
            'mimeType' => mime_content_type($filePath)
        ];
    }

    echo "  Documents à traiter: " . count($batch) . "\n\n";

    $startTime = microtime(true);

    try {
        $results = $extractor->extractBatch($batch);
        $duration = round(microtime(true) - $startTime, 2);

        $successCount = 0;
        $errorCount = 0;

        foreach ($results['results'] ?? [] as $type => $result) {
            if (isset($result['error'])) {
                $errorCount++;
                echo "  " . C_RED . "✗ $type: " . $result['error'] . C_RESET . "\n";
            } else {
                $successCount++;
                $confidence = $result['confidence'] ?? $result['overall_confidence'] ?? 0;
                echo "  " . C_GREEN . "✓ $type: " . round($confidence * 100) . "% confiance" . C_RESET . "\n";
            }
        }

        echo "\n";
        printTestResult(
            "Batch extraction complète",
            $errorCount === 0,
            "$successCount succès, $errorCount erreurs en {$duration}s"
        );

        $globalResults['scenarios']['batch_extraction'] = $errorCount === 0;

    } catch (Exception $e) {
        printTestResult("Batch extraction", false, "Erreur: " . $e->getMessage());
        $globalResults['scenarios']['batch_extraction'] = false;
    }
}

// ============================================================================
// SCÉNARIO 7: Test Champs Requis
// ============================================================================
function scenario7_requiredFields(array $extractedData): void {
    global $globalResults;
    printHeader("SCÉNARIO 7: Champs Requis", C_MAGENTA);

    $requiredFields = [
        'passport' => [
            'fields.surname.value' => 'Nom',
            'fields.given_names.value' => 'Prénoms',
            'fields.document_number.value' => 'N° Passeport',
            'fields.nationality.value' => 'Nationalité',
            'fields.date_of_birth.value' => 'Date naissance',
            'fields.date_of_expiry.value' => 'Date expiration'
        ],
        'ticket' => [
            'passenger_name' => 'Passager',
            'flight_number' => 'N° Vol',
            'departure_city' => 'Ville départ',
            'arrival_city' => 'Ville arrivée',
            'departure_date' => 'Date départ'
        ],
        'hotel' => [
            'guest_name' => 'Client',
            'hotel_name' => 'Hôtel',
            'check_in_date' => 'Check-in',
            'check_out_date' => 'Check-out'
        ],
        'vaccination' => [
            'holder_name' => 'Titulaire',
            'vaccine_type' => 'Type vaccin',
            'vaccination_date' => 'Date',
            'valid' => 'Validité'
        ],
        'invitation' => [
            'invitee.name' => 'Invité',
            'inviter.name' => 'Invitant',
            'purpose' => 'Objet'
        ]
    ];

    $allPassed = true;

    foreach ($requiredFields as $docType => $fields) {
        if (!isset($extractedData[$docType])) {
            printWarning("Document $docType non extrait");
            continue;
        }

        $found = 0;
        $missing = [];

        foreach ($fields as $path => $label) {
            $value = getNestedValue($extractedData[$docType], $path);
            if ($value !== null && $value !== '' && $value !== 'N/A') {
                $found++;
            } else {
                $missing[] = $label;
            }
        }

        $total = count($fields);
        $percentage = round(($found / $total) * 100);
        $passed = $percentage >= 80;

        printTestResult(
            ucfirst($docType) . " - Champs requis",
            $passed,
            "$found/$total ($percentage%)" . (!empty($missing) ? " - Manquants: " . implode(', ', $missing) : '')
        );

        if (!$passed) $allPassed = false;
    }

    $globalResults['scenarios']['required_fields'] = $allPassed;
}

// ============================================================================
// RAPPORT FINAL
// ============================================================================
function printFinalReport(): void {
    global $globalResults;

    $totalTime = round(microtime(true) - $globalResults['start_time'], 2);

    printHeader("RAPPORT FINAL", C_BLUE);

    // Résumé des scénarios
    echo "  " . C_BOLD . "Résultats par Scénario:" . C_RESET . "\n\n";

    $scenarioNames = [
        'individual_extraction' => 'Extraction Individuelle',
        'cache_performance' => 'Performance Cache',
        'name_consistency' => 'Cohérence Noms',
        'date_validation' => 'Validation Dates',
        'resilience' => 'Résilience',
        'batch_extraction' => 'Extraction Batch',
        'required_fields' => 'Champs Requis'
    ];

    foreach ($globalResults['scenarios'] as $key => $passed) {
        $name = $scenarioNames[$key] ?? $key;
        $icon = $passed ? C_GREEN . "✓" . C_RESET : C_RED . "✗" . C_RESET;
        $status = $passed ? C_GREEN . "PASS" . C_RESET : C_RED . "FAIL" . C_RESET;
        echo "    $icon $name: $status\n";
    }

    // Statistiques globales
    echo "\n  " . C_BOLD . "Statistiques:" . C_RESET . "\n\n";

    $passRate = $globalResults['total_tests'] > 0
        ? round(($globalResults['passed'] / $globalResults['total_tests']) * 100, 1)
        : 0;

    echo "    • Tests totaux:    " . C_CYAN . $globalResults['total_tests'] . C_RESET . "\n";
    echo "    • Réussis:         " . C_GREEN . $globalResults['passed'] . C_RESET . "\n";
    echo "    • Échoués:         " . C_RED . $globalResults['failed'] . C_RESET . "\n";
    echo "    • Warnings:        " . C_YELLOW . $globalResults['warnings'] . C_RESET . "\n";
    echo "    • Taux de réussite: " . ($passRate >= 80 ? C_GREEN : C_RED) . "{$passRate}%" . C_RESET . "\n";
    echo "    • Temps total:     " . C_CYAN . "{$totalTime}s" . C_RESET . "\n";

    // Verdict final
    echo "\n";
    $allScenariosPassed = !in_array(false, $globalResults['scenarios'], true);

    if ($allScenariosPassed && $passRate >= 90) {
        echo "  " . C_BG_GREEN . C_WHITE . C_BOLD . " ✓ TOUS LES TESTS RÉUSSIS " . C_RESET . "\n";
    } elseif ($passRate >= 70) {
        echo "  " . C_YELLOW . C_BOLD . "⚠ TESTS PARTIELLEMENT RÉUSSIS ({$passRate}%)" . C_RESET . "\n";
    } else {
        echo "  " . C_BG_RED . C_WHITE . C_BOLD . " ✗ TESTS ÉCHOUÉS ({$passRate}%) " . C_RESET . "\n";
    }

    echo "\n";
}

// ============================================================================
// EXÉCUTION PRINCIPALE
// ============================================================================

echo C_BOLD . C_CYAN;
echo "
╔══════════════════════════════════════════════════════════════════════════╗
║                                                                          ║
║   ████████╗███████╗███████╗████████╗    ███████╗██╗   ██╗██╗████████╗   ║
║   ╚══██╔══╝██╔════╝██╔════╝╚══██╔══╝    ██╔════╝██║   ██║██║╚══██╔══╝   ║
║      ██║   █████╗  ███████╗   ██║       ███████╗██║   ██║██║   ██║      ║
║      ██║   ██╔══╝  ╚════██║   ██║       ╚════██║██║   ██║██║   ██║      ║
║      ██║   ███████╗███████║   ██║       ███████║╚██████╔╝██║   ██║      ║
║      ╚═╝   ╚══════╝╚══════╝   ╚═╝       ╚══════╝ ╚═════╝ ╚═╝   ╚═╝      ║
║                                                                          ║
║                    VISA CHATBOT - OCR TEST SUITE                         ║
║                         Multi-Scénarios v1.0                             ║
╚══════════════════════════════════════════════════════════════════════════╝
" . C_RESET . "\n";

echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Dossier test: $testDir\n";
echo "Cache: $cacheDir\n";

// Vérifier les fichiers de test
$missingFiles = [];
foreach ($documents as $type => $filename) {
    if (!file_exists($testDir . $filename)) {
        $missingFiles[] = $filename;
    }
}

if (!empty($missingFiles)) {
    echo "\n" . C_RED . "ERREUR: Fichiers manquants:" . C_RESET . "\n";
    foreach ($missingFiles as $file) {
        echo "  • $file\n";
    }
    exit(1);
}

echo C_GREEN . "✓ Tous les fichiers de test présents" . C_RESET . "\n";

// Exécution des scénarios
$extractedData = scenario1_individualExtraction($documents, $testDir);
scenario2_cachePerformance($documents, $testDir, $cacheDir);
scenario3_nameConsistency($extractedData);
scenario4_dateValidation($extractedData);
scenario5_resilience();
scenario6_batchExtraction($documents, $testDir);
scenario7_requiredFields($extractedData);

// Rapport final
printFinalReport();
