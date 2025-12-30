<?php
/**
 * Validateur général de documents avec Claude Layer 3
 * Ambassade de Côte d'Ivoire en Éthiopie
 *
 * Orchestre la validation cross-documents et la détection de fraude
 *
 * @package VisaChatbot\Validators
 * @version 1.0.0
 */

namespace VisaChatbot\Validators;

require_once __DIR__ . '/AbstractValidator.php';
require_once dirname(__DIR__) . '/claude-diplomatic-validator.php';

class DocumentValidator extends AbstractValidator {

    /**
     * Client Claude pour validation
     */
    private ?\ClaudeDiplomaticValidator $claudeClient = null;

    /**
     * Documents de la demande
     */
    private array $documents = [];

    /**
     * Constructeur
     */
    public function __construct(array $config = []) {
        parent::__construct($config);

        // Initialiser Claude si disponible
        try {
            $this->claudeClient = new \ClaudeDiplomaticValidator([
                'debug' => $this->config['debug']
            ]);
        } catch (\Exception $e) {
            $this->log('Claude initialization failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Valide tous les documents d'une demande de visa
     */
    public function validate(array $extractedData, array $applicationContext = []): array {
        $this->clearAlerts();
        $this->documents = $extractedData;

        $result = [
            'valid' => true,
            'confidence' => 1.0,
            'documents_validated' => [],
            'cross_validations' => [],
            'fraud_indicators' => [],
            'anomalies' => [],
            'risk_level' => 'LOW',
            'requires_manual_review' => false,
            'review_reasons' => [],
            'recommendations' => [],
            'alerts' => []
        ];

        // 1. Valider chaque document individuellement
        foreach ($extractedData as $docType => $docData) {
            if (!is_array($docData)) continue;

            $docValidation = $this->validateDocument($docType, $docData);
            $result['documents_validated'][$docType] = $docValidation;

            if (!$docValidation['valid']) {
                $result['valid'] = false;
            }
        }

        // 2. Cross-validation entre documents
        $result['cross_validations'] = $this->performCrossValidations($extractedData);

        // 3. Détection de fraude globale
        if ($this->config['fraud_detection']) {
            $result['fraud_indicators'] = $this->detectFraud($extractedData);
        }

        // 4. Identifier les anomalies
        $result['anomalies'] = $this->identifyAnomalies($extractedData, $applicationContext);

        // 5. Calculer le risque global
        $riskScore = $this->calculateRiskScore($result['fraud_indicators'], $result['anomalies']);
        $result['risk_level'] = $this->determineRiskLevel($riskScore);
        $result['confidence'] = 1 - $riskScore;

        // 6. Déterminer si review manuel requis et invalider si risque critique
        if ($result['risk_level'] === 'CRITICAL') {
            $result['valid'] = false;
            $result['requires_manual_review'] = true;
            $result['review_reasons'] = $this->generateReviewReasons($result);
        } elseif ($result['risk_level'] === 'HIGH') {
            $result['requires_manual_review'] = true;
            $result['review_reasons'] = $this->generateReviewReasons($result);
        }

        // Invalider si incohérence de noms
        if (isset($result['cross_validations']['name_consistency']) &&
            !$result['cross_validations']['name_consistency']['consistent']) {
            $result['valid'] = false;
        }

        // 7. Générer les recommandations
        $result['recommendations'] = $this->generateRecommendations($result);

        // 8. Ajouter les alertes
        $result['alerts'] = $this->getAlerts();

        // 9. Validation Claude si configurée
        if ($this->claudeClient && ($this->config['use_claude_validation'] ?? false)) {
            $result['claude_validation'] = $this->requestClaudeValidation($result, $applicationContext);
        }

        return $result;
    }

    /**
     * Valide un document individuel
     */
    private function validateDocument(string $docType, array $docData): array {
        $validation = [
            'type' => $docType,
            'valid' => true,
            'issues' => [],
            'confidence' => 1.0,
            'has_required_fields' => true,
            'missing_fields' => []
        ];

        // Vérifier les champs requis selon le type
        $requiredFields = $this->getRequiredFields($docType);
        foreach ($requiredFields as $field) {
            $value = $docData['extracted'][$field] ?? $docData[$field] ?? null;
            if (empty($value)) {
                $validation['valid'] = false;
                $validation['has_required_fields'] = false;
                $validation['missing_fields'][] = $field;
                $validation['issues'][] = "Missing required field: {$field}";
            }
        }

        // Vérifier les validations internes du document
        if (isset($docData['validations'])) {
            foreach ($docData['validations'] as $check => $passed) {
                if ($passed === false) {
                    $validation['issues'][] = "Validation failed: {$check}";
                    $validation['confidence'] *= 0.9;
                }
            }
        }

        // Vérifier le success flag
        if (isset($docData['success']) && !$docData['success']) {
            $validation['valid'] = false;
            $validation['issues'][] = 'Document extraction failed';
        }

        return $validation;
    }

    /**
     * Effectue les cross-validations entre documents
     */
    private function performCrossValidations(array $documents): array {
        $crossValidations = [];

        // 1. Cohérence des noms
        $names = $this->collectNames($documents);
        if (count($names) > 1) {
            $crossValidations['name_consistency'] = $this->validateNameConsistency($names);

            if (!$crossValidations['name_consistency']['consistent']) {
                $this->addAlert(
                    'NAME_MISMATCH',
                    'Noms différents détectés entre les documents',
                    'HIGH',
                    $crossValidations['name_consistency']['variations']
                );
            }
        }

        // 2. Cohérence des dates
        $dates = $this->collectDates($documents);
        $crossValidations['date_consistency'] = $this->validateDateConsistency($dates);

        foreach ($crossValidations['date_consistency']['issues'] as $issue) {
            $this->addAlert('DATE_INCONSISTENCY', $issue, 'MEDIUM');
        }

        // 3. Cohérence des nationalités
        $nationalities = $this->collectNationalities($documents);
        if (count(array_unique($nationalities)) > 1) {
            $crossValidations['nationality_consistency'] = [
                'consistent' => false,
                'values' => $nationalities
            ];
            $this->addAlert('NATIONALITY_MISMATCH', 'Nationalités différentes détectées', 'HIGH');
        }

        // 4. Cohérence du numéro de passeport
        $passportNumbers = $this->collectPassportNumbers($documents);
        if (count(array_unique($passportNumbers)) > 1) {
            $crossValidations['passport_number_consistency'] = [
                'consistent' => false,
                'values' => $passportNumbers
            ];
            $this->addAlert('PASSPORT_NUMBER_MISMATCH', 'Numéros de passeport différents', 'CRITICAL');
        }

        return $crossValidations;
    }

    /**
     * Détecte les indicateurs de fraude
     *
     * Supporte les deux formats de données (extracted ou direct)
     */
    public function detectFraud(array $extractedData): array {
        $indicators = [];

        // Helper pour récupérer une valeur
        $getValue = function($doc, $field, $default = null) {
            return $doc['extracted'][$field] ?? $doc[$field] ?? $default;
        };

        // 1. MRZ invalide
        if (isset($extractedData['passport'])) {
            $mrzValid = $extractedData['passport']['mrz']['checksums_valid']
                ?? $extractedData['passport']['mrz_valid']
                ?? true;

            if ($mrzValid === false) {
                $indicators[] = [
                    'type' => 'INVALID_MRZ_CHECKSUM',
                    'severity' => 'CRITICAL',
                    'weight' => 3,
                    'description' => 'MRZ checksum validation failed'
                ];
            }
        }

        // 2. Passeport expiré ou expiration proche
        if (isset($extractedData['passport'])) {
            $passportExpiry = $getValue($extractedData['passport'], 'expiry_date');
            if ($passportExpiry) {
                $expiryDate = strtotime($passportExpiry);
                $now = time();
                $sixMonths = strtotime('+6 months');

                if ($expiryDate && $expiryDate < $now) {
                    $indicators[] = [
                        'type' => 'EXPIRED_PASSPORT',
                        'severity' => 'CRITICAL',
                        'weight' => 3,
                        'description' => 'Passport is expired'
                    ];
                } elseif ($expiryDate && $expiryDate < $sixMonths) {
                    $indicators[] = [
                        'type' => 'PASSPORT_EXPIRING_SOON',
                        'severity' => 'HIGH',
                        'weight' => 2,
                        'description' => 'Passport expires within 6 months'
                    ];
                }
            }
        }

        // 3. Fièvre jaune manquante ou invalide
        if (isset($extractedData['vaccination'])) {
            $yfValid = $extractedData['vaccination']['validations']['yellow_fever_valid']
                ?? $getValue($extractedData['vaccination'], 'yellow_fever_valid')
                ?? null;

            if ($yfValid === false) {
                $indicators[] = [
                    'type' => 'INVALID_YELLOW_FEVER',
                    'severity' => 'HIGH',
                    'weight' => 2,
                    'description' => 'Yellow fever vaccination invalid or missing'
                ];
            }
        }

        // 4. Montant de paiement incorrect
        if (isset($extractedData['payment'])) {
            $amountMatch = $extractedData['payment']['amount_analysis']['matches_expected']
                ?? $getValue($extractedData['payment'], 'amount_matches_expected')
                ?? true;

            if ($amountMatch === false) {
                $indicators[] = [
                    'type' => 'INCORRECT_PAYMENT_AMOUNT',
                    'severity' => 'MEDIUM',
                    'weight' => 1.5,
                    'description' => 'Payment amount does not match expected visa fee'
                ];
            }
        }

        // 5. Destination vol != Côte d'Ivoire
        if (isset($extractedData['ticket'])) {
            $destValid = $extractedData['ticket']['validations']['destination_is_abidjan']
                ?? $getValue($extractedData['ticket'], 'destination_is_abidjan')
                ?? true;

            if ($destValid === false) {
                $indicators[] = [
                    'type' => 'WRONG_DESTINATION',
                    'severity' => 'MEDIUM',
                    'weight' => 1,
                    'description' => 'Flight destination is not Côte d\'Ivoire'
                ];
            }
        }

        // 6. Note verbale manquante pour diplomatique
        $passportType = $getValue($extractedData['passport'] ?? [], 'passport_type', 'ORDINAIRE');
        if (in_array($passportType, ['DIPLOMATIQUE', 'SERVICE'])) {
            if (!isset($extractedData['verbal_note']) ||
                ($extractedData['verbal_note']['success'] ?? true) === false) {
                $indicators[] = [
                    'type' => 'MISSING_VERBAL_NOTE',
                    'severity' => 'CRITICAL',
                    'weight' => 3,
                    'description' => 'Verbal note required for diplomatic passport but missing'
                ];
            }
        }

        return $indicators;
    }

    /**
     * Identifie les anomalies
     *
     * Supporte les deux formats de données (extracted ou direct)
     */
    private function identifyAnomalies(array $documents, array $context): array {
        $anomalies = [];

        // Helper pour récupérer une valeur
        $getValue = function($doc, $field, $default = null) {
            return $doc['extracted'][$field] ?? $doc[$field] ?? $default;
        };

        // Anomalie: Durée de séjour très longue
        if (isset($documents['hotel'])) {
            $nights = $getValue($documents['hotel'], 'nights');

            // Calculer les nuits à partir des dates si non fourni
            if ($nights === null) {
                $checkIn = $getValue($documents['hotel'], 'check_in_date');
                $checkOut = $getValue($documents['hotel'], 'check_out_date');

                if ($checkIn && $checkOut) {
                    $checkInTime = strtotime($checkIn);
                    $checkOutTime = strtotime($checkOut);

                    if ($checkInTime !== false && $checkOutTime !== false) {
                        $nights = ($checkOutTime - $checkInTime) / 86400;
                    }
                }
            }

            if ($nights !== null) {
                $nights = (int)$nights;
                if ($nights > 90) {
                    $anomalies[] = [
                        'type' => 'LONG_STAY',
                        'weight' => 0.5,
                        'description' => "Stay duration of {$nights} nights exceeds typical short-term visa"
                    ];
                }
            }
        }

        // Anomalie: Dates de voyage très proches
        if (isset($documents['ticket'])) {
            $departureDate = $getValue($documents['ticket'], 'departure_date');
            if ($departureDate) {
                $travelDate = strtotime($departureDate);
                if ($travelDate) {
                    $daysUntilTravel = ($travelDate - time()) / 86400;

                    if ($daysUntilTravel > 0 && $daysUntilTravel < 7) {
                        $anomalies[] = [
                            'type' => 'URGENT_TRAVEL',
                            'weight' => 0.5,
                            'description' => 'Travel date is within 7 days'
                        ];
                    }
                }
            }
        }

        // Anomalie: Invitation sans légalisation
        if (isset($documents['invitation'])) {
            $notarized = $getValue($documents['invitation'], 'notarized', false);
            if (!$notarized) {
                $anomalies[] = [
                    'type' => 'UNNOTARIZED_INVITATION',
                    'weight' => 0.5,
                    'description' => 'Invitation letter is not notarized'
                ];
            }
        }

        return $anomalies;
    }

    /**
     * Collecte les noms de tous les documents
     *
     * Supporte les deux formats:
     * - Données imbriquées: passport.extracted.surname
     * - Données directes: passport.surname
     */
    private function collectNames(array $documents): array {
        $names = [];

        $nameFields = [
            'passport' => ['surname', 'given_names'],
            'ticket' => ['passenger_name'],
            'hotel' => ['guest_name'],
            'vaccination' => ['holder_name'],
            'invitation' => ['invitee_name']
        ];

        foreach ($nameFields as $docType => $fields) {
            if (!isset($documents[$docType])) continue;

            $docData = $documents[$docType];

            foreach ($fields as $field) {
                // Essayer d'abord sous 'extracted', puis directement
                $value = $docData['extracted'][$field] ?? $docData[$field] ?? null;

                if (!empty($value)) {
                    // Pour passport, combiner surname + given_names
                    if ($docType === 'passport') {
                        $surname = $docData['extracted']['surname'] ?? $docData['surname'] ?? '';
                        $givenNames = $docData['extracted']['given_names'] ?? $docData['given_names'] ?? '';
                        $fullName = trim($surname . ' ' . $givenNames);
                        if (!empty($fullName)) {
                            $names[$docType] = $fullName;
                        }
                        break; // Ne pas ajouter deux fois
                    } else {
                        $names[$docType] = $value;
                        break;
                    }
                }
            }
        }

        return $names;
    }

    /**
     * Collecte les dates pertinentes
     *
     * Supporte les deux formats (extracted ou direct)
     */
    private function collectDates(array $documents): array {
        $dates = [];

        // Helper pour récupérer une valeur imbriquée ou directe
        $getValue = function($doc, $field) {
            return $doc['extracted'][$field] ?? $doc[$field] ?? null;
        };

        if (isset($documents['passport'])) {
            $expiry = $getValue($documents['passport'], 'expiry_date');
            if ($expiry) {
                $dates['passport_expiry'] = $expiry;
            }
        }

        if (isset($documents['ticket'])) {
            $departure = $getValue($documents['ticket'], 'departure_date');
            if ($departure) {
                $dates['travel_date'] = $departure;
                $dates['arrival'] = $departure;
            }
            $arrival = $getValue($documents['ticket'], 'arrival_date');
            if ($arrival) {
                $dates['arrival'] = $arrival;
            }
        }

        if (isset($documents['hotel'])) {
            $checkIn = $getValue($documents['hotel'], 'check_in_date');
            if ($checkIn) {
                $dates['check_in'] = $checkIn;
            }
            $checkOut = $getValue($documents['hotel'], 'check_out_date');
            if ($checkOut) {
                $dates['check_out'] = $checkOut;
            }
        }

        return $dates;
    }

    /**
     * Collecte les nationalités
     */
    private function collectNationalities(array $documents): array {
        $nationalities = [];

        if (isset($documents['passport']['extracted']['nationality'])) {
            $nationalities[] = $documents['passport']['extracted']['nationality'];
        }

        if (isset($documents['invitation']['extracted']['invitee_nationality'])) {
            $nationalities[] = $documents['invitation']['extracted']['invitee_nationality'];
        }

        return array_filter($nationalities);
    }

    /**
     * Collecte les numéros de passeport
     */
    private function collectPassportNumbers(array $documents): array {
        $numbers = [];

        if (isset($documents['passport']['extracted']['passport_number'])) {
            $numbers[] = $documents['passport']['extracted']['passport_number'];
        }

        if (isset($documents['invitation']['extracted']['invitee_passport_number'])) {
            $numbers[] = $documents['invitation']['extracted']['invitee_passport_number'];
        }

        return array_filter($numbers);
    }

    /**
     * Génère les raisons de review manuel
     */
    private function generateReviewReasons(array $result): array {
        $reasons = [];

        if (!empty($result['fraud_indicators'])) {
            foreach ($result['fraud_indicators'] as $indicator) {
                if ($indicator['severity'] === 'CRITICAL' || $indicator['severity'] === 'HIGH') {
                    $reasons[] = $indicator['description'];
                }
            }
        }

        if (isset($result['cross_validations']['name_consistency']['consistent'])) {
            if (!$result['cross_validations']['name_consistency']['consistent']) {
                $reasons[] = 'Name inconsistency detected across documents';
            }
        }

        return array_unique($reasons);
    }

    /**
     * Génère les recommandations
     */
    private function generateRecommendations(array $result): array {
        $recommendations = [];

        if ($result['risk_level'] === 'CRITICAL') {
            $recommendations[] = 'REJECT: Critical fraud indicators detected';
        } elseif ($result['risk_level'] === 'HIGH') {
            $recommendations[] = 'MANUAL_REVIEW: High risk - require human verification';
        } elseif ($result['risk_level'] === 'MEDIUM') {
            $recommendations[] = 'PROCEED_WITH_CAUTION: Additional verification recommended';
        } else {
            $recommendations[] = 'APPROVE: Low risk application';
        }

        return $recommendations;
    }

    /**
     * Demande validation Claude
     */
    private function requestClaudeValidation(array $validationResult, array $context): array {
        if (!$this->claudeClient) {
            return ['error' => 'Claude client not available'];
        }

        try {
            $prompt = $this->generateValidationPrompt($validationResult, $context);
            return $this->claudeClient->validate([
                'validation_result' => $validationResult,
                'context' => $context
            ]);
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Retourne les champs requis par type de document
     */
    private function getRequiredFields(string $docType): array {
        $requiredFields = [
            'passport' => ['passport_number', 'surname', 'expiry_date'],
            'ticket' => ['passenger_name', 'flight_number', 'departure_date'],
            'vaccination' => ['holder_name', 'yellow_fever_date'],
            'hotel' => ['guest_name', 'hotel_name', 'check_in_date'],
            'verbal_note' => ['sending_entity', 'diplomat_name'],
            'invitation' => ['inviter_name', 'invitee_name'],
            'payment' => ['amount', 'date', 'reference'],
            'residence_card' => ['holder_name', 'card_number', 'expiry_date']
        ];

        return $requiredFields[$docType] ?? [];
    }

    /**
     * Récupère une valeur imbriquée (path.to.value)
     */
    private function getNestedValue(array $data, string $path) {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    public function getDocumentType(): string {
        return 'all';
    }
}
