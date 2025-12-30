<?php
/**
 * CLI Test détaillé pour un document
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

$testType = $argv[1] ?? 'ticket';
$file = $testDir . ($documents[$testType] ?? $documents['ticket']);

echo "=== Test détaillé: $testType ===\n";
echo "Fichier: " . basename($file) . "\n\n";

$extractor = new DocumentExtractor(['debug' => true]);

$content = file_get_contents($file);
$base64 = base64_encode($content);
$mimeType = mime_content_type($file);

$result = $extractor->extract($testType, $base64, $mimeType, false);

echo "\n=== Résultat complet (JSON) ===\n";
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n";
