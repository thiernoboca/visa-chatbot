<?php
/**
 * Données de référence - Types de passeport et workflows
 * Ambassade de Côte d'Ivoire à Addis-Abeba
 * Aligné avec T_Reference_Valeurs et T_Workflow du dictionnaire
 * 
 * @package VisaChatbot
 */

// Types de workflow
const WORKFLOW_ORDINAIRE = 'ORDINAIRE';
const WORKFLOW_DIPLOMATIQUE = 'DIPLOMATIQUE';

// Workflow types (pour différenciation des traitements)
const WORKFLOW_TYPE_STANDARD = 'STANDARD';   // 5-10 jours, payant
const WORKFLOW_TYPE_PRIORITY = 'PRIORITY';   // 24-48h, gratuit
const WORKFLOW_TYPE_CUSTOM = 'CUSTOM';       // Cas par cas

// Types de passeport avec leur configuration (aligné avec T_Reference_Valeurs)
const PASSPORT_TYPES = [
    'ORDINAIRE' => [
        'code' => 'ORD',
        'labelFr' => 'Passeport ordinaire',
        'labelEn' => 'Ordinary passport',
        'workflowCategory' => WORKFLOW_ORDINAIRE,
        'workflowType' => WORKFLOW_TYPE_STANDARD,
        'mrzCode' => 'P',
        'isFree' => false,
        'isPriority' => false,
        'requiresVerbalNote' => false,
        'expressAvailable' => true,
        'processingTime' => '5-10 jours ouvrés',
        'characteristics' => [
            'fr' => ['Paiement requis', 'Délai standard (5-10 jours)', 'Lettre invitation, Billet, Réservation requis'],
            'en' => ['Payment required', 'Standard processing (5-10 days)', 'Invitation letter, Ticket, Reservation required']
        ]
    ],
    'DIPLOMATIQUE' => [
        'code' => 'DIP',
        'labelFr' => 'Passeport diplomatique',
        'labelEn' => 'Diplomatic passport',
        'workflowCategory' => WORKFLOW_DIPLOMATIQUE,
        'workflowType' => WORKFLOW_TYPE_PRIORITY,
        'mrzCode' => 'D',
        'isFree' => true,
        'isPriority' => true,
        'requiresVerbalNote' => true,
        'expressAvailable' => false, // Déjà prioritaire
        'processingTime' => '24-48h',
        'characteristics' => [
            'fr' => ['Gratuit', 'Traitement prioritaire (24-48h)', 'Note verbale obligatoire'],
            'en' => ['Free of charge', 'Priority processing (24-48h)', 'Verbal note required']
        ]
    ],
    'SERVICE' => [
        'code' => 'SRV',
        'labelFr' => 'Passeport de service',
        'labelEn' => 'Service passport',
        'workflowCategory' => WORKFLOW_DIPLOMATIQUE,
        'workflowType' => WORKFLOW_TYPE_PRIORITY,
        'mrzCode' => 'S',
        'isFree' => true,
        'isPriority' => true,
        'requiresVerbalNote' => true,
        'expressAvailable' => false,
        'processingTime' => '24-48h',
        'characteristics' => [
            'fr' => ['Gratuit', 'Traitement prioritaire (24-48h)', 'Note verbale obligatoire'],
            'en' => ['Free of charge', 'Priority processing (24-48h)', 'Verbal note required']
        ]
    ],
    'SPECIAL' => [
        'code' => 'SPC',
        'labelFr' => 'Passeport spécial',
        'labelEn' => 'Special passport',
        'workflowCategory' => WORKFLOW_DIPLOMATIQUE,
        'workflowType' => WORKFLOW_TYPE_PRIORITY,
        'mrzCode' => 'PS',
        'isFree' => true,
        'isPriority' => true,
        'requiresVerbalNote' => true, // Selon cas
        'expressAvailable' => false,
        'processingTime' => '48-72h',
        'characteristics' => [
            'fr' => ['Gratuit', 'Priorité moyenne-haute', 'Documents selon mission'],
            'en' => ['Free of charge', 'Medium-high priority', 'Documents per mission']
        ]
    ],
    'LP_ONU' => [
        'code' => 'LPO',
        'labelFr' => 'Laissez-passer ONU',
        'labelEn' => 'UN Laissez-passer',
        'workflowCategory' => WORKFLOW_DIPLOMATIQUE,
        'workflowType' => WORKFLOW_TYPE_PRIORITY,
        'mrzCode' => 'L',
        'isFree' => true,
        'isPriority' => true,
        'requiresVerbalNote' => false,
        'expressAvailable' => false,
        'processingTime' => '24-48h',
        'characteristics' => [
            'fr' => ['Gratuit', 'Traitement prioritaire (24-48h)', 'Laissez-passer ONU requis'],
            'en' => ['Free of charge', 'Priority processing (24-48h)', 'UN Laissez-passer required']
        ]
    ],
    'LP_UA' => [
        'code' => 'LPA',
        'labelFr' => 'Laissez-passer UA',
        'labelEn' => 'AU Laissez-passer',
        'workflowCategory' => WORKFLOW_DIPLOMATIQUE,
        'workflowType' => WORKFLOW_TYPE_PRIORITY,
        'mrzCode' => 'L',
        'isFree' => true,
        'isPriority' => true,
        'requiresVerbalNote' => false,
        'expressAvailable' => false,
        'processingTime' => '24-48h',
        'characteristics' => [
            'fr' => ['Gratuit', 'Traitement prioritaire (24-48h)', 'Laissez-passer UA requis'],
            'en' => ['Free of charge', 'Priority processing (24-48h)', 'AU Laissez-passer required']
        ]
    ],
    'TITRE_VOYAGE' => [
        'code' => 'TDV',
        'labelFr' => 'Titre de voyage',
        'labelEn' => 'Travel document',
        'workflowCategory' => WORKFLOW_ORDINAIRE,
        'workflowType' => WORKFLOW_TYPE_STANDARD,
        'mrzCode' => 'P',
        'isFree' => false,
        'isPriority' => false,
        'requiresVerbalNote' => false,
        'expressAvailable' => true,
        'processingTime' => '5-10 jours',
        'characteristics' => [
            'fr' => ['Paiement requis (73 000 XOF)', 'Titre de voyage, Justificatifs requis'],
            'en' => ['Payment required (73,000 XOF)', 'Travel document, Justifications required']
        ]
    ],
    'SAUF_CONDUIT' => [
        'code' => 'SCO',
        'labelFr' => 'Sauf-conduit',
        'labelEn' => 'Safe conduct',
        'workflowCategory' => WORKFLOW_ORDINAIRE,
        'workflowType' => WORKFLOW_TYPE_CUSTOM,
        'mrzCode' => 'P',
        'isFree' => false, // Variable
        'isPriority' => false, // Selon urgence
        'requiresVerbalNote' => true, // Selon cas
        'expressAvailable' => false,
        'processingTime' => 'Variable',
        'characteristics' => [
            'fr' => ['Frais variables selon situation', 'Délai selon urgence'],
            'en' => ['Variable fees per situation', 'Processing time per urgency']
        ]
    ]
];

// Tarification complète (en XOF - CFA) selon T_Workflow
const PRICING = [
    'ORDINAIRE' => [
        'COURT_SEJOUR' => [
            'Unique' => ['baseFee' => 73000, 'expressFee' => 50000],
            'Multiple' => ['baseFee' => 120000, 'expressFee' => 50000]
        ],
        'TRANSIT' => [
            'Unique' => ['baseFee' => 50000, 'expressFee' => 0]
        ],
        'TRES_COURT' => [
            'Unique' => ['baseFee' => 50000, 'expressFee' => 30000]
        ]
    ],
    'DIPLOMATIQUE' => [
        'COURT_SEJOUR' => [
            'Unique' => ['baseFee' => 0, 'expressFee' => 0],
            'Multiple' => ['baseFee' => 0, 'expressFee' => 0]
        ],
        'TRANSIT' => [
            'Unique' => ['baseFee' => 0, 'expressFee' => 0]
        ],
        'TRES_COURT' => [
            'Unique' => ['baseFee' => 0, 'expressFee' => 0]
        ]
    ]
];

// Délais de traitement détaillés
const PROCESSING_TIMES = [
    WORKFLOW_ORDINAIRE => [
        'standard' => ['min' => 5, 'max' => 10, 'unit' => 'days', 'hours' => 120],
        'express' => ['min' => 1, 'max' => 2, 'unit' => 'days', 'hours' => 48]
    ],
    WORKFLOW_DIPLOMATIQUE => [
        'standard' => ['min' => 24, 'max' => 48, 'unit' => 'hours', 'hours' => 48],
        'express' => ['min' => 24, 'max' => 48, 'unit' => 'hours', 'hours' => 48]
    ]
];

// Types nécessitant une note verbale
const TYPES_REQUIRING_VERBAL_NOTE = ['DIPLOMATIQUE', 'SERVICE', 'SPECIAL'];

// Types gratuits (pas de frais)
const FREE_PASSPORT_TYPES = ['DIPLOMATIQUE', 'SERVICE', 'SPECIAL', 'LP_ONU', 'LP_UA'];

// Types prioritaires (traitement 24-48h)
const PRIORITY_PASSPORT_TYPES = ['DIPLOMATIQUE', 'SERVICE', 'SPECIAL', 'LP_ONU', 'LP_UA'];

/**
 * Retourne la catégorie de workflow pour un type de passeport
 */
function getWorkflowCategory(string $passportType): string {
    return PASSPORT_TYPES[$passportType]['workflowCategory'] ?? WORKFLOW_ORDINAIRE;
}

/**
 * Retourne le type de workflow (STANDARD/PRIORITY/CUSTOM)
 */
function getWorkflowType(string $passportType): string {
    return PASSPORT_TYPES[$passportType]['workflowType'] ?? WORKFLOW_TYPE_STANDARD;
}

/**
 * Détecte le type de passeport depuis le code MRZ (ligne 1)
 */
function detectPassportTypeFromMRZ(string $mrzLine1): string {
    $typeCode = strtoupper(substr($mrzLine1, 0, 2));
    $firstChar = $typeCode[0] ?? 'P';
    
    // Mapping des codes MRZ vers types (2 caractères)
    $mrzMapping = [
        'PD' => 'DIPLOMATIQUE',
        'PS' => 'SERVICE',
        'D<' => 'DIPLOMATIQUE',
        'S<' => 'SERVICE',
        'U<' => 'LP_ONU',
        'L<' => 'LP_UA',
        'A<' => 'LP_UA',
    ];
    
    if (isset($mrzMapping[$typeCode])) {
        return $mrzMapping[$typeCode];
    }
    
    // Par premier caractère
    switch ($firstChar) {
        case 'D': return 'DIPLOMATIQUE';
        case 'S': return 'SERVICE';
        case 'U': return 'LP_ONU';      // Laissez-passer ONU
        case 'L': return 'LP_UA';       // Laissez-passer UA
        case 'A': return 'LP_UA';       // Alternative pour UA
        case 'C': return 'SPECIAL';
        case 'T': return 'TITRE_VOYAGE';
        default: return 'ORDINAIRE';
    }
}

/**
 * Vérifie si un type de passeport bénéficie de la gratuité
 */
function isPassportFree(string $passportType): bool {
    return in_array($passportType, FREE_PASSPORT_TYPES) || 
           (PASSPORT_TYPES[$passportType]['isFree'] ?? false);
}

/**
 * Vérifie si un type de passeport bénéficie du traitement prioritaire
 */
function isPassportPriority(string $passportType): bool {
    return in_array($passportType, PRIORITY_PASSPORT_TYPES) ||
           (PASSPORT_TYPES[$passportType]['isPriority'] ?? false);
}

/**
 * Vérifie si une note verbale est requise
 */
function requiresVerbalNote(string $passportType): bool {
    return in_array($passportType, TYPES_REQUIRING_VERBAL_NOTE) ||
           (PASSPORT_TYPES[$passportType]['requiresVerbalNote'] ?? false);
}

/**
 * Vérifie si l'option express est disponible
 */
function isExpressAvailable(string $passportType): bool {
    return PASSPORT_TYPES[$passportType]['expressAvailable'] ?? false;
}

/**
 * Calcule les frais de visa
 */
function calculateFees(
    string $passportType,
    string $visaType = 'COURT_SEJOUR',
    string $nbEntrees = 'Unique',
    bool $isExpress = false
): array {
    $category = getWorkflowCategory($passportType);
    
    // Types gratuits
    if (isPassportFree($passportType)) {
        return [
            'baseFee' => 0,
            'expressFee' => 0,
            'total' => 0,
            'isFree' => true,
            'currency' => 'XOF',
            'currencySymbol' => 'FCFA'
        ];
    }
    
    // Sauf-conduit: frais variables
    if ($passportType === 'SAUF_CONDUIT') {
        return [
            'baseFee' => null,
            'expressFee' => null,
            'total' => null,
            'isFree' => false,
            'isVariable' => true,
            'currency' => 'XOF',
            'currencySymbol' => 'FCFA',
            'message' => 'Frais à déterminer selon situation'
        ];
    }
    
    // Tarification standard
    $pricing = PRICING[$category][$visaType][$nbEntrees] 
            ?? PRICING[$category]['COURT_SEJOUR']['Unique'] 
            ?? ['baseFee' => 73000, 'expressFee' => 50000];
    
    $expressFee = ($isExpress && isExpressAvailable($passportType)) ? $pricing['expressFee'] : 0;
    
    return [
        'baseFee' => $pricing['baseFee'],
        'expressFee' => $expressFee,
        'total' => $pricing['baseFee'] + $expressFee,
        'isFree' => false,
        'currency' => 'XOF',
        'currencySymbol' => 'FCFA'
    ];
}

/**
 * Formate un prix pour l'affichage
 */
function formatPrice(int|null $amount, string $currency = 'XOF'): string {
    if ($amount === null) {
        return 'Variable';
    }
    if ($amount === 0) {
        return 'GRATUIT';
    }
    return number_format($amount, 0, ',', ' ') . ' ' . $currency;
}

/**
 * Retourne les informations d'un type de passeport
 */
function getPassportTypeInfo(string $passportType, string $lang = 'fr'): ?array {
    $type = PASSPORT_TYPES[$passportType] ?? null;
    if (!$type) return null;
    
    return [
        'type' => $passportType,
        'code' => $type['code'],
        'label' => $lang === 'fr' ? $type['labelFr'] : $type['labelEn'],
        'workflow' => $type['workflowCategory'],
        'workflowType' => $type['workflowType'],
        'isFree' => $type['isFree'],
        'isPriority' => $type['isPriority'],
        'requiresVerbalNote' => $type['requiresVerbalNote'],
        'expressAvailable' => $type['expressAvailable'],
        'processingTime' => $type['processingTime'],
        'characteristics' => $type['characteristics'][$lang] ?? $type['characteristics']['fr']
    ];
}

/**
 * Retourne le délai de traitement formaté
 */
function getProcessingTimeText(string $workflowCategory, bool $isExpress = false, string $lang = 'fr'): string {
    $times = PROCESSING_TIMES[$workflowCategory] ?? PROCESSING_TIMES[WORKFLOW_ORDINAIRE];
    $time = $isExpress ? $times['express'] : $times['standard'];
    
    if ($time['unit'] === 'hours') {
        $text = $time['min'] . '-' . $time['max'] . 'h';
        return $text;
    }
    
    if ($time['min'] === $time['max']) {
        $text = $time['min'];
    } else {
        $text = $time['min'] . '-' . $time['max'];
    }
    
    if ($lang === 'fr') {
        $unit = $time['unit'] === 'days' ? 'jours ouvrés' : 'heures';
        return "$text $unit";
    } else {
        $unit = $time['unit'] === 'days' ? 'business days' : 'hours';
        return "$text $unit";
    }
}

/**
 * Retourne tous les types de passeport pour affichage
 */
function getAllPassportTypes(string $lang = 'fr'): array {
    $types = [];
    foreach (PASSPORT_TYPES as $code => $type) {
        $types[] = [
            'code' => $code,
            'label' => $lang === 'fr' ? $type['labelFr'] : $type['labelEn'],
            'isFree' => $type['isFree']
        ];
    }
    return $types;
}

