<?php
/**
 * Extracteur de Réservation Hôtel
 * Ambassade de Côte d'Ivoire en Éthiopie
 *
 * Conforme au PRD: DOC_HOTEL
 * Extrait: client, hôtel, dates séjour, confirmation
 *
 * @package VisaChatbot\Extractors
 * @version 1.0.0
 */

namespace VisaChatbot\Extractors;

require_once __DIR__ . '/AbstractExtractor.php';

class HotelReservationExtractor extends AbstractExtractor {

    /**
     * Plateformes de réservation connues
     */
    public const BOOKING_PLATFORMS = [
        'BOOKING.COM', 'BOOKING', 'EXPEDIA', 'HOTELS.COM', 'AGODA',
        'AIRBNB', 'VRBO', 'TRIPADVISOR', 'KAYAK', 'TRIVAGO'
    ];

    /**
     * Villes de Côte d'Ivoire (avec variantes)
     */
    public const CI_CITIES = [
        'ABIDJAN', 'YAMOUSSOUKRO', 'BOUAKE', 'DALOA', 'SAN PEDRO',
        'KORHOGO', 'MAN', 'DIVO', 'GAGNOA', 'ABENGOUROU', 'GRAND BASSAM',
        'ASSINIE', 'SASSANDRA', 'BINGERVILLE', 'PLATEAU', 'COCODY',
        'MARCORY', 'TREICHVILLE', 'YOPOUGON', 'ABOBO', 'PORT-BOUET'
    ];

    /**
     * Variantes de noms de villes (pour normalisation)
     */
    private const CITY_ALIASES = [
        'SAN-PEDRO' => 'SAN PEDRO',
        'SANPEDRO' => 'SAN PEDRO',
        'GRAND-BASSAM' => 'GRAND BASSAM',
        'GRANDBASSAM' => 'GRAND BASSAM',
        'PORT BOUET' => 'PORT-BOUET',
        'PORTBOUET' => 'PORT-BOUET',
        'BOUAKÉ' => 'BOUAKE',
        'BOUAKÈ' => 'BOUAKE',
        'YAMOUSSOUKRO' => 'YAMOUSSOUKRO',
        'YAMOUSSOKRO' => 'YAMOUSSOUKRO'
    ];

    protected array $requiredFields = [
        'guest_name',
        'hotel_name',
        'check_in_date',
        'check_out_date'
    ];

    protected array $optionalFields = [
        'hotel_address',
        'hotel_city',
        'hotel_country',
        'nights',
        'room_type',
        'confirmation_number',
        'booking_platform',
        'total_amount'
    ];

    /**
     * Extrait les données de la réservation hôtel
     */
    public function extract(string $rawText, array $ocrMetadata = []): array {
        $result = [
            'success' => false,
            'extracted' => []
        ];

        $text = $this->cleanOcrText($rawText);

        // 1. Extraire le nom du client
        $result['extracted']['guest_name'] = $this->extractGuestName($text);

        // 2. Extraire le nom de l'hôtel
        $result['extracted']['hotel_name'] = $this->extractHotelName($text);

        // 3. Extraire l'adresse de l'hôtel
        $address = $this->extractHotelAddress($text);
        $result['extracted']['hotel_address'] = $address['address'] ?? null;
        $result['extracted']['hotel_city'] = $address['city'] ?? null;
        $result['extracted']['hotel_country'] = $address['country'] ?? null;

        // 4. Extraire les dates de séjour
        $dates = $this->extractStayDates($text);
        $result['extracted']['check_in_date'] = $dates['check_in'] ?? null;
        $result['extracted']['check_out_date'] = $dates['check_out'] ?? null;

        // 5. Calculer le nombre de nuits
        if ($result['extracted']['check_in_date'] && $result['extracted']['check_out_date']) {
            $result['extracted']['nights'] = $this->calculateNights(
                $result['extracted']['check_in_date'],
                $result['extracted']['check_out_date']
            );
        }

        // 6. Extraire le numéro de confirmation
        $result['extracted']['confirmation_number'] = $this->extractConfirmationNumber($text);

        // 7. Extraire le type de chambre
        $result['extracted']['room_type'] = $this->extractRoomType($text);

        // 8. Détecter la plateforme de réservation
        $result['extracted']['booking_platform'] = $this->detectBookingPlatform($text);

        // 9. Extraire le montant total
        $result['extracted']['total_amount'] = $this->extractTotalAmount($text);

        // 10. Succès si champs requis présents
        $result['success'] = $this->hasRequiredFields($result['extracted']);

        return $result;
    }

    /**
     * Extrait le nom du client/invité
     */
    private function extractGuestName(string $text): ?string {
        $patterns = [
            '/(?:GUEST|CLIENT|CUSTOMER|NAME|NOM)[:\s]*([A-Z][A-Z\-\'\s]+)/i',
            '/(?:RESERVATION\s*FOR|BOOKING\s*FOR|BOOKED\s*BY)[:\s]*([A-Z][A-Z\-\'\s]+)/i',
            '/(?:MR|MRS|MS|MLLE|MME)[\.\/\s]+([A-Z][A-Z\-\'\s]+)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return $this->normalizeName(trim($match[1]));
            }
        }

        return null;
    }

    /**
     * Extrait le nom de l'hôtel
     */
    private function extractHotelName(string $text): ?string {
        $patterns = [
            '/(?:HOTEL|RESORT|RESIDENCE|APARTHOTEL|MOTEL)[:\s]*([A-Za-z][A-Za-z\s\-\'\.]+)/i',
            '/([A-Z][A-Za-z\s\-]+(?:HOTEL|RESORT|PALACE|INN|SUITES|LODGE))/i',
            '/(?:PROPERTY|ACCOMMODATION|LODGING)[:\s]*([A-Za-z][A-Za-z\s\-\'\.]+)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $name = trim($match[1]);
                // Nettoyer le nom
                $name = preg_replace('/\s+/', ' ', $name);
                if (strlen($name) > 3) {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * Normalise un nom de ville (gère aliases et casse)
     */
    private function normalizeCity(string $city): ?string {
        $upper = strtoupper(trim($city));

        // Retirer accents pour comparaison
        $normalized = $this->removeAccents($upper);

        // Vérifier aliases
        if (isset(self::CITY_ALIASES[$normalized])) {
            return self::CITY_ALIASES[$normalized];
        }
        if (isset(self::CITY_ALIASES[$upper])) {
            return self::CITY_ALIASES[$upper];
        }

        // Vérifier si c'est une ville valide
        if (in_array($normalized, self::CI_CITIES) || in_array($upper, self::CI_CITIES)) {
            return $upper;
        }

        return null;
    }

    /**
     * Retire les accents d'une chaîne
     */
    private function removeAccents(string $str): string {
        $accents = [
            'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A',
            'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E',
            'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I',
            'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O',
            'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U',
            'Ç'=>'C', 'Ñ'=>'N',
            'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a',
            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e',
            'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i',
            'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o',
            'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ü'=>'u',
            'ç'=>'c', 'ñ'=>'n'
        ];
        return strtr($str, $accents);
    }

    /**
     * Extrait l'adresse de l'hôtel
     */
    private function extractHotelAddress(string $text): array {
        $result = ['address' => null, 'city' => null, 'country' => null];
        $textUpper = strtoupper($text);
        $textNormalized = $this->removeAccents($textUpper);

        // 1. Chercher une ville de Côte d'Ivoire (case-insensitive avec aliases)
        foreach (self::CI_CITIES as $city) {
            // Recherche directe
            if (stripos($textNormalized, $city) !== false) {
                $result['city'] = $city;
                $result['country'] = 'COTE D\'IVOIRE';
                break;
            }
        }

        // 2. Si pas trouvé, chercher via aliases
        if (!$result['city']) {
            foreach (self::CITY_ALIASES as $alias => $normalized) {
                if (stripos($textNormalized, $alias) !== false ||
                    stripos($textUpper, $alias) !== false) {
                    $result['city'] = $normalized;
                    $result['country'] = 'COTE D\'IVOIRE';
                    break;
                }
            }
        }

        // 3. Recherche par pattern contextuel (ville après "à", "in", etc.)
        if (!$result['city']) {
            $cityPatterns = [
                '/(?:À|A|IN|AT)\s+([A-Z][A-Z\-\s]+),?\s*(?:COTE|IVORY|CI)/i',
                '/(?:CITY|VILLE)[:\s]*([A-Z][A-Z\-\s]+)/i',
                '/(?:LOCATION|LOCALISATION)[:\s]*[^,]*,\s*([A-Z][A-Z\-\s]+)/i'
            ];

            foreach ($cityPatterns as $pattern) {
                if (preg_match($pattern, $text, $match)) {
                    $potentialCity = $this->normalizeCity($match[1]);
                    if ($potentialCity) {
                        $result['city'] = $potentialCity;
                        $result['country'] = 'COTE D\'IVOIRE';
                        break;
                    }
                }
            }
        }

        // 4. Pattern pour adresse complète
        $addressPattern = '/(?:ADDRESS|ADRESSE|LOCATION)[:\s]*([^\n]+)/i';
        if (preg_match($addressPattern, $text, $match)) {
            $result['address'] = trim($match[1]);

            // Essayer d'extraire la ville de l'adresse
            if (!$result['city']) {
                $addressParts = preg_split('/[,;]/', $result['address']);
                foreach ($addressParts as $part) {
                    $potentialCity = $this->normalizeCity(trim($part));
                    if ($potentialCity) {
                        $result['city'] = $potentialCity;
                        $result['country'] = 'COTE D\'IVOIRE';
                        break;
                    }
                }
            }
        }

        // 5. Chercher mention explicite de Côte d'Ivoire
        if (preg_match('/(?:IVORY\s*COAST|C[OÔ]TE\s*D[\'\'\\x60]?IVOIRE|CI\b|CIV\b)/i', $text)) {
            $result['country'] = 'COTE D\'IVOIRE';
        }

        return $result;
    }

    /**
     * Extrait les dates de séjour
     */
    private function extractStayDates(string $text): array {
        $result = ['check_in' => null, 'check_out' => null];

        // Pattern explicite check-in/check-out
        $patterns = [
            'check_in' => [
                '/(?:CHECK[\-\s]?IN|ARRIVAL|ARRIVEE|FROM)[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
                '/(?:CHECK[\-\s]?IN|ARRIVAL)[:\s]*(\d{1,2}\s+\w+\s+\d{4})/i'
            ],
            'check_out' => [
                '/(?:CHECK[\-\s]?OUT|DEPARTURE|DEPART|TO|UNTIL)[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
                '/(?:CHECK[\-\s]?OUT|DEPARTURE)[:\s]*(\d{1,2}\s+\w+\s+\d{4})/i'
            ]
        ];

        foreach ($patterns as $type => $typePatterns) {
            foreach ($typePatterns as $pattern) {
                if (preg_match($pattern, $text, $match)) {
                    $result[$type] = $this->parseDate($match[1]);
                    break;
                }
            }
        }

        // Si pas trouvé, chercher deux dates consécutives
        if (!$result['check_in'] || !$result['check_out']) {
            $allDates = [];
            preg_match_all('/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i', $text, $matches);

            foreach ($matches[1] as $dateStr) {
                $parsed = $this->parseDate($dateStr);
                if ($parsed) {
                    $allDates[] = $parsed;
                }
            }

            sort($allDates);

            if (count($allDates) >= 2) {
                $result['check_in'] = $result['check_in'] ?? $allDates[0];
                $result['check_out'] = $result['check_out'] ?? $allDates[count($allDates) - 1];
            }
        }

        return $result;
    }

    /**
     * Calcule le nombre de nuits
     */
    private function calculateNights(string $checkIn, string $checkOut): int {
        $in = strtotime($checkIn);
        $out = strtotime($checkOut);

        if ($in === false || $out === false) {
            return 0;
        }

        return (int)ceil(($out - $in) / 86400);
    }

    /**
     * Extrait le numéro de confirmation
     */
    private function extractConfirmationNumber(string $text): ?string {
        $patterns = [
            '/(?:CONFIRMATION|BOOKING|RESERVATION)\s*(?:NO|NUMBER|N°|#)?[:\s]*([A-Z0-9\-]{6,20})/i',
            '/(?:REF|REFERENCE)[:\s]*([A-Z0-9\-]{6,20})/i',
            '/\b([0-9]{8,12})\b/' // Numéro purement numérique
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return strtoupper($match[1]);
            }
        }

        return null;
    }

    /**
     * Extrait le type de chambre
     */
    private function extractRoomType(string $text): ?string {
        $roomTypes = [
            'SINGLE', 'DOUBLE', 'TWIN', 'TRIPLE', 'QUADRUPLE', 'SUITE',
            'DELUXE', 'SUPERIOR', 'STANDARD', 'EXECUTIVE', 'FAMILY',
            'STUDIO', 'APARTMENT', 'VILLA', 'BUNGALOW', 'PENTHOUSE'
        ];

        foreach ($roomTypes as $type) {
            if (preg_match('/\b' . $type . '\b/i', $text)) {
                return $type;
            }
        }

        // Pattern plus générique
        if (preg_match('/(?:ROOM|CHAMBRE)\s*(?:TYPE)?[:\s]*([A-Za-z\s]+)/i', $text, $match)) {
            return strtoupper(trim($match[1]));
        }

        return null;
    }

    /**
     * Détecte la plateforme de réservation
     */
    private function detectBookingPlatform(string $text): ?string {
        $textUpper = strtoupper($text);

        foreach (self::BOOKING_PLATFORMS as $platform) {
            if (strpos($textUpper, $platform) !== false) {
                return $platform;
            }
        }

        return null;
    }

    /**
     * Extrait le montant total
     */
    private function extractTotalAmount(string $text): ?array {
        $patterns = [
            '/(?:TOTAL|AMOUNT|MONTANT|PRICE|PRIX)[:\s]*([0-9,.\s]+)\s*(XOF|CFA|EUR|USD|FCFA)?/i',
            '/([0-9,.\s]+)\s*(XOF|CFA|EUR|USD|FCFA)\s*(?:TOTAL)?/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return $this->parseAmount($match[0]);
            }
        }

        return null;
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

        // Validation localisation en Côte d'Ivoire (avec normalisation)
        if (isset($extracted['hotel_city'])) {
            $normalizedCity = $this->normalizeCity($extracted['hotel_city']);
            $validations['location_is_cote_divoire'] = $normalizedCity !== null;
        } elseif (isset($extracted['hotel_country'])) {
            $validations['location_is_cote_divoire'] = stripos($extracted['hotel_country'], 'IVOIRE') !== false;
        }

        // Validation dates dans le futur
        if (isset($extracted['check_in_date'])) {
            $validations['dates_are_future'] = $this->isFutureDate($extracted['check_in_date']);
        }

        // Validation cohérence des dates
        if (isset($extracted['check_in_date'], $extracted['check_out_date'])) {
            $checkIn = strtotime($extracted['check_in_date']);
            $checkOut = strtotime($extracted['check_out_date']);
            $validations['dates_coherent'] = $checkOut > $checkIn;
        }

        // Validation présence numéro de confirmation
        $validations['confirmation_number_present'] = !empty($extracted['confirmation_number']);

        // Validation durée raisonnable (max 90 jours pour visa court séjour)
        if (isset($extracted['nights'])) {
            $validations['stay_duration_valid'] = $extracted['nights'] > 0 && $extracted['nights'] <= 90;
        }

        return $validations;
    }

    public function getDocumentType(): string {
        return 'hotel';
    }

    public function getPrdCode(): string {
        return 'DOC_HOTEL';
    }
}
