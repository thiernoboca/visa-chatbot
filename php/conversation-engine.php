<?php
/**
 * Moteur Conversationnel Intelligent - Chatbot Visa CI
 * Architecture Triple Layer:
 * - Layer 1: Google Vision (OCR) - voir document-extractor.php
 * - Layer 2: Gemini Flash (Conversation) - ce fichier
 * - Layer 3: Claude Sonnet (Superviseur/Validation) - validation asynchrone
 * 
 * @package VisaChatbot
 * @version 3.0.0
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/gemini-client.php';

// Charger les prompts Gemini
require_once __DIR__ . '/prompts/gemini/greeting-prompt.php';
require_once __DIR__ . '/prompts/gemini/residence-prompt.php';
require_once __DIR__ . '/prompts/gemini/passport-prompt.php';
require_once __DIR__ . '/prompts/gemini/conversation-prompt.php';

class ConversationEngine {
    
    /**
     * Persona du chatbot
     */
    public const PERSONA = [
        'name' => 'Aya',
        'full_name' => 'Aya, Assistante Visa CI',
        'personality' => 'Chaleureuse, professionnelle, légèrement humoristique, culturellement ivoirienne',
        'emoji_signature' => '🇨🇮',
        'cultural_expressions' => [
            'greeting' => ['Akwaba !', 'Bienvenue !', 'Aw ni sogoma !'],
            'encouragement' => ['C\'est doux !', 'On est ensemble !', 'Ça va aller !'],
            'celebration' => ['Yako !', 'C\'est gagné !', 'Bravo !']
        ]
    ];
    
    /**
     * Variations de messages par contexte
     */
    private const MESSAGE_VARIATIONS = [
        'welcome' => [
            'default' => [
                "Akwaba ! 👋 Moi c'est **Aya**, votre assistante pour les visas.\n\nJe vais vous accompagner pas à pas. C'est simple, rapide, et je suis là pour vous aider !\n\nDans quelle langue préférez-vous continuer ?",
                "Bonjour et bienvenue ! 🇨🇮 Je suis **Aya**, de l'Ambassade de Côte d'Ivoire.\n\nEnsemble, on va préparer votre demande de visa. Comptez environ 8 minutes - le temps d'un bon café ☕\n\nQuelle langue choisissez-vous ?",
                "Salut ! 👋 Aya ici, votre guide pour le visa ivoirien.\n\nPrêt(e) à découvrir la Côte d'Ivoire ? Commençons par choisir votre langue !"
            ],
            'returning_user' => "Rebonjour ! 😊 Contente de vous revoir.\n\nVotre précédente demande était en cours. On reprend où vous en étiez ?\n\n{resume_summary}"
        ],
        
        'residence_question' => [
            'default' => [
                "Parfait ! Première étape : votre lieu de résidence.\n\n🌍 Dans quel pays habitez-vous actuellement ?",
                "Super ! Pour commencer, j'ai besoin de savoir où vous résidez.\n\nNotre ambassade couvre l'Éthiopie, le Kenya, Djibouti, la Tanzanie, l'Ouganda, le Soudan du Sud et la Somalie. Où êtes-vous ?",
                "C'est parti ! 🚀 Dites-moi dans quel pays vous vivez, et je vérifie que notre ambassade peut traiter votre demande."
            ]
        ],
        
        'residence_confirmed' => [
            'default' => "✅ Parfait, {city}, {country} ! Notre ambassade peut bien traiter votre demande.\n\nOn continue ? 💪"
        ],
        
        'passport_intro' => [
            'default' => [
                "Maintenant, le moment clé : votre passeport ! 📸\n\nNotre IA va lire automatiquement vos informations - fini la saisie manuelle !\n\n**Astuce** : Une bonne lumière = extraction parfaite ✨",
                "Passons au passeport ! C'est l'étape la plus importante.\n\nScannez ou photographiez la page avec votre photo. Notre technologie fait le reste en quelques secondes 🚀",
                "Super {firstname} ! 🎉 Place au passeport.\n\nJe vais analyser votre document avec l'IA. Préparez la page d'identité et c'est parti !"
            ],
            'diplomatic' => "En tant que diplomate, vous bénéficiez d'un traitement VIP ! 🎖️\n\nMontrez-moi votre passeport diplomatique, et je simplifie tout le processus pour vous.",
            'has_documents' => "J'ai déjà extrait des infos de vos documents ! 📄\n\nVérifions ensemble que tout est correct. Voici ce que j'ai trouvé :"
        ],
        
        'trip_intro' => [
            'default' => [
                "Parlons de votre voyage ! ✈️🌴\n\nQuand prévoyez-vous d'arriver en Côte d'Ivoire ?",
                "Passons aux détails du voyage ! La Côte d'Ivoire vous attend 🇨🇮\n\nQuelle est votre date d'arrivée prévue ?",
                "Excellent {firstname} ! On avance bien. 💪\n\nMaintenant, parlons de votre séjour. Quand arrivez-vous ?"
            ]
        ],
        
        'health_vaccination' => [
            'default' => [
                "⚠️ **Point important** : La vaccination fièvre jaune est **obligatoire** pour entrer en Côte d'Ivoire.\n\nÊtes-vous vacciné(e) ?",
                "Avant de continuer, vérifions un point crucial ! 💉\n\nLa Côte d'Ivoire exige la vaccination contre la fièvre jaune. C'est le cas pour vous ?"
            ],
            'already_has_document' => "J'ai vu votre carnet de vaccination ! 💉✅\n\nJe confirme : vaccination fièvre jaune valide. On peut continuer !"
        ],
        
        'confirmation' => [
            'default' => [
                "🎉 **On y est presque, {firstname} !**\n\nVoici le récapitulatif de votre demande. Vérifiez bien chaque point avant de valider.",
                "Dernière ligne droite ! 🏁\n\nRelisez attentivement les informations ci-dessous. Tout est correct ?"
            ]
        ],
        
        'success' => [
            'default' => [
                "🎊 **Félicitations {firstname} !** Votre demande est soumise !\n\nNuméro de dossier : **{application_number}**\n\nVous recevrez un email de confirmation à {email}.\n\nÀ bientôt en Côte d'Ivoire ! 🇨🇮🌴",
                "✅ **C'est fait !** Bravo {firstname} !\n\n📧 Confirmaton envoyée à {email}\n📋 Dossier N° {application_number}\n⏱️ Délai estimé : {processing_time}\n\nBon voyage et... Akwaba ! 🇨🇮"
            ]
        ]
    ];
    
    /**
     * Encouragements selon la progression (clés = seuil * 100)
     */
    private const PROGRESS_ENCOURAGEMENTS = [
        25 => [
            "Un quart du chemin ! 🚀 Vous allez vite !",
            "Super début ! On continue sur cette lancée 💪"
        ],
        50 => [
            "Mi-parcours ! Vous êtes au top 🌟",
            "La moitié est faite ! Courage, on y est presque !"
        ],
        75 => [
            "Plus que quelques étapes ! 🏁",
            "On approche de la ligne d'arrivée !"
        ],
        90 => [
            "Dernière ligne droite ! 💪",
            "Presque fini, tenez bon !"
        ]
    ];
    
    /**
     * Client Gemini (Layer 2 - Conversation)
     */
    private ?GeminiClient $gemini = null;
    
    /**
     * API Key Claude (Layer 3 - Validation)
     */
    private string $claudeApiKey;
    
    /**
     * Modèle Claude à utiliser
     */
    private string $claudeModel;
    
    /**
     * Mode debug
     */
    private bool $debug;
    
    /**
     * Utiliser Gemini pour la conversation
     */
    private bool $useGemini = true;
    
    /**
     * Constructeur
     */
    public function __construct(array $options = []) {
        $this->claudeApiKey = $options['claude_api_key'] ?? (defined('CLAUDE_API_KEY') ? CLAUDE_API_KEY : getenv('CLAUDE_API_KEY'));
        $this->claudeModel = $options['claude_model'] ?? (defined('CLAUDE_MODEL') ? CLAUDE_MODEL : 'claude-3-5-haiku-20241022');
        $this->debug = $options['debug'] ?? false;
        $this->useGemini = $options['use_gemini'] ?? true;
        
        // Initialiser Gemini si configuré
        $geminiApiKey = getenv('GEMINI_API_KEY');
        if ($this->useGemini && !empty($geminiApiKey)) {
            try {
                $this->gemini = new GeminiClient([
                    'api_key' => $geminiApiKey,
                    'debug' => $this->debug
                ]);
                $this->log("Gemini client initialized (Layer 2 active)");
            } catch (Exception $e) {
                $this->log("Gemini init failed, falling back to Claude: " . $e->getMessage());
                $this->useGemini = false;
            }
        } else {
            $this->useGemini = false;
            $this->log("Gemini not configured, using Claude only");
        }
    }
    
    /**
     * Vérifie si Gemini est actif
     */
    public function isGeminiActive(): bool {
        return $this->useGemini && $this->gemini !== null;
    }
    
    /**
     * Génère un message personnalisé avec variations
     * 
     * @param string $messageKey Clé du message
     * @param array $context Contexte (données collectées, étape, etc.)
     * @param string $lang Langue
     * @return string Message personnalisé
     */
    public function generateMessage(string $messageKey, array $context = [], string $lang = 'fr'): string {
        // Obtenir les variations disponibles
        $variations = self::MESSAGE_VARIATIONS[$messageKey] ?? null;
        
        if (!$variations) {
            // Fallback sur les messages existants
            return getMessage($messageKey, $lang, $context);
        }
        
        // Sélectionner la variation appropriée selon le contexte
        $selectedVariation = $this->selectVariation($variations, $context);
        
        // Remplacer les placeholders
        $message = $this->replacePlaceholders($selectedVariation, $context);
        
        // Ajouter un encouragement si approprié
        $message = $this->maybeAddEncouragement($message, $context);
        
        return $message;
    }
    
    /**
     * Sélectionne la variation de message appropriée
     */
    private function selectVariation(array $variations, array $context): string {
        // Vérifier les conditions spéciales
        if (isset($context['is_returning_user']) && $context['is_returning_user'] && isset($variations['returning_user'])) {
            return $variations['returning_user'];
        }
        
        if (isset($context['is_diplomatic']) && $context['is_diplomatic'] && isset($variations['diplomatic'])) {
            return $variations['diplomatic'];
        }
        
        if (isset($context['has_documents']) && $context['has_documents'] && isset($variations['has_documents'])) {
            return $variations['has_documents'];
        }
        
        if (isset($context['already_has_document']) && $context['already_has_document'] && isset($variations['already_has_document'])) {
            return $variations['already_has_document'];
        }
        
        // Sélectionner une variation aléatoire par défaut
        $defaultVariations = $variations['default'] ?? [$variations];
        
        if (is_array($defaultVariations)) {
            return $defaultVariations[array_rand($defaultVariations)];
        }
        
        return $defaultVariations;
    }
    
    /**
     * Remplace les placeholders dans le message
     */
    private function replacePlaceholders(string $message, array $context): string {
        $placeholders = [
            '{firstname}' => $context['firstname'] ?? $context['given_names'] ?? '',
            '{surname}' => $context['surname'] ?? '',
            '{city}' => $context['city'] ?? '',
            '{country}' => $context['country'] ?? $context['country_name'] ?? '',
            '{email}' => $context['email'] ?? '',
            '{application_number}' => $context['application_number'] ?? '',
            '{processing_time}' => $context['processing_time'] ?? '5-10 jours ouvrés',
            '{passport_type}' => $context['passport_type'] ?? 'Ordinaire',
            '{resume_summary}' => $context['resume_summary'] ?? ''
        ];
        
        foreach ($placeholders as $key => $value) {
            $message = str_replace($key, $value, $message);
        }
        
        return $message;
    }
    
    /**
     * Ajoute un encouragement basé sur la progression
     */
    private function maybeAddEncouragement(string $message, array $context): string {
        if (!isset($context['progress'])) {
            return $message;
        }
        
        $progress = $context['progress'];
        
        // Convertir la progression en pourcentage si c'est une fraction
        $progressPercent = $progress > 1 ? $progress : $progress * 100;
        
        foreach (self::PROGRESS_ENCOURAGEMENTS as $threshold => $encouragements) {
            if (abs($progressPercent - $threshold) < 5) {
                $encouragement = $encouragements[array_rand($encouragements)];
                return $message . "\n\n" . $encouragement;
            }
        }
        
        return $message;
    }
    
    /**
     * Comprend l'intention de l'utilisateur via Gemini (Layer 2) ou Claude (fallback)
     * 
     * @param string $userInput Message de l'utilisateur
     * @param array $context Contexte actuel (étape, données collectées)
     * @return array Intention détectée avec données extraites
     */
    public function understandIntent(string $userInput, array $context): array {
        $currentStep = $context['current_step'] ?? 'unknown';
        $lang = $context['language'] ?? 'fr';
        
        // Essayer d'abord avec Gemini (Layer 2)
        if ($this->isGeminiActive()) {
            try {
                return $this->understandIntentWithGemini($userInput, $context, $currentStep, $lang);
            } catch (Exception $e) {
                $this->log("Gemini NLU failed, falling back to Claude: " . $e->getMessage());
            }
        }
        
        // Fallback Claude
        $systemPrompt = $this->buildNLUSystemPrompt($currentStep, $context, $lang);
        
        $userPrompt = <<<PROMPT
Message de l'utilisateur: "{$userInput}"

Analyse ce message et retourne un JSON avec:
1. "intent": l'intention principale (provide_info, confirm, deny, ask_help, express_confusion, other)
2. "extracted_data": les données extraites selon le contexte de l'étape
3. "sentiment": positif, neutre, négatif, confus
4. "needs_clarification": boolean, si le message n'est pas clair
5. "suggested_response_tone": formel, amical, encourageant, explicatif
PROMPT;
        
        try {
            $response = $this->callClaude($systemPrompt, $userPrompt);
            return $this->parseNLUResponse($response);
        } catch (Exception $e) {
            $this->log("NLU Error: " . $e->getMessage());
            return $this->fallbackNLU($userInput, $currentStep);
        }
    }
    
    /**
     * Comprend l'intention via Gemini (Layer 2)
     */
    private function understandIntentWithGemini(string $userInput, array $context, string $currentStep, string $lang): array {
        $prompt = <<<PROMPT
Tu es un assistant NLU pour une demande de visa Côte d'Ivoire.
Étape actuelle: {$currentStep}
Langue: {$lang}

Message de l'utilisateur: "{$userInput}"

Analyse ce message et retourne UNIQUEMENT un JSON valide:
{
  "intent": "provide_info|confirm|deny|ask_help|express_confusion|other",
  "extracted_data": {},
  "sentiment": "positif|neutre|négatif|confus",
  "needs_clarification": false,
  "confidence": 0.9
}
PROMPT;

        $result = $this->gemini->chat($prompt, [
            'step' => $currentStep,
            'language' => $lang,
            'action' => 'nlu'
        ]);
        
        // Essayer de parser le JSON de la réponse
        $message = $result['message'] ?? '';
        
        if (preg_match('/\{[\s\S]*\}/', $message, $matches)) {
            $data = json_decode($matches[0], true);
            if ($data && isset($data['intent'])) {
                $data['source'] = 'gemini';
                return $data;
            }
        }
        
        // Si pas de JSON valide, fallback
        throw new Exception('Gemini NLU response not parseable');
    }
    
    /**
     * Construit le prompt système pour NLU selon l'étape
     */
    private function buildNLUSystemPrompt(string $step, array $context, string $lang): string {
        $basePrompt = "Tu es un assistant NLU pour une demande de visa Côte d'Ivoire. ";
        $basePrompt .= "Tu dois comprendre l'intention et extraire les données pertinentes. ";
        $basePrompt .= "Réponds UNIQUEMENT en JSON valide.\n\n";
        
        switch ($step) {
            case 'welcome':
                $basePrompt .= "L'utilisateur doit choisir sa langue (fr ou en). ";
                $basePrompt .= "extracted_data attendu: {\"language\": \"fr\" ou \"en\"}";
                break;
                
            case 'residence':
                $basePrompt .= "L'utilisateur doit indiquer son pays et/ou ville de résidence. ";
                $basePrompt .= "Pays valides de notre juridiction: Éthiopie (ET), Kenya (KE), Djibouti (DJ), Tanzanie (TZ), Ouganda (UG), Soudan du Sud (SS), Somalie (SO). ";
                $basePrompt .= "extracted_data attendu: {\"country_code\": \"XX\", \"country_name\": \"...\", \"city\": \"...\"}";
                break;
                
            case 'contact':
                $substep = $context['contact_substep'] ?? 'email';
                if ($substep === 'email') {
                    $basePrompt .= "L'utilisateur doit fournir son email. ";
                    $basePrompt .= "extracted_data attendu: {\"email\": \"...\"}";
                } elseif ($substep === 'phone') {
                    $basePrompt .= "L'utilisateur doit fournir son numéro de téléphone. ";
                    $basePrompt .= "extracted_data attendu: {\"phone\": \"...\"}";
                }
                break;
                
            case 'trip':
                $substep = $context['trip_substep'] ?? 'arrival';
                if ($substep === 'arrival') {
                    $basePrompt .= "L'utilisateur doit indiquer sa date d'arrivée. ";
                    $basePrompt .= "extracted_data attendu: {\"arrival_date\": \"DD/MM/YYYY\"}";
                } elseif ($substep === 'departure') {
                    $basePrompt .= "L'utilisateur doit indiquer sa date de départ. ";
                    $basePrompt .= "extracted_data attendu: {\"departure_date\": \"DD/MM/YYYY\"}";
                } elseif ($substep === 'purpose') {
                    $basePrompt .= "L'utilisateur doit indiquer le motif du voyage. ";
                    $basePrompt .= "Motifs valides: TOURISME, AFFAIRES, FAMILIAL, OFFICIEL, MEDICAL, ETUDES. ";
                    $basePrompt .= "extracted_data attendu: {\"purpose\": \"...\"}";
                }
                break;
                
            case 'health':
                $basePrompt .= "L'utilisateur doit confirmer s'il est vacciné contre la fièvre jaune. ";
                $basePrompt .= "extracted_data attendu: {\"is_vaccinated\": true/false}";
                break;
                
            default:
                $basePrompt .= "Extrait les données pertinentes du message. ";
        }
        
        return $basePrompt;
    }
    
    /**
     * Parse la réponse NLU de Claude
     */
    private function parseNLUResponse(string $response): array {
        // Chercher le JSON dans la réponse
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $response, $matches)) {
            $jsonString = trim($matches[1]);
        } elseif (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $jsonString = $matches[0];
        } else {
            return $this->getDefaultNLUResult();
        }
        
        $data = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->getDefaultNLUResult();
        }
        
        return [
            'intent' => $data['intent'] ?? 'other',
            'extracted_data' => $data['extracted_data'] ?? [],
            'sentiment' => $data['sentiment'] ?? 'neutre',
            'needs_clarification' => $data['needs_clarification'] ?? false,
            'suggested_response_tone' => $data['suggested_response_tone'] ?? 'amical',
            'confidence' => $data['confidence'] ?? 0.8
        ];
    }
    
    /**
     * Fallback NLU quand Claude n'est pas disponible
     */
    private function fallbackNLU(string $input, string $step): array {
        $input = strtolower(trim($input));
        
        $result = $this->getDefaultNLUResult();
        
        // Détection basique d'intention
        if (in_array($input, ['oui', 'yes', 'ok', 'okay', 'd\'accord', 'confirmer', 'confirm', 'valider'])) {
            $result['intent'] = 'confirm';
        } elseif (in_array($input, ['non', 'no', 'pas', 'modifier', 'modify', 'changer'])) {
            $result['intent'] = 'deny';
        } elseif (preg_match('/aide|help|comment|pourquoi|why|what|quoi/', $input)) {
            $result['intent'] = 'ask_help';
        } elseif (preg_match('/je ne comprends pas|confused|lost|perdu/', $input)) {
            $result['intent'] = 'express_confusion';
        } else {
            $result['intent'] = 'provide_info';
        }
        
        // Extraction basique selon l'étape
        switch ($step) {
            case 'welcome':
                if (preg_match('/franc|fr|french/', $input)) {
                    $result['extracted_data']['language'] = 'fr';
                } elseif (preg_match('/engl|en|angl/', $input)) {
                    $result['extracted_data']['language'] = 'en';
                }
                break;
                
            case 'residence':
                // Détection de pays
                $countries = [
                    'ethiopia|éthiopie|ethiopie|addis' => ['code' => 'ET', 'name' => 'Éthiopie'],
                    'kenya|nairobi' => ['code' => 'KE', 'name' => 'Kenya'],
                    'djibouti' => ['code' => 'DJ', 'name' => 'Djibouti'],
                    'tanzania|tanzanie|dar es salaam' => ['code' => 'TZ', 'name' => 'Tanzanie'],
                    'uganda|ouganda|kampala' => ['code' => 'UG', 'name' => 'Ouganda'],
                    'south sudan|soudan du sud|juba' => ['code' => 'SS', 'name' => 'Soudan du Sud'],
                    'somalia|somalie|mogadishu|mogadiscio' => ['code' => 'SO', 'name' => 'Somalie']
                ];
                
                foreach ($countries as $pattern => $data) {
                    if (preg_match("/$pattern/i", $input)) {
                        $result['extracted_data']['country_code'] = $data['code'];
                        $result['extracted_data']['country_name'] = $data['name'];
                        break;
                    }
                }
                break;
                
            case 'contact':
                // Détection email
                if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $input, $matches)) {
                    $result['extracted_data']['email'] = $matches[0];
                }
                // Détection téléphone
                if (preg_match('/\+?[\d\s\-()]{8,}/', $input, $matches)) {
                    $result['extracted_data']['phone'] = preg_replace('/\s+/', '', $matches[0]);
                }
                break;
        }
        
        return $result;
    }
    
    /**
     * Résultat NLU par défaut
     */
    private function getDefaultNLUResult(): array {
        return [
            'intent' => 'provide_info',
            'extracted_data' => [],
            'sentiment' => 'neutre',
            'needs_clarification' => false,
            'suggested_response_tone' => 'amical',
            'confidence' => 0.5
        ];
    }
    
    /**
     * Génère une réponse conversationnelle via Gemini (Layer 2) ou Claude (fallback)
     */
    public function generateConversationalResponse(string $step, array $context, string $lang = 'fr'): string {
        // Essayer d'abord avec Gemini (Layer 2)
        if ($this->isGeminiActive()) {
            try {
                return $this->generateResponseWithGemini($step, $context, $lang);
            } catch (Exception $e) {
                $this->log("Gemini response failed, falling back to Claude: " . $e->getMessage());
            }
        }
        
        // Fallback Claude
        $systemPrompt = $this->buildResponseSystemPrompt($lang);
        $userPrompt = $this->buildResponseUserPrompt($step, $context, $lang);
        
        try {
            $response = $this->callClaude($systemPrompt, $userPrompt);
            return $this->cleanResponse($response);
        } catch (Exception $e) {
            $this->log("Response generation error: " . $e->getMessage());
            // Fallback sur les messages statiques
            return $this->generateMessage($step . '_intro', $context, $lang) 
                ?: getMessage($step, $lang, $context);
        }
    }
    
    /**
     * Génère une réponse via Gemini (Layer 2)
     */
    private function generateResponseWithGemini(string $step, array $context, string $lang): string {
        // Utiliser les prompts spécialisés si disponibles
        $specializedResponse = $this->trySpecializedGeminiPrompt($step, $context, $lang);
        if ($specializedResponse !== null) {
            return $specializedResponse;
        }
        
        // Sinon utiliser le prompt générique
        $systemPrompt = ConversationPrompt::buildSystemPrompt($lang);
        $stepContext = ConversationPrompt::buildStepContext($step, $lang, $context['collected_data'] ?? []);
        
        $result = $this->gemini->chat(
            "Génère un message pour l'étape '{$step}'.",
            [
                'step' => $step,
                'language' => $lang,
                'workflow_type' => $context['workflow_type'] ?? 'STANDARD',
                'collected_data' => $context['collected_data'] ?? []
            ],
            $systemPrompt . "\n\n" . $stepContext
        );
        
        return $this->cleanResponse($result['message'] ?? '');
    }
    
    /**
     * Essaie d'utiliser un prompt spécialisé pour l'étape
     */
    private function trySpecializedGeminiPrompt(string $step, array $context, string $lang): ?string {
        switch ($step) {
            case 'welcome':
                // Utiliser les réponses prédéfinies pour la rapidité
                $responses = GreetingPrompt::getQuickResponses();
                $timeKey = $this->getTimeOfDayKey();
                return $responses[$lang][$timeKey] ?? $responses[$lang]['default'] ?? null;
                
            case 'residence':
                $responses = ResidencePrompt::getQuickResponses();
                return $responses['ask_country'][$lang] ?? null;
                
            case 'passport':
                $responses = PassportPrompt::getQuickResponses();
                return $responses['ask_scan'][$lang] ?? null;
                
            default:
                return null;
        }
    }
    
    /**
     * Retourne la clé pour le moment de la journée
     */
    private function getTimeOfDayKey(): string {
        $hour = (int) date('H');
        if ($hour >= 5 && $hour < 12) return 'morning';
        if ($hour >= 18 || $hour < 5) return 'evening';
        return 'default';
    }
    
    /**
     * Construit le prompt système pour la génération de réponse
     */
    private function buildResponseSystemPrompt(string $lang): string {
        $persona = self::PERSONA;
        
        if ($lang === 'fr') {
            return <<<PROMPT
Tu es {$persona['name']}, assistante virtuelle de l'Ambassade de Côte d'Ivoire.

PERSONNALITÉ:
- {$persona['personality']}
- Tu utilises des emojis avec modération (1-2 par message)
- Tu tutoies l'utilisateur naturellement
- Tu intègres parfois des expressions ivoiriennes (Akwaba = bienvenue)

RÈGLES:
- Réponses concises (max 150 mots)
- Toujours positif et encourageant
- Si problème détecté, rester rassurant
- Utiliser le prénom de l'utilisateur quand disponible
- Ne jamais mentionner que tu es une IA

FORMAT:
- Utilise **gras** pour les infos importantes
- Listes à puces pour les étapes
- Un emoji par paragraphe max
PROMPT;
        }
        
        return <<<PROMPT
You are {$persona['name']}, virtual assistant of the Embassy of Côte d'Ivoire.

PERSONALITY:
- Warm, professional, slightly humorous
- Use emojis sparingly (1-2 per message)
- Be natural and friendly
- Include Ivorian expressions sometimes (Akwaba = welcome)

RULES:
- Concise responses (max 150 words)
- Always positive and encouraging
- If issue detected, remain reassuring
- Use the user's first name when available
- Never mention being an AI

FORMAT:
- Use **bold** for important info
- Bullet points for steps
- One emoji per paragraph max
PROMPT;
    }
    
    /**
     * Construit le prompt utilisateur pour la génération de réponse
     */
    private function buildResponseUserPrompt(string $step, array $context, string $lang): string {
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        $stepDescriptions = [
            'welcome' => 'Accueillir et demander la langue',
            'residence' => 'Demander le pays de résidence',
            'documents' => 'Proposer de télécharger les documents',
            'passport' => 'Demander le scan du passeport',
            'photo' => 'Demander la photo d\'identité',
            'contact' => 'Demander les coordonnées',
            'trip' => 'Demander les informations du voyage',
            'health' => 'Vérifier la vaccination',
            'customs' => 'Déclaration douanes',
            'confirm' => 'Récapitulatif et confirmation'
        ];
        
        $stepDesc = $stepDescriptions[$step] ?? $step;
        
        return <<<PROMPT
ÉTAPE ACTUELLE: {$step} ({$stepDesc})

CONTEXTE DE LA SESSION:
{$contextJson}

Génère un message pour cette étape. Le message doit:
1. Être naturel et engageant
2. Guider l'utilisateur vers la prochaine action
3. Utiliser les infos du contexte si disponibles
PROMPT;
    }
    
    /**
     * Appelle l'API Claude (Layer 3 - Superviseur/Validation)
     */
    private function callClaude(string $systemPrompt, string $userPrompt): string {
        $payload = [
            'model' => $this->claudeModel,
            'max_tokens' => 500,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt]
            ]
        ];
        
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->claudeApiKey,
                'anthropic-version: 2023-06-01'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 15
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($httpCode !== 200) {
            throw new Exception("Claude API error: HTTP {$httpCode}");
        }
        
        $data = json_decode($response, true);
        return $data['content'][0]['text'] ?? '';
    }
    
    /**
     * Demande une validation à Claude (Layer 3 - Superviseur)
     * Utilisé pour les cas critiques: détection de type passeport, anomalies, etc.
     * 
     * @param string $dataType Type de données à valider
     * @param array $data Données à valider
     * @return array Résultat de validation
     */
    public function requestClaudeValidation(string $dataType, array $data): array {
        $prompt = $this->buildValidationPrompt($dataType, $data);
        
        try {
            $response = $this->callClaude(
                "Tu es un superviseur de validation pour les demandes de visa. Analyse les données et signale les anomalies.",
                $prompt
            );
            
            // Parser la réponse JSON
            if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
                $result = json_decode($matches[0], true);
                if ($result) {
                    $result['validated_by'] = 'claude';
                    $result['timestamp'] = date('c');
                    return $result;
                }
            }
            
            return ['valid' => true, 'validated_by' => 'claude', 'warnings' => []];
            
        } catch (Exception $e) {
            $this->log("Claude validation error: " . $e->getMessage());
            return ['valid' => true, 'validated_by' => 'fallback', 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Construit le prompt de validation pour Claude (Layer 3)
     */
    private function buildValidationPrompt(string $dataType, array $data): string {
        $dataJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $prompts = [
            'passport_type' => <<<PROMPT
Valide la détection du type de passeport.

DONNÉES:
{$dataJson}

Vérifie:
1. Le type détecté correspond-il aux indices MRZ et visuels?
2. Y a-t-il des incohérences?
3. Le workflow assigné est-il correct?

Retourne JSON: {"valid": true/false, "detected_type": "...", "confidence": 0.95, "warnings": [], "suggested_workflow": "STANDARD|PRIORITY"}
PROMPT,
            
            'document_coherence' => <<<PROMPT
Vérifie la cohérence entre les documents soumis.

DONNÉES:
{$dataJson}

Vérifie:
1. Le nom sur le passeport correspond-il au nom sur le billet/vaccination?
2. Les dates sont-elles cohérentes?
3. Y a-t-il des anomalies suspectes?

Retourne JSON: {"valid": true/false, "coherence_score": 0.95, "warnings": [], "anomalies": []}
PROMPT,
            
            'application_complete' => <<<PROMPT
Valide la complétude de la demande de visa.

DONNÉES:
{$dataJson}

Vérifie:
1. Tous les champs obligatoires sont-ils remplis?
2. Les documents requis sont-ils présents?
3. Y a-t-il des données suspectes?

Retourne JSON: {"valid": true/false, "completeness": 0.95, "missing_fields": [], "warnings": []}
PROMPT
        ];
        
        return $prompts[$dataType] ?? "Valide ces données:\n{$dataJson}\n\nRetourne JSON: {\"valid\": true/false, \"warnings\": []}";
    }
    
    /**
     * Nettoie la réponse de Claude
     */
    private function cleanResponse(string $response): string {
        // Supprimer les balises de code si présentes
        $response = preg_replace('/```[a-z]*\n?/', '', $response);
        $response = preg_replace('/```/', '', $response);
        
        // Nettoyer les espaces
        $response = trim($response);
        
        return $response;
    }
    
    /**
     * Génère un message d'aide contextuel
     */
    public function generateHelp(string $step, array $context, string $lang = 'fr'): string {
        $helpTopics = [
            'welcome' => [
                'fr' => "Pas de souci ! Choisissez simplement votre langue préférée en cliquant sur 🇫🇷 Français ou 🇬🇧 English.",
                'en' => "No worries! Just choose your preferred language by clicking 🇫🇷 French or 🇬🇧 English."
            ],
            'residence' => [
                'fr' => "Notre ambassade à Addis-Abeba couvre ces pays : Éthiopie, Kenya, Djibouti, Tanzanie, Ouganda, Soudan du Sud et Somalie.\n\nSi vous résidez ailleurs, vous devrez contacter une autre ambassade de Côte d'Ivoire.",
                'en' => "Our embassy in Addis Ababa covers these countries: Ethiopia, Kenya, Djibouti, Tanzania, Uganda, South Sudan and Somalia.\n\nIf you live elsewhere, you'll need to contact another Embassy of Côte d'Ivoire."
            ],
            'passport' => [
                'fr' => "Pour scanner votre passeport :\n1. Ouvrez la page avec votre photo\n2. Placez-la bien à plat, bonne lumière\n3. Photographiez ou scannez\n4. Vérifiez que la zone MRZ (les 2 lignes de code en bas) soit lisible",
                'en' => "To scan your passport:\n1. Open the page with your photo\n2. Place it flat, good lighting\n3. Take a photo or scan\n4. Check that the MRZ zone (2 code lines at bottom) is readable"
            ],
            'health' => [
                'fr' => "La vaccination contre la fièvre jaune est **obligatoire** pour entrer en Côte d'Ivoire.\n\nSi vous n'êtes pas vacciné(e), rendez-vous dans un centre de vaccination agréé. Le vaccin est efficace 10 jours après l'injection et valide à vie.",
                'en' => "Yellow fever vaccination is **mandatory** to enter Côte d'Ivoire.\n\nIf you're not vaccinated, visit an approved vaccination center. The vaccine is effective 10 days after injection and valid for life."
            ]
        ];
        
        return $helpTopics[$step][$lang] ?? $helpTopics[$step]['fr'] ?? 
            ($lang === 'fr' ? "Je suis là pour vous aider ! Que puis-je clarifier ?" : "I'm here to help! What can I clarify?");
    }
    
    /**
     * Log conditionnel
     */
    private function log(string $message): void {
        if ($this->debug) {
            error_log("[ConversationEngine] {$message}");
        }
    }
}

