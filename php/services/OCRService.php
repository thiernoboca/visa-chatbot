<?php
/**
 * Service OCR Triple Layer Principal
 * Ambassade de Côte d'Ivoire en Éthiopie
 *
 * Architecture conforme au PRD:
 * - Layer 1: Google Vision OCR (~200ms synchrone)
 * - Layer 2: Gemini 3 Flash (~800ms synchrone)
 * - Layer 3: Claude Sonnet (5-10s asynchrone)
 *
 * @package VisaChatbot\Services
 * @version 1.0.0
 */

namespace VisaChatbot\Services;

require_once dirname(__DIR__) . '/../../passport-ocr-module/php/google-vision-client.php';
require_once dirname(__DIR__) . '/gemini-client.php';
require_once dirname(__DIR__) . '/claude-diplomatic-validator.php';

use Exception;

class OCRService {

    /**
     * Configuration des layers
     */
    private const LAYER_CONFIG = [
        'layer1' => [
            'name' => 'Google Vision OCR',
            'timeout_ms' => 200,
            'sync' => true
        ],
        'layer2' => [
            'name' => 'Gemini 3 Flash',
            'timeout_ms' => 800,
            'sync' => true
        ],
        'layer3' => [
            'name' => 'Claude Sonnet',
            'timeout_ms' => 10000,
            'sync' => false
        ]
    ];

    /**
     * Types de documents selon PRD
     */
    public const DOC_TYPES = [
        'DOC_PASSEPORT' => 'passport',
        'DOC_CARTE_RESIDENT' => 'residence_card',
        'DOC_BILLET' => 'ticket',
        'DOC_VACCINATION' => 'vaccination',
        'DOC_HOTEL' => 'hotel',
        'DOC_NOTE_VERBALE' => 'verbal_note',
        'DOC_INVITATION' => 'invitation',
        'DOC_PAIEMENT' => 'payment'
    ];

    /**
     * Client Google Vision (Layer 1)
     */
    private \GoogleVisionClient $visionClient;

    /**
     * Client Gemini (Layer 2)
     */
    private ?\GeminiClient $geminiClient = null;

    /**
     * Validateur Claude (Layer 3)
     */
    private ?\ClaudeDiplomaticValidator $claudeValidator = null;

    /**
     * Extracteurs par type de document
     */
    private array $extractors = [];

    /**
     * Traces d'exécution
     */
    private array $traces = [];

    /**
     * Mode debug
     */
    private bool $debug;

    /**
     * Constructeur
     */
    public function __construct(array $options = []) {
        $this->debug = $options['debug'] ?? false;

        // Initialiser Layer 1: Google Vision
        $this->initLayer1();

        // Initialiser Layer 2: Gemini
        $this->initLayer2($options);

        // Initialiser Layer 3: Claude (lazy loading)
        $this->initLayer3($options);

        // Charger les extracteurs
        $this->loadExtractors();
    }

    /**
     * Initialise Google Vision (Layer 1)
     */
    private function initLayer1(): void {
        try {
            $this->visionClient = new \GoogleVisionClient(['debug' => $this->debug]);
            $this->trace('layer1_init', 'Google Vision initialized', ['status' => 'success']);
        } catch (Exception $e) {
            $this->trace('layer1_init', 'Google Vision init failed', [
                'status' => 'error',
                'error' => $e->getMessage()
            ]);
            throw new Exception("Layer 1 (Google Vision) initialization failed: " . $e->getMessage());
        }
    }

    /**
     * Initialise Gemini Flash (Layer 2)
     */
    private function initLayer2(array $options): void {
        $apiKey = $options['gemini_api_key'] ?? getenv('GEMINI_API_KEY');

        if (!empty($apiKey)) {
            try {
                $this->geminiClient = new \GeminiClient([
                    'api_key' => $apiKey,
                    'debug' => $this->debug
                ]);
                $this->trace('layer2_init', 'Gemini Flash initialized', ['status' => 'success']);
            } catch (Exception $e) {
                $this->trace('layer2_init', 'Gemini init failed, will use fallback', [
                    'status' => 'warning',
                    'error' => $e->getMessage()
                ]);
                $this->geminiClient = null;
            }
        }
    }

    /**
     * Initialise Claude Sonnet (Layer 3) - Lazy loading
     */
    private function initLayer3(array $options): void {
        // Layer 3 est initialisé à la demande pour économiser les ressources
        $this->trace('layer3_init', 'Claude Sonnet ready (lazy loading)', ['status' => 'pending']);
    }

    /**
     * Charge les extracteurs par type de document
     */
    private function loadExtractors(): void {
        $extractorsPath = dirname(__DIR__) . '/extractors';

        // Mapping type -> classe
        $extractorClasses = [
            'passport' => 'PassportExtractor',
            'residence_card' => 'ResidenceCardExtractor',
            'ticket' => 'FlightTicketExtractor',
            'vaccination' => 'VaccinationCardExtractor',
            'hotel' => 'HotelReservationExtractor',
            'verbal_note' => 'VerbalNoteExtractor',
            'invitation' => 'InvitationLetterExtractor',
            'payment' => 'PaymentProofExtractor'
        ];

        foreach ($extractorClasses as $type => $className) {
            $classFile = $extractorsPath . '/' . $className . '.php';
            if (file_exists($classFile)) {
                require_once $classFile;
                $fullClassName = "VisaChatbot\\Extractors\\{$className}";
                if (class_exists($fullClassName)) {
                    $this->extractors[$type] = new $fullClassName($this);
                }
            }
        }

        $this->trace('extractors_loaded', 'Document extractors loaded', [
            'count' => count($this->extractors),
            'types' => array_keys($this->extractors)
        ]);
    }

    /**
     * Pipeline Triple Layer complet
     *
     * @param string $imagePath Chemin ou contenu base64 de l'image
     * @param string $docType Type de document (passport, ticket, etc.)
     * @param array $options Options supplémentaires
     * @return OCRResult Résultat de l'extraction
     */
    public function processDocument(string $imagePath, string $docType, array $options = []): array {
        $startTime = microtime(true);
        $this->traces = []; // Reset pour nouvelle extraction

        $this->trace('process_start', "Starting Triple Layer extraction for {$docType}", [
            'document_type' => $docType,
            'options' => $options
        ]);

        try {
            // Layer 1: Google Vision OCR
            $layer1Result = $this->extractWithVision($imagePath);

            // Layer 2: Gemini structuration
            $layer2Result = $this->structureWithGemini($layer1Result['raw_text'], $docType, $layer1Result);

            // Fusionner les résultats
            $result = [
                'success' => true,
                'document_type' => $docType,
                'extracted' => $layer2Result['extracted'],
                'validations' => $layer2Result['validations'],
                'confidence' => $this->calculateConfidence($layer1Result, $layer2Result),
                'layers' => [
                    'layer1' => [
                        'provider' => 'google_vision',
                        'raw_text' => $layer1Result['raw_text'],
                        'confidence' => $layer1Result['confidence'],
                        'processing_time_ms' => $layer1Result['processing_time_ms']
                    ],
                    'layer2' => [
                        'provider' => $this->geminiClient ? 'gemini_flash' : 'fallback',
                        'structured' => true,
                        'processing_time_ms' => $layer2Result['processing_time_ms']
                    ],
                    'layer3' => [
                        'provider' => 'claude_sonnet',
                        'status' => 'pending',
                        'async' => true
                    ]
                ],
                'metadata' => [
                    'total_processing_time_ms' => round((microtime(true) - $startTime) * 1000),
                    'timestamp' => date('c'),
                    'traces' => $this->traces
                ]
            ];

            // Layer 3: Claude validation (async si demandé)
            if ($options['validate_with_claude'] ?? false) {
                $this->queueClaudeValidation($result, $docType, $options);
            }

            return $result;

        } catch (Exception $e) {
            $this->trace('process_error', 'Extraction failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'document_type' => $docType,
                'error' => $e->getMessage(),
                'metadata' => [
                    'total_processing_time_ms' => round((microtime(true) - $startTime) * 1000),
                    'timestamp' => date('c'),
                    'traces' => $this->traces
                ]
            ];
        }
    }

    /**
     * Layer 1: Extraction avec Google Vision
     *
     * @param string $imageContent Contenu base64 ou chemin de l'image
     * @return array Texte brut extrait + métadonnées
     */
    public function extractWithVision(string $imageContent): array {
        $startTime = microtime(true);

        $this->trace('layer1_start', 'Google Vision OCR starting');

        // Déterminer si c'est un chemin ou du base64
        if (file_exists($imageContent)) {
            $content = base64_encode(file_get_contents($imageContent));
            $mimeType = mime_content_type($imageContent);
        } else {
            $content = $imageContent;
            $mimeType = 'image/jpeg'; // Défaut
        }

        // Appeler Google Vision
        $result = $this->visionClient->extractText($content, $mimeType);

        $processingTime = round((microtime(true) - $startTime) * 1000);

        $this->trace('layer1_complete', 'Google Vision OCR completed', [
            'chars_extracted' => strlen($result['full_text'] ?? ''),
            'confidence' => $result['confidence'] ?? 0,
            'processing_time_ms' => $processingTime
        ]);

        return [
            'raw_text' => $result['full_text'] ?? '',
            'blocks' => $result['blocks'] ?? [],
            'confidence' => $result['confidence'] ?? 0,
            'processing_time_ms' => $processingTime
        ];
    }

    /**
     * Layer 2: Structuration avec Gemini Flash
     *
     * @param string $rawText Texte brut du Layer 1
     * @param string $docType Type de document
     * @param array $layer1Result Résultat complet Layer 1
     * @return array Données structurées
     */
    public function structureWithGemini(string $rawText, string $docType, array $layer1Result = []): array {
        $startTime = microtime(true);

        $this->trace('layer2_start', 'Gemini structuration starting', ['doc_type' => $docType]);

        // ÉTAPE 1: TOUJOURS utiliser l'extracteur spécialisé pour extraction de base
        $extractorResult = $this->fallbackExtraction($rawText, $docType);

        $this->trace('extractor_done', 'Specialized extractor completed', [
            'fields' => count($extractorResult['extracted'] ?? []),
            'has_data' => !empty($extractorResult['extracted'])
        ]);

        // Charger le prompt approprié pour ce type de document
        $prompt = $this->getExtractionPrompt($docType, $rawText, $layer1Result);

        try {
            if ($this->geminiClient) {
                // ÉTAPE 2: Utiliser Gemini Flash pour enrichir/valider
                $response = $this->geminiClient->chat($prompt);
                $geminiResult = $this->parseGeminiResponse($response, $docType);

                // ÉTAPE 3: Fusionner les résultats (extracteur prioritaire car plus précis)
                $structured = $this->mergeExtractionResults($extractorResult, $geminiResult);
            } else {
                // Si pas de Gemini, utiliser uniquement l'extracteur
                $structured = $extractorResult;
            }

            $processingTime = round((microtime(true) - $startTime) * 1000);

            $this->trace('layer2_complete', 'Gemini structuration completed', [
                'fields_extracted' => count($structured['extracted'] ?? []),
                'processing_time_ms' => $processingTime
            ]);

            return [
                'extracted' => $structured['extracted'] ?? [],
                'validations' => $structured['validations'] ?? [],
                'processing_time_ms' => $processingTime
            ];

        } catch (Exception $e) {
            $this->trace('layer2_error', 'Gemini failed, using extractor results only', ['error' => $e->getMessage()]);

            return [
                'extracted' => $extractorResult['extracted'] ?? [],
                'validations' => $extractorResult['validations'] ?? [],
                'processing_time_ms' => round((microtime(true) - $startTime) * 1000)
            ];
        }
    }

    /**
     * Fusionne les résultats d'extraction (extracteur + Gemini)
     * L'extracteur a priorité car ses patterns sont plus précis
     */
    private function mergeExtractionResults(array $extractorResult, array $geminiResult): array {
        $extracted = $extractorResult['extracted'] ?? [];
        $geminiExtracted = $geminiResult['extracted'] ?? [];

        // Pour chaque champ Gemini, utiliser seulement si l'extracteur n'a pas de valeur
        foreach ($geminiExtracted as $key => $value) {
            if (!isset($extracted[$key]) || empty($extracted[$key])) {
                $extracted[$key] = $value;
            }
        }

        // Fusionner les validations
        $validations = array_merge(
            $extractorResult['validations'] ?? [],
            $geminiResult['validations'] ?? []
        );

        return [
            'extracted' => $extracted,
            'validations' => $validations
        ];
    }

    /**
     * Layer 3: Validation asynchrone avec Claude
     *
     * @param array $data Données extraites
     * @param string $docType Type de document
     * @param array $options Options de validation
     */
    public function queueClaudeValidation(array $data, string $docType, array $options = []): void {
        $this->trace('layer3_queue', 'Queuing Claude validation', ['doc_type' => $docType]);

        // En production, ceci enverrait un job à une queue (Redis, DB, etc.)
        // Pour l'instant, validation synchrone si demandée
        if ($options['sync_validation'] ?? false) {
            $this->validateWithClaude($data, $docType, $options);
        }
    }

    /**
     * Layer 3: Validation avec Claude Sonnet (synchrone)
     *
     * @param array $data Données à valider
     * @param string $docType Type de document
     * @param array $options Options
     * @return array Résultat de validation
     */
    public function validateWithClaude(array $data, string $docType, array $options = []): array {
        $startTime = microtime(true);

        $this->trace('layer3_start', 'Claude validation starting', ['doc_type' => $docType]);

        // Lazy loading du validateur
        if (!$this->claudeValidator) {
            $this->claudeValidator = new \ClaudeDiplomaticValidator([
                'debug' => $this->debug
            ]);
        }

        try {
            // Construire le contexte de validation
            $validationContext = [
                'document_type' => $docType,
                'extracted_data' => $data['extracted'] ?? [],
                'layer2_validations' => $data['validations'] ?? [],
                'application_context' => $options['application_context'] ?? []
            ];

            // Appeler Claude pour validation
            $result = $this->claudeValidator->validate($validationContext);

            $processingTime = round((microtime(true) - $startTime) * 1000);

            $this->trace('layer3_complete', 'Claude validation completed', [
                'fraud_score' => $result['fraud_score'] ?? 0,
                'confidence' => $result['confidence'] ?? 0,
                'processing_time_ms' => $processingTime
            ]);

            return [
                'validated' => true,
                'fraud_score' => $result['fraud_score'] ?? 0,
                'alerts' => $result['alerts'] ?? [],
                'recommendations' => $result['recommendations'] ?? [],
                'confidence' => $result['confidence'] ?? 0,
                'processing_time_ms' => $processingTime
            ];

        } catch (Exception $e) {
            $this->trace('layer3_error', 'Claude validation failed', ['error' => $e->getMessage()]);

            return [
                'validated' => false,
                'error' => $e->getMessage(),
                'processing_time_ms' => round((microtime(true) - $startTime) * 1000)
            ];
        }
    }

    /**
     * Génère le prompt d'extraction pour Gemini selon le type de document
     */
    private function getExtractionPrompt(string $docType, string $rawText, array $layer1Result = []): string {
        $prompts = [
            'passport' => $this->getPassportPrompt($rawText, $layer1Result),
            'ticket' => $this->getFlightTicketPrompt($rawText),
            'vaccination' => $this->getVaccinationPrompt($rawText),
            'hotel' => $this->getHotelPrompt($rawText),
            'verbal_note' => $this->getVerbalNotePrompt($rawText),
            'invitation' => $this->getInvitationPrompt($rawText),
            'payment' => $this->getPaymentPrompt($rawText),
            'residence_card' => $this->getResidenceCardPrompt($rawText)
        ];

        return $prompts[$docType] ?? $this->getGenericPrompt($rawText, $docType);
    }

    /**
     * Prompt pour extraction passeport (MRZ + VIZ)
     */
    private function getPassportPrompt(string $rawText, array $layer1Result = []): string {
        return <<<PROMPT
Tu es un expert en extraction de données de passeport. Analyse le texte OCR suivant et extrais les informations structurées.

TEXTE OCR:
{$rawText}

INSTRUCTIONS:
1. Identifier et parser la zone MRZ (2 ou 3 lignes de caractères < et chiffres)
2. Extraire les informations de la zone visuelle (VIZ)
3. Cross-valider MRZ et VIZ
4. Détecter le type de passeport (ORDINAIRE, DIPLOMATIQUE, SERVICE, etc.)

RETOURNE UN JSON STRICT:
{
  "extracted": {
    "mrz": {
      "line1": "P<XXXNOMPRENOM<<<<<<<<<<<<<<<<<<<<<<<<",
      "line2": "XXXXXXXXX<XXXX...",
      "parsed": {
        "document_type": "P",
        "country_code": "XXX",
        "surname": "",
        "given_names": "",
        "passport_number": "",
        "nationality": "",
        "date_of_birth": "YYMMDD",
        "sex": "M/F",
        "expiry_date": "YYMMDD",
        "personal_number": ""
      }
    },
    "viz": {
      "surname": "",
      "given_names": "",
      "date_of_birth": "",
      "place_of_birth": "",
      "nationality": "",
      "passport_number": "",
      "issue_date": "",
      "expiry_date": "",
      "issuing_authority": ""
    },
    "passport_type": "ORDINAIRE|DIPLOMATIQUE|SERVICE|LAISSEZ_PASSER",
    "passport_type_confidence": 0.95
  },
  "validations": {
    "mrz_checksum_valid": true,
    "mrz_viz_match": true,
    "expiry_valid": true,
    "expiry_6months": true,
    "discrepancies": []
  }
}
PROMPT;
    }

    /**
     * Prompt pour billet d'avion
     */
    private function getFlightTicketPrompt(string $rawText): string {
        return <<<PROMPT
Analyse ce billet d'avion et extrais TOUS les vols (aller ET retour si présent).

TEXTE OCR:
{$rawText}

IMPORTANT: Un billet aller-retour contient généralement 2 vols ou plus. Identifie:
- Le vol ALLER (vers Abidjan/Côte d'Ivoire)
- Le vol RETOUR (depuis Abidjan vers le pays d'origine)

RETOURNE UN JSON STRICT:
{
  "extracted": {
    "passenger_name": "",
    "is_round_trip": true,
    "outbound_flight": {
      "flight_number": "",
      "airline": "",
      "departure": {
        "airport": "",
        "city": "",
        "date": "YYYY-MM-DD",
        "time": "HH:MM"
      },
      "arrival": {
        "airport": "",
        "city": "",
        "date": "YYYY-MM-DD",
        "time": "HH:MM"
      }
    },
    "return_flight": {
      "flight_number": "",
      "airline": "",
      "departure": {
        "airport": "",
        "city": "",
        "date": "YYYY-MM-DD",
        "time": "HH:MM"
      },
      "arrival": {
        "airport": "",
        "city": "",
        "date": "YYYY-MM-DD",
        "time": "HH:MM"
      }
    },
    "flight_number": "",
    "airline": "",
    "departure_date": "YYYY-MM-DD",
    "arrival_airport": "",
    "return_flight_number": "",
    "return_date": "YYYY-MM-DD",
    "booking_reference": "",
    "ticket_number": "",
    "seat": "",
    "class": "ECONOMY|BUSINESS|FIRST"
  },
  "validations": {
    "destination_is_abidjan": true,
    "date_is_future": true,
    "has_return_flight": true,
    "passenger_name_format_valid": true
  }
}

REGLES:
- flight_number = numéro du vol aller (ex: ET935)
- return_flight_number = numéro du vol retour (ex: ET513)
- departure_date = date du vol aller
- return_date = date du vol retour
- Si pas de vol retour détecté, laisser return_flight et return_date vides et is_round_trip=false
PROMPT;
    }

    /**
     * Prompt pour carnet de vaccination
     */
    private function getVaccinationPrompt(string $rawText): string {
        return <<<PROMPT
Analyse ce carnet de vaccination et extrais les informations sur la fièvre jaune.

TEXTE OCR:
{$rawText}

RETOURNE UN JSON STRICT:
{
  "extracted": {
    "holder_name": "",
    "date_of_birth": "",
    "certificate_number": "",
    "yellow_fever": {
      "vaccination_date": "YYYY-MM-DD",
      "valid_from": "YYYY-MM-DD",
      "valid_until": "YYYY-MM-DD ou LIFETIME",
      "batch_number": "",
      "vaccination_center": "",
      "administering_physician": ""
    },
    "other_vaccinations": []
  },
  "validations": {
    "yellow_fever_present": true,
    "yellow_fever_valid": true,
    "certificate_authentic_indicators": true,
    "name_matches_format": true
  }
}
PROMPT;
    }

    /**
     * Prompt pour réservation hôtel
     */
    private function getHotelPrompt(string $rawText): string {
        return <<<PROMPT
Analyse cette réservation d'hôtel et extrais les détails du séjour.

TEXTE OCR:
{$rawText}

RETOURNE UN JSON STRICT:
{
  "extracted": {
    "guest_name": "",
    "hotel_name": "",
    "hotel_address": "",
    "hotel_city": "",
    "hotel_country": "",
    "check_in_date": "YYYY-MM-DD",
    "check_out_date": "YYYY-MM-DD",
    "nights": 0,
    "room_type": "",
    "confirmation_number": "",
    "booking_platform": ""
  },
  "validations": {
    "location_is_cote_divoire": true,
    "dates_are_future": true,
    "dates_coherent": true,
    "confirmation_number_present": true
  }
}
PROMPT;
    }

    /**
     * Prompt pour note verbale
     */
    private function getVerbalNotePrompt(string $rawText): string {
        return <<<PROMPT
Analyse cette note verbale diplomatique.

TEXTE OCR:
{$rawText}

RETOURNE UN JSON STRICT:
{
  "extracted": {
    "sending_entity": "",
    "receiving_entity": "Ambassade de Côte d'Ivoire",
    "reference_number": "",
    "date": "YYYY-MM-DD",
    "subject": "",
    "diplomat_name": "",
    "diplomat_title": "",
    "diplomat_passport_number": "",
    "mission_purpose": "",
    "requested_visa_type": "",
    "travel_dates": {
      "from": "YYYY-MM-DD",
      "to": "YYYY-MM-DD"
    }
  },
  "validations": {
    "official_letterhead": true,
    "official_stamp": true,
    "signature_present": true,
    "addressed_to_ci_embassy": true,
    "diplomat_identified": true
  }
}
PROMPT;
    }

    /**
     * Prompt pour lettre d'invitation
     */
    private function getInvitationPrompt(string $rawText): string {
        return <<<PROMPT
Analyse cette lettre d'invitation.

TEXTE OCR:
{$rawText}

RETOURNE UN JSON STRICT:
{
  "extracted": {
    "inviter": {
      "name": "",
      "address": "",
      "city": "",
      "country": "",
      "phone": "",
      "email": "",
      "id_number": "",
      "relationship_to_invitee": ""
    },
    "invitee": {
      "name": "",
      "passport_number": "",
      "nationality": ""
    },
    "visit_details": {
      "purpose": "",
      "arrival_date": "YYYY-MM-DD",
      "departure_date": "YYYY-MM-DD",
      "accommodation_address": ""
    },
    "legalization": {
      "notarized": true,
      "notary_name": "",
      "notary_date": "YYYY-MM-DD",
      "stamp_present": true
    }
  },
  "validations": {
    "inviter_in_cote_divoire": true,
    "dates_specified": true,
    "signature_present": true,
    "legalization_valid": true
  }
}
PROMPT;
    }

    /**
     * Prompt pour preuve de paiement (NOUVEAU selon PRD)
     */
    private function getPaymentPrompt(string $rawText): string {
        return <<<PROMPT
Analyse cette preuve de paiement pour frais de visa.

TEXTE OCR:
{$rawText}

RETOURNE UN JSON STRICT:
{
  "extracted": {
    "amount": 0,
    "currency": "XOF|ETB|EUR|USD",
    "date": "YYYY-MM-DD",
    "reference": "",
    "payer": "",
    "payee": "Trésor Public Côte d'Ivoire",
    "payment_method": "VIREMENT|ESPECES|MOBILE_MONEY",
    "bank_name": "",
    "transaction_id": ""
  },
  "validations": {
    "amount_matches_expected": true,
    "date_is_recent": true,
    "payee_is_tresor_ci": true,
    "reference_format_valid": true
  }
}
PROMPT;
    }

    /**
     * Prompt pour carte de résident
     */
    private function getResidenceCardPrompt(string $rawText): string {
        return <<<PROMPT
Analyse cette carte de séjour/résidence.

TEXTE OCR:
{$rawText}

RETOURNE UN JSON STRICT:
{
  "extracted": {
    "holder_name": "",
    "card_number": "",
    "nationality": "",
    "date_of_birth": "",
    "issue_date": "YYYY-MM-DD",
    "expiry_date": "YYYY-MM-DD",
    "issuing_country": "",
    "residence_type": "TRAVAIL|ETUDES|FAMILLE|REFUGIE",
    "employer": "",
    "address": ""
  },
  "validations": {
    "card_not_expired": true,
    "issuing_country_in_jurisdiction": true,
    "photo_present": true,
    "official_format": true
  }
}
PROMPT;
    }

    /**
     * Prompt générique pour types inconnus
     */
    private function getGenericPrompt(string $rawText, string $docType): string {
        return <<<PROMPT
Analyse ce document de type "{$docType}" et extrais toutes les informations pertinentes.

TEXTE OCR:
{$rawText}

RETOURNE UN JSON avec la structure:
{
  "extracted": { ... toutes les informations identifiées ... },
  "validations": { ... vérifications applicables ... }
}
PROMPT;
    }

    /**
     * Parse la réponse de Gemini
     */
    private function parseGeminiResponse(array $response, string $docType): array {
        $content = $response['message'] ?? $response['content'] ?? '';

        // Extraire le JSON de la réponse
        if (preg_match('/\{[\s\S]*\}/m', $content, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }

        // Si pas de JSON valide, utiliser le fallback
        return $this->fallbackExtraction($content, $docType);
    }

    /**
     * Extraction de fallback - utilise les extracteurs spécialisés
     */
    private function fallbackExtraction(string $rawText, string $docType): array {
        $this->trace('fallback_extraction', "Using specialized extractor for {$docType}");

        // Utiliser l'extracteur spécialisé si disponible
        if (isset($this->extractors[$docType])) {
            $extractor = $this->extractors[$docType];
            $result = $extractor->extract($rawText);

            $this->trace('extractor_result', "Extractor returned", [
                'success' => $result['success'] ?? false,
                'fields_count' => count($result['extracted'] ?? [])
            ]);

            // Retourner les données extraites + validations
            return [
                'extracted' => $result['extracted'] ?? [],
                'validations' => $extractor->validate($result['extracted'] ?? [])
            ];
        }

        $this->trace('fallback_basic', "No specialized extractor for {$docType}, using basic patterns");

        // Extraction basique par regex si pas d'extracteur
        $extracted = [];
        $validations = [];

        // Patterns communs
        $patterns = [
            'date' => '/(\d{2}[\/\-\.]\d{2}[\/\-\.]\d{4}|\d{4}[\/\-\.]\d{2}[\/\-\.]\d{2})/i',
            'email' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/i',
            'phone' => '/\+?\d{1,4}[\s\-]?\d{6,14}/i',
            'passport_number' => '/[A-Z]{1,2}\d{6,9}/i',
            'amount' => '/(\d{1,3}(?:[,\s]\d{3})*(?:\.\d{2})?)\s*(XOF|ETB|EUR|USD|FCFA)?/i'
        ];

        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $rawText, $match)) {
                $extracted[$key] = $match[0];
            }
        }

        return [
            'extracted' => $extracted,
            'validations' => $validations
        ];
    }

    /**
     * Calcule le score de confiance global
     */
    private function calculateConfidence(array $layer1Result, array $layer2Result): float {
        $layer1Conf = $layer1Result['confidence'] ?? 0;
        $layer2Fields = count($layer2Result['extracted'] ?? []);
        $layer2Validations = array_filter($layer2Result['validations'] ?? [], fn($v) => $v === true);
        $layer2ValidationRate = count($layer2Validations) / max(1, count($layer2Result['validations'] ?? []));

        // Pondération: 40% OCR confidence, 30% fields extracted, 30% validations passed
        $confidence = ($layer1Conf * 0.4) +
                      (min(1, $layer2Fields / 10) * 0.3) +
                      ($layer2ValidationRate * 0.3);

        return round($confidence, 3);
    }

    /**
     * Ajoute une trace d'exécution
     */
    private function trace(string $action, string $message, array $data = []): void {
        $this->traces[] = [
            'timestamp' => microtime(true),
            'time' => date('H:i:s.') . substr(microtime(), 2, 3),
            'action' => $action,
            'message' => $message,
            'data' => $data
        ];

        if ($this->debug) {
            error_log("[OCRService] {$action}: {$message}");
        }
    }

    /**
     * Retourne les traces d'exécution
     */
    public function getTraces(): array {
        return $this->traces;
    }

    /**
     * Vérifie si Gemini est disponible
     */
    public function isGeminiAvailable(): bool {
        return $this->geminiClient !== null;
    }

    /**
     * Retourne le client Vision pour utilisation externe
     */
    public function getVisionClient(): \GoogleVisionClient {
        return $this->visionClient;
    }

    /**
     * Retourne le client Gemini pour utilisation externe
     */
    public function getGeminiClient(): ?\GeminiClient {
        return $this->geminiClient;
    }
}
