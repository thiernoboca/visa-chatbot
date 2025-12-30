<?php
/**
 * Client API Gemini 3 Flash - Chatbot Visa CI
 * Ambassade de Côte d'Ivoire en Éthiopie
 *
 * Layer 2 de l'architecture Triple Layer:
 * - Google Vision (Layer 1): OCR rapide
 * - Gemini 3 Flash (Layer 2): Conversation intelligente avec THINKING MODE
 * - Claude Sonnet (Layer 3): Superviseur/Validation
 *
 * GEMINI 3 FLASH THINKING MODE:
 * - minimal: Latence minimale, pas de raisonnement étendu
 * - low: Raisonnement léger, bon équilibre vitesse/qualité
 * - medium: Raisonnement modéré, recommandé pour la plupart des tâches
 * - high: Raisonnement approfondi, meilleure qualité (plus lent)
 *
 * @package VisaChatbot
 * @version 2.0.0 - Gemini 3 Flash avec Thinking Mode natif
 * @see https://ai.google.dev/gemini-api/docs/thinking
 */

class GeminiClient {
    
    /**
     * Clé API Gemini
     */
    private string $apiKey;
    
    /**
     * URL de base de l'API Gemini
     */
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';
    
    /**
     * Modèle Gemini à utiliser
     */
    private string $model;
    
    /**
     * Configuration
     */
    private array $config;

    /**
     * Active le mode thinking natif de Gemini 3
     */
    private bool $enableThinking = true;

    /**
     * Niveaux de "thinking" par contexte
     * Gemini 3 Flash supporte: minimal, low, medium, high
     */
    private array $thinkingLevels = [
        'greeting' => 'minimal',        // Réponses simples, latence min
        'dateQuestions' => 'low',       // Questions basiques
        'passportDetection' => 'high',  // Analyse complexe MRZ/VIZ
        'photoValidation' => 'medium',  // Validation qualité photo
        'feeCalculation' => 'low',      // Calculs simples
        'diplomaticCase' => 'high',     // Cas diplomatiques complexes
        'summary' => 'medium',          // Récapitulatifs
        'ocr_extraction' => 'high',     // Extraction OCR structurée
        'cross_validation' => 'high',   // Validation croisée documents
        'fraud_detection' => 'high',    // Détection fraude
        'default' => 'medium'           // Par défaut: équilibré
    ];
    
    /**
     * Constructeur
     * @param array $options Options de configuration
     */
    public function __construct(array $options = []) {
        $this->apiKey = $options['api_key'] ?? getenv('GEMINI_API_KEY') ?? '';
        // Gemini 3 Flash - Meilleure qualité de raisonnement et extraction
        // Fallback: gemini-2.5-flash > gemini-2.0-flash
        $this->model = $options['model'] ?? getenv('GEMINI_MODEL') ?? 'gemini-3-flash-preview';

        // Activer le mode thinking natif (Gemini 3 uniquement)
        $this->enableThinking = $options['enable_thinking'] ?? true;

        $this->config = [
            'max_tokens' => $options['max_tokens'] ?? 8192, // Augmenté pour thinking + réponses
            'timeout' => $options['timeout'] ?? 60, // Augmenté pour thinking mode
            'temperature' => $options['temperature'] ?? 0.2, // Bas pour précision extraction
            'top_p' => $options['top_p'] ?? 0.9,
            'debug' => $options['debug'] ?? (defined('DEBUG_MODE') && DEBUG_MODE),
            'default_thinking_level' => $options['thinking_level'] ?? 'medium'
        ];

        if (empty($this->apiKey)) {
            throw new Exception('Clé API Gemini non configurée');
        }

        $this->log("Gemini 3 Flash initialisé - Thinking: " . ($this->enableThinking ? 'ACTIVÉ' : 'désactivé'));
    }
    
    /**
     * Génère une réponse conversationnelle
     * 
     * @param string $userMessage Message de l'utilisateur
     * @param array $context Contexte de la conversation (étape, données collectées, etc.)
     * @param string $systemPrompt Prompt système optionnel
     * @return array Réponse structurée
     */
    public function chat(string $userMessage, array $context = [], string $systemPrompt = ''): array {
        $startTime = microtime(true);
        
        // Déterminer le niveau de thinking
        $thinkingLevel = $this->getThinkingLevel($context);
        
        // Construire le prompt avec contexte
        $fullPrompt = $this->buildConversationPrompt($userMessage, $context, $systemPrompt);
        
        // Préparer le payload avec Gemini 3 Thinking Mode
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $fullPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $this->getTemperatureForLevel($thinkingLevel),
                'topP' => $this->config['top_p'],
                'maxOutputTokens' => $this->config['max_tokens'],
                'candidateCount' => 1
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ]
            ]
        ];

        // Ajouter thinkingConfig pour Gemini 3 Flash
        if ($this->enableThinking && $this->isGemini3Model()) {
            $payload['generationConfig']['thinkingConfig'] = [
                'thinkingLevel' => strtoupper($thinkingLevel) // MINIMAL, LOW, MEDIUM, HIGH
            ];
            $this->log("Thinking Mode activé: {$thinkingLevel}");
        }
        
        // Effectuer la requête
        $response = $this->makeRequest($payload);
        
        // Parser la réponse
        $result = $this->parseResponse($response);
        
        // Ajouter les métadonnées
        $result['_metadata'] = [
            'model' => $this->model,
            'thinking_level' => $thinkingLevel,
            'processing_time' => round(microtime(true) - $startTime, 3),
            'timestamp' => date('c')
        ];
        
        $this->log("Chat réussi en {$result['_metadata']['processing_time']}s (thinking: {$thinkingLevel})");
        
        return $result;
    }
    
    /**
     * Génère une réponse avec historique de conversation
     * 
     * @param array $messages Historique des messages [{role, content}, ...]
     * @param array $context Contexte additionnel
     * @return array Réponse structurée
     */
    public function chatWithHistory(array $messages, array $context = []): array {
        $startTime = microtime(true);
        
        // Construire le contenu avec historique
        $contents = [];
        
        foreach ($messages as $msg) {
            $role = $msg['role'] === 'assistant' ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $msg['content']]]
            ];
        }
        
        // Ajouter le contexte système au premier message si fourni
        if (!empty($context['system_prompt']) && !empty($contents)) {
            $systemContext = $this->buildSystemContext($context);
            $contents[0]['parts'][0]['text'] = $systemContext . "\n\n" . $contents[0]['parts'][0]['text'];
        }
        
        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $this->config['temperature'],
                'topP' => $this->config['top_p'],
                'maxOutputTokens' => $this->config['max_tokens']
            ]
        ];
        
        $response = $this->makeRequest($payload);
        $result = $this->parseResponse($response);
        
        $result['_metadata'] = [
            'model' => $this->model,
            'message_count' => count($messages),
            'processing_time' => round(microtime(true) - $startTime, 3),
            'timestamp' => date('c')
        ];
        
        return $result;
    }
    
    /**
     * Analyse et structure des données extraites (pour OCR)
     * Utilise le mode thinking HIGH pour une extraction précise
     *
     * @param string $rawText Texte brut à structurer
     * @param string $documentType Type de document (passport, ticket, vaccination, etc.)
     * @return array Données structurées
     */
    public function structureDocument(string $rawText, string $documentType): array {
        $startTime = microtime(true);

        // Déterminer le niveau de thinking selon le type de document
        $thinkingLevel = $this->getThinkingLevelForDocument($documentType);

        $prompt = $this->buildDocumentStructuringPrompt($rawText, $documentType);

        // OPTIMISATION: max_tokens réduit pour extraction OCR
        // La réponse JSON est petite (~1500 tokens max), pas besoin de 8192
        $maxTokensForOcr = 2048;

        $payload = [
            'contents' => [
                [
                    'parts' => [['text' => $prompt]]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1, // Très bas pour extraction précise
                'topP' => 0.8,
                'maxOutputTokens' => $maxTokensForOcr
            ]
        ];

        // Ajouter thinkingConfig pour Gemini 3 Flash - OCR utilise HIGH par défaut
        if ($this->enableThinking && $this->isGemini3Model()) {
            $payload['generationConfig']['thinkingConfig'] = [
                'thinkingLevel' => strtoupper($thinkingLevel)
            ];
            $this->log("OCR Thinking Mode: {$thinkingLevel} pour {$documentType}");
        }

        $response = $this->makeRequest($payload);
        $result = $this->parseJsonResponse($response);

        $result['_metadata'] = [
            'model' => $this->model,
            'document_type' => $documentType,
            'thinking_level' => $thinkingLevel,
            'thinking_enabled' => $this->enableThinking && $this->isGemini3Model(),
            'processing_time' => round(microtime(true) - $startTime, 3),
            'timestamp' => date('c')
        ];

        return $result;
    }

    /**
     * Détermine le niveau de thinking optimal pour chaque type de document
     *
     * NOTE: Google Vision (Layer 1) fait le gros du travail OCR.
     * Gemini (Layer 2) structure les données - pas besoin de HIGH pour la plupart.
     * HIGH réservé aux cas diplomatiques (validation critique).
     */
    private function getThinkingLevelForDocument(string $documentType): string {
        // OPTIMISÉ v3: LOW pour documents complexes, MINIMAL pour simples
        // Google Vision (Layer 1) fait le gros du travail OCR + MRZ parsing
        // PHP (DocumentExtractor) fait la cross-validation MRZ ↔ VIZ
        // Gemini (Layer 2) structure JSON - LOW requis pour extraction complexe
        // NOTE: MINIMAL trop restrictif - Gemini ne retourne pas de JSON valide
        $levels = [
            'passport' => 'low',        // Extraction VIZ + structuration (LOW nécessaire)
            'verbal_note' => 'low',     // Documents diplomatiques
            'residence_card' => 'low',  // Extraction champs standard
            'vaccination' => 'low',     // Extraction dates/vaccins
            'ticket' => 'low',          // Parsing vols
            'hotel' => 'low',           // Dates séjour
            'invitation' => 'low',      // Extraction invité/invitant
            'payment' => 'low'          // Montants/références
        ];

        return $levels[$documentType] ?? 'minimal';
    }

    /**
     * Vérifie si le modèle actuel est Gemini 3
     */
    private function isGemini3Model(): bool {
        return strpos($this->model, 'gemini-3') !== false ||
               strpos($this->model, 'gemini-3-flash') !== false ||
               strpos($this->model, 'gemini-3-pro') !== false;
    }
    
    /**
     * Détermine le niveau de thinking basé sur le contexte
     */
    private function getThinkingLevel(array $context): string {
        $step = $context['step'] ?? 'default';
        $action = $context['action'] ?? '';
        
        // Cas spéciaux
        if ($action === 'detect_passport_type') {
            return 'high';
        }
        if ($action === 'diplomatic_workflow') {
            return 'high';
        }
        if ($action === 'calculate_fees') {
            return 'low';
        }
        if ($action === 'generate_summary') {
            return 'medium';
        }
        
        // Par étape
        $stepMappings = [
            'welcome' => 'minimal',
            'residence' => 'low',
            'documents' => 'medium',
            'passport' => 'high',
            'photo' => 'medium',
            'contact' => 'low',
            'trip' => 'low',
            'health' => 'medium',
            'customs' => 'low',
            'confirm' => 'medium'
        ];
        
        return $stepMappings[$step] ?? $this->thinkingLevels['default'];
    }
    
    /**
     * Retourne la température selon le niveau de thinking
     */
    private function getTemperatureForLevel(string $level): float {
        $temps = [
            'minimal' => 0.3,
            'low' => 0.5,
            'medium' => 0.7,
            'high' => 0.8
        ];
        return $temps[$level] ?? 0.7;
    }
    
    /**
     * Construit le prompt de conversation complet
     */
    private function buildConversationPrompt(string $userMessage, array $context, string $systemPrompt): string {
        $lang = $context['language'] ?? 'fr';
        $step = $context['step'] ?? 'welcome';
        $collectedData = $context['collected_data'] ?? [];
        $workflowType = $context['workflow_type'] ?? 'STANDARD';
        
        // Contexte système de base
        $baseContext = $this->getBaseSystemPrompt($lang);
        
        // Contexte spécifique à l'étape
        $stepContext = $this->getStepContext($step, $lang, $collectedData, $workflowType);
        
        // Construire le prompt final
        $fullPrompt = $baseContext;
        
        if (!empty($systemPrompt)) {
            $fullPrompt .= "\n\n" . $systemPrompt;
        }
        
        if (!empty($stepContext)) {
            $fullPrompt .= "\n\n" . $stepContext;
        }
        
        // Ajouter les données collectées pertinentes
        if (!empty($collectedData)) {
            $fullPrompt .= "\n\n## Données déjà collectées:\n" . json_encode($collectedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        
        $fullPrompt .= "\n\n## Message utilisateur:\n" . $userMessage;
        
        return $fullPrompt;
    }
    
    /**
     * Retourne le prompt système de base
     */
    private function getBaseSystemPrompt(string $lang): string {
        if ($lang === 'fr') {
            return <<<PROMPT
Tu es l'assistant virtuel officiel de l'Ambassade de Côte d'Ivoire en Éthiopie pour les demandes de visa e-Visa.

## Ton rôle:
- Guider les utilisateurs étape par étape dans leur demande de visa
- Être professionnel, chaleureux et efficace
- Expliquer clairement les exigences et les prochaines étapes
- Détecter automatiquement le type de workflow (STANDARD ou PRIORITY) selon le passeport

## Règles importantes:
- Réponds TOUJOURS dans la langue de l'utilisateur (français par défaut)
- Sois concis mais complet
- Utilise des émojis avec parcimonie pour humaniser l'interaction
- Ne révèle JAMAIS que tu es une IA ou un "modèle de langage"
- Présente-toi comme "l'assistant de l'Ambassade"

## Workflows:
- STANDARD: Passeport ordinaire/officiel → Payant, 5-10 jours
- PRIORITY: Passeport diplomatique/service → Gratuit, 24-48h, note verbale requise

## Format de réponse:
Réponds de manière naturelle et conversationnelle. Si tu dois proposer des actions rapides, liste-les clairement.
PROMPT;
        } else {
            return <<<PROMPT
You are the official virtual assistant of the Embassy of Côte d'Ivoire in Ethiopia for e-Visa applications.

## Your role:
- Guide users step by step through their visa application
- Be professional, warm and efficient
- Clearly explain requirements and next steps
- Automatically detect workflow type (STANDARD or PRIORITY) based on passport

## Important rules:
- ALWAYS respond in the user's language (English in this case)
- Be concise but complete
- Use emojis sparingly to humanize the interaction
- NEVER reveal that you are an AI or "language model"
- Present yourself as "the Embassy assistant"

## Workflows:
- STANDARD: Ordinary/official passport → Paid, 5-10 days
- PRIORITY: Diplomatic/service passport → Free, 24-48h, verbal note required

## Response format:
Respond naturally and conversationally. If you need to suggest quick actions, list them clearly.
PROMPT;
        }
    }
    
    /**
     * Retourne le contexte spécifique à l'étape
     */
    private function getStepContext(string $step, string $lang, array $collectedData, string $workflowType): string {
        $contexts = [
            'welcome' => $lang === 'fr' 
                ? "Étape actuelle: ACCUEIL. Souhaite la bienvenue et demande la langue préférée (FR/EN)."
                : "Current step: WELCOME. Welcome the user and ask for preferred language (FR/EN).",
            
            'residence' => $lang === 'fr'
                ? "Étape actuelle: RÉSIDENCE. Demande le pays de résidence. Pays couverts: Éthiopie, Kenya, Djibouti, Tanzanie, Ouganda, Soudan du Sud, Somalie. Si hors zone, indique poliment que l'ambassade ne couvre pas ce pays."
                : "Current step: RESIDENCE. Ask for country of residence. Covered countries: Ethiopia, Kenya, Djibouti, Tanzania, Uganda, South Sudan, Somalia. If outside zone, politely indicate the embassy doesn't cover that country.",
            
            'documents' => $lang === 'fr'
                ? "Étape actuelle: DOCUMENTS. Propose d'uploader plusieurs documents (passeport, billet, vaccination, etc.) ou de scanner uniquement le passeport."
                : "Current step: DOCUMENTS. Offer to upload multiple documents (passport, ticket, vaccination, etc.) or scan passport only.",
            
            'passport' => $lang === 'fr'
                ? "Étape actuelle: PASSEPORT. Analyse les données OCR du passeport. CRITIQUE: Détecter le type (ORDINAIRE→STANDARD, DIPLOMATIQUE/SERVICE→PRIORITY). Confirmer les données avec l'utilisateur."
                : "Current step: PASSPORT. Analyze passport OCR data. CRITICAL: Detect type (ORDINARY→STANDARD, DIPLOMATIC/SERVICE→PRIORITY). Confirm data with user.",
            
            'photo' => $lang === 'fr'
                ? "Étape actuelle: PHOTO. Demande une photo d'identité conforme (fond uni, visage centré, pas de lunettes). Valide la qualité."
                : "Current step: PHOTO. Request compliant ID photo (plain background, centered face, no glasses). Validate quality.",
            
            'contact' => $lang === 'fr'
                ? "Étape actuelle: CONTACT. Collecter email, téléphone, et demander si WhatsApp disponible."
                : "Current step: CONTACT. Collect email, phone, and ask if WhatsApp available.",
            
            'trip' => $lang === 'fr'
                ? "Étape actuelle: VOYAGE. Collecter dates d'arrivée/départ, motif, type de visa. Pour STANDARD: demander hébergement. Pour PRIORITY: optionnel."
                : "Current step: TRIP. Collect arrival/departure dates, purpose, visa type. For STANDARD: ask accommodation. For PRIORITY: optional.",
            
            'health' => $lang === 'fr'
                ? "Étape actuelle: SANTÉ. Vérifier vaccination fièvre jaune (OBLIGATOIRE). Demander upload du carnet si vacciné."
                : "Current step: HEALTH. Check yellow fever vaccination (MANDATORY). Request card upload if vaccinated.",
            
            'customs' => $lang === 'fr'
                ? "Étape actuelle: DOUANES. Questions sur devises >10k€, marchandises commerciales, produits sensibles, médicaments."
                : "Current step: CUSTOMS. Questions about currency >10k€, commercial goods, sensitive products, medications.",
            
            'confirm' => $lang === 'fr'
                ? "Étape actuelle: CONFIRMATION. Récapituler toutes les informations. Calculer frais (STANDARD) ou confirmer gratuité (PRIORITY). Demander validation finale."
                : "Current step: CONFIRMATION. Summarize all information. Calculate fees (STANDARD) or confirm free (PRIORITY). Request final validation."
        ];
        
        $context = $contexts[$step] ?? '';
        
        if ($workflowType === 'PRIORITY') {
            $context .= $lang === 'fr' 
                ? "\n\n⚡ WORKFLOW PRIORITY ACTIF: Traitement gratuit et prioritaire (24-48h)."
                : "\n\n⚡ PRIORITY WORKFLOW ACTIVE: Free and priority processing (24-48h).";
        }
        
        return $context;
    }
    
    /**
     * Construit le contexte système pour l'historique
     */
    private function buildSystemContext(array $context): string {
        return $this->getBaseSystemPrompt($context['language'] ?? 'fr');
    }
    
    /**
     * Construit le prompt pour structurer un document
     */
    private function buildDocumentStructuringPrompt(string $rawText, string $documentType): string {
        $prompts = [
            'passport' => <<<PROMPT
Tu es un expert en documents de voyage et en analyse MRZ (Machine Readable Zone). 
Ton objectif est d'extraire TOUTES les données du document en CROISANT les informations visuelles (VIZ) avec le MRZ.

TEXTE OCR:
{$rawText}

## ÉTAPE 1: EXTRACTION MRZ (PRIORITAIRE)

Le MRZ est la zone lisible par machine en bas du document (2 lignes de 44 caractères).
CHERCHE ces lignes attentivement dans le texte OCR.

### Structure MRZ ICAO 9303 - LIGNE 1 (44 caractères):
- Positions 1-2: Type document (P<, PL, V<, I<)
- Positions 3-5: Code pays/organisation émetteur
- Positions 6-44: NOM<<PRÉNOMS (séparés par <<)

### Structure MRZ ICAO 9303 - LIGNE 2 (44 caractères):
- Positions 1-9: NUMÉRO DE DOCUMENT ⚠️ CRITIQUE - copie EXACTEMENT
- Position 10: Check digit du numéro
- Positions 11-13: Code nationalité
- Positions 14-19: Date naissance (YYMMDD)
- Position 20: Check digit
- Position 21: Sexe (M/F/<)
- Positions 22-27: Date expiration (YYMMDD)
- Position 28: Check digit
- Positions 29-42: Données optionnelles
- Positions 43-44: Check digits

### Exemples de MRZ:
Passeport standard:
  P<CIVKANE<<MOUHAMAD<HADY<<<<<<<<<<<<<<<<<<<<<
  C12345678<0CIV8903151M2803156<<<<<<<<<<<<<<00

Laissez-Passer:
  PLEUETRAORE<<LEYLA<ZINAB<<<<<<<<<<<<<<<<<<<<<
  S920909<<5FRA8306292F2908177<<<<<<<<<<<<<<4

## ÉTAPE 2: EXTRACTION VISUELLE (VIZ) - DONNÉES ENRICHIES

Le VIZ contient des informations UNIQUES absentes du MRZ. C'est la zone visuelle du document.

### Champs Standards VIZ:
- Champ 3 ou "No/N°": Numéro de document
- Champ 4: Nom (AVEC ACCENTS - ex: TRAORÉ, CÔTÉ, MÜLLER)
- Champ 5: Prénoms (AVEC ACCENTS - ex: Léïla, François, María)
- Champ 6: Nationalité ⚠️ IMPORTANT - peut être format "ORG/PAYS" (ex: "EUE/FRA" = France)
- Champ 7: Date de naissance
- Champ 8: Sexe
- Champ 9: Lieu de naissance ⚠️ ABSENT DU MRZ - très important
- Champ 10: Date de délivrance ⚠️ ABSENT DU MRZ
- Champ 11: Autorité/Délivré par ⚠️ ABSENT DU MRZ
- Champ 12: Date d'expiration

### Données VIZ EXCLUSIVES (jamais dans MRZ):
| Champ | Importance | Exemple |
|-------|-----------|---------|
| place_of_birth | CRITIQUE | "ABIDJAN", "PARIS 12E", "ADDIS ABABA" |
| place_of_issue | IMPORTANT | "AMBASSADE PARIS", "DIRECTION GÉNÉRALE" |
| date_of_issue | IMPORTANT | "15/03/2020" |
| issuing_authority | IMPORTANT | "MINISTÈRE DES AFFAIRES ÉTRANGÈRES" |
| personal_number | OPTIONNEL | Numéro national (si présent) |

### Détection Nationalité VIZ pour Laissez-Passer:
⚠️ Dans les LP, le champ 6 peut avoir DEUX informations:
- Format "ORGANISATION / PAYS": ex: "EUE / FRA" → Nationalité = FRA (France)
- Format "ORGANISATION": ex: "NATIONS UNIES" → Pas de nationalité d'État
- Format "CODE": ex: "FRA", "CIV" → Nationalité d'État directe

RÈGLE: Si tu vois "XXX / YYY" où YYY est un code pays valide → YYY est la nationalité

## ÉTAPE 3: CROISEMENT MRZ ↔ VIZ

⚠️ RÈGLES DE PRIORITÉ ABSOLUES:
| Champ | Source Prioritaire | Raison |
|-------|-------------------|--------|
| document_number | MRZ (pos 1-9 ligne 2) | Plus fiable, pas d'OCR ambiguë |
| date_of_birth | MRZ (pos 14-19 ligne 2) | Format standardisé YYMMDD |
| date_of_expiry | MRZ (pos 22-27 ligne 2) | Format standardisé YYMMDD |
| sex | MRZ (pos 21 ligne 2) | Pas d'ambiguïté |
| nationality | MRZ (pos 11-13 ligne 2) | Code ISO standard |
| surname | VIZ (champ 4) | Préserve les accents (TRAORÉ vs TRAORE) |
| given_names | VIZ (champ 5) | Préserve les accents |
| place_of_birth | VIZ uniquement | Absent du MRZ |
| place_of_issue | VIZ uniquement | Absent du MRZ |
| date_of_issue | VIZ uniquement | Absent du MRZ |

## INDICATEURS DE CLASSIFICATION

### LAISSEZ-PASSER:
1. MRZ commence par "PL", "V<", "I<" (pas "P<")
2. Code organisation: UNO, XXA, XAU, XUA, XOM, XPO, XXB, XCC, XCE, XEU, EUE
3. Mentions: "LAISSEZ-PASSER", "UNITED NATIONS", "AFRICAN UNION", "EUROPEAN UNION"

### PASSEPORT:
1. MRZ commence par "P<" suivi d'un code pays ISO
2. Code pays standard (CIV, FRA, ETH, USA, etc.)
3. Sous-types: ORDINARY, DIPLOMATIC (mention "DIPLOMATIQUE"), SERVICE, OFFICIAL

## ÉLIGIBILITÉ e-Visa Côte d'Ivoire - RÈGLES ABSOLUES

⚠️ CRITIQUE - Applique ces règles EXACTEMENT pour visa_eligibility:

| Type Document | is_valid | workflow | Raison |
|--------------|----------|----------|--------|
| PASSPORT ORDINARY | true | STANDARD | Passeport valide pour e-Visa |
| PASSPORT DIPLOMATIC | true | PRIORITY | Passeport diplomatique - prioritaire |
| PASSPORT SERVICE | true | PRIORITY | Passeport de service - prioritaire |
| PASSPORT OFFICIAL | true | STANDARD | Passeport officiel - standard |
| LAISSEZ_PASSER (toute org) | **false** | null | ⛔ NON éligible - demander passeport ordinaire |
| TRAVEL_DOCUMENT EMERGENCY | **false** | null | ⛔ NON éligible |
| TRAVEL_DOCUMENT REFUGEE | null | MANUAL_REVIEW | Évaluation manuelle requise |
| TRAVEL_DOCUMENT STATELESS | null | MANUAL_REVIEW | Évaluation manuelle requise |

Pour LAISSEZ_PASSER, reason_fr DOIT être:
"Les Laissez-Passer ne sont pas acceptés pour une demande de visa. Veuillez fournir votre passeport ordinaire."

Pour LAISSEZ_PASSER, reason_en DOIT être:
"Laissez-Passer documents cannot be used for visa applications. Please provide your ordinary passport."

## EXPLICATIONS DÉTAILLÉES (OBLIGATOIRE pour is_valid=false)

⚠️ RÈGLE ABSOLUE: NE JAMAIS INVENTER d'information manquante. Analyse UNIQUEMENT les données RÉELLEMENT extraites du document.

Génère une explication COURTE (1-2 phrases) et FACTUELLE basée sur les données RÉELLES extraites.

### LOGIQUE D'ANALYSE pour LAISSEZ_PASSER:

1. VÉRIFIE si le champ "nationality" contient un CODE PAYS valide (FRA, CIV, USA, ETH, etc.)
   - Si OUI (ex: "EUE/FRA" ou "FRA"): la nationalité EST présente → ne dis PAS qu'elle manque
   - Si NON (ex: "UNO", "XXA", null): la nationalité est absente ou invalide

2. VÉRIFIE chaque champ extrait AVANT de dire qu'il manque:
   - place_of_birth présent? (ex: COCODY, PARIS)
   - date_of_birth présent?
   - issuing_authority présent?

3. GÉNÈRE le message selon la situation RÉELLE:

CAS A - Nationalité d'État PRÉSENTE (ex: FRA, CIV):
"[NOM], ce Laissez-Passer [ORGANISATION] indique votre nationalité [CODE PAYS], mais seul un passeport national est accepté pour un e-Visa. Veuillez fournir votre passeport [PAYS]."

CAS B - Nationalité ABSENTE ou code organisation (UNO, XXA):
"[NOM], ce Laissez-Passer [ORGANISATION] ne contient pas de nationalité d'État. Veuillez fournir votre passeport ordinaire."

CAS C - Toutes infos présentes mais document non accepté:
"[NOM], les Laissez-Passer ne sont pas acceptés pour un e-Visa, même avec toutes les informations. Veuillez fournir votre passeport national."

## FORMAT JSON REQUIS

{
  "viz_data": {
    "document_number": "Numéro lu visuellement (champ 3)",
    "surname": "NOM avec accents (champ 4) - ex: TRAORÉ, CÔTÉ",
    "given_names": "Prénoms avec accents (champ 5) - ex: Léïla Zinab",
    "date_of_birth": "JJ/MM/AAAA (champ 7)",
    "date_of_expiry": "JJ/MM/AAAA (champ 12)",
    "date_of_issue": "JJ/MM/AAAA (champ 10) ⚠️ ABSENT DU MRZ",
    "place_of_birth": "Lieu complet (champ 9) ⚠️ ABSENT DU MRZ - ex: ABIDJAN, CÔTE D'IVOIRE",
    "place_of_issue": "Lieu de délivrance ⚠️ ABSENT DU MRZ",
    "issuing_authority": "Autorité (champ 11) ⚠️ ABSENT DU MRZ",
    "nationality": "Nationalité (champ 6) - SI format 'ORG/PAYS', extraire PAYS",
    "nationality_raw": "Valeur brute du champ 6 - ex: 'EUE / FRA' ou 'FRANÇAISE'",
    "nationality_code": "Code pays ISO 3 lettres extrait - ex: 'FRA' depuis 'EUE/FRA'",
    "sex": "M ou F (champ 8)"
  },
  "mrz_data": {
    "line1": "Ligne 1 EXACTE du MRZ (44 caractères)",
    "line2": "Ligne 2 EXACTE du MRZ (44 caractères)",
    "parsed": {
      "document_type": "P< ou PL ou V<",
      "issuing_state": "Code 3 lettres",
      "surname": "NOM sans accents",
      "given_names": "PRÉNOMS sans accents",
      "document_number": "NUMÉRO EXACT (9 caractères max)",
      "nationality": "Code 3 lettres",
      "date_of_birth": "YYYY-MM-DD",
      "sex": "M ou F",
      "date_of_expiry": "YYYY-MM-DD"
    }
  },
  "cross_validation": {
    "document_number": {
      "viz": "Valeur VIZ",
      "mrz": "Valeur MRZ",
      "match": true,
      "final": "Valeur MRZ (prioritaire)",
      "source": "mrz"
    },
    "date_of_birth": {
      "viz": "JJ/MM/AAAA",
      "mrz": "YYYY-MM-DD",
      "match": true,
      "final": "YYYY-MM-DD",
      "source": "mrz"
    },
    "surname": {
      "viz": "TRAORÉ",
      "mrz": "TRAORE",
      "match": true,
      "final": "TRAORÉ",
      "source": "viz"
    }
  },
  "document_classification": {
    "category": "PASSPORT|LAISSEZ_PASSER|TRAVEL_DOCUMENT|OTHER",
    "subcategory": "ORDINARY|DIPLOMATIC|SERVICE|OFFICIAL|INTERNATIONAL_ORGANIZATION|EMERGENCY|REFUGEE|STATELESS|null",
    "issuing_organization": "Nom organisation si LP, sinon null",
    "issuing_organization_code": "Code si LP (UNO, XAU, EUE, etc.), sinon null",
    "issuing_country": "Code ISO 3 lettres si passeport, sinon null",
    "detected_indicators": ["liste des indices"],
    "confidence": 0.95
  },
  "visa_eligibility": {
    "is_valid": true,
    "reason_fr": "Message court en français",
    "reason_en": "Short message in English",
    "detailed_explanation_fr": "Explication détaillée générée par l'IA expliquant POURQUOI ce document seul ne suffit pas (pour LAISSEZ_PASSER: expliquer que ces documents délivrés par les organisations internationales ne contiennent pas les informations d'identité nationale requises)",
    "detailed_explanation_en": "Detailed AI-generated explanation of WHY this document alone is not sufficient",
    "workflow": "STANDARD|PRIORITY|MANUAL_REVIEW|null"
  },
  "field_analysis": {
    "is_sufficient_for_visa": false,
    "completeness_score": 80,
    "has_state_nationality": true,
    "detected_nationality_code": "FRA (extrait de viz_data.nationality ou mrz_data.parsed.nationality)",
    "nationality_source": "viz ou mrz - indique d'où vient la nationalité",
    "viz_exclusive_fields": {
      "place_of_birth": "Présent ou null",
      "date_of_issue": "Présent ou null",
      "place_of_issue": "Présent ou null",
      "issuing_authority": "Présent ou null"
    },
    "critical_missing_fields": [],
    "present_fields": ["liste des champs réellement extraits"],
    "mrz_checksum_status": {
      "document_number_valid": true,
      "date_of_birth_valid": true,
      "date_of_expiry_valid": true
    },
    "other_observations_fr": "Analyse FACTUELLE: lister UNIQUEMENT les champs réellement absents",
    "other_observations_en": "FACTUAL analysis: list ONLY actually missing fields"
  },
  "fields": {
    "surname": {"value": "Valeur finale (VIZ prioritaire)", "confidence": 0.95, "source": "viz"},
    "given_names": {"value": "Valeur finale (VIZ prioritaire)", "confidence": 0.95, "source": "viz"},
    "date_of_birth": {"value": "YYYY-MM-DD (MRZ prioritaire)", "confidence": 0.99, "source": "mrz"},
    "sex": {"value": "M|F (MRZ prioritaire)", "confidence": 0.99, "source": "mrz"},
    "nationality": {"value": "Code (MRZ prioritaire)", "confidence": 0.95, "source": "mrz"},
    "document_number": {"value": "NUMÉRO EXACT (MRZ prioritaire)", "confidence": 0.99, "source": "mrz"},
    "date_of_issue": {"value": "YYYY-MM-DD (VIZ seul)", "confidence": 0.90, "source": "viz"},
    "date_of_expiry": {"value": "YYYY-MM-DD (MRZ prioritaire)", "confidence": 0.99, "source": "mrz"},
    "place_of_birth": {"value": "Lieu (VIZ seul)", "confidence": 0.85, "source": "viz"},
    "place_of_issue": {"value": "Lieu (VIZ seul)", "confidence": 0.85, "source": "viz"}
  }
}

## RÈGLES STRICTES

1. ⚠️ NUMÉRO DE DOCUMENT: Utilise TOUJOURS la valeur MRZ (positions 1-9 de la ligne 2)
2. Le numéro peut contenir des lettres (ex: S920909, C1234567)
3. Mets null pour tout champ NON PRÉSENT - Ne devine JAMAIS
4. Convertis les dates MRZ (YYMMDD) en format ISO (YYYY-MM-DD)
5. Pour YYMMDD: si YY <= 30, siècle = 2000; sinon siècle = 1900
6. Les accents sont dans VIZ, pas dans MRZ (TRAORÉ vs TRAORE)
PROMPT,
            
            'ticket' => <<<PROMPT
Tu es un expert en extraction de données de billets d'avion pour demandes de visa.
Ce document peut être un e-ticket, une confirmation de réservation, ou un itinéraire de vol.

TEXTE OCR DU BILLET:
{$rawText}

## INSTRUCTIONS D'EXTRACTION

### 1. VOL ALLER (vers Abidjan/Côte d'Ivoire)
Cherche le PREMIER vol ou le vol vers ABJ/Abidjan:
- Numéro de vol: format XX123 ou XX 123 (ex: ET935, KQ510)
- Date de départ: convertis au format YYYY-MM-DD
- Heure de départ: format HH:MM
- Ville/Aéroport de départ
- Ville/Aéroport d'arrivée

### 2. VOL RETOUR (depuis Abidjan/Côte d'Ivoire) - TRÈS IMPORTANT
Cherche un vol APRÈS le vol aller avec:
- Direction inverse (ABJ → ADD, Abidjan → origine)
- Date postérieure au vol aller
- Mots-clés: "Return", "Retour", "OUTBOUND"/"INBOUND", second segment
- Numéro de vol différent du vol aller

### 3. PASSAGER ET RÉSERVATION
- Nom complet du passager (format NOM/PRÉNOM ou PRÉNOM NOM)
- Référence de réservation (PNR): 6 caractères alphanumériques
- Statut: CONFIRMED, OK, HK = confirmé

FORMAT JSON REQUIS (réponds UNIQUEMENT avec ce JSON):
{
  "passenger_name": "NOM/PRÉNOM ou NOM COMPLET",
  "booking_reference": "ABC123",
  "airline": "Ethiopian Airlines",
  "airline_code": "ET",
  "flight_number": "ET935",
  "ticket_number": "0712345678901 ou null",
  "departure_city": "ADDIS ABABA",
  "arrival_city": "ABIDJAN",
  "departure_date": "YYYY-MM-DD",
  "departure_time": "HH:MM",
  "arrival_time": "HH:MM ou null",
  "return_date": "YYYY-MM-DD ou null si pas de retour",
  "return_flight_number": "ET936 ou null si pas de retour",
  "return_time": "HH:MM ou null",
  "return_departure_city": "ABIDJAN ou null",
  "return_arrival_city": "ADDIS ABABA ou null",
  "is_round_trip": true,
  "status": "CONFIRMED",
  "confidence": 0.90
}

⚠️ RÈGLES IMPORTANTES:
1. Cherche TOUJOURS un vol retour - c'est crucial pour les demandes de visa
2. Si le PDF a plusieurs pages, le vol retour peut être sur une page différente
3. Les dates doivent être au format YYYY-MM-DD (pas DD/MM/YYYY)
4. Mets null (pas "null") pour les champs non trouvés
5. is_round_trip = true si vol retour trouvé, false sinon
6. Déduis la compagnie aérienne du code vol (ET=Ethiopian, AF=Air France, KQ=Kenya Airways, etc.)
7. Le ticket_number est un numéro à 13 chiffres (e-ticket)
PROMPT,
            
            'vaccination' => <<<PROMPT
Tu es un expert en extraction de données de certificats de vaccination internationaux (Carnet Jaune OMS).
L'objectif est de vérifier la vaccination contre la FIÈVRE JAUNE, obligatoire pour la Côte d'Ivoire.

TEXTE OCR DU CERTIFICAT:
{$rawText}

## INSTRUCTIONS D'EXTRACTION

### 1. NOM DU TITULAIRE (holder_name)
Cherche les marqueurs suivants (dans cet ordre de priorité):
- "Name/Nom:" suivi du nom complet
- "SURNAME:" et "GIVEN NAMES:" (combine les deux)
- "Full name:" ou "Nom complet:"
- Tout texte après "This is to certify that" suivi d'un nom propre
- Format éthiopien: prénom suivi du nom du père (ex: "GEZAHEGN MOGES EJIGU")

⚠️ IMPORTANT: Le nom peut être sur plusieurs lignes. Cherche le nom COMPLET.

### 2. DATE DE VACCINATION (vaccination_date)
Cherche les marqueurs:
- "Date of vaccination" / "Date de vaccination"
- "Vaccinated on:" / "Date:"
- Texte près de "YELLOW FEVER" ou "FIÈVRE JAUNE"
- Formats possibles: DD/MM/YYYY, DD-MM-YYYY, DD.MM.YYYY, YYYY-MM-DD, "15 March 2023"

⚠️ Convertis TOUJOURS au format ISO: YYYY-MM-DD

### 3. NUMÉRO DE CERTIFICAT (certificate_number)
Cherche:
- "Certificate No." / "N° Certificat" / "ICV No."
- Format typique: "ETH No.XXXXXX" ou juste un numéro à 6 chiffres
- Peut inclure un préfixe pays (ETH, KEN, DJI, etc.)

### 4. CENTRE DE VACCINATION (center_name)
Cherche:
- "Vaccination Center" / "Centre de vaccination"
- "Administered at" / "Given at"
- Noms d'hôpitaux ou instituts de santé publique

### 5. VALIDITÉ (valid)
- true si la vaccination fièvre jaune est confirmée
- false si manquante ou non identifiable

FORMAT JSON REQUIS (réponds UNIQUEMENT avec ce JSON):
{
  "holder_name": "NOM COMPLET DU TITULAIRE",
  "vaccine_type": "YELLOW_FEVER",
  "vaccination_date": "YYYY-MM-DD",
  "certificate_number": "NUMÉRO COMPLET AVEC PRÉFIXE",
  "center_name": "NOM DU CENTRE",
  "country": "PAYS D'ÉMISSION",
  "valid": true,
  "confidence": 0.90
}

⚠️ Si tu ne trouves pas une information, mets null mais continue à chercher les autres champs.
PROMPT,
            
            'hotel' => <<<PROMPT
Tu es un expert en extraction de données de confirmations de réservation d'hébergement.
Ce document peut être: un email de Booking.com, Airbnb, Hotels.com, Expedia, ou une confirmation directe d'hôtel.

TEXTE OCR DE LA CONFIRMATION:
{$rawText}

## INSTRUCTIONS D'EXTRACTION

### 1. NOM DU CLIENT (guest_name)
Cherche les marqueurs:
- "Guest name:" / "Nom du client:" / "Booked by:"
- "Dear [Nom]," en début d'email
- "Mr/Mrs/Ms [Nom]"
- "Reservation for:" suivi du nom
- Format email: prénom nom (ex: "Gezahegn Moges")

### 2. NOM DE L'HÉBERGEMENT (hotel_name)
Cherche:
- "Property:" / "Hotel:" / "Accommodation:"
- Titre principal de la confirmation
- "Your stay at [Nom]"
- "Reservation at [Nom]"
- Peut être un appartement, Airbnb, résidence, hôtel

### 3. DATES DE SÉJOUR (CRITIQUE - check_in_date et check_out_date)
Cherche ces marqueurs PRÉCISÉMENT:
- "Check-in:" / "Arrivée:" / "From:" / "Début:"
- "Check-out:" / "Départ:" / "To:" / "Until:" / "Fin:"
- "Date d'arrivée" / "Date de départ"
- Dans Booking.com: cherche près de l'icône calendrier ou après "Your reservation"

⚠️ FORMATS DE DATES POSSIBLES:
- "Sat, Dec 28, 2024" → 2024-12-28
- "28 December 2024" → 2024-12-28
- "28/12/2024" → 2024-12-28
- "12/28/2024" (format US) → 2024-12-28
- "2024-12-28" → 2024-12-28

⚠️ CONVERTIS TOUJOURS AU FORMAT ISO: YYYY-MM-DD

### 4. VILLE (hotel_city)
Cherche:
- Ville mentionnée dans l'adresse
- Villes de Côte d'Ivoire: Abidjan, Yamoussoukro, Bouaké, Grand-Bassam, San Pedro

### 5. NUMÉRO DE CONFIRMATION (confirmation_number)
Cherche:
- "Confirmation #:" / "Booking reference:" / "Reservation ID:"
- Numéro à 8-12 chiffres
- Code alphanumérique (ex: "ABC123456")

FORMAT JSON REQUIS (réponds UNIQUEMENT avec ce JSON):
{
  "guest_name": "NOM COMPLET DU CLIENT",
  "hotel_name": "NOM DE L'HÉBERGEMENT",
  "hotel_address": "ADRESSE COMPLÈTE",
  "hotel_city": "VILLE",
  "confirmation_number": "NUMÉRO DE CONFIRMATION",
  "check_in_date": "YYYY-MM-DD",
  "check_out_date": "YYYY-MM-DD",
  "room_type": "TYPE DE CHAMBRE OU APPARTEMENT",
  "number_of_guests": 1,
  "payment_status": "PAID|PENDING|CONFIRMED",
  "confidence": 0.90
}

⚠️ IMPORTANT: Les dates check_in et check_out sont CRITIQUES. Cherche-les attentivement dans tout le document.
⚠️ Si tu ne trouves pas une information, mets null mais continue à chercher les autres champs.
PROMPT,
            
            'invitation' => <<<PROMPT
Extrais les données de cette lettre d'invitation. Réponds UNIQUEMENT en JSON valide.

TEXTE OCR:
{$rawText}

FORMAT JSON REQUIS:
{
  "inviter": {
    "name": "NOM DE L'INVITANT",
    "organization": "ORGANISATION",
    "address": "ADRESSE",
    "phone": "TÉLÉPHONE",
    "email": "EMAIL"
  },
  "invitee": {
    "name": "NOM DE L'INVITÉ",
    "passport_number": "SI MENTIONNÉ"
  },
  "purpose": "MOTIF DE L'INVITATION",
  "dates": {
    "from": "YYYY-MM-DD",
    "to": "YYYY-MM-DD"
  },
  "accommodation_provided": true,
  "legalized": false,
  "date_issued": "YYYY-MM-DD",
  "confidence": 0.85
}
PROMPT,
            
            'verbal_note' => <<<PROMPT
Extrais les données de cette note verbale diplomatique. Réponds UNIQUEMENT en JSON valide.

TEXTE OCR:
{$rawText}

FORMAT JSON REQUIS:
{
  "sender": {
    "ministry": "MINISTÈRE ÉMETTEUR",
    "country": "PAYS",
    "reference": "NUMÉRO DE RÉFÉRENCE"
  },
  "recipient": {
    "embassy": "AMBASSADE DESTINATAIRE",
    "country": "CÔTE D'IVOIRE"
  },
  "subject": "OBJET DE LA NOTE",
  "date": "YYYY-MM-DD",
  "persons_mentioned": [
    {
      "name": "NOM COMPLET",
      "title": "TITRE/FONCTION",
      "passport_number": "SI MENTIONNÉ"
    }
  ],
  "purpose": "BUT DU VOYAGE",
  "travel_dates": {
    "from": "YYYY-MM-DD",
    "to": "YYYY-MM-DD"
  },
  "confidence": 0.85
}
PROMPT,

            'residence_card' => <<<PROMPT
Extrais les données de cette carte de séjour/résidence (titre de séjour, permis de résidence). Réponds UNIQUEMENT en JSON valide.

TEXTE OCR:
{$rawText}

FORMAT JSON REQUIS:
{
  "holder": {
    "surname": "NOM DE FAMILLE",
    "given_names": "PRÉNOMS",
    "date_of_birth": "YYYY-MM-DD",
    "sex": "M ou F",
    "nationality": "NATIONALITÉ D'ORIGINE",
    "photo_present": true
  },
  "document": {
    "card_number": "NUMÉRO DE CARTE",
    "type": "TITRE DE SÉJOUR|PERMIS DE RÉSIDENCE|CARTE DE RÉSIDENT",
    "category": "TRAVAIL|ÉTUDIANT|FAMILLE|RÉFUGIÉ|AUTRE",
    "issuing_country": "PAYS DE DÉLIVRANCE",
    "issuing_authority": "AUTORITÉ DE DÉLIVRANCE",
    "date_of_issue": "YYYY-MM-DD",
    "date_of_expiry": "YYYY-MM-DD",
    "valid": true
  },
  "residence": {
    "address": "ADRESSE SI MENTIONNÉE",
    "city": "VILLE",
    "country": "PAYS DE RÉSIDENCE"
  },
  "confidence": 0.85
}
PROMPT
        ];
        
        return $prompts[$documentType] ?? "Extrais les données structurées de ce texte:\n\n{$rawText}\n\nRéponds en JSON valide.";
    }
    
    /**
     * Configuration des retries
     */
    private int $maxRetries = 2;
    private array $retryDelays = [1, 3]; // Délais en secondes (backoff exponentiel)

    /**
     * Effectue une requête à l'API Gemini avec retry automatique
     *
     * @param array $payload Données de la requête
     * @param int $timeout Timeout en secondes (par défaut: config timeout)
     * @return array Réponse de l'API
     * @throws Exception En cas d'erreur après tous les retries
     */
    private function makeRequest(array $payload, ?int $timeout = null): array {
        $timeout = $timeout ?? $this->config['timeout'];
        $lastException = null;

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $this->executeRequest($payload, $timeout);
            } catch (Exception $e) {
                $lastException = $e;
                $isRetryable = $this->isRetryableError($e);

                if (!$isRetryable || $attempt >= $this->maxRetries) {
                    $this->log("Échec définitif après " . ($attempt + 1) . " tentative(s): " . $e->getMessage(), 'error');
                    throw $e;
                }

                $delay = $this->retryDelays[$attempt] ?? 3;
                $this->log("Tentative " . ($attempt + 1) . " échouée, retry dans {$delay}s: " . $e->getMessage(), 'warning');
                sleep($delay);
            }
        }

        throw $lastException ?? new Exception('Échec de la requête Gemini');
    }

    /**
     * Vérifie si une erreur peut être retentée
     */
    private function isRetryableError(Exception $e): bool {
        $message = strtolower($e->getMessage());

        // Erreurs réseau/timeout
        if (str_contains($message, 'timeout') ||
            str_contains($message, 'connexion') ||
            str_contains($message, 'connection') ||
            str_contains($message, 'temporarily') ||
            str_contains($message, 'service unavailable') ||
            str_contains($message, 'rate limit') ||
            str_contains($message, 'too many requests') ||
            str_contains($message, '429') ||
            str_contains($message, '503') ||
            str_contains($message, '502')) {
            return true;
        }

        return false;
    }

    /**
     * Exécute une requête cURL unique
     */
    private function executeRequest(array $payload, int $timeout): array {
        $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $duration = round((microtime(true) - $startTime) * 1000);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        // Note: curl_close() is deprecated in PHP 8.0+ and has no effect

        // Log de la durée de la requête
        $this->log("Requête Gemini terminée en {$duration}ms (HTTP {$httpCode})");

        // Gérer les erreurs cURL (timeout, réseau, etc.)
        if ($curlErrno !== 0) {
            $errorMsg = $this->getCurlErrorMessage($curlErrno, $curlError);
            $this->log("Erreur cURL [{$curlErrno}]: {$curlError}", 'error');
            throw new Exception($errorMsg);
        }

        // Parser la réponse JSON
        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log("Réponse JSON invalide: " . substr($response, 0, 500), 'error');
            throw new Exception('Réponse invalide de l\'API Gemini');
        }

        // Gérer les erreurs HTTP
        if ($httpCode !== 200) {
            $errorMessage = $data['error']['message'] ?? $response;
            $this->log("Erreur API [{$httpCode}]: {$errorMessage}", 'error');
            throw new Exception($this->getHttpErrorMessage($httpCode, $errorMessage));
        }

        return $data;
    }

    /**
     * Obtient un message d'erreur cURL lisible
     */
    private function getCurlErrorMessage(int $errno, string $error): string {
        $messages = [
            CURLE_OPERATION_TIMEDOUT => "L'analyse prend plus de temps que prévu. Veuillez patienter et réessayer.",
            CURLE_COULDNT_CONNECT => "Impossible de se connecter au service. Vérifiez votre connexion internet.",
            CURLE_COULDNT_RESOLVE_HOST => "Service temporairement indisponible. Réessayez dans quelques instants.",
            CURLE_SSL_CONNECT_ERROR => "Erreur de connexion sécurisée. Réessayez dans quelques instants.",
        ];

        return $messages[$errno] ?? "Erreur de connexion: {$error}";
    }
    
    /**
     * Parse la réponse de Gemini
     */
    private function parseResponse(array $response): array {
        // Extraire le contenu texte
        $content = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
        if (empty($content)) {
            // Vérifier si bloqué par safety
            if (isset($response['candidates'][0]['finishReason']) && 
                $response['candidates'][0]['finishReason'] === 'SAFETY') {
                throw new Exception('Réponse bloquée pour raisons de sécurité');
            }
            throw new Exception('Réponse vide de l\'API Gemini');
        }
        
        return [
            'message' => $content,
            'finish_reason' => $response['candidates'][0]['finishReason'] ?? 'STOP',
            'usage' => $response['usageMetadata'] ?? null
        ];
    }
    
    /**
     * Parse une réponse JSON de Gemini
     * Supporte le mode thinking de Gemini 3 Flash (plusieurs parts)
     */
    private function parseJsonResponse(array $response): array {
        // Gemini 3 Flash avec thinking mode retourne plusieurs parts:
        // - part[0] peut être le thinking (avec thought: true)
        // - part[N] contient la réponse finale avec le JSON
        $parts = $response['candidates'][0]['content']['parts'] ?? [];

        $content = '';
        $thinkingContent = '';

        // Parcourir tous les parts pour trouver le contenu JSON
        foreach ($parts as $part) {
            // Ignorer les parts de thinking (marked with thought: true)
            if (isset($part['thought']) && $part['thought'] === true) {
                $thinkingContent = $part['text'] ?? '';
                continue;
            }

            // Collecter le contenu texte
            if (isset($part['text'])) {
                $content .= $part['text'];
            }
        }

        // Fallback: si aucun contenu non-thinking, utiliser le premier part
        if (empty($content) && !empty($parts)) {
            $content = $parts[0]['text'] ?? '';
        }

        if (empty($content)) {
            $this->log("Réponse vide de Gemini. Parts: " . json_encode(array_keys($parts[0] ?? [])), 'error');
            throw new Exception('Réponse vide de l\'API Gemini');
        }

        // Extraire le JSON de la réponse
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $content, $matches)) {
            $jsonString = trim($matches[1]);
        } elseif (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $jsonString = $matches[0];
        } else {
            $this->log("JSON non trouvé dans réponse: " . substr($content, 0, 500), 'error');
            throw new Exception('Format de réponse invalide (JSON non trouvé)');
        }

        $data = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON invalide dans la réponse: ' . json_last_error_msg());
        }

        return $data;
    }
    
    /**
     * Retourne un message d'erreur user-friendly
     */
    private function getHttpErrorMessage(int $httpCode, string $apiMessage): string {
        $messages = [
            400 => 'Requête invalide',
            401 => 'Clé API Gemini invalide',
            403 => 'Accès refusé à l\'API Gemini',
            404 => 'Modèle Gemini non trouvé',
            429 => 'Quota dépassé, veuillez réessayer plus tard',
            500 => 'Erreur serveur Gemini',
            503 => 'Service Gemini temporairement indisponible'
        ];
        
        return $messages[$httpCode] ?? "Erreur API Gemini (code {$httpCode}): {$apiMessage}";
    }
    
    /**
     * Log conditionnel
     */
    private function log(string $message, string $level = 'info'): void {
        if ($this->config['debug']) {
            $prefix = '[GeminiClient]';
            error_log("{$prefix} " . strtoupper($level) . ": {$message}");
        }
    }
    
    /**
     * Teste la connexion à l'API
     */
    public function testConnection(): bool {
        try {
            $result = $this->chat('Test', ['step' => 'welcome', 'language' => 'fr']);
            return !empty($result['message']);
        } catch (Exception $e) {
            $this->log("Test de connexion échoué: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Retourne les informations de configuration
     */
    public function getInfo(): array {
        return [
            'model' => $this->model,
            'configured' => !empty($this->apiKey),
            'max_tokens' => $this->config['max_tokens'],
            'temperature' => $this->config['temperature'],
            'thinking_enabled' => $this->enableThinking,
            'is_gemini3' => $this->isGemini3Model(),
            'thinking_levels' => $this->thinkingLevels,
            'version' => '2.0.0-thinking'
        ];
    }

    /**
     * Active ou désactive le mode thinking
     */
    public function setThinkingEnabled(bool $enabled): self {
        $this->enableThinking = $enabled;
        $this->log("Thinking mode " . ($enabled ? 'activé' : 'désactivé'));
        return $this;
    }

    /**
     * Définit un niveau de thinking personnalisé pour un contexte
     */
    public function setThinkingLevel(string $context, string $level): self {
        $validLevels = ['minimal', 'low', 'medium', 'high'];
        if (in_array(strtolower($level), $validLevels)) {
            $this->thinkingLevels[$context] = strtolower($level);
        }
        return $this;
    }
}

