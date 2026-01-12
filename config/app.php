<?php
/**
 * Configuration de l'application Chatbot e-Visa
 * Architecture MVC - Côte d'Ivoire
 */

// Empêcher l'accès direct
if (!defined('APP_LOADED')) {
    define('APP_LOADED', true);
}

// ========================================
// CHEMINS DE L'APPLICATION
// ========================================

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
if (!defined('CHATBOT_ROOT')) {
    define('CHATBOT_ROOT', BASE_PATH);  // Alias pour compatibilité
}
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', BASE_PATH . '/config');
}
if (!defined('CONTROLLERS_PATH')) {
    define('CONTROLLERS_PATH', BASE_PATH . '/controllers');
}
if (!defined('VIEWS_PATH')) {
    define('VIEWS_PATH', BASE_PATH . '/views');
}
if (!defined('INCLUDES_PATH')) {
    define('INCLUDES_PATH', BASE_PATH . '/includes');
}
if (!defined('ASSETS_PATH')) {
    define('ASSETS_PATH', BASE_PATH . '/assets');
}

// URL de base (à ajuster selon l'environnement)
if (!defined('BASE_URL')) {
    define('BASE_URL', '/hunyuanocr/visa-chatbot');
}
if (!defined('ASSETS_URL')) {
    define('ASSETS_URL', BASE_URL . '/assets');
}

// ========================================
// CONFIGURATION DE L'APPLICATION
// ========================================

$config = [
    // Informations générales
    'app' => [
        'name' => "Côte d'Ivoire e-Visa",
        'version' => '2.0.0',
        'embassy' => 'Addis-Abeba',
        'country' => 'Éthiopie',
    ],
    
    // Langues supportées
    'languages' => [
        'fr' => [
            'label' => 'FR',
            'name' => 'Français',
        ],
        'en' => [
            'label' => 'EN',
            'name' => 'English',
        ],
    ],
    'default_language' => 'fr',
    
    // Thème
    'theme' => [
        'default' => 'light',
        'colors' => [
            'primary' => '#0D5C46',
            'primary_light' => '#10816A',
            'primary_dark' => '#094536',
            'accent_orange' => '#F77F00',
            'accent_green' => '#009A44',
        ],
    ],
    
    // Assets externes
    'external_assets' => [
        'fonts' => [
            'google_fonts' => 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=DM+Sans:wght@400;500;600;700&display=swap',
            'material_symbols' => 'https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap',
        ],
        'tailwind' => 'https://cdn.tailwindcss.com?plugins=forms,container-queries',
        'qrcode' => 'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js',
    ],
    
    // Configuration des uploads
    'upload' => [
        'max_size' => 5 * 1024 * 1024, // 5MB
        'allowed_types' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    ],
    
    // Étapes du processus de visa
    // Étapes du processus de visa (10 étapes)
    'steps' => [
        'welcome'       => ['order' => 1,  'label_fr' => 'Langue',            'label_en' => 'Language',         'progress' => 5],
        'geolocation'   => ['order' => 2,  'label_fr' => 'Géolocalisation',   'label_en' => 'Geolocation',      'progress' => 10],
        'passport'      => ['order' => 3,  'label_fr' => 'Passeport',         'label_en' => 'Passport',         'progress' => 25],
        'ticket'        => ['order' => 4,  'label_fr' => 'Billet d\'avion',   'label_en' => 'Flight Ticket',    'progress' => 40],
        'vaccination'   => ['order' => 5,  'label_fr' => 'Vaccination',       'label_en' => 'Vaccination',      'progress' => 50],
        'photo'         => ['order' => 6,  'label_fr' => 'Photo ID',          'label_en' => 'Photo ID',         'progress' => 60],
        'contact'       => ['order' => 7,  'label_fr' => 'Contact',           'label_en' => 'Contact',          'progress' => 70],
        'trip'          => ['order' => 8,  'label_fr' => 'Voyage',            'label_en' => 'Trip',             'progress' => 80],
        'payment'       => ['order' => 9,  'label_fr' => 'Paiement',          'label_en' => 'Payment',          'progress' => 90],
        'confirm'       => ['order' => 10, 'label_fr' => 'Confirmation',      'label_en' => 'Confirmation',     'progress' => 100],
    ],
    
    // Pays de la juridiction
    'jurisdiction_countries' => [
        ['code' => 'ETH', 'name_fr' => 'Éthiopie', 'name_en' => 'Ethiopia', 'flag' => '🇪🇹'],
        ['code' => 'KEN', 'name_fr' => 'Kenya', 'name_en' => 'Kenya', 'flag' => '🇰🇪'],
        ['code' => 'DJI', 'name_fr' => 'Djibouti', 'name_en' => 'Djibouti', 'flag' => '🇩🇯'],
        ['code' => 'TZA', 'name_fr' => 'Tanzanie', 'name_en' => 'Tanzania', 'flag' => '🇹🇿'],
        ['code' => 'UGA', 'name_fr' => 'Ouganda', 'name_en' => 'Uganda', 'flag' => '🇺🇬'],
        ['code' => 'SSD', 'name_fr' => 'Soudan du Sud', 'name_en' => 'South Sudan', 'flag' => '🇸🇸'],
        ['code' => 'SOM', 'name_fr' => 'Somalie', 'name_en' => 'Somalia', 'flag' => '🇸🇴'],
    ],
];

// Rendre la configuration accessible globalement
if (!defined('APP_CONFIG')) {
    define('APP_CONFIG', serialize($config));
}

/**
 * Récupère la configuration de l'application
 */
function getConfig($key = null) {
    $config = unserialize(APP_CONFIG);
    
    if ($key === null) {
        return $config;
    }
    
    $keys = explode('.', $key);
    $value = $config;
    
    foreach ($keys as $k) {
        if (isset($value[$k])) {
            $value = $value[$k];
        } else {
            return null;
        }
    }
    
    return $value;
}

