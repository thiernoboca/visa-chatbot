<?php
/**
 * Array Utilities
 * Méthodes utilitaires pour la manipulation de tableaux
 *
 * @package VisaChatbot\Utils
 * @version 1.0.0
 */

namespace VisaChatbot\Utils;

class ArrayUtils {

    /**
     * Récupère une valeur imbriquée par chemin de points
     *
     * @param array $array
     * @param string $path Chemin séparé par des points (ex: "fields.name.value")
     * @param mixed $default Valeur par défaut
     * @return mixed
     */
    public static function getNestedValue(array $array, string $path, $default = null) {
        $keys = explode('.', $path);
        $value = $array;

        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Définit une valeur imbriquée par chemin de points
     *
     * @param array $array
     * @param string $path
     * @param mixed $value
     * @return array
     */
    public static function setNestedValue(array $array, string $path, $value): array {
        $keys = explode('.', $path);
        $current = &$array;

        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $current[$key] = $value;
            } else {
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
        }

        return $array;
    }

    /**
     * Vérifie si une clé imbriquée existe
     *
     * @param array $array
     * @param string $path
     * @return bool
     */
    public static function hasNestedKey(array $array, string $path): bool {
        $keys = explode('.', $path);
        $value = $array;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Supprime une clé imbriquée
     *
     * @param array $array
     * @param string $path
     * @return array
     */
    public static function removeNestedKey(array $array, string $path): array {
        $keys = explode('.', $path);
        $lastKey = array_pop($keys);
        $current = &$array;

        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) {
                return $array;
            }
            $current = &$current[$key];
        }

        unset($current[$lastKey]);
        return $array;
    }

    /**
     * Fusionne récursivement deux tableaux
     *
     * @param array $array1
     * @param array $array2
     * @return array
     */
    public static function mergeRecursive(array $array1, array $array2): array {
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($array1[$key]) && is_array($array1[$key])) {
                $array1[$key] = self::mergeRecursive($array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }
        return $array1;
    }

    /**
     * Aplatit un tableau multidimensionnel
     *
     * @param array $array
     * @param string $prefix Préfixe pour les clés
     * @param string $separator Séparateur de clés
     * @return array
     */
    public static function flatten(array $array, string $prefix = '', string $separator = '.'): array {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix ? $prefix . $separator . $key : $key;

            if (is_array($value) && !empty($value) && !self::isIndexedArray($value)) {
                $result = array_merge($result, self::flatten($value, $newKey, $separator));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Vérifie si un tableau est indexé (non associatif)
     *
     * @param array $array
     * @return bool
     */
    public static function isIndexedArray(array $array): bool {
        if (empty($array)) return true;
        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Vérifie si un tableau est associatif
     *
     * @param array $array
     * @return bool
     */
    public static function isAssociativeArray(array $array): bool {
        if (empty($array)) return false;
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Filtre un tableau par clés
     *
     * @param array $array
     * @param array $keys Clés à conserver
     * @return array
     */
    public static function filterByKeys(array $array, array $keys): array {
        return array_intersect_key($array, array_flip($keys));
    }

    /**
     * Extrait les valeurs d'une colonne d'un tableau multidimensionnel
     *
     * @param array $array
     * @param string $column
     * @param string|null $indexKey Clé à utiliser comme index
     * @return array
     */
    public static function pluck(array $array, string $column, ?string $indexKey = null): array {
        return array_column($array, $column, $indexKey);
    }

    /**
     * Groupe un tableau par une clé
     *
     * @param array $array
     * @param string $key
     * @return array
     */
    public static function groupBy(array $array, string $key): array {
        $result = [];

        foreach ($array as $item) {
            if (is_array($item) && isset($item[$key])) {
                $groupKey = $item[$key];
                $result[$groupKey][] = $item;
            }
        }

        return $result;
    }

    /**
     * Trie un tableau par une clé
     *
     * @param array $array
     * @param string $key
     * @param string $direction 'asc' ou 'desc'
     * @return array
     */
    public static function sortBy(array $array, string $key, string $direction = 'asc'): array {
        usort($array, function ($a, $b) use ($key, $direction) {
            $valueA = is_array($a) ? ($a[$key] ?? null) : null;
            $valueB = is_array($b) ? ($b[$key] ?? null) : null;

            $result = $valueA <=> $valueB;
            return $direction === 'desc' ? -$result : $result;
        });

        return $array;
    }

    /**
     * Trouve le premier élément correspondant à une condition
     *
     * @param array $array
     * @param callable $callback
     * @return mixed|null
     */
    public static function find(array $array, callable $callback) {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Vérifie si au moins un élément correspond à une condition
     *
     * @param array $array
     * @param callable $callback
     * @return bool
     */
    public static function some(array $array, callable $callback): bool {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Vérifie si tous les éléments correspondent à une condition
     *
     * @param array $array
     * @param callable $callback
     * @return bool
     */
    public static function every(array $array, callable $callback): bool {
        foreach ($array as $key => $value) {
            if (!$callback($value, $key)) {
                return false;
            }
        }
        return true;
    }
}
