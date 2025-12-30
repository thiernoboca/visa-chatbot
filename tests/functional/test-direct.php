<?php
/**
 * Test direct des extracteurs avec des fichiers locaux
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Charger les dépendances
require_once __DIR__ . '/php/config.php';
require_once __DIR__ . '/php/document-extractor.php';

// Fichiers de test
$testDir = '/Users/cheickmouhamedelhadykane/Downloads/test/';
$testFiles = [
    'passport' => $testDir . 'passportpassport-scan.pdf',
    'ticket' => $testDir . 'billetelectronic-ticket-receipt-december-28-for-mr-gezahegn-mogesejigu.pdf',
    'hotel' => $testDir . 'hotelgmail-thanks-your-booking-is-confirmed-at-appartement-1-a-3-pieces-equipe-cosy-calme-aigle.pdf',
    'vaccination' => $testDir . 'vaccinationyellow-faver-certificate-gezahegn-moges.pdf',
    'invitation' => $testDir . 'ordremissioninvitation-letter-gezahegn-moges-ejigu.pdf',
    'passport_photo' => $testDir . 'gezahegn-moges-20251221-175604-694834b4e6da1-passport-photo.jpg'
];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test Direct Extracteurs</title>
    <style>
        body { font-family: -apple-system, sans-serif; background: #1a1a2e; color: #e0e0e0; padding: 2rem; }
        h1 { color: #4ade80; }
        .test { background: rgba(255,255,255,0.05); padding: 1rem; margin: 1rem 0; border-radius: 8px; }
        .test h3 { color: #60a5fa; margin-bottom: 0.5rem; }
        .success { border-left: 3px solid #4ade80; }
        .error { border-left: 3px solid #ef4444; }
        pre { background: rgba(0,0,0,0.3); padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: 0.85rem; max-height: 400px; overflow-y: auto; }
        .file-info { color: #94a3b8; font-size: 0.85rem; margin-bottom: 0.5rem; }
    </style>
</head>
<body>
    <h1>Test Direct des Extracteurs OCR</h1>

    <?php
    // Sélectionner le type à tester
    $testType = $_GET['type'] ?? 'passport_photo';

    echo "<p>Types disponibles: ";
    foreach (array_keys($testFiles) as $type) {
        $active = ($type === $testType) ? ' style="color:#4ade80;font-weight:bold;"' : '';
        echo "<a href='?type=$type'$active>$type</a> | ";
    }
    echo "</p>";

    $filePath = $testFiles[$testType] ?? null;

    if (!$filePath || !file_exists($filePath)) {
        echo "<div class='test error'><h3>Erreur</h3><p>Fichier non trouvé: $filePath</p></div>";
        exit;
    }

    $fileInfo = pathinfo($filePath);
    $fileSize = filesize($filePath);
    $mimeType = mime_content_type($filePath);

    echo "<div class='test'>";
    echo "<h3>Fichier: " . basename($filePath) . "</h3>";
    echo "<div class='file-info'>Type: $mimeType | Taille: " . round($fileSize/1024, 1) . " KB</div>";
    echo "</div>";

    // Déterminer le type de document pour l'extracteur
    $docType = $testType;
    if ($testType === 'passport_photo') {
        $docType = 'passport';
    }

    try {
        $startTime = microtime(true);

        // Lire et encoder le fichier
        $fileContent = file_get_contents($filePath);
        $base64Content = base64_encode($fileContent);

        // Créer l'extracteur
        $extractor = new DocumentExtractor(['debug' => true]);

        // Extraire
        $result = $extractor->extract($docType, $base64Content, $mimeType, false);

        $duration = round((microtime(true) - $startTime) * 1000);

        echo "<div class='test success'>";
        echo "<h3>✅ Extraction réussie en {$duration}ms</h3>";
        echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        echo "</div>";

    } catch (Exception $e) {
        echo "<div class='test error'>";
        echo "<h3>❌ Erreur</h3>";
        echo "<pre>" . $e->getMessage() . "\n\n" . $e->getTraceAsString() . "</pre>";
        echo "</div>";
    } catch (Error $e) {
        echo "<div class='test error'>";
        echo "<h3>❌ Erreur Fatale</h3>";
        echo "<pre>" . $e->getMessage() . "\n\n" . $e->getTraceAsString() . "</pre>";
        echo "</div>";
    }
    ?>
</body>
</html>
