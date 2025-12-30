/**
 * Test de la fonction normalizeNationality
 * Vérifie que la comparaison nationalité/résidence fonctionne correctement
 */

// Reproduire la table de mapping
const nationalityMapping = {
    // Éthiopie / Ethiopia
    'ethiopian': 'ETH',
    'eth': 'ETH',
    'ethiopia': 'ETH',
    'ethiopie': 'ETH', 
    'ethiopienne': 'ETH',
    'ethiopien': 'ETH',
    // Kenya
    'kenyan': 'KEN',
    'ken': 'KEN',
    'kenya': 'KEN',
    'kenyane': 'KEN',
    'kenyen': 'KEN',
    // Djibouti
    'djiboutian': 'DJI',
    'dji': 'DJI',
    'djibouti': 'DJI',
    'djiboutien': 'DJI',
    'djiboutienne': 'DJI',
    // Tanzanie / Tanzania
    'tanzanian': 'TZA',
    'tza': 'TZA',
    'tanzania': 'TZA',
    'tanzanie': 'TZA', 
    'tanzanien': 'TZA',
    'tanzanienne': 'TZA',
    // Ouganda / Uganda
    'ugandan': 'UGA',
    'uga': 'UGA',
    'uganda': 'UGA',
    'ouganda': 'UGA', 
    'ougandais': 'UGA',
    'ougandaise': 'UGA',
    // Côte d'Ivoire
    'ivorian': 'CIV',
    'civ': 'CIV',
    'ivory coast': 'CIV',
    'ivoirien': 'CIV',
    'ivoirienne': 'CIV',
    // Autres
    'french': 'FRA',
    'fra': 'FRA',
    'france': 'FRA',
    'francais': 'FRA',
    'francaise': 'FRA'
};

// Fonction normalizeNationality
function normalizeNationality(nationality) {
    if (!nationality) return null;
    
    // Normaliser: minuscules et supprimer les accents pour la recherche
    const normalized = nationality
        .toLowerCase()
        .trim()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, ''); // Supprime les diacritiques
    
    // Chercher dans le mapping avec la version normalisée
    for (const [key, code] of Object.entries(nationalityMapping)) {
        const normalizedKey = key
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
        
        if (normalized === normalizedKey || normalized.includes(normalizedKey) || normalizedKey.includes(normalized)) {
            return code;
        }
    }
    
    // Si pas trouvé, essayer avec la version originale en minuscules
    const lowerNationality = nationality.toLowerCase().trim();
    if (nationalityMapping[lowerNationality]) {
        return nationalityMapping[lowerNationality];
    }
    
    // Dernière tentative: chercher si la nationalité contient un des mots clés
    for (const [key, code] of Object.entries(nationalityMapping)) {
        if (lowerNationality.includes(key) || key.includes(lowerNationality)) {
            return code;
        }
    }
    
    return null;
}

// Tests
const tests = [
    // Cas 1: Éthiopien résidant en Éthiopie -> PAS de carte de séjour
    { nationality: 'ETHIOPIAN', residence: 'ETH', expected: false, description: 'Ethiopian in Ethiopia' },
    { nationality: 'Ethiopian', residence: 'ETH', expected: false, description: 'Ethiopian (capitalized) in Ethiopia' },
    { nationality: 'ETH', residence: 'ETH', expected: false, description: 'ETH code in Ethiopia' },
    { nationality: 'Ethiopia', residence: 'ETH', expected: false, description: 'Ethiopia country name in Ethiopia' },
    { nationality: 'ÉTHIOPIEN', residence: 'ETH', expected: false, description: 'Éthiopien (FR accent) in Ethiopia' },
    
    // Cas 2: Éthiopien résidant au Kenya -> carte de séjour requise
    { nationality: 'ETHIOPIAN', residence: 'KEN', expected: true, description: 'Ethiopian in Kenya' },
    { nationality: 'ETH', residence: 'KEN', expected: true, description: 'ETH code in Kenya' },
    
    // Cas 3: Kenyan résidant au Kenya -> PAS de carte de séjour
    { nationality: 'KENYAN', residence: 'KEN', expected: false, description: 'Kenyan in Kenya' },
    { nationality: 'KEN', residence: 'KEN', expected: false, description: 'KEN code in Kenya' },
    { nationality: 'Kenya', residence: 'KEN', expected: false, description: 'Kenya country name in Kenya' },
    
    // Cas 4: Ivoirien résidant en Éthiopie -> carte de séjour requise
    { nationality: 'IVORIAN', residence: 'ETH', expected: true, description: 'Ivorian in Ethiopia' },
    { nationality: 'Ivoirien', residence: 'ETH', expected: true, description: 'Ivoirien (FR) in Ethiopia' },
    { nationality: 'CIV', residence: 'ETH', expected: true, description: 'CIV code in Ethiopia' },
    
    // Cas 5: Français résidant en Éthiopie -> carte de séjour requise
    { nationality: 'FRENCH', residence: 'ETH', expected: true, description: 'French in Ethiopia' },
    { nationality: 'Français', residence: 'ETH', expected: true, description: 'Français (FR) in Ethiopia' },
    
    // Cas 6: Djiboutien résidant à Djibouti -> PAS de carte de séjour
    { nationality: 'DJIBOUTIAN', residence: 'DJI', expected: false, description: 'Djiboutian in Djibouti' },
    { nationality: 'Djiboutien', residence: 'DJI', expected: false, description: 'Djiboutien (FR) in Djibouti' },
];

console.log('=== Test de comparaison nationalité/résidence ===\n');

let passed = 0;
let failed = 0;

for (const test of tests) {
    const nationalityCode = normalizeNationality(test.nationality);
    const residenceCode = test.residence;
    const needsResidenceCard = nationalityCode !== null && nationalityCode !== residenceCode;
    
    const success = needsResidenceCard === test.expected;
    
    if (success) {
        passed++;
        console.log(`✅ ${test.description}`);
        console.log(`   Nationality: "${test.nationality}" -> Code: ${nationalityCode}`);
        console.log(`   Residence: ${residenceCode}`);
        console.log(`   Needs residence card: ${needsResidenceCard} (expected: ${test.expected})\n`);
    } else {
        failed++;
        console.log(`❌ FAILED: ${test.description}`);
        console.log(`   Nationality: "${test.nationality}" -> Code: ${nationalityCode}`);
        console.log(`   Residence: ${residenceCode}`);
        console.log(`   Needs residence card: ${needsResidenceCard} (expected: ${test.expected})\n`);
    }
}

console.log('=== Résultats ===');
console.log(`Passés: ${passed}/${tests.length}`);
console.log(`Échoués: ${failed}/${tests.length}`);

if (failed === 0) {
    console.log('\n🎉 Tous les tests sont passés!');
} else {
    console.log('\n⚠️ Certains tests ont échoué!');
    process.exit(1);
}
