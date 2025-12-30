<?php
/**
 * Fonctions utilitaires pour l'application
 */

/**
 * Échappe une chaîne pour l'affichage HTML
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Génère une URL d'asset
 */
function asset($path) {
    return ASSETS_URL . '/' . ltrim($path, '/');
}

/**
 * Génère une URL de base
 */
function url($path = '') {
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * Récupère la langue courante
 */
function getCurrentLanguage() {
    return $_GET['lang'] ?? getConfig('default_language') ?? 'fr';
}

/**
 * Vérifie si le mode sombre est activé
 */
function isDarkMode() {
    return isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark';
}

/**
 * Récupère le label d'une étape selon la langue
 */
function getStepLabel($stepKey, $lang = null) {
    $lang = $lang ?? getCurrentLanguage();
    $steps = getConfig('steps');
    
    if (isset($steps[$stepKey])) {
        $labelKey = "label_{$lang}";
        return $steps[$stepKey][$labelKey] ?? $steps[$stepKey]['label_fr'] ?? $stepKey;
    }
    
    return $stepKey;
}

/**
 * Récupère les pays de la juridiction
 */
function getJurisdictionCountries($lang = null) {
    $lang = $lang ?? getCurrentLanguage();
    $countries = getConfig('jurisdiction_countries');
    $nameKey = "name_{$lang}";
    
    return array_map(function($country) use ($nameKey) {
        return [
            'code' => $country['code'],
            'name' => $country[$nameKey] ?? $country['name_fr'],
            'flag' => $country['flag'],
        ];
    }, $countries);
}

/**
 * Génère le favicon en SVG (drapeau de Côte d'Ivoire)
 */
function getFaviconSvg() {
    return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect fill='%23F77F00' width='33' height='100'/%3E%3Crect fill='%23FFFFFF' x='33' width='34' height='100'/%3E%3Crect fill='%23009A44' x='67' width='33' height='100'/%3E%3C/svg%3E";
}

/**
 * Génère un ID unique pour les sessions
 */
function generateSessionId() {
    return 'session-' . time() . '-' . bin2hex(random_bytes(8));
}

/**
 * Formate une date selon la langue
 */
function formatDate($date, $lang = null) {
    $lang = $lang ?? getCurrentLanguage();
    $timestamp = is_string($date) ? strtotime($date) : $date;
    
    if ($lang === 'fr') {
        return strftime('%d %B %Y', $timestamp);
    }
    
    return date('F d, Y', $timestamp);
}

/**
 * Inclut un partial avec des données
 */
function partial($name, $data = []) {
    extract($data);
    include VIEWS_PATH . '/partials/' . $name . '.php';
}

/**
 * Debug helper
 */
function dd($var) {
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
    die();
}

