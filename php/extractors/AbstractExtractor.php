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
     * Supporte les formats internationaux et éthiopiens
     */
    protected function parseDate(string $text): ?string {
        $months = [
            'JAN'=>'01','FEB'=>'02','MAR'=>'03','APR'=>'04','MAY'=>'05','JUN'=>'06',
            'JUL'=>'07','AUG'=>'08','SEP'=>'09','OCT'=>'10','NOV'=>'11','DEC'=>'12',
            'JANUARY'=>'01','FEBRUARY'=>'02','MARCH'=>'03','APRIL'=>'04',
            'JUNE'=>'06','JULY'=>'07','AUGUST'=>'08','SEPTEMBER'=>'09',
            'OCTOBER'=>'10','NOVEMBER'=>'11','DECEMBER'=>'12'
        ];

        $patterns = [
            // DD/MM/YYYY ou DD-MM-YYYY ou DD.MM.YYYY
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
            // DD MMM YYYY (ex: 15 JAN 2024, 22 AUG 2025)
            '/(\d{1,2})\s+(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)[A-Z]*\s+(\d{4})/i' => function($m) use ($months) {
                $month = $months[strtoupper(substr($m[2], 0, 3))] ?? '01';
                return sprintf('%s-%s-%02d', $m[3], $month, (int)$m[1]);
            },
            // DD MMM YY (Ethiopian format: 22 AUG 95, 16 SEP 30)
            '/(\d{1,2})\s+(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)[A-Z]*\s+(\d{2})\b/i' => function($m) use ($months) {
                $month = $months[strtoupper(substr($m[2], 0, 3))] ?? '01';
                $yearShort = (int)$m[3];
                $currentYear = (int)date('y');
                // Pour les dates de naissance: si > année courante, c'est 19XX
                // Pour les dates d'expiration: si <= année courante + 15, c'est 20XX
                // Heuristique: années 00-40 = 2000-2040, 41-99 = 1941-1999
                $fullYear = ($yearShort <= 40) ? 2000 + $yearShort : 1900 + $yearShort;
                return sprintf('%04d-%s-%02d', $fullYear, $month, (int)$m[1]);
            },
            // MMM DD, YYYY (US format: JAN 15, 2024)
            '/(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)[A-Z]*\s+(\d{1,2}),?\s+(\d{4})/i' => function($m) use ($months) {
                $month = $months[strtoupper(substr($m[1], 0, 3))] ?? '01';
                return sprintf('%s-%s-%02d', $m[3], $month, (int)$m[2]);
            }
        ];

        foreach ($patterns as $pattern => $formatter) {
            if (preg_match($pattern, trim($text), $matches)) {
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
     * Amélioré pour gérer les erreurs OCR courantes
     */
    protected function extractMrzLines(string $text): ?array {
        // Normaliser le texte: supprimer espaces, convertir en majuscules
        $cleanText = strtoupper($text);
        $cleanText = preg_replace('/\s+/', '', $cleanText);

        // Corriger les confusions OCR courantes dans le contexte MRZ
        $mrzCorrections = [
            'O' => '0',  // O -> 0 dans les zones numériques (sera corrigé contextuellement)
            '|' => '<',
            '/' => '<',
            '\\' => '<',
            '[' => '<',
            ']' => '<',
            '{' => '<',
            '}' => '<',
        ];

        // Pattern pour lignes MRZ TD3 (44 caractères avec < et alphanumériques)
        // Plus flexible: permet 42-46 caractères pour gérer les erreurs OCR
        $mrzPattern = '/([A-Z0-9<]{42,46})/';

        preg_match_all($mrzPattern, $cleanText, $matches);

        if (count($matches[0]) >= 2) {
            $line1 = $this->normalizeMrzLine($matches[0][0], 44);
            $line2 = $this->normalizeMrzLine($matches[0][1], 44);

            // Vérifier que ligne 1 commence par P (passeport)
            if (preg_match('/^P[A-Z<]/', $line1)) {
                return [
                    'type' => 'TD3',
                    'line1' => $line1,
                    'line2' => $line2
                ];
            }
        }

        // Essayer de trouver les lignes MRZ par pattern plus spécifique
        // Ligne 1 TD3: P<XXXNOM<<PRENOM<<<... (commence par P + type + pays)
        $line1Pattern = '/P[A-Z<][A-Z]{3}[A-Z<]{38,42}/';
        // Ligne 2 TD3: commence par numéro de passeport, contient dates et sexe
        $line2Pattern = '/[A-Z0-9]{9}[0-9<][A-Z]{3}[0-9]{6}[0-9<][MFX<][0-9]{6}[0-9<][A-Z0-9<]{14,16}[0-9<]/';

        preg_match($line1Pattern, $cleanText, $line1Match);
        preg_match($line2Pattern, $cleanText, $line2Match);

        if (!empty($line1Match) && !empty($line2Match)) {
            return [
                'type' => 'TD3',
                'line1' => $this->normalizeMrzLine($line1Match[0], 44),
                'line2' => $this->normalizeMrzLine($line2Match[0], 44)
            ];
        }

        // Fallback: chercher par pattern de passeport éthiopien spécifique
        // Format: PQETH ou P<ETH au début (Q peut être < mal lu)
        $ethiopianPattern = '/P[Q<O]ETH[A-Z<]{36,40}/';
        preg_match($ethiopianPattern, $cleanText, $ethiopianLine1);

        // Ligne 2 éthiopienne: EQ suivi de 7 chiffres (numéro de passeport)
        $ethiopianLine2Pattern = '/E[A-Z][0-9]{7}[0-9][A-Z]{3}[0-9]{6}[0-9][MFX][0-9]{6}[0-9<]{15,17}/';
        preg_match($ethiopianLine2Pattern, $cleanText, $ethiopianLine2);

        if (!empty($ethiopianLine1) && !empty($ethiopianLine2)) {
            return [
                'type' => 'TD3',
                'line1' => $this->normalizeMrzLine($ethiopianLine1[0], 44),
                'line2' => $this->normalizeMrzLine($ethiopianLine2[0], 44)
            ];
        }

        // Essayer TD1/TD2 (3 lignes de 30 caractères)
        $td1Pattern = '/([A-Z0-9<]{28,32})/';
        preg_match_all($td1Pattern, $cleanText, $td1Matches);

        if (count($td1Matches[0]) >= 3) {
            return [
                'type' => 'TD1',
                'line1' => $this->normalizeMrzLine($td1Matches[0][0], 30),
                'line2' => $this->normalizeMrzLine($td1Matches[0][1], 30),
                'line3' => $this->normalizeMrzLine($td1Matches[0][2], 30)
            ];
        }

        return null;
    }

    /**
     * Normalise une ligne MRZ à la longueur attendue
     */
    protected function normalizeMrzLine(string $line, int $expectedLength): string {
        // Supprimer les caractères non-MRZ
        $line = preg_replace('/[^A-Z0-9<]/', '', strtoupper($line));

        // Ajuster la longueur
        if (strlen($line) > $expectedLength) {
            // Tronquer à la longueur attendue
            $line = substr($line, 0, $expectedLength);
        } elseif (strlen($line) < $expectedLength) {
            // Compléter avec des <
            $line = str_pad($line, $expectedLength, '<');
        }

        return $line;
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
