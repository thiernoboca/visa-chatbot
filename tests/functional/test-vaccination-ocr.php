<?php
/**
 * Test pour voir le texte OCR brut du certificat de vaccination
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/php/config.php';
require_once __DIR__ . '/php/document-extractor.php';

$testDir = '/Users/cheickmouhamedelhadykane/Downloads/test/';
$file = 'vaccinationyellow-faver-certificate-gezahegn-moges.pdf';
$filePath = $testDir . $file;

$extractor = new DocumentExtractor(['debug' => false]);

// Accéder à Google Vision via reflection
$reflection = new ReflectionClass($extractor);
$gvProp = $reflection->getProperty('googleVision');
$pdfProp = $reflection->getProperty('pdfConverter');
$googleVision = $gvProp->getValue($extractor);
$pdfConverter = $pdfProp->getValue($extractor);

$content = file_get_contents($filePath);
$base64 = base64_encode($content);

// Convertir PDF et faire OCR
$conv = $pdfConverter->convertToImage($base64, 1);
if ($conv['success']) {
    $ocr = $googleVision->extractText($conv['image'], $conv['mime_type']);

    echo "\033[1m\033[36m═══════════════════════════════════════════════════════════════════\033[0m\n";
    echo "\033[1mTEXTE OCR BRUT - CERTIFICAT VACCINATION\033[0m\n";
    echo "\033[1m\033[36m═══════════════════════════════════════════════════════════════════\033[0m\n\n";

    echo "Confiance OCR: " . round($ocr['confidence'] * 100, 1) . "%\n";
    echo "Caractères: " . strlen($ocr['full_text']) . "\n\n";

    echo "─────────────────────────────────────────────────────────────────\n";
    echo $ocr['full_text'];
    echo "\n─────────────────────────────────────────────────────────────────\n";
}
