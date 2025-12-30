<?php
/**
 * Données de référence - Règles de workflow
 * Ambassade de Côte d'Ivoire à Addis-Abeba
 * Aligné avec T_Regles_Affichage du dictionnaire
 * 
 * @package VisaChatbot
 */

require_once __DIR__ . '/passport-types.php';
require_once __DIR__ . '/countries.php';

// =========================================
// RÈGLES D'AFFICHAGE CONDITIONNEL (T_Regles_Affichage)
// =========================================

/**
 * Actions possibles pour les règles
 */
const DISPLAY_ACTION_SHOW = 'SHOW';
const DISPLAY_ACTION_HIDE = 'HIDE';
const DISPLAY_ACTION_REQUIRED = 'REQUIRED';
const DISPLAY_ACTION_OPTIONAL = 'OPTIONAL';
const DISPLAY_ACTION_DISABLED = 'DISABLED';

/**
 * 31 règles d'affichage conditionnel alignées avec T_Regles_Affichage
 */
const DISPLAY_RULES = [
    // ===== SECTION: Note Verbale =====
    // R01: NoteVerbale - SHOW si typePassport IN (DIPLOMATIQUE, SERVICE, SPECIAL)
    'R01_NoteVerbale_Show' => [
        'id' => 'R01',
        'field' => 'verbal_note',
        'action' => DISPLAY_ACTION_SHOW,
        'condition' => [
            'field' => 'passport_type',
            'operator' => 'IN',
            'value' => ['DIPLOMATIQUE', 'SERVICE', 'SPECIAL']
        ],
        'message_fr' => 'Note verbale requise pour ce type de passeport',
        'message_en' => 'Verbal note required for this passport type'
    ],
    
    // R02: NoteVerbale - REQUIRED si workflow DIPLOMATIQUE
    'R02_NoteVerbale_Required' => [
        'id' => 'R02',
        'field' => 'verbal_note',
        'action' => DISPLAY_ACTION_REQUIRED,
        'condition' => [
            'field' => 'workflow_category',
            'operator' => '==',
            'value' => WORKFLOW_DIPLOMATIQUE
        ]
    ],
    
    // ===== SECTION: Certificat Vaccination =====
    // R03: CertificatVaccination - SHOW si HasVaccination = Oui
    'R03_Vaccination_Show' => [
        'id' => 'R03',
        'field' => 'vaccination_certificate',
        'action' => DISPLAY_ACTION_SHOW,
        'condition' => [
            'field' => 'has_vaccination',
            'operator' => '==',
            'value' => true
        ]
    ],
    
    // R04: CertificatVaccination - REQUIRED (toujours pour entrée CI)
    'R04_Vaccination_Required' => [
        'id' => 'R04',
        'field' => 'vaccination_certificate',
        'action' => DISPLAY_ACTION_REQUIRED,
        'condition' => [
            'field' => 'destination',
            'operator' => '==',
            'value' => 'CI'
        ],
        'message_fr' => 'Carnet de vaccination fièvre jaune OBLIGATOIRE',
        'message_en' => 'Yellow fever vaccination certificate MANDATORY'
    ],
    
    // ===== SECTION: Option Express =====
    // R05: IsExpress - HIDE si typePassport IN (DIPLOMATIQUE, SERVICE, SPECIAL, LP_ONU, LP_UA)
    'R05_Express_Hide' => [
        'id' => 'R05',
        'field' => 'is_express',
        'action' => DISPLAY_ACTION_HIDE,
        'condition' => [
            'field' => 'passport_type',
            'operator' => 'IN',
            'value' => ['DIPLOMATIQUE', 'SERVICE', 'SPECIAL', 'LP_ONU', 'LP_UA']
        ],
        'message_fr' => 'Option express non disponible (traitement déjà prioritaire)',
        'message_en' => 'Express option not available (already priority processing)'
    ],
    
    // R06: IsExpress - SHOW si workflow ORDINAIRE
    'R06_Express_Show' => [
        'id' => 'R06',
        'field' => 'is_express',
        'action' => DISPLAY_ACTION_SHOW,
        'condition' => [
            'field' => 'workflow_category',
            'operator' => '==',
            'value' => WORKFLOW_ORDINAIRE
        ]
    ],
    
    // ===== SECTION: Paiement =====
    // R07: ChoixModePaiement - HIDE si typePassport gratuit
    'R07_Payment_Hide' => [
        'id' => 'R07',
        'field' => 'payment_method',
        'action' => DISPLAY_ACTION_HIDE,
        'condition' => [
            'field' => 'passport_type',
            'operator' => 'IN',
            'value' => ['DIPLOMATIQUE', 'SERVICE', 'SPECIAL', 'LP_ONU', 'LP_UA']
        ]
    ],
    
    // R08: MontantFrais - SHOW 0 XOF si gratuit
    'R08_Fees_Free' => [
        'id' => 'R08',
        'field' => 'total_fees',
        'action' => DISPLAY_ACTION_SHOW,
        'condition' => [
            'field' => 'is_free',
            'operator' => '==',
            'value' => true
        ],
        'display_value' => 'GRATUIT'
    ],
    
    // R09: FraisExpress - SHOW si IsExpress = Oui ET workflow ORDINAIRE
    'R09_ExpressFees_Show' => [
        'id' => 'R09',
        'field' => 'express_fees',
        'action' => DISPLAY_ACTION_SHOW,
        'condition' => [
            'type' => 'AND',
            'conditions' => [
                ['field' => 'is_express', 'operator' => '==', 'value' => true],
                ['field' => 'workflow_category', 'operator' => '==', 'value' => WORKFLOW_ORDINAIRE]
            ]
        ]
    ],
    
    // ===== SECTION: Type de séjour =====
    // R10: PersoInvitLetter - SHOW si TypeLieuSejour = "Chez un particulier"
    'R10_InvitationLetter_Show' => [
        'id' => 'R10',
        'field' => 'personal_invitation_letter',
        'action' => DISPLAY_ACTION_SHOW,
        'condition' => [
            'field' => 'accommodation_type',
            'operator' => '==',
            'value' => 'PARTICULIER'
        ],
        'message_fr' => 'Lettre d\'invitation de l\'hôte requise',
        'message_en' => 'Host invitation letter required'
    ],
    
    // R11: HotePassport - SHOW si TypeLieuSejour = "Chez un particulier"
    'R11_HostPassport_Show' => [
        'id' => 'R11',
        'field' => 'host_passport',
        'action' => DISPLAY_ACTION_SHOW,
        'condition' => [
            'field' => 'accommodation_type',
            'operator' => '==',
            'value' => 'PARTICULIER'
        ],
        'message_fr' => 'Copie du passeport/CNI de l\'hôte requise',
        'message_en' => 'Host passport/ID copy required'
    ],
    
    // R12: ReservationHotel - SHOW si TypeLieuSejour = "Hôtel"
    'R12_HotelReservation_Show' => [
        'id' => 'R12',
        'field' => 'hotel_reservation',
        'action' => DISPLAY_ACTION_SHOW,
        'condition' => [
            'field' => 'accommodation_type',
            'operator' => '==',
            'value' => 'HOTEL'
        ]
    ],
    
    // R13: AdresseEntreprise - SHOW si TypeLieuSejour = "Entreprise"
    'R13_CompanyAddress_Show' => [
        'id' => 'R13',
        'field' => 'company_address',
        'action' => DISPLAY_ACTION_SHOW,
        'condition' => [
            'field' => 'accommodation_type',
            'operator' => '==',
            'value' => 'ENTREPRISE'
        ]
    ],
    
    // ===== SECTION: Nombre d'entrées =====
    // R14: NbEntrees - HIDE si visaType = TRANSIT
    'R14_NumEntries_Hide' => [
        'id' => 'R14',
        'field' => 'num_entries',
        'action' => DISPLAY_ACTION_HIDE,
        'condition' => [
            'field' => 'visa_type',
            'operator' => '==',
            'value' => 'TRANSIT'
        ]
    ],
    
    // R15: NbEntrees - SHOW Multiple option si durée > 30 jours
    'R15_MultipleEntries_Show' => [
        'id' => 'R15',
        'field' => 'num_entries_multiple',
        'action' => DISPLAY_ACTION_SHOW,
        'condition' => [
            'field' => 'stay_duration',
            'operator' => '>',
            'value' => 30
        ]
    ],
    
    // ===== SECTION: Motif de voyage =====
    // R16: MissionOrder - SHOW si tripPurpose = AFFAIRES
    'R16_MissionOrder_Show' => [
        'id' => 'R16',
        'field' => 'mission_order',
        'action' => DISPLAY_ACTION_SHOW,
        'condition' => [
            'field' => 'trip_purpose',
            'operator' => '==',
            'value' => 'AFFAIRES'
        ]
    ],
    
    // R17: ConferenceInvitation - SHOW si tripPurpose = CONFERENCE
    'R17_ConferenceInvite_Show' => [
        'id' => 'R17',
        'field' => 'conference_invitation',
        'action' => DISPLAY_ACTION_SHOW,
        'condition' => [
            'field' => 'trip_purpose',
            'operator' => '==',
            'value' => 'CONFERENCE'
        ]
    ],
    
    // R18: MedicalCertificate - SHOW si tripPurpose = MEDICAL
    'R18_MedicalCert_Show' => [
        'id' => 'R18',
        'field' => 'medical_certificate',
        'action' => DISPLAY_ACTION_SHOW,
        'condition' => [
            'field' => 'trip_purpose',
            'operator' => '==',
            'value' => 'MEDICAL'
        ]
    ],
    
    // R19: FamilyProof - SHOW si tripPurpose = FAMILLE
    'R19_FamilyProof_Show' => [
        'id' => 'R19',
        'field' => 'family_proof',
        'action' => DISPLAY_ACTION_SHOW,
        'condition' => [
            'field' => 'trip_purpose',
            'operator' => '==',
            'value' => 'FAMILLE'
        ]
    ],
    
    // ===== SECTION: Mineur =====
    // R20: ParentalConsent - SHOW si age < 18
    'R20_ParentalConsent_Show' => [
        'id' => 'R20',
        'field' => 'parental_consent',
        'action' => DISPLAY_ACTION_SHOW,
        'condition' => [
            'field' => 'is_minor',
            'operator' => '==',
            'value' => true
        ],
        'message_fr' => 'Autorisation parentale requise pour les mineurs',
        'message_en' => 'Parental consent required for minors'
    ],
    
    // R21: BirthCertificate - SHOW si age < 18
    'R21_BirthCert_Show' => [
        'id' => 'R21',
        'field' => 'birth_certificate',
        'action' => DISPLAY_ACTION_SHOW,
        'condition' => [
            'field' => 'is_minor',
            'operator' => '==',
            'value' => true
        ]
    ],
    
    // ===== SECTION: Délai de traitement =====
    // R22: ProcessingTime - SHOW "24-48h" si workflow DIPLOMATIQUE
    'R22_ProcessingTime_Priority' => [
        'id' => 'R22',
        'field' => 'processing_time',
        'action' => DISPLAY_ACTION_SHOW,
        'condition' => [
            'field' => 'workflow_category',
            'operator' => '==',
            'value' => WORKFLOW_DIPLOMATIQUE
        ],
        'display_value' => '24-48h'
    ],
    
    // R23: ProcessingTime - SHOW "5-10 jours" si workflow ORDINAIRE standard
    'R23_ProcessingTime_Standard' => [
        'id' => 'R23',
        'field' => 'processing_time',
        'action' => DISPLAY_ACTION_SHOW,
        'condition' => [
            'type' => 'AND',
            'conditions' => [
                ['field' => 'workflow_category', 'operator' => '==', 'value' => WORKFLOW_ORDINAIRE],
                ['field' => 'is_express', 'operator' => '==', 'value' => false]
            ]
        ],
        'display_value' => '5-10 jours ouvrés'
    ],
    
    // R24: ProcessingTime - SHOW "24-48h" si ORDINAIRE + Express
    'R24_ProcessingTime_Express' => [
        'id' => 'R24',
        'field' => 'processing_time',
        'action' => DISPLAY_ACTION_SHOW,
        'condition' => [
            'type' => 'AND',
            'conditions' => [
                ['field' => 'workflow_category', 'operator' => '==', 'value' => WORKFLOW_ORDINAIRE],
                ['field' => 'is_express', 'operator' => '==', 'value' => true]
            ]
        ],
        'display_value' => '24-48h'
    ],
    
    // ===== SECTION: Documents justificatifs =====
    // R25: FinancialProof - SHOW si workflow ORDINAIRE
    'R25_FinancialProof_Show' => [
        'id' => 'R25',
        'field' => 'financial_proof',
        'action' => DISPLAY_ACTION_SHOW,
        'condition' => [
            'field' => 'workflow_category',
            'operator' => '==',
            'value' => WORKFLOW_ORDINAIRE
        ]
    ],
    
    // R26: FinancialProof - HIDE si workflow DIPLOMATIQUE
    'R26_FinancialProof_Hide' => [
        'id' => 'R26',
        'field' => 'financial_proof',
        'action' => DISPLAY_ACTION_HIDE,
        'condition' => [
            'field' => 'workflow_category',
            'operator' => '==',
            'value' => WORKFLOW_DIPLOMATIQUE
        ]
    ],
    
    // R27: FlightTicket - OPTIONAL si typePassport = LP_ONU ou LP_UA
    'R27_FlightTicket_Optional' => [
        'id' => 'R27',
        'field' => 'flight_ticket',
        'action' => DISPLAY_ACTION_OPTIONAL,
        'condition' => [
            'field' => 'passport_type',
            'operator' => 'IN',
            'value' => ['LP_ONU', 'LP_UA']
        ]
    ],
    
    // ===== SECTION: Résidence =====
    // R28: ResidenceProof - SHOW si nationalité != pays de résidence
    'R28_ResidenceProof_Show' => [
        'id' => 'R28',
        'field' => 'residence_proof',
        'action' => DISPLAY_ACTION_SHOW,
        'condition' => [
            'type' => 'DIFF',
            'fields' => ['nationality', 'residence_country']
        ],
        'message_fr' => 'Justificatif de résidence requis',
        'message_en' => 'Proof of residence required'
    ],
    
    // ===== SECTION: Visa type spécifique =====
    // R29: ReturnTicket - REQUIRED si visaType = TRANSIT
    'R29_ReturnTicket_Transit' => [
        'id' => 'R29',
        'field' => 'return_ticket',
        'action' => DISPLAY_ACTION_REQUIRED,
        'condition' => [
            'field' => 'visa_type',
            'operator' => '==',
            'value' => 'TRANSIT'
        ],
        'message_fr' => 'Billet de continuation/retour obligatoire pour le transit',
        'message_en' => 'Onward/return ticket mandatory for transit'
    ],
    
    // R30: FinalDestinationVisa - SHOW si visaType = TRANSIT
    'R30_FinalDestVisa_Transit' => [
        'id' => 'R30',
        'field' => 'final_destination_visa',
        'action' => DISPLAY_ACTION_SHOW,
        'condition' => [
            'field' => 'visa_type',
            'operator' => '==',
            'value' => 'TRANSIT'
        ],
        'message_fr' => 'Visa de la destination finale (si requis)',
        'message_en' => 'Final destination visa (if required)'
    ],
    
    // R31: SAUF_CONDUIT - Workflow spécial
    'R31_SaufConduit_Special' => [
        'id' => 'R31',
        'field' => 'special_circumstances',
        'action' => DISPLAY_ACTION_SHOW,
        'condition' => [
            'field' => 'passport_type',
            'operator' => '==',
            'value' => 'SAUF_CONDUIT'
        ],
        'message_fr' => 'Situation exceptionnelle - documents selon cas',
        'message_en' => 'Exceptional situation - documents as per case'
    ]
];

// =========================================
// DOCUMENTS REQUIS
// =========================================

// Documents requis par workflow
const REQUIRED_DOCUMENTS = [
    // Documents communs (tous les workflows)
    'COMMON' => [
        'passport_scan' => [
            'id' => 'passport_scan',
            'nameFr' => 'Copie du passeport (page biographique)',
            'nameEn' => 'Passport copy (biographical page)',
            'required' => true,
            'formats' => ['PDF', 'JPG', 'PNG'],
            'maxSizeMB' => 5
        ],
        'photo' => [
            'id' => 'photo',
            'nameFr' => 'Photo d\'identité récente (4x4)',
            'nameEn' => 'Recent passport photo (4x4)',
            'required' => true,
            'formats' => ['JPG', 'PNG'],
            'maxSizeMB' => 2
        ],
        'flight_ticket' => [
            'id' => 'flight_ticket',
            'nameFr' => 'Billet d\'avion aller-retour',
            'nameEn' => 'Round-trip flight ticket',
            'required' => true,
            'formats' => ['PDF', 'JPG', 'PNG'],
            'maxSizeMB' => 5
        ],
        'vaccination' => [
            'id' => 'vaccination',
            'nameFr' => 'Carnet de vaccination fièvre jaune',
            'nameEn' => 'Yellow fever vaccination certificate',
            'required' => true,
            'formats' => ['PDF', 'JPG', 'PNG'],
            'maxSizeMB' => 5
        ]
    ],
    
    // Documents spécifiques ORDINAIRE
    'ORDINAIRE' => [
        'invitation_letter' => [
            'id' => 'invitation_letter',
            'nameFr' => 'Lettre d\'invitation légalisée',
            'nameEn' => 'Legalized invitation letter',
            'required' => true,
            'formats' => ['PDF', 'JPG', 'PNG'],
            'maxSizeMB' => 5
        ],
        'accommodation' => [
            'id' => 'accommodation',
            'nameFr' => 'Justificatif d\'hébergement',
            'nameEn' => 'Accommodation proof',
            'required' => true,
            'formats' => ['PDF', 'JPG', 'PNG'],
            'maxSizeMB' => 5,
            'conditionalOn' => 'accommodation_type'
        ],
        'financial_proof' => [
            'id' => 'financial_proof',
            'nameFr' => 'Justificatif de ressources financières',
            'nameEn' => 'Proof of financial resources',
            'required' => true,
            'formats' => ['PDF', 'JPG', 'PNG'],
            'maxSizeMB' => 5
        ]
    ],
    
    // Documents spécifiques DIPLOMATIQUE
    'DIPLOMATIQUE' => [
        'verbal_note' => [
            'id' => 'verbal_note',
            'nameFr' => 'Note verbale',
            'nameEn' => 'Verbal note',
            'required' => true,
            'formats' => ['PDF', 'DOC', 'DOCX'],
            'maxSizeMB' => 5
        ]
    ]
];

// =========================================
// RÈGLES DE VALIDATION
// =========================================

// Règles de validation par étape (flux complet 21 étapes)
const STEP_VALIDATION_RULES = [
    'welcome' => [
        'required_fields' => ['language'],
        'next_step' => 'passport',
        'order' => 0
    ],
    'passport' => [
        'required_fields' => ['passport_data'],
        'validators' => ['validatePassport'],
        'next_step' => 'residence',
        'workflow_detection' => true,
        'order' => 1
    ],
    'residence' => [
        'required_fields' => ['country_code'],
        'validators' => ['validateResidence'],
        'next_step' => 'residence_card',
        'blocking_condition' => 'isOutOfJurisdiction',
        'order' => 2
    ],
    'residence_card' => [
        'required_fields' => ['residence_card_data'],
        'condition' => ['nationality', '!=', 'residence_country'],
        'next_step' => 'ticket',
        'order' => 3
    ],
    'ticket' => [
        'required_fields' => ['ticket_data'],
        'validators' => ['validateTicket'],
        'condition' => ['passport_type', '==', 'ORDINAIRE'],
        'next_step' => 'hotel',
        'order' => 4
    ],
    'hotel' => [
        'required_fields' => ['accommodation_data'],
        'condition' => [
            ['passport_type', '==', 'ORDINAIRE'],
            ['has_invitation', '!=', true]
        ],
        'next_step' => 'vaccination',
        'alternative_to' => 'accommodation',
        'order' => 5
    ],
    'accommodation' => [
        'required_fields' => ['accommodation_data', 'host_data'],
        'condition' => [
            ['passport_type', '==', 'ORDINAIRE'],
            ['has_hotel', '!=', true]
        ],
        'next_step' => 'vaccination',
        'alternative_to' => 'hotel',
        'order' => 6
    ],
    'vaccination' => [
        'required_fields' => ['vaccination_certificate'],
        'validators' => ['validateVaccination'],
        'condition' => ['nationality', 'NOT_IN', 'YELLOW_FEVER_EXEMPT_COUNTRIES'],
        'next_step' => 'invitation',
        'order' => 7
    ],
    'invitation' => [
        'required_fields' => [],
        'optional' => true,
        'condition' => ['passport_type', '==', 'ORDINAIRE'],
        'next_step' => 'verbal_note',
        'order' => 8
    ],
    'verbal_note' => [
        'required_fields' => ['verbal_note_data'],
        'condition' => ['passport_type', 'IN', ['DIPLOMATIQUE', 'SERVICE', 'LP_ONU', 'LP_UA']],
        'next_step' => 'eligibility',
        'order' => 9
    ],
    'eligibility' => [
        'required_fields' => [],
        'validators' => ['validateCrossDocuments', 'validateCompleteness'],
        'is_checkpoint' => true,
        'next_step' => 'minor_auth',
        'order' => 10
    ],
    'minor_auth' => [
        'required_fields' => ['parental_auth', 'birth_certificate', 'parent_id'],
        'condition' => ['is_minor', '==', true],
        'next_step' => 'transit_info',
        'order' => 11
    ],
    'transit_info' => [
        'required_fields' => ['final_destination', 'continuation_ticket'],
        'condition' => ['visa_type', '==', 'transit'],
        'validators' => ['validateTransitDuration'],
        'max_hours' => 72,
        'next_step' => 'photo',
        'order' => 12
    ],
    'photo' => [
        'required_fields' => ['photo_path'],
        'validators' => ['validatePhoto', 'validateFaceMatch'],
        'next_step' => 'contact',
        'order' => 13
    ],
    'contact' => [
        'required_fields' => ['email', 'phone'],
        'validators' => ['validateEmail', 'validatePhone'],
        'next_step' => 'trip',
        'order' => 14
    ],
    'trip' => [
        'required_fields' => ['trip_purpose', 'visa_type'],
        'validators' => ['validateTripDates'],
        'next_step' => 'health',
        'order' => 15
    ],
    'health' => [
        'required_fields' => ['health_declaration'],
        'next_step' => 'customs',
        'order' => 16
    ],
    'customs' => [
        'required_fields' => ['customs_declaration'],
        'next_step' => 'payment',
        'order' => 17
    ],
    'payment' => [
        'required_fields' => ['payment_method', 'payment_proof'],
        'condition' => ['passport_type', '==', 'ORDINAIRE'],
        'validators' => ['validatePayment'],
        'next_step' => 'confirm',
        'order' => 18
    ],
    'confirm' => [
        'required_fields' => ['summary_approved'],
        'is_checkpoint' => true,
        'next_step' => 'signature',
        'order' => 19
    ],
    'signature' => [
        'required_fields' => ['signature', 'terms_accepted'],
        'next_step' => 'completion',
        'order' => 20
    ],
    'completion' => [
        'required_fields' => [],
        'next_step' => null,
        'is_final' => true,
        'generates' => ['reference_number', 'submission_timestamp'],
        'order' => 21
    ]
];

/**
 * Pays exemptés de vaccination fièvre jaune
 */
const YELLOW_FEVER_EXEMPT_COUNTRIES = [
    'FRA', 'DEU', 'GBR', 'ITA', 'ESP', 'PRT', 'NLD', 'BEL', 'CHE', 'AUT',
    'SWE', 'NOR', 'DNK', 'FIN', 'IRL', 'USA', 'CAN', 'AUS', 'NZL',
    'JPN', 'KOR', 'SGP', 'POL', 'CZE', 'HUN', 'ROU', 'GRC',
    'ARE', 'QAT', 'KWT', 'BHR', 'OMN', 'SAU', 'ISR', 'JOR', 'LBN'
];

// Types de visa
const VISA_TYPES = [
    'COURT_SEJOUR' => [
        'labelFr' => 'Visa de court séjour',
        'labelEn' => 'Short-stay visa',
        'maxDays' => 90,
        'entriesAllowed' => ['Unique', 'Multiple']
    ],
    'TRANSIT' => [
        'labelFr' => 'Visa de transit',
        'labelEn' => 'Transit visa',
        'maxDays' => 7,
        'entriesAllowed' => ['Unique']
    ],
    'TRES_COURT' => [
        'labelFr' => 'Visa très court séjour',
        'labelEn' => 'Very short stay visa',
        'maxDays' => 15,
        'entriesAllowed' => ['Unique']
    ]
];

// Motifs de voyage
const TRIP_PURPOSES = [
    'TOURISME' => ['labelFr' => 'Tourisme', 'labelEn' => 'Tourism', 'icon' => '🏖️'],
    'AFFAIRES' => ['labelFr' => 'Affaires', 'labelEn' => 'Business', 'icon' => '💼'],
    'FAMILLE' => ['labelFr' => 'Visite familiale', 'labelEn' => 'Family visit', 'icon' => '👨‍👩‍👧'],
    'CONFERENCE' => ['labelFr' => 'Conférence', 'labelEn' => 'Conference', 'icon' => '🎓'],
    'MEDICAL' => ['labelFr' => 'Soins médicaux', 'labelEn' => 'Medical treatment', 'icon' => '🏥'],
    'TRANSIT' => ['labelFr' => 'Transit', 'labelEn' => 'Transit', 'icon' => '✈️'],
    'OFFICIEL' => ['labelFr' => 'Mission officielle', 'labelEn' => 'Official mission', 'icon' => '🏛️']
];

// Types d'hébergement
const ACCOMMODATION_TYPES = [
    'HOTEL' => ['labelFr' => 'Hôtel', 'labelEn' => 'Hotel', 'docs' => ['hotel_reservation']],
    'PARTICULIER' => ['labelFr' => 'Chez un particulier', 'labelEn' => 'Private host', 'docs' => ['personal_invitation_letter', 'host_passport']],
    'ENTREPRISE' => ['labelFr' => 'Logement entreprise', 'labelEn' => 'Company housing', 'docs' => ['company_address']]
];

// =========================================
// FONCTIONS
// =========================================

/**
 * Évalue les règles d'affichage pour un contexte donné
 */
function evaluateDisplayRules(array $context): array {
    $result = [];
    
    foreach (DISPLAY_RULES as $ruleKey => $rule) {
        $matches = evaluateCondition($rule['condition'], $context);
        
        if ($matches) {
            $field = $rule['field'];
            $result[$field] = [
                'action' => $rule['action'],
                'rule_id' => $rule['id'],
                'display_value' => $rule['display_value'] ?? null,
                'message' => $context['lang'] === 'en' 
                    ? ($rule['message_en'] ?? null) 
                    : ($rule['message_fr'] ?? null)
            ];
        }
    }
    
    return $result;
}

/**
 * Évalue une condition
 */
function evaluateCondition(array $condition, array $context): bool {
    // Condition composite (AND/OR)
    if (isset($condition['type'])) {
        $type = $condition['type'];
        
        if ($type === 'AND') {
            foreach ($condition['conditions'] as $subCondition) {
                if (!evaluateCondition($subCondition, $context)) {
                    return false;
                }
            }
            return true;
        }
        
        if ($type === 'OR') {
            foreach ($condition['conditions'] as $subCondition) {
                if (evaluateCondition($subCondition, $context)) {
                    return true;
                }
            }
            return false;
        }
        
        if ($type === 'DIFF') {
            $fields = $condition['fields'];
            $val1 = $context[$fields[0]] ?? null;
            $val2 = $context[$fields[1]] ?? null;
            return $val1 !== $val2;
        }
    }
    
    // Condition simple
    $field = $condition['field'];
    $operator = $condition['operator'];
    $value = $condition['value'];
    $contextValue = $context[$field] ?? null;
    
    switch ($operator) {
        case '==':
            return $contextValue == $value;
        case '!=':
            return $contextValue != $value;
        case '>':
            return $contextValue > $value;
        case '>=':
            return $contextValue >= $value;
        case '<':
            return $contextValue < $value;
        case '<=':
            return $contextValue <= $value;
        case 'IN':
            return in_array($contextValue, (array)$value);
        case 'NOT_IN':
            return !in_array($contextValue, (array)$value);
        default:
            return false;
    }
}

/**
 * Retourne les documents requis pour un workflow avec règles d'affichage
 */
function getRequiredDocuments(string $workflowCategory, array $context = [], string $lang = 'fr'): array {
    $docs = [];
    
    // Appliquer les règles d'affichage
    $displayRules = evaluateDisplayRules(array_merge($context, ['lang' => $lang]));
    
    // Documents communs
    foreach (REQUIRED_DOCUMENTS['COMMON'] as $doc) {
        $docRule = $displayRules[$doc['id']] ?? null;
        
        // Vérifier si masqué
        if ($docRule && $docRule['action'] === DISPLAY_ACTION_HIDE) {
            continue;
        }
        
        $docs[] = [
            'id' => $doc['id'],
            'name' => $lang === 'fr' ? $doc['nameFr'] : $doc['nameEn'],
            'required' => $docRule ? ($docRule['action'] === DISPLAY_ACTION_REQUIRED) : $doc['required'],
            'optional' => $docRule ? ($docRule['action'] === DISPLAY_ACTION_OPTIONAL) : false,
            'formats' => $doc['formats'],
            'message' => $docRule['message'] ?? null
        ];
    }
    
    // Documents spécifiques au workflow
    $specific = REQUIRED_DOCUMENTS[$workflowCategory] ?? [];
    foreach ($specific as $doc) {
        $docRule = $displayRules[$doc['id']] ?? null;
        
        if ($docRule && $docRule['action'] === DISPLAY_ACTION_HIDE) {
            continue;
        }
        
        $docs[] = [
            'id' => $doc['id'],
            'name' => $lang === 'fr' ? $doc['nameFr'] : $doc['nameEn'],
            'required' => $docRule ? ($docRule['action'] === DISPLAY_ACTION_REQUIRED) : $doc['required'],
            'optional' => $docRule ? ($docRule['action'] === DISPLAY_ACTION_OPTIONAL) : false,
            'formats' => $doc['formats'],
            'message' => $docRule['message'] ?? null
        ];
    }
    
    return $docs;
}

/**
 * Valide la résidence (vérifie si dans la circonscription)
 */
function validateResidence(array $data): array {
    $errors = [];
    
    if (empty($data['country_code'])) {
        $errors[] = ['field' => 'country_code', 'message' => 'Le pays de résidence est requis'];
    } elseif (!isInJurisdiction($data['country_code'])) {
        $errors[] = [
            'field' => 'country_code', 
            'message' => 'Ce pays n\'est pas dans la circonscription de cette ambassade',
            'blocking' => true
        ];
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'blocking' => !empty(array_filter($errors, fn($e) => $e['blocking'] ?? false))
    ];
}

/**
 * Vérifie si l'utilisateur est hors juridiction
 */
function isOutOfJurisdiction(array $data): bool {
    return !empty($data['country_code']) && !isInJurisdiction($data['country_code']);
}

/**
 * Valide les données du passeport
 */
function validatePassport(array $data): array {
    $errors = [];
    
    $passport = $data['passport_data'] ?? [];
    
    if (empty($passport['passport_number'])) {
        $errors[] = ['field' => 'passport_number', 'message' => 'Le numéro de passeport est requis'];
    }
    
    if (!empty($passport['date_of_expiry'])) {
        $expiry = strtotime($passport['date_of_expiry']);
        $departureDate = strtotime($data['departure_date'] ?? '+6 months');
        $minValidity = strtotime('+6 months', $departureDate);
        
        if ($expiry < $minValidity) {
            $errors[] = [
                'field' => 'date_of_expiry',
                'message' => 'Le passeport doit être valide au moins 6 mois après la date de départ',
                'warning' => true
            ];
        }
    }
    
    return [
        'valid' => empty(array_filter($errors, fn($e) => !($e['warning'] ?? false))),
        'errors' => $errors
    ];
}

/**
 * Valide les dates de voyage
 */
function validateTripDates(array $data): array {
    $errors = [];
    
    $arrival = strtotime($data['arrival_date'] ?? '');
    $departure = strtotime($data['departure_date'] ?? '');
    
    if (!$arrival) {
        $errors[] = ['field' => 'arrival_date', 'message' => 'La date d\'arrivée est requise'];
    }
    
    if (!$departure) {
        $errors[] = ['field' => 'departure_date', 'message' => 'La date de départ est requise'];
    }
    
    if ($arrival && $departure) {
        if ($arrival >= $departure) {
            $errors[] = ['field' => 'dates', 'message' => 'La date de départ doit être après la date d\'arrivée'];
        }
        
        $stayDays = ($departure - $arrival) / 86400;
        $visaType = $data['visa_type'] ?? 'COURT_SEJOUR';
        $maxDays = VISA_TYPES[$visaType]['maxDays'] ?? 90;
        
        if ($stayDays > $maxDays) {
            $errors[] = [
                'field' => 'dates',
                'message' => "La durée du séjour dépasse le maximum autorisé ($maxDays jours)"
            ];
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Valide la vaccination
 */
function validateVaccination(array $data): array {
    $errors = [];
    
    if (empty($data['yellow_fever_vaccinated']) || $data['yellow_fever_vaccinated'] !== true) {
        $errors[] = [
            'field' => 'yellow_fever_vaccinated',
            'message' => 'La vaccination contre la fièvre jaune est OBLIGATOIRE pour entrer en Côte d\'Ivoire',
            'blocking' => true
        ];
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'blocking' => !empty(array_filter($errors, fn($e) => $e['blocking'] ?? false))
    ];
}

/**
 * Retourne les quick actions pour les types de visa
 */
function getVisaTypeQuickActions(string $lang = 'fr'): array {
    $actions = [];
    foreach (VISA_TYPES as $code => $type) {
        $actions[] = [
            'label' => $lang === 'fr' ? $type['labelFr'] : $type['labelEn'],
            'value' => $code
        ];
    }
    return $actions;
}

/**
 * Retourne les quick actions pour les motifs de voyage
 */
function getTripPurposeQuickActions(string $lang = 'fr'): array {
    $actions = [];
    foreach (TRIP_PURPOSES as $code => $purpose) {
        $actions[] = [
            'label' => $purpose['icon'] . ' ' . ($lang === 'fr' ? $purpose['labelFr'] : $purpose['labelEn']),
            'value' => $code
        ];
    }
    return $actions;
}

/**
 * Retourne les quick actions pour les types d'hébergement
 */
function getAccommodationTypeQuickActions(string $lang = 'fr'): array {
    $actions = [];
    foreach (ACCOMMODATION_TYPES as $code => $type) {
        $actions[] = [
            'label' => $lang === 'fr' ? $type['labelFr'] : $type['labelEn'],
            'value' => $code
        ];
    }
    return $actions;
}

/**
 * Retourne les champs conditionnels requis pour une étape et un workflow
 */
function getConditionalFields(string $step, string $workflowCategory): array {
    $rules = STEP_VALIDATION_RULES[$step] ?? [];
    return $rules['conditional_fields'][$workflowCategory] ?? [];
}

/**
 * Calcule le résumé des frais basé sur le contexte
 */
function calculateFeeSummary(array $context): array {
    $passportType = $context['passport_type'] ?? 'ORDINAIRE';
    $visaType = $context['visa_type'] ?? 'COURT_SEJOUR';
    $nbEntrees = $context['num_entries'] ?? 'Unique';
    $isExpress = $context['is_express'] ?? false;
    
    $fees = calculateFees($passportType, $visaType, $nbEntrees, $isExpress);
    
    return [
        'base' => $fees['baseFee'],
        'express' => $fees['expressFee'],
        'total' => $fees['total'],
        'is_free' => $fees['isFree'],
        'currency' => $fees['currency'],
        'formatted_total' => formatPrice($fees['total'], $fees['currency'])
    ];
}

