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
            // Ethiopian Airlines format: PASSENGER NAME followed by NAME/SURNAME MR
            '#PASSENGER\s+NAME\s+([A-Z][A-Z\-\']+/[A-Z][A-Z\s\-\']+?)(?:\s+(?:MR|MRS|MS|MLLE|MME))?(?=\s+(?:ISSUE|DATE|FLIGHT|TICKET))#i',
            // Format: PASSENGER NAME / NOM DU PASSAGER: BEKELE/ABEBE TESHOME MR (use lookahead for next field)
            '#(?:PASSENGER\s*NAME\s*/\s*NOM\s*DU\s*PASSAGER|PASSENGER\s*NAME|NOM\s*DU\s*PASSAGER)\s*:?\s*([A-Z][A-Z\-\'\s/]+?)(?:\s+(?:MR|MRS|MS|MLLE|MME))?(?=\s+(?:ISSUE|FLIGHT|VOL|BOOKING|DATE|FROM|TO|TICKET|DETAILS|STATUS|CLASS|SEAT|\d))#i',
            // Format: Passenger: NAME/SURNAME
            '#(?:Passenger|PASSENGER)\s*:\s*([A-Z][A-Z\-\'\s/]+?)(?:\s+(?:MR|MRS|MS))?(?=\s+(?:ISSUE|FLIGHT|VOL|BOOKING|DATE|FROM|OUTBOUND|RETURN))#i',
            // Format: NOM/PRENOM directly (no label)
            '#\b([A-Z]{2,})/([A-Z][A-Z\s]+?)\s*(?:MR|MRS|MS)?(?=\s+(?:ISSUE|FLIGHT|VOL|BOOKING|DATE|FROM|E-TICKET|TICKET))#i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $name = trim($match[1]);
                if (isset($match[2]) && !empty($match[2])) {
                    $name = trim($match[1]) . '/' . trim($match[2]);
                }
                // Nettoyer les titres à la fin et mots parasites
                $name = preg_replace('/\s+(MR|MRS|MS|MLLE|MME|ISSUE|DATE)\s*$/i', '', $name);
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

        // Pattern 0: Ethiopian Airlines e-ticket - extract flight segments
        // Split text into flight segments (each starts with "ET XXX")
        $flightSegments = preg_split('/(?=\bET\s+\d{3,4}\b)/i', $text);

        foreach ($flightSegments as $segment) {
            // Extract flight number: "ET 935" or "ET935"
            if (!preg_match('/\b(ET)\s*(\d{3,4})\b/i', $segment, $flightMatch)) {
                continue;
            }

            $flight = [
                'airline_code' => 'ET',
                'airline_name' => 'Ethiopian Airlines',
                'flight_number' => 'ET' . $flightMatch[2]
            ];

            // Extract airport codes (3 uppercase letters in parentheses)
            preg_match_all('/\(([A-Z]{3})\)/i', $segment, $airportMatches);
            $airports = $airportMatches[1] ?? [];

            if (count($airports) >= 2) {
                $flight['departure_airport'] = strtoupper($airports[0]);
                $flight['arrival_airport'] = strtoupper($airports[1]);
            } elseif (count($airports) === 1) {
                // Check context for departure vs arrival
                $beforeAirport = strpos($segment, 'DEPART') !== false ||
                                 strpos($segment, 'FROM') !== false;
                if ($beforeAirport) {
                    $flight['departure_airport'] = strtoupper($airports[0]);
                } else {
                    $flight['arrival_airport'] = strtoupper($airports[0]);
                }
            }

            // Extract date: multiple formats
            // Format 1: DD/Mon/YYYY (28/Dec/2025)
            // Format 2: DD Mon YYYY (15 MAR 2026)
            // Format 3: DATE: DD Mon YYYY
            if (preg_match('/(\d{1,2})[\/\-]([A-Z]{3})[\/\-](\d{4})/i', $segment, $dateMatch)) {
                $flight['departure_date'] = $this->parseDateText($dateMatch[0]);
            } elseif (preg_match('/(?:DATE:?\s*)?(\d{1,2})\s+([A-Z]{3,9})\s+(\d{4})/i', $segment, $dateMatch)) {
                $flight['departure_date'] = $this->parseDateText($dateMatch[0]);
            }

            // Only add if we have essential info
            if (isset($flight['flight_number']) &&
                (isset($flight['departure_airport']) || isset($flight['arrival_airport']))) {
                $flights[] = $flight;
            }
        }

        // If Ethiopian pattern found flights, return them
        if (!empty($flights)) {
            return $flights;
        }

        // Pattern 1: Format standard après nettoyage OCR (tout sur une ligne)
        // ET508 FROM: ADDIS ABABA (ADD) ... TO: ABIDJAN (ABJ) ... DATE: 15 JAN 2025
        if (empty($flights)) {
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
     * Parse une date au format texte (15 JAN 2025, 28/Dec/2025)
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

        // Format: DD/Mon/YYYY (28/Dec/2025) - Ethiopian Airlines format
        if (preg_match('/(\d{1,2})[\/\-]([A-Z]{3})[\/\-](\d{4})/i', $dateStr, $match)) {
            $month = $monthMap[strtoupper($match[2])] ?? null;
            if ($month) {
                $day = str_pad($match[1], 2, '0', STR_PAD_LEFT);
                return "{$match[3]}-{$month}-{$day}";
            }
        }

        // Format: DD MMM YYYY (15 JAN 2025)
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

        // Chercher numéro de vol (excluding "Ok to fly" status text)
        if (preg_match('/\b([A-Z]{2})\s*(\d{3,4})\b(?!\s*Ok)/i', $text, $flightMatch)) {
            $flight['airline_code'] = strtoupper($flightMatch[1]);
            $flight['flight_number'] = strtoupper($flightMatch[1]) . $flightMatch[2];
            $flight['airline_name'] = self::AIRLINES[strtoupper($flightMatch[1])] ?? $flightMatch[1];
        }

        // Chercher dates - plusieurs formats, excluding issue dates
        $departureDates = [];
        $issueDates = [];

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

        // First, find Issue Date to exclude it
        if (preg_match('/ISSUE\s*DATE[:\s]*(\d{1,2})\s+([A-Z]+)\s+(\d{4})/i', $text, $issueMatch)) {
            $month = $monthMap[strtoupper($issueMatch[2])] ?? '01';
            $day = str_pad($issueMatch[1], 2, '0', STR_PAD_LEFT);
            $issueDates[] = "{$issueMatch[3]}-{$month}-{$day}";
        }

        // Format DD/Mon/YYYY (Ethiopian Airlines: 28/Dec/2025)
        preg_match_all('/(\d{1,2})[\/\-]([A-Z]{3})[\/\-](\d{4})/i', $text, $slashDateMatches, PREG_SET_ORDER);
        foreach ($slashDateMatches as $match) {
            $month = $monthMap[strtoupper($match[2])] ?? null;
            if ($month) {
                $day = str_pad($match[1], 2, '0', STR_PAD_LEFT);
                $date = "{$match[3]}-{$month}-{$day}";
                if (!in_array($date, $issueDates)) {
                    $departureDates[] = $date;
                }
            }
        }

        // Format numérique: 15/01/2025, 15-01-2025 (excluding issue dates context)
        // Note: Variable-length lookbehinds not supported in PHP PCRE, filtering done post-match
        preg_match_all('/(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})/i', $text, $dateMatches, PREG_SET_ORDER);
        foreach ($dateMatches ?? [] as $match) {
            $parsed = $this->parseDate($match[0]);
            if ($parsed && !in_array($parsed, $issueDates)) {
                $departureDates[] = $parsed;
            }
        }

        // Format texte: 15 JAN 2025 (excluding issue dates)
        preg_match_all('/(\d{1,2})\s+(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC|JANUARY|FEBRUARY|MARCH|APRIL|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)\s+(\d{4})/i', $text, $textDateMatches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($textDateMatches as $match) {
            $month = $monthMap[strtoupper($match[2][0])] ?? '01';
            $day = str_pad($match[1][0], 2, '0', STR_PAD_LEFT);
            $date = "{$match[3][0]}-{$month}-{$day}";

            // Check if this date is preceded by "Issue Date" (within 50 chars before)
            $offset = $match[0][1];
            $precedingText = substr($text, max(0, $offset - 50), 50);
            $isIssueDate = preg_match('/ISSUE\s*DATE/i', $precedingText);

            if (!$isIssueDate && !in_array($date, $issueDates)) {
                $departureDates[] = $date;
            }
        }

        // Use departure dates, filtering out issue dates
        $departureDates = array_unique($departureDates);
        if (!empty($departureDates)) {
            sort($departureDates);
            // Take the earliest date that is NOT an issue date
            $flight['departure_date'] = $departureDates[0];
            if (count($departureDates) > 1) {
                $flight['arrival_date'] = $departureDates[count($departureDates) - 1];
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
