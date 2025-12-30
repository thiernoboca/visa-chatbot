<?php
/**
 * Service d'intégration OCR Triple Layer
 * Ambassade de Côte d'Ivoire en Éthiopie
 *
 * Connecte le nouveau système OCR modulaire avec le workflow existant
 * Fournit une API unifiée pour le traitement des documents
 *
 * @package VisaChatbot\Services
 * @version 1.0.0
 */

namespace VisaChatbot\Services;

require_once __DIR__ . '/OCRService.php';
require_once dirname(__DIR__) . '/validators/DocumentValidator.php';

use VisaChatbot\Validators\DocumentValidator;
use Exception;

class OCRIntegrationService {

    /**
     * Service OCR principal
     */
    private ?OCRService $ocrService = null;

    /**
     * Validateur de documents
     */
    private ?DocumentValidator $validator = null;

    /**
     * Cache des résultats d'extraction
     */
    private array $extractionCache = [];

    /**
     * Documents traités dans la session
     */
    private array $processedDocuments = [];

    /**
     * Configuration
     */
    private array $config;

    /**
     * Constructeur
     */
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'debug' => false,
            'use_claude_validation' => true,
            'cache_enabled' => true,
            'cross_validation' => true
        ], $config);

        $this->initServices();
    }

    /**
     * Initialise les services
     */
    private function initServices(): void {
        try {
            $this->ocrService = new OCRService([
                'debug' => $this->config['debug']
            ]);
        } catch (Exception $e) {
            error_log("OCR Service init failed: " . $e->getMessage());
        }

        try {
            $this->validator = new DocumentValidator([
                'debug' => $this->config['debug'],
                'use_claude_validation' => $this->config['use_claude_validation']
            ]);
        } catch (Exception $e) {
            error_log("Validator init failed: " . $e->getMessage());
        }
    }

    /**
     * Traite un document uploadé
     *
     * @param string $docType Type de document (passport, ticket, etc.)
     * @param string $fileContent Contenu base64 du fichier
     * @param string $mimeType Type MIME du fichier
     * @param array $options Options de traitement
     * @return array Résultat du traitement
     */
    public function processDocument(string $docType, string $fileContent, string $mimeType, array $options = []): array {
        $startTime = microtime(true);

        try {
            // 1. Vérifier le cache
            $cacheKey = md5($fileContent . $docType);
            if ($this->config['cache_enabled'] && isset($this->extractionCache[$cacheKey])) {
                return $this->extractionCache[$cacheKey];
            }

            // 2. Conversion PDF si nécessaire
            if ($mimeType === 'application/pdf') {
                $converted = $this->convertPdfToImage($fileContent);
                if (!$converted['success']) {
                    throw new Exception("PDF conversion failed: " . $converted['error']);
                }
                $fileContent = $converted['image'];
                $mimeType = $converted['mime_type'];
            }

            // 3. Extraction OCR Triple Layer
            $ocrResult = $this->ocrService->processDocument($fileContent, $docType, [
                'validate_with_claude' => $options['validate_with_claude'] ?? false
            ]);

            // 4. Formater le résultat pour le workflow
            $result = $this->formatResultForWorkflow($docType, $ocrResult);

            // 5. Stocker dans le cache et les documents traités
            $this->extractionCache[$cacheKey] = $result;
            $this->processedDocuments[$docType] = $result;

            // 6. Ajouter les métadonnées
            $result['_processing'] = [
                'total_time_ms' => round((microtime(true) - $startTime) * 1000),
                'triple_layer' => $ocrResult['layers'] ?? [],
                'timestamp' => date('c')
            ];

            return $result;

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'document_type' => $docType,
                '_processing' => [
                    'total_time_ms' => round((microtime(true) - $startTime) * 1000),
                    'timestamp' => date('c')
                ]
            ];
        }
    }

    /**
     * Formate le résultat OCR pour le workflow existant
     */
    private function formatResultForWorkflow(string $docType, array $ocrResult): array {
        $formatted = [
            'success' => $ocrResult['success'] ?? false,
            'document_type' => $docType,
            'confidence' => $ocrResult['confidence'] ?? 0,
            'fields' => [],
            'validations' => $ocrResult['validations'] ?? [],
            'raw_extracted' => $ocrResult['extracted'] ?? []
        ];

        // Formater les champs extraits au format attendu par le workflow
        $extracted = $ocrResult['extracted'] ?? [];

        switch ($docType) {
            case 'passport':
                $formatted['fields'] = $this->formatPassportFields($extracted, $ocrResult);
                $formatted['mrz'] = $ocrResult['layers']['layer1']['mrz'] ?? null;
                $formatted['passport_type'] = $extracted['passport_type'] ?? 'ORDINAIRE';
                break;

            case 'ticket':
                $formatted['fields'] = $this->formatTicketFields($extracted);
                break;

            case 'vaccination':
                $formatted['fields'] = $this->formatVaccinationFields($extracted);
                break;

            case 'hotel':
                $formatted['fields'] = $this->formatHotelFields($extracted);
                break;

            case 'verbal_note':
                $formatted['fields'] = $this->formatVerbalNoteFields($extracted);
                break;

            case 'invitation':
                $formatted['fields'] = $this->formatInvitationFields($extracted);
                break;

            case 'payment':
                $formatted['fields'] = $this->formatPaymentFields($extracted);
                $formatted['amount_analysis'] = $ocrResult['amount_analysis'] ?? [];
                break;

            case 'residence_card':
                $formatted['fields'] = $this->formatResidenceCardFields($extracted);
                break;

            default:
                $formatted['fields'] = $this->formatGenericFields($extracted);
        }

        return $formatted;
    }

    /**
     * Formate les champs du passeport
     *
     * Aplatit la structure MRZ/VIZ et applique les règles de priorité :
     * - MRZ prioritaire : passport_number, date_of_birth, date_of_expiry, sex, nationality
     * - VIZ prioritaire : surname, given_names (accents), place_of_birth
     */
    private function formatPassportFields(array $extracted, array $fullResult): array {
        // Extraire les données MRZ et VIZ
        $mrz = $extracted['mrz']['parsed'] ?? [];
        $viz = $extracted['viz'] ?? [];
        $confidence = $fullResult['confidence'] ?? 0;

        // Fonction helper pour obtenir la valeur avec priorité
        $getValue = function($field, $priority = 'mrz') use ($mrz, $viz, $extracted) {
            if ($priority === 'mrz') {
                return $mrz[$field] ?? $viz[$field] ?? $extracted[$field] ?? null;
            } else {
                return $viz[$field] ?? $mrz[$field] ?? $extracted[$field] ?? null;
            }
        };

        return [
            'surname' => [
                'value' => $getValue('surname', 'viz'),  // VIZ prioritaire (accents)
                'confidence' => $confidence
            ],
            'given_names' => [
                'value' => $getValue('given_names', 'viz'),  // VIZ prioritaire (accents)
                'confidence' => $confidence
            ],
            'passport_number' => [
                'value' => $getValue('passport_number', 'mrz'),  // MRZ prioritaire
                'confidence' => 0.99  // Haute confiance si MRZ
            ],
            'nationality' => [
                'value' => $getValue('nationality', 'mrz'),  // MRZ prioritaire
                'confidence' => $confidence
            ],
            'date_of_birth' => [
                'value' => $getValue('date_of_birth', 'mrz'),  // MRZ prioritaire
                'confidence' => 0.99
            ],
            'date_of_expiry' => [
                'value' => $getValue('expiry_date', 'mrz') ?? $getValue('date_of_expiry', 'mrz'),
                'confidence' => 0.99
            ],
            'sex' => [
                'value' => $getValue('sex', 'mrz'),  // MRZ prioritaire
                'confidence' => 0.99
            ],
            'place_of_birth' => [
                'value' => $getValue('place_of_birth', 'viz'),  // VIZ seul
                'confidence' => $confidence
            ],
            'issuing_country' => [
                'value' => $mrz['country_code'] ?? $viz['issuing_country'] ?? $extracted['issuing_country'] ?? null,
                'confidence' => $confidence
            ],
            'date_of_issue' => [
                'value' => $getValue('issue_date', 'viz') ?? $getValue('date_of_issue', 'viz'),
                'confidence' => $confidence
            ],
            'issuing_authority' => [
                'value' => $getValue('issuing_authority', 'viz'),
                'confidence' => $confidence
            ],
            'passport_type' => [
                'value' => $extracted['passport_type'] ?? 'ORDINAIRE',
                'confidence' => $extracted['passport_type_confidence'] ?? 0.95
            ]
        ];
    }

    /**
     * Formate les champs du billet d'avion
     */
    private function formatTicketFields(array $extracted): array {
        // Extraire les données du vol aller (outbound)
        $outbound = $extracted['outbound_flight'] ?? [];
        $returnFlight = $extracted['return_flight'] ?? [];

        // Priorité: champs directs > champs du vol aller
        $flightNumber = $extracted['flight_number'] ?? $outbound['flight_number'] ?? null;
        $airline = $extracted['airline'] ?? $outbound['airline'] ?? null;
        $departureDate = $extracted['departure_date'] ?? $outbound['departure']['date'] ?? null;
        $departureAirport = $extracted['departure_airport'] ?? $outbound['departure']['airport'] ?? null;
        $arrivalAirport = $extracted['arrival_airport'] ?? $outbound['arrival']['airport'] ?? null;

        // Champs du vol retour
        $returnFlightNumber = $extracted['return_flight_number'] ?? $returnFlight['flight_number'] ?? null;
        $returnDate = $extracted['return_date'] ?? $returnFlight['departure']['date'] ?? null;

        return [
            'passenger_name' => ['value' => $extracted['passenger_name'] ?? null],
            'flight_number' => ['value' => $flightNumber],
            'airline' => ['value' => $airline],
            'departure_airport' => ['value' => $departureAirport],
            'arrival_airport' => ['value' => $arrivalAirport],
            'departure_date' => ['value' => $departureDate],
            'departure_time' => ['value' => $extracted['departure_time'] ?? $outbound['departure']['time'] ?? null],
            'booking_reference' => ['value' => $extracted['booking_reference'] ?? null],
            'ticket_number' => ['value' => $extracted['ticket_number'] ?? null],
            // Nouveaux champs pour le vol retour
            'return_flight_number' => ['value' => $returnFlightNumber],
            'return_date' => ['value' => $returnDate],
            'is_round_trip' => ['value' => $extracted['is_round_trip'] ?? (!empty($returnFlightNumber))]
        ];
    }

    /**
     * Formate les champs du carnet de vaccination
     */
    private function formatVaccinationFields(array $extracted): array {
        return [
            'holder_name' => ['value' => $extracted['holder_name'] ?? null],
            'date_of_birth' => ['value' => $extracted['date_of_birth'] ?? null],
            'certificate_number' => ['value' => $extracted['certificate_number'] ?? null],
            'yellow_fever_date' => ['value' => $extracted['yellow_fever_date'] ?? null],
            'yellow_fever_valid_until' => ['value' => $extracted['yellow_fever_valid_until'] ?? 'LIFETIME'],
            'vaccination_center' => ['value' => $extracted['vaccination_center'] ?? null],
            'batch_number' => ['value' => $extracted['batch_number'] ?? null]
        ];
    }

    /**
     * Formate les champs de la réservation hôtel
     */
    private function formatHotelFields(array $extracted): array {
        return [
            'guest_name' => ['value' => $extracted['guest_name'] ?? null],
            'hotel_name' => ['value' => $extracted['hotel_name'] ?? null],
            'hotel_address' => ['value' => $extracted['hotel_address'] ?? null],
            'hotel_city' => ['value' => $extracted['hotel_city'] ?? null],
            'check_in_date' => ['value' => $extracted['check_in_date'] ?? null],
            'check_out_date' => ['value' => $extracted['check_out_date'] ?? null],
            'nights' => ['value' => $extracted['nights'] ?? null],
            'confirmation_number' => ['value' => $extracted['confirmation_number'] ?? null],
            'booking_platform' => ['value' => $extracted['booking_platform'] ?? null]
        ];
    }

    /**
     * Formate les champs de la note verbale
     */
    private function formatVerbalNoteFields(array $extracted): array {
        return [
            'sending_entity' => ['value' => $extracted['sending_entity'] ?? null],
            'receiving_entity' => ['value' => $extracted['receiving_entity'] ?? null],
            'reference_number' => ['value' => $extracted['reference_number'] ?? null],
            'date' => ['value' => $extracted['date'] ?? null],
            'diplomat_name' => ['value' => $extracted['diplomat_name'] ?? null],
            'diplomat_title' => ['value' => $extracted['diplomat_title'] ?? null],
            'diplomat_passport_number' => ['value' => $extracted['diplomat_passport_number'] ?? null],
            'requested_visa_type' => ['value' => $extracted['requested_visa_type'] ?? null]
        ];
    }

    /**
     * Formate les champs de la lettre d'invitation
     */
    private function formatInvitationFields(array $extracted): array {
        return [
            'inviter_name' => ['value' => $extracted['inviter_name'] ?? null],
            'inviter_address' => ['value' => $extracted['inviter_address'] ?? null],
            'inviter_city' => ['value' => $extracted['inviter_city'] ?? null],
            'invitee_name' => ['value' => $extracted['invitee_name'] ?? null],
            'invitee_passport_number' => ['value' => $extracted['invitee_passport_number'] ?? null],
            'relationship' => ['value' => $extracted['relationship'] ?? null],
            'purpose' => ['value' => $extracted['purpose'] ?? null],
            'arrival_date' => ['value' => $extracted['arrival_date'] ?? null],
            'departure_date' => ['value' => $extracted['departure_date'] ?? null],
            'notarized' => ['value' => $extracted['notarized'] ?? false]
        ];
    }

    /**
     * Formate les champs de la preuve de paiement
     */
    private function formatPaymentFields(array $extracted): array {
        return [
            'amount' => ['value' => $extracted['amount'] ?? null],
            'currency' => ['value' => $extracted['currency'] ?? 'XOF'],
            'date' => ['value' => $extracted['date'] ?? null],
            'reference' => ['value' => $extracted['reference'] ?? null],
            'payer' => ['value' => $extracted['payer'] ?? null],
            'payee' => ['value' => $extracted['payee'] ?? null],
            'payment_method' => ['value' => $extracted['payment_method'] ?? null],
            'bank_name' => ['value' => $extracted['bank_name'] ?? null],
            'transaction_id' => ['value' => $extracted['transaction_id'] ?? null]
        ];
    }

    /**
     * Formate les champs de la carte de résidence
     */
    private function formatResidenceCardFields(array $extracted): array {
        return [
            'holder_name' => ['value' => $extracted['holder_name'] ?? null],
            'card_number' => ['value' => $extracted['card_number'] ?? null],
            'nationality' => ['value' => $extracted['nationality'] ?? null],
            'date_of_birth' => ['value' => $extracted['date_of_birth'] ?? null],
            'issue_date' => ['value' => $extracted['issue_date'] ?? null],
            'expiry_date' => ['value' => $extracted['expiry_date'] ?? null],
            'issuing_country' => ['value' => $extracted['issuing_country'] ?? null],
            'residence_type' => ['value' => $extracted['residence_type'] ?? null],
            'employer' => ['value' => $extracted['employer'] ?? null]
        ];
    }

    /**
     * Formate les champs génériques
     */
    private function formatGenericFields(array $extracted): array {
        $fields = [];
        foreach ($extracted as $key => $value) {
            if (!is_array($value)) {
                $fields[$key] = ['value' => $value];
            }
        }
        return $fields;
    }

    /**
     * Valide tous les documents collectés
     */
    public function validateAllDocuments(array $applicationContext = []): array {
        if (!$this->validator) {
            return ['error' => 'Validator not available'];
        }

        return $this->validator->validate($this->processedDocuments, $applicationContext);
    }

    /**
     * Cross-validation entre documents
     */
    public function crossValidateDocuments(): array {
        if (!$this->config['cross_validation']) {
            return ['skipped' => true];
        }

        $results = [
            'name_consistency' => $this->checkNameConsistency(),
            'date_consistency' => $this->checkDateConsistency(),
            'passport_number_consistency' => $this->checkPassportNumberConsistency()
        ];

        $results['overall_valid'] = !in_array(false, array_column($results, 'valid'));

        return $results;
    }

    /**
     * Vérifie la cohérence des noms
     */
    private function checkNameConsistency(): array {
        $names = [];

        $nameFields = [
            'passport' => 'surname',
            'ticket' => 'passenger_name',
            'hotel' => 'guest_name',
            'vaccination' => 'holder_name',
            'invitation' => 'invitee_name'
        ];

        foreach ($nameFields as $docType => $field) {
            if (isset($this->processedDocuments[$docType]['fields'][$field]['value'])) {
                $names[$docType] = $this->processedDocuments[$docType]['fields'][$field]['value'];
            }
        }

        if (count($names) < 2) {
            return ['valid' => true, 'message' => 'Insufficient documents for cross-validation'];
        }

        $normalized = array_map('strtoupper', $names);
        $unique = array_unique($normalized);

        return [
            'valid' => count($unique) === 1,
            'names' => $names,
            'variations' => count($unique) > 1 ? $unique : []
        ];
    }

    /**
     * Vérifie la cohérence des dates
     */
    private function checkDateConsistency(): array {
        $issues = [];

        // Vérifier dates vol vs dates hôtel
        $flightDate = $this->processedDocuments['ticket']['fields']['departure_date']['value'] ?? null;
        $checkIn = $this->processedDocuments['hotel']['fields']['check_in_date']['value'] ?? null;

        if ($flightDate && $checkIn) {
            $flightTs = strtotime($flightDate);
            $checkInTs = strtotime($checkIn);

            if (abs($flightTs - $checkInTs) > 86400) {
                $issues[] = "Flight arrival ({$flightDate}) doesn't match hotel check-in ({$checkIn})";
            }
        }

        // Vérifier expiration passeport vs dates voyage
        $passportExpiry = $this->processedDocuments['passport']['fields']['date_of_expiry']['value'] ?? null;

        if ($passportExpiry && $flightDate) {
            $expiryTs = strtotime($passportExpiry);
            $travelTs = strtotime($flightDate);
            $sixMonths = strtotime('+6 months', $travelTs);

            if ($expiryTs < $sixMonths) {
                $issues[] = 'Passport expires within 6 months of travel date';
            }
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues
        ];
    }

    /**
     * Vérifie la cohérence des numéros de passeport
     */
    private function checkPassportNumberConsistency(): array {
        $numbers = [];

        if (isset($this->processedDocuments['passport']['fields']['passport_number']['value'])) {
            $numbers['passport'] = $this->processedDocuments['passport']['fields']['passport_number']['value'];
        }

        if (isset($this->processedDocuments['invitation']['fields']['invitee_passport_number']['value'])) {
            $numbers['invitation'] = $this->processedDocuments['invitation']['fields']['invitee_passport_number']['value'];
        }

        if (count($numbers) < 2) {
            return ['valid' => true, 'message' => 'Insufficient documents for cross-validation'];
        }

        $unique = array_unique($numbers);

        return [
            'valid' => count($unique) === 1,
            'numbers' => $numbers
        ];
    }

    /**
     * Convertit un PDF en image
     */
    private function convertPdfToImage(string $pdfContent): array {
        // Utiliser le convertisseur existant
        require_once dirname(__DIR__) . '/../../passport-ocr-module/php/pdf-converter.php';

        try {
            $converter = new \PdfConverter(['debug' => $this->config['debug']]);
            return $converter->convertToImage($pdfContent, 1);
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Retourne les documents traités
     */
    public function getProcessedDocuments(): array {
        return $this->processedDocuments;
    }

    /**
     * Nettoie le cache
     */
    public function clearCache(): void {
        $this->extractionCache = [];
    }

    /**
     * Nettoie les documents traités
     */
    public function clearProcessedDocuments(): void {
        $this->processedDocuments = [];
    }

    /**
     * Vérifie si le service OCR est disponible
     */
    public function isOcrAvailable(): bool {
        return $this->ocrService !== null;
    }

    /**
     * Vérifie si Gemini est disponible (Layer 2)
     */
    public function isGeminiAvailable(): bool {
        return $this->ocrService && $this->ocrService->isGeminiAvailable();
    }
}
