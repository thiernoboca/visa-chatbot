<?php
/**
 * Debug script to trace passport extraction
 */

namespace VisaChatbot\Extractors;

require_once __DIR__ . '/extractors/AbstractExtractor.php';
require_once __DIR__ . '/extractors/PassportExtractor.php';

// Use sample Ethiopian passport text (simulating OCR output)
$ocrText = null;
if (!$ocrText) {
    // Fallback: use sample Ethiopian passport text
    $ocrText = <<<'OCR'
FEDERAL DEMOCRATIC REPUBLIC
OF ETHIOPIA
ስው
PASSPORT/PASSEPORT
ÉTHIOPIE
PASSEPOP
Types Type P
Code du pays/Country Code ETH
Passport No. EQ1799898
/Passport No.
Surname/Nom EJIGU
Woreda/Sous-Préfecture
/Sub-City/
OROMIA
Given Names/ Prénoms GEZAHEGN MOGES
Spouse
Nationality/Nationalité ETHIOPIAN ADDIS ABABA
Date of Birth/ Date de Naissance 22 AUG 95
Sex/Sexe M
22/08/1995
M
Place of Birth / Lieu de Naissance ETHIOPIA/OROMIA
Date of issue /Date de Délivrance 16 SEP 25
16/09/2025
Date of Expiry / Date d'Expiration
16 SEP 30
16/09/2030
Issuing Authority / Autorité Autorisée
Signature of Bearer/
Signature du Titulaire
IMMIGRATION AND
CITIZENSHIP SERVICES
P<ETHEJ I GU<<GEZAHEGN<MOGES<<<<<<<<<<<<<<<<
EQ17998982ETH9508223M3009169<<<<<<<<<<<<<<04
OCR;
}

echo "=== PASSPORT EXTRACTION DEBUG ===\n\n";
echo "OCR Text Length: " . strlen($ocrText) . " bytes\n\n";

$extractor = new PassportExtractor();
$result = $extractor->extract($ocrText);

echo "=== RESULT STRUCTURE ===\n";
echo "Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
echo "Passport Type: " . ($result['passport_type'] ?? 'N/A') . "\n\n";

echo "=== MRZ DATA ===\n";
if ($result['mrz']) {
    echo "MRZ Raw:\n";
    print_r($result['mrz']['raw'] ?? 'N/A');
    echo "\nMRZ Parsed:\n";
    print_r($result['mrz']['parsed'] ?? []);
    echo "\nMRZ Checksums Valid: " . ($result['mrz']['checksums_valid'] ? 'Yes' : 'No') . "\n";
} else {
    echo "MRZ: NOT FOUND\n";
}

echo "\n=== VIZ DATA ===\n";
print_r($result['viz'] ?? []);

echo "\n=== MERGED EXTRACTED DATA ===\n";
print_r($result['extracted'] ?? []);

echo "\n=== KEY FIELDS ===\n";
echo "Passport Number: " . ($result['extracted']['passport_number'] ?? 'N/A') . "\n";
echo "Surname: " . ($result['extracted']['surname'] ?? 'N/A') . "\n";
echo "Given Names: " . ($result['extracted']['given_names'] ?? 'N/A') . "\n";
echo "Date of Birth: " . ($result['extracted']['date_of_birth'] ?? 'N/A') . "\n";
echo "Expiry Date: " . ($result['extracted']['expiry_date'] ?? 'N/A') . "\n";
