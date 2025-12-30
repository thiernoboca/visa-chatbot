<?php
/**
 * Classe abstraite pour tous les extracteurs de documents
 * Ambassade de Côte d'Ivoire en Éthiopie
 *
 * @package VisaChatbot\Extractors
 * @version 1.0.0
 */

namespace VisaChatbot\Extractors;

use Exception;

abstract class AbstractExtractor {

    /**
     * Service OCR parent
     */
    protected $ocrService;

    /**
     * Configuration de l'extracteur
     */
    protected array $config = [];

    /**
     * Champs requis pour ce type de document
     */
    protected array $requiredFields = [];

    /**
     * Champs optionnels
     */
    protected array $optionalFields = [];

    /**
     * Règles de validation
     */
    protected array $validationRules = [];

    /**
     * Constructeur
     */
    public function __construct($ocrService = null, array $config = []) {
        $this->ocrService = $ocrService;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Configuration par défaut
     */
    protected function getDefaultConfig(): array {
        return [
            'strict_mode' => false,
            'min_confidence' => 0.7,
            'validate_checksums' => true,
            'cross_validate' => true
        ];
    }

    /**
     * Extrait les données du document
     *
     * @param string $rawText Texte brut OCR
     * @param array $ocrMetadata Métadonnées OCR (blocs, confidence, etc.)
     * @return array Données extraites
     */
    abstract public function extract(string $rawText, array $ocrMetadata = []): array;

    /**
     * Valide les données extraites
     *
     * @param array $extracted Données extraites
     * @return array Résultats de validation
     */
    abstract public function validate(array $extracted): array;

    /**
     * Retourne le type de document
     */
    abstract public function getDocumentType(): string;

    /**
     * Retourne le code PRD du document
     */
    abstract public function getPrdCode(): string;

    /**
     * Retourne les champs attendus
     */
    public function getExpectedFields(): array {
        return [
            'required' => $this->requiredFields,
            'optional' => $this->optionalFields
        ];
    }

    /**
     * Parse une date depuis un texte (formats multiples)
     */
    protected function parseDate(string $text): ?string {
        $patterns = [
            // DD/MM/YYYY ou DD-MM-YYYY
            '/(\d{2})[\/\-\.](\d{2})[\/\-\.](\d{4})/' => function($m) {
                return sprintf('%s-%s-%s', $m[3], $m[2], $m[1]);
            },
            // YYYY/MM/DD ou YYYY-MM-DD
            '/(\d{4})[\/\-\.](\d{2})[\/\-\.](\d{2})/' => function($m) {
                return sprintf('%s-%s-%s', $m[1], $m[2], $m[3]);
            },
            // YYMMDD (format MRZ)
            '/^(\d{2})(\d{2})(\d{2})$/' => function($m) {
                $year = (int)$m[1] > 30 ? '19' . $m[1] : '20' . $m[1];
                return sprintf('%s-%s-%s', $year, $m[2], $m[3]);
            },
            // DD MMM YYYY (ex: 15 JAN 2024)
            '/(\d{1,2})\s+(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)[A-Z]*\s+(\d{4})/i' => function($m) {
                $months = ['JAN'=>'01','FEB'=>'02','MAR'=>'03','APR'=>'04','MAY'=>'05','JUN'=>'06',
                           'JUL'=>'07','AUG'=>'08','SEP'=>'09','OCT'=>'10','NOV'=>'11','DEC'=>'12'];
                $month = $months[strtoupper(substr($m[2], 0, 3))] ?? '01';
                return sprintf('%s-%s-%02d', $m[3], $month, (int)$m[1]);
            }
        ];

        foreach ($patterns as $pattern => $formatter) {
            if (preg_match($pattern, $text, $matches)) {
                return $formatter($matches);
            }
        }

        return null;
    }

    /**
     * Normalise un nom (majuscules, sans accents)
     */
    protected function normalizeName(string $name): string {
        // Translittération des accents
        $transliteration = [
            'À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A','Æ'=>'AE',
            'Ç'=>'C','È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E',
            'Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I',
            'Ñ'=>'N','Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O',
            'Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U','Ý'=>'Y',
            'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','æ'=>'ae',
            'ç'=>'c','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
            'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
            'ñ'=>'n','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
            'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ý'=>'y','ÿ'=>'y'
        ];

        $name = strtr($name, $transliteration);
        $name = strtoupper($name);
        $name = preg_replace('/[^A-Z\s\-\']/', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }

    /**
     * Extrait un montant avec devise
     */
    protected function parseAmount(string $text): ?array {
        $pattern = '/(\d{1,3}(?:[,\.\s]\d{3})*(?:[,\.]\d{2})?)\s*(XOF|FCFA|ETB|EUR|USD|CFA)?/i';

        if (preg_match($pattern, $text, $matches)) {
            $amount = preg_replace('/[^\d,\.]/', '', $matches[1]);
            $amount = str_replace([',', ' '], ['.', ''], $amount);

            $currency = strtoupper($matches[2] ?? 'XOF');
            if ($currency === 'FCFA' || $currency === 'CFA') {
                $currency = 'XOF';
            }

            return [
                'amount' => (float)$amount,
                'currency' => $currency
            ];
        }

        return null;
    }

    /**
     * Calcule le checksum MRZ
     */
    protected function calculateMrzChecksum(string $data): int {
        $weights = [7, 3, 1];
        $charValues = [];

        // Valeurs des caractères pour MRZ
        for ($i = 0; $i < 10; $i++) {
            $charValues[(string)$i] = $i;
        }
        for ($i = 0; $i < 26; $i++) {
            $charValues[chr(65 + $i)] = $i + 10;
        }
        $charValues['<'] = 0;

        $sum = 0;
        for ($i = 0; $i < strlen($data); $i++) {
            $char = strtoupper($data[$i]);
            $value = $charValues[$char] ?? 0;
            $sum += $value * $weights[$i % 3];
        }

        return $sum % 10;
    }

    /**
     * Vérifie si une date est dans le futur
     */
    protected function isFutureDate(string $date): bool {
        $timestamp = strtotime($date);
        return $timestamp !== false && $timestamp > time();
    }

    /**
     * Vérifie si une date d'expiration est valide (+6 mois)
     */
    protected function isExpiryValid(string $expiryDate, int $minMonths = 6): bool {
        $expiry = strtotime($expiryDate);
        $minDate = strtotime("+{$minMonths} months");
        return $expiry !== false && $expiry > $minDate;
    }

    /**
     * Calcule la similarité entre deux chaînes (Levenshtein normalisé)
     */
    protected function stringSimilarity(string $str1, string $str2): float {
        $str1 = $this->normalizeName($str1);
        $str2 = $this->normalizeName($str2);

        if ($str1 === $str2) {
            return 1.0;
        }

        $maxLen = max(strlen($str1), strlen($str2));
        if ($maxLen === 0) {
            return 1.0;
        }

        $distance = levenshtein($str1, $str2);
        return 1 - ($distance / $maxLen);
    }

    /**
     * Vérifie si une ville est en Côte d'Ivoire
     */
    protected function isCoteDivoireCity(string $city): bool {
        $cities = [
            'ABIDJAN', 'YAMOUSSOUKRO', 'BOUAKE', 'DALOA', 'SAN PEDRO',
            'KORHOGO', 'MAN', 'DIVO', 'GAGNOA', 'ABENGOUROU',
            'GRAND BASSAM', 'ASSINIE', 'SASSANDRA', 'ODIENNE'
        ];

        $normalized = $this->normalizeName($city);
        foreach ($cities as $ciCity) {
            if (strpos($normalized, $ciCity) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie si un pays fait partie de la circonscription (7 pays)
     */
    protected function isInJurisdiction(string $country): bool {
        $jurisdictionCountries = [
            'ETHIOPIE', 'ETHIOPIA', 'ETH',
            'DJIBOUTI', 'DJI',
            'ERYTHREE', 'ERITREA', 'ERI',
            'KENYA', 'KEN',
            'OUGANDA', 'UGANDA', 'UGA',
            'SOMALIE', 'SOMALIA', 'SOM',
            'SOUDAN DU SUD', 'SOUTH SUDAN', 'SSD'
        ];

        $normalized = $this->normalizeName($country);
        foreach ($jurisdictionCountries as $jCountry) {
            if (strpos($normalized, $jCountry) !== false || $normalized === $jCountry) {
                return true;
            }
        }

        return false;
    }

    /**
     * Nettoie et normalise le texte OCR
     */
    protected function cleanOcrText(string $text): string {
        // Supprimer les caractères de contrôle
        $text = preg_replace('/[\x00-\x1F\x7F]/', ' ', $text);

        // Normaliser les espaces
        $text = preg_replace('/\s+/', ' ', $text);

        // Corriger les confusions OCR courantes
        $corrections = [
            '0' => 'O', // Dans les noms
            '1' => 'I', // Dans les noms
            '5' => 'S', // Dans les noms
            '|' => 'I',
            '/' => 'I'  // Parfois
        ];

        return trim($text);
    }

    /**
     * Extrait les lignes MRZ du texte
     */
    protected function extractMrzLines(string $text): ?array {
        // Pattern pour lignes MRZ (44 caractères avec < et alphanumériques)
        $mrzPattern = '/([A-Z0-9<]{44})/';

        preg_match_all($mrzPattern, str_replace(' ', '', $text), $matches);

        if (count($matches[0]) >= 2) {
            // TD3 (passeport standard) - 2 lignes de 44 caractères
            return [
                'type' => 'TD3',
                'line1' => $matches[0][0],
                'line2' => $matches[0][1]
            ];
        }

        // Essayer TD1/TD2 (3 lignes de 30 caractères)
        $td1Pattern = '/([A-Z0-9<]{30})/';
        preg_match_all($td1Pattern, str_replace(' ', '', $text), $td1Matches);

        if (count($td1Matches[0]) >= 3) {
            return [
                'type' => 'TD1',
                'line1' => $td1Matches[0][0],
                'line2' => $td1Matches[0][1],
                'line3' => $td1Matches[0][2]
            ];
        }

        return null;
    }

    /**
     * Log un message si debug activé
     */
    protected function log(string $message, array $context = []): void {
        if ($this->config['debug'] ?? false) {
            error_log("[{$this->getDocumentType()}Extractor] {$message} " . json_encode($context));
        }
    }
}
