<?php
/**
 * Service de Validation de Cohérence Cross-Documents
 * Ambassade de Côte d'Ivoire en Éthiopie
 *
 * Détecte les incohérences entre les documents du dossier visa:
 * - Couverture hébergement vs durée séjour
 * - Vol retour obligatoire
 * - Cohérence des dates
 * - Cohérence des lieux
 *
 * @package VisaChatbot\Services
 * @version 1.0.0
 */

namespace VisaChatbot\Services;

class DocumentCoherenceValidator
{
    // Niveaux de sévérité
    const SEVERITY_INFO = 'info';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_ERROR = 'error';

    // Types d'issues
    const ISSUE_RETURN_FLIGHT_MISSING = 'RETURN_FLIGHT_MISSING';
    const ISSUE_ACCOMMODATION_GAP = 'ACCOMMODATION_GAP';
    const ISSUE_DATE_MISMATCH = 'DATE_MISMATCH';
    const ISSUE_LOCATION_MISMATCH = 'LOCATION_MISMATCH';
    const ISSUE_NAME_MISMATCH = 'NAME_MISMATCH';
    const ISSUE_PASSPORT_EXPIRY = 'PASSPORT_EXPIRY';
    const ISSUE_NON_JURISDICTION = 'NON_JURISDICTION';
    const ISSUE_LONG_STAY = 'LONG_STAY';
    const ISSUE_VACCINATION_EXPIRED = 'VACCINATION_EXPIRED';
    const ISSUE_VACCINATION_MISSING = 'VACCINATION_MISSING';
    const ISSUE_URGENT_TRAVEL = 'URGENT_TRAVEL';
    const ISSUE_MINOR_TRAVELING = 'MINOR_TRAVELING';

    // Pays de la juridiction de l'ambassade d'Addis-Abeba
    const JURISDICTION_COUNTRIES = ['ETH', 'DJI', 'ERI', 'KEN', 'SSD', 'SOM', 'UGA'];

    // Types d'actions suggérées
    const ACTION_UPLOAD = 'upload';
    const ACTION_CONFIRM = 'confirm';
    const ACTION_UPDATE = 'update';

    /**
     * Valide la cohérence d'un dossier complet
     *
     * @param array $docs Documents extraits indexés par type
     *                    ['passport' => [...], 'ticket' => [...], 'hotel' => [...], 'invitation' => [...]]
     * @return array Résultat de validation avec issues et actions suggérées
     */
    public function validateDossier(array $docs): array
    {
        $issues = [];
        $stayInfo = $this->calculateStayDuration($docs);

        // Règle 1: Vol retour obligatoire
        $issues = array_merge($issues, $this->checkReturnFlight($docs, $stayInfo));

        // Règle 2: Couverture hébergement
        $issues = array_merge($issues, $this->checkAccommodationCoverage($docs, $stayInfo));

        // Règle 3: Cohérence des dates
        $issues = array_merge($issues, $this->checkDateCoherence($docs, $stayInfo));

        // Règle 4: Cohérence des lieux
        $issues = array_merge($issues, $this->checkLocationCoherence($docs));

        // Règle 5: Cohérence des noms
        $issues = array_merge($issues, $this->checkNameCoherence($docs));

        // Règle 6: Validité passeport
        $issues = array_merge($issues, $this->checkPassportValidity($docs, $stayInfo));

        // Règle 7: Juridiction (nationalité)
        $issues = array_merge($issues, $this->checkJurisdiction($docs));

        // Règle 8: Séjour long (> 90 jours)
        $issues = array_merge($issues, $this->checkLongStay($docs, $stayInfo));

        // Règle 9: Vaccination expirée
        $issues = array_merge($issues, $this->checkVaccinationValidity($docs));

        // Règle 10: Voyage urgent (délai insuffisant)
        $issues = array_merge($issues, $this->checkUrgentTravel($docs, $stayInfo));

        // Règle 11: Mineur voyageant
        $issues = array_merge($issues, $this->checkMinorTraveling($docs));

        // Trier par sévérité (error > warning > info)
        usort($issues, function ($a, $b) {
            $order = [self::SEVERITY_ERROR => 0, self::SEVERITY_WARNING => 1, self::SEVERITY_INFO => 2];
            return ($order[$a['severity']] ?? 3) - ($order[$b['severity']] ?? 3);
        });

        // Déterminer si le dossier est bloqué (erreurs critiques)
        $hasErrors = !empty(array_filter($issues, fn($i) => $i['severity'] === self::SEVERITY_ERROR));
        $hasWarnings = !empty(array_filter($issues, fn($i) => $i['severity'] === self::SEVERITY_WARNING));

        return [
            'is_coherent' => empty($issues),
            'is_blocked' => $hasErrors,
            'has_warnings' => $hasWarnings,
            'issues' => $issues,
            'required_actions' => $this->generateRequiredActions($issues),
            'stay_info' => $stayInfo,
            'summary' => $this->generateSummary($docs, $stayInfo, $issues),
            'validated_at' => date('c')
        ];
    }

    /**
     * Calcule la durée de séjour à partir des documents
     */
    private function calculateStayDuration(array $docs): array
    {
        $info = [
            'arrival_date' => null,
            'departure_date' => null,
            'stay_days' => null,
            'accommodation_nights' => 0,
            'accommodation_from' => null,
            'accommodation_to' => null,
            'invitation_from' => null,
            'invitation_to' => null,
            'invitation_days' => null,
            'accommodation_provided' => false,
            'source' => null
        ];

        // 1. D'abord essayer depuis l'invitation (source la plus fiable pour la durée)
        if (isset($docs['invitation']['dates'])) {
            $from = $this->parseDate($docs['invitation']['dates']['from'] ?? null);
            $to = $this->parseDate($docs['invitation']['dates']['to'] ?? null);

            if ($from && $to) {
                $info['invitation_from'] = $from->format('Y-m-d');
                $info['invitation_to'] = $to->format('Y-m-d');
                $info['invitation_days'] = $from->diff($to)->days + 1;
            }

            // Vérifier si hébergement fourni par l'invitant
            $info['accommodation_provided'] = $docs['invitation']['accommodation_provided'] ?? false;
        }

        // 2. Dates depuis le billet d'avion
        if (isset($docs['ticket'])) {
            $arrivalDate = $this->parseDate($docs['ticket']['departure_date'] ?? $docs['ticket']['arrival_date'] ?? null);
            if ($arrivalDate) {
                $info['arrival_date'] = $arrivalDate->format('Y-m-d');
                $info['source'] = 'ticket';
            }

            // Vol retour (si présent)
            $returnDate = $this->parseDate($docs['ticket']['return_date'] ?? null);
            if ($returnDate) {
                $info['departure_date'] = $returnDate->format('Y-m-d');
            }
        }

        // 3. Dates depuis l'hôtel
        if (isset($docs['hotel'])) {
            $checkIn = $this->parseDate($docs['hotel']['check_in_date'] ?? null);
            $checkOut = $this->parseDate($docs['hotel']['check_out_date'] ?? null);

            if ($checkIn && $checkOut) {
                $info['accommodation_from'] = $checkIn->format('Y-m-d');
                $info['accommodation_to'] = $checkOut->format('Y-m-d');
                $info['accommodation_nights'] = $checkIn->diff($checkOut)->days;
            }
        }

        // 4. Calculer la durée totale du séjour
        if ($info['arrival_date'] && $info['departure_date']) {
            $arrival = new \DateTime($info['arrival_date']);
            $departure = new \DateTime($info['departure_date']);
            $info['stay_days'] = $arrival->diff($departure)->days;
        } elseif ($info['invitation_days']) {
            // Utiliser la durée de l'invitation si pas de vol retour
            $info['stay_days'] = $info['invitation_days'];
            $info['source'] = 'invitation';
        }

        return $info;
    }

    /**
     * Vérifie la présence du vol retour
     */
    private function checkReturnFlight(array $docs, array $stayInfo): array
    {
        $issues = [];

        if (!isset($docs['ticket'])) {
            return $issues; // Pas de billet, vérifié ailleurs
        }

        // Vérifier si le vol retour est présent
        $hasReturnFlight = !empty($docs['ticket']['return_date']) ||
                          !empty($docs['ticket']['return_flight_number']);

        if (!$hasReturnFlight) {
            $issues[] = [
                'type' => self::ISSUE_RETURN_FLIGHT_MISSING,
                'severity' => self::SEVERITY_WARNING,
                'message' => 'Vol retour non détecté dans le billet',
                'message_fr' => 'Vol retour non détecté dans le billet d\'avion',
                'message_en' => 'Return flight not detected in the ticket',
                'detail' => $stayInfo['invitation_days']
                    ? "Séjour prévu: {$stayInfo['invitation_days']} jours selon l'invitation"
                    : "Durée de séjour non déterminée",
                'actions' => [
                    [
                        'type' => self::ACTION_UPLOAD,
                        'label_fr' => 'Ajouter billet retour',
                        'label_en' => 'Upload return ticket',
                        'doc_type' => 'ticket_return'
                    ],
                    [
                        'type' => self::ACTION_CONFIRM,
                        'label_fr' => 'Le vol retour est sur un autre billet',
                        'label_en' => 'Return flight is on a separate ticket'
                    ]
                ]
            ];
        }

        return $issues;
    }

    /**
     * Vérifie la couverture hébergement
     */
    private function checkAccommodationCoverage(array $docs, array $stayInfo): array
    {
        $issues = [];

        // Si pas d'hôtel et pas d'invitation avec hébergement
        if (!isset($docs['hotel']) && !$stayInfo['accommodation_provided']) {
            $issues[] = [
                'type' => self::ISSUE_ACCOMMODATION_GAP,
                'severity' => self::SEVERITY_WARNING,
                'message' => 'Aucune preuve d\'hébergement fournie',
                'message_fr' => 'Aucune preuve d\'hébergement fournie',
                'message_en' => 'No accommodation proof provided',
                'actions' => [
                    [
                        'type' => self::ACTION_UPLOAD,
                        'label_fr' => 'Ajouter réservation hôtel',
                        'label_en' => 'Upload hotel reservation',
                        'doc_type' => 'hotel'
                    ]
                ]
            ];
            return $issues;
        }

        // Calculer le gap hébergement
        $stayDays = $stayInfo['stay_days'] ?? $stayInfo['invitation_days'] ?? 0;
        $accommodationNights = $stayInfo['accommodation_nights'] ?? 0;

        if ($stayDays > 0 && $accommodationNights > 0) {
            $gap = $stayDays - $accommodationNights;

            if ($gap > 0) {
                // Il y a un gap
                $severity = self::SEVERITY_INFO;
                $actions = [];

                // Si l'invitant ne fournit pas l'hébergement, c'est un warning
                if (!$stayInfo['accommodation_provided']) {
                    $severity = self::SEVERITY_WARNING;

                    // Calculer les dates non couvertes
                    $uncoveredFrom = $stayInfo['accommodation_to'];
                    $uncoveredTo = $stayInfo['departure_date'] ?? $stayInfo['invitation_to'];

                    $actions = [
                        [
                            'type' => self::ACTION_UPLOAD,
                            'label_fr' => 'Ajouter preuve hébergement supplémentaire',
                            'label_en' => 'Upload additional accommodation proof',
                            'doc_type' => 'hotel_additional',
                            'detail' => "Pour la période du {$uncoveredFrom} au {$uncoveredTo}"
                        ],
                        [
                            'type' => self::ACTION_CONFIRM,
                            'label_fr' => 'Hébergé par l\'invitant',
                            'label_en' => 'Hosted by inviter',
                            'requires_justification' => true
                        ]
                    ];
                }

                $coveragePercent = round(($accommodationNights / $stayDays) * 100);

                $issues[] = [
                    'type' => self::ISSUE_ACCOMMODATION_GAP,
                    'severity' => $severity,
                    'message' => "Hébergement: {$accommodationNights} nuit(s) pour {$stayDays} jours de séjour",
                    'message_fr' => "Hébergement: {$accommodationNights} nuit(s) couverte(s) sur {$stayDays} jours de séjour ({$coveragePercent}%)",
                    'message_en' => "Accommodation: {$accommodationNights} night(s) covered for {$stayDays} days stay ({$coveragePercent}%)",
                    'detail' => $stayInfo['accommodation_provided']
                        ? "L'invitant fournit l'hébergement selon la lettre d'invitation"
                        : "{$gap} jour(s) non couvert(s)",
                    'data' => [
                        'stay_days' => $stayDays,
                        'accommodation_nights' => $accommodationNights,
                        'gap_days' => $gap,
                        'coverage_percent' => $coveragePercent,
                        'accommodation_provided' => $stayInfo['accommodation_provided']
                    ],
                    'actions' => $actions
                ];
            }
        }

        return $issues;
    }

    /**
     * Vérifie la cohérence des dates entre documents
     */
    private function checkDateCoherence(array $docs, array $stayInfo): array
    {
        $issues = [];

        // Vérifier si arrivée vol > début invitation
        if ($stayInfo['arrival_date'] && $stayInfo['invitation_from']) {
            $arrival = new \DateTime($stayInfo['arrival_date']);
            $invitationStart = new \DateTime($stayInfo['invitation_from']);

            if ($arrival > $invitationStart) {
                $daysDiff = $invitationStart->diff($arrival)->days;
                $issues[] = [
                    'type' => self::ISSUE_DATE_MISMATCH,
                    'severity' => self::SEVERITY_INFO,
                    'message' => "Arrivée prévue {$daysDiff} jour(s) après le début de l'invitation",
                    'message_fr' => "Votre vol arrive le {$stayInfo['arrival_date']}, mais l'invitation commence le {$stayInfo['invitation_from']}",
                    'message_en' => "Your flight arrives on {$stayInfo['arrival_date']}, but the invitation starts on {$stayInfo['invitation_from']}",
                    'data' => [
                        'flight_arrival' => $stayInfo['arrival_date'],
                        'invitation_start' => $stayInfo['invitation_from']
                    ]
                ];
            }
        }

        // Vérifier si départ vol < fin invitation (si vol retour présent)
        if ($stayInfo['departure_date'] && $stayInfo['invitation_to']) {
            $departure = new \DateTime($stayInfo['departure_date']);
            $invitationEnd = new \DateTime($stayInfo['invitation_to']);

            if ($departure < $invitationEnd) {
                $daysDiff = $departure->diff($invitationEnd)->days;
                $issues[] = [
                    'type' => self::ISSUE_DATE_MISMATCH,
                    'severity' => self::SEVERITY_INFO,
                    'message' => "Départ prévu {$daysDiff} jour(s) avant la fin de l'invitation",
                    'message_fr' => "Votre vol retour est le {$stayInfo['departure_date']}, mais l'invitation se termine le {$stayInfo['invitation_to']}",
                    'message_en' => "Your return flight is on {$stayInfo['departure_date']}, but the invitation ends on {$stayInfo['invitation_to']}",
                    'data' => [
                        'flight_departure' => $stayInfo['departure_date'],
                        'invitation_end' => $stayInfo['invitation_to']
                    ]
                ];
            }
        }

        // Vérifier si check-in hôtel = arrivée vol
        if ($stayInfo['arrival_date'] && $stayInfo['accommodation_from']) {
            $arrival = new \DateTime($stayInfo['arrival_date']);
            $checkIn = new \DateTime($stayInfo['accommodation_from']);

            $daysDiff = abs($arrival->diff($checkIn)->days);
            if ($daysDiff > 1) {
                $issues[] = [
                    'type' => self::ISSUE_DATE_MISMATCH,
                    'severity' => self::SEVERITY_INFO,
                    'message' => "Check-in hôtel diffère de l'arrivée du vol de {$daysDiff} jour(s)",
                    'message_fr' => "Check-in hôtel: {$stayInfo['accommodation_from']}, Arrivée vol: {$stayInfo['arrival_date']}",
                    'message_en' => "Hotel check-in: {$stayInfo['accommodation_from']}, Flight arrival: {$stayInfo['arrival_date']}"
                ];
            }
        }

        return $issues;
    }

    /**
     * Vérifie la cohérence des lieux
     */
    private function checkLocationCoherence(array $docs): array
    {
        $issues = [];

        // Extraire les villes mentionnées
        $hotelCity = $docs['hotel']['hotel_city'] ?? null;
        $inviterCity = $docs['invitation']['inviter']['address'] ?? null;
        $arrivalCity = $docs['ticket']['arrival_city'] ?? null;

        // Normaliser Abidjan
        $isHotelAbidjan = $hotelCity && $this->isSameCity($hotelCity, 'Abidjan');
        $isArrivalAbidjan = $arrivalCity && $this->isSameCity($arrivalCity, 'Abidjan');

        // Si l'hôtel n'est pas à Abidjan mais le vol arrive à Abidjan
        if ($hotelCity && $arrivalCity && !$isHotelAbidjan && $isArrivalAbidjan) {
            $issues[] = [
                'type' => self::ISSUE_LOCATION_MISMATCH,
                'severity' => self::SEVERITY_INFO,
                'message' => "Hôtel dans une ville différente de l'arrivée",
                'message_fr' => "Votre hôtel est à {$hotelCity}, mais votre vol arrive à Abidjan",
                'message_en' => "Your hotel is in {$hotelCity}, but your flight arrives in Abidjan",
                'detail' => "Assurez-vous de prévoir le transport entre Abidjan et {$hotelCity}"
            ];
        }

        return $issues;
    }

    /**
     * Vérifie la cohérence des noms entre documents
     */
    private function checkNameCoherence(array $docs): array
    {
        $issues = [];
        $names = [];

        // Collecter les noms de chaque document
        if (isset($docs['passport'])) {
            $passport = $docs['passport'];
            $fullName = trim(($passport['fields']['surname']['value'] ?? '') . ' ' .
                           ($passport['fields']['given_names']['value'] ?? ''));
            if ($fullName) {
                $names['passport'] = $this->normalizeName($fullName);
            }
        }

        if (isset($docs['ticket']['passenger_name'])) {
            $names['ticket'] = $this->normalizeName($docs['ticket']['passenger_name']);
        }

        if (isset($docs['hotel']['guest_name'])) {
            $names['hotel'] = $this->normalizeName($docs['hotel']['guest_name']);
        }

        if (isset($docs['invitation']['invitee']['name'])) {
            $names['invitation'] = $this->normalizeName($docs['invitation']['invitee']['name']);
        }

        if (isset($docs['vaccination']['holder_name'])) {
            $names['vaccination'] = $this->normalizeName($docs['vaccination']['holder_name']);
        }

        // Comparer les noms au passeport (référence)
        if (isset($names['passport']) && count($names) > 1) {
            $passportName = $names['passport'];

            foreach ($names as $docType => $name) {
                if ($docType === 'passport') continue;

                $similarity = $this->calculateNameSimilarity($passportName, $name);

                if ($similarity < 0.7) {
                    $issues[] = [
                        'type' => self::ISSUE_NAME_MISMATCH,
                        'severity' => self::SEVERITY_WARNING,
                        'message' => "Nom différent sur le {$docType}",
                        'message_fr' => "Le nom sur le {$docType} ({$name}) diffère du passeport ({$passportName})",
                        'message_en' => "Name on {$docType} ({$name}) differs from passport ({$passportName})",
                        'data' => [
                            'document' => $docType,
                            'passport_name' => $passportName,
                            'document_name' => $name,
                            'similarity' => $similarity
                        ]
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Vérifie la validité du passeport
     */
    private function checkPassportValidity(array $docs, array $stayInfo): array
    {
        $issues = [];

        if (!isset($docs['passport']['fields']['date_of_expiry']['value'])) {
            return $issues;
        }

        $expiryDate = $this->parseDate($docs['passport']['fields']['date_of_expiry']['value']);

        if (!$expiryDate) {
            return $issues;
        }

        $today = new \DateTime();
        $sixMonthsLater = (clone $today)->modify('+6 months');

        // Calculer la date de fin de séjour
        $stayEnd = $stayInfo['departure_date']
            ? new \DateTime($stayInfo['departure_date'])
            : ($stayInfo['invitation_to'] ? new \DateTime($stayInfo['invitation_to']) : $sixMonthsLater);

        // Vérifier si le passeport expire dans les 6 mois après le séjour
        $sixMonthsAfterStay = (clone $stayEnd)->modify('+6 months');

        if ($expiryDate < $stayEnd) {
            $issues[] = [
                'type' => self::ISSUE_PASSPORT_EXPIRY,
                'severity' => self::SEVERITY_ERROR,
                'message' => 'Passeport expiré ou expire avant la fin du séjour',
                'message_fr' => "Votre passeport expire le {$expiryDate->format('d/m/Y')}, avant la fin prévue de votre séjour",
                'message_en' => "Your passport expires on {$expiryDate->format('Y-m-d')}, before your stay ends",
                'actions' => [
                    [
                        'type' => self::ACTION_UPDATE,
                        'label_fr' => 'Renouveler le passeport',
                        'label_en' => 'Renew passport'
                    ]
                ]
            ];
        } elseif ($expiryDate < $sixMonthsAfterStay) {
            $issues[] = [
                'type' => self::ISSUE_PASSPORT_EXPIRY,
                'severity' => self::SEVERITY_WARNING,
                'message' => 'Passeport expire dans moins de 6 mois après le séjour',
                'message_fr' => "Votre passeport expire le {$expiryDate->format('d/m/Y')}. Certains pays exigent 6 mois de validité.",
                'message_en' => "Your passport expires on {$expiryDate->format('Y-m-d')}. Some countries require 6 months validity."
            ];
        }

        return $issues;
    }

    /**
     * Génère les actions requises à partir des issues
     */
    private function generateRequiredActions(array $issues): array
    {
        $actions = [];

        foreach ($issues as $issue) {
            if (!empty($issue['actions'])) {
                foreach ($issue['actions'] as $action) {
                    $action['issue_type'] = $issue['type'];
                    $actions[] = $action;
                }
            }
        }

        return $actions;
    }

    /**
     * Génère un résumé du dossier
     */
    private function generateSummary(array $docs, array $stayInfo, array $issues): array
    {
        $summary = [
            'documents_count' => count($docs),
            'documents_present' => array_keys($docs),
            'applicant_name' => null,
            'destination' => 'Côte d\'Ivoire',
            'purpose' => null,
            'stay_duration' => null,
            'issues_count' => count($issues),
            'errors_count' => count(array_filter($issues, fn($i) => $i['severity'] === self::SEVERITY_ERROR)),
            'warnings_count' => count(array_filter($issues, fn($i) => $i['severity'] === self::SEVERITY_WARNING))
        ];

        // Nom du demandeur (depuis passeport ou invitation)
        if (isset($docs['passport']['fields']['surname']['value'])) {
            $summary['applicant_name'] = $docs['passport']['fields']['surname']['value'] . ' ' .
                                        ($docs['passport']['fields']['given_names']['value'] ?? '');
        }

        // Objet du voyage
        if (isset($docs['invitation']['purpose'])) {
            $summary['purpose'] = $docs['invitation']['purpose'];
        }

        // Durée du séjour
        if ($stayInfo['stay_days']) {
            $summary['stay_duration'] = "{$stayInfo['stay_days']} jour(s)";
            if ($stayInfo['arrival_date']) {
                $summary['stay_duration'] .= " (du {$stayInfo['arrival_date']}";
                if ($stayInfo['departure_date']) {
                    $summary['stay_duration'] .= " au {$stayInfo['departure_date']}";
                }
                $summary['stay_duration'] .= ")";
            }
        }

        return $summary;
    }

    /**
     * Parse une date en différents formats
     */
    private function parseDate($dateStr): ?\DateTime
    {
        if (!$dateStr || $dateStr === 'N/A') {
            return null;
        }

        // Formats courants
        $formats = [
            'Y-m-d',      // 2025-12-28
            'd/m/Y',      // 28/12/2025
            'd-m-Y',      // 28-12-2025
            'Y/m/d',      // 2025/12/28
            'd.m.Y',      // 28.12.2025
            'Ymd',        // 20251228 (MRZ)
            'j F Y',      // 28 December 2025
            'F j, Y',     // December 28, 2025
        ];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateStr);
            if ($date !== false) {
                return $date;
            }
        }

        // Essayer strtotime en dernier recours
        $timestamp = strtotime($dateStr);
        if ($timestamp !== false) {
            return new \DateTime("@{$timestamp}");
        }

        return null;
    }

    /**
     * Vérifie si deux villes sont identiques (normalisation)
     */
    private function isSameCity(string $city1, string $city2): bool
    {
        $normalize = function ($city) {
            $city = mb_strtolower(trim($city));
            // Enlever accents
            $city = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $city);
            // Enlever caractères spéciaux
            $city = preg_replace('/[^a-z]/', '', $city);
            return $city;
        };

        return $normalize($city1) === $normalize($city2);
    }

    /**
     * Normalise un nom pour comparaison
     */
    private function normalizeName(string $name): string
    {
        // Majuscules
        $name = mb_strtoupper($name);
        // Enlever les titres (MR, MRS, DR, etc.)
        $name = preg_replace('/\b(MR|MRS|MS|DR|PROF)\b\.?/i', '', $name);
        // Enlever les caractères spéciaux sauf espaces
        $name = preg_replace('/[^A-Z\s]/', '', $name);
        // Réduire les espaces multiples
        $name = preg_replace('/\s+/', ' ', $name);
        return trim($name);
    }

    /**
     * Calcule la similarité entre deux noms (0-1)
     */
    private function calculateNameSimilarity(string $name1, string $name2): float
    {
        $name1 = $this->normalizeName($name1);
        $name2 = $this->normalizeName($name2);

        if ($name1 === $name2) {
            return 1.0;
        }

        // Vérifier si l'un contient l'autre
        if (strpos($name1, $name2) !== false || strpos($name2, $name1) !== false) {
            return 0.9;
        }

        // Similarité Levenshtein normalisée
        $maxLen = max(strlen($name1), strlen($name2));
        if ($maxLen === 0) {
            return 1.0;
        }

        $distance = levenshtein($name1, $name2);
        return 1 - ($distance / $maxLen);
    }

    /**
     * Vérifie si la nationalité est dans la juridiction de l'ambassade
     */
    private function checkJurisdiction(array $docs): array
    {
        $issues = [];

        // Extraire la nationalité du passeport
        $nationality = null;
        if (isset($docs['passport']['fields']['nationality']['value'])) {
            $nationality = strtoupper($docs['passport']['fields']['nationality']['value']);
        } elseif (isset($docs['passport']['mrz_data']['parsed']['nationality'])) {
            $nationality = strtoupper($docs['passport']['mrz_data']['parsed']['nationality']);
        }

        if ($nationality && !in_array($nationality, self::JURISDICTION_COUNTRIES)) {
            // Trouver l'ambassade appropriée
            $embassyMap = [
                'COD' => 'Kinshasa (RDC)',
                'COG' => 'Brazzaville (Congo)',
                'NGA' => 'Abuja (Nigeria)',
                'GHA' => 'Accra (Ghana)',
                'SEN' => 'Dakar (Sénégal)',
                'CMR' => 'Yaoundé (Cameroun)',
                'ZAF' => 'Pretoria (Afrique du Sud)',
                'EGY' => 'Le Caire (Égypte)',
                'MAR' => 'Rabat (Maroc)',
            ];

            $suggestedEmbassy = $embassyMap[$nationality] ?? 'l\'ambassade de votre pays de résidence';

            $issues[] = [
                'type' => self::ISSUE_NON_JURISDICTION,
                'severity' => self::SEVERITY_ERROR,
                'message' => 'Nationalité hors juridiction',
                'message_fr' => "Votre nationalité ($nationality) n'est pas dans la juridiction de l'ambassade d'Addis-Abeba. Veuillez contacter l'ambassade de $suggestedEmbassy.",
                'message_en' => "Your nationality ($nationality) is not within the jurisdiction of the Addis Ababa embassy. Please contact the embassy in $suggestedEmbassy.",
                'data' => [
                    'nationality' => $nationality,
                    'jurisdiction_countries' => self::JURISDICTION_COUNTRIES,
                    'suggested_embassy' => $suggestedEmbassy
                ],
                'actions' => [
                    [
                        'type' => 'redirect',
                        'label_fr' => 'Contacter une autre ambassade',
                        'label_en' => 'Contact another embassy',
                        'url' => 'https://www.diplomatie.gouv.ci/ambassades'
                    ]
                ]
            ];
        }

        return $issues;
    }

    /**
     * Vérifie si le séjour dépasse 90 jours (e-Visa limité à 90 jours max)
     */
    private function checkLongStay(array $docs, array $stayInfo): array
    {
        $issues = [];

        // e-Visa Côte d'Ivoire: maximum 90 jours
        if ($stayInfo['stay_days'] && $stayInfo['stay_days'] > 90) {
            $issues[] = [
                'type' => self::ISSUE_LONG_STAY,
                'severity' => self::SEVERITY_ERROR, // BLOQUANT - pas de visa > 90 jours
                'message' => 'Séjour supérieur à 90 jours non autorisé',
                'message_fr' => "Votre séjour prévu est de {$stayInfo['stay_days']} jours. Le e-Visa est limité à 90 jours maximum. Pour un séjour plus long, vous devez demander un visa de long séjour auprès de l'ambassade.",
                'message_en' => "Your planned stay is {$stayInfo['stay_days']} days. The e-Visa is limited to 90 days maximum. For a longer stay, you must apply for a long-stay visa at the embassy.",
                'data' => [
                    'stay_days' => $stayInfo['stay_days'],
                    'max_allowed' => 90
                ],
                'actions' => [
                    [
                        'type' => 'redirect',
                        'label_fr' => 'Demander un visa long séjour',
                        'label_en' => 'Apply for long-stay visa',
                        'detail' => 'Contactez l\'ambassade directement'
                    ]
                ]
            ];
        }

        return $issues;
    }

    /**
     * Vérifie si la vaccination est valide (fièvre jaune valable 10 ans)
     */
    private function checkVaccinationValidity(array $docs): array
    {
        $issues = [];

        // Vérifier si la vaccination est absente (OBLIGATOIRE pour la Côte d'Ivoire)
        if (!isset($docs['vaccination']) || empty($docs['vaccination'])) {
            $issues[] = [
                'type' => self::ISSUE_VACCINATION_MISSING,
                'severity' => self::SEVERITY_ERROR,
                'message' => 'Certificat de vaccination fièvre jaune manquant',
                'message_fr' => 'Le certificat de vaccination contre la fièvre jaune est OBLIGATOIRE pour entrer en Côte d\'Ivoire. Veuillez fournir votre certificat.',
                'message_en' => 'Yellow fever vaccination certificate is MANDATORY to enter Côte d\'Ivoire. Please provide your certificate.',
                'actions' => [
                    [
                        'type' => self::ACTION_UPLOAD,
                        'label_fr' => 'Télécharger le certificat de vaccination',
                        'label_en' => 'Upload vaccination certificate',
                        'docType' => 'vaccination'
                    ]
                ]
            ];
            return $issues;
        }

        $vaccination = $docs['vaccination'];

        // Vérifier si marqué comme invalide
        if (isset($vaccination['valid']) && $vaccination['valid'] === false) {
            $issues[] = [
                'type' => self::ISSUE_VACCINATION_EXPIRED,
                'severity' => self::SEVERITY_ERROR,
                'message' => 'Vaccination fièvre jaune non valide',
                'message_fr' => 'Votre certificat de vaccination fièvre jaune n\'est pas valide. Une nouvelle vaccination est requise.',
                'message_en' => 'Your yellow fever vaccination certificate is not valid. A new vaccination is required.',
                'actions' => [
                    [
                        'type' => self::ACTION_UPLOAD,
                        'label_fr' => 'Télécharger nouveau certificat',
                        'label_en' => 'Upload new certificate',
                        'docType' => 'vaccination'
                    ]
                ]
            ];
            return $issues;
        }

        // Vérifier la date de vaccination (valable 10 ans depuis 2016, à vie avant)
        if (isset($vaccination['vaccination_date'])) {
            $vaccDate = $this->parseDate($vaccination['vaccination_date']);
            if ($vaccDate) {
                $tenYearsAgo = (new \DateTime())->modify('-10 years');
                // Note: Depuis 2016, la vaccination fièvre jaune est valable à vie selon l'OMS
                // Mais certains pays peuvent encore exiger moins de 10 ans
                if ($vaccDate < $tenYearsAgo) {
                    $issues[] = [
                        'type' => self::ISSUE_VACCINATION_EXPIRED,
                        'severity' => self::SEVERITY_WARNING,
                        'message' => 'Vaccination fièvre jaune de plus de 10 ans',
                        'message_fr' => "Votre vaccination date du {$vaccDate->format('d/m/Y')} (plus de 10 ans). Bien que l'OMS la considère valide à vie, certaines autorités peuvent exiger un rappel.",
                        'message_en' => "Your vaccination is from {$vaccDate->format('Y-m-d')} (over 10 years). While WHO considers it valid for life, some authorities may require a booster.",
                        'detail' => 'Contactez l\'ambassade pour confirmer si votre certificat est accepté'
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Vérifie si le voyage est trop urgent (délai de traitement insuffisant)
     */
    private function checkUrgentTravel(array $docs, array $stayInfo): array
    {
        $issues = [];

        // Délai minimum recommandé: 5 jours ouvrés
        $minDays = 5;

        if ($stayInfo['arrival_date']) {
            $arrivalDate = $this->parseDate($stayInfo['arrival_date']);
            if ($arrivalDate) {
                $today = new \DateTime();
                $daysUntilTravel = $today->diff($arrivalDate)->days;
                $isFuture = $arrivalDate > $today;

                if ($isFuture && $daysUntilTravel < $minDays) {
                    $issues[] = [
                        'type' => self::ISSUE_URGENT_TRAVEL,
                        'severity' => self::SEVERITY_WARNING,
                        'message' => 'Voyage urgent - délai de traitement court',
                        'message_fr' => "Votre vol est dans {$daysUntilTravel} jour(s). Le délai de traitement standard est de 5-10 jours ouvrés. Un traitement en urgence peut être nécessaire.",
                        'message_en' => "Your flight is in {$daysUntilTravel} day(s). Standard processing time is 5-10 business days. Expedited processing may be required.",
                        'data' => [
                            'days_until_travel' => $daysUntilTravel,
                            'min_recommended' => $minDays
                        ],
                        'detail' => 'Contactez l\'ambassade pour un traitement en urgence'
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Vérifie si le voyageur est mineur (< 18 ans)
     */
    private function checkMinorTraveling(array $docs): array
    {
        $issues = [];

        if (!isset($docs['passport']['fields']['date_of_birth']['value'])) {
            return $issues;
        }

        $dob = $this->parseDate($docs['passport']['fields']['date_of_birth']['value']);
        if (!$dob) {
            return $issues;
        }

        $today = new \DateTime();
        $age = $today->diff($dob)->y;

        if ($age < 18) {
            $issues[] = [
                'type' => self::ISSUE_MINOR_TRAVELING,
                'severity' => self::SEVERITY_WARNING,
                'message' => 'Voyageur mineur',
                'message_fr' => "Le demandeur a {$age} ans. Les mineurs voyageant seuls doivent fournir une autorisation parentale et les documents d'identité des parents.",
                'message_en' => "The applicant is {$age} years old. Minors traveling alone must provide parental authorization and parents' ID documents.",
                'data' => [
                    'age' => $age,
                    'date_of_birth' => $dob->format('Y-m-d')
                ],
                'actions' => [
                    [
                        'type' => self::ACTION_UPLOAD,
                        'label_fr' => 'Télécharger autorisation parentale',
                        'label_en' => 'Upload parental authorization',
                        'docType' => 'parental_consent'
                    ]
                ],
                'required_documents' => [
                    'Autorisation parentale signée et légalisée',
                    'Copie des pièces d\'identité des deux parents',
                    'Acte de naissance de l\'enfant'
                ]
            ];
        }

        return $issues;
    }

    /**
     * Méthode statique pour validation rapide
     */
    public static function validate(array $docs): array
    {
        $validator = new self();
        return $validator->validateDossier($docs);
    }
}
