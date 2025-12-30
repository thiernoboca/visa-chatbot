<?php
/**
 * Script de Debug - Voir la réponse Gemini brute
 * Pour identifier pourquoi certains champs sont N/A
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(300);

require_once __DIR__ . '/php/config.php';
require_once __DIR__ . '/php/document-extractor.php';

// Couleurs
define('GREEN', "\033[32m");
define('RED', "\033[31m");
define('YELLOW', "\033[33m");
define('CYAN', "\033[36m");
define('BOLD', "\033[1m");
define('RESET', "\033[0m");

$testDir = '/Users/cheickmouhamedelhadykane/Downloads/test/';

// Documents à débugger
$documents = [
    'hotel' => 'hotelgmail-thanks-your-booking-is-confirmed-at-appartement-1-a-3-pieces-equipe-cosy-calme-aigle.pdf',
    'vaccination' => 'vaccinationyellow-faver-certificate-gezahegn-moges.pdf'
];

$extractor = new DocumentExtractor(['debug' => true]);

foreach ($documents as $type => $filename) {
    echo BOLD . CYAN . "\n═══════════════════════════════════════════════════════════════════\n";
    echo "DEBUG: " . strtoupper($type) . "\n";
    echo "═══════════════════════════════════════════════════════════════════" . RESET . "\n\n";

    $filePath = $testDir . $filename;

    if (!file_exists($filePath)) {
        echo RED . "Fichier non trouvé: $filename" . RESET . "\n";
        continue;
    }

    echo "📄 Fichier: " . YELLOW . $filename . RESET . "\n\n";

    // Charger le fichier
    $content = file_get_contents($filePath);
    $base64 = base64_encode($content);
    $mimeType = mime_content_type($filePath);

    try {
        echo "Extraction en cours...\n";
        $startTime = microtime(true);
        $result = $extractor->extract($type, $base64, $mimeType, false);
        $duration = round(microtime(true) - $startTime, 2);

        echo GREEN . "✓ Extraction réussie en {$duration}s" . RESET . "\n\n";

        echo BOLD . "RÉPONSE COMPLÈTE (JSON):" . RESET . "\n";
        echo "─────────────────────────────────────────────────────────────────\n";

        // Afficher tout le résultat sans les métadonnées
        $displayResult = $result;
        unset($displayResult['_metadata']);

        echo json_encode($displayResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        echo "─────────────────────────────────────────────────────────────────\n\n";

        // Analyser les champs attendus
        echo BOLD . "ANALYSE DES CHAMPS ATTENDUS:" . RESET . "\n";

        if ($type === 'hotel') {
            $expectedFields = [
                'guest_name',
                'hotel_name',
                'hotel_city',
                'check_in_date',
                'check_out_date',
                'confirmation_number'
            ];
        } elseif ($type === 'vaccination') {
            $expectedFields = [
                'holder_name',
                'vaccine_type',
                'vaccination_date',
                'certificate_number',
                'valid'
            ];
        } else {
            $expectedFields = [];
        }

        foreach ($expectedFields as $field) {
            // Chercher le champ à différents niveaux
            $value = null;

            // Niveau racine
            if (isset($result[$field])) {
                $value = $result[$field];
            }
            // Dans 'reservation' pour hotel
            elseif (isset($result['reservation'][$field])) {
                $value = $result['reservation'][$field];
            }
            // Dans 'hotel' pour hotel
            elseif (isset($result['hotel'][$field])) {
                $value = $result['hotel'][$field];
            }
            // Dans 'guest' pour hotel
            elseif (isset($result['guest'][$field])) {
                $value = $result['guest'][$field];
            }
            // Dans 'holder' pour vaccination
            elseif (isset($result['holder'][$field])) {
                $value = $result['holder'][$field];
            }
            // Dans 'yellow_fever' pour vaccination
            elseif (isset($result['yellow_fever'][$field])) {
                $value = $result['yellow_fever'][$field];
            }

            $status = ($value !== null && $value !== '' && $value !== 'null')
                ? GREEN . "✓"
                : RED . "✗ MANQUANT";

            $displayValue = is_array($value) ? json_encode($value) : ($value ?? 'null');
            echo "   $status $field: " . CYAN . $displayValue . RESET . "\n";
        }

        // Afficher toutes les clés disponibles
        echo "\n" . BOLD . "CLÉS DISPONIBLES DANS LA RÉPONSE:" . RESET . "\n";
        $allKeys = array_keys($displayResult);
        foreach ($allKeys as $key) {
            $val = $displayResult[$key];
            if (is_array($val)) {
                echo "   📁 $key: " . YELLOW . "[" . implode(', ', array_keys($val)) . "]" . RESET . "\n";
            } else {
                echo "   📄 $key: " . CYAN . json_encode($val) . RESET . "\n";
            }
        }

    } catch (Exception $e) {
        echo RED . "Erreur: " . $e->getMessage() . RESET . "\n";
        echo $e->getTraceAsString() . "\n";
    }

    echo "\n";
}

echo BOLD . GREEN . "\n═══ DEBUG TERMINÉ ═══\n" . RESET;
