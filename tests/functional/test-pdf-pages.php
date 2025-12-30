<?php
/**
 * Test pour voir le nombre de pages du PDF vaccination
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/php/config.php';
require_once __DIR__ . '/php/document-extractor.php';

$testDir = '/Users/cheickmouhamedelhadykane/Downloads/test/';
$file = 'vaccinationyellow-faver-certificate-gezahegn-moges.pdf';
$filePath = $testDir . $file;

$extractor = new DocumentExtractor(['debug' => false]);

$reflection = new ReflectionClass($extractor);
$pdfProp = $reflection->getProperty('pdfConverter');
$gvProp = $reflection->getProperty('googleVision');
$pdfConverter = $pdfProp->getValue($extractor);
$googleVision = $gvProp->getValue($extractor);

$content = file_get_contents($filePath);
$base64 = base64_encode($content);

// Compter les pages
$pageCount = $pdfConverter->getPageCount($base64);

echo "\033[1m\033[36m═══════════════════════════════════════════════════════════════════\033[0m\n";
echo "\033[1mANALYSE PDF VACCINATION\033[0m\n";
echo "\033[1m\033[36m═══════════════════════════════════════════════════════════════════\033[0m\n\n";

echo "Fichier: $file\n";
echo "Taille: " . round(strlen($content)/1024, 1) . " KB\n";
echo "Pages détectées: $pageCount\n\n";

// Extraire chaque page
for ($page = 1; $page <= min($pageCount, 5); $page++) {
    echo "\033[1m--- PAGE $page ---\033[0m\n";

    $conv = $pdfConverter->convertToImage($base64, $page);
    if ($conv['success']) {
        echo "Image: " . round($conv['size']/1024, 1) . " KB\n";

        $ocr = $googleVision->extractText($conv['image'], $conv['mime_type']);
        echo "OCR chars: " . strlen($ocr['full_text']) . "\n";
        echo "Confiance: " . round($ocr['confidence'] * 100, 1) . "%\n";

        // Montrer les 500 premiers caractères
        $preview = substr($ocr['full_text'], 0, 500);
        echo "Aperçu: " . $preview . "\n";

        // Chercher des indices du nom et de la date
        $hasName = preg_match('/GEZAHEGN|MOGES|EJIGU/i', $ocr['full_text']);
        $hasDate = preg_match('/\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}/', $ocr['full_text']);
        $hasVaccine = preg_match('/YELLOW|FEVER|JAUNE|AMARIL/i', $ocr['full_text']);

        echo "Indices trouvés: ";
        if ($hasName) echo "NOM ";
        if ($hasDate) echo "DATE ";
        if ($hasVaccine) echo "VACCIN ";
        echo "\n\n";
    } else {
        echo "Erreur conversion page $page\n\n";
    }
}
