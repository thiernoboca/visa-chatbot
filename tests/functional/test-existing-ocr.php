<?php
/**
 * Test de l'API OCR existante (passport-ocr-module)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fichier de test
// Utiliser le vrai scan de passeport (PDF)
$testFile = '/Users/cheickmouhamedelhadykane/Downloads/test/passportpassport-scan.pdf';

if (!file_exists($testFile)) {
    die("Fichier non trouvé: $testFile");
}

// Préparer la requête vers l'API existante
$imageContent = file_get_contents($testFile);
$base64Image = base64_encode($imageContent);
$mimeType = mime_content_type($testFile);

$apiUrl = 'http://localhost:8888/hunyuanocr/passport-ocr-module/php/api-handler.php';

$payload = json_encode([
    'action' => 'extract_passport',
    'image' => $base64Image,
    'mime_type' => $mimeType
]);

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test API OCR Existante</title>
    <style>
        body { font-family: -apple-system, sans-serif; background: #1a1a2e; color: #e0e0e0; padding: 2rem; }
        h1 { color: #4ade80; }
        .result { background: rgba(0,0,0,0.3); padding: 1rem; border-radius: 8px; margin: 1rem 0; }
        .success { border-left: 3px solid #4ade80; }
        .error { border-left: 3px solid #ef4444; }
        pre { white-space: pre-wrap; word-wrap: break-word; font-size: 0.85rem; max-height: 600px; overflow-y: auto; }
        img { max-width: 300px; border-radius: 8px; margin: 1rem 0; }
    </style>
</head>
<body>
    <h1>Test API OCR Existante (passport-ocr-module)</h1>

    <div class="result">
        <h3>Fichier testé:</h3>
        <p><?php echo basename($testFile); ?> (<?php echo round(strlen($imageContent)/1024, 1); ?> KB)</p>
        <img src="data:<?php echo $mimeType; ?>;base64,<?php echo $base64Image; ?>" alt="Passport">
    </div>

    <div class="result">
        <h3>API Response (HTTP <?php echo $httpCode; ?>):</h3>
        <?php if ($error): ?>
            <p style="color: #ef4444;">cURL Error: <?php echo $error; ?></p>
        <?php endif; ?>
        <pre><?php
        $decoded = json_decode($response, true);
        if ($decoded) {
            echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            echo htmlspecialchars($response);
        }
        ?></pre>
    </div>
</body>
</html>
