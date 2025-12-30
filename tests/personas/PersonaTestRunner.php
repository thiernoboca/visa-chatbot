<?php
/**
 * Persona Test Runner - Système de test du workflow chatbot
 *
 * Ce script simule différents profils de demandeurs de visa
 * pour tester le comportement du chatbot et identifier les améliorations.
 */

// Pas de bootstrap requis - chargement direct des dépendances

class PersonaTestRunner
{
    private array $personas = [];
    private array $results = [];
    private string $cacheDir;

    public function __construct()
    {
        $this->cacheDir = __DIR__ . '/../../cache/ocr';
        $this->loadPersonas();
    }

    /**
     * Charge toutes les personas de test
     */
    private function loadPersonas(): void
    {
        // Persona 1: Voyageur d'affaires éthiopien standard
        $this->personas['ethiopian_business'] = [
            'name' => 'Abebe Kebede',
            'description' => 'Homme d\'affaires éthiopien - Voyage court, hôtel réservé',
            'expected_workflow' => 'STANDARD',
            'documents' => [
                'passport' => [
                    'fields' => [
                        'surname' => ['value' => 'KEBEDE'],
                        'given_names' => ['value' => 'ABEBE TADESSE'],
                        'nationality' => ['value' => 'ETH'],
                        'document_number' => ['value' => 'EP1234567'],
                        'date_of_birth' => ['value' => '1985-03-15'],
                        'date_of_expiry' => ['value' => '2028-06-20'],
                        'sex' => ['value' => 'M'],
                    ],
                    'passport_type_detection' => [
                        'type' => 'ORDINAIRE',
                        'workflow' => 'STANDARD'
                    ],
                    'visa_eligibility' => ['is_valid' => true]
                ],
                'ticket' => [
                    'flight_number' => 'ET 501',
                    'departure_date' => '2026-01-15',
                    'departure_city' => 'Addis Ababa',
                    'arrival_city' => 'Abidjan',
                    'passenger_name' => 'KEBEDE ABEBE TADESSE',
                    'return_date' => '2026-01-22',
                    'return_flight_number' => 'ET 502',
                    'is_round_trip' => true
                ],
                'hotel' => [
                    'guest_name' => 'KEBEDE ABEBE TADESSE',
                    'hotel_name' => 'Sofitel Abidjan Hôtel Ivoire',
                    'hotel_city' => 'Abidjan',
                    'check_in_date' => '2026-01-15',
                    'check_out_date' => '2026-01-22',
                    'confirmation_number' => 'SOF789456',
                    'payment_status' => 'CONFIRMED',
                    'number_of_guests' => 1
                ],
                'vaccination' => [
                    'holder_name' => 'KEBEDE ABEBE TADESSE',
                    'vaccine_type' => 'YELLOW_FEVER',
                    'vaccination_date' => '2025-12-01',
                    'certificate_number' => 'ETH-YF-2025-001',
                    'valid' => true
                ]
            ],
            'expected_issues' => [],
            'scenario' => 'happy_path'
        ];

        // Persona 2: Diplomate kenyan
        $this->personas['kenyan_diplomat'] = [
            'name' => 'James Ochieng',
            'description' => 'Diplomate kenyan - Passeport diplomatique, note verbale requise',
            'expected_workflow' => 'DIPLOMATIC',
            'documents' => [
                'passport' => [
                    'fields' => [
                        'surname' => ['value' => 'OCHIENG'],
                        'given_names' => ['value' => 'JAMES KIPCHOGE'],
                        'nationality' => ['value' => 'KEN'],
                        'document_number' => ['value' => 'D0012345'],
                        'date_of_birth' => ['value' => '1978-07-21'],
                        'date_of_expiry' => ['value' => '2027-12-31'],
                        'sex' => ['value' => 'M'],
                    ],
                    'passport_type_detection' => [
                        'type' => 'DIPLOMATIQUE',
                        'workflow' => 'DIPLOMATIC'
                    ],
                    'document_classification' => [
                        'category' => 'PASSPORT',
                        'subcategory' => 'DIPLOMATIC'
                    ],
                    'visa_eligibility' => ['is_valid' => true]
                ],
                'verbal_note' => [
                    'sender' => [
                        'ministry' => 'Ministry of Foreign Affairs - Kenya',
                        'reference' => 'MFA/CI/2026/001'
                    ],
                    'date' => '2026-01-05',
                    'purpose' => 'Official diplomatic mission'
                ],
                'ticket' => [
                    'flight_number' => 'KQ 100',
                    'departure_date' => '2026-02-01',
                    'departure_city' => 'Nairobi',
                    'arrival_city' => 'Abidjan',
                    'passenger_name' => 'OCHIENG JAMES KIPCHOGE',
                    'return_date' => '2026-02-10',
                    'return_flight_number' => 'KQ 101',
                    'is_round_trip' => true
                ]
            ],
            'expected_issues' => [],
            'scenario' => 'diplomatic_workflow'
        ];

        // Persona 3: Étudiante éthiopienne - Long séjour (BLOQUÉ car > 90 jours)
        $this->personas['ethiopian_student'] = [
            'name' => 'Meron Hailu',
            'description' => 'Étudiante éthiopienne - Formation 6 mois (REJETÉ: e-Visa limité à 90 jours max)',
            'expected_workflow' => 'BLOCKED',
            'documents' => [
                'passport' => [
                    'fields' => [
                        'surname' => ['value' => 'HAILU'],
                        'given_names' => ['value' => 'MERON TADESSE'],
                        'nationality' => ['value' => 'ETH'],
                        'document_number' => ['value' => 'EP7654321'],
                        'date_of_birth' => ['value' => '1998-11-30'],
                        'date_of_expiry' => ['value' => '2029-04-15'],
                        'sex' => ['value' => 'F'],
                    ],
                    'passport_type_detection' => [
                        'type' => 'ORDINAIRE',
                        'workflow' => 'STANDARD'
                    ],
                    'visa_eligibility' => ['is_valid' => true]
                ],
                'invitation' => [
                    'inviter' => [
                        'name' => 'Dr. Kouassi Yao',
                        'organization' => 'Université Félix Houphouët-Boigny',
                        'address' => 'Abidjan, Cocody'
                    ],
                    'invitee' => [
                        'name' => 'HAILU Meron Tadesse',
                        'passport_number' => 'EP7654321'
                    ],
                    'purpose' => 'Programme d\'échange universitaire - Master en Économie',
                    'dates' => [
                        'from' => '2026-02-01',
                        'to' => '2026-07-31'
                    ],
                    'accommodation_provided' => true,
                    'legalized' => true
                ],
                'ticket' => [
                    'flight_number' => 'ET 920',
                    'departure_date' => '2026-02-01',
                    'departure_city' => 'Addis Ababa',
                    'arrival_city' => 'Abidjan',
                    'passenger_name' => 'HAILU MERON TADESSE',
                    'return_date' => '2026-08-01',
                    'return_flight_number' => 'ET 921',
                    'is_round_trip' => true
                ],
                'vaccination' => [
                    'holder_name' => 'HAILU MERON TADESSE',
                    'vaccine_type' => 'YELLOW_FEVER',
                    'vaccination_date' => '2026-01-10',
                    'certificate_number' => 'ETH-YF-2026-100',
                    'valid' => true
                ]
            ],
            'expected_issues' => ['LONG_STAY'],
            'scenario' => 'long_stay_blocked'
        ];

        // Persona 4: Voyageur avec billet aller simple (WARNING)
        $this->personas['one_way_traveler'] = [
            'name' => 'Solomon Tesfaye',
            'description' => 'Voyageur éthiopien avec billet ALLER SIMPLE - Doit déclencher warning',
            'expected_workflow' => 'STANDARD',
            'documents' => [
                'passport' => [
                    'fields' => [
                        'surname' => ['value' => 'TESFAYE'],
                        'given_names' => ['value' => 'SOLOMON BEKELE'],
                        'nationality' => ['value' => 'ETH'],
                        'document_number' => ['value' => 'EP9999888'],
                        'date_of_birth' => ['value' => '1990-05-20'],
                        'date_of_expiry' => ['value' => '2028-09-10'],
                        'sex' => ['value' => 'M'],
                    ],
                    'passport_type_detection' => [
                        'type' => 'ORDINAIRE',
                        'workflow' => 'STANDARD'
                    ],
                    'visa_eligibility' => ['is_valid' => true]
                ],
                'ticket' => [
                    'flight_number' => 'ET 503',
                    'departure_date' => '2026-03-01',
                    'departure_city' => 'Addis Ababa',
                    'arrival_city' => 'Abidjan',
                    'passenger_name' => 'TESFAYE SOLOMON BEKELE',
                    'return_date' => null,
                    'return_flight_number' => null,
                    'is_round_trip' => false
                ],
                'hotel' => [
                    'guest_name' => 'TESFAYE SOLOMON BEKELE',
                    'hotel_name' => 'Radisson Blu Abidjan',
                    'hotel_city' => 'Abidjan',
                    'check_in_date' => '2026-03-01',
                    'check_out_date' => '2026-03-05',
                    'confirmation_number' => 'RAD456123',
                    'payment_status' => 'CONFIRMED'
                ],
                'vaccination' => [
                    'holder_name' => 'TESFAYE SOLOMON BEKELE',
                    'vaccine_type' => 'YELLOW_FEVER',
                    'vaccination_date' => '2026-02-15',
                    'certificate_number' => 'ETH-YF-2026-200',
                    'valid' => true
                ]
            ],
            'expected_issues' => ['RETURN_FLIGHT_MISSING'],
            'scenario' => 'one_way_ticket_warning'
        ];

        // Persona 5: Passeport expiré (ERROR)
        $this->personas['expired_passport'] = [
            'name' => 'Dawit Mengistu',
            'description' => 'Voyageur avec passeport EXPIRÉ - Doit être bloqué',
            'expected_workflow' => 'BLOCKED',
            'documents' => [
                'passport' => [
                    'fields' => [
                        'surname' => ['value' => 'MENGISTU'],
                        'given_names' => ['value' => 'DAWIT ALEMAYEHU'],
                        'nationality' => ['value' => 'ETH'],
                        'document_number' => ['value' => 'EP1111222'],
                        'date_of_birth' => ['value' => '1982-08-12'],
                        'date_of_expiry' => ['value' => '2025-06-01'], // EXPIRÉ!
                        'sex' => ['value' => 'M'],
                    ],
                    'passport_type_detection' => [
                        'type' => 'ORDINAIRE',
                        'workflow' => 'STANDARD'
                    ],
                    'visa_eligibility' => [
                        'is_valid' => false,
                        'reason_fr' => 'Passeport expiré',
                        'reason_en' => 'Expired passport'
                    ]
                ]
            ],
            'expected_issues' => ['PASSPORT_EXPIRY'],
            'scenario' => 'expired_passport_blocked'
        ];

        // Persona 6: Gap d'hébergement (WARNING)
        $this->personas['accommodation_gap'] = [
            'name' => 'Tigist Bekele',
            'description' => 'Voyageuse avec hébergement insuffisant - 1 nuit pour 14 jours de séjour',
            'expected_workflow' => 'STANDARD',
            'documents' => [
                'passport' => [
                    'fields' => [
                        'surname' => ['value' => 'BEKELE'],
                        'given_names' => ['value' => 'TIGIST ALEMITU'],
                        'nationality' => ['value' => 'ETH'],
                        'document_number' => ['value' => 'EP5555666'],
                        'date_of_birth' => ['value' => '1995-02-28'],
                        'date_of_expiry' => ['value' => '2030-01-15'],
                        'sex' => ['value' => 'F'],
                    ],
                    'passport_type_detection' => [
                        'type' => 'ORDINAIRE',
                        'workflow' => 'STANDARD'
                    ],
                    'visa_eligibility' => ['is_valid' => true]
                ],
                'ticket' => [
                    'flight_number' => 'ET 510',
                    'departure_date' => '2026-04-01',
                    'departure_city' => 'Addis Ababa',
                    'arrival_city' => 'Abidjan',
                    'passenger_name' => 'BEKELE TIGIST ALEMITU',
                    'return_date' => '2026-04-15',
                    'return_flight_number' => 'ET 511',
                    'is_round_trip' => true
                ],
                'hotel' => [
                    'guest_name' => 'BEKELE TIGIST ALEMITU',
                    'hotel_name' => 'Novotel Abidjan',
                    'hotel_city' => 'Abidjan',
                    'check_in_date' => '2026-04-01',
                    'check_out_date' => '2026-04-02', // Seulement 1 nuit!
                    'confirmation_number' => 'NOV789012',
                    'payment_status' => 'CONFIRMED'
                ],
                'vaccination' => [
                    'holder_name' => 'BEKELE TIGIST ALEMITU',
                    'vaccine_type' => 'YELLOW_FEVER',
                    'vaccination_date' => '2026-03-20',
                    'certificate_number' => 'ETH-YF-2026-300',
                    'valid' => true
                ]
            ],
            'expected_issues' => ['ACCOMMODATION_GAP'],
            'scenario' => 'accommodation_gap_warning'
        ];

        // Persona 7: Nationalité hors juridiction (REDIRECT)
        $this->personas['non_jurisdiction'] = [
            'name' => 'Jean-Pierre Mbeki',
            'description' => 'Voyageur congolais (RDC) - Hors juridiction Addis-Abeba',
            'expected_workflow' => 'REDIRECT',
            'documents' => [
                'passport' => [
                    'fields' => [
                        'surname' => ['value' => 'MBEKI'],
                        'given_names' => ['value' => 'JEAN-PIERRE KABEYA'],
                        'nationality' => ['value' => 'COD'], // Congo RDC
                        'document_number' => ['value' => 'OB1234567'],
                        'date_of_birth' => ['value' => '1988-04-10'],
                        'date_of_expiry' => ['value' => '2028-11-30'],
                        'sex' => ['value' => 'M'],
                    ],
                    'passport_type_detection' => [
                        'type' => 'ORDINAIRE',
                        'workflow' => 'REDIRECT'
                    ],
                    'visa_eligibility' => [
                        'is_valid' => false,
                        'reason_fr' => 'La RDC n\'est pas dans la juridiction de l\'ambassade d\'Addis-Abeba',
                        'reason_en' => 'DRC is not within the jurisdiction of the Addis Ababa embassy'
                    ]
                ]
            ],
            'expected_issues' => ['NON_JURISDICTION'],
            'scenario' => 'non_jurisdiction_redirect'
        ];

        // Persona 8: Touriste sans invitation (hôtel seulement)
        $this->personas['tourist_hotel_only'] = [
            'name' => 'Fatuma Ahmed',
            'description' => 'Touriste djiboutienne - Pas d\'invitation, juste hôtel',
            'expected_workflow' => 'STANDARD',
            'documents' => [
                'passport' => [
                    'fields' => [
                        'surname' => ['value' => 'AHMED'],
                        'given_names' => ['value' => 'FATUMA HASSAN'],
                        'nationality' => ['value' => 'DJI'], // Djibouti - dans juridiction
                        'document_number' => ['value' => 'B1234567'],
                        'date_of_birth' => ['value' => '1990-06-15'],
                        'date_of_expiry' => ['value' => '2029-03-20'],
                        'sex' => ['value' => 'F'],
                    ],
                    'passport_type_detection' => [
                        'type' => 'ORDINAIRE',
                        'workflow' => 'STANDARD'
                    ],
                    'visa_eligibility' => ['is_valid' => true]
                ],
                'ticket' => [
                    'flight_number' => 'ET 800',
                    'departure_date' => '2026-06-01',
                    'departure_city' => 'Djibouti',
                    'arrival_city' => 'Abidjan',
                    'passenger_name' => 'AHMED FATUMA HASSAN',
                    'return_date' => '2026-06-10',
                    'return_flight_number' => 'ET 801',
                    'is_round_trip' => true
                ],
                'hotel' => [
                    'guest_name' => 'AHMED FATUMA HASSAN',
                    'hotel_name' => 'Hôtel Ivoire Sofitel',
                    'hotel_city' => 'Abidjan',
                    'check_in_date' => '2026-06-01',
                    'check_out_date' => '2026-06-10',
                    'confirmation_number' => 'IVO123456',
                    'payment_status' => 'CONFIRMED',
                    'number_of_guests' => 1
                ],
                'vaccination' => [
                    'holder_name' => 'AHMED FATUMA HASSAN',
                    'vaccine_type' => 'YELLOW_FEVER',
                    'vaccination_date' => '2026-05-15',
                    'certificate_number' => 'DJI-YF-2026-001',
                    'valid' => true
                ]
            ],
            'expected_issues' => [],
            'scenario' => 'tourist_no_invitation'
        ];

        // Persona 9: Vaccination expirée (> 10 ans)
        $this->personas['expired_vaccination'] = [
            'name' => 'Haile Selassie',
            'description' => 'Voyageur avec vaccination fièvre jaune EXPIRÉE (> 10 ans)',
            'expected_workflow' => 'STANDARD',
            'documents' => [
                'passport' => [
                    'fields' => [
                        'surname' => ['value' => 'SELASSIE'],
                        'given_names' => ['value' => 'HAILE MARIAM'],
                        'nationality' => ['value' => 'ETH'],
                        'document_number' => ['value' => 'EP8888777'],
                        'date_of_birth' => ['value' => '1975-11-02'],
                        'date_of_expiry' => ['value' => '2028-05-15'],
                        'sex' => ['value' => 'M'],
                    ],
                    'passport_type_detection' => [
                        'type' => 'ORDINAIRE',
                        'workflow' => 'STANDARD'
                    ],
                    'visa_eligibility' => ['is_valid' => true]
                ],
                'ticket' => [
                    'flight_number' => 'ET 550',
                    'departure_date' => '2026-07-01',
                    'departure_city' => 'Addis Ababa',
                    'arrival_city' => 'Abidjan',
                    'passenger_name' => 'SELASSIE HAILE MARIAM',
                    'return_date' => '2026-07-15',
                    'return_flight_number' => 'ET 551',
                    'is_round_trip' => true
                ],
                'hotel' => [
                    'guest_name' => 'SELASSIE HAILE MARIAM',
                    'hotel_name' => 'Azalaï Hôtel',
                    'hotel_city' => 'Abidjan',
                    'check_in_date' => '2026-07-01',
                    'check_out_date' => '2026-07-15',
                    'confirmation_number' => 'AZA789012',
                    'payment_status' => 'CONFIRMED'
                ],
                'vaccination' => [
                    'holder_name' => 'SELASSIE HAILE MARIAM',
                    'vaccine_type' => 'YELLOW_FEVER',
                    'vaccination_date' => '2014-03-10', // Plus de 10 ans!
                    'certificate_number' => 'ETH-YF-2014-500',
                    'valid' => false // Marqué comme invalide
                ]
            ],
            'expected_issues' => ['VACCINATION_EXPIRED'],
            'scenario' => 'expired_vaccination'
        ];

        // Persona 10: Passeport de service
        $this->personas['service_passport'] = [
            'name' => 'Amina Wako',
            'description' => 'Fonctionnaire avec passeport de SERVICE - workflow spécial',
            'expected_workflow' => 'SERVICE',
            'documents' => [
                'passport' => [
                    'fields' => [
                        'surname' => ['value' => 'WAKO'],
                        'given_names' => ['value' => 'AMINA DIRE'],
                        'nationality' => ['value' => 'ETH'],
                        'document_number' => ['value' => 'ES0012345'],
                        'date_of_birth' => ['value' => '1985-04-22'],
                        'date_of_expiry' => ['value' => '2027-08-30'],
                        'sex' => ['value' => 'F'],
                    ],
                    'passport_type_detection' => [
                        'type' => 'SERVICE',
                        'workflow' => 'SERVICE'
                    ],
                    'document_classification' => [
                        'category' => 'PASSPORT',
                        'subcategory' => 'SERVICE'
                    ],
                    'visa_eligibility' => ['is_valid' => true]
                ],
                'verbal_note' => [
                    'sender' => [
                        'ministry' => 'Ministry of Finance - Ethiopia',
                        'reference' => 'MOF/2026/0042'
                    ],
                    'date' => '2026-01-10',
                    'purpose' => 'Official government mission'
                ],
                'ticket' => [
                    'flight_number' => 'ET 560',
                    'departure_date' => '2026-02-15',
                    'departure_city' => 'Addis Ababa',
                    'arrival_city' => 'Abidjan',
                    'passenger_name' => 'WAKO AMINA DIRE',
                    'return_date' => '2026-02-22',
                    'return_flight_number' => 'ET 561',
                    'is_round_trip' => true
                ]
            ],
            'expected_issues' => [],
            'scenario' => 'service_passport_workflow'
        ];

        // Persona 11: Voyage urgent (dates très proches)
        $this->personas['urgent_travel'] = [
            'name' => 'Tesfaye Lemma',
            'description' => 'Voyage URGENT - Départ dans 2 jours, délai de traitement insuffisant',
            'expected_workflow' => 'STANDARD',
            'documents' => [
                'passport' => [
                    'fields' => [
                        'surname' => ['value' => 'LEMMA'],
                        'given_names' => ['value' => 'TESFAYE GIRMA'],
                        'nationality' => ['value' => 'ETH'],
                        'document_number' => ['value' => 'EP1112223'],
                        'date_of_birth' => ['value' => '1988-09-18'],
                        'date_of_expiry' => ['value' => '2030-02-28'],
                        'sex' => ['value' => 'M'],
                    ],
                    'passport_type_detection' => [
                        'type' => 'ORDINAIRE',
                        'workflow' => 'STANDARD'
                    ],
                    'visa_eligibility' => ['is_valid' => true]
                ],
                'ticket' => [
                    'flight_number' => 'ET 570',
                    'departure_date' => date('Y-m-d', strtotime('+2 days')), // Dans 2 jours!
                    'departure_city' => 'Addis Ababa',
                    'arrival_city' => 'Abidjan',
                    'passenger_name' => 'LEMMA TESFAYE GIRMA',
                    'return_date' => date('Y-m-d', strtotime('+9 days')),
                    'return_flight_number' => 'ET 571',
                    'is_round_trip' => true
                ],
                'hotel' => [
                    'guest_name' => 'LEMMA TESFAYE GIRMA',
                    'hotel_name' => 'Radisson Blu Abidjan',
                    'hotel_city' => 'Abidjan',
                    'check_in_date' => date('Y-m-d', strtotime('+2 days')),
                    'check_out_date' => date('Y-m-d', strtotime('+9 days')),
                    'confirmation_number' => 'RAD999888',
                    'payment_status' => 'CONFIRMED'
                ],
                'vaccination' => [
                    'holder_name' => 'LEMMA TESFAYE GIRMA',
                    'vaccine_type' => 'YELLOW_FEVER',
                    'vaccination_date' => '2025-06-01',
                    'certificate_number' => 'ETH-YF-2025-999',
                    'valid' => true
                ]
            ],
            'expected_issues' => ['URGENT_TRAVEL'],
            'scenario' => 'urgent_travel_warning'
        ];

        // Persona 12: Mineur voyageant seul
        $this->personas['minor_traveling'] = [
            'name' => 'Samuel Tadesse',
            'description' => 'Mineur de 16 ans voyageant seul - Documents parentaux requis',
            'expected_workflow' => 'STANDARD',
            'documents' => [
                'passport' => [
                    'fields' => [
                        'surname' => ['value' => 'TADESSE'],
                        'given_names' => ['value' => 'SAMUEL BEREKET'],
                        'nationality' => ['value' => 'ETH'],
                        'document_number' => ['value' => 'EP4445556'],
                        'date_of_birth' => ['value' => '2010-03-25'], // 16 ans
                        'date_of_expiry' => ['value' => '2028-10-15'],
                        'sex' => ['value' => 'M'],
                    ],
                    'passport_type_detection' => [
                        'type' => 'ORDINAIRE',
                        'workflow' => 'STANDARD'
                    ],
                    'visa_eligibility' => ['is_valid' => true]
                ],
                'invitation' => [
                    'inviter' => [
                        'name' => 'Dr. Kouame Jean',
                        'organization' => 'Youth Exchange Program CI'
                    ],
                    'invitee' => [
                        'name' => 'TADESSE Samuel Bereket',
                        'passport_number' => 'EP4445556'
                    ],
                    'purpose' => 'Programme d\'échange jeunesse',
                    'dates' => [
                        'from' => '2026-08-01',
                        'to' => '2026-08-15'
                    ],
                    'accommodation_provided' => true
                ],
                'ticket' => [
                    'flight_number' => 'ET 580',
                    'departure_date' => '2026-08-01',
                    'departure_city' => 'Addis Ababa',
                    'arrival_city' => 'Abidjan',
                    'passenger_name' => 'TADESSE SAMUEL BEREKET',
                    'return_date' => '2026-08-15',
                    'return_flight_number' => 'ET 581',
                    'is_round_trip' => true
                ],
                'vaccination' => [
                    'holder_name' => 'TADESSE SAMUEL BEREKET',
                    'vaccine_type' => 'YELLOW_FEVER',
                    'vaccination_date' => '2026-07-01',
                    'certificate_number' => 'ETH-YF-2026-JR01',
                    'valid' => true
                ]
            ],
            'expected_issues' => ['MINOR_TRAVELING'],
            'scenario' => 'minor_requires_parental_consent'
        ];

        // Persona 13: Résident éthiopien au Kenya
        $this->personas['resident_abroad'] = [
            'name' => 'Bekele Worku',
            'description' => 'Éthiopien résident au Kenya - Carte de séjour valide',
            'expected_workflow' => 'STANDARD',
            'documents' => [
                'passport' => [
                    'fields' => [
                        'surname' => ['value' => 'WORKU'],
                        'given_names' => ['value' => 'BEKELE TESSEMA'],
                        'nationality' => ['value' => 'ETH'],
                        'document_number' => ['value' => 'EP7778889'],
                        'date_of_birth' => ['value' => '1980-12-05'],
                        'date_of_expiry' => ['value' => '2029-06-30'],
                        'sex' => ['value' => 'M'],
                    ],
                    'passport_type_detection' => [
                        'type' => 'ORDINAIRE',
                        'workflow' => 'STANDARD'
                    ],
                    'visa_eligibility' => ['is_valid' => true]
                ],
                'residence_card' => [
                    'document' => [
                        'card_number' => 'KEN-RES-2024-12345',
                        'date_of_issue' => '2024-01-15',
                        'date_of_expiry' => '2027-01-14'
                    ],
                    'residence' => [
                        'country' => 'Kenya',
                        'city' => 'Nairobi',
                        'status' => 'Permanent Resident'
                    ],
                    'holder' => [
                        'name' => 'WORKU BEKELE TESSEMA'
                    ]
                ],
                'ticket' => [
                    'flight_number' => 'KQ 200',
                    'departure_date' => '2026-09-10',
                    'departure_city' => 'Nairobi', // Départ du Kenya, pas d'Éthiopie
                    'arrival_city' => 'Abidjan',
                    'passenger_name' => 'WORKU BEKELE TESSEMA',
                    'return_date' => '2026-09-20',
                    'return_flight_number' => 'KQ 201',
                    'is_round_trip' => true
                ],
                'hotel' => [
                    'guest_name' => 'WORKU BEKELE TESSEMA',
                    'hotel_name' => 'Golden Tulip Abidjan',
                    'hotel_city' => 'Abidjan',
                    'check_in_date' => '2026-09-10',
                    'check_out_date' => '2026-09-20',
                    'confirmation_number' => 'GT456789',
                    'payment_status' => 'CONFIRMED'
                ],
                'vaccination' => [
                    'holder_name' => 'WORKU BEKELE TESSEMA',
                    'vaccine_type' => 'YELLOW_FEVER',
                    'vaccination_date' => '2025-09-01',
                    'certificate_number' => 'KEN-YF-2025-100',
                    'valid' => true
                ]
            ],
            'expected_issues' => [],
            'scenario' => 'ethiopian_resident_kenya'
        ];

        // Persona 14: Incohérence de noms entre documents
        $this->personas['name_mismatch'] = [
            'name' => 'Yohannes Gebre',
            'description' => 'Noms différents entre passeport et billet - Doit déclencher alerte',
            'expected_workflow' => 'STANDARD',
            'documents' => [
                'passport' => [
                    'fields' => [
                        'surname' => ['value' => 'GEBRE'],
                        'given_names' => ['value' => 'YOHANNES TADESSE'],
                        'nationality' => ['value' => 'ETH'],
                        'document_number' => ['value' => 'EP3333444'],
                        'date_of_birth' => ['value' => '1992-09-05'],
                        'date_of_expiry' => ['value' => '2029-07-20'],
                        'sex' => ['value' => 'M'],
                    ],
                    'passport_type_detection' => [
                        'type' => 'ORDINAIRE',
                        'workflow' => 'STANDARD'
                    ],
                    'visa_eligibility' => ['is_valid' => true]
                ],
                'ticket' => [
                    'flight_number' => 'ET 520',
                    'departure_date' => '2026-05-10',
                    'departure_city' => 'Addis Ababa',
                    'arrival_city' => 'Abidjan',
                    'passenger_name' => 'JOHANNES GEBRE', // Prénom différent!
                    'return_date' => '2026-05-17',
                    'return_flight_number' => 'ET 521',
                    'is_round_trip' => true
                ],
                'hotel' => [
                    'guest_name' => 'YOHANNES GEBRE',
                    'hotel_name' => 'Pullman Abidjan',
                    'hotel_city' => 'Abidjan',
                    'check_in_date' => '2026-05-10',
                    'check_out_date' => '2026-05-17',
                    'confirmation_number' => 'PUL456789',
                    'payment_status' => 'CONFIRMED'
                ],
                'vaccination' => [
                    'holder_name' => 'YOHANNES GEBRE TADESSE',
                    'vaccine_type' => 'YELLOW_FEVER',
                    'vaccination_date' => '2026-04-25',
                    'certificate_number' => 'ETH-YF-2026-400',
                    'valid' => true
                ]
            ],
            'expected_issues' => ['NAME_MISMATCH'],
            'scenario' => 'name_inconsistency_warning'
        ];

        // ================================================================
        // NOUVELLES PERSONAS
        // ================================================================

        // Persona 15: Étudiante valide - séjour court (< 90 jours)
        $this->personas['valid_student_short'] = [
            'name' => 'Hanna Gebremedhin',
            'description' => 'Étudiante éthiopienne - Stage 2 mois (60 jours) - VALIDE',
            'expected_workflow' => 'STANDARD',
            'documents' => [
                'passport' => [
                    'fields' => [
                        'surname' => ['value' => 'GEBREMEDHIN'],
                        'given_names' => ['value' => 'HANNA BIRUK'],
                        'nationality' => ['value' => 'ETH'],
                        'document_number' => ['value' => 'EP5556667'],
                        'date_of_birth' => ['value' => '2000-08-12'],
                        'date_of_expiry' => ['value' => '2030-01-15'],
                        'sex' => ['value' => 'F'],
                    ],
                    'passport_type_detection' => [
                        'type' => 'ORDINAIRE',
                        'workflow' => 'STANDARD'
                    ],
                    'visa_eligibility' => ['is_valid' => true]
                ],
                'invitation' => [
                    'inviter' => [
                        'name' => 'Prof. Adjoua Koné',
                        'organization' => 'Institut National Polytechnique',
                        'address' => 'Yamoussoukro, Côte d\'Ivoire'
                    ],
                    'invitee' => [
                        'name' => 'GEBREMEDHIN Hanna Biruk',
                        'passport_number' => 'EP5556667'
                    ],
                    'purpose' => 'Stage d\'ingénierie - Projet hydraulique',
                    'dates' => [
                        'from' => '2026-03-01',
                        'to' => '2026-04-30' // 60 jours - OK
                    ],
                    'accommodation_provided' => true,
                    'legalized' => true
                ],
                'ticket' => [
                    'flight_number' => 'ET 600',
                    'departure_date' => '2026-03-01',
                    'departure_city' => 'Addis Ababa',
                    'arrival_city' => 'Abidjan',
                    'passenger_name' => 'GEBREMEDHIN HANNA BIRUK',
                    'return_date' => '2026-04-30',
                    'return_flight_number' => 'ET 601',
                    'is_round_trip' => true
                ],
                'vaccination' => [
                    'holder_name' => 'GEBREMEDHIN HANNA BIRUK',
                    'vaccine_type' => 'YELLOW_FEVER',
                    'vaccination_date' => '2026-02-15',
                    'certificate_number' => 'ETH-YF-2026-200',
                    'valid' => true
                ]
            ],
            'expected_issues' => [],
            'scenario' => 'valid_short_internship'
        ];

        // Persona 16: Participant à une conférence
        $this->personas['conference_attendee'] = [
            'name' => 'Dr. Wondimu Assefa',
            'description' => 'Médecin participant à un congrès médical - 5 jours',
            'expected_workflow' => 'STANDARD',
            'documents' => [
                'passport' => [
                    'fields' => [
                        'surname' => ['value' => 'ASSEFA'],
                        'given_names' => ['value' => 'WONDIMU TEKLE'],
                        'nationality' => ['value' => 'ETH'],
                        'document_number' => ['value' => 'EP9998887'],
                        'date_of_birth' => ['value' => '1975-05-18'],
                        'date_of_expiry' => ['value' => '2028-11-30'],
                        'sex' => ['value' => 'M'],
                    ],
                    'passport_type_detection' => [
                        'type' => 'ORDINAIRE',
                        'workflow' => 'STANDARD'
                    ],
                    'visa_eligibility' => ['is_valid' => true]
                ],
                'invitation' => [
                    'inviter' => [
                        'name' => 'Société Ivoirienne de Cardiologie',
                        'organization' => 'CHU Cocody',
                        'address' => 'Abidjan, Cocody'
                    ],
                    'invitee' => [
                        'name' => 'Dr. ASSEFA Wondimu Tekle'
                    ],
                    'purpose' => 'Congrès International de Cardiologie 2026',
                    'dates' => [
                        'from' => '2026-04-15',
                        'to' => '2026-04-20'
                    ],
                    'accommodation_provided' => false,
                    'legalized' => true
                ],
                'ticket' => [
                    'flight_number' => 'ET 610',
                    'departure_date' => '2026-04-14',
                    'departure_city' => 'Addis Ababa',
                    'arrival_city' => 'Abidjan',
                    'passenger_name' => 'ASSEFA WONDIMU TEKLE',
                    'return_date' => '2026-04-21',
                    'return_flight_number' => 'ET 611',
                    'is_round_trip' => true
                ],
                'hotel' => [
                    'guest_name' => 'ASSEFA WONDIMU TEKLE',
                    'hotel_name' => 'Sofitel Abidjan Hôtel Ivoire',
                    'hotel_city' => 'Abidjan',
                    'check_in_date' => '2026-04-14',
                    'check_out_date' => '2026-04-21',
                    'confirmation_number' => 'SOF101112',
                    'payment_status' => 'CONFIRMED'
                ],
                'vaccination' => [
                    'holder_name' => 'DR ASSEFA WONDIMU TEKLE',
                    'vaccine_type' => 'YELLOW_FEVER',
                    'vaccination_date' => '2023-12-01',
                    'certificate_number' => 'ETH-YF-2023-DOC',
                    'valid' => true
                ]
            ],
            'expected_issues' => [],
            'scenario' => 'conference_attendee'
        ];

        // Persona 17: Voyageur médical
        $this->personas['medical_traveler'] = [
            'name' => 'Meseret Alemu',
            'description' => 'Patient voyageant pour traitement médical spécialisé',
            'expected_workflow' => 'STANDARD',
            'documents' => [
                'passport' => [
                    'fields' => [
                        'surname' => ['value' => 'ALEMU'],
                        'given_names' => ['value' => 'MESERET WORKNEH'],
                        'nationality' => ['value' => 'ETH'],
                        'document_number' => ['value' => 'EP2223334'],
                        'date_of_birth' => ['value' => '1968-02-28'],
                        'date_of_expiry' => ['value' => '2027-08-15'],
                        'sex' => ['value' => 'F'],
                    ],
                    'passport_type_detection' => [
                        'type' => 'ORDINAIRE',
                        'workflow' => 'STANDARD'
                    ],
                    'visa_eligibility' => ['is_valid' => true]
                ],
                'invitation' => [
                    'inviter' => [
                        'name' => 'Prof. Kouadio Marcel',
                        'organization' => 'Clinique Internationale St-Jean',
                        'address' => 'Abidjan, Marcory'
                    ],
                    'invitee' => [
                        'name' => 'ALEMU Meseret Workneh'
                    ],
                    'purpose' => 'Traitement ophtalmologique spécialisé',
                    'dates' => [
                        'from' => '2026-05-01',
                        'to' => '2026-05-21'
                    ],
                    'accommodation_provided' => false,
                    'legalized' => true
                ],
                'ticket' => [
                    'flight_number' => 'ET 620',
                    'departure_date' => '2026-04-30',
                    'departure_city' => 'Addis Ababa',
                    'arrival_city' => 'Abidjan',
                    'passenger_name' => 'ALEMU MESERET WORKNEH',
                    'return_date' => '2026-05-22',
                    'return_flight_number' => 'ET 621',
                    'is_round_trip' => true
                ],
                'hotel' => [
                    'guest_name' => 'ALEMU MESERET WORKNEH',
                    'hotel_name' => 'Hôtel Président',
                    'hotel_city' => 'Abidjan',
                    'check_in_date' => '2026-04-30',
                    'check_out_date' => '2026-05-22',
                    'confirmation_number' => 'PRES77788',
                    'payment_status' => 'CONFIRMED'
                ],
                'vaccination' => [
                    'holder_name' => 'ALEMU MESERET WORKNEH',
                    'vaccine_type' => 'YELLOW_FEVER',
                    'vaccination_date' => '2026-04-01',
                    'certificate_number' => 'ETH-YF-2026-MED',
                    'valid' => true
                ]
            ],
            'expected_issues' => [],
            'scenario' => 'medical_tourism'
        ];

        // Persona 18: Multiples incohérences
        $this->personas['multiple_issues'] = [
            'name' => 'Tadesse Beyene',
            'description' => 'Dossier avec MULTIPLES problèmes: aller simple + gap hébergement + voyage urgent',
            'expected_workflow' => 'STANDARD',
            'documents' => [
                'passport' => [
                    'fields' => [
                        'surname' => ['value' => 'BEYENE'],
                        'given_names' => ['value' => 'TADESSE MULUGETA'],
                        'nationality' => ['value' => 'ETH'],
                        'document_number' => ['value' => 'EP6667778'],
                        'date_of_birth' => ['value' => '1987-07-14'],
                        'date_of_expiry' => ['value' => '2028-03-20'],
                        'sex' => ['value' => 'M'],
                    ],
                    'passport_type_detection' => [
                        'type' => 'ORDINAIRE',
                        'workflow' => 'STANDARD'
                    ],
                    'visa_eligibility' => ['is_valid' => true]
                ],
                'ticket' => [
                    'flight_number' => 'ET 630',
                    'departure_date' => date('Y-m-d', strtotime('+3 days')), // Urgent!
                    'departure_city' => 'Addis Ababa',
                    'arrival_city' => 'Abidjan',
                    'passenger_name' => 'BEYENE TADESSE MULUGETA',
                    'return_date' => null, // Aller simple!
                    'return_flight_number' => null,
                    'is_round_trip' => false
                ],
                'hotel' => [
                    'guest_name' => 'BEYENE TADESSE MULUGETA',
                    'hotel_name' => 'Novotel Abidjan',
                    'hotel_city' => 'Abidjan',
                    'check_in_date' => date('Y-m-d', strtotime('+3 days')),
                    'check_out_date' => date('Y-m-d', strtotime('+5 days')), // 2 nuits seulement
                    'confirmation_number' => 'NOV333444',
                    'payment_status' => 'CONFIRMED'
                ],
                'vaccination' => [
                    'holder_name' => 'BEYENE TADESSE MULUGETA',
                    'vaccine_type' => 'YELLOW_FEVER',
                    'vaccination_date' => '2025-01-15',
                    'certificate_number' => 'ETH-YF-2025-500',
                    'valid' => true
                ]
            ],
            'expected_issues' => ['RETURN_FLIGHT_MISSING', 'URGENT_TRAVEL'],
            'scenario' => 'multiple_red_flags'
        ];

        // Persona 19: Famille voyageant ensemble
        $this->personas['family_travel'] = [
            'name' => 'Famille Desta',
            'description' => 'Couple avec enfant - Tous les documents cohérents',
            'expected_workflow' => 'STANDARD',
            'documents' => [
                'passport' => [
                    'fields' => [
                        'surname' => ['value' => 'DESTA'],
                        'given_names' => ['value' => 'ABEBE MIKAEL'],
                        'nationality' => ['value' => 'ETH'],
                        'document_number' => ['value' => 'EP8889990'],
                        'date_of_birth' => ['value' => '1982-11-03'],
                        'date_of_expiry' => ['value' => '2029-05-10'],
                        'sex' => ['value' => 'M'],
                    ],
                    'passport_type_detection' => [
                        'type' => 'ORDINAIRE',
                        'workflow' => 'STANDARD'
                    ],
                    'visa_eligibility' => ['is_valid' => true]
                ],
                'ticket' => [
                    'flight_number' => 'ET 640',
                    'departure_date' => '2026-07-20',
                    'departure_city' => 'Addis Ababa',
                    'arrival_city' => 'Abidjan',
                    'passenger_name' => 'DESTA ABEBE MIKAEL',
                    'return_date' => '2026-08-05',
                    'return_flight_number' => 'ET 641',
                    'is_round_trip' => true,
                    'additional_passengers' => [
                        'DESTA TIGIST BEYENE',
                        'DESTA LIYA ABEBE'
                    ]
                ],
                'hotel' => [
                    'guest_name' => 'DESTA ABEBE MIKAEL',
                    'hotel_name' => 'Mövenpick Abidjan',
                    'hotel_city' => 'Abidjan',
                    'check_in_date' => '2026-07-20',
                    'check_out_date' => '2026-08-05',
                    'confirmation_number' => 'MOV555666',
                    'payment_status' => 'CONFIRMED',
                    'number_of_guests' => 3
                ],
                'vaccination' => [
                    'holder_name' => 'DESTA ABEBE MIKAEL',
                    'vaccine_type' => 'YELLOW_FEVER',
                    'vaccination_date' => '2026-06-01',
                    'certificate_number' => 'ETH-YF-2026-FAM1',
                    'valid' => true
                ]
            ],
            'expected_issues' => [],
            'scenario' => 'family_vacation'
        ];

        // Persona 20: Sans vaccination (doit être bloqué)
        $this->personas['no_vaccination'] = [
            'name' => 'Girma Tefera',
            'description' => 'Voyageur SANS certificat de vaccination - BLOQUÉ',
            'expected_workflow' => 'STANDARD',
            'documents' => [
                'passport' => [
                    'fields' => [
                        'surname' => ['value' => 'TEFERA'],
                        'given_names' => ['value' => 'GIRMA SOLOMON'],
                        'nationality' => ['value' => 'ETH'],
                        'document_number' => ['value' => 'EP1110009'],
                        'date_of_birth' => ['value' => '1995-03-22'],
                        'date_of_expiry' => ['value' => '2030-09-30'],
                        'sex' => ['value' => 'M'],
                    ],
                    'passport_type_detection' => [
                        'type' => 'ORDINAIRE',
                        'workflow' => 'STANDARD'
                    ],
                    'visa_eligibility' => ['is_valid' => true]
                ],
                'ticket' => [
                    'flight_number' => 'ET 650',
                    'departure_date' => '2026-09-15',
                    'departure_city' => 'Addis Ababa',
                    'arrival_city' => 'Abidjan',
                    'passenger_name' => 'TEFERA GIRMA SOLOMON',
                    'return_date' => '2026-09-22',
                    'return_flight_number' => 'ET 651',
                    'is_round_trip' => true
                ],
                'hotel' => [
                    'guest_name' => 'TEFERA GIRMA SOLOMON',
                    'hotel_name' => 'Ibis Abidjan Plateau',
                    'hotel_city' => 'Abidjan',
                    'check_in_date' => '2026-09-15',
                    'check_out_date' => '2026-09-22',
                    'confirmation_number' => 'IBIS777888',
                    'payment_status' => 'CONFIRMED'
                ]
                // PAS DE VACCINATION!
            ],
            'expected_issues' => ['VACCINATION_MISSING'],
            'scenario' => 'missing_vaccination_blocked'
        ];
    }

    /**
     * Exécute tous les tests de personas
     */
    public function runAllTests(): array
    {
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "🧪 PERSONA TEST RUNNER - Chatbot Visa CI\n";
        echo str_repeat("=", 70) . "\n\n";

        foreach ($this->personas as $id => $persona) {
            $this->results[$id] = $this->testPersona($id, $persona);
        }

        $this->printSummary();
        return $this->results;
    }

    /**
     * Teste une persona spécifique
     */
    public function testPersona(string $id, array $persona): array
    {
        echo "┌" . str_repeat("─", 68) . "┐\n";
        echo "│ 👤 " . str_pad($persona['name'], 63) . "│\n";
        echo "│    " . str_pad($persona['description'], 63) . "│\n";
        echo "└" . str_repeat("─", 68) . "┘\n";

        $result = [
            'persona_id' => $id,
            'name' => $persona['name'],
            'expected_workflow' => $persona['expected_workflow'],
            'expected_issues' => $persona['expected_issues'],
            'scenario' => $persona['scenario'],
            'tests' => [],
            'coherence_result' => null,
            'passed' => true,
            'suggestions' => []
        ];

        // 1. Sauvegarder les documents dans le cache
        echo "  📁 Création des fichiers cache...\n";
        $this->savePersonaDocuments($id, $persona['documents']);

        // 2. Tester le validateur de cohérence
        echo "  🔍 Test de cohérence cross-documents...\n";
        $coherenceResult = $this->testCoherence($id);
        $result['coherence_result'] = $coherenceResult;

        // 3. Vérifier les issues attendues
        $foundIssues = array_column($coherenceResult['issues'] ?? [], 'type');

        foreach ($persona['expected_issues'] as $expectedIssue) {
            $found = in_array($expectedIssue, $foundIssues);
            $result['tests'][$expectedIssue] = [
                'expected' => true,
                'found' => $found,
                'passed' => $found
            ];

            if ($found) {
                echo "    ✅ Issue '$expectedIssue' détectée comme attendu\n";
            } else {
                echo "    ❌ Issue '$expectedIssue' NON détectée (attendue)\n";
                $result['passed'] = false;
            }
        }

        // Vérifier les issues inattendues
        foreach ($foundIssues as $foundIssue) {
            if (!in_array($foundIssue, $persona['expected_issues'])) {
                $severity = $this->getIssueSeverity($coherenceResult['issues'], $foundIssue);
                if ($severity === 'error') {
                    echo "    ⚠️  Issue inattendue: '$foundIssue' (severity: $severity)\n";
                    $result['suggestions'][] = "Issue inattendue détectée: $foundIssue";
                } else {
                    echo "    ℹ️  Issue info: '$foundIssue'\n";
                }
            }
        }

        // 4. Analyser les améliorations potentielles
        $suggestions = $this->analyzePotentialImprovements($persona, $coherenceResult);
        $result['suggestions'] = array_merge($result['suggestions'], $suggestions);

        if (!empty($result['suggestions'])) {
            echo "  💡 Suggestions d'amélioration:\n";
            foreach ($result['suggestions'] as $suggestion) {
                echo "     → $suggestion\n";
            }
        }

        echo "\n";

        // Nettoyer les fichiers cache de test
        $this->cleanupPersonaCache($id);

        return $result;
    }

    /**
     * Sauvegarde les documents d'une persona dans le cache
     */
    private function savePersonaDocuments(string $personaId, array $documents): void
    {
        foreach ($documents as $docType => $data) {
            $filename = "{$docType}_test_{$personaId}.json";
            $filepath = $this->cacheDir . '/' . $filename;

            // Ajouter les métadonnées
            $data['_metadata'] = [
                'test_persona' => $personaId,
                'document_type' => $docType,
                'timestamp' => date('c')
            ];

            file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * Teste la cohérence via l'API
     */
    private function testCoherence(string $personaId): array
    {
        // Charger le validateur
        require_once __DIR__ . '/../../php/services/DocumentCoherenceValidator.php';

        $validator = new \VisaChatbot\Services\DocumentCoherenceValidator();

        // Charger les documents de la persona depuis le cache
        $documents = [];
        $files = glob($this->cacheDir . "/*_test_{$personaId}.json");

        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            $docType = $data['_metadata']['document_type'] ?? basename($file, '.json');
            $documents[$docType] = $data;
        }

        return $validator->validateDossier($documents);
    }

    /**
     * Obtient la sévérité d'une issue
     */
    private function getIssueSeverity(array $issues, string $type): string
    {
        foreach ($issues as $issue) {
            if ($issue['type'] === $type) {
                return $issue['severity'] ?? 'info';
            }
        }
        return 'info';
    }

    /**
     * Analyse les améliorations potentielles
     */
    private function analyzePotentialImprovements(array $persona, array $coherenceResult): array
    {
        $suggestions = [];

        // Vérifier si le workflow est correctement détecté
        if (isset($persona['documents']['passport']['passport_type_detection'])) {
            $detectedWorkflow = $persona['documents']['passport']['passport_type_detection']['workflow'] ?? 'UNKNOWN';
            if ($detectedWorkflow !== $persona['expected_workflow'] && $persona['expected_workflow'] !== 'BLOCKED') {
                $suggestions[] = "Workflow détecté ($detectedWorkflow) différent de l'attendu ({$persona['expected_workflow']})";
            }
        }

        // Vérifier la complétude des documents
        $requiredDocs = ['passport', 'ticket', 'vaccination'];
        $providedDocs = array_keys($persona['documents']);
        $missingDocs = array_diff($requiredDocs, $providedDocs);

        if (!empty($missingDocs) && $persona['expected_workflow'] === 'STANDARD') {
            $suggestions[] = "Documents manquants pour test complet: " . implode(', ', $missingDocs);
        }

        // Analyser la durée du séjour
        if (isset($persona['documents']['ticket'])) {
            $ticket = $persona['documents']['ticket'];
            if ($ticket['departure_date'] && $ticket['return_date']) {
                $stayDays = (new \DateTime($ticket['return_date']))->diff(new \DateTime($ticket['departure_date']))->days;
                if ($stayDays > 90) {
                    $suggestions[] = "Séjour de $stayDays jours - vérifier si visa long séjour requis";
                }
            }
        }

        return $suggestions;
    }

    /**
     * Nettoie les fichiers cache de test
     */
    private function cleanupPersonaCache(string $personaId): void
    {
        $files = glob($this->cacheDir . "/*_test_{$personaId}.json");
        foreach ($files as $file) {
            unlink($file);
        }
    }

    /**
     * Affiche le résumé des tests
     */
    private function printSummary(): void
    {
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "📊 RÉSUMÉ DES TESTS\n";
        echo str_repeat("=", 70) . "\n\n";

        $passed = 0;
        $failed = 0;
        $allSuggestions = [];

        foreach ($this->results as $id => $result) {
            $status = $result['passed'] ? '✅' : '❌';
            echo "$status {$result['name']} ({$result['scenario']})\n";

            if ($result['passed']) {
                $passed++;
            } else {
                $failed++;
            }

            $allSuggestions = array_merge($allSuggestions, $result['suggestions']);
        }

        echo "\n" . str_repeat("-", 70) . "\n";
        echo "Total: " . count($this->results) . " personas testées\n";
        echo "✅ Réussis: $passed\n";
        echo "❌ Échoués: $failed\n";

        if (!empty($allSuggestions)) {
            echo "\n💡 SUGGESTIONS D'AMÉLIORATION GLOBALES:\n";
            $uniqueSuggestions = array_unique($allSuggestions);
            foreach ($uniqueSuggestions as $i => $suggestion) {
                echo "  " . ($i + 1) . ". $suggestion\n";
            }
        }

        echo "\n";
    }

    /**
     * Retourne les résultats
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Retourne les personas
     */
    public function getPersonas(): array
    {
        return $this->personas;
    }
}

// Exécution si lancé directement
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $runner = new PersonaTestRunner();
    $runner->runAllTests();
}
