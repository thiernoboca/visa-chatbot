<?php
/**
 * Extracteur de Billet d'Avion
 * Ambassade de Côte d'Ivoire en Éthiopie
 *
 * Conforme au PRD: DOC_BILLET
 * Extrait: passager, vol, dates, compagnie, destination
 *
 * @package VisaChatbot\Extractors
 * @version 1.0.0
 */

namespace VisaChatbot\Extractors;

require_once __DIR__ . '/AbstractExtractor.php';

class FlightTicketExtractor extends AbstractExtractor {

    /**
     * Codes IATA des aéroports de Côte d'Ivoire
     */
    public const CI_AIRPORTS = [
        'ABJ' => 'Abidjan - Félix Houphouët-Boigny',
        'BYK' => 'Bouaké',
        'MJC' => 'Man',
        'SPY' => 'San Pedro',
        'OGO' => 'Odienné',
        'HGO' => 'Korhogo'
    ];

    /**
     * Codes IATA des aéroports de la circonscription
     */
    public const JURISDICTION_AIRPORTS = [
        'ADD' => 'Addis Ababa (Éthiopie)',
        'JIB' => 'Djibouti',
        'ASM' => 'Asmara (Érythrée)',
        'NBO' => 'Nairobi (Kenya)',
        'MBA' => 'Mombasa (Kenya)',
        'EBB' => 'Entebbe (Ouganda)',
        'MGQ' => 'Mogadishu (Somalie)',
        'JUB' => 'Juba (Soudan du Sud)'
    ];

    /**
     * Principales compagnies aériennes
     */
    public const AIRLINES = [
        'ET' => 'Ethiopian Airlines',
        'KQ' => 'Kenya Airways',
        'AF' => 'Air France',
        'TK' => 'Turkish Airlines',
        'EK' => 'Emirates',
        'QR' => 'Qatar Airways',
        'LH' => 'Lufthansa',
        'BA' => 'British Airways',
        'KL' => 'KLM',
        'MS' => 'EgyptAir',
        'WB' => 'RwandAir',
        'HF' => 'Air Côte d\'Ivoire',
        'W3' => 'ASKY Airlines'
    ];

    protected array $requiredFields = [
        'passenger_name',
        'flight_number',
        'departure_airport',
        'arrival_airport',
        'departure_date'
    ];

    protected array $optionalFields = [
        'airline',
        'departure_time',
        'arrival_date',
        'arrival_time',
        'booking_reference',
        'ticket_number',
        'seat',
        'class'
    ];

    /**
     * Extrait les données du billet d'avion
     */
    public function extract(string $rawText, array $ocrMetadata = []): array {
        $result = [
            'success' => false,
            'extracted' => [],
            'flights' => [],
            'is_round_trip' => false
        ];

        $text = $this->cleanOcrText($rawText);
        $textUpper = strtoupper($text);

        // 1. Extraire le nom du passager
        $result['extracted']['passenger_name'] = $this->extractPassengerName($text);

        // 2. Extraire les vols (potentiellement multiples segments)
        $result['flights'] = $this->extractFlights($textUpper);

        // 3. Identifier le vol principal (vers Abidjan)
        $mainFlight = $this->identifyMainFlight($result['flights']);
        if ($mainFlight) {
            $result['extracted'] = array_merge($result['extracted'], $mainFlight);
        }

        // 4. Extraire la référence de réservation
        $result['extracted']['booking_reference'] = $this->extractBookingReference($textUpper);

        // 5. Extraire le numéro de billet
        $result['extracted']['ticket_number'] = $this->extractTicketNumber($textUpper);

        // 6. Détecter si aller-retour
        $result['is_round_trip'] = $this->isRoundTrip($result['flights']);

        // 7. Succès si champs requis présents
        $result['success'] = $this->hasRequiredFields($result['extracted']);

        return $result;
    }

    /**
     * Extrait le nom du passager
     */
    private function extractPassengerName(string $text): ?string {
        $patterns = [
            // Format: PASSENGER NAME / NOM DU PASSAGER: BEKELE/ABEBE TESHOME MR (use lookahead for next field)
            '#(?:PASSENGER\s*NAME\s*/\s*NOM\s*DU\s*PASSAGER|PASSENGER\s*NAME|NOM\s*DU\s*PASSAGER)\s*:\s*([A-Z][A-Z\-\'\s/]+?)(?:\s+(?:MR|MRS|MS|MLLE|MME))?(?=\s+(?:FLIGHT|VOL|BOOKING|DATE|FROM|TO|TICKET|DETAILS|STATUS|CLASS|SEAT|\d))#i',
            // Format: Passenger: NAME/SURNAME
            '#(?:Passenger|PASSENGER)\s*:\s*([A-Z][A-Z\-\'\s/]+?)(?:\s+(?:MR|MRS|MS))?(?=\s+(?:FLIGHT|VOL|BOOKING|DATE|FROM|OUTBOUND|RETURN))#i',
            // Format: NOM/PRENOM directly (no label)
            '#\b([A-Z]{2,})/([A-Z][A-Z\s]+?)\s*(?:MR|MRS|MS)?(?=\s+(?:FLIGHT|VOL|BOOKING|DATE|FROM|E-TICKET|TICKET))#i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $name = trim($match[1]);
                if (isset($match[2]) && !empty($match[2])) {
                    $name = trim($match[1]) . '/' . trim($match[2]);
                }
                // Nettoyer les titres à la fin
                $name = preg_replace('/\s+(MR|MRS|MS|MLLE|MME)\s*$/i', '', $name);
                $name = trim($name);
                if (!empty($name) && strlen($name) > 3) {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * Extrait tous les segments de vol
     */
    private function extractFlights(string $text): array {
        $flights = [];

        // Pattern 1: Format standard après nettoyage OCR (tout sur une ligne)
        // ET508 FROM: ADDIS ABABA (ADD) ... TO: ABIDJAN (ABJ) ... DATE: 15 JAN 2025
        $pattern1 = '/([A-Z]{2})\s*(\d{3,4})\s+FROM:?\s*[^(]*\(([A-Z]{3})\)[^T]*TO:?\s*[^(]*\(([A-Z]{3})\).*?DATE:?\s*(\d{1,2}\s+[A-Z]+\s+\d{4}|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/i';

        if (preg_match_all($pattern1, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $flight = [
                    'airline_code' => strtoupper($match[1]),
                    'airline_name' => self::AIRLINES[strtoupper($match[1])] ?? $match[1],
                    'flight_number' => strtoupper($match[1]) . $match[2],
                    'departure_airport' => strtoupper($match[3]),
                    'arrival_airport' => strtoupper($match[4]),
                    'departure_date' => $this->parseDateText($match[5])
                ];
                $flights[] = $flight;
            }
        }

        // Pattern 2: Format Kenya Airways round trip
        // KQ 510 Departure: NAIROBI (NBO) - 20 JAN 2025 ... Arrival: ABIDJAN (ABJ)
        if (empty($flights)) {
            $pattern2 = '/([A-Z]{2})\s*(\d{3,4})\s+Departure:?\s*[^(]*\(([A-Z]{3})\)\s*-?\s*(\d{1,2}\s+[A-Z]+\s+\d{4}).*?Arrival:?\s*[^(]*\(([A-Z]{3})\)/i';

            if (preg_match_all($pattern2, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $flight = [
                        'airline_code' => strtoupper($match[1]),
                        'airline_name' => self::AIRLINES[strtoupper($match[1])] ?? $match[1],
                        'flight_number' => strtoupper($match[1]) . $match[2],
                        'departure_airport' => strtoupper($match[3]),
                        'departure_date' => $this->parseDateText($match[4]),
                        'arrival_airport' => strtoupper($match[5])
                    ];
                    $flights[] = $flight;
                }
            }
        }

        // Pattern 3: Format compact (code vol suivi immédiatement des aéroports)
        if (empty($flights)) {
            $pattern3 = '/([A-Z]{2,3})\s*(\d{3,4})\s+(?:FROM\s+)?([A-Z]{3})\s*(?:TO|->|→|-)\s*([A-Z]{3})\s+(\d{1,2}[\/\-]\w+[\/\-]\d{2,4}|\d{1,2}\s+\w+\s+\d{4})/i';

            if (preg_match_all($pattern3, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $flight = [
                        'airline_code' => strtoupper($match[1]),
                        'airline_name' => self::AIRLINES[strtoupper($match[1])] ?? $match[1],
                        'flight_number' => strtoupper($match[1]) . $match[2],
                        'departure_airport' => strtoupper($match[3]),
                        'arrival_airport' => strtoupper($match[4]),
                        'departure_date' => $this->parseDate($match[5])
                    ];
                    $flights[] = $flight;
                }
            }
        }

        // Fallback: extraction alternative
        if (empty($flights)) {
            $flights = $this->extractFlightsAlternative($text);
        }

        return $flights;
    }

    /**
     * Parse une date au format texte (15 JAN 2025)
     */
    private function parseDateText(string $dateStr): ?string {
        $monthMap = [
            'JAN' => '01', 'JANUARY' => '01',
            'FEB' => '02', 'FEBRUARY' => '02',
            'MAR' => '03', 'MARCH' => '03',
            'APR' => '04', 'APRIL' => '04',
            'MAY' => '05',
            'JUN' => '06', 'JUNE' => '06',
            'JUL' => '07', 'JULY' => '07',
            'AUG' => '08', 'AUGUST' => '08',
            'SEP' => '09', 'SEPTEMBER' => '09',
            'OCT' => '10', 'OCTOBER' => '10',
            'NOV' => '11', 'NOVEMBER' => '11',
            'DEC' => '12', 'DECEMBER' => '12'
        ];

        if (preg_match('/(\d{1,2})\s+([A-Z]+)\s+(\d{4})/i', $dateStr, $match)) {
            $month = $monthMap[strtoupper($match[2])] ?? null;
            if ($month) {
                $day = str_pad($match[1], 2, '0', STR_PAD_LEFT);
                return "{$match[3]}-{$month}-{$day}";
            }
        }

        // Essayer le format numérique
        return $this->parseDate($dateStr);
    }

    /**
     * Extraction alternative des vols
     */
    private function extractFlightsAlternative(string $text): array {
        $flights = [];
        $flight = [];

        // Chercher codes aéroport
        preg_match_all('/\b([A-Z]{3})\b/', $text, $airportMatches);
        $airports = array_unique($airportMatches[1]);

        // Filtrer les aéroports valides (CI ou circonscription)
        $validAirports = [];
        foreach ($airports as $code) {
            if (isset(self::CI_AIRPORTS[$code]) || isset(self::JURISDICTION_AIRPORTS[$code]) ||
                $this->isKnownAirport($code)) {
                $validAirports[] = $code;
            }
        }

        if (count($validAirports) >= 2) {
            $flight['departure_airport'] = $validAirports[0];
            $flight['arrival_airport'] = $validAirports[1];
        }

        // Chercher numéro de vol
        if (preg_match('/([A-Z]{2})\s*(\d{3,4})/i', $text, $flightMatch)) {
            $flight['airline_code'] = $flightMatch[1];
            $flight['flight_number'] = $flightMatch[1] . $flightMatch[2];
            $flight['airline_name'] = self::AIRLINES[$flightMatch[1]] ?? $flightMatch[1];
        }

        // Chercher dates - plusieurs formats
        $dates = [];

        // Format numérique: 15/01/2025, 15-01-2025
        preg_match_all('/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i', $text, $dateMatches);
        foreach ($dateMatches[1] as $dateStr) {
            $parsed = $this->parseDate($dateStr);
            if ($parsed) {
                $dates[] = $parsed;
            }
        }

        // Format texte: 15 JAN 2025, 15 JANUARY 2025
        preg_match_all('/(\d{1,2})\s+(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC|JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)\s+(\d{4})/i', $text, $textDateMatches, PREG_SET_ORDER);
        foreach ($textDateMatches as $match) {
            $monthMap = [
                'JAN' => '01', 'JANUARY' => '01',
                'FEB' => '02', 'FEBRUARY' => '02',
                'MAR' => '03', 'MARCH' => '03',
                'APR' => '04', 'APRIL' => '04',
                'MAY' => '05',
                'JUN' => '06', 'JUNE' => '06',
                'JUL' => '07', 'JULY' => '07',
                'AUG' => '08', 'AUGUST' => '08',
                'SEP' => '09', 'SEPTEMBER' => '09',
                'OCT' => '10', 'OCTOBER' => '10',
                'NOV' => '11', 'NOVEMBER' => '11',
                'DEC' => '12', 'DECEMBER' => '12'
            ];
            $month = $monthMap[strtoupper($match[2])] ?? '01';
            $day = str_pad($match[1], 2, '0', STR_PAD_LEFT);
            $dates[] = "{$match[3]}-{$month}-{$day}";
        }

        if (!empty($dates)) {
            sort($dates);
            $flight['departure_date'] = $dates[0];
            if (count($dates) > 1) {
                $flight['arrival_date'] = $dates[count($dates) - 1];
            }
        }

        if (!empty($flight)) {
            $flights[] = $flight;
        }

        return $flights;
    }

    /**
     * Vérifie si c'est un code aéroport connu
     */
    private function isKnownAirport(string $code): bool {
        // Liste étendue des principaux aéroports
        $knownCodes = ['CDG', 'ORY', 'LHR', 'AMS', 'FRA', 'IST', 'DXB', 'DOH', 'JFK', 'CAI'];
        return in_array($code, $knownCodes);
    }

    /**
     * Identifie le vol principal (vers Abidjan) et calcule la date d'arrivée
     */
    private function identifyMainFlight(array $flights): ?array {
        if (empty($flights)) {
            return null;
        }

        // Chercher un vol vers Abidjan
        $mainFlight = null;
        foreach ($flights as $flight) {
            if (isset(self::CI_AIRPORTS[$flight['arrival_airport'] ?? ''])) {
                $mainFlight = $flight;
                break;
            }
        }

        // Sinon utiliser le premier vol
        if ($mainFlight === null) {
            $mainFlight = $flights[0];
        }

        // Calculer la date d'arrivée si non présente
        // Pour les vols multi-segments, prendre la date du dernier segment vers ABJ
        if (!isset($mainFlight['arrival_date']) || $mainFlight['arrival_date'] === null) {
            // Si multi-segments, chercher le dernier vol vers CI
            $lastFlightToCI = null;
            foreach ($flights as $flight) {
                if (isset(self::CI_AIRPORTS[$flight['arrival_airport'] ?? ''])) {
                    $lastFlightToCI = $flight;
                }
            }

            if ($lastFlightToCI && isset($lastFlightToCI['departure_date'])) {
                // La date d'arrivée est généralement le même jour ou le lendemain
                $mainFlight['arrival_date'] = $lastFlightToCI['departure_date'];
            } elseif (isset($mainFlight['departure_date'])) {
                // Fallback: même date que le départ (vol court-courrier)
                $mainFlight['arrival_date'] = $mainFlight['departure_date'];
            }
        }

        // Mapper departure_airport vers departure_city et arrival_airport vers arrival_city
        if (isset($mainFlight['departure_airport'])) {
            $mainFlight['departure_city'] = self::JURISDICTION_AIRPORTS[$mainFlight['departure_airport']]
                ?? $mainFlight['departure_airport'];
        }
        if (isset($mainFlight['arrival_airport'])) {
            $mainFlight['arrival_city'] = self::CI_AIRPORTS[$mainFlight['arrival_airport']]
                ?? $mainFlight['arrival_airport'];
        }

        return $mainFlight;
    }

    /**
     * Extrait la référence de réservation (PNR)
     */
    private function extractBookingReference(string $text): ?string {
        $patterns = [
            // Format complet: BOOKING REFERENCE: ABCDEF
            '/BOOKING\s+(?:REF(?:ERENCE)?|NO)[:\s#]+([A-Z0-9]{5,8})/i',
            // Format simple avec label
            '/(?:REF|REFERENCE|PNR|CONFIRMATION|DOSSIER|BOOKING)\s*[:\s#]+([A-Z0-9]{5,8})/i',
            // Format avant le mot BOOKING/REF
            '/\b([A-Z0-9]{6})\b\s+(?:BOOKING|REF|CONFIRMATION)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $ref = strtoupper($match[1]);
                // S'assurer que ce n'est pas un faux positif (numéro de vol, etc.)
                if (!preg_match('/^(?:ET|KQ|AF|TK|EK)\d{3,4}$/', $ref)) {
                    return $ref;
                }
            }
        }

        return null;
    }

    /**
     * Extrait le numéro de billet électronique
     */
    private function extractTicketNumber(string $text): ?string {
        // Format: 13 chiffres commençant par le code compagnie (3 chiffres)
        $patterns = [
            '/(?:TICKET|BILLET|ETKT|E-TICKET)[:\s#]*(\d{13,14})/i',
            '/\b(\d{3}[-\s]?\d{10})\b/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return preg_replace('/[\s\-]/', '', $match[1]);
            }
        }

        return null;
    }

    /**
     * Vérifie si c'est un aller-retour
     */
    private function isRoundTrip(array $flights): bool {
        if (count($flights) < 2) {
            return false;
        }

        $first = $flights[0];
        $last = $flights[count($flights) - 1];

        return ($first['departure_airport'] ?? '') === ($last['arrival_airport'] ?? '');
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

        // Validation destination Côte d'Ivoire
        if (isset($extracted['arrival_airport'])) {
            $validations['destination_is_abidjan'] = isset(self::CI_AIRPORTS[$extracted['arrival_airport']]);
        }

        // Validation date dans le futur
        if (isset($extracted['departure_date'])) {
            $validations['date_is_future'] = $this->isFutureDate($extracted['departure_date']);
        }

        // Validation départ depuis la circonscription
        if (isset($extracted['departure_airport'])) {
            $validations['departure_in_jurisdiction'] = isset(self::JURISDICTION_AIRPORTS[$extracted['departure_airport']]);
        }

        // Validation format nom passager
        if (isset($extracted['passenger_name'])) {
            $validations['passenger_name_format_valid'] = strlen($extracted['passenger_name']) >= 3;
        }

        // Validation numéro de vol
        if (isset($extracted['flight_number'])) {
            $validations['flight_number_valid'] = preg_match('/^[A-Z]{2}\d{3,4}$/', $extracted['flight_number']);
        }

        return $validations;
    }

    public function getDocumentType(): string {
        return 'ticket';
    }

    public function getPrdCode(): string {
        return 'DOC_BILLET';
    }
}
