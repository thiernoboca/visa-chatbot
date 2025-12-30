<?php
/**
 * GeolocationService - IP-based country detection using ipinfo.io
 *
 * Detects user's country from IP address and checks jurisdiction
 * for the Ivory Coast Embassy covering 7 East African countries.
 *
 * @version 1.0.0
 * @author Visa Chatbot Team
 */

class GeolocationService {

    /**
     * ipinfo.io API token (optional - 50k free requests/month without token)
     */
    private string $apiToken;

    /**
     * ipinfo.io API base URL
     */
    private const IPINFO_URL = 'https://ipinfo.io/';

    /**
     * Request timeout in seconds
     */
    private const TIMEOUT = 5;

    /**
     * Cache duration in seconds (1 hour)
     */
    private const CACHE_DURATION = 3600;

    /**
     * 7 countries under embassy jurisdiction
     * ISO 3166-1 alpha-2 codes => Country names (EN/FR)
     */
    private const JURISDICTION_COUNTRIES = [
        'ET' => [
            'en' => 'Ethiopia',
            'fr' => 'Éthiopie',
            'flag' => '🇪🇹',
            'capital' => 'Addis Ababa'
        ],
        'KE' => [
            'en' => 'Kenya',
            'fr' => 'Kenya',
            'flag' => '🇰🇪',
            'capital' => 'Nairobi'
        ],
        'DJ' => [
            'en' => 'Djibouti',
            'fr' => 'Djibouti',
            'flag' => '🇩🇯',
            'capital' => 'Djibouti'
        ],
        'TZ' => [
            'en' => 'Tanzania',
            'fr' => 'Tanzanie',
            'flag' => '🇹🇿',
            'capital' => 'Dodoma'
        ],
        'UG' => [
            'en' => 'Uganda',
            'fr' => 'Ouganda',
            'flag' => '🇺🇬',
            'capital' => 'Kampala'
        ],
        'SS' => [
            'en' => 'South Sudan',
            'fr' => 'Soudan du Sud',
            'flag' => '🇸🇸',
            'capital' => 'Juba'
        ],
        'SO' => [
            'en' => 'Somalia',
            'fr' => 'Somalie',
            'flag' => '🇸🇴',
            'capital' => 'Mogadishu'
        ]
    ];

    /**
     * Constructor
     *
     * @param string|null $apiToken Optional ipinfo.io API token
     */
    public function __construct(?string $apiToken = null) {
        // Try to load from environment if not provided
        $this->apiToken = $apiToken ?? ($_ENV['IPINFO_TOKEN'] ?? '');
    }

    /**
     * Detect country from IP address
     *
     * @param string|null $ip IP address (null = auto-detect from request)
     * @return array Detection result with country info
     */
    public function detectCountry(?string $ip = null): array {
        $ip = $ip ?? $this->getClientIP();

        // Skip localhost/private IPs
        if ($this->isLocalIP($ip)) {
            return $this->buildResponse(null, null, [
                'is_local' => true,
                'message' => 'Local IP detected, geolocation not available'
            ]);
        }

        // Check cache first
        $cached = $this->getFromCache($ip);
        if ($cached !== null) {
            return $cached;
        }

        // Call ipinfo.io API
        $geoData = $this->callIpInfoAPI($ip);

        if (!$geoData || !isset($geoData['country'])) {
            return $this->buildResponse(null, null, [
                'error' => true,
                'message' => 'Could not determine location'
            ]);
        }

        $response = $this->buildResponse($ip, $geoData);

        // Cache the result
        $this->saveToCache($ip, $response);

        return $response;
    }

    /**
     * Check if a country code is within embassy jurisdiction
     *
     * @param string $countryCode ISO 3166-1 alpha-2 country code
     * @return bool
     */
    public function isInJurisdiction(string $countryCode): bool {
        return isset(self::JURISDICTION_COUNTRIES[strtoupper($countryCode)]);
    }

    /**
     * Get list of jurisdiction countries
     *
     * @param string $lang Language code (en/fr)
     * @return array
     */
    public function getJurisdictionCountries(string $lang = 'en'): array {
        $countries = [];
        foreach (self::JURISDICTION_COUNTRIES as $code => $data) {
            $countries[$code] = [
                'code' => $code,
                'name' => $data[$lang] ?? $data['en'],
                'flag' => $data['flag'],
                'capital' => $data['capital']
            ];
        }
        return $countries;
    }

    /**
     * Format jurisdiction countries for display
     *
     * @param string $lang Language code
     * @return string Formatted list with flags
     */
    public function formatJurisdictionList(string $lang = 'en'): string {
        $lines = [];
        foreach (self::JURISDICTION_COUNTRIES as $code => $data) {
            $name = $data[$lang] ?? $data['en'];
            $lines[] = "{$data['flag']} {$name}";
        }
        return implode("\n", $lines);
    }

    /**
     * Get client IP address from request
     *
     * @return string IP address
     */
    private function getClientIP(): string {
        // Check for proxy headers first
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Standard proxy
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_CLIENT_IP',            // Alternative
            'REMOTE_ADDR'                // Direct connection
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // X-Forwarded-For can contain multiple IPs, take the first
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Check if IP is local/private
     *
     * @param string $ip IP address
     * @return bool
     */
    private function isLocalIP(string $ip): bool {
        // Localhost
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost'])) {
            return true;
        }

        // Private ranges
        $privateRanges = [
            ['10.0.0.0', '10.255.255.255'],
            ['172.16.0.0', '172.31.255.255'],
            ['192.168.0.0', '192.168.255.255']
        ];

        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return true; // Invalid IP, treat as local
        }

        foreach ($privateRanges as $range) {
            if ($ipLong >= ip2long($range[0]) && $ipLong <= ip2long($range[1])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Call ipinfo.io API
     *
     * @param string $ip IP address
     * @return array|null API response or null on failure
     */
    private function callIpInfoAPI(string $ip): ?array {
        $url = self::IPINFO_URL . $ip . '/json';

        if (!empty($this->apiToken)) {
            $url .= '?token=' . urlencode($this->apiToken);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::TIMEOUT,
                'header' => [
                    'Accept: application/json',
                    'User-Agent: VisaChatbot/1.0'
                ]
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            error_log("GeolocationService: Failed to call ipinfo.io for IP {$ip}");
            return null;
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("GeolocationService: Invalid JSON response from ipinfo.io");
            return null;
        }

        return $data;
    }

    /**
     * Build standardized response array
     *
     * @param string|null $ip IP address
     * @param array|null $geoData Raw geolocation data
     * @param array $meta Additional metadata
     * @return array
     */
    private function buildResponse(?string $ip, ?array $geoData, array $meta = []): array {
        if (!$geoData) {
            return array_merge([
                'success' => false,
                'ip' => $ip,
                'country_code' => null,
                'country_name' => null,
                'city' => null,
                'in_jurisdiction' => false,
                'jurisdiction_countries' => self::JURISDICTION_COUNTRIES
            ], $meta);
        }

        $countryCode = strtoupper($geoData['country'] ?? '');
        $inJurisdiction = $this->isInJurisdiction($countryCode);

        // Get country name from our list or use the one from ipinfo
        $countryInfo = self::JURISDICTION_COUNTRIES[$countryCode] ?? null;

        return [
            'success' => true,
            'ip' => $ip,
            'country_code' => $countryCode,
            'country_name' => [
                'en' => $countryInfo['en'] ?? ($geoData['country'] ?? 'Unknown'),
                'fr' => $countryInfo['fr'] ?? ($geoData['country'] ?? 'Inconnu')
            ],
            'country_flag' => $countryInfo['flag'] ?? null,
            'city' => $geoData['city'] ?? null,
            'region' => $geoData['region'] ?? null,
            'timezone' => $geoData['timezone'] ?? null,
            'in_jurisdiction' => $inJurisdiction,
            'jurisdiction_countries' => self::JURISDICTION_COUNTRIES,
            'detected_at' => date('c')
        ];
    }

    /**
     * Get cached geolocation result
     *
     * @param string $ip IP address
     * @return array|null Cached result or null
     */
    private function getFromCache(string $ip): ?array {
        $cacheFile = $this->getCacheFilePath($ip);

        if (!file_exists($cacheFile)) {
            return null;
        }

        $cacheData = json_decode(file_get_contents($cacheFile), true);

        if (!$cacheData || !isset($cacheData['expires_at'])) {
            return null;
        }

        if (time() > $cacheData['expires_at']) {
            @unlink($cacheFile);
            return null;
        }

        return $cacheData['data'];
    }

    /**
     * Save geolocation result to cache
     *
     * @param string $ip IP address
     * @param array $data Geolocation data
     */
    private function saveToCache(string $ip, array $data): void {
        $cacheDir = $this->getCacheDir();

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        $cacheFile = $this->getCacheFilePath($ip);
        $cacheData = [
            'expires_at' => time() + self::CACHE_DURATION,
            'data' => $data
        ];

        @file_put_contents($cacheFile, json_encode($cacheData));
    }

    /**
     * Get cache directory path
     *
     * @return string
     */
    private function getCacheDir(): string {
        return dirname(__DIR__) . '/cache/geo';
    }

    /**
     * Get cache file path for an IP
     *
     * @param string $ip IP address
     * @return string
     */
    private function getCacheFilePath(string $ip): string {
        // Hash IP for privacy and filesystem safety
        $hash = md5($ip);
        return $this->getCacheDir() . '/' . $hash . '.json';
    }
}
