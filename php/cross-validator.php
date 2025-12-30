<?php
/**
 * Validateur de Cohérence entre Documents
 * Ambassade de Côte d'Ivoire en Éthiopie
 * 
 * Effectue des vérifications croisées entre les différents documents
 * extraits pour garantir la cohérence des données de la demande de visa.
 * 
 * @package VisaChatbot
 * @version 1.0.0
 */

class CrossValidator {
    
    /**
     * Types de validation
     */
    public const VALIDATION_SUCCESS = 'success';
    public const VALIDATION_WARNING = 'warning';
    public const VALIDATION_ERROR = 'error';
    
    /**
     * Documents extraits
     */
    private array $documents = [];
    
    /**
     * Résultats de validation
     */
    private array $validations = [];
    
    /**
     * Score de cohérence global
     */
    private float $coherenceScore = 0;
    
    /**
     * Constructeur
     * 
     * @param array $documents Documents extraits par type
     */
    public function __construct(array $documents) {
        $this->documents = $documents;
    }
    
    /**
     * Exécute toutes les validations croisées
     *
     * @return array Résultats de validation complets
     */
    public function validateAll(): array {
        $this->validations = [];

        // 0. Compléter les noms partiels avec le passeport comme référence
        $this->completePartialNames();

        // 1. Vérification des noms
        $this->validateNames();
        
        // 2. Vérification des dates de voyage
        $this->validateTravelDates();
        
        // 3. Vérification de la validité du passeport
        $this->validatePassportValidity();
        
        // 4. Vérification de la vaccination
        $this->validateVaccination();
        
        // 5. Vérification de la cohérence vol/hôtel
        $this->validateFlightHotelCoherence();
        
        // 6. Vérification de la note verbale (si diplomatique)
        $this->validateVerbalNote();
        
        // Calculer le score de cohérence global
        $this->calculateCoherenceScore();
        
        return [
            'validations' => $this->validations,
            'coherence_score' => $this->coherenceScore,
            'summary' => $this->getSummary(),
            'timestamp' => date('c'),
            'completed_documents' => $this->documents
        ];
    }

    /**
     * Complète les noms partiels en utilisant le passeport comme référence
     *
     * Cas typiques:
     * - Hotel: "Gezahegn Moges" (manque EJIGU)
     * - Vaccination: "GEZAHEGN MOGES" (manque EJIGU)
     * - Passeport: "EJIGU GEZAHEGN MOGES"
     */
    private function completePartialNames(): void {
        // Le passeport est la source de vérité
        if (!$this->hasDocument('passport')) {
            return;
        }

        $passport = $this->documents['passport'];
        $passportSurname = $this->normalizeName($passport['fields']['surname']['value'] ?? '');
        $passportGivenNames = $this->normalizeName($passport['fields']['given_names']['value'] ?? '');
        $passportFullName = trim($passportSurname . ' ' . $passportGivenNames);
        $passportParts = array_filter(explode(' ', $passportFullName));

        if (empty($passportParts)) {
            return;
        }

        // Documents à compléter
        $docMappings = [
            'hotel' => ['path' => ['guest'], 'surname' => 'surname', 'given' => 'given_names'],
            'vaccination' => ['path' => ['holder'], 'surname' => 'surname', 'given' => 'given_names'],
            'ticket' => ['path' => ['passenger'], 'surname' => 'surname', 'given' => 'given_names'],
        ];

        foreach ($docMappings as $docType => $mapping) {
            if (!$this->hasDocument($docType)) {
                continue;
            }

            // Extraire le nom du document
            $docData = &$this->documents[$docType];
            $container = &$docData;
            foreach ($mapping['path'] as $key) {
                if (!isset($container[$key])) {
                    $container[$key] = [];
                }
                $container = &$container[$key];
            }

            $docSurname = $this->normalizeName($container[$mapping['surname']] ?? '');
            $docGiven = $this->normalizeName($container[$mapping['given']] ?? '');
            $docFullName = trim($docSurname . ' ' . $docGiven);
            $docParts = array_filter(explode(' ', $docFullName));

            // Si le document a moins de parties que le passeport, tenter de compléter
            if (count($docParts) < count($passportParts) && !empty($docParts)) {
                $completed = $this->attemptNameCompletion($passportParts, $docParts);

                if ($completed !== null) {
                    // Mettre à jour avec le nom complet du passeport
                    $container[$mapping['surname']] = $passportSurname;
                    $container[$mapping['given']] = $passportGivenNames;
                    $container['_name_completed'] = true;
                    $container['_original_name'] = $docFullName;
                }
            }
        }
    }

    /**
     * Tente de compléter un nom partiel avec le nom de référence
     *
     * @param array $referenceParts Parties du nom de référence (passeport)
     * @param array $partialParts Parties du nom partiel
     * @return array|null Le nom complété ou null si pas de correspondance
     */
    private function attemptNameCompletion(array $referenceParts, array $partialParts): ?array {
        // Vérifier que chaque partie du nom partiel existe dans le nom de référence
        $matchCount = 0;
        foreach ($partialParts as $part) {
            foreach ($referenceParts as $refPart) {
                // Correspondance exacte ou le début correspond (pour les noms tronqués)
                if ($part === $refPart || str_starts_with($refPart, $part) || str_starts_with($part, $refPart)) {
                    $matchCount++;
                    break;
                }
            }
        }

        // Si au moins 50% des parties correspondent, on considère que c'est la même personne
        $matchRatio = $matchCount / count($partialParts);
        if ($matchRatio >= 0.5) {
            return $referenceParts;
        }

        return null;
    }

    /**
     * Retourne les documents complétés (avec noms corrigés)
     */
    public function getCompletedDocuments(): array {
        return $this->documents;
    }

    /**
     * Vérifie la cohérence des noms entre documents
     */
    private function validateNames(): void {
        $names = [];
        
        // Collecter les noms de chaque document
        if ($this->hasDocument('passport')) {
            $passport = $this->documents['passport'];
            $names['passport'] = $this->normalizeName(
                ($passport['fields']['surname']['value'] ?? '') . ' ' .
                ($passport['fields']['given_names']['value'] ?? '')
            );
        }
        
        if ($this->hasDocument('ticket')) {
            $ticket = $this->documents['ticket'];
            $names['ticket'] = $this->normalizeName(
                ($ticket['passenger']['surname'] ?? '') . ' ' .
                ($ticket['passenger']['given_names'] ?? '')
            );
        }
        
        if ($this->hasDocument('hotel')) {
            $hotel = $this->documents['hotel'];
            $names['hotel'] = $this->normalizeName(
                ($hotel['guest']['surname'] ?? '') . ' ' .
                ($hotel['guest']['given_names'] ?? '')
            );
        }
        
        if ($this->hasDocument('vaccination')) {
            $vaccination = $this->documents['vaccination'];
            $names['vaccination'] = $this->normalizeName(
                ($vaccination['holder']['surname'] ?? '') . ' ' .
                ($vaccination['holder']['given_names'] ?? '')
            );
        }
        
        // Comparer les noms
        if (count($names) < 2) {
            return; // Pas assez de documents pour comparer
        }
        
        $referenceDoc = 'passport';
        $referenceName = $names[$referenceDoc] ?? null;
        
        if (!$referenceName) {
            $referenceDoc = array_key_first($names);
            $referenceName = $names[$referenceDoc];
        }
        
        $allMatch = true;
        $mismatches = [];
        
        foreach ($names as $docType => $name) {
            if ($docType === $referenceDoc) continue;
            
            $similarity = $this->calculateNameSimilarity($referenceName, $name);
            
            if ($similarity < 0.8) {
                $allMatch = false;
                $mismatches[] = [
                    'document' => $docType,
                    'expected' => $referenceName,
                    'found' => $name,
                    'similarity' => $similarity
                ];
            }
        }
        
        if ($allMatch) {
            $this->addValidation(
                'name_match',
                self::VALIDATION_SUCCESS,
                'Les noms correspondent sur tous les documents',
                ['documents_checked' => array_keys($names)]
            );
        } else {
            $this->addValidation(
                'name_match',
                self::VALIDATION_WARNING,
                'Différences de noms détectées entre les documents',
                ['mismatches' => $mismatches]
            );
        }
    }
    
    /**
     * Vérifie la cohérence des dates de voyage
     */
    private function validateTravelDates(): void {
        $arrivalDate = null;
        $departureDate = null;
        
        // Obtenir les dates du billet
        if ($this->hasDocument('ticket')) {
            $ticket = $this->documents['ticket'];
            $arrivalDate = $this->parseDate($ticket['arrival']['date'] ?? null);
            
            if (!empty($ticket['return_flight']['exists'])) {
                $departureDate = $this->parseDate($ticket['return_flight']['date'] ?? null);
            }
        }
        
        // Comparer avec les dates de l'hôtel
        if ($this->hasDocument('hotel')) {
            $hotel = $this->documents['hotel'];
            $checkIn = $this->parseDate($hotel['reservation']['check_in']['date'] ?? null);
            $checkOut = $this->parseDate($hotel['reservation']['check_out']['date'] ?? null);
            
            // Vérifier arrivée vol = check-in hôtel
            if ($arrivalDate && $checkIn) {
                $diffDays = $arrivalDate->diff($checkIn)->days;
                
                if ($diffDays === 0) {
                    $this->addValidation(
                        'flight_hotel_arrival',
                        self::VALIDATION_SUCCESS,
                        'Date d\'arrivée du vol et check-in hôtel correspondent'
                    );
                } elseif ($diffDays <= 1) {
                    $this->addValidation(
                        'flight_hotel_arrival',
                        self::VALIDATION_WARNING,
                        'Date d\'arrivée vol et check-in hôtel diffèrent de ' . $diffDays . ' jour(s)',
                        [
                            'flight_arrival' => $arrivalDate->format('d/m/Y'),
                            'hotel_checkin' => $checkIn->format('d/m/Y')
                        ]
                    );
                } else {
                    $this->addValidation(
                        'flight_hotel_arrival',
                        self::VALIDATION_ERROR,
                        'Incohérence: ' . $diffDays . ' jours entre arrivée vol et check-in hôtel',
                        [
                            'flight_arrival' => $arrivalDate->format('d/m/Y'),
                            'hotel_checkin' => $checkIn->format('d/m/Y')
                        ]
                    );
                }
            }
            
            // Vérifier départ vol = check-out hôtel
            if ($departureDate && $checkOut) {
                $diffDays = $departureDate->diff($checkOut)->days;
                
                if ($diffDays === 0) {
                    $this->addValidation(
                        'flight_hotel_departure',
                        self::VALIDATION_SUCCESS,
                        'Date de départ vol et check-out hôtel correspondent'
                    );
                } elseif ($diffDays <= 1) {
                    $this->addValidation(
                        'flight_hotel_departure',
                        self::VALIDATION_WARNING,
                        'Date de départ vol et check-out hôtel diffèrent de ' . $diffDays . ' jour(s)'
                    );
                }
            }
        }
    }
    
    /**
     * Vérifie la validité du passeport (> 6 mois après retour)
     */
    private function validatePassportValidity(): void {
        if (!$this->hasDocument('passport')) {
            return;
        }
        
        $passport = $this->documents['passport'];
        $expiryDateStr = $passport['fields']['date_of_expiry']['value'] ?? null;
        
        if (!$expiryDateStr) {
            $this->addValidation(
                'passport_validity',
                self::VALIDATION_WARNING,
                'Date d\'expiration du passeport non détectée'
            );
            return;
        }
        
        $expiryDate = $this->parseDate($expiryDateStr);
        if (!$expiryDate) {
            return;
        }
        
        // Trouver la date de retour
        $returnDate = null;
        
        if ($this->hasDocument('ticket')) {
            $ticket = $this->documents['ticket'];
            if (!empty($ticket['return_flight']['date'])) {
                $returnDate = $this->parseDate($ticket['return_flight']['date']);
            } else {
                $returnDate = $this->parseDate($ticket['departure']['date'] ?? null);
            }
        }
        
        if ($this->hasDocument('hotel')) {
            $hotel = $this->documents['hotel'];
            $checkOut = $this->parseDate($hotel['reservation']['check_out']['date'] ?? null);
            if (!$returnDate || ($checkOut && $checkOut > $returnDate)) {
                $returnDate = $checkOut;
            }
        }
        
        // Si pas de date de retour, utiliser aujourd'hui + 30 jours
        if (!$returnDate) {
            $returnDate = new DateTime();
            $returnDate->modify('+30 days');
        }
        
        // Vérifier validité > 6 mois après retour
        $sixMonthsAfterReturn = clone $returnDate;
        $sixMonthsAfterReturn->modify('+6 months');
        
        $monthsValid = $expiryDate->diff($returnDate)->m + ($expiryDate->diff($returnDate)->y * 12);
        
        if ($expiryDate > $sixMonthsAfterReturn) {
            $this->addValidation(
                'passport_validity',
                self::VALIDATION_SUCCESS,
                'Passeport valide plus de 6 mois après la date de retour',
                [
                    'expiry_date' => $expiryDate->format('d/m/Y'),
                    'return_date' => $returnDate->format('d/m/Y'),
                    'months_valid' => $monthsValid
                ]
            );
        } elseif ($expiryDate > $returnDate) {
            $this->addValidation(
                'passport_validity',
                self::VALIDATION_WARNING,
                'Passeport expire moins de 6 mois après le retour prévu',
                [
                    'expiry_date' => $expiryDate->format('d/m/Y'),
                    'return_date' => $returnDate->format('d/m/Y'),
                    'months_valid' => $monthsValid
                ]
            );
        } else {
            $this->addValidation(
                'passport_validity',
                self::VALIDATION_ERROR,
                'Passeport expiré ou expire pendant le voyage',
                [
                    'expiry_date' => $expiryDate->format('d/m/Y'),
                    'return_date' => $returnDate->format('d/m/Y')
                ]
            );
        }
    }
    
    /**
     * Vérifie la vaccination fièvre jaune
     */
    private function validateVaccination(): void {
        if (!$this->hasDocument('vaccination')) {
            $this->addValidation(
                'yellow_fever',
                self::VALIDATION_WARNING,
                'Carnet de vaccination non fourni - Vaccination fièvre jaune OBLIGATOIRE'
            );
            return;
        }
        
        $vaccination = $this->documents['vaccination'];
        
        // Vérifier si vacciné contre la fièvre jaune
        if (empty($vaccination['yellow_fever']['vaccinated'])) {
            $this->addValidation(
                'yellow_fever',
                self::VALIDATION_ERROR,
                'Vaccination fièvre jaune non détectée - OBLIGATOIRE pour la Côte d\'Ivoire'
            );
            return;
        }
        
        // Vérifier la validité
        $validUntil = $vaccination['yellow_fever']['valid_until'] ?? 'A_VIE';
        
        if ($validUntil !== 'A_VIE') {
            $expiryDate = $this->parseDate($validUntil);
            $today = new DateTime();
            
            if ($expiryDate && $expiryDate < $today) {
                $this->addValidation(
                    'yellow_fever',
                    self::VALIDATION_ERROR,
                    'Certificat de vaccination fièvre jaune expiré',
                    ['expiry_date' => $validUntil]
                );
                return;
            }
        }
        
        // Vaccination valide
        $this->addValidation(
            'yellow_fever',
            self::VALIDATION_SUCCESS,
            'Vaccination fièvre jaune valide',
            [
                'vaccination_date' => $vaccination['yellow_fever']['date_of_vaccination'] ?? null,
                'valid_until' => $validUntil,
                'certificate' => $vaccination['yellow_fever']['certificate_number'] ?? null
            ]
        );
        
        // Vérifier la cohérence du nom
        if ($this->hasDocument('passport')) {
            $passportName = $this->normalizeName(
                ($this->documents['passport']['fields']['surname']['value'] ?? '') . ' ' .
                ($this->documents['passport']['fields']['given_names']['value'] ?? '')
            );
            $vaccineName = $this->normalizeName(
                ($vaccination['holder']['surname'] ?? '') . ' ' .
                ($vaccination['holder']['given_names'] ?? '')
            );
            
            $similarity = $this->calculateNameSimilarity($passportName, $vaccineName);
            
            if ($similarity < 0.8) {
                $this->addValidation(
                    'vaccination_name',
                    self::VALIDATION_WARNING,
                    'Le nom sur le carnet de vaccination diffère du passeport',
                    [
                        'passport_name' => $passportName,
                        'vaccination_name' => $vaccineName
                    ]
                );
            }
        }
    }
    
    /**
     * Vérifie la cohérence vol/hôtel
     */
    private function validateFlightHotelCoherence(): void {
        if (!$this->hasDocument('ticket') || !$this->hasDocument('hotel')) {
            return;
        }
        
        $ticket = $this->documents['ticket'];
        $hotel = $this->documents['hotel'];
        
        // Vérifier que l'aéroport d'arrivée est en Côte d'Ivoire
        $arrivalCode = $ticket['arrival']['airport']['code'] ?? '';
        $ciAirports = ['ABJ', 'BYK', 'DJO', 'HGO', 'MJC', 'SPY'];
        
        if (!empty($arrivalCode) && !in_array($arrivalCode, $ciAirports)) {
            $this->addValidation(
                'arrival_destination',
                self::VALIDATION_WARNING,
                'L\'aéroport d\'arrivée ne semble pas être en Côte d\'Ivoire',
                ['airport_code' => $arrivalCode]
            );
        }
        
        // Vérifier que l'hôtel est en Côte d'Ivoire
        $hotelCountry = $hotel['hotel']['address']['country'] ?? '';
        if (!empty($hotelCountry) && !preg_match('/c[oô]te\s*d[\'\s]?ivoire|ivory\s*coast/i', $hotelCountry)) {
            $this->addValidation(
                'hotel_location',
                self::VALIDATION_WARNING,
                'L\'hôtel ne semble pas être en Côte d\'Ivoire',
                ['country' => $hotelCountry]
            );
        }
    }
    
    /**
     * Vérifie la note verbale (pour passeports diplomatiques)
     */
    private function validateVerbalNote(): void {
        if (!$this->hasDocument('passport')) {
            return;
        }
        
        $passport = $this->documents['passport'];
        $passportType = $passport['document_type'] ?? 'P';
        
        // Détecter si passeport diplomatique
        $isDiplomatic = false;
        $mrz = $passport['mrz']['line1'] ?? '';
        
        if (strpos($mrz, 'PD') === 0 || strpos($mrz, 'PS') === 0) {
            $isDiplomatic = true;
        }
        
        if ($isDiplomatic && !$this->hasDocument('verbal_note')) {
            $this->addValidation(
                'verbal_note',
                self::VALIDATION_ERROR,
                'Note verbale OBLIGATOIRE pour les passeports diplomatiques/de service'
            );
        } elseif ($isDiplomatic && $this->hasDocument('verbal_note')) {
            $this->addValidation(
                'verbal_note',
                self::VALIDATION_SUCCESS,
                'Note verbale présente pour passeport diplomatique'
            );
        }
    }
    
    /**
     * Calcule le score de cohérence global
     */
    private function calculateCoherenceScore(): void {
        if (empty($this->validations)) {
            $this->coherenceScore = 0;
            return;
        }
        
        $weights = [
            self::VALIDATION_SUCCESS => 1.0,
            self::VALIDATION_WARNING => 0.5,
            self::VALIDATION_ERROR => 0.0
        ];
        
        $totalWeight = 0;
        $weightedScore = 0;
        
        foreach ($this->validations as $validation) {
            $weight = $weights[$validation['type']] ?? 0;
            $weightedScore += $weight;
            $totalWeight++;
        }
        
        $this->coherenceScore = $totalWeight > 0 
            ? round($weightedScore / $totalWeight, 2) 
            : 0;
    }
    
    /**
     * Retourne un résumé des validations
     */
    private function getSummary(): array {
        $counts = [
            self::VALIDATION_SUCCESS => 0,
            self::VALIDATION_WARNING => 0,
            self::VALIDATION_ERROR => 0
        ];
        
        foreach ($this->validations as $validation) {
            $type = $validation['type'];
            if (isset($counts[$type])) {
                $counts[$type]++;
            }
        }
        
        return [
            'total' => count($this->validations),
            'success' => $counts[self::VALIDATION_SUCCESS],
            'warnings' => $counts[self::VALIDATION_WARNING],
            'errors' => $counts[self::VALIDATION_ERROR],
            'coherence_score' => $this->coherenceScore,
            'ready_for_submission' => $counts[self::VALIDATION_ERROR] === 0
        ];
    }
    
    /**
     * Ajoute une validation
     */
    private function addValidation(string $key, string $type, string $message, array $details = []): void {
        $this->validations[] = [
            'key' => $key,
            'type' => $type,
            'message' => $message,
            'details' => $details,
            'timestamp' => date('c')
        ];
    }
    
    /**
     * Vérifie si un document est présent
     */
    private function hasDocument(string $type): bool {
        return isset($this->documents[$type]) && 
               !empty($this->documents[$type]) &&
               empty($this->documents[$type]['error']);
    }
    
    /**
     * Normalise un nom pour comparaison
     */
    private function normalizeName(string $name): string {
        // Supprimer les accents
        $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        // Majuscules
        $name = strtoupper($name);
        // Supprimer les caractères spéciaux
        $name = preg_replace('/[^A-Z\s]/', '', $name);
        // Supprimer les espaces multiples
        $name = preg_replace('/\s+/', ' ', $name);
        // Trim
        return trim($name);
    }
    
    /**
     * Calcule la similarité entre deux noms
     */
    private function calculateNameSimilarity(string $name1, string $name2): float {
        if ($name1 === $name2) {
            return 1.0;
        }
        
        // Similarité de Levenshtein normalisée
        $maxLen = max(strlen($name1), strlen($name2));
        if ($maxLen === 0) {
            return 1.0;
        }
        
        $distance = levenshtein($name1, $name2);
        return 1 - ($distance / $maxLen);
    }
    
    /**
     * Parse une date au format DD/MM/YYYY ou YYYY-MM-DD
     */
    private function parseDate(?string $dateStr): ?DateTime {
        if (!$dateStr) {
            return null;
        }
        
        // Essayer DD/MM/YYYY
        $date = DateTime::createFromFormat('d/m/Y', $dateStr);
        if ($date) {
            return $date;
        }
        
        // Essayer YYYY-MM-DD
        $date = DateTime::createFromFormat('Y-m-d', $dateStr);
        if ($date) {
            return $date;
        }
        
        return null;
    }
    
    /**
     * Retourne les validations actuelles
     */
    public function getValidations(): array {
        return $this->validations;
    }
    
    /**
     * Retourne le score de cohérence
     */
    public function getCoherenceScore(): float {
        return $this->coherenceScore;
    }
}

