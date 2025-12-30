<?php
/**
 * CrossDocumentSync - Cross-Document Validation & Synchronization
 *
 * Validates consistency between documents:
 * - Name matching across passport, ticket, hotel, etc.
 * - Date coherence between ticket and hotel
 * - Identity verification across all documents
 *
 * @version 6.0.0
 * @author Visa Chatbot Team
 */

class CrossDocumentSync {

    /**
     * Similarity threshold for name matching (0-100)
     */
    private const NAME_SIMILARITY_THRESHOLD = 85;

    /**
     * Fields that contain names (require fuzzy matching)
     */
    private const NAME_FIELDS = [
        'full_name', 'passenger_name', 'guest_name', 'patient_name',
        'invitee_name', 'holder_name', 'applicant_name', 'diplomat_name',
        'surname', 'given_names', 'inviter_name'
    ];

    /**
     * Fields that contain dates (require date comparison)
     */
    private const DATE_FIELDS = [
        'departure_date', 'return_date', 'check_in', 'check_out',
        'visit_from', 'visit_to', 'vaccination_date', 'date_of_birth',
        'expiry_date', 'issue_date'
    ];

    /**
     * Compare and validate extracted data against expected prefill
     *
     * @param array $extracted Extracted data from OCR
     * @param array $expected Expected data from prefill
     * @param string $docType Document type
     * @return array Validation result
     */
    public function compareAndValidate(array $extracted, array $expected, string $docType = 'unknown'): array {
        $discrepancies = [];
        $matches = [];
        $totalFields = 0;
        $matchedFields = 0;

        foreach ($expected as $field => $expectedValue) {
            if ($expectedValue === null || $expectedValue === '') {
                continue;
            }

            $totalFields++;
            $extractedValue = $this->findMatchingField($field, $extracted);

            if ($extractedValue === null) {
                // Field not found in extracted data
                $discrepancies[] = [
                    'field' => $field,
                    'type' => 'missing',
                    'expected' => $expectedValue,
                    'extracted' => null,
                    'severity' => $this->getSeverity($field)
                ];
                continue;
            }

            // Compare values
            $comparison = $this->compareValues($field, $expectedValue, $extractedValue);

            if ($comparison['match']) {
                $matchedFields++;
                $matches[] = [
                    'field' => $field,
                    'value' => $extractedValue,
                    'similarity' => $comparison['similarity'] ?? 100
                ];
            } else {
                $discrepancies[] = [
                    'field' => $field,
                    'type' => $comparison['type'],
                    'expected' => $expectedValue,
                    'extracted' => $extractedValue,
                    'similarity' => $comparison['similarity'] ?? 0,
                    'severity' => $this->getSeverity($field),
                    'message' => $comparison['message'] ?? null
                ];
            }
        }

        $overallScore = $totalFields > 0 ? (int)(($matchedFields / $totalFields) * 100) : 0;
        $isValid = $overallScore >= self::NAME_SIMILARITY_THRESHOLD && !$this->hasHighSeverityDiscrepancy($discrepancies);

        return [
            'valid' => $isValid,
            'has_prefill' => true,
            'overall_score' => $overallScore,
            'total_fields' => $totalFields,
            'matched_fields' => $matchedFields,
            'matches' => $matches,
            'discrepancies' => $discrepancies,
            'requires_user_confirmation' => !empty($discrepancies),
            'document_type' => $docType
        ];
    }

    /**
     * Compare two values based on field type
     *
     * @param string $field Field name
     * @param mixed $expected Expected value
     * @param mixed $extracted Extracted value
     * @return array Comparison result
     */
    private function compareValues(string $field, $expected, $extracted): array {
        // Handle null/empty cases
        if ($expected === null || $expected === '') {
            return ['match' => true, 'type' => 'empty'];
        }

        if ($extracted === null || $extracted === '') {
            return ['match' => false, 'type' => 'missing', 'similarity' => 0];
        }

        // Name fields: fuzzy matching
        if ($this->isNameField($field)) {
            return $this->compareNames($expected, $extracted);
        }

        // Date fields: date comparison
        if ($this->isDateField($field)) {
            return $this->compareDates($expected, $extracted);
        }

        // Numeric fields: exact or threshold match
        if (is_numeric($expected) && is_numeric($extracted)) {
            return $this->compareNumbers($expected, $extracted);
        }

        // Default: case-insensitive string comparison
        return $this->compareStrings($expected, $extracted);
    }

    /**
     * Compare names with fuzzy matching
     *
     * @param string $expected Expected name
     * @param string $extracted Extracted name
     * @return array Comparison result
     */
    private function compareNames(string $expected, string $extracted): array {
        // Normalize names
        $normalizedExpected = $this->normalizeName($expected);
        $normalizedExtracted = $this->normalizeName($extracted);

        // Exact match after normalization
        if ($normalizedExpected === $normalizedExtracted) {
            return ['match' => true, 'type' => 'exact', 'similarity' => 100];
        }

        // Calculate similarity
        $similarity = $this->calculateNameSimilarity($normalizedExpected, $normalizedExtracted);

        if ($similarity >= self::NAME_SIMILARITY_THRESHOLD) {
            return [
                'match' => true,
                'type' => 'fuzzy',
                'similarity' => $similarity,
                'message' => "Names are similar ({$similarity}% match)"
            ];
        }

        // Check if one contains the other (partial match)
        if (strpos($normalizedExpected, $normalizedExtracted) !== false ||
            strpos($normalizedExtracted, $normalizedExpected) !== false) {
            return [
                'match' => true,
                'type' => 'partial',
                'similarity' => $similarity,
                'message' => "Partial name match detected"
            ];
        }

        return [
            'match' => false,
            'type' => 'mismatch',
            'similarity' => $similarity,
            'message' => "Names differ: '{$expected}' vs '{$extracted}' ({$similarity}% similarity)"
        ];
    }

    /**
     * Compare dates
     *
     * @param string $expected Expected date
     * @param string $extracted Extracted date
     * @return array Comparison result
     */
    private function compareDates(string $expected, string $extracted): array {
        try {
            $expectedDate = new DateTime($expected);
            $extractedDate = new DateTime($extracted);

            $diff = $expectedDate->diff($extractedDate);
            $daysDiff = $diff->days;

            if ($daysDiff === 0) {
                return ['match' => true, 'type' => 'exact', 'similarity' => 100];
            }

            // Allow 1 day difference (timezone issues)
            if ($daysDiff <= 1) {
                return [
                    'match' => true,
                    'type' => 'close',
                    'similarity' => 95,
                    'message' => "Dates differ by 1 day (timezone adjustment)"
                ];
            }

            return [
                'match' => false,
                'type' => 'date_mismatch',
                'similarity' => max(0, 100 - ($daysDiff * 5)),
                'message' => "Dates differ by {$daysDiff} days"
            ];
        } catch (Exception $e) {
            // Try string comparison if date parsing fails
            return $this->compareStrings($expected, $extracted);
        }
    }

    /**
     * Compare numbers
     *
     * @param mixed $expected Expected number
     * @param mixed $extracted Extracted number
     * @return array Comparison result
     */
    private function compareNumbers($expected, $extracted): array {
        $expected = (float) $expected;
        $extracted = (float) $extracted;

        if ($expected == $extracted) {
            return ['match' => true, 'type' => 'exact', 'similarity' => 100];
        }

        // Calculate percentage difference
        $diff = abs($expected - $extracted);
        $average = ($expected + $extracted) / 2;
        $percentDiff = $average > 0 ? ($diff / $average) * 100 : 100;
        $similarity = max(0, 100 - $percentDiff);

        return [
            'match' => $similarity >= 95,
            'type' => $similarity >= 95 ? 'close' : 'mismatch',
            'similarity' => (int) $similarity
        ];
    }

    /**
     * Compare strings (case-insensitive)
     *
     * @param string $expected Expected string
     * @param string $extracted Extracted string
     * @return array Comparison result
     */
    private function compareStrings(string $expected, string $extracted): array {
        $normalizedExpected = strtolower(trim($expected));
        $normalizedExtracted = strtolower(trim($extracted));

        if ($normalizedExpected === $normalizedExtracted) {
            return ['match' => true, 'type' => 'exact', 'similarity' => 100];
        }

        // Use Levenshtein distance for similarity
        similar_text($normalizedExpected, $normalizedExtracted, $percent);
        $similarity = (int) $percent;

        return [
            'match' => $similarity >= 85,
            'type' => $similarity >= 85 ? 'similar' : 'mismatch',
            'similarity' => $similarity
        ];
    }

    /**
     * Normalize name for comparison
     *
     * @param string $name Name to normalize
     * @return string Normalized name
     */
    private function normalizeName(string $name): string {
        // Convert to uppercase
        $name = mb_strtoupper($name, 'UTF-8');

        // Remove common titles
        $titles = ['MR', 'MRS', 'MS', 'MISS', 'DR', 'PROF', 'M', 'MME', 'MLLE'];
        foreach ($titles as $title) {
            $name = preg_replace('/\b' . $title . '\.?\s*/i', '', $name);
        }

        // Remove accents
        $name = $this->removeAccents($name);

        // Remove extra whitespace
        $name = preg_replace('/\s+/', ' ', trim($name));

        // Remove special characters except spaces
        $name = preg_replace('/[^A-Z\s]/', '', $name);

        return $name;
    }

    /**
     * Remove accents from string
     *
     * @param string $str String with accents
     * @return string String without accents
     */
    private function removeAccents(string $str): string {
        $accents = [
            'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A',
            'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E',
            'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I',
            'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O',
            'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U',
            'Ý'=>'Y', 'Ñ'=>'N', 'Ç'=>'C'
        ];
        return strtr($str, $accents);
    }

    /**
     * Calculate name similarity using multiple algorithms
     *
     * @param string $name1 First name
     * @param string $name2 Second name
     * @return int Similarity percentage
     */
    private function calculateNameSimilarity(string $name1, string $name2): int {
        // Method 1: similar_text
        similar_text($name1, $name2, $similarity1);

        // Method 2: Levenshtein distance based
        $maxLen = max(strlen($name1), strlen($name2));
        if ($maxLen === 0) {
            return 100;
        }
        $levenshtein = levenshtein($name1, $name2);
        $similarity2 = (1 - ($levenshtein / $maxLen)) * 100;

        // Method 3: Word-based matching (for reordered names)
        $words1 = array_filter(explode(' ', $name1));
        $words2 = array_filter(explode(' ', $name2));
        $commonWords = array_intersect($words1, $words2);
        $totalWords = max(count($words1), count($words2));
        $similarity3 = $totalWords > 0 ? (count($commonWords) / $totalWords) * 100 : 0;

        // Return weighted average, prioritizing word-based for name reordering
        return (int) (($similarity1 * 0.3) + ($similarity2 * 0.3) + ($similarity3 * 0.4));
    }

    /**
     * Find matching field in extracted data (handles field name variations)
     *
     * @param string $field Expected field name
     * @param array $extracted Extracted data
     * @return mixed|null Field value or null
     */
    private function findMatchingField(string $field, array $extracted) {
        // Direct match
        if (isset($extracted[$field])) {
            return $extracted[$field];
        }

        // Common field name variations
        $variations = [
            'full_name' => ['name', 'passenger_name', 'guest_name', 'holder_name', 'patient_name'],
            'surname' => ['last_name', 'family_name'],
            'given_names' => ['first_name', 'first_names', 'forename'],
            'check_in' => ['check_in_date', 'checkin', 'arrival_date'],
            'check_out' => ['check_out_date', 'checkout', 'departure_date'],
            'date_of_birth' => ['dob', 'birth_date', 'birthdate'],
            'departure_date' => ['depart_date', 'outbound_date'],
            'return_date' => ['return', 'inbound_date', 'arrival_date']
        ];

        if (isset($variations[$field])) {
            foreach ($variations[$field] as $variation) {
                if (isset($extracted[$variation])) {
                    return $extracted[$variation];
                }
            }
        }

        return null;
    }

    /**
     * Check if field is a name field
     *
     * @param string $field Field name
     * @return bool
     */
    private function isNameField(string $field): bool {
        return in_array($field, self::NAME_FIELDS);
    }

    /**
     * Check if field is a date field
     *
     * @param string $field Field name
     * @return bool
     */
    private function isDateField(string $field): bool {
        return in_array($field, self::DATE_FIELDS);
    }

    /**
     * Get severity level for a field discrepancy
     *
     * @param string $field Field name
     * @return string Severity level (high, medium, low)
     */
    private function getSeverity(string $field): string {
        $highSeverityFields = ['full_name', 'surname', 'passport_number', 'date_of_birth'];
        $mediumSeverityFields = ['departure_date', 'return_date', 'check_in', 'check_out', 'nationality'];

        if (in_array($field, $highSeverityFields)) {
            return 'high';
        }

        if (in_array($field, $mediumSeverityFields)) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Check if discrepancies include high severity issues
     *
     * @param array $discrepancies List of discrepancies
     * @return bool
     */
    private function hasHighSeverityDiscrepancy(array $discrepancies): bool {
        foreach ($discrepancies as $d) {
            if (($d['severity'] ?? 'low') === 'high') {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate discrepancy message for user
     *
     * @param array $discrepancy Single discrepancy
     * @param string $lang Language (fr/en)
     * @return string User-friendly message
     */
    public function formatDiscrepancyMessage(array $discrepancy, string $lang = 'fr'): string {
        $field = $discrepancy['field'];
        $expected = $discrepancy['expected'];
        $extracted = $discrepancy['extracted'];
        $type = $discrepancy['type'] ?? 'mismatch';

        $fieldLabels = [
            'full_name' => ['fr' => 'Nom complet', 'en' => 'Full name'],
            'surname' => ['fr' => 'Nom de famille', 'en' => 'Surname'],
            'date_of_birth' => ['fr' => 'Date de naissance', 'en' => 'Date of birth'],
            'check_in' => ['fr' => 'Date d\'arrivée', 'en' => 'Check-in date'],
            'check_out' => ['fr' => 'Date de départ', 'en' => 'Check-out date'],
            'departure_date' => ['fr' => 'Date de départ', 'en' => 'Departure date'],
            'return_date' => ['fr' => 'Date de retour', 'en' => 'Return date']
        ];

        $fieldLabel = $fieldLabels[$field][$lang] ?? $field;

        if ($type === 'missing') {
            return $lang === 'fr'
                ? "{$fieldLabel}: Non trouvé dans le document (attendu: {$expected})"
                : "{$fieldLabel}: Not found in document (expected: {$expected})";
        }

        return $lang === 'fr'
            ? "{$fieldLabel}: '{$extracted}' diffère de '{$expected}'"
            : "{$fieldLabel}: '{$extracted}' differs from '{$expected}'";
    }

    /**
     * Validate coherence between invitation letter and flight ticket
     *
     * @param array $invitation Invitation letter data
     * @param array $ticket Flight ticket data
     * @return array Validation result with discrepancies
     */
    public function validateInvitationVsTicket(array $invitation, array $ticket): array {
        $discrepancies = [];
        $warnings = [];

        // 1. Extract dates from both documents
        $invitationArrival = $this->extractDate($invitation, ['visit_from', 'arrival_date', 'dates.from']);
        $invitationDeparture = $this->extractDate($invitation, ['visit_to', 'departure_date', 'dates.to']);
        $invitationDuration = $invitation['duration_days'] ?? $invitation['stay_duration'] ?? null;

        $ticketArrival = $this->extractDate($ticket, ['departure_date', 'outbound_date']);
        $ticketReturn = $this->extractDate($ticket, ['return_date', 'inbound_date']);

        // 2. Check arrival date mismatch
        if ($invitationArrival && $ticketArrival) {
            $invArrivalDate = new \DateTime($invitationArrival);
            $ticketArrivalDate = new \DateTime($ticketArrival);
            $daysDiff = (int) $invArrivalDate->diff($ticketArrivalDate)->format('%r%a');

            if (abs($daysDiff) > 1) {
                $discrepancies[] = [
                    'type' => 'arrival_date_mismatch',
                    'severity' => abs($daysDiff) > 7 ? 'high' : 'medium',
                    'invitation_date' => $invitationArrival,
                    'ticket_date' => $ticketArrival,
                    'days_difference' => abs($daysDiff),
                    'message_fr' => "Date d'arrivée: L'invitation indique le {$invitationArrival}, le billet le {$ticketArrival} (" . abs($daysDiff) . " jours d'écart)",
                    'message_en' => "Arrival date: Invitation shows {$invitationArrival}, ticket shows {$ticketArrival} (" . abs($daysDiff) . " days difference)"
                ];
            }
        }

        // 3. Calculate and compare duration
        if ($ticketArrival && $ticketReturn) {
            $ticketArrivalDate = new \DateTime($ticketArrival);
            $ticketReturnDate = new \DateTime($ticketReturn);
            $ticketDuration = (int) $ticketArrivalDate->diff($ticketReturnDate)->days;

            // Compare with invitation duration
            if ($invitationDuration !== null) {
                $durationDiff = abs($ticketDuration - (int) $invitationDuration);

                if ($durationDiff > 3) {
                    $severity = $durationDiff > 14 ? 'high' : 'medium';
                    $discrepancies[] = [
                        'type' => 'duration_mismatch',
                        'severity' => $severity,
                        'invitation_duration' => (int) $invitationDuration,
                        'ticket_duration' => $ticketDuration,
                        'days_difference' => $durationDiff,
                        'message_fr' => "Durée de séjour: L'invitation prévoit {$invitationDuration} jours, le billet couvre {$ticketDuration} jours (" . $durationDiff . " jours d'écart)",
                        'message_en' => "Stay duration: Invitation mentions {$invitationDuration} days, ticket covers {$ticketDuration} days ({$durationDiff} days difference)"
                    ];
                }
            }

            // Compare with invitation end date if no duration specified
            if ($invitationDeparture) {
                $invDepDate = new \DateTime($invitationDeparture);
                $returnDiff = (int) $invDepDate->diff($ticketReturnDate)->format('%r%a');

                if (abs($returnDiff) > 3) {
                    $warnings[] = [
                        'type' => 'return_date_mismatch',
                        'severity' => 'medium',
                        'invitation_date' => $invitationDeparture,
                        'ticket_date' => $ticketReturn,
                        'days_difference' => abs($returnDiff),
                        'message_fr' => "Date de retour: L'invitation prévoit le départ le {$invitationDeparture}, le vol retour est le {$ticketReturn}",
                        'message_en' => "Return date: Invitation expects departure on {$invitationDeparture}, return flight is on {$ticketReturn}"
                    ];
                }
            }
        }

        // 4. Check if ticket duration is significantly shorter than invitation
        if (isset($ticketDuration) && $invitationDuration !== null) {
            if ($ticketDuration < ((int) $invitationDuration * 0.5)) {
                $discrepancies[] = [
                    'type' => 'ticket_too_short',
                    'severity' => 'high',
                    'invitation_duration' => (int) $invitationDuration,
                    'ticket_duration' => $ticketDuration,
                    'message_fr' => "⚠️ Le billet couvre seulement {$ticketDuration} jours alors que l'invitation prévoit {$invitationDuration} jours. Incohérence majeure.",
                    'message_en' => "⚠️ Ticket only covers {$ticketDuration} days while invitation mentions {$invitationDuration} days. Major inconsistency."
                ];
            }
        }

        // 5. Calculate overall coherence score
        $totalChecks = 3; // arrival, duration, return
        $failedChecks = count($discrepancies);
        $coherenceScore = max(0, 100 - ($failedChecks * 30));

        return [
            'is_coherent' => empty($discrepancies),
            'coherence_score' => $coherenceScore,
            'discrepancies' => $discrepancies,
            'warnings' => $warnings,
            'summary' => [
                'invitation_arrival' => $invitationArrival,
                'invitation_departure' => $invitationDeparture,
                'invitation_duration' => $invitationDuration,
                'ticket_arrival' => $ticketArrival,
                'ticket_return' => $ticketReturn,
                'ticket_duration' => $ticketDuration ?? null
            ]
        ];
    }

    /**
     * Extract date from document using multiple possible keys
     *
     * @param array $doc Document data
     * @param array $possibleKeys List of possible keys
     * @return string|null Date in Y-m-d format or null
     */
    private function extractDate(array $doc, array $possibleKeys): ?string {
        foreach ($possibleKeys as $key) {
            // Support nested keys like 'dates.from'
            if (strpos($key, '.') !== false) {
                $parts = explode('.', $key);
                $value = $doc;
                foreach ($parts as $part) {
                    if (!isset($value[$part])) {
                        $value = null;
                        break;
                    }
                    $value = $value[$part];
                }
                if ($value !== null) {
                    return $this->normalizeDate($value);
                }
            } elseif (isset($doc[$key]) && !empty($doc[$key])) {
                return $this->normalizeDate($doc[$key]);
            }
        }
        return null;
    }

    /**
     * Normalize date to Y-m-d format
     *
     * @param string $date Date string
     * @return string|null Normalized date or null
     */
    private function normalizeDate(string $date): ?string {
        if (empty($date) || $date === 'N/A') {
            return null;
        }

        // Already in Y-m-d format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        // Try common formats
        $formats = [
            'd/m/Y', 'd-m-Y', 'd.m.Y',
            'Y/m/d', 'm/d/Y',
            'j F Y', 'F j, Y', 'd F Y'
        ];

        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $date);
            if ($parsed !== false) {
                return $parsed->format('Y-m-d');
            }
        }

        // Try strtotime
        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }

    /**
     * Validate hotel reservation against ticket dates
     *
     * @param array $hotel Hotel reservation data
     * @param array $ticket Flight ticket data
     * @return array Validation result
     */
    public function validateHotelVsTicket(array $hotel, array $ticket): array {
        $discrepancies = [];

        $hotelCheckIn = $this->extractDate($hotel, ['check_in_date', 'check_in']);
        $hotelCheckOut = $this->extractDate($hotel, ['check_out_date', 'check_out']);
        $ticketArrival = $this->extractDate($ticket, ['departure_date', 'outbound_date']);
        $ticketReturn = $this->extractDate($ticket, ['return_date', 'inbound_date']);

        // Hotel check-in should be close to flight arrival
        if ($hotelCheckIn && $ticketArrival) {
            $checkInDate = new \DateTime($hotelCheckIn);
            $arrivalDate = new \DateTime($ticketArrival);
            $diff = abs((int) $checkInDate->diff($arrivalDate)->format('%r%a'));

            if ($diff > 1) {
                $discrepancies[] = [
                    'type' => 'checkin_mismatch',
                    'severity' => 'medium',
                    'hotel_date' => $hotelCheckIn,
                    'ticket_date' => $ticketArrival,
                    'days_difference' => $diff,
                    'message_fr' => "Check-in hôtel ({$hotelCheckIn}) ne correspond pas à l'arrivée du vol ({$ticketArrival})",
                    'message_en' => "Hotel check-in ({$hotelCheckIn}) doesn't match flight arrival ({$ticketArrival})"
                ];
            }
        }

        // Calculate accommodation coverage
        if ($hotelCheckIn && $hotelCheckOut && $ticketArrival && $ticketReturn) {
            $checkInDate = new \DateTime($hotelCheckIn);
            $checkOutDate = new \DateTime($hotelCheckOut);
            $arrivalDate = new \DateTime($ticketArrival);
            $returnDate = new \DateTime($ticketReturn);

            $hotelNights = (int) $checkInDate->diff($checkOutDate)->days;
            $stayDays = (int) $arrivalDate->diff($returnDate)->days;

            if ($hotelNights < $stayDays) {
                $gap = $stayDays - $hotelNights;
                $coverage = round(($hotelNights / $stayDays) * 100);

                $discrepancies[] = [
                    'type' => 'accommodation_gap',
                    'severity' => $coverage < 50 ? 'high' : 'medium',
                    'hotel_nights' => $hotelNights,
                    'stay_days' => $stayDays,
                    'gap_days' => $gap,
                    'coverage_percent' => $coverage,
                    'message_fr' => "Hébergement: {$hotelNights} nuit(s) réservée(s) pour {$stayDays} jours de séjour ({$coverage}% couvert)",
                    'message_en' => "Accommodation: {$hotelNights} night(s) booked for {$stayDays} days stay ({$coverage}% covered)"
                ];
            }
        }

        return [
            'is_coherent' => empty($discrepancies),
            'discrepancies' => $discrepancies
        ];
    }
}
