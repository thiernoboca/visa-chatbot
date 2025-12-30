<?php
/**
 * Validateur spécifique pour Cartes de Résidence
 * Ambassade de Côte d'Ivoire en Éthiopie
 *
 * Validation approfondie incluant:
 * - Vérification du format selon le pays émetteur
 * - Validation des checksums (si applicable)
 * - Cross-validation avec le passeport
 * - Détection de fraude
 *
 * @package VisaChatbot\Validators
 * @version 1.0.0
 */

namespace VisaChatbot\Validators;

require_once __DIR__ . '/AbstractValidator.php';

class ResidenceCardValidator extends AbstractValidator {

    /**
     * Formats de carte par pays de la circonscription
     * Patterns pour validation du numéro de carte
     */
    private const CARD_FORMATS = [
        'ETHIOPIA' => [
            'patterns' => [
                '/^RP[\/\-]?\d{6,10}$/i',           // Residence Permit
                '/^WP[\/\-]?\d{6,10}$/i',           // Work Permit
                '/^ET[A-Z]{2}\d{6,}$/i',            // Ethiopian format
                '/^\d{4}[\/\-]\d{4}[\/\-]\d{4}$/i'  // 12 digit format
            ],
            'validity_years' => [1, 2, 5],  // Common validity periods
            'has_checksum' => false
        ],
        'KENYA' => [
            'patterns' => [
                '/^FP[\/\-]?\d{6,10}$/i',           // Foreign Permit
                '/^WP[\/\-]?\d{6,10}$/i',           // Work Permit
                '/^KE[A-Z]{2}\d{6,}$/i'             // Kenyan format
            ],
            'validity_years' => [1, 2, 3],
            'has_checksum' => false
        ],
        'UGANDA' => [
            'patterns' => [
                '/^RP[\/\-]?\d{6,10}$/i',
                '/^UG[A-Z]{2}\d{6,}$/i'
            ],
            'validity_years' => [1, 2, 5, 10],
            'has_checksum' => false
        ],
        'DJIBOUTI' => [
            'patterns' => [
                '/^CR[\/\-]?\d{6,10}$/i',           // Carte de Résident
                '/^DJ[A-Z]{2}\d{6,}$/i'
            ],
            'validity_years' => [1, 2],
            'has_checksum' => false
        ],
        'SOMALIA' => [
            'patterns' => [
                '/^SO[A-Z]{2}\d{6,}$/i',
                '/^\d{8,12}$/i'
            ],
            'validity_years' => [1],
            'has_checksum' => false
        ],
        'SOUTH_SUDAN' => [
            'patterns' => [
                '/^SS[A-Z]{2}\d{6,}$/i',
                '/^RP[\/\-]?\d{6,10}$/i'
            ],
            'validity_years' => [1, 2],
            'has_checksum' => false
        ],
        'TANZANIA' => [
            'patterns' => [
                '/^RP[\/\-]?\d{6,10}$/i',
                '/^TZ[A-Z]{2}\d{6,}$/i'
            ],
            'validity_years' => [1, 2, 5],
            'has_checksum' => false
        ]
    ];

    /**
     * Types de résidence valides
     */
    private const VALID_RESIDENCE_TYPES = [
        'WORK', 'STUDY', 'FAMILY', 'REFUGEE', 'PERMANENT', 'DIPLOMATIC', 'TEMPORARY'
    ];

    /**
     * Valide les données extraites de la carte de résidence
     */
    public function validate(array $extractedData, array $passportData = []): array {
        $this->clearAlerts();

        $result = [
            'valid' => true,
            'confidence' => 1.0,
            'checks' => [],
            'fraud_indicators' => [],
            'cross_validation' => [],
            'recommendations' => []
        ];

        $data = $extractedData['extracted'] ?? $extractedData;

        // 1. Vérifier les champs obligatoires
        $result['checks']['required_fields'] = $this->checkRequiredFields($data);
        if (!$result['checks']['required_fields']['passed']) {
            $result['valid'] = false;
            $result['confidence'] *= 0.5;
        }

        // 2. Valider le format du numéro de carte selon le pays
        $issuingCountry = $data['issuing_country'] ?? null;
        if ($issuingCountry && isset($data['card_number'])) {
            $result['checks']['card_format'] = $this->validateCardFormat(
                $data['card_number'],
                $issuingCountry
            );
            if (!$result['checks']['card_format']['valid']) {
                $result['confidence'] *= 0.7;
                $this->addAlert(
                    'SUSPICIOUS_CARD_FORMAT',
                    "Le format de carte ne correspond pas aux standards de {$issuingCountry}",
                    'MEDIUM'
                );
            }
        }

        // 3. Vérifier la validité (non expirée + minimum 3 mois restants)
        if (isset($data['expiry_date'])) {
            $result['checks']['expiry_valid'] = $this->checkExpiry($data['expiry_date']);
            if (!$result['checks']['expiry_valid']['valid']) {
                $result['valid'] = false;
                $result['confidence'] *= 0.3;
                $this->addAlert(
                    'EXPIRED_RESIDENCE_CARD',
                    'La carte de résidence est expirée ou expire dans moins de 3 mois',
                    'CRITICAL'
                );
            }
        }

        // 4. Vérifier le pays émetteur (doit être dans la circonscription)
        if ($issuingCountry) {
            $result['checks']['issuing_country'] = $this->checkIssuingCountry($issuingCountry);
            if (!$result['checks']['issuing_country']['in_jurisdiction']) {
                $this->addAlert(
                    'COUNTRY_NOT_IN_JURISDICTION',
                    "Le pays émetteur {$issuingCountry} n'est pas dans la circonscription consulaire",
                    'HIGH'
                );
            }
        }

        // 5. Vérifier le type de résidence
        if (isset($data['residence_type'])) {
            $result['checks']['residence_type'] = $this->checkResidenceType($data['residence_type']);
        }

        // 6. Cross-validation avec le passeport
        if (!empty($passportData)) {
            $result['cross_validation'] = $this->crossValidateWithPassport($data, $passportData);

            if (!$result['cross_validation']['name_match']) {
                $result['valid'] = false;
                $result['confidence'] *= 0.2;
                $this->addAlert(
                    'NAME_MISMATCH',
                    'Le nom sur la carte de résidence ne correspond pas au passeport',
                    'CRITICAL'
                );
            }

            if (!$result['cross_validation']['nationality_consistent']) {
                $result['confidence'] *= 0.5;
                $this->addAlert(
                    'NATIONALITY_INCONSISTENCY',
                    'La nationalité sur la carte ne correspond pas au passeport',
                    'HIGH'
                );
            }
        }

        // 7. Détection de fraude
        $result['fraud_indicators'] = $this->detectFraudIndicators($data);
        if (!empty($result['fraud_indicators'])) {
            $result['confidence'] *= 0.6;
            foreach ($result['fraud_indicators'] as $indicator) {
                if ($indicator['severity'] === 'CRITICAL') {
                    $result['valid'] = false;
                }
            }
        }

        // 8. Générer les recommandations
        $result['recommendations'] = $this->generateRecommendations($result);

        // 9. Ajouter les alertes
        $result['alerts'] = $this->getAlerts();

        return $result;
    }

    /**
     * Vérifie les champs obligatoires
     */
    private function checkRequiredFields(array $data): array {
        $required = ['holder_name', 'card_number', 'expiry_date'];
        $missing = [];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                $missing[] = $field;
            }
        }

        return [
            'passed' => empty($missing),
            'missing' => $missing
        ];
    }

    /**
     * Valide le format du numéro de carte selon le pays
     */
    private function validateCardFormat(string $cardNumber, string $country): array {
        $result = [
            'valid' => false,
            'format_matched' => null,
            'country' => $country
        ];

        // Normaliser le numéro
        $normalizedNumber = strtoupper(preg_replace('/\s+/', '', $cardNumber));

        // Vérifier si le pays est connu
        $countryKey = strtoupper($country);
        if (!isset(self::CARD_FORMATS[$countryKey])) {
            // Pays non connu, accepter si format générique valide
            if (preg_match('/^[A-Z0-9\-\/]{6,20}$/', $normalizedNumber)) {
                $result['valid'] = true;
                $result['format_matched'] = 'generic';
            }
            return $result;
        }

        // Vérifier les patterns du pays
        $countryFormats = self::CARD_FORMATS[$countryKey];
        foreach ($countryFormats['patterns'] as $pattern) {
            if (preg_match($pattern, $normalizedNumber)) {
                $result['valid'] = true;
                $result['format_matched'] = $pattern;
                break;
            }
        }

        // Fallback: format générique si aucun pattern ne correspond
        if (!$result['valid']) {
            if (strlen($normalizedNumber) >= 6 && strlen($normalizedNumber) <= 20) {
                $result['valid'] = true;
                $result['format_matched'] = 'generic_length';
                $result['warning'] = 'Format non standard mais accepté';
            }
        }

        return $result;
    }

    /**
     * Vérifie la validité de la date d'expiration
     */
    private function checkExpiry(string $expiryDate): array {
        $expiry = strtotime($expiryDate);
        $now = time();
        $threeMonthsFromNow = strtotime('+3 months');

        return [
            'valid' => $expiry !== false && $expiry > $threeMonthsFromNow,
            'expiry_date' => $expiryDate,
            'is_expired' => $expiry !== false && $expiry < $now,
            'expires_soon' => $expiry !== false && $expiry > $now && $expiry < $threeMonthsFromNow,
            'days_remaining' => $expiry !== false ? max(0, ($expiry - $now) / 86400) : 0
        ];
    }

    /**
     * Vérifie si le pays émetteur est dans la circonscription
     */
    private function checkIssuingCountry(string $country): array {
        $jurisdictionCountries = [
            'ETHIOPIA', 'ETHIOPIE', 'ETH',
            'DJIBOUTI', 'DJI',
            'KENYA', 'KEN',
            'UGANDA', 'OUGANDA', 'UGA',
            'SOMALIA', 'SOMALIE', 'SOM',
            'SOUTH_SUDAN', 'SOUDAN_DU_SUD', 'SSD',
            'TANZANIA', 'TANZANIE', 'TZA'
        ];

        $normalized = strtoupper(str_replace([' ', '-'], '_', $country));
        $inJurisdiction = in_array($normalized, $jurisdictionCountries);

        return [
            'country' => $country,
            'normalized' => $normalized,
            'in_jurisdiction' => $inJurisdiction
        ];
    }

    /**
     * Vérifie le type de résidence
     */
    private function checkResidenceType(?string $type): array {
        if (!$type) {
            return ['valid' => true, 'type' => null, 'note' => 'Type non spécifié'];
        }

        $normalized = strtoupper($type);
        $valid = in_array($normalized, self::VALID_RESIDENCE_TYPES);

        return [
            'valid' => $valid,
            'type' => $normalized,
            'recognized' => $valid
        ];
    }

    /**
     * Cross-validation avec le passeport
     */
    private function crossValidateWithPassport(array $cardData, array $passportData): array {
        $result = [
            'name_match' => false,
            'name_similarity' => 0,
            'nationality_consistent' => true,
            'dob_match' => true,
            'details' => []
        ];

        // Comparer les noms
        $cardName = $cardData['holder_name'] ?? '';
        $passportName = $this->getPassportFullName($passportData);

        if ($cardName && $passportName) {
            $similarity = $this->calculateNameSimilarity($cardName, $passportName);
            $result['name_similarity'] = $similarity;
            $result['name_match'] = $similarity >= 0.8; // 80% de similarité minimum
            $result['details']['card_name'] = $cardName;
            $result['details']['passport_name'] = $passportName;
        }

        // Comparer les nationalités
        $cardNationality = $cardData['nationality'] ?? null;
        $passportNationality = $passportData['extracted']['nationality']
            ?? $passportData['nationality'] ?? null;

        if ($cardNationality && $passportNationality) {
            $result['nationality_consistent'] = $this->normalizeCountryName($cardNationality)
                === $this->normalizeCountryName($passportNationality);
            $result['details']['card_nationality'] = $cardNationality;
            $result['details']['passport_nationality'] = $passportNationality;
        }

        // Comparer les dates de naissance
        $cardDob = $cardData['date_of_birth'] ?? null;
        $passportDob = $passportData['extracted']['date_of_birth']
            ?? $passportData['date_of_birth'] ?? null;

        if ($cardDob && $passportDob) {
            $result['dob_match'] = $this->normalizeDateForComparison($cardDob)
                === $this->normalizeDateForComparison($passportDob);
            $result['details']['card_dob'] = $cardDob;
            $result['details']['passport_dob'] = $passportDob;
        }

        return $result;
    }

    /**
     * Détecte les indicateurs de fraude
     */
    private function detectFraudIndicators(array $data): array {
        $indicators = [];

        // 1. Numéro de carte trop court
        $cardNumber = $data['card_number'] ?? '';
        if (strlen(preg_replace('/[\s\-\/]/', '', $cardNumber)) < 6) {
            $indicators[] = [
                'type' => 'SHORT_CARD_NUMBER',
                'severity' => 'MEDIUM',
                'description' => 'Numéro de carte anormalement court'
            ];
        }

        // 2. Date d'émission dans le futur
        $issueDate = $data['issue_date'] ?? null;
        if ($issueDate && strtotime($issueDate) > time()) {
            $indicators[] = [
                'type' => 'FUTURE_ISSUE_DATE',
                'severity' => 'CRITICAL',
                'description' => 'Date d\'émission dans le futur'
            ];
        }

        // 3. Date d'expiration trop lointaine (> 10 ans)
        $expiryDate = $data['expiry_date'] ?? null;
        if ($expiryDate) {
            $expiry = strtotime($expiryDate);
            $tenYears = strtotime('+10 years');
            if ($expiry > $tenYears) {
                $indicators[] = [
                    'type' => 'EXCESSIVE_VALIDITY',
                    'severity' => 'HIGH',
                    'description' => 'Durée de validité anormalement longue (> 10 ans)'
                ];
            }
        }

        // 4. Nom suspect (trop court ou caractères inhabituels)
        $holderName = $data['holder_name'] ?? '';
        if (strlen($holderName) < 3) {
            $indicators[] = [
                'type' => 'SHORT_NAME',
                'severity' => 'MEDIUM',
                'description' => 'Nom du titulaire anormalement court'
            ];
        }
        if (preg_match('/\d/', $holderName)) {
            $indicators[] = [
                'type' => 'DIGITS_IN_NAME',
                'severity' => 'HIGH',
                'description' => 'Chiffres détectés dans le nom'
            ];
        }

        // 5. Pays émetteur ne correspond pas au pays de résidence déclaré
        $issuingCountry = $data['issuing_country'] ?? null;
        $residenceCountry = $data['residence_country'] ?? null;
        if ($issuingCountry && $residenceCountry) {
            if ($this->normalizeCountryName($issuingCountry) !== $this->normalizeCountryName($residenceCountry)) {
                $indicators[] = [
                    'type' => 'COUNTRY_MISMATCH',
                    'severity' => 'HIGH',
                    'description' => 'Pays émetteur différent du pays de résidence déclaré'
                ];
            }
        }

        return $indicators;
    }

    /**
     * Génère les recommandations
     */
    private function generateRecommendations(array $result): array {
        $recommendations = [];

        if (!$result['valid']) {
            $recommendations[] = 'REJECT: Document invalide ou données manquantes';
        } elseif ($result['confidence'] < 0.5) {
            $recommendations[] = 'MANUAL_REVIEW: Confiance faible, vérification manuelle requise';
        } elseif ($result['confidence'] < 0.8) {
            $recommendations[] = 'PROCEED_WITH_CAUTION: Vérifier les alertes avant approbation';
        } else {
            $recommendations[] = 'APPROVE: Document valide';
        }

        if (!empty($result['fraud_indicators'])) {
            $recommendations[] = 'VERIFY: Indicateurs de fraude détectés, vérification supplémentaire recommandée';
        }

        if (isset($result['checks']['expiry_valid']['expires_soon']) &&
            $result['checks']['expiry_valid']['expires_soon']) {
            $recommendations[] = 'NOTE: La carte expire dans moins de 3 mois';
        }

        return $recommendations;
    }

    /**
     * Obtient le nom complet du passeport
     */
    private function getPassportFullName(array $passportData): string {
        $surname = $passportData['extracted']['surname'] ?? $passportData['surname'] ?? '';
        $givenNames = $passportData['extracted']['given_names'] ?? $passportData['given_names'] ?? '';

        return trim($surname . ' ' . $givenNames);
    }

    /**
     * Calcule la similarité entre deux noms
     */
    private function calculateNameSimilarity(string $name1, string $name2): float {
        $n1 = $this->normalizeName($name1);
        $n2 = $this->normalizeName($name2);

        if ($n1 === $n2) return 1.0;
        if (empty($n1) || empty($n2)) return 0.0;

        $maxLen = max(strlen($n1), strlen($n2));
        $distance = levenshtein($n1, $n2);

        return 1 - ($distance / $maxLen);
    }

    /**
     * Normalise un nom pour comparaison
     */
    private function normalizeName(string $name): string {
        $name = strtoupper($name);
        $name = preg_replace('/[^A-Z\s]/', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        return trim($name);
    }

    /**
     * Normalise un nom de pays
     */
    private function normalizeCountryName(string $country): string {
        $mapping = [
            'ETHIOPIA' => 'ETH', 'ETHIOPIE' => 'ETH', 'ETH' => 'ETH',
            'KENYA' => 'KEN', 'KEN' => 'KEN',
            'UGANDA' => 'UGA', 'OUGANDA' => 'UGA', 'UGA' => 'UGA',
            'DJIBOUTI' => 'DJI', 'DJI' => 'DJI',
            'SOMALIA' => 'SOM', 'SOMALIE' => 'SOM', 'SOM' => 'SOM',
            'SOUTH SUDAN' => 'SSD', 'SOUDAN DU SUD' => 'SSD', 'SSD' => 'SSD',
            'TANZANIA' => 'TZA', 'TANZANIE' => 'TZA', 'TZA' => 'TZA'
        ];

        $normalized = strtoupper(trim($country));
        return $mapping[$normalized] ?? $normalized;
    }

    /**
     * Normalise une date pour comparaison
     */
    private function normalizeDateForComparison(string $date): string {
        $timestamp = strtotime($date);
        return $timestamp ? date('Y-m-d', $timestamp) : $date;
    }

    public function getDocumentType(): string {
        return 'residence_card';
    }
}
