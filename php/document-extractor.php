<?php
/**
 * Service Universel d'Extraction de Documents
 * Ambassade de Côte d'Ivoire en Éthiopie
 * 
 * ARCHITECTURE TRIPLE LAYER:
 * - Layer 1 (OCR): Google Vision - Extraction texte brut (sync ~200ms)
 * - Layer 2 (Structuration): Gemini Flash - Structuration intelligente (sync ~800ms)
 * - Layer 3 (Validation): Claude Sonnet - Superviseur asynchrone
 * 
 * Supporte 6 types de documents:
 * - Passeport (OCR + MRZ)
 * - Billet d'avion (PDF + NLP)
 * - Réservation hôtel (PDF + NLP)
 * - Carnet vaccinal (OCR + NLP)
 * - Lettre d'invitation (NLP)
 * - Note verbale (NLP)
 * 
 * @package VisaChatbot
 * @version 2.0.0 - Triple Layer Architecture
 */

// Charger les dépendances du module OCR existant
require_once dirname(__DIR__) . '/../passport-ocr-module/php/config.php';
require_once dirname(__DIR__) . '/../passport-ocr-module/php/google-vision-client.php';
require_once dirname(__DIR__) . '/../passport-ocr-module/php/claude-client.php';
require_once dirname(__DIR__) . '/../passport-ocr-module/php/pdf-converter.php';
require_once dirname(__DIR__) . '/../passport-ocr-module/php/security-helper.php';

// Charger Gemini (Layer 2)
require_once __DIR__ . '/gemini-client.php';

// Charger les prompts (fallback si Gemini indisponible)
require_once __DIR__ . '/prompts/flight-ticket-prompt.php';
require_once __DIR__ . '/prompts/hotel-reservation-prompt.php';
require_once __DIR__ . '/prompts/vaccination-card-prompt.php';
require_once __DIR__ . '/prompts/invitation-letter-prompt.php';

class DocumentExtractor {
    
    /**
     * Types de documents supportés
     */
    public const DOCUMENT_TYPES = [
        'passport' => [
            'name' => 'Passeport',
            'nameFr' => 'Passeport',
            'nameEn' => 'Passport',
            'icon' => '🛂',
            'priority' => 1,
            'required' => true,
            'accepts' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
            'maxSize' => 10485760 // 10MB
        ],
        'ticket' => [
            'name' => 'Billet d\'avion',
            'nameFr' => 'Billet d\'avion',
            'nameEn' => 'Flight Ticket',
            'icon' => '✈️',
            'priority' => 2,
            'required' => true,
            'accepts' => ['application/pdf', 'image/jpeg', 'image/png'],
            'maxSize' => 10485760
        ],
        'hotel' => [
            'name' => 'Réservation hôtel',
            'nameFr' => 'Réservation hôtel',
            'nameEn' => 'Hotel Reservation',
            'icon' => '🏨',
            'priority' => 3,
            'required' => true,
            'accepts' => ['application/pdf', 'image/jpeg', 'image/png'],
            'maxSize' => 10485760
        ],
        'vaccination' => [
            'name' => 'Carnet vaccinal',
            'nameFr' => 'Carnet vaccinal',
            'nameEn' => 'Vaccination Card',
            'icon' => '💉',
            'priority' => 4,
            'required' => true,
            'accepts' => ['image/jpeg', 'image/png', 'application/pdf'],
            'maxSize' => 10485760
        ],
        'invitation' => [
            'name' => 'Lettre d\'invitation',
            'nameFr' => 'Lettre d\'invitation',
            'nameEn' => 'Invitation Letter',
            'icon' => '📄',
            'priority' => 5,
            'required' => false,
            'accepts' => ['application/pdf', 'image/jpeg', 'image/png'],
            'maxSize' => 10485760
        ],
        'verbal_note' => [
            'name' => 'Note verbale',
            'nameFr' => 'Note verbale',
            'nameEn' => 'Verbal Note',
            'icon' => '💼',
            'priority' => 6,
            'required' => false, // Required only for diplomatic passports
            'accepts' => ['application/pdf', 'image/jpeg', 'image/png'],
            'maxSize' => 10485760
        ],
        'residence_card' => [
            'name' => 'Carte de séjour',
            'nameFr' => 'Carte de séjour/résidence',
            'nameEn' => 'Residence Card/Permit',
            'icon' => '🪪',
            'priority' => 7,
            'required' => false, // Required for non-nationals in residence country
            'accepts' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
            'maxSize' => 10485760
        ],
        'accommodation' => [
            'name' => 'Attestation d\'hébergement',
            'nameFr' => 'Attestation d\'hébergement',
            'nameEn' => 'Accommodation Certificate',
            'icon' => '🏠',
            'priority' => 8,
            'required' => false, // Required for ordinary passports
            'accepts' => ['application/pdf', 'image/jpeg', 'image/png'],
            'maxSize' => 10485760
        ],
        'financial_proof' => [
            'name' => 'Justificatif de ressources',
            'nameFr' => 'Justificatif de ressources financières',
            'nameEn' => 'Proof of Financial Resources',
            'icon' => '💰',
            'priority' => 9,
            'required' => false, // Required for ordinary passports
            'accepts' => ['application/pdf', 'image/jpeg', 'image/png'],
            'maxSize' => 10485760
        ]
    ];
    
    /**
     * Matrice des exigences par type de passeport
     * Selon la matrice officielle de l'Ambassade
     */
    public const PASSPORT_REQUIREMENTS_MATRIX = [
        'ORDINAIRE' => [
            'workflow' => 'STANDARD',
            'required' => ['passport', 'ticket', 'vaccination', 'accommodation', 'financial_proof', 'invitation'],
            'conditional' => ['hotel'], // Alternative à accommodation
            'optional' => ['residence_card'], // Si résident hors nationalité
            'fees' => true,
            'processing_days' => '5-10',
            'verbal_note' => false
        ],
        'OFFICIEL' => [
            'workflow' => 'STANDARD',
            'required' => ['passport', 'ticket', 'vaccination', 'accommodation', 'financial_proof', 'invitation'],
            'conditional' => ['hotel'], // Alternative à accommodation
            'optional' => ['residence_card'],
            'fees' => true,
            'processing_days' => '5-10',
            'verbal_note' => false
        ],
        'DIPLOMATIQUE' => [
            'workflow' => 'PRIORITY',
            'required' => ['passport', 'ticket', 'verbal_note', 'vaccination'],
            'conditional' => [], // Pas besoin d'hôtel/invitation
            'optional' => [],
            'fees' => false, // Gratuit
            'processing_days' => '24-48h',
            'verbal_note' => true
        ],
        'SERVICE' => [
            'workflow' => 'PRIORITY',
            'required' => ['passport', 'ticket', 'verbal_note', 'vaccination'],
            'conditional' => [],
            'optional' => [],
            'fees' => false,
            'processing_days' => '24-48h',
            'verbal_note' => true
        ],
        'LP_ONU' => [ // Laissez-Passer ONU
            'workflow' => 'PRIORITY',
            'required' => ['passport', 'ticket', 'verbal_note'],
            'conditional' => [],
            'optional' => ['vaccination'], // Souvent exempté
            'fees' => false,
            'processing_days' => '24-48h',
            'verbal_note' => true
        ],
        'LP_UA' => [ // Laissez-Passer Union Africaine
            'workflow' => 'PRIORITY',
            'required' => ['passport', 'ticket', 'verbal_note'],
            'conditional' => [],
            'optional' => ['vaccination'],
            'fees' => false,
            'processing_days' => '24-48h',
            'verbal_note' => true
        ],
        'LP_INTERNATIONAL' => [ // Laissez-Passer de toute organisation internationale (UE, OTAN, CEDEAO, Banque Mondiale, FMI, INTERPOL, etc.)
            'workflow' => 'PRIORITY',
            'required' => ['passport', 'ticket', 'verbal_note'],
            'conditional' => [],
            'optional' => ['vaccination'],
            'fees' => false,
            'processing_days' => '24-48h',
            'verbal_note' => true
        ],
        'EMERGENCY' => [ // Titre de voyage d'urgence
            'workflow' => 'STANDARD',
            'required' => ['passport', 'ticket'],
            'conditional' => ['hotel', 'invitation'],
            'optional' => ['vaccination'],
            'fees' => true,
            'processing_days' => '5-10',
            'verbal_note' => false
        ],
        'LAISSEZ_PASSER' => [ // Laissez-Passer générique (toute organisation) - NON ÉLIGIBLE
            'workflow' => 'NOT_ELIGIBLE',
            'required' => [],
            'conditional' => [],
            'optional' => [],
            'fees' => false,
            'processing_days' => 'N/A',
            'verbal_note' => false,
            'eligible' => false,
            'message_fr' => 'Les Laissez-Passer ne peuvent pas être utilisés pour une demande de visa. Veuillez fournir votre passeport ordinaire.',
            'message_en' => 'Laissez-Passer documents cannot be used for a visa application. Please provide your ordinary passport.'
        ],
        'TRAVEL_REFUGEE' => [ // Document de voyage pour réfugié
            'workflow' => 'MANUAL_REVIEW',
            'required' => ['passport', 'ticket'],
            'conditional' => [],
            'optional' => ['vaccination'],
            'fees' => true,
            'processing_days' => 'Variable',
            'verbal_note' => false,
            'eligible' => 'review',
            'message_fr' => 'Les documents de voyage pour réfugiés nécessitent une évaluation manuelle.',
            'message_en' => 'Refugee travel documents require manual review.'
        ],
        'TRAVEL_STATELESS' => [ // Document de voyage pour apatride
            'workflow' => 'MANUAL_REVIEW',
            'required' => ['passport', 'ticket'],
            'conditional' => [],
            'optional' => ['vaccination'],
            'fees' => true,
            'processing_days' => 'Variable',
            'verbal_note' => false,
            'eligible' => 'review',
            'message_fr' => 'Les documents de voyage pour apatrides nécessitent une évaluation manuelle.',
            'message_en' => 'Stateless travel documents require manual review.'
        ]
    ];
    
    /**
     * Client Google Vision (Layer 1 - OCR)
     */
    private GoogleVisionClient $googleVision;
    
    /**
     * Client Gemini (Layer 2 - Structuration)
     */
    private ?GeminiClient $gemini = null;
    
    /**
     * Client Claude (Layer 3 - Validation/Fallback)
     */
    private ClaudeClient $claude;
    
    /**
     * Convertisseur PDF
     */
    private PdfConverter $pdfConverter;
    
    /**
     * Configuration
     */
    private array $config;
    
    /**
     * Traces du workflow Triple Layer
     */
    private array $workflowTrace = [];

    /**
     * Répertoire de cache OCR
     */
    private string $cacheDir;

    /**
     * Activer le cache OCR
     */
    private bool $useCache = true;

    /**
     * Durée de vie du cache en secondes (24h par défaut)
     */
    private int $cacheTtl = 86400;

    /**
     * Données du passeport pour cross-validation des noms
     */
    private ?array $passportData = null;

    /**
     * Extracteurs modulaires V2 (chargés dynamiquement)
     * @var array<string, \VisaChatbot\Extractors\AbstractExtractor>
     */
    private array $modularExtractors = [];

    /**
     * Utiliser les extracteurs modulaires quand disponibles
     */
    private bool $useModularExtractors = true;

    /**
     * Constructeur
     */
    public function __construct(array $options = []) {
        $this->config = [
            'debug' => $options['debug'] ?? (defined('DEBUG_MODE') && DEBUG_MODE),
            'timeout' => $options['timeout'] ?? 60,
            'use_gemini' => $options['use_gemini'] ?? true,
            'use_modular_extractors' => $options['use_modular_extractors'] ?? true
        ];

        // Configuration extracteurs modulaires
        $this->useModularExtractors = $this->config['use_modular_extractors'];

        // Configuration du cache
        $this->useCache = $options['use_cache'] ?? true;
        $this->cacheTtl = $options['cache_ttl'] ?? 86400;
        $this->cacheDir = $options['cache_dir'] ?? dirname(__DIR__) . '/cache/ocr';

        // Créer le répertoire de cache si nécessaire
        if ($this->useCache && !is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
        
        // Initialiser Layer 1: Google Vision (OCR)
        // CRITIQUE: Timeout augmenté à 60s pour les images complexes/grandes
        $this->googleVision = new GoogleVisionClient([
            'debug' => $this->config['debug'],
            'timeout' => 60
        ]);
        $this->addWorkflowTrace('layer1_init', 'Google Vision initialized');
        
        // Initialiser Layer 2: Gemini (Structuration)
        $geminiApiKey = getenv('GEMINI_API_KEY');
        if ($this->config['use_gemini'] && !empty($geminiApiKey)) {
            try {
                $this->gemini = new GeminiClient([
                    'api_key' => $geminiApiKey,
                    'debug' => $this->config['debug']
                ]);
                $this->addWorkflowTrace('layer2_init', 'Gemini initialized');
            } catch (Exception $e) {
                $this->log("Gemini init failed, will use Claude as fallback: " . $e->getMessage());
                $this->gemini = null;
            }
        }
        
        // Initialiser Layer 3: Claude (Validation/Fallback)
        $this->claude = new ClaudeClient(['debug' => $this->config['debug']]);
        $this->addWorkflowTrace('layer3_init', 'Claude initialized (supervisor)');
        
        $this->pdfConverter = new PdfConverter(['debug' => $this->config['debug']]);

        // Charger les extracteurs modulaires si disponibles
        if ($this->useModularExtractors) {
            $this->loadModularExtractors();
        }
    }

    /**
     * Charge les extracteurs modulaires depuis /php/extractors/
     * Ces extracteurs V2 offrent une meilleure séparation des responsabilités
     */
    private function loadModularExtractors(): void {
        $extractorsPath = __DIR__ . '/extractors';

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

        // Charger la classe abstraite en premier
        $abstractFile = $extractorsPath . '/AbstractExtractor.php';
        if (file_exists($abstractFile)) {
            require_once $abstractFile;
        } else {
            $this->addWorkflowTrace('modular_extractors_skip', 'AbstractExtractor.php not found');
            return;
        }

        $loaded = 0;
        foreach ($extractorClasses as $type => $className) {
            $classFile = $extractorsPath . '/' . $className . '.php';
            if (file_exists($classFile)) {
                try {
                    require_once $classFile;
                    $fullClassName = "VisaChatbot\\Extractors\\{$className}";
                    if (class_exists($fullClassName)) {
                        // Passer $this comme référence pour accéder à Google Vision
                        $this->modularExtractors[$type] = new $fullClassName(null, [
                            'debug' => $this->config['debug']
                        ]);
                        $loaded++;
                    }
                } catch (Exception $e) {
                    $this->log("Failed to load modular extractor {$className}: " . $e->getMessage());
                }
            }
        }

        if ($loaded > 0) {
            $this->addWorkflowTrace('modular_extractors_loaded', "Loaded {$loaded} modular extractors", [
                'types' => array_keys($this->modularExtractors)
            ]);
        }
    }

    /**
     * Vérifie si un extracteur modulaire est disponible pour un type
     */
    public function hasModularExtractor(string $type): bool {
        return isset($this->modularExtractors[$type]);
    }

    /**
     * Retourne les types d'extracteurs modulaires disponibles
     */
    public function getModularExtractorTypes(): array {
        return array_keys($this->modularExtractors);
    }

    /**
     * Extrait les données en utilisant un extracteur modulaire V2
     *
     * @param string $type Type de document
     * @param string $rawText Texte OCR brut
     * @param array $ocrMetadata Métadonnées OCR
     * @return array|null Résultat ou null si pas d'extracteur disponible
     */
    private function extractWithModularExtractor(string $type, string $rawText, array $ocrMetadata = []): ?array {
        if (!isset($this->modularExtractors[$type])) {
            return null;
        }

        try {
            $extractor = $this->modularExtractors[$type];
            $this->addWorkflowTrace('modular_extraction_start', "Using modular {$type} extractor");

            // Appeler la méthode extract de l'extracteur modulaire
            $extracted = $extractor->extract($rawText, $ocrMetadata);

            // Valider les données extraites
            $validations = $extractor->validate($extracted);

            $this->addWorkflowTrace('modular_extraction_complete', "Modular {$type} extraction complete", [
                'fields_count' => count($extracted),
                'validation_passed' => $validations['valid'] ?? false
            ]);

            return [
                'extracted' => $extracted,
                'validations' => $validations,
                'extractor_type' => 'modular_v2',
                'extractor_class' => get_class($extractor)
            ];
        } catch (Exception $e) {
            $this->log("Modular extraction failed for {$type}: " . $e->getMessage());
            $this->addWorkflowTrace('modular_extraction_fallback', "Falling back to inline extractor for {$type}");
            return null;
        }
    }

    /**
     * Vérifie si on doit utiliser l'extracteur modulaire pour ce type
     */
    private function shouldUseModularExtractor(string $type): bool {
        return $this->useModularExtractors && isset($this->modularExtractors[$type]);
    }

    /**
     * Ajoute une trace au workflow
     */
    private function addWorkflowTrace(string $action, string $details, array $metadata = []): void {
        $this->workflowTrace[] = [
            'timestamp' => microtime(true),
            'action' => $action,
            'details' => $details,
            'metadata' => $metadata
        ];
    }
    
    /**
     * Retourne les traces du workflow
     */
    public function getWorkflowTrace(): array {
        return $this->workflowTrace;
    }

    /**
     * Définit les données du passeport pour cross-validation
     *
     * @param array $passportData Données extraites du passeport (fields.surname.value, fields.given_names.value)
     */
    public function setPassportData(array $passportData): void {
        $this->passportData = $passportData;
        $this->addWorkflowTrace('passport_data_set', 'Passport data set for cross-validation', [
            'has_surname' => !empty($this->getPassportFullName())
        ]);
    }

    /**
     * Retourne le nom complet du passeport (SURNAME + GIVEN_NAMES)
     */
    private function getPassportFullName(): ?string {
        if ($this->passportData === null) {
            return null;
        }

        $surname = $this->passportData['fields']['surname']['value']
            ?? $this->passportData['surname']
            ?? null;
        $givenNames = $this->passportData['fields']['given_names']['value']
            ?? $this->passportData['given_names']
            ?? null;

        if ($surname && $givenNames) {
            return strtoupper(trim($surname . ' ' . $givenNames));
        }

        return $surname ? strtoupper($surname) : ($givenNames ? strtoupper($givenNames) : null);
    }

    /**
     * Cross-valide un nom extrait avec les données du passeport
     *
     * Si le nom extrait est partiel (seulement prénom ou nom de famille),
     * on le complète avec les informations du passeport.
     *
     * @param string|null $extractedName Nom extrait du document
     * @return array ['name' => string, 'cross_validated' => bool, 'match_type' => string]
     */
    private function crossValidateNameWithPassport(?string $extractedName): array {
        $result = [
            'name' => $extractedName,
            'cross_validated' => false,
            'match_type' => 'none'
        ];

        if (empty($extractedName) || $this->passportData === null) {
            return $result;
        }

        $passportFullName = $this->getPassportFullName();
        if (!$passportFullName) {
            return $result;
        }

        // Normaliser les noms pour comparaison
        $extractedNormalized = $this->normalizeNameForComparison($extractedName);
        $passportNormalized = $this->normalizeNameForComparison($passportFullName);

        // Cas 1: Match exact
        if ($extractedNormalized === $passportNormalized) {
            $result['cross_validated'] = true;
            $result['match_type'] = 'exact';
            return $result;
        }

        // Cas 2: Le nom extrait est un sous-ensemble du nom du passeport
        $extractedParts = preg_split('/\s+/', $extractedNormalized);
        $passportParts = preg_split('/\s+/', $passportNormalized);

        $matchingParts = 0;
        foreach ($extractedParts as $part) {
            if (in_array($part, $passportParts)) {
                $matchingParts++;
            }
        }

        // Si au moins 50% des parties du nom extrait matchent
        if ($matchingParts >= count($extractedParts) * 0.5 && $matchingParts > 0) {
            // Compléter avec le nom complet du passeport
            $result['name'] = $passportFullName;
            $result['cross_validated'] = true;
            $result['match_type'] = 'partial_completed';
            $result['original_name'] = $extractedName;
            $result['matched_parts'] = $matchingParts . '/' . count($extractedParts);

            $this->addWorkflowTrace('name_cross_validation', 'Partial name completed with passport data', [
                'original' => $extractedName,
                'completed' => $passportFullName,
                'matched_parts' => $matchingParts
            ]);
        }

        return $result;
    }

    /**
     * Normalise un nom pour comparaison (majuscules, sans accents, sans tirets)
     */
    private function normalizeNameForComparison(string $name): string {
        // Majuscules
        $name = strtoupper($name);

        // Supprimer les accents
        $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);

        // Supprimer les caractères spéciaux sauf espaces
        $name = preg_replace('/[^A-Z\s]/', '', $name);

        // Normaliser les espaces multiples
        $name = preg_replace('/\s+/', ' ', trim($name));

        return $name;
    }

    /**
     * Applique la cross-validation des noms sur un résultat d'extraction
     *
     * @param string $type Type de document (hotel, vaccination, ticket, invitation)
     * @param array $result Résultat d'extraction
     * @return array Résultat avec noms cross-validés
     */
    private function applyCrossValidation(string $type, array $result): array {
        if ($this->passportData === null) {
            return $result;
        }

        // Mapper les champs de nom par type de document
        $nameFields = [
            'hotel' => 'guest_name',
            'vaccination' => 'holder_name',
            'ticket' => 'passenger_name',
            'invitation' => ['invitee.name', 'invitee_name']
        ];

        $fieldToValidate = $nameFields[$type] ?? null;
        if ($fieldToValidate === null) {
            return $result;
        }

        // Gérer les champs multiples (comme invitation)
        $fields = is_array($fieldToValidate) ? $fieldToValidate : [$fieldToValidate];

        foreach ($fields as $field) {
            // Gérer la notation pointée (ex: invitee.name)
            $value = $this->getNestedValue($result, $field);

            if ($value) {
                $validation = $this->crossValidateNameWithPassport($value);

                if ($validation['cross_validated']) {
                    // Mettre à jour le champ avec le nom complété
                    $result = $this->setNestedValue($result, $field, $validation['name']);

                    // Ajouter les métadonnées de cross-validation
                    if (!isset($result['_metadata']['cross_validation'])) {
                        $result['_metadata']['cross_validation'] = [];
                    }
                    $result['_metadata']['cross_validation'][$field] = $validation;
                }
            }
        }

        return $result;
    }

    /**
     * Récupère une valeur imbriquée (notation pointée)
     */
    private function getNestedValue(array $array, string $path) {
        $keys = explode('.', $path);
        $value = $array;
        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }
        return $value;
    }

    /**
     * Définit une valeur imbriquée (notation pointée)
     */
    private function setNestedValue(array $array, string $path, $value): array {
        $keys = explode('.', $path);
        $ref = &$array;
        foreach ($keys as $key) {
            if (!isset($ref[$key])) {
                $ref[$key] = [];
            }
            $ref = &$ref[$key];
        }
        $ref = $value;
        return $array;
    }

    /**
     * Génère une clé de cache unique pour un document
     */
    private function getCacheKey(string $type, string $content): string {
        return $type . '_' . md5($content);
    }

    /**
     * Récupère un résultat depuis le cache
     */
    private function getFromCache(string $type, string $content): ?array {
        if (!$this->useCache) {
            return null;
        }

        $cacheKey = $this->getCacheKey($type, $content);
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.json';

        if (!file_exists($cacheFile)) {
            return null;
        }

        // Vérifier l'expiration
        if (filemtime($cacheFile) < (time() - $this->cacheTtl)) {
            @unlink($cacheFile);
            return null;
        }

        $data = @file_get_contents($cacheFile);
        if ($data === false) {
            return null;
        }

        $result = json_decode($data, true);
        if ($result === null) {
            return null;
        }

        $this->addWorkflowTrace('cache_hit', 'Result loaded from cache', ['cache_key' => $cacheKey]);
        $result['_metadata']['from_cache'] = true;
        $result['_metadata']['cache_key'] = $cacheKey;

        return $result;
    }

    /**
     * Sauvegarde un résultat dans le cache
     */
    private function saveToCache(string $type, string $content, array $result): void {
        if (!$this->useCache) {
            return;
        }

        $cacheKey = $this->getCacheKey($type, $content);
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.json';

        // Ne pas mettre en cache les erreurs
        if (isset($result['error'])) {
            return;
        }

        @file_put_contents($cacheFile, json_encode($result, JSON_PRETTY_PRINT));
        $this->addWorkflowTrace('cache_save', 'Result saved to cache', ['cache_key' => $cacheKey]);
    }

    /**
     * Vide le cache OCR
     */
    public function clearCache(): int {
        $count = 0;
        $files = glob($this->cacheDir . '/*.json');
        foreach ($files as $file) {
            if (@unlink($file)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Vérifie si Gemini (Layer 2) est disponible
     */
    public function isGeminiAvailable(): bool {
        return $this->gemini !== null;
    }

    /**
     * Extrait les données d'un document selon son type
     * Pipeline Triple Layer:
     * 1. Google Vision (Layer 1) → Extraction texte brut
     * 2. Gemini Flash (Layer 2) → Structuration intelligente
     * 3. Claude Sonnet (Layer 3) → Validation asynchrone (optionnel)
     * 
     * @param string $type Type de document (passport, ticket, hotel, vaccination, invitation, verbal_note)
     * @param string $content Contenu base64 du fichier
     * @param string $mimeType Type MIME du fichier
     * @param bool $validateWithClaude Active la validation Layer 3
     * @return array Données extraites avec scores de confiance
     */
    public function extract(string $type, string $content, string $mimeType, bool $validateWithClaude = false): array {
        $startTime = microtime(true);
        $this->workflowTrace = []; // Reset traces for this extraction

        $this->addWorkflowTrace('extraction_start', "Starting {$type} extraction", [
            'document_type' => $type,
            'mime_type' => $mimeType
        ]);

        // Valider le type de document
        if (!isset(self::DOCUMENT_TYPES[$type])) {
            throw new Exception("Type de document non supporté: {$type}");
        }

        // Valider le type MIME
        $allowedTypes = self::DOCUMENT_TYPES[$type]['accepts'];
        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception("Format de fichier non accepté pour {$type}. Formats acceptés: " . implode(', ', $allowedTypes));
        }

        // Vérifier le cache AVANT tout traitement
        $cachedResult = $this->getFromCache($type, $content);
        if ($cachedResult !== null) {
            $cachedResult['_metadata']['extraction_time'] = round(microtime(true) - $startTime, 3);
            return $cachedResult;
        }

        // Convertir PDF en image si nécessaire
        $imageContent = $content;
        $imageMimeType = $mimeType;
        $pdfConverted = false;
        $pdfPagesCount = 1;
        $combinedOcrText = null;

        if ($mimeType === 'application/pdf') {
            // Pour hotel et vaccination, extraire TOUTES les pages car les dates
            // peuvent être sur d'autres pages (emails Booking.com, certificats multi-pages)
            $multiPageTypes = ['hotel', 'vaccination', 'ticket', 'invitation'];

            if (in_array($type, $multiPageTypes)) {
                // Compter les pages du PDF
                $pdfPagesCount = $this->pdfConverter->getPageCount($content);
                $pdfPagesCount = min($pdfPagesCount, 5); // Max 5 pages pour éviter les temps trop longs

                $this->addWorkflowTrace('pdf_multipage', "PDF has {$pdfPagesCount} pages, extracting all");

                // Extraire et OCR toutes les pages
                $allTexts = [];
                for ($page = 1; $page <= $pdfPagesCount; $page++) {
                    $conversionResult = $this->pdfConverter->convertToImage($content, $page);
                    if ($conversionResult['success']) {
                        try {
                            $ocrResult = $this->googleVision->extractText(
                                $conversionResult['image'],
                                $conversionResult['mime_type']
                            );
                            if (!empty($ocrResult['full_text'])) {
                                $allTexts[] = "--- PAGE {$page} ---\n" . $ocrResult['full_text'];
                            }
                        } catch (Exception $e) {
                            $this->log("OCR page {$page} failed: " . $e->getMessage());
                        }
                    }
                }

                // Combiner tous les textes OCR
                $combinedOcrText = implode("\n\n", $allTexts);
                $this->addWorkflowTrace('pdf_ocr_combined', "Combined OCR from {$pdfPagesCount} pages", [
                    'total_chars' => strlen($combinedOcrText)
                ]);

                // Utiliser la première page comme image de référence
                $conversionResult = $this->pdfConverter->convertToImage($content, 1);
                $imageContent = $conversionResult['image'];
                $imageMimeType = $conversionResult['mime_type'];
            } else {
                // Pour les autres types, juste la première page
                $conversionResult = $this->pdfConverter->convertToImage($content, 1);
                if (!$conversionResult['success']) {
                    throw new Exception('Erreur conversion PDF: ' . $conversionResult['error']);
                }
                $imageContent = $conversionResult['image'];
                $imageMimeType = $conversionResult['mime_type'];
            }

            $pdfConverted = true;
            $this->addWorkflowTrace('pdf_conversion', 'PDF converted to image');
        }

        // =====================================================================
        // ARCHITECTURE HYBRIDE: Extracteurs Modulaires V2 + Inline (fallback)
        // =====================================================================
        // 1. Si un extracteur modulaire V2 existe, on l'utilise en priorité
        // 2. Sinon, on utilise l'extracteur inline historique
        // =====================================================================

        $result = null;
        $usedModularExtractor = false;

        // Essayer d'abord les extracteurs modulaires V2 (si disponibles)
        if ($this->shouldUseModularExtractor($type)) {
            // Pour les extracteurs modulaires, on doit d'abord obtenir le texte OCR brut
            $rawOcrText = $combinedOcrText; // Déjà disponible pour les PDFs multi-pages

            // Si pas de texte OCR combiné, faire l'OCR sur l'image
            if (empty($rawOcrText)) {
                try {
                    $ocrResult = $this->googleVision->extractText($imageContent, $imageMimeType);
                    $rawOcrText = $ocrResult['full_text'] ?? '';
                    $this->addWorkflowTrace('layer1_ocr', 'Google Vision OCR completed for modular extractor', [
                        'text_length' => strlen($rawOcrText)
                    ]);
                } catch (Exception $e) {
                    $this->log("OCR failed for modular extractor: " . $e->getMessage());
                    $rawOcrText = '';
                }
            }

            // Utiliser l'extracteur modulaire
            if (!empty($rawOcrText)) {
                $modularResult = $this->extractWithModularExtractor($type, $rawOcrText, [
                    'mime_type' => $imageMimeType,
                    'pdf_converted' => $pdfConverted,
                    'pdf_pages' => $pdfPagesCount ?? 1
                ]);

                if ($modularResult !== null) {
                    // Transformer le résultat modulaire au format attendu
                    $result = [
                        'fields' => $modularResult['extracted'],
                        '_validations' => $modularResult['validations'],
                        '_metadata' => [
                            'extractor_type' => 'modular_v2',
                            'extractor_class' => $modularResult['extractor_class']
                        ]
                    ];
                    $usedModularExtractor = true;
                    $this->addWorkflowTrace('modular_extractor_used', "Used modular V2 extractor for {$type}");
                }
            }
        }

        // Fallback: Utiliser les extracteurs inline si pas de résultat modulaire
        if ($result === null) {
            // Router vers l'extracteur inline approprié
            // Passer le texte OCR combiné si disponible (pour PDFs multi-pages)
            $result = match($type) {
                'passport' => $this->extractPassport($imageContent, $imageMimeType),
                'ticket' => $this->extractFlightTicket($imageContent, $imageMimeType, $combinedOcrText),
                'hotel' => $this->extractHotelReservation($imageContent, $imageMimeType, $combinedOcrText),
                'vaccination' => $this->extractVaccinationCard($imageContent, $imageMimeType, $combinedOcrText),
                'invitation' => $this->extractInvitationLetter($imageContent, $imageMimeType, $combinedOcrText),
                'verbal_note' => $this->extractVerbalNote($imageContent, $imageMimeType),
                'residence_card' => $this->extractResidenceCard($imageContent, $imageMimeType),
                default => throw new Exception("Extracteur non implémenté pour: {$type}")
            };
        }

        // Cross-validation des noms avec les données du passeport
        // Appliqué pour hotel, vaccination, ticket, invitation
        if (in_array($type, ['hotel', 'vaccination', 'ticket', 'invitation'])) {
            $result = $this->applyCrossValidation($type, $result);
        }

        // Layer 3: Validation Claude (optionnelle, asynchrone en production)
        if ($validateWithClaude) {
            $result['_claude_validation'] = $this->requestClaudeValidation($type, $result);
            $this->addWorkflowTrace('layer3_validation', 'Claude validation completed');
        }
        
        // Ajouter les métadonnées enrichies
        $result['_metadata'] = array_merge($result['_metadata'] ?? [], [
            'document_type' => $type,
            'document_info' => self::DOCUMENT_TYPES[$type],
            'pdf_converted' => $pdfConverted,
            'total_processing_time' => round(microtime(true) - $startTime, 3),
            'timestamp' => date('c'),
            'triple_layer' => [
                'layer1' => 'google_vision',
                'layer2' => $usedModularExtractor ? 'modular_v2_extractor' : ($this->isGeminiAvailable() ? 'gemini_flash' : 'claude_fallback'),
                'layer3' => $validateWithClaude ? 'claude_validated' : 'skipped'
            ],
            'modular_extractor_used' => $usedModularExtractor,
            'modular_extractors_available' => $this->getModularExtractorTypes(),
            'workflow_trace' => $this->workflowTrace
        ]);
        
        $this->log("Extraction {$type} terminée en {$result['_metadata']['total_processing_time']}s (Layer2: {$result['_metadata']['triple_layer']['layer2']})");

        // Sauvegarder dans le cache pour les prochaines requêtes
        $this->saveToCache($type, $content, $result);

        return $result;
    }

    /**
     * Extrait les données en batch (plusieurs documents)
     * 
     * @param array $documents Liste de documents [{type, content, mimeType}, ...]
     * @return array Résultats d'extraction par type
     */
    public function extractBatch(array $documents): array {
        $results = [];
        $errors = [];
        
        foreach ($documents as $doc) {
            $type = $doc['type'] ?? null;
            $content = $doc['content'] ?? null;
            $mimeType = $doc['mimeType'] ?? null;
            
            if (!$type || !$content || !$mimeType) {
                $errors[$type ?? 'unknown'] = 'Paramètres manquants';
                continue;
            }
            
            try {
                $results[$type] = $this->extract($type, $content, $mimeType);
            } catch (Exception $e) {
                $errors[$type] = $e->getMessage();
                $results[$type] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    '_metadata' => [
                        'document_type' => $type,
                        'timestamp' => date('c')
                    ]
                ];
            }
        }
        
        return [
            'results' => $results,
            'errors' => $errors,
            'summary' => [
                'total' => count($documents),
                'success' => count($documents) - count($errors),
                'failed' => count($errors)
            ]
        ];
    }
    
    /**
     * Extraction passeport (Pipeline Triple Layer)
     * Layer 1: Google Vision OCR + MRZ detection
     * Layer 2: Gemini structuration (ou Claude fallback)
     * Layer 2.5: Cross-validation MRZ ↔ VIZ
     */
    private function extractPassport(string $content, string $mimeType): array {
        $layer1Start = microtime(true);
        
        // === LAYER 1: Google Vision OCR ===
        $ocrResult = $this->googleVision->extractText($content, $mimeType);
        $this->addWorkflowTrace('layer1_ocr', 'Google Vision OCR completed', [
            'chars_extracted' => strlen($ocrResult['full_text']),
            'confidence' => $ocrResult['confidence'],
            'processing_time' => round(microtime(true) - $layer1Start, 3)
        ]);
        
        // Détecter et parser MRZ depuis le texte OCR
        $mrzData = $this->googleVision->detectMRZFromText($ocrResult['full_text']);
        $this->addWorkflowTrace('layer1_mrz', 'MRZ detection completed', [
            'detected' => $mrzData !== null,
            'has_parsed' => isset($mrzData['parsed'])
        ]);
        
        $layer2Start = microtime(true);
        
        // === LAYER 2: Structuration intelligente ===
        $structuredData = null;
        $layer2Source = 'none';
        
        // Essayer Gemini d'abord (Layer 2 préféré)
        if ($this->isGeminiAvailable()) {
            try {
                $structuredData = $this->gemini->structureDocument($ocrResult['full_text'], 'passport');
                $layer2Source = 'gemini_flash';
                $this->addWorkflowTrace('layer2_gemini', 'Gemini structuration completed', [
                    'processing_time' => round(microtime(true) - $layer2Start, 3)
                ]);
            } catch (Exception $e) {
                $this->log("Gemini passport structuration failed: " . $e->getMessage());
                $this->addWorkflowTrace('layer2_gemini_error', 'Gemini failed, trying Claude', [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Fallback sur Claude si Gemini échoue
        if ($structuredData === null) {
            $structuredData = $this->claude->structurePassportText(
                $ocrResult['full_text'],
                $mrzData ?? []
            );
            $layer2Source = 'claude_fallback';
            $this->addWorkflowTrace('layer2_claude', 'Claude fallback structuration completed', [
                'processing_time' => round(microtime(true) - $layer2Start, 3)
            ]);
        }
        
        // Fusionner les données MRZ détectées par Google Vision avec celles de Gemini
        // Priorité aux données parsées par Google Vision (plus fiables)
        if ($mrzData !== null && isset($mrzData['parsed'])) {
            // Si Gemini n'a pas fourni de MRZ ou si la détection GV est plus complète
            if (!isset($structuredData['mrz_data']) || !isset($structuredData['mrz_data']['parsed'])) {
                $structuredData['mrz_data'] = [
                    'line1' => $mrzData['line1'],
                    'line2' => $mrzData['line2'],
                    'parsed' => $mrzData['parsed']
                ];
            }
        }
        
        $structuredData['mrz'] = $mrzData ?? ['detected' => false];
        
        // === LAYER 2.5: Cross-validation MRZ ↔ VIZ ===
        $crossValidationStart = microtime(true);
        $structuredData = $this->crossValidateMRZandVIZ($structuredData, $mrzData);
        $this->addWorkflowTrace('layer2_5_crossvalidation', 'MRZ-VIZ cross-validation completed', [
            'processing_time' => round(microtime(true) - $crossValidationStart, 5)
        ]);
        
        $structuredData['_metadata'] = [
            'ocr_confidence' => $ocrResult['confidence'],
            'ocr_chars' => strlen($ocrResult['full_text']),
            'mrz_detected' => $mrzData !== null,
            'mrz_parsed' => isset($mrzData['parsed']),
            'layer2_source' => $layer2Source
        ];
        
        // === ARCHITECTURE EXPERT: Classification hybride MRZ + IA ===
        // Priorité 1: Classification MRZ (déterministe, basée sur codes ICAO)
        // Priorité 2: Classification IA (sémantique, pour enrichissement)
        
        // Récupérer la classification MRZ (générée par crossValidateMRZandVIZ)
        $mrzClassification = $structuredData['mrz_classification'] ?? null;
        $aiClassification = $structuredData['document_classification'] ?? null;
        $aiEligibility = $structuredData['visa_eligibility'] ?? null;
        
        // Déterminer la classification finale (MRZ prioritaire si disponible)
        if ($mrzClassification !== null) {
            // === SOURCE PRIMAIRE: Classification MRZ (ICAO) ===
            $category = $mrzClassification['category'];
            $subcategory = $mrzClassification['subcategory'];
            $isValidForVisa = $mrzClassification['is_valid_for_evisa'];
            $issuingOrg = $mrzClassification['organization_name'];
            $issuingOrgCode = $mrzClassification['issuing_state_code'];
            $hasStateNationality = $mrzClassification['has_state_nationality'];
            $detectedNationality = $mrzClassification['detected_nationality'];
            
            // Construire la classification finale enrichie avec IA
            $mrzIndicators = $mrzClassification['detected_indicators'] ?? [];
            $allIndicators = array_merge(['mrz_icao_' . $mrzClassification['document_type_code']], $mrzIndicators);
            
            $classification = [
                'category' => $category,
                'subcategory' => $subcategory,
                'issuing_organization' => $issuingOrg ?? ($aiClassification['issuing_organization'] ?? null),
                'issuing_organization_code' => $issuingOrgCode,
                'issuing_country' => $isValidForVisa ? $issuingOrgCode : null,
                'detected_indicators' => array_unique($allIndicators),
                'confidence' => 0.99, // Confiance maximale pour MRZ
                'has_state_nationality' => $hasStateNationality,
                'detected_nationality_code' => $detectedNationality,
                'source' => 'mrz_icao_primary'
            ];
            
            // Récupérer le workflow de la classification MRZ (déjà calculé)
            $mrzWorkflow = $mrzClassification['workflow'] ?? null;
            
            // Déterminer l'éligibilité basée sur MRZ + enrichissement IA
            $eligibility = [
                'is_valid' => $isValidForVisa,
                'reason_fr' => $aiEligibility['reason_fr'] ?? ($isValidForVisa 
                    ? ($subcategory === 'DIPLOMATIC' ? 'Passeport diplomatique valide - Traitement prioritaire' :
                       ($subcategory === 'SERVICE' ? 'Passeport de service valide - Traitement prioritaire' :
                       'Document valide pour e-Visa'))
                    : 'Les Laissez-Passer ne sont pas acceptés pour une demande de visa.'),
                'reason_en' => $aiEligibility['reason_en'] ?? ($isValidForVisa 
                    ? ($subcategory === 'DIPLOMATIC' ? 'Valid diplomatic passport - Priority processing' :
                       ($subcategory === 'SERVICE' ? 'Valid service passport - Priority processing' :
                       'Document valid for e-Visa'))
                    : 'Laissez-Passer documents cannot be used for visa applications.'),
                'detailed_explanation_fr' => $aiEligibility['detailed_explanation_fr'] ?? null,
                'detailed_explanation_en' => $aiEligibility['detailed_explanation_en'] ?? null,
                'workflow' => $mrzWorkflow ?? ($isValidForVisa ? ($subcategory === 'DIPLOMATIC' || $subcategory === 'SERVICE' ? 'PRIORITY' : 'STANDARD') : null),
                'has_state_nationality' => $hasStateNationality,
                'detected_nationality' => $detectedNationality
            ];
            
            $this->log("Classification Expert: MRZ primary - {$category}/{$subcategory}, nationality={$detectedNationality}, valid={$isValidForVisa}");
            
        } elseif ($aiClassification !== null && $aiEligibility !== null) {
            // === SOURCE SECONDAIRE: Classification IA (si pas de MRZ) ===
            $classification = $aiClassification;
            $eligibility = $aiEligibility;
            $classification['source'] = 'ai_fallback';
            
            $this->log("Classification Expert: AI fallback - " . ($classification['category'] ?? 'UNKNOWN'));
            
        } else {
            // === FALLBACK: Détection regex legacy ===
            $passportTypeInfo = self::detectPassportType($structuredData);
            $structuredData['passport_type_detection'] = $passportTypeInfo;
            $structuredData['is_standard_passport'] = $passportTypeInfo['is_standard_passport'];
            $structuredData['document_subtype'] = $passportTypeInfo['document_subtype'];
            
            if (!$passportTypeInfo['is_standard_passport']) {
                $structuredData['field_analysis'] = self::analyzeFieldCompleteness(
                    $structuredData['fields'] ?? [],
                    $passportTypeInfo['document_subtype'] ?? 'UNKNOWN'
                );
                $structuredData['alternatives'] = self::getDocumentAlternatives(
                    $passportTypeInfo['document_subtype'] ?? 'UNKNOWN'
                );
            }
            
            $this->log("Classification Expert: Legacy regex fallback");
            return $structuredData;
        }
        
        // === Construire la réponse finale ===
        $detectedIndicators = $classification['detected_indicators'] ?? ['classification'];
        $mappedType = $this->mapAIClassificationToType($classification);
        
        $structuredData['document_classification'] = $classification;
        $structuredData['passport_type_detection'] = [
            'type' => $mappedType,
            'confidence' => $classification['confidence'] ?? 0.95,
            'indicators' => $detectedIndicators,
            'requirements' => self::getRequiredDocuments($mappedType),
            'workflow' => $eligibility['workflow'] ?? 'STANDARD',
            'is_standard_passport' => $eligibility['is_valid'] ?? true,
            'document_subtype' => $classification['category'] ?? 'PASSPORT',
            'issuing_organization' => $classification['issuing_organization'] ?? null,
            'issuing_organization_code' => $classification['issuing_organization_code'] ?? null,
            'has_state_nationality' => $classification['has_state_nationality'] ?? null,
            'detected_nationality' => $classification['detected_nationality_code'] ?? null
        ];
        $structuredData['is_standard_passport'] = $eligibility['is_valid'] ?? true;
        $structuredData['document_subtype'] = $classification['category'] ?? 'PASSPORT';
        $structuredData['visa_eligibility'] = $eligibility;
        
        // === Analyse des champs pour documents non éligibles ===
        if (!($eligibility['is_valid'] ?? true)) {
            // Utiliser l'analyse IA si disponible
            if (isset($structuredData['field_analysis']) && isset($structuredData['field_analysis']['is_sufficient_for_visa'])) {
                $iaAnalysis = $structuredData['field_analysis'];
                $structuredData['field_analysis'] = [
                    'present_count' => count($structuredData['fields'] ?? []),
                    'missing_count' => count($iaAnalysis['critical_missing_fields'] ?? []),
                    'completeness_score' => $iaAnalysis['completeness_score'] ?? 0,
                    'is_sufficient_alone' => false,
                    'has_state_nationality' => $classification['has_state_nationality'] ?? false,
                    'detected_nationality' => $classification['detected_nationality_code'] ?? null,
                    'missing' => $iaAnalysis['missing_or_invalid_fields'] ?? [],
                    'critical_missing' => $iaAnalysis['critical_missing_fields'] ?? [],
                    'summary_fr' => $iaAnalysis['other_observations_fr'] ?? $eligibility['detailed_explanation_fr'] ?? '',
                    'summary_en' => $iaAnalysis['other_observations_en'] ?? $eligibility['detailed_explanation_en'] ?? ''
                ];
            } else {
                // Générer l'analyse basée sur MRZ + VIZ
                $structuredData['field_analysis'] = self::analyzeFieldCompleteness(
                    $structuredData['fields'] ?? [],
                    $classification['category'] ?? 'UNKNOWN'
                );
                $structuredData['field_analysis']['has_state_nationality'] = $classification['has_state_nationality'] ?? false;
                $structuredData['field_analysis']['detected_nationality'] = $classification['detected_nationality_code'] ?? null;
            }

            $structuredData['alternatives'] = self::getDocumentAlternatives(
                $classification['category'] ?? 'UNKNOWN'
            );
        }
        
        // FIN de la classification hybride - ne pas continuer avec l'ancien code

        // Ajouter le score de confiance au niveau racine pour l'affichage
        // Priorité: classification MRZ (0.99) > OCR confidence > fallback 0.95
        $structuredData['confidence'] = $classification['confidence']
            ?? $structuredData['_metadata']['ocr_confidence']
            ?? 0.95;
        $structuredData['overall_confidence'] = $structuredData['confidence'];

        return $structuredData;
        
        // === CODE LEGACY DÉSACTIVÉ ===
        if (false && isset($structuredData['document_classification']) && isset($structuredData['visa_eligibility'])) {
            // Fallback: Détecter le type avec la méthode regex (compatibilité)
            $passportTypeInfo = self::detectPassportType($structuredData);
            $structuredData['passport_type_detection'] = $passportTypeInfo;
            $structuredData['is_standard_passport'] = $passportTypeInfo['is_standard_passport'];
            $structuredData['document_subtype'] = $passportTypeInfo['document_subtype'];
            
            // Analyser la complétude des champs pour les documents non standard
            if (!$passportTypeInfo['is_standard_passport']) {
                $structuredData['field_analysis'] = self::analyzeFieldCompleteness(
                    $structuredData['fields'] ?? [],
                    $passportTypeInfo['document_subtype'] ?? 'UNKNOWN'
                );
                $structuredData['alternatives'] = self::getDocumentAlternatives(
                    $passportTypeInfo['document_subtype'] ?? 'UNKNOWN'
                );
            }
        }
        
        return $structuredData;
    }
    
    /**
     * Cross-validation MRZ ↔ VIZ (Visual Inspection Zone) - Version Expert
     * 
     * Architecture hybride ICAO 9303 avec validation checksums :
     * 1. Valide les checksums MRZ pour garantir l'intégrité des données
     * 2. Utilise VIZ pour corriger si checksum MRZ échoue (self-healing)
     * 3. Classifie le document via code MRZ (P<, PL, PD, PS)
     * 4. Enrichit avec VIZ pour les données absentes du MRZ
     * 
     * Règles de priorité :
     * - MRZ prioritaire (si checksum OK) : document_number, dates, sex, nationality
     * - VIZ prioritaire : nom, prénoms (accents), lieu de naissance, autorité
     * 
     * @param array $structuredData Données structurées par Gemini/Claude
     * @param array|null $mrzData Données MRZ parsées par Google Vision
     * @return array Données avec cross-validation appliquée
     */
    private function crossValidateMRZandVIZ(array $structuredData, ?array $mrzData): array {
        // Si pas de MRZ parsé, retourner les données telles quelles
        if ($mrzData === null || !isset($mrzData['parsed'])) {
            // Essayer les données MRZ de Gemini si disponibles
            if (!isset($structuredData['mrz_data']['parsed'])) {
                $structuredData['mrz_validation'] = [
                    'available' => false,
                    'reason' => 'No MRZ detected'
                ];
                return $structuredData;
            }
            $parsedMRZ = $structuredData['mrz_data']['parsed'];
            $checkDigits = $structuredData['mrz_data']['parsed']['check_digits'] ?? [];
        } else {
            $parsedMRZ = $mrzData['parsed'];
            $checkDigits = $mrzData['parsed']['check_digits'] ?? [];
        }
        
        // Récupérer les données VIZ (soit de viz_data, soit de fields)
        $vizData = $structuredData['viz_data'] ?? [];
        $fields = $structuredData['fields'] ?? [];
        
        // === ÉTAPE 1: Validation des Checksums MRZ ===
        $checksumValidation = $this->validateMRZChecksums($checkDigits);
        $structuredData['mrz_validation'] = $checksumValidation;
        
        // === ÉTAPE 2: Classification par code document MRZ (source primaire) ===
        $docType = $parsedMRZ['document_type'] ?? 'P<';
        $issuingState = $parsedMRZ['issuing_state'] ?? '';
        $mrzClassification = $this->classifyDocumentByMRZ($docType, $issuingState, $parsedMRZ);
        $structuredData['mrz_classification'] = $mrzClassification;
        
        // Initialiser cross_validation
        if (!isset($structuredData['cross_validation'])) {
            $structuredData['cross_validation'] = [];
        }
        
        // === ÉTAPE 3: Règles de priorité avec self-healing ===
        // MRZ prioritaire (si checksum valide): document_number, date_of_birth, date_of_expiry, sex, nationality
        // VIZ prioritaire (richesse données): surname, given_names (accents), place_of_birth, issuing_authority
        // VIZ exclusif (absent du MRZ): place_of_issue, date_of_issue, issuing_authority
        
        $priorityRules = [
            'document_number' => ['priority' => 'mrz', 'checksum_field' => 'document_number'],
            'date_of_birth' => ['priority' => 'mrz', 'checksum_field' => 'date_of_birth'],
            'date_of_expiry' => ['priority' => 'mrz', 'checksum_field' => 'date_of_expiry'],
            'sex' => ['priority' => 'mrz', 'checksum_field' => null],
            'nationality' => ['priority' => 'mrz', 'checksum_field' => null],
            'surname' => ['priority' => 'viz', 'checksum_field' => null],
            'given_names' => ['priority' => 'viz', 'checksum_field' => null],
            'place_of_birth' => ['priority' => 'viz_only', 'checksum_field' => null],
            'place_of_issue' => ['priority' => 'viz_only', 'checksum_field' => null],
            'date_of_issue' => ['priority' => 'viz_only', 'checksum_field' => null],
            'issuing_authority' => ['priority' => 'viz_only', 'checksum_field' => null]
        ];
        
        // Pour chaque champ avec règle de priorité
        foreach ($priorityRules as $fieldName => $rule) {
            $priority = $rule['priority'];
            $checksumField = $rule['checksum_field'];
            
            // Obtenir la valeur MRZ
            $mrzValue = $parsedMRZ[$fieldName] ?? null;
            
            // Obtenir la valeur VIZ (de viz_data ou fields)
            $vizValue = $vizData[$fieldName] ?? null;
            if ($vizValue === null && isset($fields[$fieldName])) {
                $vizValue = is_array($fields[$fieldName]) 
                    ? ($fields[$fieldName]['value'] ?? null) 
                    : $fields[$fieldName];
            }
            
            // Vérifier si le checksum MRZ est valide pour ce champ
            $mrzChecksumValid = true;
            if ($checksumField !== null && isset($checkDigits[$checksumField])) {
                $mrzChecksumValid = $checkDigits[$checksumField]['valid'] ?? true;
            }
            
            // Calculer la correspondance
            $match = $this->compareFieldValues($fieldName, $vizValue, $mrzValue);
            
            // === LOGIQUE SELF-HEALING ===
            $finalValue = null;
            $source = 'none';
            $confidence = 0.70;
            $healingApplied = false;
            
            if ($priority === 'mrz' && $mrzValue !== null) {
                if ($mrzChecksumValid) {
                    // Checksum OK : MRZ est fiable
                    $finalValue = $mrzValue;
                    $source = 'mrz_checksum_valid';
                    $confidence = 0.99;
                } else {
                    // Checksum KO : tenter de corriger avec VIZ (self-healing)
                    if ($vizValue !== null && $this->isPlausibleValue($fieldName, $vizValue)) {
                        $finalValue = $vizValue;
                        $source = 'viz_self_healing';
                        $confidence = 0.85;
                        $healingApplied = true;
                        $this->log("Self-healing applied for {$fieldName}: MRZ checksum failed, using VIZ value");
                    } else {
                        // Utiliser MRZ malgré le checksum (mieux que rien)
                        $finalValue = $mrzValue;
                        $source = 'mrz_checksum_failed';
                        $confidence = 0.70;
                    }
                }
            } elseif ($priority === 'viz' && $vizValue !== null) {
                // VIZ prioritaire pour ce champ (noms avec accents)
                $finalValue = $vizValue;
                $source = 'viz_priority';
                $confidence = $match ? 0.97 : 0.90;
            } elseif ($priority === 'viz_only') {
                // Champ uniquement disponible dans VIZ
                if ($vizValue !== null) {
                    $finalValue = $vizValue;
                    $source = 'viz_exclusive';
                    $confidence = 0.85;
                }
            } elseif ($mrzValue !== null) {
                $finalValue = $mrzValue;
                $source = 'mrz_fallback';
                $confidence = 0.80;
            } elseif ($vizValue !== null) {
                $finalValue = $vizValue;
                $source = 'viz_fallback';
                $confidence = 0.75;
            }
            
            // Enregistrer la cross-validation
            $structuredData['cross_validation'][$fieldName] = [
                'viz' => $vizValue,
                'mrz' => $mrzValue,
                'mrz_checksum_valid' => $mrzChecksumValid,
                'match' => $match,
                'final' => $finalValue,
                'source' => $source,
                'confidence' => $confidence,
                'self_healing' => $healingApplied
            ];
            
            // Mettre à jour le champ fields avec la valeur finale
            if ($finalValue !== null) {
                if (!isset($structuredData['fields'])) {
                    $structuredData['fields'] = [];
                }
                
                $structuredData['fields'][$fieldName] = [
                    'value' => $finalValue,
                    'confidence' => $confidence,
                    'source' => $source
                ];
            }
        }
        
        // === ÉTAPE 4: Résumé de la cross-validation ===
        $discrepancies = array_filter($structuredData['cross_validation'], function($cv) {
            return isset($cv['match']) && $cv['match'] === false && $cv['viz'] !== null && $cv['mrz'] !== null;
        });
        
        $selfHealingFields = array_filter($structuredData['cross_validation'], function($cv) {
            return isset($cv['self_healing']) && $cv['self_healing'] === true;
        });
        
        $structuredData['cross_validation']['_summary'] = [
            'total_fields' => count($priorityRules),
            'mrz_available' => $parsedMRZ !== null,
            'viz_available' => !empty($vizData) || !empty($fields),
            'mrz_checksums_valid' => $checksumValidation['all_valid'],
            'discrepancies_count' => count($discrepancies),
            'discrepancies' => array_keys($discrepancies),
            'self_healing_applied' => count($selfHealingFields) > 0,
            'self_healing_fields' => array_keys($selfHealingFields)
        ];
        
        $this->log("Cross-validation Expert: " . count($discrepancies) . " discrepancies, " . count($selfHealingFields) . " self-healing corrections");
        
        return $structuredData;
    }
    
    /**
     * Valide tous les checksums MRZ
     */
    private function validateMRZChecksums(array $checkDigits): array {
        $results = [
            'document_number' => $checkDigits['document_number']['valid'] ?? null,
            'date_of_birth' => $checkDigits['date_of_birth']['valid'] ?? null,
            'date_of_expiry' => $checkDigits['date_of_expiry']['valid'] ?? null,
            'all_valid' => true,
            'failed_fields' => []
        ];
        
        foreach (['document_number', 'date_of_birth', 'date_of_expiry'] as $field) {
            if (isset($results[$field]) && $results[$field] === false) {
                $results['all_valid'] = false;
                $results['failed_fields'][] = $field;
            }
        }
        
        return $results;
    }
    
    /**
     * Classifie le document selon le code MRZ (ICAO 9303) + indicateurs VIZ
     * 
     * Architecture hybride:
     * 1. Code MRZ (P<, PL, PD, PS) - source primaire
     * 2. Numéro de document (DD = Diplomatique, SV = Service)
     * 3. Indicateurs VIZ (texte "DIPLOMATIQUE", "SERVICE")
     */
    private function classifyDocumentByMRZ(string $docType, string $issuingState, array $parsedMRZ): array {
        // Codes pays des organisations internationales
        $internationalOrgCodes = [
            'UNO' => 'United Nations',
            'UNA' => 'United Nations Agency',
            'UNK' => 'United Nations Kosovo',
            'XXA' => 'Stateless',
            'XXB' => 'Refugee',
            'XXC' => 'Refugee (Convention)',
            'XXX' => 'Unspecified Nationality',
            'XAU' => 'African Union',
            'AUE' => 'African Union',
            'EUE' => 'European Union',
            'EUR' => 'European Union',
            'XBA' => 'African Development Bank',
            'XCC' => 'Caribbean Community',
            'XCO' => 'Common Market Eastern/Southern Africa',
            'XEC' => 'ECOWAS',
            'XPO' => 'Interpol',
            'XOM' => 'Sovereign Military Order of Malta',
            'NATO' => 'NATO',
            'NTO' => 'NATO'
        ];
        
        // Déterminer la catégorie principale
        $category = 'PASSPORT';
        $subcategory = 'ORDINARY';
        $isInternationalOrg = false;
        $organization = null;
        $detectedIndicators = [];
        
        // === ÉTAPE 1: Classification par type de document MRZ (ICAO) ===
        if ($docType === 'PL' || str_starts_with($docType, 'V')) {
            $category = 'LAISSEZ_PASSER';
            $subcategory = 'INTERNATIONAL_ORGANIZATION';
            $detectedIndicators[] = 'mrz_type_' . $docType;
        } elseif ($docType === 'PD') {
            $subcategory = 'DIPLOMATIC';
            $detectedIndicators[] = 'mrz_type_diplomatic';
        } elseif ($docType === 'PS') {
            $subcategory = 'SERVICE';
            $detectedIndicators[] = 'mrz_type_service';
        } elseif ($docType === 'PO') {
            $subcategory = 'OFFICIAL';
            $detectedIndicators[] = 'mrz_type_official';
        }
        
        // === ÉTAPE 2: Détection par numéro de document (patterns pays) ===
        $documentNumber = $parsedMRZ['document_number'] ?? '';
        if (!empty($documentNumber)) {
            // Patterns courants pour passeports diplomatiques/service
            // Côte d'Ivoire: DD = Diplomatique, SV = Service
            // Autres pays: D = Diplomatique, S = Service (en préfixe)
            if (preg_match('/^(\d{2})DD/', $documentNumber) || preg_match('/^D[A-Z]?\d/', $documentNumber)) {
                if ($subcategory === 'ORDINARY') {
                    $subcategory = 'DIPLOMATIC';
                    $detectedIndicators[] = 'docnum_pattern_diplomatic';
                }
            } elseif (preg_match('/^(\d{2})SV/', $documentNumber) || preg_match('/^S[A-Z]?\d/', $documentNumber)) {
                if ($subcategory === 'ORDINARY') {
                    $subcategory = 'SERVICE';
                    $detectedIndicators[] = 'docnum_pattern_service';
                }
            }
        }
        
        // === ÉTAPE 3: Vérifier si émetteur = organisation internationale ===
        if (isset($internationalOrgCodes[$issuingState])) {
            $isInternationalOrg = true;
            $organization = $internationalOrgCodes[$issuingState];
            if ($category === 'PASSPORT') {
                $category = 'LAISSEZ_PASSER';
                $subcategory = 'INTERNATIONAL_ORGANIZATION';
                $detectedIndicators[] = 'issuer_international_org';
            }
        }
        
        // Vérifier aussi la nationalité
        $nationality = $parsedMRZ['nationality'] ?? '';
        $hasStateNationality = !empty($nationality) && !isset($internationalOrgCodes[$nationality]);
        
        // Déterminer l'éligibilité et le workflow
        $isValidForVisa = ($category === 'PASSPORT');
        $workflow = null;
        if ($isValidForVisa) {
            $workflow = ($subcategory === 'DIPLOMATIC' || $subcategory === 'SERVICE') ? 'PRIORITY' : 'STANDARD';
        }
        
        return [
            'category' => $category,
            'subcategory' => $subcategory,
            'document_type_code' => $docType,
            'issuing_state_code' => $issuingState,
            'is_international_organization' => $isInternationalOrg,
            'organization_name' => $organization,
            'has_state_nationality' => $hasStateNationality,
            'detected_nationality' => $nationality,
            'is_valid_for_evisa' => $isValidForVisa,
            'workflow' => $workflow,
            'detected_indicators' => $detectedIndicators,
            'source' => 'mrz_icao_classification'
        ];
    }
    
    /**
     * Vérifie si une valeur est plausible pour un champ donné
     */
    private function isPlausibleValue(string $fieldName, $value): bool {
        if ($value === null || $value === '') {
            return false;
        }
        
        switch ($fieldName) {
            case 'document_number':
                // Numéro: 6-12 caractères alphanumériques
                return preg_match('/^[A-Z0-9]{6,12}$/i', $value);
                
            case 'date_of_birth':
            case 'date_of_expiry':
            case 'date_of_issue':
                // Date: format ISO ou DD/MM/YYYY
                return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || 
                       preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value);
                
            case 'nationality':
                // Code pays: 3 lettres
                return preg_match('/^[A-Z]{3}$/i', $value);
                
            case 'sex':
                // Sexe: M, F ou <
                return in_array(strtoupper($value), ['M', 'F', '<']);
                
            default:
                // Autres champs: au moins 1 caractère
                return strlen(trim($value)) >= 1;
        }
    }
    
    /**
     * Compare deux valeurs de champ (VIZ vs MRZ) avec tolérance
     * 
     * @param string $fieldName Nom du champ
     * @param mixed $vizValue Valeur visuelle
     * @param mixed $mrzValue Valeur MRZ
     * @return bool True si les valeurs correspondent
     */
    private function compareFieldValues(string $fieldName, $vizValue, $mrzValue): bool {
        if ($vizValue === null || $mrzValue === null) {
            return true; // Pas de comparaison possible
        }
        
        // Normaliser les valeurs pour comparaison
        $vizNorm = $this->normalizeForComparison($vizValue);
        $mrzNorm = $this->normalizeForComparison($mrzValue);
        
        // Comparaison exacte
        if ($vizNorm === $mrzNorm) {
            return true;
        }
        
        // Comparaisons spécifiques par type de champ
        switch ($fieldName) {
            case 'surname':
            case 'given_names':
                // Tolérance pour les accents (TRAORÉ vs TRAORE)
                $vizNoAccent = $this->removeAccents($vizNorm);
                $mrzNoAccent = $this->removeAccents($mrzNorm);
                return $vizNoAccent === $mrzNoAccent;
                
            case 'date_of_birth':
            case 'date_of_expiry':
            case 'date_of_issue':
                // Comparaison de dates (différents formats possibles)
                $vizDate = $this->parseDate($vizValue);
                $mrzDate = $this->parseDate($mrzValue);
                return $vizDate === $mrzDate;
                
            case 'sex':
                // M/F ou MALE/FEMALE
                $vizSex = strtoupper(substr($vizValue, 0, 1));
                $mrzSex = strtoupper(substr($mrzValue, 0, 1));
                return $vizSex === $mrzSex;
                
            case 'document_number':
                // Tolérance pour les espaces et tirets
                $vizClean = preg_replace('/[\s\-]/', '', strtoupper($vizValue));
                $mrzClean = preg_replace('/[\s\-]/', '', strtoupper($mrzValue));
                return $vizClean === $mrzClean;
                
            default:
                // Comparaison standard sans casse
                return strtoupper(trim($vizValue)) === strtoupper(trim($mrzValue));
        }
    }
    
    /**
     * Normalise une valeur pour comparaison
     */
    private function normalizeForComparison($value): string {
        if (!is_string($value)) {
            $value = (string)$value;
        }
        return strtoupper(trim(preg_replace('/\s+/', ' ', $value)));
    }
    
    /**
     * Supprime les accents d'une chaîne
     */
    private function removeAccents(string $string): string {
        $accents = [
            'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A',
            'Ç'=>'C',
            'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E',
            'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I',
            'Ñ'=>'N',
            'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O',
            'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U',
            'Ý'=>'Y', 'Ÿ'=>'Y',
            'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a',
            'ç'=>'c',
            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e',
            'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i',
            'ñ'=>'n',
            'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o',
            'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ü'=>'u',
            'ý'=>'y', 'ÿ'=>'y'
        ];
        return strtr($string, $accents);
    }
    
    /**
     * Parse une date en format normalisé (YYYY-MM-DD)
     */
    private function parseDate($value): ?string {
        if (empty($value)) {
            return null;
        }
        
        // Format déjà normalisé
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        
        // Format JJ/MM/AAAA ou JJ.MM.AAAA
        if (preg_match('/^(\d{1,2})[\/\.\-](\d{1,2})[\/\.\-](\d{2,4})$/', $value, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];
            if (strlen($year) === 2) {
                $year = ($year > 50) ? '19' . $year : '20' . $year;
            }
            return "{$year}-{$month}-{$day}";
        }
        
        // Format YYMMDD (MRZ)
        if (preg_match('/^(\d{2})(\d{2})(\d{2})$/', $value, $matches)) {
            $year = ($matches[1] > 50) ? '19' . $matches[1] : '20' . $matches[1];
            return "{$year}-{$matches[2]}-{$matches[3]}";
        }
        
        // Essayer strtotime en dernier recours
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        
        return null;
    }
    
    /**
     * Mappe la classification IA vers le type de passeport interne
     * 
     * La classification est faite par l'IA (Gemini/Claude) qui identifie
     * dynamiquement le type de document et l'organisation émettrice.
     */
    private function mapAIClassificationToType(array $classification): string {
        $category = strtoupper($classification['category'] ?? 'PASSPORT');
        $subcategory = strtoupper($classification['subcategory'] ?? 'ORDINARY');
        
        if ($category === 'PASSPORT') {
            switch ($subcategory) {
                case 'DIPLOMATIC':
                    return 'DIPLOMATIQUE';
                case 'SERVICE':
                    return 'SERVICE';
                case 'OFFICIAL':
                    return 'OFFICIEL';
                default:
                    return 'ORDINAIRE';
            }
        } elseif ($category === 'LAISSEZ_PASSER') {
            // L'IA identifie l'organisation - on retourne un type générique
            // L'organisation spécifique est disponible dans issuing_organization_code
            return 'LAISSEZ_PASSER';
        } elseif ($category === 'TRAVEL_DOCUMENT') {
            switch ($subcategory) {
                case 'REFUGEE':
                    return 'TRAVEL_REFUGEE';
                case 'STATELESS':
                    return 'TRAVEL_STATELESS';
                default:
                    return 'EMERGENCY';
            }
        }
        
        return 'ORDINAIRE';
    }
    
    /**
     * Extraction billet d'avion (Pipeline Triple Layer)
     * @param string|null $preExtractedText Texte OCR pré-extrait (pour PDFs multi-pages)
     */
    private function extractFlightTicket(string $content, string $mimeType, ?string $preExtractedText = null): array {
        $layer1Start = microtime(true);

        // === LAYER 1: Google Vision OCR ===
        if (!empty($preExtractedText)) {
            $text = $preExtractedText;
            $ocrConfidence = 0.9;
            $this->addWorkflowTrace('layer1_ocr', 'Using pre-extracted multi-page OCR text', [
                'chars_extracted' => strlen($text),
                'confidence' => $ocrConfidence
            ]);
        } else {
            $ocrResult = $this->googleVision->extractText($content, $mimeType);
            $text = $ocrResult['full_text'];
            $ocrConfidence = $ocrResult['confidence'];
            $this->addWorkflowTrace('layer1_ocr', 'Google Vision OCR completed', [
                'chars_extracted' => strlen($text),
                'confidence' => $ocrConfidence
            ]);
        }

        if (empty($text)) {
            throw new Exception('Impossible de lire le texte du billet');
        }

        $layer2Start = microtime(true);
        $layer2Source = 'none';
        $data = null;

        // === LAYER 2: Structuration intelligente ===
        if ($this->isGeminiAvailable()) {
            try {
                $data = $this->gemini->structureDocument($text, 'ticket');
                $layer2Source = 'gemini_flash';
                $this->addWorkflowTrace('layer2_gemini', 'Gemini structuration completed', [
                    'processing_time' => round(microtime(true) - $layer2Start, 3)
                ]);
            } catch (Exception $e) {
                $this->log("Gemini ticket structuration failed: " . $e->getMessage());
            }
        }

        // Fallback sur Claude
        if ($data === null) {
            $prompt = FlightTicketPrompt::build($text);
            $response = $this->claudeComplete($prompt);
            $data = $this->parseClaudeJson($response);
            $layer2Source = 'claude_fallback';
            $this->addWorkflowTrace('layer2_claude', 'Claude fallback completed');
        }

        // Normaliser les champs
        $data['_metadata'] = [
            'ocr_confidence' => $ocrConfidence,
            'extraction_method' => 'triple_layer',
            'layer2_source' => $layer2Source
        ];

        return $data;
    }
    
    /**
     * Extraction réservation hôtel (Pipeline Triple Layer)
     * @param string|null $preExtractedText Texte OCR pré-extrait (pour PDFs multi-pages)
     */
    private function extractHotelReservation(string $content, string $mimeType, ?string $preExtractedText = null): array {
        $layer1Start = microtime(true);

        // === LAYER 1: Google Vision OCR ===
        // Utiliser le texte pré-extrait si disponible (multi-pages PDF)
        if (!empty($preExtractedText)) {
            $text = $preExtractedText;
            $ocrConfidence = 0.9; // Estimation car multi-pages
            $this->addWorkflowTrace('layer1_ocr', 'Using pre-extracted multi-page OCR text', [
                'chars_extracted' => strlen($text),
                'confidence' => $ocrConfidence
            ]);
        } else {
            $ocrResult = $this->googleVision->extractText($content, $mimeType);
            $text = $ocrResult['full_text'];
            $ocrConfidence = $ocrResult['confidence'];
            $this->addWorkflowTrace('layer1_ocr', 'Google Vision OCR completed', [
                'chars_extracted' => strlen($text),
                'confidence' => $ocrConfidence
            ]);
        }

        if (empty($text)) {
            throw new Exception('Impossible de lire la réservation d\'hôtel');
        }
        
        $layer2Start = microtime(true);
        $layer2Source = 'none';
        $data = null;
        
        // === LAYER 2: Structuration intelligente ===
        if ($this->isGeminiAvailable()) {
            try {
                $data = $this->gemini->structureDocument($text, 'hotel');
                $layer2Source = 'gemini_flash';
                $this->addWorkflowTrace('layer2_gemini', 'Gemini structuration completed');
            } catch (Exception $e) {
                $this->log("Gemini hotel structuration failed: " . $e->getMessage());
            }
        }
        
        // Fallback sur Claude
        if ($data === null) {
            $prompt = HotelReservationPrompt::build($text);
            $response = $this->claudeComplete($prompt);
            $data = $this->parseClaudeJson($response);
            $layer2Source = 'claude_fallback';
            $this->addWorkflowTrace('layer2_claude', 'Claude fallback completed');
        }
        
        $data['_metadata'] = [
            'ocr_confidence' => $ocrConfidence,
            'extraction_method' => 'triple_layer',
            'layer2_source' => $layer2Source
        ];

        return $data;
    }

    /**
     * Extraction carnet vaccinal (Pipeline Triple Layer)
     * @param string|null $preExtractedText Texte OCR pré-extrait (pour PDFs multi-pages)
     */
    private function extractVaccinationCard(string $content, string $mimeType, ?string $preExtractedText = null): array {
        $layer1Start = microtime(true);

        // === LAYER 1: Google Vision OCR ===
        // Utiliser le texte pré-extrait si disponible (multi-pages PDF)
        if (!empty($preExtractedText)) {
            $text = $preExtractedText;
            $ocrConfidence = 0.9;
            $this->addWorkflowTrace('layer1_ocr', 'Using pre-extracted multi-page OCR text', [
                'chars_extracted' => strlen($text),
                'confidence' => $ocrConfidence
            ]);
        } else {
            $ocrResult = $this->googleVision->extractText($content, $mimeType);
            $text = $ocrResult['full_text'];
            $ocrConfidence = $ocrResult['confidence'];
            $this->addWorkflowTrace('layer1_ocr', 'Google Vision OCR completed', [
                'chars_extracted' => strlen($text),
                'confidence' => $ocrConfidence
            ]);
        }

        if (empty($text)) {
            throw new Exception('Impossible de lire le carnet vaccinal');
        }
        
        $layer2Source = 'none';
        $data = null;
        
        // === LAYER 2: Structuration intelligente ===
        if ($this->isGeminiAvailable()) {
            try {
                $data = $this->gemini->structureDocument($text, 'vaccination');
                $layer2Source = 'gemini_flash';
                $this->addWorkflowTrace('layer2_gemini', 'Gemini structuration completed');
            } catch (Exception $e) {
                $this->log("Gemini vaccination structuration failed: " . $e->getMessage());
            }
        }
        
        // Fallback sur Claude
        if ($data === null) {
            $prompt = VaccinationCardPrompt::build($text);
            $response = $this->claudeComplete($prompt);
            $data = $this->parseClaudeJson($response);
            $layer2Source = 'claude_fallback';
            $this->addWorkflowTrace('layer2_claude', 'Claude fallback completed');
        }
        
        $data['_metadata'] = [
            'ocr_confidence' => $ocrConfidence,
            'extraction_method' => 'triple_layer',
            'layer2_source' => $layer2Source
        ];

        return $data;
    }

    /**
     * Extraction lettre d'invitation (Pipeline Triple Layer)
     * @param string|null $preExtractedText Texte OCR pré-extrait (pour PDFs multi-pages)
     */
    private function extractInvitationLetter(string $content, string $mimeType, ?string $preExtractedText = null): array {
        $layer1Start = microtime(true);

        // === LAYER 1: Google Vision OCR ===
        if (!empty($preExtractedText)) {
            $text = $preExtractedText;
            $ocrConfidence = 0.9;
            $this->addWorkflowTrace('layer1_ocr', 'Using pre-extracted multi-page OCR text', [
                'chars_extracted' => strlen($text),
                'confidence' => $ocrConfidence
            ]);
        } else {
            $ocrResult = $this->googleVision->extractText($content, $mimeType);
            $text = $ocrResult['full_text'];
            $ocrConfidence = $ocrResult['confidence'];
            $this->addWorkflowTrace('layer1_ocr', 'Google Vision OCR completed', [
                'chars_extracted' => strlen($text),
                'confidence' => $ocrConfidence
            ]);
        }

        if (empty($text)) {
            throw new Exception('Impossible de lire la lettre d\'invitation');
        }
        
        $layer2Source = 'none';
        $data = null;
        
        // === LAYER 2: Structuration intelligente ===
        if ($this->isGeminiAvailable()) {
            try {
                $data = $this->gemini->structureDocument($text, 'invitation');
                $layer2Source = 'gemini_flash';
                $this->addWorkflowTrace('layer2_gemini', 'Gemini structuration completed');
            } catch (Exception $e) {
                $this->log("Gemini invitation structuration failed: " . $e->getMessage());
            }
        }
        
        // Fallback sur Claude
        if ($data === null) {
            $prompt = InvitationLetterPrompt::build($text);
            $response = $this->claudeComplete($prompt);
            $data = $this->parseClaudeJson($response);
            $layer2Source = 'claude_fallback';
            $this->addWorkflowTrace('layer2_claude', 'Claude fallback completed');
        }
        
        $data['_metadata'] = [
            'ocr_confidence' => $ocrConfidence,
            'extraction_method' => 'triple_layer',
            'layer2_source' => $layer2Source
        ];

        return $data;
    }

    /**
     * Extraction note verbale (Pipeline Triple Layer)
     */
    private function extractVerbalNote(string $content, string $mimeType): array {
        $layer1Start = microtime(true);
        
        // === LAYER 1: Google Vision OCR ===
        $ocrResult = $this->googleVision->extractText($content, $mimeType);
        $text = $ocrResult['full_text'];
        
        if (empty($text)) {
            throw new Exception('Impossible de lire la note verbale');
        }
        
        $this->addWorkflowTrace('layer1_ocr', 'Google Vision OCR completed', [
            'chars_extracted' => strlen($text),
            'confidence' => $ocrResult['confidence']
        ]);
        
        $layer2Source = 'none';
        $data = null;
        
        // === LAYER 2: Structuration intelligente ===
        if ($this->isGeminiAvailable()) {
            try {
                $data = $this->gemini->structureDocument($text, 'verbal_note');
                $layer2Source = 'gemini_flash';
                $this->addWorkflowTrace('layer2_gemini', 'Gemini structuration completed');
            } catch (Exception $e) {
                $this->log("Gemini verbal_note structuration failed: " . $e->getMessage());
            }
        }
        
        // Fallback sur Claude
        if ($data === null) {
            $prompt = <<<PROMPT
Analyse cette note verbale diplomatique et extrait les informations:

TEXTE:
{$text}

Retourne UNIQUEMENT un JSON avec cette structure:
{
  "sender": {
    "ministry": "Ministère émetteur",
    "country": "Pays",
    "reference": "Numéro de référence"
  },
  "recipient": {
    "embassy": "Ambassade destinataire",
    "country": "Pays"
  },
  "subject": "Objet de la note",
  "date": "DD/MM/YYYY",
  "persons_mentioned": [
    {
      "name": "NOM COMPLET",
      "title": "Titre/Fonction",
      "passport_number": "Si mentionné"
    }
  ],
  "purpose": "But du voyage",
  "confidence": 0.85
}
PROMPT;
            
            $response = $this->claudeComplete($prompt);
            $data = $this->parseClaudeJson($response);
            $layer2Source = 'claude_fallback';
            $this->addWorkflowTrace('layer2_claude', 'Claude fallback completed');
        }
        
        $data['_metadata'] = [
            'ocr_confidence' => $ocrResult['confidence'],
            'extraction_method' => 'triple_layer',
            'layer2_source' => $layer2Source
        ];
        
        return $data;
    }
    
    /**
     * Extraction carte de séjour/résidence (Pipeline Triple Layer)
     * Utilisée pour les non-nationaux résidant dans un pays de la zone
     */
    private function extractResidenceCard(string $content, string $mimeType): array {
        $layer1Start = microtime(true);
        
        // === LAYER 1: Google Vision OCR ===
        $ocrResult = $this->googleVision->extractText($content, $mimeType);
        $text = $ocrResult['full_text'];
        
        if (empty($text)) {
            throw new Exception('Impossible de lire la carte de séjour');
        }
        
        $this->addWorkflowTrace('layer1_ocr', 'Google Vision OCR completed', [
            'chars_extracted' => strlen($text),
            'confidence' => $ocrResult['confidence']
        ]);
        
        $layer2Source = 'none';
        $data = null;
        
        // === LAYER 2: Structuration intelligente ===
        if ($this->isGeminiAvailable()) {
            try {
                $data = $this->gemini->structureDocument($text, 'residence_card');
                $layer2Source = 'gemini_flash';
                $this->addWorkflowTrace('layer2_gemini', 'Gemini structuration completed');
            } catch (Exception $e) {
                $this->log("Gemini residence_card structuration failed: " . $e->getMessage());
            }
        }
        
        // Fallback sur Claude
        if ($data === null) {
            $prompt = <<<PROMPT
Analyse cette carte de séjour/permis de résidence et extrait les informations:

TEXTE:
{$text}

Retourne UNIQUEMENT un JSON avec cette structure:
{
  "holder": {
    "surname": "NOM",
    "given_names": "PRÉNOMS",
    "date_of_birth": "YYYY-MM-DD",
    "sex": "M ou F",
    "nationality": "NATIONALITÉ D'ORIGINE"
  },
  "document": {
    "card_number": "NUMÉRO",
    "type": "TYPE DE DOCUMENT",
    "category": "CATÉGORIE",
    "issuing_country": "PAYS",
    "date_of_issue": "YYYY-MM-DD",
    "date_of_expiry": "YYYY-MM-DD",
    "valid": true
  },
  "residence": {
    "address": "ADRESSE",
    "city": "VILLE",
    "country": "PAYS"
  },
  "confidence": 0.85
}
PROMPT;
            
            $response = $this->claudeComplete($prompt);
            $data = $this->parseClaudeJson($response);
            $layer2Source = 'claude_fallback';
            $this->addWorkflowTrace('layer2_claude', 'Claude fallback completed');
        }
        
        // Validation de la date d'expiration
        if (isset($data['document']['date_of_expiry'])) {
            $expiryDate = strtotime($data['document']['date_of_expiry']);
            $data['document']['valid'] = $expiryDate > time();
            $data['document']['days_until_expiry'] = $expiryDate ? floor(($expiryDate - time()) / 86400) : null;
        }
        
        $data['_metadata'] = [
            'ocr_confidence' => $ocrResult['confidence'],
            'extraction_method' => 'triple_layer',
            'layer2_source' => $layer2Source
        ];
        
        return $data;
    }
    
    /**
     * Layer 3: Validation Claude (Superviseur)
     * Vérifie la cohérence et détecte les anomalies
     */
    private function requestClaudeValidation(string $documentType, array $extractedData): array {
        $dataJson = json_encode($extractedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $prompt = <<<PROMPT
Tu es un superviseur qualité pour les demandes de visa Côte d'Ivoire.
Analyse ces données extraites d'un document de type "{$documentType}" et détecte les anomalies.

DONNÉES EXTRAITES:
{$dataJson}

Vérifie:
1. Cohérence des données (dates valides, formats corrects)
2. Champs manquants critiques
3. Anomalies suspectes (dates futures, incohérences)

Retourne UNIQUEMENT un JSON:
{
  "valid": true,
  "confidence_score": 0.95,
  "warnings": [],
  "anomalies": [],
  "missing_fields": [],
  "recommendation": "approved|manual_review|rejected"
}
PROMPT;
        
        try {
            $response = $this->claudeComplete($prompt);
            $validation = $this->parseClaudeJson($response);
            $validation['timestamp'] = date('c');
            $validation['validated_by'] = 'claude_layer3';
            return $validation;
        } catch (Exception $e) {
            $this->log("Layer 3 validation failed: " . $e->getMessage());
            return [
                'valid' => true,
                'confidence_score' => 0.5,
                'warnings' => ['Validation Claude indisponible'],
                'validated_by' => 'fallback',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Appelle Claude pour complétion texte
     */
    private function claudeComplete(string $prompt): string {
        // Utiliser la méthode existante de ClaudeClient pour texte
        $apiKey = defined('CLAUDE_API_KEY') ? CLAUDE_API_KEY : getenv('CLAUDE_API_KEY');
        $model = defined('CLAUDE_MODEL') ? CLAUDE_MODEL : 'claude-3-5-haiku-20241022';
        
        $payload = [
            'model' => $model,
            'max_tokens' => 2048,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ]
        ];
        
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => $this->config['timeout']
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($httpCode !== 200) {
            throw new Exception('Erreur Claude API: HTTP ' . $httpCode);
        }
        
        $data = json_decode($response, true);
        return $data['content'][0]['text'] ?? '';
    }
    
    /**
     * Parse le JSON de la réponse Claude
     */
    private function parseClaudeJson(string $response): array {
        // Chercher le JSON dans la réponse
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $response, $matches)) {
            $jsonString = trim($matches[1]);
        } elseif (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $jsonString = $matches[0];
        } else {
            throw new Exception('JSON non trouvé dans la réponse');
        }
        
        $data = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON invalide: ' . json_last_error_msg());
        }
        
        return $data;
    }
    
    /**
     * Retourne les types de documents supportés
     */
    public static function getSupportedTypes(): array {
        return self::DOCUMENT_TYPES;
    }
    
    /**
     * Vérifie si un type de document est supporté
     */
    public static function isTypeSupported(string $type): bool {
        return isset(self::DOCUMENT_TYPES[$type]);
    }
    
    /**
     * Retourne les documents requis selon le type de passeport
     * Utilise la matrice PASSPORT_REQUIREMENTS_MATRIX
     * 
     * @param string $passportType Type de passeport (ORDINAIRE, DIPLOMATIQUE, etc.)
     * @param bool $isResidentOutsideNationality Si le demandeur réside hors de son pays de nationalité
     * @return array Configuration complète des documents requis
     */
    public static function getRequiredDocuments(string $passportType = 'ORDINAIRE', bool $isResidentOutsideNationality = false): array {
        // Normaliser le type de passeport
        $passportType = strtoupper(trim($passportType));
        
        // Mapper les variantes vers les types standards
        $typeMapping = [
            'ORDINARY' => 'ORDINAIRE',
            'DIPLOMATIC' => 'DIPLOMATIQUE',
            'OFFICIAL' => 'OFFICIEL',
            'SERVICE' => 'SERVICE',
            'LP ONU' => 'LP_ONU',
            'LP UA' => 'LP_UA',
            'LAISSEZ-PASSER ONU' => 'LP_ONU',
            'LAISSEZ-PASSER UA' => 'LP_UA',
            'UN LAISSEZ-PASSER' => 'LP_ONU',
            'AU LAISSEZ-PASSER' => 'LP_UA',
            'TRAVEL DOCUMENT' => 'EMERGENCY',
            'TITRE DE VOYAGE' => 'EMERGENCY'
        ];
        
        $passportType = $typeMapping[$passportType] ?? $passportType;
        
        // Fallback si type non trouvé
        if (!isset(self::PASSPORT_REQUIREMENTS_MATRIX[$passportType])) {
            $passportType = 'ORDINAIRE';
        }
        
        $requirements = self::PASSPORT_REQUIREMENTS_MATRIX[$passportType];
        
        // Ajouter la carte de séjour si résident hors nationalité
        if ($isResidentOutsideNationality && !in_array('residence_card', $requirements['required'])) {
            $requirements['required'][] = 'residence_card';
        }
        
        return $requirements;
    }
    
    /**
     * Détecte le type de passeport depuis les données extraites
     * 
     * @param array $passportData Données extraites du passeport
     * @return array Type détecté avec confiance
     */
    public static function detectPassportType(array $passportData): array {
        $type = 'ORDINAIRE';
        $confidence = 0.5;
        $indicators = [];
        
        // Chercher le type dans les champs extraits (passport_type et nationality)
        $possibleTypeFields = [
            $passportData['fields']['passport_type']['value'] ?? null,
            $passportData['passport_type'] ?? null,
            $passportData['type'] ?? null
        ];
        
        $nationalityField = $passportData['fields']['nationality']['value'] ?? 
                           $passportData['nationality'] ?? null;
        
        // Vérifier d'abord la nationalité pour les organisations internationales
        if ($nationalityField) {
            $nationalityUpper = strtoupper($nationalityField);
            // UNO = United Nations Organization
            if (preg_match('/\bUNO\b|UNITED NATIONS|ONU|NATIONS UNIES/i', $nationalityUpper)) {
                $type = 'LP_ONU';
                $confidence = 0.98;
                $indicators[] = 'nationality_uno';
            }
            // AU = African Union
            elseif (preg_match('/\bAU\b|AFRICAN UNION|UNION AFRICAINE|UA/i', $nationalityUpper)) {
                $type = 'LP_UA';
                $confidence = 0.98;
                $indicators[] = 'nationality_au';
            }
        }
        
        foreach ($possibleTypeFields as $value) {
            if (!$value) continue;
            
            $value = strtoupper($value);
            
            // Détection diplomatique
            if (preg_match('/DIPLOM|DIPLOMAT/i', $value)) {
                $type = 'DIPLOMATIQUE';
                $confidence = 0.95;
                $indicators[] = 'keyword_diplomatic';
            }
            // Détection service
            elseif (preg_match('/SERVICE/i', $value)) {
                $type = 'SERVICE';
                $confidence = 0.95;
                $indicators[] = 'keyword_service';
            }
            // Détection officiel
            elseif (preg_match('/OFFICIEL|OFFICIAL/i', $value)) {
                $type = 'OFFICIEL';
                $confidence = 0.90;
                $indicators[] = 'keyword_official';
            }
            // Laissez-passer UA (doit être testé AVANT ONU car "AU" est plus court)
            elseif (preg_match('/\bUA\b|\bAU\b|AFRICAN UNION|UNION AFRICAINE|AULP/i', $value)) {
                $type = 'LP_UA';
                $confidence = 0.95;
                $indicators[] = 'keyword_au';
            }
            // Laissez-passer ONU (UNLP = United Nations Laissez-Passer)
            // PL = Laissez-Passer (type de document)
            elseif (preg_match('/UNLP|\bONU\b|\bUN\b|UNITED NATIONS|NATIONS UNIES|\bUNO\b|\bPL\b|LAISSEZ.PASSER/i', $value)) {
                $type = 'LP_ONU';
                $confidence = 0.95;
                $indicators[] = 'keyword_un';
            }
            // Titre de voyage d'urgence
            elseif (preg_match('/EMERGENCY|URGENCE|TRAVEL DOC/i', $value)) {
                $type = 'EMERGENCY';
                $confidence = 0.85;
                $indicators[] = 'keyword_emergency';
            }
            // Ordinaire par défaut
            elseif (preg_match('/ORDINAIRE|ORDINARY|REGULAR|STANDARD/i', $value)) {
                $type = 'ORDINAIRE';
                $confidence = 0.95;
                $indicators[] = 'keyword_ordinary';
            }
        }
        
        // Vérifier aussi le code MRZ si disponible (Position 1, caractère 1)
        if (isset($passportData['mrz']['line1'])) {
            $mrzType = substr($passportData['mrz']['line1'], 0, 2);
            if ($mrzType === 'PD') {
                $type = 'DIPLOMATIQUE';
                $confidence = max($confidence, 0.98);
                $indicators[] = 'mrz_pd';
            } elseif ($mrzType === 'PS') {
                $type = 'SERVICE';
                $confidence = max($confidence, 0.98);
                $indicators[] = 'mrz_ps';
            } elseif ($mrzType === 'PO') {
                $type = 'OFFICIEL';
                $confidence = max($confidence, 0.98);
                $indicators[] = 'mrz_po';
            }
        }
        
        // Déterminer si le document est accepté pour une demande de visa
        // Les Laissez-Passer et titres de voyage d'urgence ne sont PAS acceptés
        $invalidTypes = ['LP_ONU', 'LP_UA', 'EMERGENCY'];
        $isStandardPassport = !in_array($type, $invalidTypes);
        
        // Catégoriser le document
        $documentSubtype = 'PASSPORT'; // Par défaut
        if (in_array($type, ['LP_ONU', 'LP_UA'])) {
            $documentSubtype = 'LAISSEZ_PASSER';
        } elseif ($type === 'EMERGENCY') {
            $documentSubtype = 'TRAVEL_DOCUMENT';
        }
        
        return [
            'type' => $type,
            'confidence' => $confidence,
            'indicators' => $indicators,
            'requirements' => self::getRequiredDocuments($type),
            'workflow' => self::PASSPORT_REQUIREMENTS_MATRIX[$type]['workflow'] ?? 'STANDARD',
            'is_standard_passport' => $isStandardPassport,
            'document_subtype' => $documentSubtype
        ];
    }
    
    /**
     * Vérifie si tous les documents requis sont fournis
     * 
     * @param string $passportType Type de passeport
     * @param array $uploadedDocuments Documents déjà uploadés
     * @param bool $isResidentOutsideNationality Résident hors nationalité
     * @return array Statut de complétude et documents manquants
     */
    public static function checkDocumentCompleteness(
        string $passportType, 
        array $uploadedDocuments, 
        bool $isResidentOutsideNationality = false
    ): array {
        $requirements = self::getRequiredDocuments($passportType, $isResidentOutsideNationality);
        
        $missing = [];
        $provided = [];
        $conditionalMet = false;
        
        // Vérifier les documents obligatoires
        foreach ($requirements['required'] as $docType) {
            if (isset($uploadedDocuments[$docType]) && $uploadedDocuments[$docType]['success'] ?? false) {
                $provided[] = $docType;
            } else {
                $missing[] = $docType;
            }
        }
        
        // Vérifier les documents conditionnels (ex: hotel OU invitation)
        if (!empty($requirements['conditional'])) {
            foreach ($requirements['conditional'] as $docType) {
                if (isset($uploadedDocuments[$docType]) && $uploadedDocuments[$docType]['success'] ?? false) {
                    $conditionalMet = true;
                    $provided[] = $docType;
                    break;
                }
            }
            if (!$conditionalMet) {
                $missing[] = 'hotel_or_invitation'; // Indication qu'un des deux est requis
            }
        }
        
        $complete = empty($missing);
        
        return [
            'complete' => $complete,
            'provided' => $provided,
            'missing' => $missing,
            'conditional_satisfied' => $conditionalMet || empty($requirements['conditional']),
            'requirements' => $requirements,
            'message' => $complete 
                ? 'Tous les documents requis ont été fournis' 
                : 'Documents manquants: ' . implode(', ', array_map(function($doc) {
                    return self::DOCUMENT_TYPES[$doc]['nameFr'] ?? $doc;
                }, $missing))
        ];
    }
    
    /**
     * Analyse la complétude des champs extraits par rapport aux exigences visa
     * Identifie les champs présents, manquants et incomplets
     * 
     * @param array $fields Champs extraits du document
     * @param string $documentCategory Catégorie du document (PASSPORT, LAISSEZ_PASSER, etc.)
     * @return array Analyse détaillée des champs
     */
    public static function analyzeFieldCompleteness(array $fields, string $documentCategory = 'PASSPORT'): array {
        // Champs requis pour une demande de visa avec leurs labels bilingues
        $requiredFields = [
            'nationality' => [
                'label_fr' => 'Nationalité du titulaire',
                'label_en' => 'Holder\'s nationality',
                'critical' => true,
                'explanation_fr' => 'Nécessaire pour déterminer les conditions de visa applicables',
                'explanation_en' => 'Required to determine applicable visa conditions'
            ],
            'place_of_birth' => [
                'label_fr' => 'Lieu de naissance',
                'label_en' => 'Place of birth',
                'critical' => false,
                'explanation_fr' => 'Information de vérification d\'identité',
                'explanation_en' => 'Identity verification information'
            ],
            'date_of_birth' => [
                'label_fr' => 'Date de naissance',
                'label_en' => 'Date of birth',
                'critical' => true,
                'explanation_fr' => 'Requis pour la vérification d\'identité',
                'explanation_en' => 'Required for identity verification'
            ],
            'surname' => [
                'label_fr' => 'Nom de famille',
                'label_en' => 'Surname',
                'critical' => true,
                'explanation_fr' => 'Identification du demandeur',
                'explanation_en' => 'Applicant identification'
            ],
            'given_names' => [
                'label_fr' => 'Prénoms',
                'label_en' => 'Given names',
                'critical' => true,
                'explanation_fr' => 'Identification complète du demandeur',
                'explanation_en' => 'Complete applicant identification'
            ],
            'document_number' => [
                'label_fr' => 'Numéro du document',
                'label_en' => 'Document number',
                'critical' => true,
                'explanation_fr' => 'Référence unique du document de voyage',
                'explanation_en' => 'Unique travel document reference'
            ],
            'date_of_expiry' => [
                'label_fr' => 'Date d\'expiration',
                'label_en' => 'Expiry date',
                'critical' => true,
                'explanation_fr' => 'Le document doit être valide 6 mois après le voyage',
                'explanation_en' => 'Document must be valid 6 months after travel'
            ],
            'date_of_issue' => [
                'label_fr' => 'Date de délivrance',
                'label_en' => 'Date of issue',
                'critical' => false,
                'explanation_fr' => 'Information de traçabilité du document',
                'explanation_en' => 'Document traceability information'
            ],
            'issuing_authority' => [
                'label_fr' => 'Autorité de délivrance',
                'label_en' => 'Issuing authority',
                'critical' => false,
                'explanation_fr' => 'Permet de vérifier l\'authenticité du document',
                'explanation_en' => 'Allows document authenticity verification'
            ],
            'sex' => [
                'label_fr' => 'Sexe',
                'label_en' => 'Sex',
                'critical' => false,
                'explanation_fr' => 'Information de vérification d\'identité',
                'explanation_en' => 'Identity verification information'
            ]
        ];
        
        $present = [];
        $missing = [];
        $incomplete = [];
        $critical_missing = [];
        
        foreach ($requiredFields as $fieldName => $fieldInfo) {
            $fieldValue = null;
            
            // Chercher la valeur dans différents formats possibles
            if (isset($fields[$fieldName])) {
                $fieldValue = is_array($fields[$fieldName]) 
                    ? ($fields[$fieldName]['value'] ?? null) 
                    : $fields[$fieldName];
            }
            
            // Vérifier si le champ est présent et non vide
            if ($fieldValue !== null && $fieldValue !== '' && $fieldValue !== 'null') {
                $present[] = [
                    'field' => $fieldName,
                    'label_fr' => $fieldInfo['label_fr'],
                    'label_en' => $fieldInfo['label_en'],
                    'value' => $fieldValue
                ];
            } else {
                $missingInfo = [
                    'field' => $fieldName,
                    'label_fr' => $fieldInfo['label_fr'],
                    'label_en' => $fieldInfo['label_en'],
                    'critical' => $fieldInfo['critical'],
                    'explanation_fr' => $fieldInfo['explanation_fr'],
                    'explanation_en' => $fieldInfo['explanation_en']
                ];
                
                $missing[] = $missingInfo;
                
                if ($fieldInfo['critical']) {
                    $critical_missing[] = $missingInfo;
                }
            }
        }
        
        // Calcul du score de complétude
        $totalFields = count($requiredFields);
        $presentCount = count($present);
        $completenessScore = $totalFields > 0 ? round(($presentCount / $totalFields) * 100) : 0;
        
        // Déterminer si le document est suffisant seul
        $isSufficientAlone = empty($critical_missing) && $documentCategory === 'PASSPORT';
        
        // Générer un résumé explicatif
        $summary_fr = '';
        $summary_en = '';
        
        if ($documentCategory === 'LAISSEZ_PASSER') {
            $summary_fr = "Les Laissez-Passer délivrés par les organisations internationales ne contiennent généralement pas toutes les informations d'identité nationale requises pour une demande de visa.";
            $summary_en = "Laissez-Passer documents issued by international organizations typically do not contain all the national identity information required for a visa application.";
        } elseif (!empty($critical_missing)) {
            $missingLabels = array_map(fn($f) => $f['label_fr'], $critical_missing);
            $summary_fr = "Le document est incomplet. Informations critiques manquantes : " . implode(', ', $missingLabels) . ".";
            $missingLabelsEn = array_map(fn($f) => $f['label_en'], $critical_missing);
            $summary_en = "The document is incomplete. Missing critical information: " . implode(', ', $missingLabelsEn) . ".";
        }
        
        return [
            'present' => $present,
            'missing' => $missing,
            'incomplete' => $incomplete,
            'critical_missing' => $critical_missing,
            'completeness_score' => $completenessScore,
            'is_sufficient_alone' => $isSufficientAlone,
            'total_fields' => $totalFields,
            'present_count' => $presentCount,
            'missing_count' => count($missing),
            'summary_fr' => $summary_fr,
            'summary_en' => $summary_en
        ];
    }
    
    /**
     * Retourne les alternatives de documents acceptés selon le type détecté
     * 
     * @param string $documentCategory Catégorie du document actuel
     * @return array Liste des alternatives avec leurs caractéristiques
     */
    public static function getDocumentAlternatives(string $documentCategory): array {
        $alternatives = [];
        
        if ($documentCategory === 'LAISSEZ_PASSER' || $documentCategory === 'TRAVEL_DOCUMENT') {
            $alternatives = [
                [
                    'type' => 'ORDINAIRE',
                    'label_fr' => 'Passeport ordinaire',
                    'label_en' => 'Ordinary passport',
                    'description_fr' => 'Passeport biométrique ou ordinaire de votre pays de nationalité',
                    'description_en' => 'Biometric or ordinary passport from your country of nationality',
                    'requires_verbal_note' => false,
                    'workflow' => 'STANDARD',
                    'fees' => true,
                    'processing_days' => '5-10 jours'
                ],
                [
                    'type' => 'DIPLOMATIQUE',
                    'label_fr' => 'Passeport diplomatique',
                    'label_en' => 'Diplomatic passport',
                    'description_fr' => 'Nécessite une note verbale de votre ministère des affaires étrangères',
                    'description_en' => 'Requires a verbal note from your Ministry of Foreign Affairs',
                    'requires_verbal_note' => true,
                    'workflow' => 'PRIORITY',
                    'fees' => false,
                    'processing_days' => '24-48h'
                ],
                [
                    'type' => 'SERVICE',
                    'label_fr' => 'Passeport de service',
                    'label_en' => 'Service passport',
                    'description_fr' => 'Nécessite une note verbale de votre ministère',
                    'description_en' => 'Requires a verbal note from your ministry',
                    'requires_verbal_note' => true,
                    'workflow' => 'PRIORITY',
                    'fees' => false,
                    'processing_days' => '24-48h'
                ]
            ];
        }
        
        return $alternatives;
    }
    
    /**
     * Log conditionnel
     */
    private function log(string $message, string $level = 'info'): void {
        if ($this->config['debug']) {
            error_log("[DocumentExtractor] {$level}: {$message}");
        }
    }
}

