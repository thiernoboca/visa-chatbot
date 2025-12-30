<?php
/**
 * Données de référence - Pays de la circonscription
 * Ambassade de Côte d'Ivoire à Addis-Abeba
 * 
 * @package VisaChatbot
 */

// Pays couverts par l'Ambassade
const JURISDICTION_COUNTRIES = [
    'ETH' => [
        'code' => 'ETH',
        'nameFr' => 'Éthiopie',
        'nameEn' => 'Ethiopia',
        'capital' => 'Addis-Abeba',
        'defaultConsulate' => 'ADDIS_ABEBA',
        'flag' => '🇪🇹',
        'phoneCode' => '+251'
    ],
    'KEN' => [
        'code' => 'KEN',
        'nameFr' => 'Kenya',
        'nameEn' => 'Kenya',
        'capital' => 'Nairobi',
        'defaultConsulate' => 'NAIROBI',
        'flag' => '🇰🇪',
        'phoneCode' => '+254'
    ],
    'DJI' => [
        'code' => 'DJI',
        'nameFr' => 'Djibouti',
        'nameEn' => 'Djibouti',
        'capital' => 'Djibouti',
        'defaultConsulate' => 'DJIBOUTI',
        'flag' => '🇩🇯',
        'phoneCode' => '+253'
    ],
    'TZA' => [
        'code' => 'TZA',
        'nameFr' => 'Tanzanie',
        'nameEn' => 'Tanzania',
        'capital' => 'Dar es Salaam',
        'defaultConsulate' => 'NAIROBI',
        'flag' => '🇹🇿',
        'phoneCode' => '+255'
    ],
    'UGA' => [
        'code' => 'UGA',
        'nameFr' => 'Ouganda',
        'nameEn' => 'Uganda',
        'capital' => 'Kampala',
        'defaultConsulate' => 'ADDIS_ABEBA',
        'flag' => '🇺🇬',
        'phoneCode' => '+256'
    ],
    'SSD' => [
        'code' => 'SSD',
        'nameFr' => 'Soudan du Sud',
        'nameEn' => 'South Sudan',
        'capital' => 'Juba',
        'defaultConsulate' => 'ADDIS_ABEBA',
        'flag' => '🇸🇸',
        'phoneCode' => '+211'
    ],
    'SOM' => [
        'code' => 'SOM',
        'nameFr' => 'Somalie',
        'nameEn' => 'Somalia',
        'capital' => 'Mogadiscio',
        'defaultConsulate' => 'DJIBOUTI',
        'flag' => '🇸🇴',
        'phoneCode' => '+252'
    ]
];

// Consulats
const CONSULATES = [
    'ADDIS_ABEBA' => [
        'name' => 'Ambassade de Côte d\'Ivoire',
        'city' => 'Addis-Abeba',
        'country' => 'Éthiopie',
        'address' => 'Woreda 17, Kebele 19, House No. 1893',
        'phone' => '+251 11 551 3622',
        'email' => 'ambassade.addisabeba@diplomatie.gouv.ci'
    ],
    'NAIROBI' => [
        'name' => 'Consulat de Côte d\'Ivoire',
        'city' => 'Nairobi',
        'country' => 'Kenya',
        'address' => 'À définir',
        'phone' => 'À définir',
        'email' => 'consulat.nairobi@diplomatie.gouv.ci'
    ],
    'DJIBOUTI' => [
        'name' => 'Consulat de Côte d\'Ivoire',
        'city' => 'Djibouti',
        'country' => 'Djibouti',
        'address' => 'À définir',
        'phone' => 'À définir',
        'email' => 'consulat.djibouti@diplomatie.gouv.ci'
    ]
];

/**
 * Vérifie si un pays est dans la circonscription
 */
function isInJurisdiction(string $countryCode): bool {
    return isset(JURISDICTION_COUNTRIES[strtoupper($countryCode)]);
}

/**
 * Retourne le consulat par défaut pour un pays
 */
function getDefaultConsulate(string $countryCode): ?array {
    $country = JURISDICTION_COUNTRIES[strtoupper($countryCode)] ?? null;
    if (!$country) return null;
    return CONSULATES[$country['defaultConsulate']] ?? null;
}

/**
 * Retourne la liste des pays pour les quick actions
 */
function getCountryQuickActions(string $lang = 'fr'): array {
    $actions = [];
    foreach (JURISDICTION_COUNTRIES as $country) {
        $actions[] = [
            'label' => $country['flag'] . ' ' . ($lang === 'fr' ? $country['nameFr'] : $country['nameEn']),
            'value' => $country['code']
        ];
    }
    return $actions;
}

/**
 * Retourne les informations d'un pays
 */
function getCountryInfo(string $countryCode, string $lang = 'fr'): ?array {
    $country = JURISDICTION_COUNTRIES[strtoupper($countryCode)] ?? null;
    if (!$country) return null;
    
    return [
        'code' => $country['code'],
        'name' => $lang === 'fr' ? $country['nameFr'] : $country['nameEn'],
        'flag' => $country['flag'],
        'phoneCode' => $country['phoneCode'],
        'consulate' => CONSULATES[$country['defaultConsulate']] ?? null
    ];
}

