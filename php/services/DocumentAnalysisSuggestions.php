<?php
/**
 * DocumentAnalysisSuggestions - Document Analysis Suggestions for Aya Persona
 *
 * Analyzes session data to generate proactive, helpful suggestions:
 * - Date mismatches between documents
 * - One-way ticket detection
 * - Vaccination expiration warnings
 * - Name discrepancy alerts
 * - Diplomat workflow detection
 * - Embassy closure warnings
 *
 * NOTE: Renamed from ProactiveSuggestions to avoid naming collision with
 * php/proactive-suggestions.php which has different interface (setContext/getSuggestions).
 *
 * @version 6.1.0
 * @author Visa Chatbot Team
 */

class DocumentAnalysisSuggestions {

    /**
     * Embassy non-working days (day of week: 0=Sunday, 6=Saturday)
     */
    private const EMBASSY_CLOSED_DAYS = [0, 6]; // Weekend

    /**
     * Embassy holidays (MM-DD format)
     */
    private const EMBASSY_HOLIDAYS = [
        '01-01', // Nouvel An
        '05-01', // Fête du Travail
        '08-07', // Fête de l'Indépendance CI
        '12-25', // Noël
        // Note: Fêtes islamiques variables selon calendrier lunaire
    ];

    /**
     * Vaccination validity in days (Yellow Fever = lifetime since 2016)
     */
    private const VACCINATION_VALIDITY = [
        'yellow_fever' => null, // Lifetime
        'covid19' => 270, // 9 months
        'polio' => 3650 // 10 years
    ];

    /**
     * Suggestion types and their priorities
     */
    private const SUGGESTION_PRIORITY = [
        'date_mismatch' => 1,           // Critical
        'name_discrepancy' => 1,        // Critical
        'duration_mismatch' => 1,       // Critical - NEW
        'accommodation_gap' => 2,       // Important - NEW
        'one_way_ticket' => 2,          // Important
        'vaccination_expiring' => 2,    // Important
        'invitation_ticket_mismatch' => 2, // Important - NEW
        'diplomat_detected' => 3,       // Informational
        'embassy_closed' => 3,          // Informational
        'document_quality' => 3,        // Informational
        'upcoming_document' => 4,       // Helpful
        'express_available' => 4        // Helpful
    ];

    /**
     * Analyze session and generate all applicable suggestions
     *
     * @param array $session Session data
     * @return array List of suggestions sorted by priority
     */
    public function analyzeAndSuggest(array $session): array {
        $suggestions = [];

        // Run all detection methods
        $detections = [
            $this->detectDateMismatch($session),
            $this->detectOneWayTicket($session),
            $this->detectVaccinationIssues($session),
            $this->detectNameDiscrepancy($session),
            $this->detectDiplomat($session),
            $this->detectEmbassyClosure($session),
            $this->detectUpcomingDocuments($session),
            $this->detectExpressEligibility($session),
            $this->detectDocumentQualityIssues($session),
            $this->detectInvitationTicketMismatch($session),
            $this->detectAccommodationGap($session)
        ];

        // Flatten and filter null results
        foreach ($detections as $detection) {
            if ($detection !== null) {
                if (isset($detection['type'])) {
                    // Single suggestion
                    $suggestions[] = $detection;
                } else {
                    // Multiple suggestions
                    $suggestions = array_merge($suggestions, $detection);
                }
            }
        }

        // Sort by priority
        usort($suggestions, function($a, $b) {
            $priorityA = self::SUGGESTION_PRIORITY[$a['type']] ?? 5;
            $priorityB = self::SUGGESTION_PRIORITY[$b['type']] ?? 5;
            return $priorityA - $priorityB;
        });

        return $suggestions;
    }

    /**
     * Detect date mismatches between documents
     *
     * @param array $session Session data
     * @return array|null Suggestion or null
     */
    public function detectDateMismatch(array $session): ?array {
        $ticket = $session['extracted_data']['ticket'] ?? null;
        $hotel = $session['extracted_data']['hotel'] ?? null;

        if (!$ticket || !$hotel) {
            return null;
        }

        $issues = [];

        // Check if hotel check-out is before flight return
        if (isset($ticket['return_date']) && isset($hotel['check_out_date'])) {
            try {
                $returnDate = new DateTime($ticket['return_date']);
                $checkoutDate = new DateTime($hotel['check_out_date']);

                if ($checkoutDate < $returnDate) {
                    $daysDiff = $returnDate->diff($checkoutDate)->days;
                    $issues[] = [
                        'type' => 'date_mismatch',
                        'subtype' => 'hotel_ends_before_flight',
                        'severity' => 'warning',
                        'data' => [
                            'hotel_checkout' => $hotel['check_out_date'],
                            'flight_return' => $ticket['return_date'],
                            'days_difference' => $daysDiff
                        ],
                        'message' => [
                            'fr' => "⚠️ Votre hôtel se termine le {$hotel['check_out_date']}, mais votre vol retour est le {$ticket['return_date']}. Où séjournerez-vous pendant ces {$daysDiff} jours ?",
                            'en' => "⚠️ Your hotel ends on {$hotel['check_out_date']}, but your return flight is on {$ticket['return_date']}. Where will you stay for those {$daysDiff} days?"
                        ]
                    ];
                }
            } catch (Exception $e) {
                // Ignore date parsing errors
            }
        }

        // Check if hotel check-in is after flight arrival
        if (isset($ticket['departure_date']) && isset($hotel['check_in_date'])) {
            try {
                $arrivalDate = new DateTime($ticket['departure_date']);
                $checkinDate = new DateTime($hotel['check_in_date']);

                if ($checkinDate > $arrivalDate) {
                    $daysDiff = $checkinDate->diff($arrivalDate)->days;
                    if ($daysDiff > 0) {
                        $issues[] = [
                            'type' => 'date_mismatch',
                            'subtype' => 'hotel_starts_after_arrival',
                            'severity' => 'info',
                            'data' => [
                                'flight_arrival' => $ticket['departure_date'],
                                'hotel_checkin' => $hotel['check_in_date'],
                                'days_difference' => $daysDiff
                            ],
                            'message' => [
                                'fr' => "ℹ️ Vous arrivez le {$ticket['departure_date']}, mais l'hôtel commence le {$hotel['check_in_date']}. Avez-vous un autre hébergement pour les premières nuits ?",
                                'en' => "ℹ️ You arrive on {$ticket['departure_date']}, but hotel starts on {$hotel['check_in_date']}. Do you have other accommodation for the first nights?"
                            ]
                        ];
                    }
                }
            } catch (Exception $e) {
                // Ignore date parsing errors
            }
        }

        return !empty($issues) ? $issues : null;
    }

    /**
     * Detect one-way ticket
     *
     * @param array $session Session data
     * @return array|null Suggestion or null
     */
    public function detectOneWayTicket(array $session): ?array {
        $ticket = $session['extracted_data']['ticket'] ?? null;

        if (!$ticket) {
            return null;
        }

        $isOneWay = ($ticket['is_round_trip'] ?? true) === false ||
                    !isset($ticket['return_date']) ||
                    empty($ticket['return_date']);

        if ($isOneWay) {
            return [
                'type' => 'one_way_ticket',
                'severity' => 'question',
                'requires_response' => true,
                'data' => [
                    'departure_date' => $ticket['departure_date'] ?? null,
                    'departure_city' => $ticket['departure_city'] ?? null,
                    'arrival_city' => $ticket['arrival_city'] ?? 'Abidjan'
                ],
                'message' => [
                    'fr' => "✈️ Je vois que vous avez un billet **aller simple**. Comment prévoyez-vous de repartir ?\n\n" .
                           "• 🎫 J'ai un billet retour séparé\n" .
                           "• 🚗 Je repartirai par voie terrestre/maritime\n" .
                           "• 📅 J'achèterai le retour plus tard",
                    'en' => "✈️ I see you have a **one-way ticket**. How do you plan to return?\n\n" .
                           "• 🎫 I have a separate return ticket\n" .
                           "• 🚗 I'll return by land/sea\n" .
                           "• 📅 I'll buy the return ticket later"
                ],
                'options' => [
                    ['key' => 'separate_ticket', 'label' => ['fr' => 'Billet séparé', 'en' => 'Separate ticket']],
                    ['key' => 'land_sea', 'label' => ['fr' => 'Voie terrestre/maritime', 'en' => 'Land/sea']],
                    ['key' => 'buy_later', 'label' => ['fr' => 'Acheter plus tard', 'en' => 'Buy later']]
                ]
            ];
        }

        return null;
    }

    /**
     * Detect vaccination issues
     *
     * @param array $session Session data
     * @return array|null Suggestion or null
     */
    public function detectVaccinationIssues(array $session): ?array {
        $vaccination = $session['extracted_data']['vaccination'] ?? null;
        $ticket = $session['extracted_data']['ticket'] ?? null;

        if (!$vaccination) {
            return null;
        }

        $issues = [];
        $travelDate = $ticket['departure_date'] ?? date('Y-m-d');

        // Check Yellow Fever 10-day rule
        if (isset($vaccination['vaccination_date'])) {
            try {
                $vaccineDate = new DateTime($vaccination['vaccination_date']);
                $travelDateTime = new DateTime($travelDate);
                $minValidDate = clone $vaccineDate;
                $minValidDate->modify('+10 days');

                if ($travelDateTime < $minValidDate) {
                    $daysRemaining = $travelDateTime->diff($minValidDate)->days;
                    $issues[] = [
                        'type' => 'vaccination_expiring',
                        'subtype' => 'too_recent',
                        'severity' => 'critical',
                        'data' => [
                            'vaccination_date' => $vaccination['vaccination_date'],
                            'valid_from' => $minValidDate->format('Y-m-d'),
                            'travel_date' => $travelDate,
                            'days_remaining' => $daysRemaining
                        ],
                        'message' => [
                            'fr' => "⚠️ **Attention**: Votre vaccination fièvre jaune du {$vaccination['vaccination_date']} ne sera valide qu'à partir du {$minValidDate->format('d/m/Y')} (10 jours après l'injection).\n\nVotre voyage est prévu le {$travelDate}.",
                            'en' => "⚠️ **Warning**: Your yellow fever vaccination from {$vaccination['vaccination_date']} will only be valid from {$minValidDate->format('M d, Y')} (10 days after injection).\n\nYour trip is scheduled for {$travelDate}."
                        ]
                    ];
                }

                // Check COVID-19 expiration (if applicable)
                if (($vaccination['vaccine_type'] ?? '') === 'covid19') {
                    $expiryDate = clone $vaccineDate;
                    $expiryDate->modify('+' . self::VACCINATION_VALIDITY['covid19'] . ' days');

                    if ($travelDateTime > $expiryDate) {
                        $issues[] = [
                            'type' => 'vaccination_expiring',
                            'subtype' => 'expired',
                            'severity' => 'warning',
                            'data' => [
                                'vaccine_type' => 'covid19',
                                'expiry_date' => $expiryDate->format('Y-m-d')
                            ],
                            'message' => [
                                'fr' => "ℹ️ Votre certificat COVID-19 a expiré le {$expiryDate->format('d/m/Y')}. Une nouvelle vaccination peut être recommandée.",
                                'en' => "ℹ️ Your COVID-19 certificate expired on {$expiryDate->format('M d, Y')}. A new vaccination may be recommended."
                            ]
                        ];
                    }
                }
            } catch (Exception $e) {
                // Ignore date parsing errors
            }
        }

        return !empty($issues) ? $issues : null;
    }

    /**
     * Detect name discrepancy between documents
     *
     * @param array $session Session data
     * @return array|null Suggestion or null
     */
    public function detectNameDiscrepancy(array $session): ?array {
        $passport = $session['extracted_data']['passport'] ?? null;
        $ticket = $session['extracted_data']['ticket'] ?? null;

        if (!$passport || !$ticket) {
            return null;
        }

        $passportName = strtoupper(trim($passport['full_name'] ?? ''));
        $ticketName = strtoupper(trim($ticket['passenger_name'] ?? ''));

        if (empty($passportName) || empty($ticketName)) {
            return null;
        }

        // Normalize names
        $passportNormalized = $this->normalizeName($passportName);
        $ticketNormalized = $this->normalizeName($ticketName);

        if ($passportNormalized === $ticketNormalized) {
            return null;
        }

        // Calculate similarity
        similar_text($passportNormalized, $ticketNormalized, $similarity);

        if ($similarity >= 85) {
            return null; // Close enough
        }

        return [
            'type' => 'name_discrepancy',
            'severity' => 'question',
            'requires_response' => true,
            'data' => [
                'passport_name' => $passport['full_name'],
                'ticket_name' => $ticket['passenger_name'],
                'similarity' => (int) $similarity
            ],
            'message' => [
                'fr' => "🔍 J'ai remarqué une différence entre les noms:\n\n" .
                       "• **Passeport**: {$passport['full_name']}\n" .
                       "• **Billet**: {$ticket['passenger_name']}\n\n" .
                       "Est-ce correct ? Les compagnies aériennes peuvent refuser l'embarquement si les noms ne correspondent pas exactement.",
                'en' => "🔍 I noticed a difference between names:\n\n" .
                       "• **Passport**: {$passport['full_name']}\n" .
                       "• **Ticket**: {$ticket['passenger_name']}\n\n" .
                       "Is this correct? Airlines may refuse boarding if names don't match exactly."
            ],
            'options' => [
                ['key' => 'correct', 'label' => ['fr' => 'C\'est correct', 'en' => 'It\'s correct']],
                ['key' => 'update_ticket', 'label' => ['fr' => 'Je vais corriger le billet', 'en' => 'I\'ll update the ticket']]
            ]
        ];
    }

    /**
     * Detect diplomatic passport
     *
     * @param array $session Session data
     * @return array|null Suggestion or null
     */
    public function detectDiplomat(array $session): ?array {
        $passport = $session['extracted_data']['passport'] ?? null;

        if (!$passport) {
            return null;
        }

        $passportType = strtolower($passport['type'] ?? 'ordinary');

        if (in_array($passportType, ['diplomatic', 'service', 'official'])) {
            $typeLabels = [
                'diplomatic' => ['fr' => 'diplomatique', 'en' => 'diplomatic'],
                'service' => ['fr' => 'de service', 'en' => 'service'],
                'official' => ['fr' => 'officiel', 'en' => 'official']
            ];

            $typeLabel = $typeLabels[$passportType] ?? $typeLabels['diplomatic'];

            return [
                'type' => 'diplomat_detected',
                'severity' => 'info',
                'data' => [
                    'passport_type' => $passportType
                ],
                'message' => [
                    'fr' => "🎖️ **Bienvenue, Excellence !**\n\n" .
                           "J'ai détecté un passeport **{$typeLabel['fr']}**. En tant que tel:\n\n" .
                           "• ✅ Votre visa sera **gratuit**\n" .
                           "• ⚡ Traitement **prioritaire**\n" .
                           "• 📋 Une **note verbale** sera requise\n\n" .
                           "Continuons votre demande privilégiée ! 🇨🇮",
                    'en' => "🎖️ **Welcome, Your Excellency!**\n\n" .
                           "I detected a **{$typeLabel['en']}** passport. As such:\n\n" .
                           "• ✅ Your visa will be **free of charge**\n" .
                           "• ⚡ **Priority** processing\n" .
                           "• 📋 A **verbal note** will be required\n\n" .
                           "Let's continue with your privileged application! 🇨🇮"
                ]
            ];
        }

        return null;
    }

    /**
     * Detect embassy closure on travel date
     *
     * @param array $session Session data
     * @return array|null Suggestion or null
     */
    public function detectEmbassyClosure(array $session): ?array {
        $ticket = $session['extracted_data']['ticket'] ?? null;

        if (!$ticket || !isset($ticket['departure_date'])) {
            return null;
        }

        try {
            $travelDate = new DateTime($ticket['departure_date']);
            $dayOfWeek = (int) $travelDate->format('w');
            $monthDay = $travelDate->format('m-d');

            // Check weekend
            if (in_array($dayOfWeek, self::EMBASSY_CLOSED_DAYS)) {
                return [
                    'type' => 'embassy_closed',
                    'subtype' => 'weekend',
                    'severity' => 'info',
                    'data' => [
                        'date' => $ticket['departure_date'],
                        'day' => $travelDate->format('l')
                    ],
                    'message' => [
                        'fr' => "📅 Votre voyage est prévu un **{$travelDate->format('l')}** ({$travelDate->format('d/m/Y')}). L'ambassade sera fermée ce jour-là, mais cela n'affecte pas votre demande en ligne.",
                        'en' => "📅 Your trip is scheduled for a **{$travelDate->format('l')}** ({$travelDate->format('M d, Y')}). The embassy will be closed that day, but this doesn't affect your online application."
                    ]
                ];
            }

            // Check holidays
            if (in_array($monthDay, self::EMBASSY_HOLIDAYS)) {
                return [
                    'type' => 'embassy_closed',
                    'subtype' => 'holiday',
                    'severity' => 'info',
                    'data' => [
                        'date' => $ticket['departure_date']
                    ],
                    'message' => [
                        'fr' => "📅 Le {$travelDate->format('d/m/Y')} est un jour férié. L'ambassade sera fermée, mais cela n'affecte pas votre demande en ligne.",
                        'en' => "📅 {$travelDate->format('M d, Y')} is a public holiday. The embassy will be closed, but this doesn't affect your online application."
                    ]
                ];
            }
        } catch (Exception $e) {
            // Ignore date parsing errors
        }

        return null;
    }

    /**
     * Detect upcoming required documents
     *
     * @param array $session Session data
     * @return array|null Suggestion or null
     */
    public function detectUpcomingDocuments(array $session): ?array {
        $passport = $session['extracted_data']['passport'] ?? null;
        $hotel = $session['extracted_data']['hotel'] ?? null;
        $currentStep = $session['current_step'] ?? 'welcome';

        if (!$passport) {
            return null;
        }

        $suggestions = [];

        // Check if invitation letter will be needed (staying with individual)
        if ($currentStep === 'hotel' && !$hotel) {
            $suggestions[] = [
                'type' => 'upcoming_document',
                'subtype' => 'invitation_may_be_needed',
                'severity' => 'info',
                'data' => [],
                'message' => [
                    'fr' => "💡 **Conseil**: Si vous séjournez chez un particulier, vous aurez besoin d'une **lettre d'invitation**. Préparez-la maintenant !",
                    'en' => "💡 **Tip**: If you're staying with an individual, you'll need an **invitation letter**. Prepare it now!"
                ]
            ];
        }

        // Check if residence card will be needed (nationality != residence)
        $nationality = $passport['nationality_code'] ?? null;
        $residence = $session['geolocation']['country_code'] ?? null;

        if ($nationality && $residence && $nationality !== $residence) {
            $suggestions[] = [
                'type' => 'upcoming_document',
                'subtype' => 'residence_card_needed',
                'severity' => 'info',
                'data' => [
                    'nationality' => $passport['nationality'],
                    'residence' => $session['geolocation']['country_name'] ?? $residence
                ],
                'message' => [
                    'fr' => "📋 Vous êtes de nationalité **{$passport['nationality']}** mais résidez en **{$session['geolocation']['country_name']}**. Une **carte de résident** sera nécessaire.",
                    'en' => "📋 You are a **{$passport['nationality']}** national living in **{$session['geolocation']['country_name']}**. A **residence card** will be required."
                ]
            ];
        }

        return !empty($suggestions) ? $suggestions : null;
    }

    /**
     * Detect express service eligibility
     *
     * @param array $session Session data
     * @return array|null Suggestion or null
     */
    public function detectExpressEligibility(array $session): ?array {
        $ticket = $session['extracted_data']['ticket'] ?? null;
        $expressChosen = $session['express'] ?? null;

        if (!$ticket || $expressChosen !== null) {
            return null;
        }

        try {
            $departureDate = new DateTime($ticket['departure_date'] ?? 'now');
            $today = new DateTime();
            $daysUntilTravel = $today->diff($departureDate)->days;

            // Suggest express if less than 7 days
            if ($daysUntilTravel <= 7 && $departureDate > $today) {
                return [
                    'type' => 'express_available',
                    'severity' => 'suggestion',
                    'data' => [
                        'departure_date' => $ticket['departure_date'],
                        'days_until_travel' => $daysUntilTravel,
                        'express_fee' => 75000
                    ],
                    'message' => [
                        'fr' => "⚡ Votre voyage est dans **{$daysUntilTravel} jours**. Le traitement **express** (75 000 FCFA en supplément) garantit votre visa en 24-48h. Souhaitez-vous l'activer ?",
                        'en' => "⚡ Your trip is in **{$daysUntilTravel} days**. **Express** processing (75,000 FCFA extra) guarantees your visa in 24-48h. Would you like to activate it?"
                    ],
                    'options' => [
                        ['key' => 'yes_express', 'label' => ['fr' => 'Oui, traitement express', 'en' => 'Yes, express processing']],
                        ['key' => 'no_standard', 'label' => ['fr' => 'Non, traitement standard', 'en' => 'No, standard processing']]
                    ]
                ];
            }
        } catch (Exception $e) {
            // Ignore date parsing errors
        }

        return null;
    }

    /**
     * Detect document quality issues
     *
     * @param array $session Session data
     * @return array|null Suggestion or null
     */
    public function detectDocumentQualityIssues(array $session): ?array {
        $issues = [];

        // Check each document's OCR confidence
        $documents = $session['extracted_data'] ?? [];
        $confidenceThreshold = 70;

        foreach ($documents as $docType => $docData) {
            $confidence = $docData['ocr_confidence'] ?? $docData['confidence'] ?? 100;

            if ($confidence < $confidenceThreshold) {
                $docLabels = [
                    'passport' => ['fr' => 'passeport', 'en' => 'passport'],
                    'ticket' => ['fr' => 'billet d\'avion', 'en' => 'flight ticket'],
                    'hotel' => ['fr' => 'réservation hôtel', 'en' => 'hotel reservation'],
                    'vaccination' => ['fr' => 'certificat de vaccination', 'en' => 'vaccination certificate'],
                    'invitation' => ['fr' => 'lettre d\'invitation', 'en' => 'invitation letter']
                ];

                $docLabel = $docLabels[$docType] ?? ['fr' => $docType, 'en' => $docType];

                $issues[] = [
                    'type' => 'document_quality',
                    'subtype' => 'low_confidence',
                    'severity' => 'warning',
                    'data' => [
                        'document_type' => $docType,
                        'confidence' => $confidence
                    ],
                    'message' => [
                        'fr' => "📸 La qualité de votre **{$docLabel['fr']}** est un peu faible ({$confidence}%). Pour de meilleurs résultats, essayez une photo plus nette.",
                        'en' => "📸 The quality of your **{$docLabel['en']}** is a bit low ({$confidence}%). For better results, try a clearer photo."
                    ]
                ];
            }
        }

        return !empty($issues) ? $issues : null;
    }

    /**
     * Normalize name for comparison
     *
     * @param string $name Name to normalize
     * @return string Normalized name
     */
    private function normalizeName(string $name): string {
        // Remove accents
        $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);

        // Uppercase
        $name = strtoupper($name);

        // Remove common prefixes
        $prefixes = ['MR', 'MRS', 'MS', 'MISS', 'DR', 'PROF', 'M', 'MME', 'MLLE'];
        foreach ($prefixes as $prefix) {
            $name = preg_replace('/\b' . $prefix . '\.?\s*/i', '', $name);
        }

        // Remove non-letter characters
        $name = preg_replace('/[^A-Z\s]/', '', $name);

        // Collapse whitespace
        $name = preg_replace('/\s+/', ' ', trim($name));

        return $name;
    }

    /**
     * Get a single highest priority suggestion
     *
     * @param array $session Session data
     * @return array|null Top priority suggestion or null
     */
    public function getTopSuggestion(array $session): ?array {
        $suggestions = $this->analyzeAndSuggest($session);
        return !empty($suggestions) ? $suggestions[0] : null;
    }

    /**
     * Get suggestions requiring user response
     *
     * @param array $session Session data
     * @return array Suggestions that require user input
     */
    public function getSuggestionsRequiringResponse(array $session): array {
        $suggestions = $this->analyzeAndSuggest($session);
        return array_filter($suggestions, fn($s) => $s['requires_response'] ?? false);
    }

    /**
     * Detect mismatch between invitation letter and flight ticket
     * Checks for duration and date inconsistencies
     *
     * @param array $session Session data
     * @return array|null Suggestion or null
     */
    public function detectInvitationTicketMismatch(array $session): ?array {
        $invitation = $session['extracted_data']['invitation'] ?? null;
        $ticket = $session['extracted_data']['ticket'] ?? null;

        if (!$invitation || !$ticket) {
            return null;
        }

        $issues = [];

        // Extract dates
        $invitationArrival = $invitation['arrival_date'] ?? $invitation['visit_from'] ?? null;
        $invitationDuration = $invitation['duration_days'] ?? $invitation['stay_duration'] ?? null;
        $ticketArrival = $ticket['departure_date'] ?? null;
        $ticketReturn = $ticket['return_date'] ?? null;

        // 1. Check arrival date mismatch
        if ($invitationArrival && $ticketArrival) {
            try {
                $invDate = new \DateTime($invitationArrival);
                $ticketDate = new \DateTime($ticketArrival);
                $daysDiff = abs((int) $invDate->diff($ticketDate)->format('%r%a'));

                if ($daysDiff > 1) {
                    $issues[] = [
                        'type' => 'invitation_ticket_mismatch',
                        'subtype' => 'arrival_date',
                        'severity' => $daysDiff > 7 ? 'critical' : 'warning',
                        'requires_response' => true,
                        'data' => [
                            'invitation_date' => $invitationArrival,
                            'ticket_date' => $ticketArrival,
                            'days_difference' => $daysDiff
                        ],
                        'message' => [
                            'fr' => "⚠️ **Incohérence détectée**\n\n" .
                                   "• **Lettre d'invitation**: arrivée le **{$invitationArrival}**\n" .
                                   "• **Billet d'avion**: départ le **{$ticketArrival}**\n\n" .
                                   "Écart de **{$daysDiff} jour(s)**. Est-ce intentionnel ?",
                            'en' => "⚠️ **Inconsistency detected**\n\n" .
                                   "• **Invitation letter**: arrival on **{$invitationArrival}**\n" .
                                   "• **Flight ticket**: departure on **{$ticketArrival}**\n\n" .
                                   "Gap of **{$daysDiff} day(s)**. Is this intentional?"
                        ],
                        'options' => [
                            ['key' => 'intentional', 'label' => ['fr' => 'Oui, c\'est correct', 'en' => 'Yes, it\'s correct']],
                            ['key' => 'will_fix', 'label' => ['fr' => 'Je vais corriger', 'en' => 'I\'ll correct it']]
                        ]
                    ];
                }
            } catch (\Exception $e) {
                // Ignore date parsing errors
            }
        }

        // 2. Check duration mismatch
        if ($invitationDuration && $ticketArrival && $ticketReturn) {
            try {
                $ticketArrivalDate = new \DateTime($ticketArrival);
                $ticketReturnDate = new \DateTime($ticketReturn);
                $ticketDuration = (int) $ticketArrivalDate->diff($ticketReturnDate)->days;
                $invDuration = (int) $invitationDuration;

                $durationDiff = abs($ticketDuration - $invDuration);

                if ($durationDiff > 3) {
                    $severity = $durationDiff > 14 ? 'critical' : 'warning';

                    $issues[] = [
                        'type' => 'duration_mismatch',
                        'subtype' => 'invitation_vs_ticket',
                        'severity' => $severity,
                        'requires_response' => true,
                        'data' => [
                            'invitation_duration' => $invDuration,
                            'ticket_duration' => $ticketDuration,
                            'days_difference' => $durationDiff
                        ],
                        'message' => [
                            'fr' => "⚠️ **Durée de séjour incohérente**\n\n" .
                                   "• **Lettre d'invitation**: **{$invDuration} jours**\n" .
                                   "• **Billet d'avion**: **{$ticketDuration} jours** (du {$ticketArrival} au {$ticketReturn})\n\n" .
                                   "Différence de **{$durationDiff} jours**. Comment l'expliquez-vous ?",
                            'en' => "⚠️ **Stay duration inconsistency**\n\n" .
                                   "• **Invitation letter**: **{$invDuration} days**\n" .
                                   "• **Flight ticket**: **{$ticketDuration} days** (from {$ticketArrival} to {$ticketReturn})\n\n" .
                                   "Difference of **{$durationDiff} days**. How do you explain this?"
                        ],
                        'options' => [
                            ['key' => 'ticket_correct', 'label' => ['fr' => 'Le billet est correct', 'en' => 'Ticket is correct']],
                            ['key' => 'invitation_correct', 'label' => ['fr' => 'L\'invitation est correcte', 'en' => 'Invitation is correct']],
                            ['key' => 'will_update', 'label' => ['fr' => 'Je vais mettre à jour', 'en' => 'I\'ll update']]
                        ]
                    ];
                }
            } catch (\Exception $e) {
                // Ignore date parsing errors
            }
        }

        return !empty($issues) ? $issues : null;
    }

    /**
     * Detect accommodation gap between hotel and stay duration
     *
     * @param array $session Session data
     * @return array|null Suggestion or null
     */
    public function detectAccommodationGap(array $session): ?array {
        $hotel = $session['extracted_data']['hotel'] ?? null;
        $ticket = $session['extracted_data']['ticket'] ?? null;
        $invitation = $session['extracted_data']['invitation'] ?? null;

        // Need at least hotel and ticket, or hotel and invitation
        if (!$hotel) {
            return null;
        }

        // Calculate stay duration
        $stayDays = null;

        if ($ticket && isset($ticket['departure_date']) && isset($ticket['return_date'])) {
            try {
                $arrival = new \DateTime($ticket['departure_date']);
                $departure = new \DateTime($ticket['return_date']);
                $stayDays = (int) $arrival->diff($departure)->days;
            } catch (\Exception $e) {
                // Ignore
            }
        }

        if (!$stayDays && $invitation) {
            $stayDays = $invitation['duration_days'] ?? $invitation['stay_duration'] ?? null;
        }

        if (!$stayDays) {
            return null;
        }

        // Calculate hotel nights
        $hotelNights = null;
        $checkIn = $hotel['check_in_date'] ?? $hotel['check_in'] ?? null;
        $checkOut = $hotel['check_out_date'] ?? $hotel['check_out'] ?? null;

        if ($checkIn && $checkOut) {
            try {
                $checkInDate = new \DateTime($checkIn);
                $checkOutDate = new \DateTime($checkOut);
                $hotelNights = (int) $checkInDate->diff($checkOutDate)->days;
            } catch (\Exception $e) {
                // Ignore
            }
        }

        if (!$hotelNights) {
            return null;
        }

        // Check for gap
        if ($hotelNights < $stayDays) {
            $gap = $stayDays - $hotelNights;
            $coverage = round(($hotelNights / $stayDays) * 100);

            // Check if invitation provides accommodation
            $accommodationProvided = $invitation['accommodation_provided'] ?? false;

            if ($accommodationProvided) {
                // Info only - host provides accommodation
                return [
                    'type' => 'accommodation_gap',
                    'subtype' => 'partial_with_host',
                    'severity' => 'info',
                    'data' => [
                        'hotel_nights' => $hotelNights,
                        'stay_days' => $stayDays,
                        'gap_days' => $gap,
                        'coverage_percent' => $coverage
                    ],
                    'message' => [
                        'fr' => "ℹ️ Votre hôtel couvre **{$hotelNights} nuit(s)** sur **{$stayDays} jours** de séjour ({$coverage}%). " .
                               "Selon la lettre d'invitation, votre hôte fournira l'hébergement pour les autres nuits. ✅",
                        'en' => "ℹ️ Your hotel covers **{$hotelNights} night(s)** out of **{$stayDays} days** stay ({$coverage}%). " .
                               "According to the invitation letter, your host will provide accommodation for the remaining nights. ✅"
                    ]
                ];
            }

            // Warning - significant gap without host accommodation
            $severity = $coverage < 50 ? 'critical' : 'warning';

            return [
                'type' => 'accommodation_gap',
                'subtype' => 'missing_coverage',
                'severity' => $severity,
                'requires_response' => true,
                'data' => [
                    'hotel_nights' => $hotelNights,
                    'stay_days' => $stayDays,
                    'gap_days' => $gap,
                    'coverage_percent' => $coverage,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut
                ],
                'message' => [
                    'fr' => "⚠️ **Hébergement insuffisant**\n\n" .
                           "• **Hôtel réservé**: {$hotelNights} nuit(s) (du {$checkIn} au {$checkOut})\n" .
                           "• **Séjour prévu**: {$stayDays} jours\n" .
                           "• **Non couvert**: **{$gap} jour(s)** ({$coverage}% couvert)\n\n" .
                           "Où séjournerez-vous pendant les {$gap} jours restants ?",
                    'en' => "⚠️ **Insufficient accommodation**\n\n" .
                           "• **Hotel booked**: {$hotelNights} night(s) (from {$checkIn} to {$checkOut})\n" .
                           "• **Planned stay**: {$stayDays} days\n" .
                           "• **Not covered**: **{$gap} day(s)** ({$coverage}% covered)\n\n" .
                           "Where will you stay for the remaining {$gap} days?"
                ],
                'options' => [
                    ['key' => 'add_hotel', 'label' => ['fr' => 'Ajouter réservation', 'en' => 'Add reservation']],
                    ['key' => 'staying_with_host', 'label' => ['fr' => 'Chez mon hôte', 'en' => 'With my host']],
                    ['key' => 'other', 'label' => ['fr' => 'Autre arrangement', 'en' => 'Other arrangement']]
                ]
            ];
        }

        return null;
    }
}
