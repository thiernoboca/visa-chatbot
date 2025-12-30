<?php
/**
 * String Utilities
 * Méthodes utilitaires pour la manipulation de chaînes
 *
 * @package VisaChatbot\Utils
 * @version 1.0.0
 */

namespace VisaChatbot\Utils;

class StringUtils {

    /**
     * Caractères accentués et leurs équivalents ASCII
     */
    private const ACCENTS_MAP = [
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
        'Æ' => 'AE', 'Ç' => 'C', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ð' => 'D', 'Ñ' => 'N',
        'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'TH',
        'ß' => 'SS', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'å' => 'a', 'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e',
        'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'd',
        'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y',
        'þ' => 'th', 'ÿ' => 'y', 'Œ' => 'OE', 'œ' => 'oe', 'Š' => 'S', 'š' => 's',
        'Ž' => 'Z', 'ž' => 'z', 'ƒ' => 'f'
    ];

    /**
     * Normalise une chaîne pour comparaison
     *
     * @param mixed $value Valeur à normaliser
     * @return string
     */
    public static function normalizeForComparison($value): string {
        if (!is_string($value)) {
            $value = (string)$value;
        }
        $value = self::removeAccents($value);
        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]/', '', $value);
        return $value;
    }

    /**
     * Supprime les accents d'une chaîne
     *
     * @param string $string
     * @return string
     */
    public static function removeAccents(string $string): string {
        return strtr($string, self::ACCENTS_MAP);
    }

    /**
     * Normalise un nom pour comparaison
     * Gère les noms composés, tirets, apostrophes
     *
     * @param string $name
     * @return string
     */
    public static function normalizeNameForComparison(string $name): string {
        $name = self::removeAccents($name);
        $name = strtoupper($name);
        $name = preg_replace('/[^A-Z\s]/', ' ', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name);
        return $name;
    }

    /**
     * Parse une date vers format standardisé (YYYY-MM-DD)
     *
     * @param mixed $value
     * @return string|null
     */
    public static function parseDate($value): ?string {
        if (!$value) return null;

        $value = trim((string)$value);

        // Format ISO déjà correct
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        // Format DD/MM/YYYY ou DD-MM-YYYY
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $value, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        // Format YYYYMMDD (MRZ style)
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }

        // Format YYMMDD (MRZ)
        if (preg_match('/^(\d{2})(\d{2})(\d{2})$/', $value, $m)) {
            $year = (int)$m[1];
            $fullYear = $year < 50 ? 2000 + $year : 1900 + $year;
            return sprintf('%04d-%02d-%02d', $fullYear, $m[2], $m[3]);
        }

        // Try PHP's strtotime as fallback
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }

    /**
     * Calcule la similarité entre deux chaînes
     *
     * @param string $str1
     * @param string $str2
     * @return float Score de similarité (0-1)
     */
    public static function similarity(string $str1, string $str2): float {
        $str1 = self::normalizeForComparison($str1);
        $str2 = self::normalizeForComparison($str2);

        if ($str1 === $str2) return 1.0;
        if (empty($str1) || empty($str2)) return 0.0;

        $percent = 0;
        similar_text($str1, $str2, $percent);

        return $percent / 100;
    }

    /**
     * Vérifie si deux noms correspondent
     *
     * @param string $name1
     * @param string $name2
     * @param float $threshold Seuil de similarité (défaut: 0.85)
     * @return bool
     */
    public static function namesMatch(string $name1, string $name2, float $threshold = 0.85): bool {
        $norm1 = self::normalizeNameForComparison($name1);
        $norm2 = self::normalizeNameForComparison($name2);

        if ($norm1 === $norm2) return true;

        // Vérifier si l'un est contenu dans l'autre
        if (strpos($norm1, $norm2) !== false || strpos($norm2, $norm1) !== false) {
            return true;
        }

        // Comparer les mots individuels
        $words1 = explode(' ', $norm1);
        $words2 = explode(' ', $norm2);

        $matches = 0;
        foreach ($words1 as $word1) {
            foreach ($words2 as $word2) {
                if ($word1 === $word2 || self::similarity($word1, $word2) > $threshold) {
                    $matches++;
                    break;
                }
            }
        }

        $totalWords = max(count($words1), count($words2));
        return ($matches / $totalWords) >= $threshold;
    }

    /**
     * Extrait les initiales d'un nom
     *
     * @param string $name
     * @return string
     */
    public static function getInitials(string $name): string {
        $words = explode(' ', self::normalizeNameForComparison($name));
        $initials = '';
        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= $word[0];
            }
        }
        return $initials;
    }

    /**
     * Génère un slug depuis une chaîne
     *
     * @param string $string
     * @param string $separator
     * @return string
     */
    public static function slugify(string $string, string $separator = '-'): string {
        $string = self::removeAccents($string);
        $string = strtolower($string);
        $string = preg_replace('/[^a-z0-9]+/', $separator, $string);
        $string = trim($string, $separator);
        return $string;
    }

    /**
     * Masque une partie d'une chaîne sensible
     *
     * @param string $value
     * @param int $visibleStart Nombre de caractères visibles au début
     * @param int $visibleEnd Nombre de caractères visibles à la fin
     * @param string $maskChar Caractère de masquage
     * @return string
     */
    public static function mask(string $value, int $visibleStart = 2, int $visibleEnd = 2, string $maskChar = '*'): string {
        $length = strlen($value);
        if ($length <= $visibleStart + $visibleEnd) {
            return str_repeat($maskChar, $length);
        }

        $start = substr($value, 0, $visibleStart);
        $end = substr($value, -$visibleEnd);
        $middle = str_repeat($maskChar, $length - $visibleStart - $visibleEnd);

        return $start . $middle . $end;
    }
}
