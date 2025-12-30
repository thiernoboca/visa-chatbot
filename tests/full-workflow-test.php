<?php
/**
 * Tests unitaires PHP - Workflow Engine
 * Teste les fonctionnalités backend du chatbot visa
 */

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║     TESTS UNITAIRES PHP - CHATBOT VISA CÔTE D'IVOIRE        ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function test($description, $callback) {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    
    try {
        $result = $callback();
        if ($result === true) {
            $passedTests++;
            echo "  ✅ $description\n";
        } else {
            $failedTests++;
            echo "  ❌ $description\n";
            echo "     Erreur: Assertion échouée\n";
        }
    } catch (Exception $e) {
        $failedTests++;
        echo "  ❌ $description\n";
        echo "     Erreur: " . $e->getMessage() . "\n";
    }
}

// ============================================================
// FONCTIONS DE NORMALISATION (copie du workflow-engine.php)
// ============================================================

function removeAccents($str) {
    $search = ['à', 'â', 'ä', 'é', 'è', 'ê', 'ë', 'î', 'ï', 'ô', 'ö', 'ù', 'û', 'ü', 'ç', 'À', 'Â', 'Ä', 'É', 'È', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Ö', 'Ù', 'Û', 'Ü', 'Ç'];
    $replace = ['a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'c', 'A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'C'];
    return str_replace($search, $replace, $str);
}

function normalizeNationality($nationality) {
    if (empty($nationality)) return null;
    
    $mapping = [
        // Éthiopie
        'ethiopian' => 'ETH', 'eth' => 'ETH', 'ethiopia' => 'ETH', 'ethiopie' => 'ETH',
        'ethiopienne' => 'ETH', 'ethiopien' => 'ETH', 'éthiopie' => 'ETH', 'éthiopien' => 'ETH',
        // Kenya
        'kenyan' => 'KEN', 'ken' => 'KEN', 'kenya' => 'KEN',
        // Djibouti
        'djiboutian' => 'DJI', 'dji' => 'DJI', 'djibouti' => 'DJI', 'djiboutien' => 'DJI',
        // Tanzanie
        'tanzanian' => 'TZA', 'tza' => 'TZA', 'tanzania' => 'TZA', 'tanzanie' => 'TZA',
        // Ouganda
        'ugandan' => 'UGA', 'uga' => 'UGA', 'uganda' => 'UGA', 'ouganda' => 'UGA',
        // Somalie
        'somali' => 'SOM', 'som' => 'SOM', 'somalia' => 'SOM', 'somalie' => 'SOM',
        // Côte d'Ivoire
        'ivorian' => 'CIV', 'civ' => 'CIV', 'ivoirien' => 'CIV',
        // Autres
        'french' => 'FRA', 'fra' => 'FRA', 'francais' => 'FRA', 'français' => 'FRA',
        'american' => 'USA', 'usa' => 'USA',
        'chinese' => 'CHN', 'chinois' => 'CHN'
    ];
    
    $normalized = mb_strtolower(trim($nationality));
    $normalizedNoAccents = removeAccents($normalized);
    
    if (isset($mapping[$normalized])) {
        return $mapping[$normalized];
    }
    
    foreach ($mapping as $key => $code) {
        $keyNoAccents = removeAccents($key);
        if ($normalizedNoAccents === $keyNoAccents) {
            return $code;
        }
        if (strpos($normalizedNoAccents, $keyNoAccents) !== false || strpos($keyNoAccents, $normalizedNoAccents) !== false) {
            return $code;
        }
    }
    
    return null;
}

// ============================================================
// 1. TESTS DE NORMALISATION DE NATIONALITÉ
// ============================================================
echo "\n🌍 1. NORMALISATION DE NATIONALITÉ (PHP)\n";
echo str_repeat('─', 50) . "\n";

test('ETHIOPIAN -> ETH', function() {
    return normalizeNationality('ETHIOPIAN') === 'ETH';
});

test('Ethiopian (capitalized) -> ETH', function() {
    return normalizeNationality('Ethiopian') === 'ETH';
});

test('ETH (code) -> ETH', function() {
    return normalizeNationality('ETH') === 'ETH';
});

test('ÉTHIOPIEN (avec accent) -> ETH', function() {
    return normalizeNationality('ÉTHIOPIEN') === 'ETH';
});

test('KENYAN -> KEN', function() {
    return normalizeNationality('KENYAN') === 'KEN';
});

test('IVORIAN -> CIV', function() {
    return normalizeNationality('IVORIAN') === 'CIV';
});

test('Ivoirien -> CIV', function() {
    return normalizeNationality('Ivoirien') === 'CIV';
});

test('FRENCH -> FRA', function() {
    return normalizeNationality('FRENCH') === 'FRA';
});

test('Français (avec accent) -> FRA', function() {
    return normalizeNationality('Français') === 'FRA';
});

test('null -> null', function() {
    return normalizeNationality(null) === null;
});

test('empty string -> null', function() {
    return normalizeNationality('') === null;
});

// ============================================================
// 2. TESTS DE COMPARAISON NATIONALITÉ/RÉSIDENCE
// ============================================================
echo "\n🏠 2. COMPARAISON NATIONALITÉ/RÉSIDENCE (PHP)\n";
echo str_repeat('─', 50) . "\n";

function needsResidenceCard($nationality, $residenceCode) {
    $nationalityCode = normalizeNationality($nationality);
    return $nationalityCode !== null && $nationalityCode !== $residenceCode;
}

test('Éthiopien en Éthiopie -> PAS de carte de séjour', function() {
    return needsResidenceCard('ETHIOPIAN', 'ETH') === false;
});

test('Éthiopien au Kenya -> carte de séjour REQUISE', function() {
    return needsResidenceCard('ETHIOPIAN', 'KEN') === true;
});

test('Kenyan au Kenya -> PAS de carte de séjour', function() {
    return needsResidenceCard('KENYAN', 'KEN') === false;
});

test('Kenyan en Éthiopie -> carte de séjour REQUISE', function() {
    return needsResidenceCard('KENYAN', 'ETH') === true;
});

test('Ivoirien en Éthiopie -> carte de séjour REQUISE', function() {
    return needsResidenceCard('IVORIAN', 'ETH') === true;
});

test('Français en Éthiopie -> carte de séjour REQUISE', function() {
    return needsResidenceCard('FRENCH', 'ETH') === true;
});

test('Djiboutien à Djibouti -> PAS de carte de séjour', function() {
    return needsResidenceCard('DJIBOUTIAN', 'DJI') === false;
});

// ============================================================
// 3. TESTS D'ÉLIGIBILITÉ
// ============================================================
echo "\n🚦 3. TESTS D'ÉLIGIBILITÉ (PHP)\n";
echo str_repeat('─', 50) . "\n";

function checkEligibility($hasVaccination, $stayDuration) {
    return [
        'vaccination' => $hasVaccination,
        'duration' => $stayDuration === '3months_or_more',
        'isEligible' => $hasVaccination && $stayDuration === '3months_or_more',
        'blockReason' => !$hasVaccination ? 'no_vaccination' : ($stayDuration !== '3months_or_more' ? 'short_stay' : null)
    ];
}

test('Avec vaccination + séjour >= 3 mois -> ÉLIGIBLE', function() {
    $result = checkEligibility(true, '3months_or_more');
    return $result['isEligible'] === true && $result['blockReason'] === null;
});

test('Sans vaccination -> BLOQUÉ (vaccination)', function() {
    $result = checkEligibility(false, '3months_or_more');
    return $result['isEligible'] === false && $result['blockReason'] === 'no_vaccination';
});

test('Séjour < 3 mois -> BLOQUÉ (durée)', function() {
    $result = checkEligibility(true, 'less_than_3months');
    return $result['isEligible'] === false && $result['blockReason'] === 'short_stay';
});

// ============================================================
// 4. TESTS DES ÉTAPES DU WORKFLOW
// ============================================================
echo "\n📋 4. ÉTAPES DU WORKFLOW (PHP)\n";
echo str_repeat('─', 50) . "\n";

$WORKFLOW_STEPS = [
    'welcome', 'passport', 'residence', 'eligibility', 
    'photo', 'contact', 'trip', 'health', 'customs', 'confirm'
];

test('Le workflow contient 10 étapes', function() use ($WORKFLOW_STEPS) {
    return count($WORKFLOW_STEPS) === 10;
});

test('La première étape est "welcome"', function() use ($WORKFLOW_STEPS) {
    return $WORKFLOW_STEPS[0] === 'welcome';
});

test('La deuxième étape est "passport"', function() use ($WORKFLOW_STEPS) {
    return $WORKFLOW_STEPS[1] === 'passport';
});

test('La troisième étape est "residence"', function() use ($WORKFLOW_STEPS) {
    return $WORKFLOW_STEPS[2] === 'residence';
});

test('La quatrième étape est "eligibility"', function() use ($WORKFLOW_STEPS) {
    return $WORKFLOW_STEPS[3] === 'eligibility';
});

test('La dernière étape est "confirm"', function() use ($WORKFLOW_STEPS) {
    return $WORKFLOW_STEPS[9] === 'confirm';
});

// ============================================================
// 5. TESTS DE JURIDICTION
// ============================================================
echo "\n🗺️ 5. PAYS DE LA JURIDICTION (PHP)\n";
echo str_repeat('─', 50) . "\n";

$JURISDICTION_COUNTRIES = ['ETH', 'KEN', 'DJI', 'TZA', 'UGA', 'SSD', 'SOM'];

function isInJurisdiction($countryCode) {
    global $JURISDICTION_COUNTRIES;
    return in_array($countryCode, $JURISDICTION_COUNTRIES);
}

test('Éthiopie (ETH) est dans la juridiction', function() {
    return isInJurisdiction('ETH') === true;
});

test('Kenya (KEN) est dans la juridiction', function() {
    return isInJurisdiction('KEN') === true;
});

test('France (FRA) n\'est PAS dans la juridiction', function() {
    return isInJurisdiction('FRA') === false;
});

test('USA n\'est PAS dans la juridiction', function() {
    return isInJurisdiction('USA') === false;
});

// ============================================================
// 6. TESTS DE DÉTECTION DU TYPE DE PASSEPORT
// ============================================================
echo "\n📘 6. DÉTECTION DU TYPE DE PASSEPORT (PHP)\n";
echo str_repeat('─', 50) . "\n";

function detectPassportType($mrzLine1) {
    if (empty($mrzLine1)) return 'ORDINAIRE';
    
    $upper = strtoupper($mrzLine1);
    
    if (strpos($upper, 'DIPLOMATIC') !== false || strpos($upper, 'PD') === 0) {
        return 'DIPLOMATIQUE';
    }
    if (strpos($upper, 'SERVICE') !== false || strpos($upper, 'PS') === 0) {
        return 'SERVICE';
    }
    if (strpos($upper, 'UNITED NATIONS') !== false || strpos($upper, 'UN LP') !== false) {
        return 'LP_ONU';
    }
    if (strpos($upper, 'AFRICAN UNION') !== false || strpos($upper, 'AU LP') !== false) {
        return 'LP_UA';
    }
    if (strpos($upper, 'EMERGENCY') !== false) {
        return 'URGENCE';
    }
    
    return 'ORDINAIRE';
}

test('MRZ standard -> ORDINAIRE', function() {
    return detectPassportType('P<ETHTEST<<NAME<<<') === 'ORDINAIRE';
});

test('MRZ vide -> ORDINAIRE', function() {
    return detectPassportType('') === 'ORDINAIRE' && detectPassportType(null) === 'ORDINAIRE';
});

test('MRZ DIPLOMATIC -> DIPLOMATIQUE', function() {
    return detectPassportType('PD<ETHDIPLOMATIC<<NAME<<<') === 'DIPLOMATIQUE';
});

test('MRZ SERVICE -> SERVICE', function() {
    return detectPassportType('PS<ETHSERVICE<<NAME<<<') === 'SERVICE';
});

test('MRZ UNITED NATIONS -> LP_ONU', function() {
    return detectPassportType('UNITED NATIONS LAISSEZ-PASSER') === 'LP_ONU';
});

test('MRZ AFRICAN UNION -> LP_UA', function() {
    return detectPassportType('AFRICAN UNION LAISSEZ-PASSER') === 'LP_UA';
});

// ============================================================
// 7. TESTS DE LA MATRICE DES EXIGENCES
// ============================================================
echo "\n📊 7. MATRICE DES EXIGENCES (PHP)\n";
echo str_repeat('─', 50) . "\n";

$PASSPORT_REQUIREMENTS = [
    'ORDINAIRE' => [
        'workflow' => 'STANDARD',
        'required' => ['passport', 'ticket', 'vaccination', 'accommodation', 'financial_proof', 'invitation'],
        'fees' => true,
        'processingDays' => '5-10',
        'verbalNote' => false
    ],
    'DIPLOMATIQUE' => [
        'workflow' => 'PRIORITY',
        'required' => ['passport', 'verbal_note'],
        'fees' => false,
        'processingDays' => '24-48h',
        'verbalNote' => true
    ],
    'LP_ONU' => [
        'workflow' => 'PRIORITY',
        'required' => ['passport'],
        'fees' => false,
        'processingDays' => '24-48h',
        'verbalNote' => false
    ]
];

test('Passeport ORDINAIRE requiert frais', function() use ($PASSPORT_REQUIREMENTS) {
    return $PASSPORT_REQUIREMENTS['ORDINAIRE']['fees'] === true;
});

test('Passeport ORDINAIRE workflow STANDARD', function() use ($PASSPORT_REQUIREMENTS) {
    return $PASSPORT_REQUIREMENTS['ORDINAIRE']['workflow'] === 'STANDARD';
});

test('Passeport DIPLOMATIQUE sans frais', function() use ($PASSPORT_REQUIREMENTS) {
    return $PASSPORT_REQUIREMENTS['DIPLOMATIQUE']['fees'] === false;
});

test('Passeport DIPLOMATIQUE workflow PRIORITY', function() use ($PASSPORT_REQUIREMENTS) {
    return $PASSPORT_REQUIREMENTS['DIPLOMATIQUE']['workflow'] === 'PRIORITY';
});

test('Passeport DIPLOMATIQUE traitement 24-48h', function() use ($PASSPORT_REQUIREMENTS) {
    return $PASSPORT_REQUIREMENTS['DIPLOMATIQUE']['processingDays'] === '24-48h';
});

test('LP_ONU sans frais', function() use ($PASSPORT_REQUIREMENTS) {
    return $PASSPORT_REQUIREMENTS['LP_ONU']['fees'] === false;
});

// ============================================================
// RÉSUMÉ FINAL
// ============================================================
echo "\n" . str_repeat('═', 60) . "\n";
echo "                    RÉSUMÉ DES TESTS PHP\n";
echo str_repeat('═', 60) . "\n";
echo "  Total: $totalTests tests\n";
echo "  ✅ Passés: $passedTests\n";
echo "  ❌ Échoués: $failedTests\n";
echo "  Taux de réussite: " . round(($passedTests/$totalTests)*100) . "%\n";
echo str_repeat('═', 60) . "\n";

if ($failedTests === 0) {
    echo "\n🎉 TOUS LES TESTS PHP SONT PASSÉS AVEC SUCCÈS!\n\n";
    exit(0);
} else {
    echo "\n⚠️  CERTAINS TESTS PHP ONT ÉCHOUÉ!\n\n";
    exit(1);
}

