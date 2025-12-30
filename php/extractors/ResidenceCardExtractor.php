<?php
/**
 * Extracteur de Carte de Séjour/Résidence
 * Ambassade de Côte d'Ivoire en Éthiopie
 *
 * Conforme au PRD: DOC_CARTE_RESIDENT
 * Requis pour les résidents non-nationaux dans un pays de la circonscription
 *
 * @package VisaChatbot\Extractors
 * @version 1.0.0
 */

namespace VisaChatbot\Extractors;

require_once __DIR__ . '/AbstractExtractor.php';

class ResidenceCardExtractor extends AbstractExtractor {

    /**
     * Types de permis de résidence
     */
    public const RESIDENCE_TYPES = [
        'WORK' => ['WORK PERMIT', 'PERMIS DE TRAVAIL', 'TRAVAIL', 'EMPLOYMENT', 'EMPLOI'],
        'STUDY' => ['STUDENT', 'ETUDIANT', 'STUDIES', 'ETUDES', 'ACADEMIC'],
        'FAMILY' => ['FAMILY', 'FAMILLE', 'DEPENDANT', 'SPOUSE', 'REGROUPEMENT'],
        'REFUGEE' => ['REFUGEE', 'REFUGIE', 'ASYLUM', 'ASILE', 'UNHCR', 'HCR'],
        'PERMANENT' => ['PERMANENT', 'INDEFINITE', 'LONG TERM', 'RESIDENT CARD'],
        'DIPLOMATIC' => ['DIPLOMATIC', 'DIPLOMATIQUE', 'OFFICIAL', 'MISSION']
    ];

    /**
     * Pays de la circonscription
     */
    public const JURISDICTION_COUNTRIES = [
        'ETHIOPIA' => ['ETHIOPIA', 'ETHIOPIE', 'ETH', 'FEDERAL DEMOCRATIC REPUBLIC'],
        'DJIBOUTI' => ['DJIBOUTI', 'DJI', 'REPUBLIQUE DE DJIBOUTI'],
        'ERITREA' => ['ERITREA', 'ERYTHREE', 'ERI', 'STATE OF ERITREA'],
        'KENYA' => ['KENYA', 'KEN', 'REPUBLIC OF KENYA'],
        'UGANDA' => ['UGANDA', 'OUGANDA', 'UGA', 'REPUBLIC OF UGANDA'],
        'SOMALIA' => ['SOMALIA', 'SOMALIE', 'SOM', 'FEDERAL REPUBLIC OF SOMALIA'],
        'SOUTH_SUDAN' => ['SOUTH SUDAN', 'SOUDAN DU SUD', 'SSD', 'REPUBLIC OF SOUTH SUDAN']
    ];

    protected array $requiredFields = [
        'holder_name',
        'card_number',
        'expiry_date'
    ];

    protected array $optionalFields = [
        'nationality',
        'date_of_birth',
        'issue_date',
        'issuing_country',
        'residence_type',
        'employer',
        'address',
        'photo_present'
    ];

    /**
     * Extrait les données de la carte de résidence
     */
    public function extract(string $rawText, array $ocrMetadata = []): array {
        $result = [
            'success' => false,
            'extracted' => [],
            'card_type_detected' => null
        ];

        $text = $this->cleanOcrText($rawText);

        // 1. Extraire le nom du titulaire
        $result['extracted']['holder_name'] = $this->extractHolderName($text);

        // 2. Extraire le numéro de carte
        $result['extracted']['card_number'] = $this->extractCardNumber($text);

        // 3. Extraire la nationalité
        $result['extracted']['nationality'] = $this->extractNationality($text);

        // 4. Extraire la date de naissance
        $result['extracted']['date_of_birth'] = $this->extractDateOfBirth($text);

        // 5. Extraire les dates de validité
        $dates = $this->extractValidityDates($text);
        $result['extracted']['issue_date'] = $dates['issue_date'] ?? null;
        $result['extracted']['expiry_date'] = $dates['expiry_date'] ?? null;

        // 6. Identifier le pays émetteur
        $result['extracted']['issuing_country'] = $this->identifyIssuingCountry($text);

        // 7. Identifier le type de résidence
        $result['extracted']['residence_type'] = $this->identifyResidenceType($text);
        $result['card_type_detected'] = $result['extracted']['residence_type'];

        // 8. Extraire l'employeur (si permis de travail)
        $result['extracted']['employer'] = $this->extractEmployer($text);

        // 9. Extraire l'adresse
        $result['extracted']['address'] = $this->extractAddress($text);

        // 10. Indicateurs de photo
        $result['extracted']['photo_present'] = $this->detectPhotoIndicators($text, $ocrMetadata);

        // 11. Succès si champs requis présents
        $result['success'] = $this->hasRequiredFields($result['extracted']);

        return $result;
    }

    /**
     * Extrait le nom du titulaire
     */
    private function extractHolderName(string $text): ?string {
        $patterns = [
            '/(?:NAME|NOM|HOLDER|TITULAIRE)[:\s]*([A-Z][A-Z\-\'\s]+)/i',
            '/(?:SURNAME|FAMILY\s*NAME)[:\s]*([A-Z]+)[,\s]+(?:GIVEN|FIRST|PRENOM)[:\s]*([A-Z]+)/i',
            '/(?:FULL\s*NAME)[:\s]*([A-Z][A-Z\-\'\s]+)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $name = $match[1];
                if (isset($match[2])) {
                    $name .= ' ' . $match[2];
                }
                return $this->normalizeName(trim($name));
            }
        }

        return null;
    }

    /**
     * Extrait le numéro de carte
     */
    private function extractCardNumber(string $text): ?string {
        $patterns = [
            '/(?:CARD|CARTE|PERMIT|PERMIS)\s*(?:NO|N°|NUMBER)?[:\s]*([A-Z0-9\-\/]+)/i',
            '/(?:RESIDENCE|RESIDENT)\s*(?:NO|N°)?[:\s]*([A-Z0-9\-]+)/i',
            '/(?:ID|IDENTIFICATION)\s*(?:NO|N°)?[:\s]*([A-Z0-9\-]+)/i',
            // Format typique éthiopien
            '/\b(RP[\/\-]?\d{6,})\b/i',
            // Format générique
            '/\b([A-Z]{2}\d{6,10})\b/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return strtoupper(trim($match[1]));
            }
        }

        return null;
    }

    /**
     * Extrait la nationalité
     */
    private function extractNationality(string $text): ?string {
        $patterns = [
            '/(?:NATIONALITY|NATIONALITE)[:\s]*([A-Z][A-Za-z\s]+)/i',
            '/(?:CITIZEN\s*OF|RESSORTISSANT)[:\s]*([A-Z][A-Za-z\s]+)/i',
            '/(?:COUNTRY\s*OF\s*ORIGIN)[:\s]*([A-Z][A-Za-z\s]+)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return strtoupper(trim($match[1]));
            }
        }

        return null;
    }

    /**
     * Extrait la date de naissance
     */
    private function extractDateOfBirth(string $text): ?string {
        $patterns = [
            '/(?:DATE\s*OF\s*BIRTH|DOB|BIRTH|NAISSANCE)[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
            '/(?:BORN|NE\(E\))[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return $this->parseDate($match[1]);
            }
        }

        return null;
    }

    /**
     * Extrait les dates de validité
     */
    private function extractValidityDates(string $text): array {
        $dates = ['issue_date' => null, 'expiry_date' => null];

        // Date d'émission
        $issuePatterns = [
            '/(?:ISSUE\s*DATE|DATE\s*(?:OF\s*)?ISSUE|DELIVRANCE|EMIS\s*LE)[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
            '/(?:VALID\s*FROM|VALABLE\s*DU)[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i'
        ];

        foreach ($issuePatterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $dates['issue_date'] = $this->parseDate($match[1]);
                break;
            }
        }

        // Date d'expiration
        $expiryPatterns = [
            '/(?:EXPIRY|EXPIRES?|EXPIRATION|VALID\s*UNTIL|VALABLE\s*JUSQU)[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
            '/(?:DATE\s*OF\s*EXPIRY)[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i'
        ];

        foreach ($expiryPatterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $dates['expiry_date'] = $this->parseDate($match[1]);
                break;
            }
        }

        return $dates;
    }

    /**
     * Identifie le pays émetteur
     */
    private function identifyIssuingCountry(string $text): ?string {
        $textUpper = strtoupper($text);

        foreach (self::JURISDICTION_COUNTRIES as $country => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($textUpper, $keyword) !== false) {
                    return $country;
                }
            }
        }

        return null;
    }

    /**
     * Identifie le type de résidence
     */
    private function identifyResidenceType(string $text): ?string {
        $textUpper = strtoupper($text);

        foreach (self::RESIDENCE_TYPES as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($textUpper, $keyword) !== false) {
                    return $type;
                }
            }
        }

        return null;
    }

    /**
     * Extrait le nom de l'employeur
     */
    private function extractEmployer(string $text): ?string {
        $patterns = [
            '/(?:EMPLOYER|EMPLOYEUR|COMPANY|SOCIETE|ORGANIZATION)[:\s]*([A-Za-z][A-Za-z\s\-\.&]+)/i',
            '/(?:WORKS?\s*(?:AT|FOR)|TRAVAILLE\s*(?:CHEZ|POUR))[:\s]*([A-Za-z][A-Za-z\s\-\.&]+)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return trim($match[1]);
            }
        }

        return null;
    }

    /**
     * Extrait l'adresse
     */
    private function extractAddress(string $text): ?string {
        $patterns = [
            '/(?:ADDRESS|ADRESSE|RESIDENCE)[:\s]*([^\n]+)/i',
            '/(?:LIVING\s*AT|RESIDANT\s*A)[:\s]*([^\n]+)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return trim($match[1]);
            }
        }

        return null;
    }

    /**
     * Détecte les indicateurs de photo
     */
    private function detectPhotoIndicators(string $text, array $ocrMetadata): bool {
        // Vérifier dans les métadonnées OCR si une photo est détectée
        if (isset($ocrMetadata['face_detected'])) {
            return $ocrMetadata['face_detected'];
        }

        // Indicateurs textuels
        $photoKeywords = ['PHOTO', 'PHOTOGRAPH', 'PICTURE', 'IMAGE'];
        $textUpper = strtoupper($text);

        foreach ($photoKeywords as $keyword) {
            if (strpos($textUpper, $keyword) !== false) {
                return true;
            }
        }

        return false;
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

        // Validation carte non expirée
        if (isset($extracted['expiry_date'])) {
            $validations['card_not_expired'] = $this->isFutureDate($extracted['expiry_date']);
        }

        // Validation pays émetteur dans la circonscription
        if (isset($extracted['issuing_country'])) {
            $validations['issuing_country_in_jurisdiction'] = isset(self::JURISDICTION_COUNTRIES[$extracted['issuing_country']]);
        }

        // Validation photo présente
        $validations['photo_present'] = $extracted['photo_present'] ?? false;

        // Validation format officiel (indicateurs)
        $validations['official_format'] = !empty($extracted['card_number']) && !empty($extracted['holder_name']);

        // Validation numéro de carte
        if (isset($extracted['card_number'])) {
            $validations['card_number_valid'] = strlen($extracted['card_number']) >= 6;
        }

        return $validations;
    }

    public function getDocumentType(): string {
        return 'residence_card';
    }

    public function getPrdCode(): string {
        return 'DOC_CARTE_RESIDENT';
    }
}
