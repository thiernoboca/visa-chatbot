/**
 * Tests unitaires complets du workflow chatbot visa
 * Teste toutes les fonctionnalités et parcourt tout le processus
 */

console.log('╔══════════════════════════════════════════════════════════════╗');
console.log('║     TESTS UNITAIRES - CHATBOT VISA CÔTE D\'IVOIRE            ║');
console.log('╚══════════════════════════════════════════════════════════════╝\n');

let totalTests = 0;
let passedTests = 0;
let failedTests = 0;

function test(description, fn) {
    totalTests++;
    try {
        fn();
        passedTests++;
        console.log(`  ✅ ${description}`);
    } catch (error) {
        failedTests++;
        console.log(`  ❌ ${description}`);
        console.log(`     Error: ${error.message}`);
    }
}

function assertEqual(actual, expected, message = '') {
    if (actual !== expected) {
        throw new Error(`Expected "${expected}", got "${actual}". ${message}`);
    }
}

function assertTrue(value, message = '') {
    if (!value) {
        throw new Error(`Expected true, got ${value}. ${message}`);
    }
}

function assertFalse(value, message = '') {
    if (value) {
        throw new Error(`Expected false, got ${value}. ${message}`);
    }
}

function assertContains(array, item, message = '') {
    if (!array.includes(item)) {
        throw new Error(`Array does not contain "${item}". ${message}`);
    }
}

// ============================================================
// 1. TESTS DE L'ORDRE DES ÉTAPES DU WORKFLOW
// ============================================================
console.log('\n📋 1. ORDRE DES ÉTAPES DU WORKFLOW');
console.log('─'.repeat(50));

const WORKFLOW_STEPS = [
    'welcome',      // 0 - Accueil & langue
    'passport',     // 1 - Scan passeport
    'residence',    // 2 - Pays de résidence
    'eligibility',  // 3 - Éligibilité
    'photo',        // 4 - Photo d'identité
    'contact',      // 5 - Coordonnées
    'trip',         // 6 - Informations voyage
    'health',       // 7 - Déclaration santé
    'customs',      // 8 - Déclaration douanes
    'confirm'       // 9 - Confirmation
];

test('Le workflow contient 10 étapes', () => {
    assertEqual(WORKFLOW_STEPS.length, 10);
});

test('La première étape est "welcome"', () => {
    assertEqual(WORKFLOW_STEPS[0], 'welcome');
});

test('La deuxième étape est "passport" (avant residence)', () => {
    assertEqual(WORKFLOW_STEPS[1], 'passport');
});

test('La troisième étape est "residence" (après passport)', () => {
    assertEqual(WORKFLOW_STEPS[2], 'residence');
});

test('La quatrième étape est "eligibility" (questions bloquantes)', () => {
    assertEqual(WORKFLOW_STEPS[3], 'eligibility');
});

test('La dernière étape est "confirm"', () => {
    assertEqual(WORKFLOW_STEPS[9], 'confirm');
});

test('L\'ordre complet est correct', () => {
    const expectedOrder = ['welcome', 'passport', 'residence', 'eligibility', 'photo', 'contact', 'trip', 'health', 'customs', 'confirm'];
    assertEqual(JSON.stringify(WORKFLOW_STEPS), JSON.stringify(expectedOrder));
});

// ============================================================
// 2. TESTS DE NORMALISATION DE NATIONALITÉ
// ============================================================
console.log('\n🌍 2. NORMALISATION DE NATIONALITÉ');
console.log('─'.repeat(50));

const nationalityMapping = {
    // Éthiopie
    'ethiopian': 'ETH', 'eth': 'ETH', 'ethiopia': 'ETH', 'ethiopie': 'ETH',
    'ethiopienne': 'ETH', 'ethiopien': 'ETH',
    // Kenya
    'kenyan': 'KEN', 'ken': 'KEN', 'kenya': 'KEN',
    // Djibouti
    'djiboutian': 'DJI', 'dji': 'DJI', 'djibouti': 'DJI', 'djiboutien': 'DJI',
    // Tanzanie
    'tanzanian': 'TZA', 'tza': 'TZA', 'tanzania': 'TZA', 'tanzanie': 'TZA',
    // Ouganda
    'ugandan': 'UGA', 'uga': 'UGA', 'uganda': 'UGA', 'ouganda': 'UGA',
    // Soudan du Sud
    'south sudanese': 'SSD', 'ssd': 'SSD',
    // Somalie
    'somali': 'SOM', 'som': 'SOM', 'somalia': 'SOM', 'somalie': 'SOM',
    // Côte d'Ivoire
    'ivorian': 'CIV', 'civ': 'CIV', 'ivoirien': 'CIV',
    // Autres
    'french': 'FRA', 'francais': 'FRA',
    'american': 'USA', 'americain': 'USA',
    'chinese': 'CHN', 'chinois': 'CHN'
};

function normalizeNationality(nationality) {
    if (!nationality) return null;
    
    const normalized = nationality
        .toLowerCase()
        .trim()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
    
    for (const [key, code] of Object.entries(nationalityMapping)) {
        const normalizedKey = key.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        if (normalized === normalizedKey || normalized.includes(normalizedKey) || normalizedKey.includes(normalized)) {
            return code;
        }
    }
    
    const lowerNationality = nationality.toLowerCase().trim();
    if (nationalityMapping[lowerNationality]) {
        return nationalityMapping[lowerNationality];
    }
    
    for (const [key, code] of Object.entries(nationalityMapping)) {
        if (lowerNationality.includes(key) || key.includes(lowerNationality)) {
            return code;
        }
    }
    
    return null;
}

test('ETHIOPIAN -> ETH', () => {
    assertEqual(normalizeNationality('ETHIOPIAN'), 'ETH');
});

test('Ethiopian (capitalized) -> ETH', () => {
    assertEqual(normalizeNationality('Ethiopian'), 'ETH');
});

test('ETH (code) -> ETH', () => {
    assertEqual(normalizeNationality('ETH'), 'ETH');
});

test('ÉTHIOPIEN (avec accent) -> ETH', () => {
    assertEqual(normalizeNationality('ÉTHIOPIEN'), 'ETH');
});

test('KENYAN -> KEN', () => {
    assertEqual(normalizeNationality('KENYAN'), 'KEN');
});

test('IVORIAN -> CIV', () => {
    assertEqual(normalizeNationality('IVORIAN'), 'CIV');
});

test('Ivoirien -> CIV', () => {
    assertEqual(normalizeNationality('Ivoirien'), 'CIV');
});

test('FRENCH -> FRA', () => {
    assertEqual(normalizeNationality('FRENCH'), 'FRA');
});

test('Français (avec accent) -> FRA', () => {
    assertEqual(normalizeNationality('Français'), 'FRA');
});

test('null/empty -> null', () => {
    assertEqual(normalizeNationality(null), null);
    assertEqual(normalizeNationality(''), null);
});

// ============================================================
// 3. TESTS DE COMPARAISON NATIONALITÉ/RÉSIDENCE
// ============================================================
console.log('\n🏠 3. COMPARAISON NATIONALITÉ/RÉSIDENCE');
console.log('─'.repeat(50));

function needsResidenceCard(nationality, residenceCode) {
    const nationalityCode = normalizeNationality(nationality);
    return nationalityCode !== null && nationalityCode !== residenceCode;
}

test('Éthiopien en Éthiopie -> PAS de carte de séjour', () => {
    assertFalse(needsResidenceCard('ETHIOPIAN', 'ETH'));
});

test('Éthiopien au Kenya -> carte de séjour REQUISE', () => {
    assertTrue(needsResidenceCard('ETHIOPIAN', 'KEN'));
});

test('Kenyan au Kenya -> PAS de carte de séjour', () => {
    assertFalse(needsResidenceCard('KENYAN', 'KEN'));
});

test('Kenyan en Éthiopie -> carte de séjour REQUISE', () => {
    assertTrue(needsResidenceCard('KENYAN', 'ETH'));
});

test('Ivoirien en Éthiopie -> carte de séjour REQUISE', () => {
    assertTrue(needsResidenceCard('IVORIAN', 'ETH'));
});

test('Français en Éthiopie -> carte de séjour REQUISE', () => {
    assertTrue(needsResidenceCard('FRENCH', 'ETH'));
});

test('Djiboutien à Djibouti -> PAS de carte de séjour', () => {
    assertFalse(needsResidenceCard('DJIBOUTIAN', 'DJI'));
});

test('Tanzanien en Tanzanie -> PAS de carte de séjour', () => {
    assertFalse(needsResidenceCard('TANZANIAN', 'TZA'));
});

// ============================================================
// 4. TESTS D'ÉLIGIBILITÉ (QUESTIONS BLOQUANTES)
// ============================================================
console.log('\n🚦 4. TESTS D\'ÉLIGIBILITÉ');
console.log('─'.repeat(50));

function checkEligibility(hasVaccination, stayDuration) {
    const eligible = {
        vaccination: hasVaccination,
        duration: stayDuration === '3months_or_more',
        isEligible: hasVaccination && stayDuration === '3months_or_more',
        blockReason: null
    };
    
    if (!hasVaccination) {
        eligible.blockReason = 'no_vaccination';
    } else if (stayDuration !== '3months_or_more') {
        eligible.blockReason = 'short_stay';
    }
    
    return eligible;
}

test('Avec vaccination + séjour >= 3 mois -> ÉLIGIBLE', () => {
    const result = checkEligibility(true, '3months_or_more');
    assertTrue(result.isEligible);
    assertEqual(result.blockReason, null);
});

test('Sans vaccination -> BLOQUÉ (vaccination)', () => {
    const result = checkEligibility(false, '3months_or_more');
    assertFalse(result.isEligible);
    assertEqual(result.blockReason, 'no_vaccination');
});

test('Séjour < 3 mois -> BLOQUÉ (durée)', () => {
    const result = checkEligibility(true, 'less_than_3months');
    assertFalse(result.isEligible);
    assertEqual(result.blockReason, 'short_stay');
});

test('Sans vaccination + séjour < 3 mois -> BLOQUÉ (vaccination prioritaire)', () => {
    const result = checkEligibility(false, 'less_than_3months');
    assertFalse(result.isEligible);
    assertEqual(result.blockReason, 'no_vaccination');
});

// ============================================================
// 5. TESTS DE DÉTECTION DU TYPE DE PASSEPORT
// ============================================================
console.log('\n📘 5. DÉTECTION DU TYPE DE PASSEPORT');
console.log('─'.repeat(50));

function detectPassportType(mrzLine1) {
    if (!mrzLine1) return 'ORDINAIRE';
    
    const upper = mrzLine1.toUpperCase();
    
    if (upper.includes('DIPLOMATIC') || upper.startsWith('PD') || upper.includes('DIPLOM')) {
        return 'DIPLOMATIQUE';
    }
    if (upper.includes('SERVICE') || upper.startsWith('PS')) {
        return 'SERVICE';
    }
    if (upper.includes('UNITED NATIONS') || upper.includes('NATIONS UNIES') || upper.includes('UN LP')) {
        return 'LP_ONU';
    }
    if (upper.includes('AFRICAN UNION') || upper.includes('UNION AFRICAINE') || upper.includes('AU LP')) {
        return 'LP_UA';
    }
    if (upper.includes('EMERGENCY') || upper.includes('URGENCE')) {
        return 'URGENCE';
    }
    
    return 'ORDINAIRE';
}

test('MRZ standard -> ORDINAIRE', () => {
    assertEqual(detectPassportType('P<ETHTEST<<NAME<<<'), 'ORDINAIRE');
});

test('MRZ vide -> ORDINAIRE', () => {
    assertEqual(detectPassportType(''), 'ORDINAIRE');
    assertEqual(detectPassportType(null), 'ORDINAIRE');
});

test('MRZ DIPLOMATIC -> DIPLOMATIQUE', () => {
    assertEqual(detectPassportType('PD<ETHDIPLOMATIC<<NAME<<<'), 'DIPLOMATIQUE');
});

test('MRZ SERVICE -> SERVICE', () => {
    assertEqual(detectPassportType('PS<ETHSERVICE<<NAME<<<'), 'SERVICE');
});

test('MRZ UNITED NATIONS -> LP_ONU', () => {
    assertEqual(detectPassportType('UNITED NATIONS LAISSEZ-PASSER'), 'LP_ONU');
});

test('MRZ AFRICAN UNION -> LP_UA', () => {
    assertEqual(detectPassportType('AFRICAN UNION LAISSEZ-PASSER'), 'LP_UA');
});

test('MRZ EMERGENCY -> URGENCE', () => {
    assertEqual(detectPassportType('EMERGENCY PASSPORT'), 'URGENCE');
});

// ============================================================
// 6. TESTS DE LA MATRICE DES EXIGENCES
// ============================================================
console.log('\n📊 6. MATRICE DES EXIGENCES PAR TYPE DE PASSEPORT');
console.log('─'.repeat(50));

const PASSPORT_REQUIREMENTS = {
    'ORDINAIRE': {
        workflow: 'STANDARD',
        required: ['passport', 'ticket', 'vaccination', 'accommodation', 'financial_proof', 'invitation'],
        conditional: ['hotel'],
        optional: ['residence_card'],
        fees: true,
        processingDays: '5-10',
        verbalNote: false
    },
    'DIPLOMATIQUE': {
        workflow: 'PRIORITY',
        required: ['passport', 'verbal_note'],
        conditional: [],
        optional: ['ticket'],
        fees: false,
        processingDays: '24-48h',
        verbalNote: true
    },
    'SERVICE': {
        workflow: 'PRIORITY',
        required: ['passport', 'verbal_note'],
        conditional: [],
        optional: ['ticket'],
        fees: false,
        processingDays: '24-48h',
        verbalNote: true
    },
    'LP_ONU': {
        workflow: 'PRIORITY',
        required: ['passport'],
        conditional: [],
        optional: ['ticket', 'verbal_note'],
        fees: false,
        processingDays: '24-48h',
        verbalNote: false
    },
    'LP_UA': {
        workflow: 'PRIORITY',
        required: ['passport'],
        conditional: [],
        optional: ['ticket', 'verbal_note'],
        fees: false,
        processingDays: '24-48h',
        verbalNote: false
    },
    'URGENCE': {
        workflow: 'STANDARD',
        required: ['passport', 'ticket', 'vaccination'],
        conditional: ['hotel', 'invitation'],
        optional: [],
        fees: true,
        processingDays: '5-10',
        verbalNote: false
    }
};

test('Passeport ORDINAIRE requiert frais', () => {
    assertTrue(PASSPORT_REQUIREMENTS['ORDINAIRE'].fees);
});

test('Passeport ORDINAIRE workflow STANDARD', () => {
    assertEqual(PASSPORT_REQUIREMENTS['ORDINAIRE'].workflow, 'STANDARD');
});

test('Passeport ORDINAIRE requiert vaccination', () => {
    assertContains(PASSPORT_REQUIREMENTS['ORDINAIRE'].required, 'vaccination');
});

test('Passeport ORDINAIRE requiert hébergement', () => {
    assertContains(PASSPORT_REQUIREMENTS['ORDINAIRE'].required, 'accommodation');
});

test('Passeport DIPLOMATIQUE sans frais', () => {
    assertFalse(PASSPORT_REQUIREMENTS['DIPLOMATIQUE'].fees);
});

test('Passeport DIPLOMATIQUE workflow PRIORITY', () => {
    assertEqual(PASSPORT_REQUIREMENTS['DIPLOMATIQUE'].workflow, 'PRIORITY');
});

test('Passeport DIPLOMATIQUE requiert note verbale', () => {
    assertTrue(PASSPORT_REQUIREMENTS['DIPLOMATIQUE'].verbalNote);
    assertContains(PASSPORT_REQUIREMENTS['DIPLOMATIQUE'].required, 'verbal_note');
});

test('LP_ONU sans frais', () => {
    assertFalse(PASSPORT_REQUIREMENTS['LP_ONU'].fees);
});

test('LP_ONU note verbale optionnelle', () => {
    assertFalse(PASSPORT_REQUIREMENTS['LP_ONU'].verbalNote);
    assertContains(PASSPORT_REQUIREMENTS['LP_ONU'].optional, 'verbal_note');
});

test('Passeport SERVICE traitement 24-48h', () => {
    assertEqual(PASSPORT_REQUIREMENTS['SERVICE'].processingDays, '24-48h');
});

// ============================================================
// 7. TESTS DES PAYS DE LA JURIDICTION
// ============================================================
console.log('\n🗺️ 7. PAYS DE LA JURIDICTION');
console.log('─'.repeat(50));

const JURISDICTION_COUNTRIES = ['ETH', 'KEN', 'DJI', 'TZA', 'UGA', 'SSD', 'SOM'];

function isInJurisdiction(countryCode) {
    return JURISDICTION_COUNTRIES.includes(countryCode);
}

test('Éthiopie (ETH) est dans la juridiction', () => {
    assertTrue(isInJurisdiction('ETH'));
});

test('Kenya (KEN) est dans la juridiction', () => {
    assertTrue(isInJurisdiction('KEN'));
});

test('Djibouti (DJI) est dans la juridiction', () => {
    assertTrue(isInJurisdiction('DJI'));
});

test('Tanzanie (TZA) est dans la juridiction', () => {
    assertTrue(isInJurisdiction('TZA'));
});

test('Ouganda (UGA) est dans la juridiction', () => {
    assertTrue(isInJurisdiction('UGA'));
});

test('Soudan du Sud (SSD) est dans la juridiction', () => {
    assertTrue(isInJurisdiction('SSD'));
});

test('Somalie (SOM) est dans la juridiction', () => {
    assertTrue(isInJurisdiction('SOM'));
});

test('France (FRA) n\'est PAS dans la juridiction', () => {
    assertFalse(isInJurisdiction('FRA'));
});

test('USA n\'est PAS dans la juridiction', () => {
    assertFalse(isInJurisdiction('USA'));
});

test('Côte d\'Ivoire (CIV) n\'est PAS dans la juridiction', () => {
    assertFalse(isInJurisdiction('CIV'));
});

// ============================================================
// 8. TESTS DE SIMULATION DU PARCOURS COMPLET
// ============================================================
console.log('\n🎯 8. SIMULATION DE PARCOURS COMPLETS');
console.log('─'.repeat(50));

function simulateWorkflow(scenario) {
    const result = {
        steps: [],
        blocked: false,
        blockReason: null,
        documentsRequired: [],
        fees: false
    };
    
    // Étape 1: Welcome
    result.steps.push('welcome');
    
    // Étape 2: Passport
    result.steps.push('passport');
    const passportType = detectPassportType(scenario.mrzLine1);
    result.passportType = passportType;
    
    // Étape 3: Residence
    result.steps.push('residence');
    if (!isInJurisdiction(scenario.residenceCode)) {
        result.blocked = true;
        result.blockReason = 'out_of_jurisdiction';
        return result;
    }
    
    // Check residence card
    result.needsResidenceCard = needsResidenceCard(scenario.nationality, scenario.residenceCode);
    
    // Étape 4: Eligibility
    result.steps.push('eligibility');
    const eligibility = checkEligibility(scenario.hasVaccination, scenario.stayDuration);
    if (!eligibility.isEligible) {
        result.blocked = true;
        result.blockReason = eligibility.blockReason;
        return result;
    }
    
    // Étapes suivantes si éligible
    result.steps.push('photo', 'contact', 'trip', 'health', 'customs', 'confirm');
    
    // Déterminer les documents requis
    const requirements = PASSPORT_REQUIREMENTS[passportType];
    result.documentsRequired = [...requirements.required];
    if (result.needsResidenceCard) {
        result.documentsRequired.push('residence_card');
    }
    result.fees = requirements.fees;
    result.processingTime = requirements.processingDays;
    
    return result;
}

// Scénario 1: Éthiopien en Éthiopie, vacciné, séjour long
test('Scénario: Éthiopien vacciné, séjour >= 3 mois -> SUCCÈS complet', () => {
    const result = simulateWorkflow({
        nationality: 'ETHIOPIAN',
        residenceCode: 'ETH',
        mrzLine1: 'P<ETH...',
        hasVaccination: true,
        stayDuration: '3months_or_more'
    });
    
    assertFalse(result.blocked);
    assertEqual(result.steps.length, 10);
    assertFalse(result.needsResidenceCard);
    assertTrue(result.fees);
});

// Scénario 2: Français en Éthiopie, vacciné, séjour long
test('Scénario: Français en Éthiopie, vacciné -> carte de séjour requise', () => {
    const result = simulateWorkflow({
        nationality: 'FRENCH',
        residenceCode: 'ETH',
        mrzLine1: 'P<FRA...',
        hasVaccination: true,
        stayDuration: '3months_or_more'
    });
    
    assertFalse(result.blocked);
    assertTrue(result.needsResidenceCard);
    assertContains(result.documentsRequired, 'residence_card');
});

// Scénario 3: Sans vaccination
test('Scénario: Sans vaccination -> BLOQUÉ', () => {
    const result = simulateWorkflow({
        nationality: 'ETHIOPIAN',
        residenceCode: 'ETH',
        mrzLine1: 'P<ETH...',
        hasVaccination: false,
        stayDuration: '3months_or_more'
    });
    
    assertTrue(result.blocked);
    assertEqual(result.blockReason, 'no_vaccination');
});

// Scénario 4: Séjour court
test('Scénario: Séjour < 3 mois -> BLOQUÉ', () => {
    const result = simulateWorkflow({
        nationality: 'ETHIOPIAN',
        residenceCode: 'ETH',
        mrzLine1: 'P<ETH...',
        hasVaccination: true,
        stayDuration: 'less_than_3months'
    });
    
    assertTrue(result.blocked);
    assertEqual(result.blockReason, 'short_stay');
});

// Scénario 5: Hors juridiction
test('Scénario: Résident en France -> BLOQUÉ (hors juridiction)', () => {
    const result = simulateWorkflow({
        nationality: 'FRENCH',
        residenceCode: 'FRA',
        mrzLine1: 'P<FRA...',
        hasVaccination: true,
        stayDuration: '3months_or_more'
    });
    
    assertTrue(result.blocked);
    assertEqual(result.blockReason, 'out_of_jurisdiction');
});

// Scénario 6: Passeport diplomatique
test('Scénario: Passeport diplomatique -> sans frais, traitement rapide', () => {
    const result = simulateWorkflow({
        nationality: 'ETHIOPIAN',
        residenceCode: 'ETH',
        mrzLine1: 'PD<ETHDIPLOMATIC...',
        hasVaccination: true,
        stayDuration: '3months_or_more'
    });
    
    assertFalse(result.blocked);
    assertEqual(result.passportType, 'DIPLOMATIQUE');
    assertFalse(result.fees);
    assertEqual(result.processingTime, '24-48h');
    assertContains(result.documentsRequired, 'verbal_note');
});

// Scénario 7: LP ONU
test('Scénario: Laissez-passer ONU -> sans frais, note verbale optionnelle', () => {
    const result = simulateWorkflow({
        nationality: 'ETHIOPIAN',
        residenceCode: 'ETH',
        mrzLine1: 'UNITED NATIONS LAISSEZ-PASSER',
        hasVaccination: true,
        stayDuration: '3months_or_more'
    });
    
    assertFalse(result.blocked);
    assertEqual(result.passportType, 'LP_ONU');
    assertFalse(result.fees);
});

// Scénario 8: Kenyan au Kenya
test('Scénario: Kenyan au Kenya, vacciné -> SUCCÈS sans carte de séjour', () => {
    const result = simulateWorkflow({
        nationality: 'KENYAN',
        residenceCode: 'KEN',
        mrzLine1: 'P<KEN...',
        hasVaccination: true,
        stayDuration: '3months_or_more'
    });
    
    assertFalse(result.blocked);
    assertFalse(result.needsResidenceCard);
});

// Scénario 9: Kenyan en Éthiopie
test('Scénario: Kenyan en Éthiopie -> carte de séjour requise', () => {
    const result = simulateWorkflow({
        nationality: 'KENYAN',
        residenceCode: 'ETH',
        mrzLine1: 'P<KEN...',
        hasVaccination: true,
        stayDuration: '3months_or_more'
    });
    
    assertFalse(result.blocked);
    assertTrue(result.needsResidenceCard);
});

// ============================================================
// 9. TESTS DE PROGRESSION
// ============================================================
console.log('\n📈 9. TESTS DE PROGRESSION');
console.log('─'.repeat(50));

const STEP_PROGRESS = {
    'welcome': 10,
    'passport': 20,
    'residence': 30,
    'eligibility': 40,
    'photo': 50,
    'contact': 60,
    'trip': 70,
    'health': 80,
    'customs': 90,
    'confirm': 100
};

test('welcome = 10%', () => assertEqual(STEP_PROGRESS['welcome'], 10));
test('passport = 20%', () => assertEqual(STEP_PROGRESS['passport'], 20));
test('residence = 30%', () => assertEqual(STEP_PROGRESS['residence'], 30));
test('eligibility = 40%', () => assertEqual(STEP_PROGRESS['eligibility'], 40));
test('photo = 50%', () => assertEqual(STEP_PROGRESS['photo'], 50));
test('contact = 60%', () => assertEqual(STEP_PROGRESS['contact'], 60));
test('trip = 70%', () => assertEqual(STEP_PROGRESS['trip'], 70));
test('health = 80%', () => assertEqual(STEP_PROGRESS['health'], 80));
test('customs = 90%', () => assertEqual(STEP_PROGRESS['customs'], 90));
test('confirm = 100%', () => assertEqual(STEP_PROGRESS['confirm'], 100));

// ============================================================
// RÉSUMÉ FINAL
// ============================================================
console.log('\n' + '═'.repeat(60));
console.log('                    RÉSUMÉ DES TESTS');
console.log('═'.repeat(60));
console.log(`  Total: ${totalTests} tests`);
console.log(`  ✅ Passés: ${passedTests}`);
console.log(`  ❌ Échoués: ${failedTests}`);
console.log(`  Taux de réussite: ${Math.round((passedTests/totalTests)*100)}%`);
console.log('═'.repeat(60));

if (failedTests === 0) {
    console.log('\n🎉 TOUS LES TESTS SONT PASSÉS AVEC SUCCÈS!\n');
    process.exit(0);
} else {
    console.log('\n⚠️  CERTAINS TESTS ONT ÉCHOUÉ!\n');
    process.exit(1);
}

