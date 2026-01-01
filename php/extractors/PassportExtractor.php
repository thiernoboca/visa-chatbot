<?php
/**
 * Extracteur de Passeport avec MRZ
 * Ambassade de Côte d'Ivoire en Éthiopie
 *
 * Conforme au PRD: DOC_PASSEPORT
 * Extrait MRZ (TD1/TD3) + Zone Visuelle (VIZ)
 * Cross-validation MRZ <-> VIZ
 *
 * @package VisaChatbot\Extractors
 * @version 1.0.1
 */

namespace VisaChatbot\Extractors;

require_once __DIR__ . '/AbstractExtractor.php';

class PassportExtractor extends AbstractExtractor {

    /**
     * Types de passeport supportés selon PRD
     */
    public const PASSPORT_TYPES = [
        'P' => 'ORDINAIRE',
        'PO' => 'ORDINAIRE',
        'PD' => 'DIPLOMATIQUE',
        'PS' => 'SERVICE',
        'PV' => 'SERVICE', // Parfois utilisé
        'PM' => 'SERVICE', // Mission
        'LP' => 'LAISSEZ_PASSER',
        'V' => 'LAISSEZ_PASSER',
        'D' => 'DIPLOMATIQUE',
        'S' => 'SERVICE'
    ];

    /**
     * Codes pays ICAO -> Nom pays
     */
    public const COUNTRY_CODES = [
        'ETH' => 'ETHIOPIE',
        'DJI' => 'DJIBOUTI',
        'ERI' => 'ERYTHREE',
        'KEN' => 'KENYA',
        'UGA' => 'OUGANDA',
        'SOM' => 'SOMALIE',
        'SSD' => 'SOUDAN DU SUD',
        'SDN' => 'SOUDAN',
        'CIV' => 'COTE D\'IVOIRE',
        'SEN' => 'SENEGAL',
        'MLI' => 'MALI',
        'BFA' => 'BURKINA FASO',
        'GHA' => 'GHANA',
        'NGA' => 'NIGERIA',
        'CMR' => 'CAMEROUN',
        'COD' => 'RD CONGO',
        'ZAF' => 'AFRIQUE DU SUD'
    ];

    protected array $requiredFields = [
        'passport_number',
        'surname',
        'given_names',
        'nationality',
        'date_of_birth',
        'expiry_date',
        'sex'
    ];

    protected array $optionalFields = [
        'place_of_birth',
        'issue_date',
        'issuing_authority',
        'personal_number'
    ];

    /**
     * Extrait les données du passeport
     */
    public function extract(string $rawText, array $ocrMetadata = []): array {
        $result = [
            'success' => false,
            'mrz' => null,
            'viz' => [],
            'passport_type' => 'UNKNOWN',
            'passport_type_confidence' => 0,
            'cross_validation' => []
        ];

        // 1. Extraire et parser MRZ
        $mrzLines = $this->extractMrzLines($rawText);
        if ($mrzLines) {
            $result['mrz'] = $this->parseMrz($mrzLines);
        }

        // 2. Extraire Zone Visuelle (VIZ)
        $result['viz'] = $this->extractViz($rawText);

        // 3. Déterminer le type de passeport
        $typeResult = $this->determinePassportType($result['mrz'], $result['viz'], $rawText);
        $result['passport_type'] = $typeResult['type'];
        $result['passport_type_confidence'] = $typeResult['confidence'];

        // 4. Cross-validation MRZ <-> VIZ
        if ($result['mrz'] && !empty($result['viz'])) {
            $result['cross_validation'] = $this->crossValidateMrzViz($result['mrz'], $result['viz']);
        }

        // 5. Fusionner les données (priorité MRZ si valide)
        $result['extracted'] = $this->mergeData($result['mrz'], $result['viz']);
        $result['extracted']['passport_type'] = $result['passport_type'];

        // 6. Succès si champs requis présents
        $result['success'] = $this->hasRequiredFields($result['extracted']);

        return $result;
    }

    /**
     * Parse la zone MRZ
     */
    private function parseMrz(array $mrzLines): array {
        $parsed = [
            'raw' => $mrzLines,
            'parsed' => [],
            'checksums_valid' => false
        ];

        if ($mrzLines['type'] === 'TD3') {
            // Passeport standard (2 lignes de 44 caractères)
            $parsed['parsed'] = $this->parseTd3($mrzLines['line1'], $mrzLines['line2']);
        } elseif ($mrzLines['type'] === 'TD1') {
            // Carte d'identité format (3 lignes de 30 caractères)
            $parsed['parsed'] = $this->parseTd1($mrzLines['line1'], $mrzLines['line2'], $mrzLines['line3']);
        }

        // Valider les checksums
        $parsed['checksums_valid'] = $this->validateMrzChecksums($parsed);

        return $parsed;
    }

    /**
     * Parse MRZ format TD3 (Passeport)
     * Ligne 1: P<CIVNOMPRENOM<<<<<<<<<<<<<<<<<<<<<<<<<<
     * Ligne 2: XXXXXXXXX<CIV9001011M3001011<<<<<<<<<<<4
     */
    private function parseTd3(string $line1, string $line2): array {
        $parsed = [];

        // Ligne 1: Type + Pays + Nom
        $parsed['document_type'] = substr($line1, 0, 1);
        $parsed['document_subtype'] = substr($line1, 1, 1);
        $parsed['issuing_country'] = substr($line1, 2, 3);

        // Nom: après le pays, séparé par <<
        $namePart = substr($line1, 5);
        $nameParts = explode('<<', $namePart);
        $parsed['surname'] = str_replace('<', ' ', trim($nameParts[0]));
        $parsed['given_names'] = isset($nameParts[1]) ? str_replace('<', ' ', trim($nameParts[1])) : '';

        // Ligne 2: Numéro + Nationalité + Date naissance + Sexe + Expiration + Numéro personnel
        $parsed['passport_number'] = rtrim(substr($line2, 0, 9), '<');
        $parsed['passport_number_check'] = substr($line2, 9, 1);
        $parsed['nationality'] = substr($line2, 10, 3);
        $parsed['date_of_birth'] = $this->mrzDateToIso(substr($line2, 13, 6), 'birth');
        $parsed['date_of_birth_check'] = substr($line2, 19, 1);
        $parsed['sex'] = substr($line2, 20, 1);
        $parsed['expiry_date'] = $this->mrzDateToIso(substr($line2, 21, 6), 'expiry');
        $parsed['expiry_date_check'] = substr($line2, 27, 1);
        $parsed['personal_number'] = rtrim(substr($line2, 28, 14), '<');
        $parsed['personal_number_check'] = substr($line2, 42, 1);
        $parsed['composite_check'] = substr($line2, 43, 1);

        return $parsed;
    }

    /**
     * Parse MRZ format TD1 (Carte d'identité / Titre de voyage)
     */
    private function parseTd1(string $line1, string $line2, string $line3): array {
        $parsed = [];

        // Ligne 1: Type + Pays + Numéro document
        $parsed['document_type'] = substr($line1, 0, 1);
        $parsed['document_subtype'] = substr($line1, 1, 1);
        $parsed['issuing_country'] = substr($line1, 2, 3);
        $parsed['passport_number'] = rtrim(substr($line1, 5, 9), '<');
        $parsed['passport_number_check'] = substr($line1, 14, 1);

        // Ligne 2: Date naissance + Sexe + Expiration + Nationalité
        $parsed['date_of_birth'] = $this->mrzDateToIso(substr($line2, 0, 6), 'birth');
        $parsed['date_of_birth_check'] = substr($line2, 6, 1);
        $parsed['sex'] = substr($line2, 7, 1);
        $parsed['expiry_date'] = $this->mrzDateToIso(substr($line2, 8, 6), 'expiry');
        $parsed['expiry_date_check'] = substr($line2, 14, 1);
        $parsed['nationality'] = substr($line2, 15, 3);

        // Ligne 3: Nom
        $nameParts = explode('<<', $line3);
        $parsed['surname'] = str_replace('<', ' ', trim($nameParts[0]));
        $parsed['given_names'] = isset($nameParts[1]) ? str_replace('<', ' ', trim($nameParts[1])) : '';

        return $parsed;
    }

    /**
     * Convertit une date MRZ (YYMMDD) en ISO (YYYY-MM-DD)
     *
     * @param string $mrzDate Date au format YYMMDD
     * @param string $context Contexte: 'birth' ou 'expiry' pour déterminer le siècle
     * @return string|null Date ISO ou null si invalide
     */
    private function mrzDateToIso(string $mrzDate, string $context = 'auto'): ?string {
        // Validation format
        if (strlen($mrzDate) !== 6 || !ctype_digit($mrzDate)) {
            return null;
        }

        $year = (int)substr($mrzDate, 0, 2);
        $month = substr($mrzDate, 2, 2);
        $day = substr($mrzDate, 4, 2);

        // Validation mois et jour
        if ((int)$month < 1 || (int)$month > 12 || (int)$day < 1 || (int)$day > 31) {
            return null;
        }

        // Déterminer le siècle selon le contexte
        $currentYear = (int)date('Y');
        $currentYearShort = (int)date('y');

        if ($context === 'birth') {
            // Date de naissance: toujours dans le passé
            // Si l'année > année courante, c'est 19XX
            $fullYear = ($year > $currentYearShort) ? 1900 + $year : 2000 + $year;

            // Vérification supplémentaire: pas de naissance dans le futur
            if ($fullYear > $currentYear) {
                $fullYear -= 100;
            }
        } elseif ($context === 'expiry') {
            // Date d'expiration: peut être futur (jusqu'à 10 ans typiquement)
            // Si année <= année courante + 10, c'est 20XX, sinon c'est passé donc 19XX
            $fullYear = 2000 + $year;

            // Si la date résultante est trop loin dans le futur (>15 ans), c'est probablement 19XX
            if ($fullYear > $currentYear + 15) {
                $fullYear = 1900 + $year;
            }
        } else {
            // Auto-détection: ancien comportement amélioré
            // Fenêtre glissante: années 00-29 = 2000-2029, 30-99 = 1930-1999
            // Mais adaptée à l'année courante
            $pivot = max(30, $currentYearShort + 10);
            $fullYear = ($year >= $pivot) ? 1900 + $year : 2000 + $year;
        }

        $isoDate = sprintf('%04d-%s-%s', $fullYear, $month, $day);

        // Validation finale de la date
        if (!$this->isValidDate($isoDate)) {
            return null;
        }

        return $isoDate;
    }

    /**
     * Vérifie si une date ISO est valide
     */
    private function isValidDate(string $date): bool {
        $parts = explode('-', $date);
        if (count($parts) !== 3) {
            return false;
        }

        return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
    }

    /**
     * Valide les checksums MRZ
     *
     * @param array $mrzData Données MRZ parsées
     * @return bool True si tous les checksums sont valides
     */
    private function validateMrzChecksums(array $mrzData): bool {
        try {
            $parsed = $mrzData['parsed'] ?? [];

            // Si pas de données parsées, considérer comme invalide
            if (empty($parsed)) {
                return false;
            }

            $allValid = true;
            $checksumResults = [];

            // Checksum numéro de passeport
            if (isset($parsed['passport_number'], $parsed['passport_number_check'])) {
                $check = $parsed['passport_number_check'];
                // Vérifier que le check digit est valide (0-9)
                if (!ctype_digit((string)$check) && $check !== '<') {
                    $checksumResults['passport_number'] = false;
                    $allValid = false;
                } else {
                    $calculated = $this->calculateMrzChecksum($parsed['passport_number']);
                    $expected = ($check === '<') ? 0 : (int)$check;
                    if ($calculated !== $expected) {
                        $checksumResults['passport_number'] = false;
                        $allValid = false;
                    } else {
                        $checksumResults['passport_number'] = true;
                    }
                }
            }

            // Checksum date de naissance
            if (isset($parsed['date_of_birth']) && $parsed['date_of_birth'] !== null) {
                if (isset($parsed['date_of_birth_check'])) {
                    $check = $parsed['date_of_birth_check'];
                    if (!ctype_digit((string)$check) && $check !== '<') {
                        $checksumResults['date_of_birth'] = false;
                        $allValid = false;
                    } else {
                        // Extraire YYMMDD de la date ISO
                        $dobMrz = str_replace('-', '', substr($parsed['date_of_birth'], 2));
                        $calculated = $this->calculateMrzChecksum($dobMrz);
                        $expected = ($check === '<') ? 0 : (int)$check;
                        if ($calculated !== $expected) {
                            $checksumResults['date_of_birth'] = false;
                            $allValid = false;
                        } else {
                            $checksumResults['date_of_birth'] = true;
                        }
                    }
                }
            }

            // Checksum date d'expiration
            if (isset($parsed['expiry_date']) && $parsed['expiry_date'] !== null) {
                if (isset($parsed['expiry_date_check'])) {
                    $check = $parsed['expiry_date_check'];
                    if (!ctype_digit((string)$check) && $check !== '<') {
                        $checksumResults['expiry_date'] = false;
                        $allValid = false;
                    } else {
                        $expMrz = str_replace('-', '', substr($parsed['expiry_date'], 2));
                        $calculated = $this->calculateMrzChecksum($expMrz);
                        $expected = ($check === '<') ? 0 : (int)$check;
                        if ($calculated !== $expected) {
                            $checksumResults['expiry_date'] = false;
                            $allValid = false;
                        } else {
                            $checksumResults['expiry_date'] = true;
                        }
                    }
                }
            }

            // Stocker les résultats détaillés pour le debugging
            $mrzData['checksum_details'] = $checksumResults;

            return $allValid;

        } catch (\Exception $e) {
            // En cas d'erreur, loguer et retourner false
            error_log("[PassportExtractor] MRZ checksum validation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Extrait les données de la Zone Visuelle (VIZ)
     */
    private function extractViz(string $rawText): array {
        $viz = [];
        $text = $this->cleanOcrText($rawText);

        // Patterns pour chaque champ VIZ
        $patterns = [
            'surname' => [
                '/(?:SURNAME|NOM|FAMILY\s*NAME)[:\.\s]*([A-Z\-\'\s]+)/i',
                '/(?:NOM DE FAMILLE)[:\.\s]*([A-Z\-\'\s]+)/i',
                // Ethiopian format: /Surname followed by name
                '/\/Surname[:\.\s]*([A-Z\-\'\s]+)/i'
            ],
            'given_names' => [
                '/(?:GIVEN\s*NAMES?|PRENOM|PRENOMS?|FIRST\s*NAME)[:\.\s]*([A-Z\-\'\s]+)/i',
                // Ethiopian format: /Given Name
                '/\/Given\s*Name[:\.\s]*([A-Z\-\'\s]+)/i'
            ],
            'date_of_birth' => [
                '/(?:DATE\s*OF\s*BIRTH|DATE\s*DE\s*NAISSANCE|DOB|BIRTH\s*DATE|NE\(E\)\s*LE)[:\.\s]*(\d{1,2}[\/\-\.\s]+(?:JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)[A-Z]*[\/\-\.\s]+\d{2,4})/i',
                '/(?:DATE\s*OF\s*BIRTH|DATE\s*DE\s*NAISSANCE|DOB|BIRTH\s*DATE|NE\(E\)\s*LE)[:\.\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
                '/(\d{2}[\/\-\.]\d{2}[\/\-\.]\d{4})\s*(?:DATE OF BIRTH|NAISSANCE)/i',
                // Ethiopian format: DD MMM YY (e.g., 22 AUG 95)
                '/\/Date\s*of\s*Birth[:\.\s]*(\d{1,2}\s+[A-Z]{3}\s+\d{2,4})/i'
            ],
            'place_of_birth' => [
                '/(?:PLACE\s*OF\s*BIRTH|LIEU\s*DE\s*NAISSANCE)[:\.\s]*([A-Z\-\'\s,]+)/i',
                '/\/Place\s*of\s*Birth[:\.\s]*([A-Z\-\'\s,]+)/i'
            ],
            'nationality' => [
                '/(?:NATIONALITY|NATIONALITE)[:\.\s]*([A-Z]+)/i'
            ],
            'passport_number' => [
                // Standard patterns with period/colon/space tolerance
                '/(?:PASSPORT\s*(?:NO|NUMBER|N[°o]?))[:\.\s]+([A-Z]{1,2}\d{6,9})/i',
                '/(?:PASSEPORT\s*(?:NO|N[°o]?))[:\.\s]+([A-Z]{1,2}\d{6,9})/i',
                // Ethiopian format: /Passport No. followed by number
                '/\/Passport\s*No\.?\s*([A-Z]{1,2}\d{6,9})/i',
                // Direct pattern for passport numbers (2 letters + 6-9 digits)
                '/\b([A-Z]{2}\d{7})\b/',
                // More flexible: 1-2 letters + 6-9 digits
                '/\b([A-Z]{1,2}\d{6,9})\b/',
                // Ethiopian specific: EQ followed by 7 digits
                '/\b(E[A-Z]\d{7})\b/i'
            ],
            'issue_date' => [
                '/(?:DATE\s*OF\s*ISSUE|DATE\s*(?:DE\s*)?DELIVRANCE|ISSUE\s*DATE)[:\.\s]*(\d{1,2}[\/\-\.\s]+(?:JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)[A-Z]*[\/\-\.\s]+\d{2,4})/i',
                '/(?:DATE\s*OF\s*ISSUE|DATE\s*(?:DE\s*)?DELIVRANCE|ISSUE\s*DATE)[:\.\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
                '/\/Date\s*of\s*Issue[:\.\s]*(\d{1,2}\s+[A-Z]{3}\s+\d{2,4})/i'
            ],
            'expiry_date' => [
                '/(?:DATE\s*OF\s*EXPIRY|EXPIRY\s*DATE|EXPIRES?|VALID\s*UNTIL|EXPIRATION)[:\.\s]*(\d{1,2}[\/\-\.\s]+(?:JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)[A-Z]*[\/\-\.\s]+\d{2,4})/i',
                '/(?:DATE\s*OF\s*EXPIRY|EXPIRY\s*DATE|EXPIRES?|VALID\s*UNTIL|EXPIRATION)[:\.\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
                '/\/Date\s*of\s*Expiry[:\.\s]*(\d{1,2}\s+[A-Z]{3}\s+\d{2,4})/i'
            ],
            'issuing_authority' => [
                '/(?:AUTHORITY|AUTORITE|ISSUING\s*AUTHORITY)[:\.\s]*([A-Z\s\-\.]+)/i',
                '/\/\s*Issuing\s*Authority[:\.\s]*([A-Z\s\-\.]+)/i'
            ],
            'sex' => [
                '/(?:SEX|SEXE)[:\.\s]*(M|F|MALE|FEMALE|MASCULIN|FEMININ)/i',
                '/\/Sex[:\.\s]*(M|F)/i'
            ]
        ];

        foreach ($patterns as $field => $fieldPatterns) {
            foreach ($fieldPatterns as $pattern) {
                if (preg_match($pattern, $text, $match)) {
                    $value = trim($match[1]);

                    // Normalisation spécifique par champ
                    switch ($field) {
                        case 'surname':
                        case 'given_names':
                            $value = $this->normalizeName($value);
                            break;
                        case 'date_of_birth':
                        case 'issue_date':
                        case 'expiry_date':
                            $value = $this->parseDate($value);
                            break;
                        case 'sex':
                            $value = strtoupper(substr($value, 0, 1));
                            break;
                    }

                    if (!empty($value)) {
                        $viz[$field] = $value;
                        break;
                    }
                }
            }
        }

        return $viz;
    }

    /**
     * Détermine le type de passeport
     */
    private function determinePassportType(?array $mrz, array $viz, string $rawText): array {
        $type = 'ORDINAIRE';
        $confidence = 0.5;
        $indicators = [];

        // 1. Depuis MRZ
        if ($mrz && isset($mrz['parsed']['document_type'])) {
            $docType = $mrz['parsed']['document_type'];
            $subType = $mrz['parsed']['document_subtype'] ?? '';
            $combined = $docType . $subType;

            if (isset(self::PASSPORT_TYPES[$combined])) {
                $type = self::PASSPORT_TYPES[$combined];
                $confidence = 0.95;
                $indicators[] = "MRZ type: {$combined}";
            } elseif (isset(self::PASSPORT_TYPES[$docType])) {
                $type = self::PASSPORT_TYPES[$docType];
                $confidence = 0.9;
                $indicators[] = "MRZ type: {$docType}";
            }
        }

        // 2. Mots-clés dans le texte
        $diplomaticKeywords = ['DIPLOMATIC', 'DIPLOMATIQUE', 'DIPLOMAT'];
        $serviceKeywords = ['SERVICE', 'OFFICIAL', 'OFFICIEL', 'MISSION'];
        $laissezPasserKeywords = ['LAISSEZ-PASSER', 'TRAVEL DOCUMENT', 'TITRE DE VOYAGE', 'UNITED NATIONS', 'UN ', 'ONU'];

        $textUpper = strtoupper($rawText);

        foreach ($diplomaticKeywords as $kw) {
            if (strpos($textUpper, $kw) !== false) {
                if ($type === 'ORDINAIRE') {
                    $type = 'DIPLOMATIQUE';
                    $confidence = max($confidence, 0.85);
                }
                $indicators[] = "Keyword found: {$kw}";
            }
        }

        foreach ($serviceKeywords as $kw) {
            if (strpos($textUpper, $kw) !== false && $type === 'ORDINAIRE') {
                $type = 'SERVICE';
                $confidence = max($confidence, 0.8);
                $indicators[] = "Keyword found: {$kw}";
            }
        }

        foreach ($laissezPasserKeywords as $kw) {
            if (strpos($textUpper, $kw) !== false) {
                $type = 'LAISSEZ_PASSER';
                $confidence = max($confidence, 0.9);
                $indicators[] = "Keyword found: {$kw}";
            }
        }

        return [
            'type' => $type,
            'confidence' => $confidence,
            'indicators' => $indicators
        ];
    }

    /**
     * Cross-validation MRZ <-> VIZ
     */
    private function crossValidateMrzViz(array $mrz, array $viz): array {
        $validations = [
            'matches' => [],
            'discrepancies' => [],
            'overall_match' => true
        ];

        $parsed = $mrz['parsed'] ?? [];

        // Champs à comparer
        $fieldsToCompare = [
            'surname' => 0.9,
            'given_names' => 0.85,
            'passport_number' => 1.0,
            'date_of_birth' => 1.0,
            'expiry_date' => 1.0,
            'sex' => 1.0
        ];

        foreach ($fieldsToCompare as $field => $threshold) {
            $mrzValue = $parsed[$field] ?? null;
            $vizValue = $viz[$field] ?? null;

            if ($mrzValue === null || $vizValue === null) {
                continue;
            }

            $similarity = $this->stringSimilarity((string)$mrzValue, (string)$vizValue);

            if ($similarity >= $threshold) {
                $validations['matches'][$field] = [
                    'mrz' => $mrzValue,
                    'viz' => $vizValue,
                    'similarity' => $similarity
                ];
            } else {
                $validations['discrepancies'][$field] = [
                    'mrz' => $mrzValue,
                    'viz' => $vizValue,
                    'similarity' => $similarity,
                    'expected_threshold' => $threshold
                ];
                $validations['overall_match'] = false;
            }
        }

        return $validations;
    }

    /**
     * Fusionne les données MRZ et VIZ (priorité MRZ)
     */
    private function mergeData(?array $mrz, array $viz): array {
        $merged = [];
        $parsed = $mrz['parsed'] ?? [];

        // Priorité aux données MRZ si disponibles
        $fields = ['surname', 'given_names', 'passport_number', 'nationality',
                   'date_of_birth', 'expiry_date', 'sex', 'personal_number',
                   'issuing_country'];

        foreach ($fields as $field) {
            if (!empty($parsed[$field])) {
                $merged[$field] = $parsed[$field];
            } elseif (!empty($viz[$field])) {
                $merged[$field] = $viz[$field];
            }
        }

        // Champs VIZ uniquement
        $vizOnlyFields = ['place_of_birth', 'issue_date', 'issuing_authority'];
        foreach ($vizOnlyFields as $field) {
            if (!empty($viz[$field])) {
                $merged[$field] = $viz[$field];
            }
        }

        // Enrichir avec le nom du pays
        if (isset($merged['nationality']) && isset(self::COUNTRY_CODES[$merged['nationality']])) {
            $merged['nationality_name'] = self::COUNTRY_CODES[$merged['nationality']];
        }

        return $merged;
    }

    /**
     * Vérifie si les champs requis sont présents
     */
    private function hasRequiredFields(array $data): bool {
        foreach ($this->requiredFields as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Valide les données extraites
     */
    public function validate(array $extracted): array {
        $validations = [];

        // Validation expiration +6 mois
        if (isset($extracted['expiry_date'])) {
            $validations['expiry_valid'] = $this->isFutureDate($extracted['expiry_date']);
            $validations['expiry_6months'] = $this->isExpiryValid($extracted['expiry_date'], 6);
        }

        // Validation nationalité dans circonscription
        if (isset($extracted['nationality'])) {
            $validations['in_jurisdiction'] = $this->isInJurisdiction(
                $extracted['nationality_name'] ?? $extracted['nationality']
            );
        }

        // Validation MRZ checksums
        if (isset($extracted['mrz_checksums_valid'])) {
            $validations['mrz_valid'] = $extracted['mrz_checksums_valid'];
        }

        // Validation format numéro passeport
        if (isset($extracted['passport_number'])) {
            $validations['passport_number_format'] = preg_match('/^[A-Z]{1,2}\d{6,9}$/', $extracted['passport_number']);
        }

        return $validations;
    }

    public function getDocumentType(): string {
        return 'passport';
    }

    public function getPrdCode(): string {
        return 'DOC_PASSEPORT';
    }
}
