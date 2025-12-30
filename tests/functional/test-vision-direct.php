<?php
/**
 * Test direct Google Vision - voir le texte OCR brut
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(120);

require_once __DIR__ . '/php/config.php';
require_once __DIR__ . '/php/document-extractor.php';

$testDir = '/Users/cheickmouhamedelhadykane/Downloads/test/';

// On instancie le DocumentExtractor pour accéder aux classes internes
$extractor = new DocumentExtractor(['debug' => false]);

// Définir les documents à tester
$documents = [
    'hotel' => 'hotelgmail-thanks-your-booking-is-confirmed-at-appartement-1-a-3-pieces-equipe-cosy-calme-aigle.pdf',
    'vaccination' => 'vaccinationyellow-faver-certificate-gezahegn-moges.pdf'
];

echo "\n\033[1m\033[36m═══════════════════════════════════════════════════════════════════\033[0m\n";
echo "\033[1mTEST GOOGLE VISION OCR DIRECT\033[0m\n";
echo "\033[1m\033[36m═══════════════════════════════════════════════════════════════════\033[0m\n\n";

foreach ($documents as $type => $filename) {
    echo "\033[1m\033[33m--- " . strtoupper($type) . " ---\033[0m\n";
    echo "Fichier: $filename\n";

    $filePath = $testDir . $filename;

    if (!file_exists($filePath)) {
        echo "\033[31mFichier non trouvé\033[0m\n\n";
        continue;
    }

    $content = file_get_contents($filePath);
    $base64 = base64_encode($content);
    $mimeType = mime_content_type($filePath);

    echo "Type MIME: $mimeType\n";
    echo "Taille: " . round(strlen($content)/1024, 1) . " KB\n\n";

    // Lancer l'extraction et capturer le output debug
    ob_start();

    try {
        // Créer un extractor avec debug pour voir les logs
        $debugExtractor = new DocumentExtractor(['debug' => true]);
        $result = $debugExtractor->extract($type, $base64, $mimeType, false);

        $debugOutput = ob_get_clean();

        // Afficher juste les lignes pertinentes du debug
        $lines = explode("\n", $debugOutput);
        foreach ($lines as $line) {
            if (strpos($line, 'chars_extracted') !== false ||
                strpos($line, 'Extraction réussie') !== false ||
                strpos($line, 'OCR') !== false) {
                echo "$line\n";
            }
        }

        // Afficher les données extraites
        echo "\n\033[1mCHAMPS EXTRAITS:\033[0m\n";

        if ($type === 'hotel') {
            echo "  guest_name: " . ($result['guest_name'] ?? 'NULL') . "\n";
            echo "  hotel_name: " . ($result['hotel_name'] ?? 'NULL') . "\n";
            echo "  hotel_city: " . ($result['hotel_city'] ?? 'NULL') . "\n";
            echo "  \033[33mcheck_in_date: " . ($result['check_in_date'] ?? 'NULL') . "\033[0m\n";
            echo "  \033[33mcheck_out_date: " . ($result['check_out_date'] ?? 'NULL') . "\033[0m\n";
            echo "  confirmation_number: " . ($result['confirmation_number'] ?? 'NULL') . "\n";
        } elseif ($type === 'vaccination') {
            echo "  \033[33mholder_name: " . ($result['holder_name'] ?? 'NULL') . "\033[0m\n";
            echo "  vaccine_type: " . ($result['vaccine_type'] ?? 'NULL') . "\n";
            echo "  \033[33mvaccination_date: " . ($result['vaccination_date'] ?? 'NULL') . "\033[0m\n";
            echo "  certificate_number: " . ($result['certificate_number'] ?? 'NULL') . "\n";
            echo "  center_name: " . ($result['center_name'] ?? 'NULL') . "\n";
        }

    } catch (Exception $e) {
        ob_end_clean();
        echo "\033[31mErreur: " . $e->getMessage() . "\033[0m\n";
    }

    echo "\n";
}

echo "\033[1m\033[32m═══ ANALYSE ═══\033[0m\n\n";
echo "Les champs en JAUNE sont ceux qui retournent NULL.\n";
echo "Le problème est probablement:\n";
echo "1. Les dates ne sont pas sur la première page du PDF\n";
echo "2. Le texte OCR ne contient pas les marqueurs attendus\n";
echo "3. Le format des dates n'est pas reconnu par Gemini\n\n";

echo "Solution possible: Extraire TOUTES les pages du PDF, pas juste la première.\n";
