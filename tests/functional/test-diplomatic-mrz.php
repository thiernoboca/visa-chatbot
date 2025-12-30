<?php
/**
 * Test Passeport Diplomatique CIV - Données extraites de l'image
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/php/config.php';
require_once __DIR__ . '/php/gemini-client.php';

echo "=== Test Passeport Diplomatique CIV ===\n\n";

// Données OCR simulées basées sur l'image du passeport diplomatique
$ocrText = <<<OCR
RÉPUBLIQUE DE CÔTE D'IVOIRE

Passeport Diplomatique
Diplomatic Passport

Type/Type: PD-AE
Code du pays/Country code: CIV
Passport N°/Passport N°: 22DD02151

Nom/Surname: N'GORAN-THECKLY NEE YOBOUE
Prénoms/Given names: PATRICIA AMOIN

Nationalité/Nationality: IVOIRIENNE
Sexe/Sex: F

Date de naissance/Date of birth: 28 04 63
Lieu de naissance/Place of birth: ABIDJAN

Date de délivrance/Date of issue: 30 07 24
Date d'expiration/Date of expiry: 29 07 27

Autorité/Authority: MAE / SPO

Fonction/Function:
EPOUSE DE M N'GORAN-THECKLY YVES
MAGISTRAT HORS HIERARCHIE

Signes particuliers/Distinguishing marks: VOIR PHOTO

MRZ:
PDCIVNGORAN<THECKLY<NEE<YOBOUE<<PATRICIA<AMO
22DD021519CIV6304283F2707299<<<<<<<<<<<<<<00
OCR;

echo "Texte OCR:\n$ocrText\n\n";

$gemini = new GeminiClient(['debug' => true]);

echo "=== Structuration avec Gemini 3 Flash (LOW thinking) ===\n\n";

$startTime = microtime(true);
$result = $gemini->structureDocument($ocrText, 'passport');
$duration = round(microtime(true) - $startTime, 2);

echo "Durée: {$duration}s\n";
echo "Thinking: " . ($result['_metadata']['thinking_level'] ?? 'N/A') . "\n\n";

// Classification
if (isset($result['document_classification'])) {
    $class = $result['document_classification'];
    echo "📋 CLASSIFICATION:\n";
    echo "   Catégorie: " . ($class['category'] ?? 'N/A') . "\n";
    echo "   Sous-type: " . ($class['subcategory'] ?? 'N/A') . "\n";
    echo "   Pays émetteur: " . ($class['issuing_country'] ?? 'N/A') . "\n";
    echo "   Confiance: " . (isset($class['confidence']) ? round($class['confidence'] * 100) . "%" : 'N/A') . "\n\n";
}

// Eligibility
if (isset($result['visa_eligibility'])) {
    $elig = $result['visa_eligibility'];
    echo "🎫 ÉLIGIBILITÉ e-VISA:\n";
    echo "   Valide: " . (isset($elig['is_valid']) ? ($elig['is_valid'] ? '✅ OUI' : '❌ NON') : 'N/A') . "\n";
    echo "   Workflow: " . ($elig['workflow'] ?? 'N/A') . "\n";
    echo "   Raison FR: " . ($elig['reason_fr'] ?? 'N/A') . "\n";
    echo "   Raison EN: " . ($elig['reason_en'] ?? 'N/A') . "\n\n";
}

// MRZ parsed
if (isset($result['mrz_data']['parsed'])) {
    $mrz = $result['mrz_data']['parsed'];
    echo "📊 MRZ PARSÉ:\n";
    echo "   Type doc: " . ($mrz['document_type'] ?? 'N/A') . "\n";
    echo "   État émetteur: " . ($mrz['issuing_state'] ?? 'N/A') . "\n";
    echo "   Nom: " . ($mrz['surname'] ?? 'N/A') . "\n";
    echo "   Prénoms: " . ($mrz['given_names'] ?? 'N/A') . "\n";
    echo "   N° Document: " . ($mrz['document_number'] ?? 'N/A') . "\n";
    echo "   Nationalité: " . ($mrz['nationality'] ?? 'N/A') . "\n";
    echo "   Date naissance: " . ($mrz['date_of_birth'] ?? 'N/A') . "\n";
    echo "   Date expiration: " . ($mrz['date_of_expiry'] ?? 'N/A') . "\n";
    echo "   Sexe: " . ($mrz['sex'] ?? 'N/A') . "\n\n";
}

// Fields extraits
echo "📝 CHAMPS FINAUX:\n";
$fields = $result['fields'] ?? [];
foreach ($fields as $key => $field) {
    $value = is_array($field) ? ($field['value'] ?? json_encode($field)) : $field;
    if (!empty($value) && $value !== 'null' && $value !== null) {
        $conf = is_array($field) && isset($field['confidence']) ? round($field['confidence'] * 100) . "%" : '';
        $src = is_array($field) ? ($field['source'] ?? '') : '';
        echo "   - $key: $value";
        if ($conf) echo " ($conf, $src)";
        echo "\n";
    }
}

// Cross-validation
if (isset($result['cross_validation'])) {
    echo "\n🔄 CROSS-VALIDATION MRZ↔VIZ:\n";
    foreach ($result['cross_validation'] as $field => $data) {
        if (is_array($data) && isset($data['match'])) {
            $status = $data['match'] ? '✅' : '⚠️';
            echo "   $status $field: VIZ={$data['viz']} | MRZ={$data['mrz']} → {$data['final']}\n";
        }
    }
}

echo "\n=== Test terminé ===\n";
