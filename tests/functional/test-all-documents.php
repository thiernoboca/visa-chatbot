<?php
/**
 * Test complet de tous les documents avec analyse des pages
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

// Champs attendus par type avec chemins d'accès (notation pointée pour imbriqués)
$expectedFields = [
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
        'hotel_city' => 'Ville',
        'check_in_date' => 'Check-in',
        'check_out_date' => 'Check-out',
        'confirmation_number' => 'N° Confirmation'
    ],
    'vaccination' => [
        'holder_name' => 'Titulaire',
        'vaccine_type' => 'Type vaccin',
        'vaccination_date' => 'Date vaccination',
        'certificate_number' => 'N° Certificat',
        'valid' => 'Valide'
    ],
    'invitation' => [
        'invitee.name' => 'Invité',
        'inviter.name' => 'Invitant',
        'purpose' => 'Objet',
        'dates' => 'Dates'
    ]
];

// Fonction pour accéder aux valeurs imbriquées avec notation pointée
function getNestedValue(array $array, string $path) {
    $keys = explode('.', $path);
    $value = $array;
    foreach ($keys as $key) {
        if (!isset($value[$key])) {
            return null;
        }
        $value = $value[$key];
    }
    return $value;
}

$extractor = new DocumentExtractor(['debug' => false]);

// Accéder au PdfConverter via reflection
$reflection = new ReflectionClass($extractor);
$pdfProp = $reflection->getProperty('pdfConverter');
$pdfConverter = $pdfProp->getValue($extractor);

echo "\n\033[1m\033[36m═══════════════════════════════════════════════════════════════════\033[0m\n";
echo "\033[1mTEST COMPLET - TOUS LES DOCUMENTS (avec cross-validation)\033[0m\n";
echo "\033[1m\033[36m═══════════════════════════════════════════════════════════════════\033[0m\n\n";

$results = [];
$passportData = null;

// Extraire le passeport en premier pour la cross-validation
$passportFile = $testDir . $documents['passport'];
if (file_exists($passportFile)) {
    echo "\033[1m\033[35m━━━ PRÉ-EXTRACTION PASSEPORT POUR CROSS-VALIDATION ━━━\033[0m\n";
    $passportContent = file_get_contents($passportFile);
    $passportBase64 = base64_encode($passportContent);
    $passportMime = mime_content_type($passportFile);

    try {
        $passportResult = $extractor->extract('passport', $passportBase64, $passportMime, false);
        $passportData = $passportResult;
        $extractor->setPassportData($passportResult);

        $fullName = '';
        if (isset($passportResult['fields']['surname']['value'])) {
            $fullName = $passportResult['fields']['surname']['value'] . ' ' .
                ($passportResult['fields']['given_names']['value'] ?? '');
        }
        echo "\033[32m✓ Passeport chargé: $fullName\033[0m\n";
        echo "\033[36m→ Cross-validation activée pour les autres documents\033[0m\n\n";
    } catch (Exception $e) {
        echo "\033[33m⚠ Cross-validation désactivée: " . $e->getMessage() . "\033[0m\n\n";
    }
}

foreach ($documents as $type => $filename) {
    $filePath = $testDir . $filename;

    if (!file_exists($filePath)) {
        echo "\033[31m✗ $type: Fichier non trouvé\033[0m\n\n";
        continue;
    }

    echo "\033[1m\033[33m─────────────────────────────────────────────────────────────────\033[0m\n";
    echo "\033[1m📄 " . strtoupper($type) . "\033[0m\n";
    echo "\033[1m\033[33m─────────────────────────────────────────────────────────────────\033[0m\n";
    echo "Fichier: $filename\n";

    $content = file_get_contents($filePath);
    $base64 = base64_encode($content);
    $mimeType = mime_content_type($filePath);

    // Compter les pages
    $pageCount = 1;
    if ($mimeType === 'application/pdf') {
        $pageCount = $pdfConverter->getPageCount($base64);
    }

    echo "Type MIME: $mimeType\n";
    echo "Taille: " . round(strlen($content)/1024, 1) . " KB\n";
    echo "\033[1mPages: $pageCount\033[0m\n\n";

    // Extraction
    $startTime = microtime(true);

    try {
        $result = $extractor->extract($type, $base64, $mimeType, false);
        $duration = round(microtime(true) - $startTime, 2);

        echo "\033[32m✓ Extraction réussie en {$duration}s\033[0m\n\n";

        // Vérifier les champs attendus
        $expected = $expectedFields[$type] ?? [];
        $found = 0;
        $missing = [];

        foreach ($expected as $fieldPath => $label) {
            $value = getNestedValue($result, $fieldPath);
            if ($value !== null && $value !== '' && $value !== 'N/A') {
                $found++;
                $displayValue = is_bool($value) ? ($value ? 'true' : 'false') : $value;
                if (is_array($displayValue)) {
                    $displayValue = json_encode($displayValue);
                }
                if (strlen($displayValue) > 50) {
                    $displayValue = substr($displayValue, 0, 47) . '...';
                }
                echo "   \033[32m✓ $label:\033[0m \033[36m$displayValue\033[0m\n";
            } else {
                $missing[] = $label;
                echo "   \033[31m✗ $label:\033[0m NULL\n";
            }
        }

        $total = count($expected);
        $percentage = $total > 0 ? round(($found / $total) * 100) : 0;

        echo "\n\033[1mScore: $found/$total ($percentage%)\033[0m\n";

        if (!empty($missing)) {
            echo "\033[33mChamps manquants: " . implode(', ', $missing) . "\033[0m\n";
        }

        // Afficher les cross-validations si présentes
        $crossValidated = false;
        if (isset($result['_metadata']['cross_validation'])) {
            $crossValidated = true;
            echo "\n\033[35m⚡ Cross-validation avec passeport:\033[0m\n";
            foreach ($result['_metadata']['cross_validation'] as $field => $cv) {
                $original = $cv['original_name'] ?? 'N/A';
                $completed = $cv['name'] ?? 'N/A';
                $matchType = $cv['match_type'] ?? 'unknown';
                echo "   \033[35m→ $field:\033[0m $original → \033[32m$completed\033[0m ($matchType)\n";
            }
        }

        $results[$type] = [
            'success' => true,
            'pages' => $pageCount,
            'found' => $found,
            'total' => $total,
            'percentage' => $percentage,
            'missing' => $missing,
            'duration' => $duration,
            'confidence' => $result['confidence'] ?? $result['_metadata']['confidence'] ?? null,
            'cross_validated' => $crossValidated
        ];

    } catch (Exception $e) {
        $duration = round(microtime(true) - $startTime, 2);
        echo "\033[31m✗ Erreur: " . $e->getMessage() . "\033[0m\n";

        $results[$type] = [
            'success' => false,
            'pages' => $pageCount,
            'error' => $e->getMessage(),
            'duration' => $duration
        ];
    }

    echo "\n";
}

// Résumé final
echo "\033[1m\033[36m═══════════════════════════════════════════════════════════════════\033[0m\n";
echo "\033[1mRÉSUMÉ FINAL\033[0m\n";
echo "\033[1m\033[36m═══════════════════════════════════════════════════════════════════\033[0m\n\n";

echo "┌─────────────────┬───────┬────────────┬────────────┬────────────┐\n";
echo "│ Document        │ Pages │ Champs     │ Score      │ Temps      │\n";
echo "├─────────────────┼───────┼────────────┼────────────┼────────────┤\n";

foreach ($results as $type => $data) {
    $typePadded = str_pad(strtoupper($type), 15);
    $pagesPadded = str_pad($data['pages'], 5, ' ', STR_PAD_LEFT);

    if ($data['success']) {
        $fieldsPadded = str_pad($data['found'] . '/' . $data['total'], 10);
        $scorePadded = str_pad($data['percentage'] . '%', 10);
        $timePadded = str_pad($data['duration'] . 's', 10);

        $color = $data['percentage'] >= 80 ? "\033[32m" : ($data['percentage'] >= 50 ? "\033[33m" : "\033[31m");
        echo "│ $typePadded │ $pagesPadded │ $fieldsPadded │ $color$scorePadded\033[0m │ $timePadded │\n";
    } else {
        echo "│ $typePadded │ $pagesPadded │ \033[31mERREUR\033[0m     │ \033[31m-\033[0m          │ -          │\n";
    }
}

echo "└─────────────────┴───────┴────────────┴────────────┴────────────┘\n\n";

// Problèmes détectés
$problems = [];
foreach ($results as $type => $data) {
    if (!$data['success']) {
        $problems[] = "$type: " . $data['error'];
    } elseif (!empty($data['missing'])) {
        $problems[] = "$type: champs manquants - " . implode(', ', $data['missing']);
    }
}

if (!empty($problems)) {
    echo "\033[1m\033[33mPROBLÈMES DÉTECTÉS:\033[0m\n";
    foreach ($problems as $problem) {
        echo "  • $problem\n";
    }
} else {
    echo "\033[1m\033[32m✓ TOUS LES DOCUMENTS EXTRAITS AVEC SUCCÈS!\033[0m\n";
}

echo "\n";
