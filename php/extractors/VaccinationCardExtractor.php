<?php
/**
 * Extracteur de Carnet de Vaccination
 * Ambassade de Côte d'Ivoire en Éthiopie
 *
 * Conforme au PRD: DOC_VACCINATION
 * Focus sur la fièvre jaune (OBLIGATOIRE pour CI)
 *
 * @package VisaChatbot\Extractors
 * @version 1.0.0
 */

namespace VisaChatbot\Extractors;

require_once __DIR__ . '/AbstractExtractor.php';

class VaccinationCardExtractor extends AbstractExtractor {

    /**
     * Vaccins reconnus
     */
    public const VACCINES = [
        'YELLOW_FEVER' => [
            'names' => [
                // Standard names
                'YELLOW FEVER', 'FIEVRE JAUNE', 'FIÈVRE JAUNE', 'AMARIL', 'ANTI-AMARIL',
                // Vaccine brands
                'STAMARIL', 'YF-VAX', '17D-204', '17D',
                // OCR error variants (common misreads)
                'YELLOW FAVER', 'YELOW FEVER', 'YELL0W FEVER', 'YELLOW F3VER',
                'FIEVRE JUNE', 'FIEVRE JAUN', 'FI3VRE JAUNE',
                // Abbreviations
                'YF', 'YF VAX', 'Y.F.', 'Y F',
                // International Certificate format
                'INTERNATIONAL CERTIFICATE', 'ICV', 'YELLOW CARD',
                // French variants
                'VACCINATION ANTI-AMARILE', 'VACCIN AMARIL', 'ANTI AMARIL'
            ],
            'required' => true,
            'validity_years' => 'LIFETIME' // Depuis 2016, validité à vie
        ],
        'COVID19' => [
            'names' => ['COVID', 'SARS-COV-2', 'CORONAVIRUS', 'PFIZER', 'MODERNA', 'ASTRAZENECA', 'JANSSEN', 'JOHNSON', 'SINOPHARM', 'SINOVAC'],
            'required' => false,
            'validity_years' => 1
        ],
        'POLIO' => [
            'names' => ['POLIO', 'POLIOMYELITE', 'IPV', 'OPV'],
            'required' => false,
            'validity_years' => 10
        ],
        'MENINGITIS' => [
            'names' => ['MENINGITE', 'MENINGOCOCCAL', 'ACWY', 'MCV4'],
            'required' => false,
            'validity_years' => 5
        ]
    ];

    protected array $requiredFields = [
        'holder_name',
        'yellow_fever_date'
    ];

    protected array $optionalFields = [
        'date_of_birth',
        'certificate_number',
        'vaccination_center',
        'batch_number',
        'administering_physician',
        'other_vaccinations'
    ];

    /**
     * Extrait les données du carnet de vaccination
     */
    public function extract(string $rawText, array $ocrMetadata = []): array {
        $result = [
            'success' => false,
            'extracted' => [],
            'vaccinations' => [],
            'yellow_fever' => null
        ];

        $text = $this->cleanOcrText($rawText);

        // 1. Extraire le nom du titulaire
        $result['extracted']['holder_name'] = $this->extractHolderName($text);

        // 2. Extraire la date de naissance
        $result['extracted']['date_of_birth'] = $this->extractDateOfBirth($text);

        // 3. Extraire le numéro de certificat
        $result['extracted']['certificate_number'] = $this->extractCertificateNumber($text);

        // 4. Extraire toutes les vaccinations
        $result['vaccinations'] = $this->extractAllVaccinations($text);

        // 5. Identifier spécifiquement la fièvre jaune
        $result['yellow_fever'] = $this->findYellowFeverVaccination($result['vaccinations'], $text);

        if ($result['yellow_fever']) {
            $result['extracted']['yellow_fever_date'] = $result['yellow_fever']['vaccination_date'];
            $result['extracted']['yellow_fever_valid_from'] = $result['yellow_fever']['valid_from'];
            $result['extracted']['yellow_fever_valid_until'] = $result['yellow_fever']['valid_until'];
            $result['extracted']['vaccination_center'] = $result['yellow_fever']['center'] ?? null;
            $result['extracted']['batch_number'] = $result['yellow_fever']['batch_number'] ?? null;
        }

        // 6. Succès si fièvre jaune détectée
        $result['success'] = $result['yellow_fever'] !== null;

        return $result;
    }

    /**
     * Extrait le nom du titulaire
     */
    private function extractHolderName(string $text): ?string {
        // Nettoyer le texte - remplacer les retours à la ligne par des espaces pour la détection
        $cleanText = preg_replace('/\r?\n/', ' ', $text);

        $patterns = [
            // Pattern avec limite explicite pour éviter de capturer d'autres champs
            '/(?:NAME|NOM)\s*(?:\/\s*(?:NOM|NAME))?\s*:\s*([A-Z][A-Z\-\'\s]+?)(?=\s+(?:DATE|DOB|BIRTH|SEX|GENDER|NATIONALITY|COVID|YELLOW|HEPATITIS|\d{1,2}[\/\-]))/i',
            '/(?:SURNAME|FAMILY\s*NAME)[:\s]*([A-Z]+)[,\s]+(?:GIVEN\s*NAME|PRENOM)[:\s]*([A-Z]+)/i',
            '/(?:HOLDER|TITULAIRE)[:\s]*([A-Z][A-Z\-\'\s]+?)(?=\s+(?:DATE|DOB|BIRTH|SEX|\d))/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $cleanText, $match)) {
                $name = trim($match[1]);
                if (isset($match[2])) {
                    $name .= ' ' . trim($match[2]);
                }
                // Nettoyer: enlever les mots-clés parasites à la fin
                $name = preg_replace('/\s*(DATE|DOB|BIRTH|SEX|GENDER|NATIONALITY|OF|DE|NAISSANCE)\s*$/i', '', $name);
                $name = preg_replace('/\s*(DATE|DOB|BIRTH|SEX|GENDER|NATIONALITY|OF|DE|NAISSANCE)\s*$/i', '', $name); // Double pass
                $name = trim($name);
                if (!empty($name) && strlen($name) > 3) {
                    return $this->normalizeName($name);
                }
            }
        }

        // Fallback: chercher sur la ligne après "Name:"
        if (preg_match('/(?:NAME|NOM)\s*(?:\/[^:]+)?:\s*([A-Z][A-Z\s\-\']+)/i', $text, $match)) {
            $name = trim($match[1]);
            // Prendre seulement la première ligne (avant newline)
            $nameParts = preg_split('/\s*[\r\n]+\s*/', $name);
            $name = trim($nameParts[0]);
            // Nettoyer les mots-clés
            $name = preg_replace('/\s*(DATE|DOB|BIRTH|SEX|OF|DE)\s*$/i', '', $name);
            $name = trim($name);
            if (!empty($name) && strlen($name) > 3) {
                return $this->normalizeName($name);
            }
        }

        return null;
    }

    /**
     * Extrait la date de naissance
     */
    private function extractDateOfBirth(string $text): ?string {
        $patterns = [
            '/(?:DATE\s*OF\s*BIRTH|DATE\s*DE\s*NAISSANCE|DOB|BIRTH|NE\(E\)\s*LE)[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return $this->parseDate($match[1]);
            }
        }

        return null;
    }

    /**
     * Extrait le numéro de certificat international
     */
    private function extractCertificateNumber(string $text): ?string {
        $patterns = [
            '/(?:CERTIFICATE|CERTIFICAT)\s*(?:NO|N°|NUMBER)?[:\s]*([A-Z0-9\-\/]+)/i',
            '/(?:ICV|YELLOW\s*CARD)\s*(?:NO|N°)?[:\s]*([A-Z0-9\-]+)/i',
            '/\b([A-Z]{2,3}[\-\/]?\d{6,10})\b/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return strtoupper($match[1]);
            }
        }

        return null;
    }

    /**
     * Extrait toutes les vaccinations mentionnées
     */
    private function extractAllVaccinations(string $text): array {
        $vaccinations = [];

        foreach (self::VACCINES as $code => $vaccineInfo) {
            foreach ($vaccineInfo['names'] as $name) {
                if (stripos($text, $name) !== false) {
                    $vaccination = [
                        'type' => $code,
                        'name' => $name,
                        'detected' => true,
                        'required' => $vaccineInfo['required'],
                        'validity_years' => $vaccineInfo['validity_years']
                    ];

                    // Chercher la date de vaccination à proximité
                    $pattern = '/' . preg_quote($name, '/') . '[^0-9]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i';
                    if (preg_match($pattern, $text, $dateMatch)) {
                        $vaccination['vaccination_date'] = $this->parseDate($dateMatch[1]);
                    }

                    // Chercher le numéro de lot
                    $batchPattern = '/' . preg_quote($name, '/') . '[^A-Z0-9]*(?:LOT|BATCH)[:\s#]*([A-Z0-9\-]+)/i';
                    if (preg_match($batchPattern, $text, $batchMatch)) {
                        $vaccination['batch_number'] = strtoupper($batchMatch[1]);
                    }

                    $vaccinations[$code] = $vaccination;
                    break;
                }
            }
        }

        return $vaccinations;
    }

    /**
     * Trouve spécifiquement la vaccination fièvre jaune
     */
    private function findYellowFeverVaccination(array $vaccinations, string $text): ?array {
        // Si déjà trouvée dans les vaccinations
        if (isset($vaccinations['YELLOW_FEVER'])) {
            $yf = $vaccinations['YELLOW_FEVER'];

            // Compléter avec les informations de validité
            if (!isset($yf['vaccination_date'])) {
                $yf['vaccination_date'] = $this->findYellowFeverDate($text);
            }

            if ($yf['vaccination_date']) {
                $yf['valid_from'] = $this->calculateValidFrom($yf['vaccination_date']);
                $yf['valid_until'] = 'LIFETIME'; // Depuis 2016
            }

            // Chercher le centre de vaccination
            $yf['center'] = $this->extractVaccinationCenter($text);

            return $yf;
        }

        // Recherche explicite avec patterns étendus (inclut erreurs OCR)
        $yellowFeverPatterns = [
            // Standard
            'YELLOW FEVER', 'FIEVRE JAUNE', 'FIÈVRE JAUNE', 'AMARIL',
            'ANTI-AMARIL', 'ANTIAMARIL', 'YF-VAX', '17D-204', 'STAMARIL',
            // OCR errors
            'YELLOW FAVER', 'YELOW FEVER', 'YELL0W', 'FIEVRE JUNE',
            // International certificate indicators
            'INTERNATIONAL CERTIFICATE OF VACCINATION',
            'CERTIFICAT INTERNATIONAL DE VACCINATION',
            'ICV YELLOW', 'YELLOW CARD'
        ];

        foreach ($yellowFeverPatterns as $pattern) {
            if (stripos($text, $pattern) !== false) {
                return $this->buildYellowFeverResult($pattern, $text);
            }
        }

        // Recherche par regex fuzzy pour erreurs OCR plus complexes
        $fuzzyResult = $this->fuzzyYellowFeverSearch($text);
        if ($fuzzyResult) {
            return $fuzzyResult;
        }

        return null;
    }

    /**
     * Recherche floue pour Yellow Fever (gère erreurs OCR)
     */
    private function fuzzyYellowFeverSearch(string $text): ?array {
        // Patterns regex qui tolèrent les erreurs OCR
        $fuzzyPatterns = [
            // YELLOW FEVER avec substitutions communes (O→0, E→3, etc.)
            '/Y[E3]LL[O0]W\s*F[E3][AV][E3]R/i',
            // FIEVRE JAUNE avec substitutions
            '/FI[E3É]VR[E3]\s*J[AU][UN][E3]/i',
            // AMARIL avec variations
            '/A?N?T?I?[\-\s]?AM[AE]R[I1]L/i',
            // Pattern générique certificat vaccination + fever/jaune dans le même bloc
            '/(?:VACCIN|CERTIFICATE|CERTIFICAT)[\s\S]{0,50}(?:FEV[AE]R|JAUNE|AMARIL)/i',
            // International certificate format
            '/INTERNATIONAL[\s\S]{0,20}CERTIFICAT?E?[\s\S]{0,30}(?:VACCIN|IMMUNIZ)/i'
        ];

        foreach ($fuzzyPatterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return $this->buildYellowFeverResult($match[0], $text, 'fuzzy_match');
            }
        }

        return null;
    }

    /**
     * Construit le résultat Yellow Fever
     */
    private function buildYellowFeverResult(string $matchedPattern, string $text, string $method = 'exact_match'): array {
        $vaccinationDate = $this->findYellowFeverDate($text);

        return [
            'type' => 'YELLOW_FEVER',
            'name' => $matchedPattern,
            'detected' => true,
            'required' => true,
            'vaccination_date' => $vaccinationDate,
            'valid_from' => $vaccinationDate ? $this->calculateValidFrom($vaccinationDate) : null,
            'valid_until' => 'LIFETIME',
            'center' => $this->extractVaccinationCenter($text),
            'detection_method' => $method
        ];
    }

    /**
     * Cherche la date de vaccination fièvre jaune
     *
     * @param string $text Texte OCR à analyser
     * @return string|null Date ISO ou null
     */
    private function findYellowFeverDate(string $text): ?string {
        // Patterns spécifiques à la fièvre jaune (haute confiance)
        // Inclut les erreurs OCR communes
        $highConfidencePatterns = [
            // Standard patterns
            '/(?:YELLOW\s*FE[AV]ER|FIEVRE\s*JAUNE|FIÈVRE\s*JAUNE|AMARIL)[^0-9]{0,30}(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
            '/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})[^A-Z0-9]{0,10}(?:YELLOW|JAUNE|AMARIL)/i',
            '/(?:ANTI[\-\s]?AMARIL|YF[\-\s]?VAX|17D|STAMARIL)[^0-9]{0,20}(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
            // OCR error patterns (FAVER, YELL0W, etc.)
            '/(?:YELL[O0]W\s*FAV[E3]R|FI[E3]VR[E3]\s*JA?UN[E3]?)[^0-9]{0,30}(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
            // Date with month names (English)
            '/(?:YELLOW|JAUNE|AMARIL)[^0-9]{0,30}(\d{1,2})\s*(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)[A-Z]*\s*(\d{2,4})/i',
            // Date with month names (French)
            '/(?:YELLOW|JAUNE|AMARIL)[^0-9]{0,30}(\d{1,2})\s*(JANV?|FEVR?|MARS?|AVR|MAI|JUIN|JUIL|AOUT|SEPT?|OCT|NOV|DEC)[A-Z]*\s*(\d{2,4})/i'
        ];

        foreach ($highConfidencePatterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                // Handle date with month names
                if (isset($match[3])) {
                    $date = $this->parseDateWithMonthName($match[1], $match[2], $match[3]);
                } else {
                    $date = $this->parseDate($match[1]);
                }
                if ($date) {
                    $this->lastDateExtractionMethod = 'KEYWORD_MATCH';
                    $this->lastDateExtractionConfidence = 0.95;
                    return $date;
                }
            }
        }

        // Patterns de contexte moyen (section vaccination avec date)
        $mediumConfidencePatterns = [
            '/(?:VACCINATION|VACCIN|DATE\s*(?:OF\s*)?VACCINATION)[^0-9]{0,20}(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
            '/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})[^A-Z0-9]{0,15}(?:VACCINATION|VACCIN)/i',
            // Date de validité (valid from/until)
            '/(?:VALID\s*(?:FROM|UNTIL)?|VALIDE\s*(?:A PARTIR|JUSQU))[^0-9]{0,10}(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
            // ISO format
            '/(?:VACCINATION|IMMUNIZATION)[^0-9]{0,30}(\d{4}[\-\/]\d{2}[\-\/]\d{2})/i'
        ];

        foreach ($mediumConfidencePatterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $date = $this->parseDate($match[1]);
                if ($date) {
                    $this->lastDateExtractionMethod = 'CONTEXT_MATCH';
                    $this->lastDateExtractionConfidence = 0.75;
                    return $date;
                }
            }
        }

        // Fallback: chercher une date dans un contexte de certificat
        // (moins fiable mais mieux que rien)
        if (preg_match('/(?:CERTIFICATE|CERTIFICAT)[^0-9]{0,50}(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i', $text, $match)) {
            $date = $this->parseDate($match[1]);
            if ($date && $this->isReasonableVaccinationDate($date)) {
                $this->lastDateExtractionMethod = 'CERTIFICATE_FALLBACK';
                $this->lastDateExtractionConfidence = 0.60;
                return $date;
            }
        }

        // Dernier recours: première date trouvée si contexte vaccination clair
        if ($this->hasVaccinationContext($text)) {
            if (preg_match('/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/', $text, $match)) {
                $date = $this->parseDate($match[1]);
                if ($date && $this->isReasonableVaccinationDate($date)) {
                    $this->lastDateExtractionMethod = 'FIRST_DATE_FALLBACK';
                    $this->lastDateExtractionConfidence = 0.50;
                    return $date;
                }
            }
        }

        // Aucune date trouvée
        $this->addWarning('YELLOW_FEVER_DATE_NOT_FOUND',
            'Could not find a date specifically associated with Yellow Fever vaccination'
        );

        return null;
    }

    /**
     * Parse une date avec nom de mois
     */
    private function parseDateWithMonthName(string $day, string $month, string $year): ?string {
        $monthMap = [
            'JAN' => '01', 'JANV' => '01',
            'FEB' => '02', 'FEVR' => '02', 'FEV' => '02',
            'MAR' => '03', 'MARS' => '03',
            'APR' => '04', 'AVR' => '04',
            'MAY' => '05', 'MAI' => '05',
            'JUN' => '06', 'JUIN' => '06',
            'JUL' => '07', 'JUIL' => '07',
            'AUG' => '08', 'AOUT' => '08',
            'SEP' => '09', 'SEPT' => '09',
            'OCT' => '10',
            'NOV' => '11',
            'DEC' => '12'
        ];

        $monthUpper = strtoupper(substr($month, 0, 4));
        $monthNum = null;
        foreach ($monthMap as $key => $val) {
            if (strpos($monthUpper, $key) === 0) {
                $monthNum = $val;
                break;
            }
        }

        if (!$monthNum) return null;

        $yearNum = strlen($year) === 2 ? '20' . $year : $year;
        $dayNum = str_pad($day, 2, '0', STR_PAD_LEFT);

        return "{$yearNum}-{$monthNum}-{$dayNum}";
    }

    /**
     * Vérifie si une date est raisonnable pour une vaccination
     */
    private function isReasonableVaccinationDate(string $date): bool {
        $timestamp = strtotime($date);
        if ($timestamp === false) return false;

        $now = time();
        $minDate = strtotime('1970-01-01'); // Vaccins modernes
        $maxDate = $now; // Pas dans le futur

        return $timestamp >= $minDate && $timestamp <= $maxDate;
    }

    /**
     * Vérifie si le texte a un contexte de vaccination clair
     */
    private function hasVaccinationContext(string $text): bool {
        $indicators = [
            'VACCIN', 'IMMUNIZ', 'INOCUL',
            'YELLOW', 'JAUNE', 'AMARIL',
            'CERTIFICATE', 'CERTIFICAT',
            'INTERNATIONAL HEALTH',
            'WORLD HEALTH', 'OMS', 'WHO'
        ];

        $count = 0;
        foreach ($indicators as $indicator) {
            if (stripos($text, $indicator) !== false) {
                $count++;
            }
        }

        return $count >= 2; // Au moins 2 indicateurs
    }

    /**
     * Méthode et confiance de la dernière extraction de date
     */
    private string $lastDateExtractionMethod = '';
    private float $lastDateExtractionConfidence = 0.0;

    /**
     * Retourne les informations sur la dernière extraction de date
     */
    public function getLastDateExtractionInfo(): array {
        return [
            'method' => $this->lastDateExtractionMethod,
            'confidence' => $this->lastDateExtractionConfidence
        ];
    }

    /**
     * Ajoute un warning
     */
    private function addWarning(string $code, string $message): void {
        if (!isset($this->warnings)) {
            $this->warnings = [];
        }
        $this->warnings[] = [
            'code' => $code,
            'message' => $message,
            'timestamp' => date('c')
        ];
    }

    /**
     * Retourne les warnings
     */
    public function getWarnings(): array {
        return $this->warnings ?? [];
    }

    /**
     * Tableau des warnings
     */
    private array $warnings = [];

    /**
     * Fenêtre de validité en jours (10 jours selon OMS)
     */
    private int $validityWindowDays = 10;

    /**
     * Configure la fenêtre de validité
     */
    public function setValidityWindow(int $days): void {
        $this->validityWindowDays = $days;
    }

    /**
     * Calcule la date de début de validité (10 jours après vaccination par défaut)
     *
     * @param string $vaccinationDate Date de vaccination
     * @param int|null $windowDays Nombre de jours (null = utiliser la config)
     * @return string|null Date ISO de début de validité
     */
    private function calculateValidFrom(string $vaccinationDate, ?int $windowDays = null): ?string {
        $date = strtotime($vaccinationDate);
        if ($date === false) {
            return null;
        }

        $days = $windowDays ?? $this->validityWindowDays;
        return date('Y-m-d', strtotime("+{$days} days", $date));
    }

    /**
     * Extrait le centre de vaccination
     */
    private function extractVaccinationCenter(string $text): ?string {
        $patterns = [
            '/(?:CENTER|CENTRE|CLINIC|CLINIQUE|HOSPITAL|HOPITAL)[:\s]*([A-Z][A-Za-z\s\-\.]+)/i',
            '/(?:ADMINISTERING|ADMINISTERED\s*BY|VACCINATED\s*AT)[:\s]*([A-Z][A-Za-z\s\-\.]+)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return trim($match[1]);
            }
        }

        return null;
    }

    /**
     * Valide les données extraites
     */
    public function validate(array $extracted): array {
        $validations = [];

        // Validation présence fièvre jaune
        $validations['yellow_fever_present'] = !empty($extracted['yellow_fever_date']);

        // Validation date de vaccination
        if (isset($extracted['yellow_fever_date'])) {
            $vacDate = strtotime($extracted['yellow_fever_date']);

            // La vaccination doit être dans le passé
            $validations['vaccination_date_past'] = $vacDate !== false && $vacDate < time();

            // La période de validité (configurable, défaut 10 jours) doit être passée
            $validFromDate = $this->calculateValidFrom($extracted['yellow_fever_date']);
            if ($validFromDate) {
                $validFrom = strtotime($validFromDate);
                $validations['yellow_fever_valid'] = $validFrom !== false && $validFrom < time();
                $validations['validity_window_days'] = $this->validityWindowDays;
                $validations['valid_from_date'] = $validFromDate;

                // Jours restants avant validité (si pas encore valide)
                if ($validFrom > time()) {
                    $daysRemaining = ceil(($validFrom - time()) / 86400);
                    $validations['days_until_valid'] = (int)$daysRemaining;
                }
            } else {
                $validations['yellow_fever_valid'] = false;
            }
        }

        // Validation format certificat
        if (isset($extracted['certificate_number'])) {
            $validations['certificate_format_valid'] = strlen($extracted['certificate_number']) >= 6;
        }

        // Validation authenticité (indicateurs)
        $validations['certificate_authentic_indicators'] = true; // Par défaut, Claude Layer 3 vérifiera

        return $validations;
    }

    public function getDocumentType(): string {
        return 'vaccination';
    }

    public function getPrdCode(): string {
        return 'DOC_VACCINATION';
    }
}
