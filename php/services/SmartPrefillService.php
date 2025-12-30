<?php
/**
 * SmartPrefillService - Intelligent Document Prefilling
 *
 * Orchestrates the "Smart Data Cascade" pattern:
 * - Passport (source primaire) -> cascade vers tous les documents
 * - Ticket -> dates et vols pour hotel, invitation
 * - Cross-validation automatique entre documents
 *
 * @version 6.0.0
 * @author Visa Chatbot Team
 */

require_once __DIR__ . '/CrossDocumentSync.php';

class SmartPrefillService {

    /**
     * @var array Cached collected data from session
     */
    private array $dataCache = [];

    /**
     * @var CrossDocumentSync Cross-document synchronization service
     */
    private CrossDocumentSync $sync;

    /**
     * Document types that can be source for prefill
     */
    private const SOURCE_DOCUMENTS = [
        'passport',
        'ticket',
        'hotel',
        'vaccination',
        'invitation',
        'residence_card',
        'payment',
        'verbal_note'
    ];

    /**
     * Prefill mapping: document -> fields -> source
     */
    private const PREFILL_MAPPING = [
        'ticket' => [
            'passenger_name' => ['passport', 'full_name'],
            'passenger_surname' => ['passport', 'surname'],
            'passenger_given_names' => ['passport', 'given_names']
        ],
        'hotel' => [
            'guest_name' => ['passport', 'full_name'],
            'guest_surname' => ['passport', 'surname'],
            'check_in' => ['ticket', 'departure_date'],
            'check_out' => ['ticket', 'return_date']
        ],
        'vaccination' => [
            'patient_name' => ['passport', 'full_name'],
            'patient_surname' => ['passport', 'surname'],
            'date_of_birth' => ['passport', 'date_of_birth'],
            'nationality' => ['passport', 'nationality']
        ],
        'invitation' => [
            'invitee_name' => ['passport', 'full_name'],
            'invitee_surname' => ['passport', 'surname'],
            'invitee_nationality' => ['passport', 'nationality'],
            'visit_from' => ['ticket', 'departure_date'],
            'visit_to' => ['ticket', 'return_date']
        ],
        'residence_card' => [
            'holder_name' => ['passport', 'full_name'],
            'holder_surname' => ['passport', 'surname'],
            'date_of_birth' => ['passport', 'date_of_birth'],
            'nationality' => ['passport', 'nationality']
        ],
        'payment' => [
            'applicant_name' => ['passport', 'full_name'],
            'applicant_surname' => ['passport', 'surname']
        ],
        'verbal_note' => [
            'diplomat_name' => ['passport', 'full_name'],
            'diplomat_surname' => ['passport', 'surname'],
            'passport_number' => ['passport', 'number']
        ]
    ];

    /**
     * Visa fees by type (in CFA francs)
     */
    private const VISA_FEES = [
        'tourist' => 50000,
        'business' => 100000,
        'transit' => 25000,
        'diplomatic' => 0,
        'service' => 0,
        'courtesy' => 0,
        'express' => 75000 // supplement
    ];

    public function __construct() {
        $this->sync = new CrossDocumentSync();
    }

    /**
     * Collect all extracted data from session for prefilling
     *
     * @param array $session Session data with extracted documents
     * @return array Collected data keyed by document type and field
     */
    public function collectFromSession(array $session): array {
        $collected = [];

        // Passport = Source primaire
        if (isset($session['extracted_data']['passport'])) {
            $passport = $session['extracted_data']['passport'];
            $collected['passport'] = [
                'full_name' => $passport['full_name'] ?? null,
                'given_names' => $passport['given_names'] ?? null,
                'surname' => $passport['surname'] ?? null,
                'nationality' => $passport['nationality'] ?? null,
                'nationality_code' => $passport['nationality_code'] ?? null,
                'date_of_birth' => $passport['date_of_birth'] ?? null,
                'number' => $passport['number'] ?? null,
                'sex' => $passport['sex'] ?? null,
                'expiry_date' => $passport['expiry_date'] ?? null,
                'issue_date' => $passport['issue_date'] ?? null,
                'place_of_birth' => $passport['place_of_birth'] ?? null,
                'issuing_authority' => $passport['issuing_authority'] ?? null,
                'passport_type' => $passport['type'] ?? 'ordinary'
            ];
        }

        // Ticket = Dates et vols
        if (isset($session['extracted_data']['ticket'])) {
            $ticket = $session['extracted_data']['ticket'];
            $collected['ticket'] = [
                'passenger_name' => $ticket['passenger_name'] ?? null,
                'departure_date' => $ticket['departure_date'] ?? null,
                'return_date' => $ticket['return_date'] ?? null,
                'flight_number' => $ticket['flight_number'] ?? null,
                'departure_airport' => $ticket['departure_airport'] ?? null,
                'departure_city' => $ticket['departure_city'] ?? null,
                'arrival_airport' => $ticket['arrival_airport'] ?? null,
                'arrival_city' => $ticket['arrival_city'] ?? null,
                'airline' => $ticket['airline'] ?? null,
                'booking_reference' => $ticket['booking_reference'] ?? null,
                'is_round_trip' => $ticket['is_round_trip'] ?? false
            ];
        }

        // Hotel = Dates séjour
        if (isset($session['extracted_data']['hotel'])) {
            $hotel = $session['extracted_data']['hotel'];
            $collected['hotel'] = [
                'guest_name' => $hotel['guest_name'] ?? null,
                'hotel_name' => $hotel['hotel_name'] ?? null,
                'check_in' => $hotel['check_in_date'] ?? null,
                'check_out' => $hotel['check_out_date'] ?? null,
                'city' => $hotel['city'] ?? null,
                'address' => $hotel['address'] ?? null,
                'confirmation_number' => $hotel['confirmation_number'] ?? null,
                'num_nights' => $hotel['num_nights'] ?? null
            ];
        }

        // Vaccination
        if (isset($session['extracted_data']['vaccination'])) {
            $vaccination = $session['extracted_data']['vaccination'];
            $collected['vaccination'] = [
                'patient_name' => $vaccination['patient_name'] ?? null,
                'date_of_birth' => $vaccination['date_of_birth'] ?? null,
                'vaccine_type' => $vaccination['vaccine_type'] ?? null,
                'vaccination_date' => $vaccination['vaccination_date'] ?? null,
                'batch_number' => $vaccination['batch_number'] ?? null,
                'administering_center' => $vaccination['administering_center'] ?? null
            ];
        }

        // Invitation Letter
        if (isset($session['extracted_data']['invitation'])) {
            $invitation = $session['extracted_data']['invitation'];
            $collected['invitation'] = [
                'invitee_name' => $invitation['invitee_name'] ?? null,
                'inviter_name' => $invitation['inviter_name'] ?? null,
                'inviter_company' => $invitation['inviter_company'] ?? null,
                'visit_purpose' => $invitation['visit_purpose'] ?? null,
                'visit_from' => $invitation['visit_from'] ?? null,
                'visit_to' => $invitation['visit_to'] ?? null
            ];
        }

        // Residence Card
        if (isset($session['extracted_data']['residence_card'])) {
            $residence = $session['extracted_data']['residence_card'];
            $collected['residence_card'] = [
                'holder_name' => $residence['holder_name'] ?? null,
                'card_number' => $residence['card_number'] ?? null,
                'expiry_date' => $residence['expiry_date'] ?? null,
                'residence_type' => $residence['residence_type'] ?? null,
                'issuing_authority' => $residence['issuing_authority'] ?? null
            ];
        }

        // Geolocation
        if (isset($session['geolocation'])) {
            $collected['geolocation'] = [
                'country_name' => $session['geolocation']['country_name'] ?? null,
                'country_code' => $session['geolocation']['country_code'] ?? null,
                'city' => $session['geolocation']['city'] ?? null
            ];
        }

        // Session metadata
        $collected['session'] = [
            'visa_type' => $session['visa_type'] ?? 'tourist',
            'visa_duration' => $session['visa_duration'] ?? 90,
            'workflow_type' => $session['workflow_type'] ?? 'standard',
            'language' => $session['language'] ?? 'fr'
        ];

        $this->dataCache = $collected;
        return $collected;
    }

    /**
     * Get prefill data for a specific document type
     *
     * @param string $docType Document type (hotel, vaccination, invitation, etc.)
     * @param array $session Session data
     * @return array Prefill data with confidence and source documents
     */
    public function getPrefillForDocument(string $docType, array $session): array {
        $collected = $this->collectFromSession($session);
        $prefill = [];
        $sources = [];

        if (!isset(self::PREFILL_MAPPING[$docType])) {
            return [
                'prefill_data' => [],
                'confidence' => 0,
                'source_documents' => [],
                'has_prefill' => false
            ];
        }

        $mapping = self::PREFILL_MAPPING[$docType];

        foreach ($mapping as $targetField => $sourceInfo) {
            [$sourceDoc, $sourceField] = $sourceInfo;

            if (isset($collected[$sourceDoc][$sourceField]) && $collected[$sourceDoc][$sourceField] !== null) {
                $prefill[$targetField] = $collected[$sourceDoc][$sourceField];
                if (!in_array($sourceDoc, $sources)) {
                    $sources[] = $sourceDoc;
                }
            }
        }

        // Add document-specific prefill logic
        switch ($docType) {
            case 'hotel':
                // Calculate number of nights if dates available
                if (isset($prefill['check_in']) && isset($prefill['check_out'])) {
                    try {
                        $checkIn = new DateTime($prefill['check_in']);
                        $checkOut = new DateTime($prefill['check_out']);
                        $prefill['num_nights'] = $checkOut->diff($checkIn)->days;
                    } catch (Exception $e) {
                        // Ignore date parsing errors
                    }
                }
                break;

            case 'payment':
                // Add expected amount based on visa type
                $visaType = $session['visa_type'] ?? 'tourist';
                $prefill['expected_amount'] = $this->getVisaFee($visaType);
                $prefill['visa_type'] = $visaType;

                // Add express supplement if applicable
                if (($session['express'] ?? false) === true) {
                    $prefill['express_supplement'] = self::VISA_FEES['express'];
                    $prefill['total_amount'] = $prefill['expected_amount'] + $prefill['express_supplement'];
                } else {
                    $prefill['total_amount'] = $prefill['expected_amount'];
                }
                break;

            case 'invitation':
                // Combine dates for visit period
                if (isset($prefill['visit_from']) && isset($prefill['visit_to'])) {
                    $prefill['visit_dates'] = [
                        'from' => $prefill['visit_from'],
                        'to' => $prefill['visit_to']
                    ];
                }
                break;
        }

        $confidence = $this->calculatePrefillConfidence($prefill, $mapping);

        return [
            'prefill_data' => $prefill,
            'confidence' => $confidence,
            'source_documents' => $sources,
            'has_prefill' => !empty($prefill),
            'fields_count' => count($prefill),
            'total_fields' => count($mapping)
        ];
    }

    /**
     * Validate extracted data against prefilled expectations
     *
     * @param string $docType Document type
     * @param array $extracted Extracted data from OCR
     * @param array $session Session data
     * @return array Validation result with discrepancies
     */
    public function validateExtractedVsPrefill(string $docType, array $extracted, array $session): array {
        $prefillResult = $this->getPrefillForDocument($docType, $session);
        $prefill = $prefillResult['prefill_data'];

        if (empty($prefill)) {
            return [
                'valid' => true,
                'has_prefill' => false,
                'discrepancies' => []
            ];
        }

        return $this->sync->compareAndValidate($extracted, $prefill, $docType);
    }

    /**
     * Get all available prefill data for upcoming documents
     *
     * @param array $session Session data
     * @param array $completedDocs List of already completed document types
     * @return array Map of document type -> prefill availability
     */
    public function getUpcomingPrefillStatus(array $session, array $completedDocs = []): array {
        $status = [];

        foreach (self::PREFILL_MAPPING as $docType => $mapping) {
            if (in_array($docType, $completedDocs)) {
                continue;
            }

            $prefillResult = $this->getPrefillForDocument($docType, $session);
            $status[$docType] = [
                'has_prefill' => $prefillResult['has_prefill'],
                'fields_ready' => $prefillResult['fields_count'],
                'total_fields' => $prefillResult['total_fields'],
                'confidence' => $prefillResult['confidence'],
                'sources' => $prefillResult['source_documents']
            ];
        }

        return $status;
    }

    /**
     * Calculate prefill confidence based on available data
     *
     * @param array $prefill Prefilled data
     * @param array $mapping Expected field mapping
     * @return int Confidence percentage (0-100)
     */
    private function calculatePrefillConfidence(array $prefill, array $mapping): int {
        if (empty($mapping)) {
            return 0;
        }

        $filled = array_filter($prefill, fn($v) => $v !== null && $v !== '');
        return (int) (count($filled) / count($mapping) * 100);
    }

    /**
     * Get visa fee based on type
     *
     * @param string $visaType Visa type
     * @return int Fee in CFA francs
     */
    public function getVisaFee(string $visaType): int {
        return self::VISA_FEES[$visaType] ?? self::VISA_FEES['tourist'];
    }

    /**
     * Get all visa fees
     *
     * @return array Map of visa type -> fee
     */
    public function getAllVisaFees(): array {
        return self::VISA_FEES;
    }

    /**
     * Generate prefill message for Aya persona
     *
     * @param string $docType Document type
     * @param array $prefill Prefilled data
     * @param string $lang Language (fr/en)
     * @return array Message configuration
     */
    public function generatePrefillMessage(string $docType, array $prefill, string $lang = 'fr'): array {
        $docNames = [
            'hotel' => ['fr' => 'votre réservation hôtel', 'en' => 'your hotel reservation'],
            'vaccination' => ['fr' => 'votre certificat de vaccination', 'en' => 'your vaccination certificate'],
            'invitation' => ['fr' => 'votre lettre d\'invitation', 'en' => 'your invitation letter'],
            'payment' => ['fr' => 'votre preuve de paiement', 'en' => 'your payment proof'],
            'residence_card' => ['fr' => 'votre carte de résident', 'en' => 'your residence card']
        ];

        $docName = $docNames[$docType][$lang] ?? $docType;
        $fieldCount = count(array_filter($prefill, fn($v) => $v !== null));

        if ($lang === 'fr') {
            $message = "✨ **Pré-remplissage intelligent**\n\n" .
                       "J'ai déjà préparé **{$fieldCount} champs** pour {$docName} " .
                       "à partir de vos documents précédents.\n\n" .
                       "Vérifiez simplement que les informations sont correctes !";
        } else {
            $message = "✨ **Smart Prefill**\n\n" .
                       "I've already prepared **{$fieldCount} fields** for {$docName} " .
                       "from your previous documents.\n\n" .
                       "Just verify that the information is correct!";
        }

        return [
            'type' => 'prefill_notification',
            'message' => $message,
            'fields_count' => $fieldCount,
            'document_type' => $docType
        ];
    }

    /**
     * Get cached data
     *
     * @return array
     */
    public function getCachedData(): array {
        return $this->dataCache;
    }
}
