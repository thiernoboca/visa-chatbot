<?php
/**
 * Moteur de workflow - Chatbot Visa CI
 * Gère la logique conditionnelle, la validation et la progression
 * Différencie workflows STANDARD (5-10j) et PRIORITY (24-48h)
 * 
 * @package VisaChatbot
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session-manager.php';
require_once __DIR__ . '/webhook-dispatcher.php';
require_once __DIR__ . '/document-extractor.php';
require_once __DIR__ . '/data/countries.php';
require_once __DIR__ . '/data/passport-types.php';
require_once __DIR__ . '/data/workflow-rules.php';
require_once __DIR__ . '/data/chat-messages.php';

// Persona Enhancement Services
require_once __DIR__ . '/services/GeolocationService.php';
require_once __DIR__ . '/services/VaccinationRequirements.php';
require_once __DIR__ . '/services/SmartPrefillService.php';
require_once __DIR__ . '/services/CrossDocumentSync.php';
require_once __DIR__ . '/services/DocumentAnalysisSuggestions.php';
require_once __DIR__ . '/services/DocumentCoherenceValidator.php';

class WorkflowEngine {
    
    /**
     * Gestionnaire de session
     */
    private SessionManager $session;
    
    /**
     * Langue actuelle
     */
    private string $lang;
    
    /**
     * Dispatcher de webhooks
     */
    private WebhookDispatcher $webhooks;
    
    /**
     * Constructeur
     */
    public function __construct(SessionManager $session) {
        $this->session = $session;
        $this->lang = $session->getLanguage();
        $this->webhooks = WebhookDispatcher::getInstance();
    }
    
    /**
     * Dispatch un événement webhook
     */
    private function dispatchWebhook(string $event, array $additionalData = []): void {
        $payload = array_merge([
            'session_id' => $this->session->getSessionId(),
            'current_step' => $this->session->getCurrentStep(),
            'language' => $this->lang,
            'workflow_category' => $this->session->getWorkflowCategory(),
            'progress' => $this->session->getProgress()
        ], $additionalData);
        
        // Dispatch async (non-bloquant)
        try {
            $this->webhooks->dispatch($event, $payload);
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas le workflow
            error_log("Webhook dispatch error: " . $e->getMessage());
        }
    }
    
    /**
     * Navigue vers une étape spécifique
     */
    public function navigateToStep(string $targetStep): array {
        if (!$this->session->goToStep($targetStep)) {
            return $this->createResponse(
                $this->lang === 'fr' 
                    ? "❌ Impossible d'accéder à cette étape pour le moment."
                    : "❌ Cannot access this step at the moment.",
                [],
                ['navigation_error' => true]
            );
        }
        
        $this->lang = $this->session->getLanguage();
        return $this->getStepInitialMessage();
    }
    
    /**
     * Normalise une nationalité et retourne le code pays ISO correspondant
     * Gère les formes adjectivales (ETHIOPIAN), les codes (ETH), et les noms avec accents
     * @param string $nationality La nationalité à normaliser
     * @return string|null Le code pays ISO ou null si non trouvé
     */
    private function normalizeNationality(string $nationality): ?string {
        if (empty($nationality)) return null;
        
        // Table de mapping nationalité -> code pays ISO
        $mapping = [
            // Éthiopie / Ethiopia
            'ethiopian' => 'ETH', 'eth' => 'ETH', 'ethiopia' => 'ETH', 'ethiopie' => 'ETH',
            'ethiopienne' => 'ETH', 'ethiopien' => 'ETH', 'éthiopie' => 'ETH', 'éthiopien' => 'ETH', 'éthiopienne' => 'ETH',
            // Kenya
            'kenyan' => 'KEN', 'ken' => 'KEN', 'kenya' => 'KEN', 'kenyane' => 'KEN', 'kenyen' => 'KEN',
            // Djibouti
            'djiboutian' => 'DJI', 'dji' => 'DJI', 'djibouti' => 'DJI', 'djiboutien' => 'DJI', 'djiboutienne' => 'DJI',
            // Tanzanie / Tanzania
            'tanzanian' => 'TZA', 'tza' => 'TZA', 'tanzania' => 'TZA', 'tanzanie' => 'TZA',
            'tanzanien' => 'TZA', 'tanzanienne' => 'TZA',
            // Ouganda / Uganda
            'ugandan' => 'UGA', 'uga' => 'UGA', 'uganda' => 'UGA', 'ouganda' => 'UGA',
            'ougandais' => 'UGA', 'ougandaise' => 'UGA',
            // Soudan du Sud / South Sudan
            'south sudanese' => 'SSD', 'ssd' => 'SSD', 'south sudan' => 'SSD', 'soudan du sud' => 'SSD',
            'sud-soudanais' => 'SSD', 'sud-soudanaise' => 'SSD', 'sud soudanais' => 'SSD',
            // Somalie / Somalia
            'somali' => 'SOM', 'som' => 'SOM', 'somalia' => 'SOM', 'somalie' => 'SOM',
            'somalien' => 'SOM', 'somalienne' => 'SOM',
            // Côte d'Ivoire
            'ivorian' => 'CIV', 'civ' => 'CIV', 'ivory coast' => 'CIV', 'cote d\'ivoire' => 'CIV',
            'côte d\'ivoire' => 'CIV', 'ivoirien' => 'CIV', 'ivoirienne' => 'CIV',
            // Autres nationalités courantes
            'french' => 'FRA', 'fra' => 'FRA', 'france' => 'FRA', 'français' => 'FRA', 'française' => 'FRA',
            'american' => 'USA', 'usa' => 'USA', 'united states' => 'USA', 'américain' => 'USA', 'américaine' => 'USA',
            'british' => 'GBR', 'gbr' => 'GBR', 'uk' => 'GBR', 'united kingdom' => 'GBR', 'britannique' => 'GBR',
            'german' => 'DEU', 'deu' => 'DEU', 'germany' => 'DEU', 'allemand' => 'DEU', 'allemande' => 'DEU', 'allemagne' => 'DEU',
            'chinese' => 'CHN', 'chn' => 'CHN', 'china' => 'CHN', 'chinois' => 'CHN', 'chinoise' => 'CHN', 'chine' => 'CHN',
            'indian' => 'IND', 'ind' => 'IND', 'india' => 'IND', 'indien' => 'IND', 'indienne' => 'IND', 'inde' => 'IND',
            'nigerian' => 'NGA', 'nga' => 'NGA', 'nigeria' => 'NGA', 'nigérian' => 'NGA', 'nigériane' => 'NGA',
            'ghanaian' => 'GHA', 'gha' => 'GHA', 'ghana' => 'GHA', 'ghanéen' => 'GHA', 'ghanéenne' => 'GHA',
            'senegalese' => 'SEN', 'sen' => 'SEN', 'senegal' => 'SEN', 'sénégal' => 'SEN', 'sénégalais' => 'SEN', 'sénégalaise' => 'SEN',
            'cameroonian' => 'CMR', 'cmr' => 'CMR', 'cameroon' => 'CMR', 'cameroun' => 'CMR', 'camerounais' => 'CMR', 'camerounaise' => 'CMR',
            'south african' => 'ZAF', 'zaf' => 'ZAF', 'south africa' => 'ZAF', 'sud-africain' => 'ZAF', 'sud-africaine' => 'ZAF',
            'egyptian' => 'EGY', 'egy' => 'EGY', 'egypt' => 'EGY', 'égypte' => 'EGY', 'égyptien' => 'EGY', 'égyptienne' => 'EGY',
            'moroccan' => 'MAR', 'mar' => 'MAR', 'morocco' => 'MAR', 'maroc' => 'MAR', 'marocain' => 'MAR', 'marocaine' => 'MAR',
            'algerian' => 'DZA', 'dza' => 'DZA', 'algeria' => 'DZA', 'algérie' => 'DZA', 'algérien' => 'DZA', 'algérienne' => 'DZA',
            'tunisian' => 'TUN', 'tun' => 'TUN', 'tunisia' => 'TUN', 'tunisie' => 'TUN', 'tunisien' => 'TUN', 'tunisienne' => 'TUN',
            'malian' => 'MLI', 'mli' => 'MLI', 'mali' => 'MLI', 'malien' => 'MLI', 'malienne' => 'MLI',
            'burkinabe' => 'BFA', 'bfa' => 'BFA', 'burkina faso' => 'BFA', 'burkinabé' => 'BFA', 'burkinabè' => 'BFA',
            'beninese' => 'BEN', 'ben' => 'BEN', 'benin' => 'BEN', 'bénin' => 'BEN', 'béninois' => 'BEN', 'béninoise' => 'BEN',
            'togolese' => 'TGO', 'tgo' => 'TGO', 'togo' => 'TGO', 'togolais' => 'TGO', 'togolaise' => 'TGO',
            'guinean' => 'GIN', 'gin' => 'GIN', 'guinea' => 'GIN', 'guinée' => 'GIN', 'guinéen' => 'GIN', 'guinéenne' => 'GIN',
            'congolese' => 'COD', 'cod' => 'COD', 'congo' => 'COD', 'congolais' => 'COD', 'congolaise' => 'COD',
            'rwandan' => 'RWA', 'rwa' => 'RWA', 'rwanda' => 'RWA', 'rwandais' => 'RWA', 'rwandaise' => 'RWA',
            'burundian' => 'BDI', 'bdi' => 'BDI', 'burundi' => 'BDI', 'burundais' => 'BDI', 'burundaise' => 'BDI',
            'eritrean' => 'ERI', 'eri' => 'ERI', 'eritrea' => 'ERI', 'érythrée' => 'ERI', 'érythréen' => 'ERI', 'érythréenne' => 'ERI',
            'sudanese' => 'SDN', 'sdn' => 'SDN', 'sudan' => 'SDN', 'soudan' => 'SDN', 'soudanais' => 'SDN', 'soudanaise' => 'SDN'
        ];
        
        // Normaliser: minuscules et supprimer les accents pour la recherche
        $normalized = mb_strtolower(trim($nationality));
        $normalizedNoAccents = $this->removeAccents($normalized);
        
        // Recherche directe
        if (isset($mapping[$normalized])) {
            return $mapping[$normalized];
        }
        
        // Recherche sans accents
        foreach ($mapping as $key => $code) {
            $keyNoAccents = $this->removeAccents($key);
            if ($normalizedNoAccents === $keyNoAccents) {
                return $code;
            }
            // Recherche partielle
            if (strpos($normalizedNoAccents, $keyNoAccents) !== false || strpos($keyNoAccents, $normalizedNoAccents) !== false) {
                return $code;
            }
        }
        
        return null;
    }
    
    /**
     * Supprime les accents d'une chaîne
     */
    private function removeAccents(string $str): string {
        $search = ['à', 'â', 'ä', 'é', 'è', 'ê', 'ë', 'î', 'ï', 'ô', 'ö', 'ù', 'û', 'ü', 'ç', 'À', 'Â', 'Ä', 'É', 'È', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Ö', 'Ù', 'Û', 'Ü', 'Ç'];
        $replace = ['a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'c', 'A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'C'];
        return str_replace($search, $replace, $str);
    }
    
    /**
     * Avance à l'étape suivante et marque l'actuelle comme complétée
     */
    private function advanceToNextStep(): void {
        $currentStep = $this->session->getCurrentStep();
        $this->session->markStepCompleted($currentStep);
        $this->session->updateHighestStep();
        
        // Dispatch webhook: step completed
        $this->dispatchWebhook(WebhookDispatcher::EVENT_STEP_COMPLETED, [
            'completed_step' => $currentStep,
            'next_step' => getNextStep($currentStep)
        ]);
        
        $this->session->nextStep();
    }
    
    /**
     * Retourne le type de workflow actuel (STANDARD/PRIORITY)
     */
    public function getWorkflowType(): string {
        $passportType = $this->session->getCollectedField('passport_type') ?? 'ORDINAIRE';
        return getWorkflowType($passportType);
    }
    
    /**
     * Vérifie si le workflow actuel est prioritaire
     */
    public function isPriorityWorkflow(): bool {
        return $this->getWorkflowType() === WORKFLOW_TYPE_PRIORITY;
    }
    
    /**
     * Retourne les informations de traitement selon le type de workflow
     */
    public function getProcessingInfo(): array {
        $passportType = $this->session->getCollectedField('passport_type') ?? 'ORDINAIRE';
        $isExpress = $this->session->getCollectedField('is_express') ?? false;
        $workflowCategory = $this->session->getWorkflowCategory() ?? WORKFLOW_ORDINAIRE;
        
        if ($this->isPriorityWorkflow()) {
            return [
                'type' => 'PRIORITY',
                'processing_time' => '24-48h',
                'is_free' => true,
                'requires_verbal_note' => requiresVerbalNote($passportType),
                'express_available' => false
            ];
        }
        
        return [
            'type' => 'STANDARD',
            'processing_time' => $isExpress ? '24-48h' : '5-10 jours ouvrés',
            'is_free' => false,
            'requires_verbal_note' => false,
            'express_available' => isExpressAvailable($passportType)
        ];
    }
    
    /**
     * Traite un message utilisateur et retourne la réponse
     */
    public function processUserInput(string $input, array $metadata = []): array {
        $currentStep = $this->session->getCurrentStep();
        
        switch ($currentStep) {
            case 'welcome':
                return $this->handleWelcome($input);

            case 'geolocation':
                return $this->handleGeolocation($input, $metadata);

            case 'passport':
                return $this->handlePassport($input, $metadata);

            case 'residence':
                return $this->handleResidence($input, $metadata);

            // *** NOUVEAUX HANDLERS DOCUMENTS ***
            case 'ticket':
                return $this->handleTicket($input, $metadata);

            case 'hotel':
                return $this->handleHotel($input, $metadata);

            case 'vaccination':
                return $this->handleVaccination($input, $metadata);

            case 'invitation':
                return $this->handleInvitation($input, $metadata);
            // *** FIN NOUVEAUX HANDLERS ***

            case 'eligibility':
                return $this->handleEligibility($input, $metadata);
                
            case 'photo':
                return $this->handlePhoto($input, $metadata);
                
            case 'contact':
                return $this->handleContact($input, $metadata);
                
            case 'trip':
                return $this->handleTrip($input, $metadata);
                
            case 'payment':
                return $this->handlePayment($input, $metadata);
                
            case 'confirm':
                return $this->handleConfirmation($input, $metadata);
                
            default:
                return $this->createResponse(
                    getMessage('error_generic', $this->lang),
                    []
                );
        }
    }
    
    /**
     * Retourne le message initial pour l'étape actuelle
     */
    public function getStepInitialMessage(): array {
        $currentStep = $this->session->getCurrentStep();
        
        switch ($currentStep) {
            case 'welcome':
                // Message bilingue FR/EN avant sélection de langue
                return $this->createResponse(
                    getMessage('welcome_bilingual', $this->lang),
                    getLanguageQuickActions()
                );

            case 'geolocation':
                // Trigger geolocation detection via IP
                return $this->handleGeolocation('', []);

            case 'passport':
                return $this->createResponse(
                    getMessage('passport_scan_request', $this->lang),
                    [],
                    ['input_type' => 'file', 'file_type' => 'passport']
                );
                
            case 'residence':
                // FIX: Call handleResidence() to trigger geolocation detection
                // Instead of returning static message, use handler for IP detection
                return $this->handleResidence('', []);

            // *** NOUVEAUX MESSAGES INITIAUX DOCUMENTS ***
            case 'ticket':
                return $this->handleTicket(null, []);

            case 'hotel':
                return $this->handleHotel(null, []);

            case 'vaccination':
                return $this->handleVaccination(null, []);

            case 'invitation':
                return $this->handleInvitation(null, []);
            // *** FIN NOUVEAUX MESSAGES ***

            case 'eligibility':
                // Appeler le handler qui affiche le récapitulatif des documents
                return $this->handleEligibility('', []);
                
            case 'photo':
                return $this->createResponse(
                    getMessage('photo_request', $this->lang),
                    [],
                    ['input_type' => 'file', 'file_type' => 'photo']
                );
                
            case 'contact':
                return $this->createResponse(
                    getMessage('contact_request', $this->lang),
                    [],
                    ['input_type' => 'email']
                );
                
            case 'trip':
                return $this->createResponse(
                    getMessage('trip_dates_request', $this->lang),
                    [],
                    ['input_type' => 'date']
                );
                
            case 'payment':
                return $this->handlePayment(null, []);
                
            case 'confirm':
                return $this->getConfirmationRecap();
                
            default:
                return $this->createResponse(
                    getMessage('error_generic', $this->lang),
                    []
                );
        }
    }
    
    /**
     * Gère l'étape Welcome
     */
    private function handleWelcome(string $input): array {
        $lang = strtolower(trim($input));
        
        if (in_array($lang, ['fr', 'french', 'français', 'francais'])) {
            $lang = 'fr';
        } elseif (in_array($lang, ['en', 'english', 'anglais'])) {
            $lang = 'en';
        } else {
            $lang = 'fr';
        }
        
        $this->session->setLanguage($lang);
        $this->lang = $lang;
        $this->session->setCollectedField('language', $lang);

        // Dispatch webhook: session created
        $this->dispatchWebhook(WebhookDispatcher::EVENT_SESSION_CREATED, [
            'language_selected' => $lang
        ]);

        $this->advanceToNextStep();

        // After welcome, advance to geolocation step (IP country detection)
        return $this->getStepInitialMessage();
    }

    /**
     * Gère l'étape Géolocalisation
     * Détecte le pays via IP et vérifie la juridiction de l'ambassade
     */
    private function handleGeolocation(string $input, array $metadata): array {
        // Mapping ISO alpha-2 (ipinfo) -> alpha-3 (our system)
        $alpha2to3 = [
            'ET' => 'ETH', 'KE' => 'KEN', 'DJ' => 'DJI',
            'TZ' => 'TZA', 'UG' => 'UGA', 'SS' => 'SSD', 'SO' => 'SOM'
        ];

        $geoData = $this->session->getCollectedField('geolocation_data');
        $geoConfirmed = $this->session->getCollectedField('geolocation_confirmed');

        // =========================================================================
        // FIRST VISIT: Detect IP and ask for confirmation
        // =========================================================================
        if ($geoData === null) {
            $geoService = new GeolocationService();
            $geoData = $geoService->detectCountry();
            $this->session->setCollectedField('geolocation_data', $geoData);

            // Handle local IP (localhost/development)
            if (!empty($geoData['is_local'])) {
                // In development: show manual country selection
                return $this->createResponse(
                    $this->lang === 'fr'
                        ? "🌍 Dans quel pays résidez-vous actuellement ?\n\nNotre service couvre les 7 pays suivants :"
                        : "🌍 Which country do you currently reside in?\n\nOur service covers the following 7 countries:",
                    getCountryQuickActions($this->lang),
                    ['geolocation_manual' => true, 'step' => 'geolocation']
                );
            }

            // IP detection successful
            if ($geoData['success'] && $geoData['country_code']) {
                $countryName = $geoData['country_name'][$this->lang] ?? $geoData['country_name']['en'];
                $flag = $geoData['country_flag'] ?? '';

                if ($geoData['in_jurisdiction']) {
                    // Country is in jurisdiction - ask for confirmation
                    $alpha3 = $alpha2to3[$geoData['country_code']] ?? $geoData['country_code'];
                    return $this->createResponse(
                        $this->lang === 'fr'
                            ? "🌍 Je vois que vous êtes actuellement en **{$flag} {$countryName}**.\n\nEst-ce votre pays de résidence ?"
                            : "🌍 I see you're currently in **{$flag} {$countryName}**.\n\nIs this your country of residence?",
                        [
                            ['label' => $this->lang === 'fr' ? '✅ Oui, c\'est correct' : '✅ Yes, that\'s correct', 'value' => 'geo_confirm_' . $alpha3],
                            ['label' => $this->lang === 'fr' ? '🔄 Non, j\'habite ailleurs' : '🔄 No, I live elsewhere', 'value' => 'geo_other']
                        ],
                        ['geolocation_detected' => true, 'detected_country' => $geoData['country_code'], 'step' => 'geolocation']
                    );
                } else {
                    // Country is OUT of jurisdiction - show available countries
                    $jurisdictionList = $geoService->formatJurisdictionList($this->lang);
                    return $this->createResponse(
                        $this->lang === 'fr'
                            ? "🌍 Je détecte que vous êtes en **{$countryName}**.\n\n⚠️ Notre ambassade à Addis-Abeba couvre uniquement les pays suivants :\n\n{$jurisdictionList}\n\n📍 Si vous résidez dans l'un de ces pays, sélectionnez-le ci-dessous :"
                            : "🌍 I detect you're in **{$countryName}**.\n\n⚠️ Our embassy in Addis Ababa only covers the following countries:\n\n{$jurisdictionList}\n\n📍 If you reside in one of these countries, select it below:",
                        getCountryQuickActions($this->lang),
                        ['geolocation_detected' => true, 'out_of_jurisdiction' => true, 'step' => 'geolocation']
                    );
                }
            }

            // IP detection failed - show manual selection
            return $this->createResponse(
                $this->lang === 'fr'
                    ? "🌍 Dans quel pays résidez-vous actuellement ?"
                    : "🌍 Which country do you currently reside in?",
                getCountryQuickActions($this->lang),
                ['geolocation_failed' => true, 'step' => 'geolocation']
            );
        }

        // =========================================================================
        // HANDLE USER RESPONSE
        // =========================================================================

        // User confirmed detected country
        if (strpos($input, 'geo_confirm_') === 0) {
            $confirmedCode = str_replace('geo_confirm_', '', $input);
            $this->session->setCollectedField('geolocation_confirmed', true);
            $this->session->setCollectedField('residence_country', $confirmedCode);

            // Get country info for message
            $countryInfo = getCountryInfo($confirmedCode, $this->lang);
            $countryName = $countryInfo ? $countryInfo['name'] : $confirmedCode;

            // Advance to passport step
            $this->advanceToNextStep();

            return $this->createResponse(
                $this->lang === 'fr'
                    ? "✅ Parfait ! Votre résidence en **{$countryName}** est confirmée.\n\nPassons maintenant à votre passeport ! 📸\n\nNotre IA va lire automatiquement vos informations - fini la saisie manuelle !\n\n**Conseils pour un scan parfait** :\n• Page d'identité bien éclairée\n• Évitez les reflets\n• Zone MRZ (les 2 lignes en bas) bien visible\n\nC'est parti ! ✨"
                    : "✅ Perfect! Your residence in **{$countryName}** is confirmed.\n\nNow let's scan your passport! 📸\n\nOur AI will automatically read your information - no manual entry!\n\n**Tips for a perfect scan**:\n• ID page well lit\n• Avoid reflections\n• MRZ zone (2 lines at bottom) clearly visible\n\nLet's go! ✨",
                [],
                ['input_type' => 'file', 'file_type' => 'passport', 'step' => 'passport']
            );
        }

        // User wants to select different country
        if ($input === 'geo_other') {
            $this->session->setCollectedField('geolocation_confirmed', false);
            return $this->createResponse(
                $this->lang === 'fr'
                    ? "📍 Dans quel pays résidez-vous actuellement ?"
                    : "📍 Which country do you currently reside in?",
                getCountryQuickActions($this->lang),
                ['step' => 'geolocation']
            );
        }

        // User selected a country from the list (ETH, KEN, DJI, TZA, UGA, SSD, SOM)
        if (isInJurisdiction($input)) {
            $this->session->setCollectedField('residence_country', $input);
            $this->session->setCollectedField('geolocation_confirmed', true);

            $countryInfo = getCountryInfo($input, $this->lang);
            $countryName = $countryInfo ? $countryInfo['name'] : $input;

            // Advance to passport step
            $this->advanceToNextStep();

            return $this->createResponse(
                $this->lang === 'fr'
                    ? "✅ Parfait ! Votre résidence en **{$countryName}** est confirmée.\n\nPassons maintenant à votre passeport ! 📸\n\nNotre IA va lire automatiquement vos informations - fini la saisie manuelle !\n\n**Conseils pour un scan parfait** :\n• Page d'identité bien éclairée\n• Évitez les reflets\n• Zone MRZ (les 2 lignes en bas) bien visible\n\nC'est parti ! ✨"
                    : "✅ Perfect! Your residence in **{$countryName}** is confirmed.\n\nNow let's scan your passport! 📸\n\nOur AI will automatically read your information - no manual entry!\n\n**Tips for a perfect scan**:\n• ID page well lit\n• Avoid reflections\n• MRZ zone (2 lines at bottom) clearly visible\n\nLet's go! ✨",
                [],
                ['input_type' => 'file', 'file_type' => 'passport', 'step' => 'passport']
            );
        }

        // Invalid/unrecognized country - show error with jurisdiction list
        $geoService = new GeolocationService();
        $jurisdictionList = $geoService->formatJurisdictionList($this->lang);

        return $this->createResponse(
            $this->lang === 'fr'
                ? "❌ Désolé, nous ne pouvons traiter que les demandes des résidents des pays suivants :\n\n{$jurisdictionList}\n\nSi vous résidez dans l'un de ces pays, veuillez le sélectionner. Sinon, nous vous invitons à contacter l'ambassade de Côte d'Ivoire compétente pour votre pays de résidence."
                : "❌ Sorry, we can only process applications from residents of the following countries:\n\n{$jurisdictionList}\n\nIf you reside in one of these countries, please select it. Otherwise, please contact the Ivory Coast embassy responsible for your country of residence.",
            getCountryQuickActions($this->lang),
            ['error' => 'out_of_jurisdiction', 'step' => 'geolocation']
        );
    }

    /**
     * Gère l'étape Résidence
     * Note: la nationalité est déjà extraite du passeport à cette étape
     * ENHANCED: IP geolocation detection with country confirmation
     */
    private function handleResidence(string $input, array $metadata): array {
        // =========================================================================
        // FEATURE 1: IP Geolocation Detection
        // =========================================================================
        $geoData = $this->session->getCollectedField('geolocation_data');
        $geoConfirmed = $this->session->getCollectedField('geolocation_confirmed');

        // First visit: detect IP and ask for confirmation
        if ($geoData === null) {
            $geoService = new GeolocationService();
            $geoData = $geoService->detectCountry();
            $this->session->setCollectedField('geolocation_data', $geoData);

            // If IP was successfully detected
            if ($geoData['success'] && $geoData['country_code']) {
                $countryName = $geoData['country_name'][$this->lang] ?? $geoData['country_name']['en'];

                if ($geoData['in_jurisdiction']) {
                    // Country is in jurisdiction - ask for confirmation
                    return $this->createResponse(
                        $this->lang === 'fr'
                            ? "🌍 Je vois que vous êtes actuellement en **{$countryName}**. Est-ce bien votre pays de résidence ?\n\n✅ **Oui, c'est correct**\n🔄 **Non, je réside ailleurs**"
                            : "🌍 I see you're currently in **{$countryName}**. Is this your country of residence?\n\n✅ **Yes, that's correct**\n🔄 **No, I live elsewhere**",
                        [
                            ['label' => $this->lang === 'fr' ? '✅ Oui, c\'est correct' : '✅ Yes, that\'s correct', 'value' => 'geo_confirm_' . $geoData['country_code']],
                            ['label' => $this->lang === 'fr' ? '🔄 Non, je réside ailleurs' : '🔄 No, I live elsewhere', 'value' => 'geo_other']
                        ],
                        ['geolocation_detected' => true, 'detected_country' => $geoData['country_code']]
                    );
                } else {
                    // Country is out of jurisdiction - show available countries
                    $countriesList = $geoService->formatJurisdictionList($this->lang);
                    return $this->createResponse(
                        $this->lang === 'fr'
                            ? "🌍 Je détecte que vous êtes en **{$countryName}**, mais notre ambassade couvre uniquement:\n\n{$countriesList}\n\n📍 Dans quel pays résidez-vous actuellement ?"
                            : "🌍 I detect you're in **{$countryName}**, but our embassy only covers:\n\n{$countriesList}\n\n📍 Which country do you currently reside in?",
                        getCountryQuickActions($this->lang),
                        ['geolocation_detected' => true, 'out_of_jurisdiction' => true]
                    );
                }
            }
            // If IP detection failed, fall through to manual selection
        }

        // Handle geolocation confirmation response
        if (strpos($input, 'geo_confirm_') === 0) {
            $confirmedCode = str_replace('geo_confirm_', '', $input);
            $this->session->setCollectedField('geolocation_confirmed', true);
            $input = $confirmedCode; // Continue with this country code
        } elseif ($input === 'geo_other') {
            // User wants to select a different country
            $this->session->setCollectedField('geolocation_confirmed', false);
            return $this->createResponse(
                getMessage('residence_question', $this->lang),
                getCountryQuickActions($this->lang)
            );
        }

        // =========================================================================
        // END FEATURE 1 - Continue with normal residence handling
        // =========================================================================

        // FIX: If input is empty (first call with failed IP detection or initial step message),
        // return manual country selection instead of falling through to country processing
        if (empty(trim($input))) {
            return $this->createResponse(
                getMessage('residence_question', $this->lang),
                getCountryQuickActions($this->lang)
            );
        }

        $countryCode = strtoupper(trim($input));

        $found = false;
        foreach (JURISDICTION_COUNTRIES as $code => $country) {
            if ($code === $countryCode || 
                stripos($country['nameFr'], $input) !== false ||
                stripos($country['nameEn'], $input) !== false) {
                $countryCode = $code;
                $found = true;
                break;
            }
        }
        
        if (!$found || !isInJurisdiction($countryCode)) {
            $this->session->block('jurisdiction_violation', [
                'country_attempted' => $countryCode,
                'allowed_countries' => JURISDICTION_COUNTRIES
            ]);

            // Dispatch webhook: session abandoned (out of jurisdiction)
            $this->dispatchWebhook(WebhookDispatcher::EVENT_SESSION_ABANDONED, [
                'reason' => 'out_of_jurisdiction',
                'country_attempted' => $countryCode
            ]);
            
            return $this->createResponse(
                getMessage('residence_not_in_jurisdiction', $this->lang),
                [],
                ['blocking' => true]
            );
        }
        
        $this->session->setCollectedField('country_code', $countryCode);
        $countryInfo = getCountryInfo($countryCode, $this->lang);
        
        // Vérifier si la nationalité est différente du pays de résidence
        // Utiliser normalizeNationality pour comparer correctement les formes de nationalité
        $passportData = $this->session->getCollectedField('passport_data');
        $nationality = $passportData['nationality']['value'] ?? null;
        
        if ($nationality) {
            $nationalityCode = $this->normalizeNationality($nationality);
            $residenceCode = $countryCode; // Déjà un code ISO (ETH, KEN, etc.)
            
            // Comparer les codes pays pour une correspondance précise
            // Ethiopian (nationalityCode=ETH) résidant en Éthiopie (residenceCode=ETH) -> même nationalité
            // Ethiopian (nationalityCode=ETH) résidant au Kenya (residenceCode=KEN) -> nationalité différente
            $isResidentOutsideNationality = $nationalityCode !== null && $nationalityCode !== $residenceCode;
            
            $this->session->setCollectedField('is_resident_outside_nationality', $isResidentOutsideNationality);
            
            if ($isResidentOutsideNationality) {
                $this->session->setCollectedField('needs_residence_card', true);
            }
            
            error_log("[Nationality Check] passport=$nationality, nationalityCode=$nationalityCode, residenceCode=$residenceCode, needsResidenceCard=" . ($isResidentOutsideNationality ? 'true' : 'false'));
        }
        
        // Construire le message de confirmation de résidence
        $confirmMsg = $this->lang === 'fr'
            ? "✅ Parfait ! Vous résidez en **{$countryInfo['name']}**."
            : "✅ Great! You reside in **{$countryInfo['name']}**.";

        // Si non-national, informer de la carte de séjour requise
        if ($this->session->getCollectedField('needs_residence_card')) {
            $confirmMsg .= $this->lang === 'fr'
                ? "\n\n📋 En tant que non-national, vous devrez fournir un justificatif de résidence."
                : "\n\n📋 As a non-national, you will need to provide proof of residence.";
        }

        // Passer à l'étape suivante (ticket)
        $this->advanceToNextStep();

        // Retourner le message de confirmation + le message initial du step suivant
        $nextStepMessage = $this->getStepInitialMessage();
        $nextStepMessage['message'] = $confirmMsg . "\n\n" . $nextStepMessage['message'];

        return $nextStepMessage;
    }

    // =========================================================================
    // HANDLERS DOCUMENTS CONVERSATIONNELS (NOUVEAUX)
    // =========================================================================

    /**
     * Gère l'étape Billet d'avion
     */
    private function handleTicket(?string $input, array $metadata): array {
        // Si document uploadé avec données extraites
        if (!empty($metadata['document_uploaded']) && !empty($metadata['extracted_data'])) {
            $extractedData = $metadata['extracted_data'];
            $this->session->setCollectedField('extracted_ticket', $extractedData);

            return $this->formatDocumentExtraction('ticket', $extractedData, [
                'passenger_name' => $this->lang === 'fr' ? 'Passager' : 'Passenger',
                'flight_number' => $this->lang === 'fr' ? 'N° Vol' : 'Flight No',
                'departure_date' => $this->lang === 'fr' ? 'Date départ' : 'Departure date',
                'departure_city' => $this->lang === 'fr' ? 'Ville départ' : 'Departure city',
                'arrival_date' => $this->lang === 'fr' ? 'Date arrivée' : 'Arrival date',
                'arrival_city' => $this->lang === 'fr' ? 'Destination' : 'Destination'
            ]);
        }

        // Si confirmation
        if ($input !== null && in_array(strtolower($input), ['confirm', 'oui', 'yes', 'confirmer'])) {
            $this->advanceToNextStep();
            return $this->getStepInitialMessage();
        }

        // Si demande de réupload
        if ($input !== null && in_array(strtolower($input), ['reupload', 'non', 'no', 'modifier', 'retry'])) {
            // Rester sur le même step, réafficher l'interface d'upload
        }

        // Message initial avec prefill
        $response = $this->createResponse(
            $this->lang === 'fr'
                ? "✈️ **Billet d'avion**\n\nVeuillez télécharger votre billet d'avion ou confirmation de réservation.\n\n📎 Formats acceptés: PDF, JPG, PNG"
                : "✈️ **Flight Ticket**\n\nPlease upload your flight ticket or booking confirmation.\n\n📎 Accepted formats: PDF, JPG, PNG",
            [],
            [
                'input_type' => 'file',
                'document_type' => 'ticket',
                'accept' => '.pdf,.jpg,.jpeg,.png'
            ]
        );

        // Enhance with prefill data from passport
        return $this->enhanceResponseWithPrefill($response, 'ticket');
    }

    /**
     * Gère l'étape Réservation hôtel
     */
    private function handleHotel(?string $input, array $metadata): array {
        // Si document uploadé avec données extraites
        if (!empty($metadata['document_uploaded']) && !empty($metadata['extracted_data'])) {
            $extractedData = $metadata['extracted_data'];
            $this->session->setCollectedField('extracted_hotel', $extractedData);

            return $this->formatDocumentExtraction('hotel', $extractedData, [
                'guest_name' => $this->lang === 'fr' ? 'Client' : 'Guest',
                'hotel_name' => $this->lang === 'fr' ? 'Hôtel' : 'Hotel',
                'hotel_city' => $this->lang === 'fr' ? 'Ville' : 'City',
                'check_in_date' => 'Check-in',
                'check_out_date' => 'Check-out',
                'confirmation_number' => $this->lang === 'fr' ? 'N° Confirmation' : 'Confirmation No'
            ]);
        }

        // Si confirmation
        if ($input !== null && in_array(strtolower($input), ['confirm', 'oui', 'yes', 'confirmer'])) {
            $this->advanceToNextStep();
            return $this->getStepInitialMessage();
        }

        // Si demande de réupload
        if ($input !== null && in_array(strtolower($input), ['reupload', 'non', 'no', 'modifier', 'retry'])) {
            // Rester sur le même step
        }

        // Message initial avec prefill
        $response = $this->createResponse(
            $this->lang === 'fr'
                ? "🏨 **Réservation d'hébergement**\n\nVeuillez télécharger votre confirmation de réservation d'hôtel ou attestation d'hébergement.\n\n📎 Formats acceptés: PDF, JPG, PNG"
                : "🏨 **Accommodation Booking**\n\nPlease upload your hotel booking confirmation or accommodation certificate.\n\n📎 Accepted formats: PDF, JPG, PNG",
            [],
            [
                'input_type' => 'file',
                'document_type' => 'hotel',
                'accept' => '.pdf,.jpg,.jpeg,.png'
            ]
        );

        // Enhance with prefill data
        return $this->enhanceResponseWithPrefill($response, 'hotel');
    }

    /**
     * Gère l'étape Carnet de vaccination
     */
    private function handleVaccination(?string $input, array $metadata): array {
        // Si document uploadé avec données extraites
        if (!empty($metadata['document_uploaded']) && !empty($metadata['extracted_data'])) {
            $extractedData = $metadata['extracted_data'];
            $this->session->setCollectedField('extracted_vaccination', $extractedData);

            return $this->formatDocumentExtraction('vaccination', $extractedData, [
                'holder_name' => $this->lang === 'fr' ? 'Titulaire' : 'Holder',
                'vaccine_type' => $this->lang === 'fr' ? 'Type vaccin' : 'Vaccine type',
                'vaccination_date' => $this->lang === 'fr' ? 'Date vaccination' : 'Vaccination date',
                'certificate_number' => $this->lang === 'fr' ? 'N° Certificat' : 'Certificate No',
                'valid' => $this->lang === 'fr' ? 'Valide' : 'Valid'
            ]);
        }

        // Si confirmation
        if ($input !== null && in_array(strtolower($input), ['confirm', 'oui', 'yes', 'confirmer'])) {
            $this->advanceToNextStep();
            return $this->getStepInitialMessage();
        }

        // Si demande de réupload
        if ($input !== null && in_array(strtolower($input), ['reupload', 'non', 'no', 'modifier', 'retry'])) {
            // Rester sur le même step
        }

        // Message initial avec prefill
        $response = $this->createResponse(
            $this->lang === 'fr'
                ? "💉 **Carnet de vaccination**\n\n⚠️ Le vaccin contre la **fièvre jaune** est **OBLIGATOIRE** pour entrer en Côte d'Ivoire.\n\nVeuillez télécharger votre carnet de vaccination international.\n\n📎 Formats acceptés: PDF, JPG, PNG"
                : "💉 **Vaccination Card**\n\n⚠️ **Yellow fever** vaccination is **MANDATORY** to enter Côte d'Ivoire.\n\nPlease upload your international vaccination card.\n\n📎 Accepted formats: PDF, JPG, PNG",
            [],
            [
                'input_type' => 'file',
                'document_type' => 'vaccination',
                'accept' => '.pdf,.jpg,.jpeg,.png'
            ]
        );

        // Enhance with prefill data from passport
        return $this->enhanceResponseWithPrefill($response, 'vaccination');
    }

    /**
     * Gère l'étape Lettre d'invitation
     */
    private function handleInvitation(?string $input, array $metadata): array {
        // Si document uploadé avec données extraites
        if (!empty($metadata['document_uploaded']) && !empty($metadata['extracted_data'])) {
            $extractedData = $metadata['extracted_data'];
            $this->session->setCollectedField('extracted_invitation', $extractedData);

            return $this->formatDocumentExtraction('invitation', $extractedData, [
                'invitee.name' => $this->lang === 'fr' ? 'Invité' : 'Invitee',
                'inviter.name' => $this->lang === 'fr' ? 'Invitant' : 'Inviter',
                'inviter.company' => $this->lang === 'fr' ? 'Société' : 'Company',
                'purpose' => $this->lang === 'fr' ? 'Objet' : 'Purpose',
                'dates' => $this->lang === 'fr' ? 'Dates prévues' : 'Planned dates'
            ]);
        }

        // Si confirmation
        if ($input !== null && in_array(strtolower($input), ['confirm', 'oui', 'yes', 'confirmer'])) {
            $this->advanceToNextStep();
            return $this->getStepInitialMessage();
        }

        // Si demande de réupload
        if ($input !== null && in_array(strtolower($input), ['reupload', 'non', 'no', 'modifier', 'retry'])) {
            // Rester sur le même step
        }

        // Message initial avec prefill
        $response = $this->createResponse(
            $this->lang === 'fr'
                ? "📄 **Lettre d'invitation**\n\nVeuillez télécharger votre lettre d'invitation légalisée.\n\nLa lettre doit mentionner:\n• Nom de l'invitant\n• Objet de la visite\n• Dates du séjour\n\n📎 Formats acceptés: PDF, JPG, PNG"
                : "📄 **Invitation Letter**\n\nPlease upload your legalized invitation letter.\n\nThe letter must include:\n• Inviter's name\n• Purpose of visit\n• Stay dates\n\n📎 Accepted formats: PDF, JPG, PNG",
            [],
            [
                'input_type' => 'file',
                'document_type' => 'invitation',
                'accept' => '.pdf,.jpg,.jpeg,.png'
            ]
        );

        // Enhance with prefill data from passport and ticket
        return $this->enhanceResponseWithPrefill($response, 'invitation');
    }

    /**
     * Formate et affiche les résultats d'extraction OCR dans le chat
     */
    private function formatDocumentExtraction(string $docType, array $data, array $fieldLabels): array {
        $icons = [
            'ticket' => '✈️',
            'hotel' => '🏨',
            'vaccination' => '💉',
            'invitation' => '📄',
            'passport' => '🛂'
        ];

        $typeLabels = [
            'ticket' => $this->lang === 'fr' ? 'Billet d\'avion' : 'Flight ticket',
            'hotel' => $this->lang === 'fr' ? 'Réservation hôtel' : 'Hotel booking',
            'vaccination' => $this->lang === 'fr' ? 'Carnet de vaccination' : 'Vaccination card',
            'invitation' => $this->lang === 'fr' ? 'Lettre d\'invitation' : 'Invitation letter',
            'passport' => $this->lang === 'fr' ? 'Passeport' : 'Passport'
        ];

        $icon = $icons[$docType] ?? '📄';
        $label = $typeLabels[$docType] ?? ucfirst($docType);
        $confidence = round(($data['confidence'] ?? $data['_metadata']['confidence'] ?? $data['overall_confidence'] ?? 0.85) * 100);

        // Cross-validation against prefilled data
        $crossValidation = $this->validateExtractedVsPrefill($docType, $data);
        $validationScore = $crossValidation['overall_score'] ?? 100;
        $validationMatches = $crossValidation['matches'] ?? [];
        $validationMismatches = $crossValidation['mismatches'] ?? [];

        // Construire le message
        $message = $this->lang === 'fr'
            ? "✅ **{$label} analysé**\n\n"
            : "✅ **{$label} analyzed**\n\n";
        $message .= "📊 " . ($this->lang === 'fr' ? 'Confiance' : 'Confidence') . ": **{$confidence}%**\n\n";
        $message .= $this->lang === 'fr' ? "**Informations extraites:**\n" : "**Extracted information:**\n";

        $fieldsFound = 0;
        foreach ($fieldLabels as $fieldPath => $fieldLabel) {
            $value = $this->getNestedValue($data, $fieldPath);
            if ($value !== null && $value !== '' && $value !== 'N/A') {
                $fieldsFound++;
                if (is_bool($value)) {
                    $value = $value
                        ? ($this->lang === 'fr' ? '✅ Oui' : '✅ Yes')
                        : ($this->lang === 'fr' ? '❌ Non' : '❌ No');
                }
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                // Tronquer si trop long
                if (strlen($value) > 60) {
                    $value = substr($value, 0, 57) . '...';
                }

                // Add validation indicator for cross-validated fields
                $validationIndicator = '';
                if (in_array($fieldPath, array_column($validationMatches, 'field'))) {
                    $validationIndicator = ' ✓';
                } elseif (in_array($fieldPath, array_column($validationMismatches, 'field'))) {
                    $validationIndicator = ' ⚠️';
                }

                $message .= "• {$fieldLabel}: **{$value}**{$validationIndicator}\n";
            }
        }

        if ($fieldsFound === 0) {
            $message .= $this->lang === 'fr'
                ? "⚠️ _Aucun champ détecté automatiquement_\n"
                : "⚠️ _No fields automatically detected_\n";
        }

        // Add cross-validation summary if validation occurred
        if (!empty($validationMatches) && $validationScore >= 85) {
            $message .= "\n" . ($this->lang === 'fr'
                ? "🔗 _Cohérence avec vos autres documents: **{$validationScore}%**_\n"
                : "🔗 _Consistency with your other documents: **{$validationScore}%**_\n");
        } elseif (!empty($validationMismatches)) {
            $mismatchCount = count($validationMismatches);
            $message .= "\n" . ($this->lang === 'fr'
                ? "⚠️ _{$mismatchCount} différence(s) détectée(s) avec vos documents_\n"
                : "⚠️ _{$mismatchCount} difference(s) detected with your documents_\n");
        }

        $message .= "\n" . ($this->lang === 'fr'
            ? "Ces informations sont-elles correctes ?"
            : "Is this information correct?");

        return $this->createResponse(
            $message,
            [
                [
                    'label' => $this->lang === 'fr' ? '✅ Oui, c\'est correct' : '✅ Yes, correct',
                    'value' => 'confirm'
                ],
                [
                    'label' => $this->lang === 'fr' ? '🔄 Réuploader' : '🔄 Re-upload',
                    'value' => 'reupload'
                ]
            ],
            [
                'document_type' => $docType,
                'extracted_data' => $data,
                'awaiting_confirmation' => true,
                'cross_validation' => $crossValidation,
                'trigger_celebration' => $validationScore >= 85 ? 'document' : null
            ]
        );
    }

    /**
     * Accède aux valeurs imbriquées avec notation pointée (ex: 'invitee.name')
     */
    private function getNestedValue(array $array, string $path) {
        $keys = explode('.', $path);
        $value = $array;
        foreach ($keys as $key) {
            if (!is_array($value) || !isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }
        // Si c'est un tableau avec une clé 'value', extraire la valeur
        if (is_array($value) && isset($value['value'])) {
            return $value['value'];
        }
        return $value;
    }

    // =========================================================================
    // FIN HANDLERS DOCUMENTS CONVERSATIONNELS
    // =========================================================================

    /**
     * Gère l'étape Documents Multi-Upload (ANCIENNE VERSION - conservée pour compatibilité)
     */
    private function handleDocuments(string $input, array $metadata): array {
        // Vérifier si des documents ont été extraits
        if (isset($metadata['documents_extracted']) && $metadata['documents_extracted']) {
            $extractedData = $metadata['extracted_data'] ?? [];
            $validations = $metadata['validations'] ?? [];
            
            // Stocker les données extraites dans la session
            foreach ($extractedData as $type => $data) {
                $this->session->setCollectedField("extracted_{$type}", $data);
            }
            
            // Stocker les validations
            $this->session->setCollectedField('cross_validations', $validations);
            
            // Construire le résumé
            $docSummary = [];
            foreach ($extractedData as $type => $data) {
                $typeInfo = DocumentExtractor::DOCUMENT_TYPES[$type] ?? ['icon' => '📄', 'name' => $type];
                $confidence = $data['overall_confidence'] ?? $data['_metadata']['ocr_confidence'] ?? 0;
                $docSummary[] = "{$typeInfo['icon']} {$typeInfo['name']}: " . round($confidence * 100) . '%';
            }
            
            // Calculer le score de cohérence global
            $coherenceScore = $validations['coherence_score'] ?? 0;
            
            // Construire le message
            $message = getMessage('documents_analysis_complete', $this->lang, [
                'documents_summary' => implode("\n", $docSummary),
                'coherence_score' => round($coherenceScore * 100)
            ]);
            
            // Ajouter les alertes de validation si nécessaire
            $warnings = [];
            $errors = [];
            
            if (!empty($validations['validations'])) {
                foreach ($validations['validations'] as $v) {
                    if ($v['type'] === 'warning') {
                        $warnings[] = "⚠️ " . $v['message'];
                    } elseif ($v['type'] === 'error') {
                        $errors[] = "❌ " . $v['message'];
                    }
                }
            }
            
            if (!empty($warnings)) {
                $message .= "\n\n" . getMessage('documents_validation_warning', $this->lang, [
                    'warnings' => implode("\n", $warnings)
                ]);
            }
            
            if (!empty($errors)) {
                $message .= "\n\n" . getMessage('documents_validation_error', $this->lang, [
                    'errors' => implode("\n", $errors)
                ]);
            }
            
            // Si le passeport a été extrait, le traiter automatiquement
            if (isset($extractedData['passport'])) {
                return $this->processPassportFromDocuments($extractedData['passport'], $message);
            }
            
            return $this->createResponse(
                $message,
                [
                    ['label' => getMessage('documents_confirm', $this->lang), 'value' => 'confirm'],
                    ['label' => getMessage('quick_modify', $this->lang), 'value' => 'modify']
                ],
                ['documents_extracted' => true, 'coherence_score' => $coherenceScore]
            );
        }
        
        // Si l'utilisateur confirme et veut continuer
        if (in_array(strtolower($input), ['confirm', 'oui', 'yes', 'confirmer', 'continuer'])) {
            // Vérifier si on a les données de passeport
            $passportData = $this->session->getCollectedField('extracted_passport');
            if ($passportData) {
                // Passer directement à photo (on saute passport car déjà traité)
                $this->session->setCollectedField('passport_data', $passportData['fields'] ?? $passportData);
                $this->session->markStepCompleted('documents');
                $this->session->markStepCompleted('passport');
                $this->session->setCurrentStep('photo');
                return $this->getStepInitialMessage();
            }
            
            // Sinon passer à l'étape passeport
            $this->advanceToNextStep();
            return $this->getStepInitialMessage();
        }
        
        // Si l'utilisateur veut modifier
        if (in_array(strtolower($input), ['modify', 'non', 'no', 'modifier'])) {
            return $this->createResponse(
                getMessage('documents_intro', $this->lang),
                [
                    ['label' => getMessage('documents_upload_button', $this->lang), 'value' => 'upload_documents', 'action' => 'show_uploader']
                ],
                ['input_type' => 'multi_document', 'show_uploader' => true]
            );
        }
        
        // Si on reçoit un message pour skip et passer au passeport seul
        if (in_array(strtolower($input), ['skip', 'passer', 'passeport_seul', 'passport_only'])) {
            $this->advanceToNextStep();
            return $this->getStepInitialMessage();
        }
        
        // Par défaut, afficher l'interface d'upload
        return $this->createResponse(
            getMessage('documents_intro', $this->lang),
            [
                ['label' => getMessage('documents_upload_button', $this->lang), 'value' => 'upload_documents', 'action' => 'show_uploader'],
                ['label' => $this->lang === 'fr' ? 'Scanner passeport seul →' : 'Scan passport only →', 'value' => 'passport_only']
            ],
            ['input_type' => 'multi_document', 'show_uploader' => true]
        );
    }
    
    /**
     * Traite les données du passeport depuis l'extraction multi-documents
     */
    private function processPassportFromDocuments(array $passportData, string $introMessage): array {
        $fields = $passportData['fields'] ?? $passportData;
        $this->session->setCollectedField('passport_data', $fields);
        
        // Détection du type de passeport depuis le MRZ
        $passportType = 'ORDINAIRE';
        if (!empty($passportData['mrz']['line1'])) {
            $passportType = detectPassportTypeFromMRZ($passportData['mrz']['line1']);
        }
        
        $this->session->setCollectedField('passport_type', $passportType);
        
        // Déterminer la catégorie et le type de workflow
        $workflowCategory = getWorkflowCategory($passportType);
        $workflowType = getWorkflowType($passportType);
        
        $this->session->setWorkflowCategory($workflowCategory);
        $this->session->setCollectedField('workflow_type', $workflowType);
        
        $typeInfo = getPassportTypeInfo($passportType, $this->lang);
        $processingInfo = $this->getProcessingInfo();
        
        // Construire le message avec les infos passeport
        $message = $introMessage . "\n\n";
        
        if ($workflowType === WORKFLOW_TYPE_PRIORITY) {
            $message .= $this->buildPriorityPassportMessage($typeInfo, $fields, $processingInfo);
        } else {
            $message .= $this->buildStandardPassportMessage($typeInfo, $fields, $processingInfo);
        }
        
        return $this->createResponse(
            $message,
            getConfirmModifyQuickActions($this->lang),
            [
                'passport_detected' => true,
                'passport_type' => $passportType,
                'workflow_category' => $workflowCategory,
                'workflow_type' => $workflowType,
                'is_free' => isPassportFree($passportType),
                'is_priority' => isPassportPriority($passportType),
                'requires_verbal_note' => requiresVerbalNote($passportType),
                'from_multi_upload' => true
            ]
        );
    }
    
    /**
     * Gère l'étape Passeport
     */
    private function handlePassport(string $input, array $metadata): array {
        // Support nouveau format: document_uploaded + extracted_data (cohérent avec autres handlers)
        if (!empty($metadata['document_uploaded']) && !empty($metadata['extracted_data'])) {
            return $this->processPassportData($metadata['extracted_data']);
        }

        // Support ancien format: ocr_data (legacy)
        if (isset($metadata['ocr_data']) && is_array($metadata['ocr_data'])) {
            return $this->processPassportData($metadata['ocr_data']);
        }
        
        if (in_array(strtolower($input), ['confirm', 'oui', 'yes', 'confirmer'])) {
            $this->advanceToNextStep();
            return $this->getStepInitialMessage();
        }
        
        if (in_array(strtolower($input), ['modify', 'non', 'no', 'modifier'])) {
            return $this->createResponse(
                getMessage('passport_scan_request', $this->lang),
                [],
                ['input_type' => 'file', 'file_type' => 'passport']
            );
        }
        
        return $this->createResponse(
            getMessage('passport_scan_request', $this->lang),
            [],
            ['input_type' => 'file', 'file_type' => 'passport']
        );
    }
    
    /**
     * Traite les données du passeport après OCR
     * ENHANCED: Proactive vaccination warning based on nationality
     */
    public function processPassportData(array $ocrData): array {
        $passportData = $ocrData['fields'] ?? $ocrData;
        $this->session->setCollectedField('passport_data', $passportData);

        // Détection du type de passeport
        $passportType = 'ORDINAIRE';
        if (!empty($ocrData['mrz']['line1'])) {
            $passportType = detectPassportTypeFromMRZ($ocrData['mrz']['line1']);
        }

        $this->session->setCollectedField('passport_type', $passportType);

        // Déterminer la catégorie et le type de workflow
        $workflowCategory = getWorkflowCategory($passportType);
        $workflowType = getWorkflowType($passportType);

        $this->session->setWorkflowCategory($workflowCategory);
        $this->session->setCollectedField('workflow_type', $workflowType);

        // Dispatch webhook: passport scanned
        $this->dispatchWebhook(WebhookDispatcher::EVENT_PASSPORT_SCANNED, [
            'passport_type' => $passportType,
            'workflow_category' => $workflowCategory,
            'workflow_type' => $workflowType,
            'surname' => $passportData['surname']['value'] ?? null,
            'passport_number' => $passportData['passport_number']['value'] ?? null,
            'nationality' => $passportData['nationality']['value'] ?? null
        ]);

        $typeInfo = getPassportTypeInfo($passportType, $this->lang);
        $processingInfo = $this->getProcessingInfo();

        // Message différent selon PRIORITY ou STANDARD
        if ($workflowType === WORKFLOW_TYPE_PRIORITY) {
            $message = $this->buildPriorityPassportMessage($typeInfo, $passportData, $processingInfo);
        } else {
            $message = $this->buildStandardPassportMessage($typeInfo, $passportData, $processingInfo);
        }

        // =========================================================================
        // FEATURE 3: Proactive Vaccination Warning
        // =========================================================================
        $vaccinationWarning = null;
        $nationality = $passportData['nationality']['value'] ?? null;

        if ($nationality) {
            // Convert nationality to ISO 2-letter code for VaccinationRequirements
            $nationalityCode = $this->normalizeNationality($nationality);
            if ($nationalityCode) {
                // Convert 3-letter to 2-letter code for VaccinationRequirements
                $iso2Code = $this->convertIso3ToIso2($nationalityCode);

                $vaccinationService = new VaccinationRequirements();
                if ($vaccinationService->hasRequiredVaccinations($iso2Code)) {
                    $nationalityName = $passportData['nationality']['value'];
                    $vaccinationWarning = $vaccinationService->getProactiveWarning($iso2Code, $nationalityName, $this->lang);

                    // Store warning in session
                    $this->session->setCollectedField('vaccination_warning', $vaccinationWarning);

                    // Append warning to message
                    $message .= "\n\n" . $vaccinationWarning['message'][$this->lang];
                }
            }
        }
        // =========================================================================
        // END FEATURE 3
        // =========================================================================

        return $this->createResponse(
            $message,
            getConfirmModifyQuickActions($this->lang),
            [
                'passport_detected' => true,
                'passport_type' => $passportType,
                'workflow_category' => $workflowCategory,
                'workflow_type' => $workflowType,
                'is_free' => isPassportFree($passportType),
                'is_priority' => isPassportPriority($passportType),
                'requires_verbal_note' => requiresVerbalNote($passportType),
                'vaccination_warning' => $vaccinationWarning
            ]
        );
    }

    /**
     * Converts ISO 3166-1 alpha-3 country code to alpha-2
     */
    private function convertIso3ToIso2(string $iso3): ?string {
        $mapping = [
            'ETH' => 'ET', 'KEN' => 'KE', 'DJI' => 'DJ', 'TZA' => 'TZ',
            'UGA' => 'UG', 'SSD' => 'SS', 'SOM' => 'SO', 'CIV' => 'CI',
            'NGA' => 'NG', 'GHA' => 'GH', 'CMR' => 'CM', 'SEN' => 'SN',
            'MLI' => 'ML', 'BFA' => 'BF', 'NER' => 'NE', 'TCD' => 'TD',
            'CAF' => 'CF', 'COG' => 'CG', 'COD' => 'CD', 'GAB' => 'GA',
            'GNQ' => 'GQ', 'BEN' => 'BJ', 'TGO' => 'TG', 'GIN' => 'GN',
            'SLE' => 'SL', 'LBR' => 'LR', 'GMB' => 'GM', 'GNB' => 'GW',
            'MRT' => 'MR', 'RWA' => 'RW', 'BDI' => 'BI', 'ERI' => 'ER',
            'SDN' => 'SD', 'AGO' => 'AO', 'BRA' => 'BR', 'COL' => 'CO',
            'PER' => 'PE', 'VEN' => 'VE', 'ECU' => 'EC', 'BOL' => 'BO',
            'GUY' => 'GY', 'SUR' => 'SR', 'GUF' => 'GF', 'PRY' => 'PY',
            'TTO' => 'TT', 'PAN' => 'PA', 'FRA' => 'FR', 'USA' => 'US',
            'GBR' => 'GB', 'DEU' => 'DE', 'CHN' => 'CN', 'IND' => 'IN',
            'ZAF' => 'ZA', 'EGY' => 'EG', 'MAR' => 'MA', 'DZA' => 'DZ',
            'TUN' => 'TN'
        ];

        return $mapping[strtoupper($iso3)] ?? null;
    }
    
    /**
     * Construit le message pour un passeport PRIORITY (diplomatique, etc.)
     */
    private function buildPriorityPassportMessage(array $typeInfo, array $passportData, array $processingInfo): string {
        if ($this->lang === 'fr') {
            $message = "✅ **Passeport détecté: {$typeInfo['label']}**\n\n";
            $message .= "📋 **Informations extraites:**\n";
            $message .= "• Nom: " . ($passportData['surname']['value'] ?? 'N/A') . "\n";
            $message .= "• Prénoms: " . ($passportData['given_names']['value'] ?? 'N/A') . "\n";
            $message .= "• N° Passeport: " . ($passportData['passport_number']['value'] ?? 'N/A') . "\n";
            $message .= "• Expiration: " . ($passportData['date_of_expiry']['value'] ?? 'N/A') . "\n\n";
            $message .= "🌟 **Workflow PRIORITY activé:**\n";
            $message .= "• ✨ **GRATUIT** (pas de frais de visa)\n";
            $message .= "• ⚡ Traitement prioritaire: **{$processingInfo['processing_time']}**\n";
            
            if ($processingInfo['requires_verbal_note']) {
                $message .= "• 📝 Note verbale **OBLIGATOIRE**\n";
            }
            
            $message .= "\n✔️ Veuillez confirmer ou modifier ces informations.";
        } else {
            $message = "✅ **Passport detected: {$typeInfo['label']}**\n\n";
            $message .= "📋 **Extracted information:**\n";
            $message .= "• Surname: " . ($passportData['surname']['value'] ?? 'N/A') . "\n";
            $message .= "• Given names: " . ($passportData['given_names']['value'] ?? 'N/A') . "\n";
            $message .= "• Passport No: " . ($passportData['passport_number']['value'] ?? 'N/A') . "\n";
            $message .= "• Expiry: " . ($passportData['date_of_expiry']['value'] ?? 'N/A') . "\n\n";
            $message .= "🌟 **PRIORITY Workflow activated:**\n";
            $message .= "• ✨ **FREE** (no visa fees)\n";
            $message .= "• ⚡ Priority processing: **{$processingInfo['processing_time']}**\n";
            
            if ($processingInfo['requires_verbal_note']) {
                $message .= "• 📝 Verbal note **REQUIRED**\n";
            }
            
            $message .= "\n✔️ Please confirm or modify this information.";
        }
        
        return $message;
    }
    
    /**
     * Construit le message pour un passeport STANDARD (ordinaire, etc.)
     */
    private function buildStandardPassportMessage(array $typeInfo, array $passportData, array $processingInfo): string {
        $passportType = $this->session->getCollectedField('passport_type') ?? 'ORDINAIRE';
        $fees = calculateFees($passportType, 'COURT_SEJOUR', 'Unique');
        
        if ($this->lang === 'fr') {
            $message = "✅ **Passeport détecté: {$typeInfo['label']}**\n\n";
            $message .= "📋 **Informations extraites:**\n";
            $message .= "• Nom: " . ($passportData['surname']['value'] ?? 'N/A') . "\n";
            $message .= "• Prénoms: " . ($passportData['given_names']['value'] ?? 'N/A') . "\n";
            $message .= "• N° Passeport: " . ($passportData['passport_number']['value'] ?? 'N/A') . "\n";
            $message .= "• Expiration: " . ($passportData['date_of_expiry']['value'] ?? 'N/A') . "\n\n";
            $message .= "📊 **Workflow STANDARD:**\n";
            $message .= "• 💰 Frais de base: **" . formatPrice($fees['baseFee']) . "**\n";
            $message .= "• ⏱️ Délai: **{$processingInfo['processing_time']}**\n";
            
            if ($processingInfo['express_available']) {
                $expressFees = calculateFees($passportType, 'COURT_SEJOUR', 'Unique', true);
                $message .= "• ⚡ Option Express (24-48h): **+" . formatPrice($expressFees['expressFee']) . "**\n";
            }
            
            $message .= "\n✔️ Veuillez confirmer ou modifier ces informations.";
        } else {
            $message = "✅ **Passport detected: {$typeInfo['label']}**\n\n";
            $message .= "📋 **Extracted information:**\n";
            $message .= "• Surname: " . ($passportData['surname']['value'] ?? 'N/A') . "\n";
            $message .= "• Given names: " . ($passportData['given_names']['value'] ?? 'N/A') . "\n";
            $message .= "• Passport No: " . ($passportData['passport_number']['value'] ?? 'N/A') . "\n";
            $message .= "• Expiry: " . ($passportData['date_of_expiry']['value'] ?? 'N/A') . "\n\n";
            $message .= "📊 **STANDARD Workflow:**\n";
            $message .= "• 💰 Base fees: **" . formatPrice($fees['baseFee']) . "**\n";
            $message .= "• ⏱️ Processing: **{$processingInfo['processing_time']}**\n";
            
            if ($processingInfo['express_available']) {
                $expressFees = calculateFees($passportType, 'COURT_SEJOUR', 'Unique', true);
                $message .= "• ⚡ Express option (24-48h): **+" . formatPrice($expressFees['expressFee']) . "**\n";
            }
            
            $message .= "\n✔️ Please confirm or modify this information.";
        }
        
        return $message;
    }
    
    /**
     * Gère l'étape Éligibilité
     * Vérifie la cohérence des documents uploadés et pose la question de durée de séjour
     */
    private function handleEligibility(string $input, array $metadata): array {
        $subStep = $this->session->getCollectedField('eligibility_substep') ?? 'documents_check';

        // Première entrée: vérifier les documents uploadés et afficher le récapitulatif
        if ($subStep === 'documents_check') {
            $documents = $this->getUploadedDocumentsStatus();
            $message = $this->buildDocumentsCheckMessage($documents);

            // Marquer la vaccination comme présente si le document a été uploadé
            if (!empty($documents['vaccination'])) {
                $this->session->setCollectedField('has_vaccination', true);
            }

            // NOUVEAU: Validation de cohérence cross-documents
            $coherenceResult = $this->performCoherenceValidation($documents);
            $this->session->setCollectedField('coherence_result', $coherenceResult);

            // Si erreurs bloquantes, afficher et bloquer
            if ($coherenceResult['is_blocked']) {
                $this->session->block('coherence_blocking_error', [
                    'issues' => $coherenceResult['issues'],
                    'documents_involved' => array_keys($documents)
                ]);

                $this->dispatchWebhook(WebhookDispatcher::EVENT_SESSION_ABANDONED, [
                    'reason' => 'coherence_blocking_error',
                    'step' => 'eligibility',
                    'issues' => $coherenceResult['issues']
                ]);

                return $this->createResponse(
                    $this->buildCoherenceBlockedMessage($coherenceResult),
                    [],
                    ['blocking' => true, 'coherence_issues' => $coherenceResult['issues']]
                );
            }

            // Si avertissements, afficher et demander confirmation
            if ($coherenceResult['has_warnings']) {
                $this->session->setCollectedField('eligibility_substep', 'coherence_confirm');

                return $this->createResponse(
                    $message . "\n\n" . $this->buildCoherenceWarningsMessage($coherenceResult),
                    [
                        ['label' => $this->lang === 'fr' ? '✅ J\'ai compris, continuer' : '✅ I understand, continue', 'value' => 'coherence_accept'],
                        ['label' => $this->lang === 'fr' ? '🔄 Corriger mes documents' : '🔄 Correct my documents', 'value' => 'coherence_correct']
                    ],
                    ['coherence_warnings' => true, 'documents_status' => $documents, 'coherence_result' => $coherenceResult]
                );
            }

            $this->session->setCollectedField('eligibility_substep', 'duration');

            // Ajouter la question de durée
            $message .= "\n\n" . ($this->lang === 'fr'
                ? "📅 **Durée du séjour**\n\nCe service traite les demandes de visa pour les séjours de 3 mois ou plus.\n\nVotre séjour en Côte d'Ivoire sera-t-il de 3 mois ou plus ?"
                : "📅 **Stay Duration**\n\nThis service handles visa applications for stays of 3 months or more.\n\nWill your stay in Côte d'Ivoire be 3 months or more?");

            return $this->createResponse(
                $message,
                [
                    ['label' => $this->lang === 'fr' ? '✅ 3 mois ou plus' : '✅ 3 months or more', 'value' => 'duration_yes'],
                    ['label' => $this->lang === 'fr' ? '❌ Moins de 3 mois' : '❌ Less than 3 months', 'value' => 'duration_no']
                ],
                ['eligibility_question' => 'duration', 'documents_status' => $documents]
            );
        }

        // NOUVEAU: Gestion de la confirmation des avertissements de cohérence
        if ($subStep === 'coherence_confirm') {
            if (strtolower($input) === 'coherence_correct' || strtolower($input) === 'corriger' || strtolower($input) === 'correct') {
                // L'utilisateur veut corriger - retour à l'étape appropriée
                $coherenceResult = $this->session->getCollectedField('coherence_result') ?? [];
                $stepToCorrect = $this->determineStepToCorrect($coherenceResult);

                $this->session->setCollectedField('eligibility_substep', null);
                return $this->navigateToStep($stepToCorrect);
            }

            // L'utilisateur accepte les avertissements - continuer
            $this->session->setCollectedField('coherence_warnings_acknowledged', true);
            $this->session->setCollectedField('eligibility_substep', 'duration');

            // Ajouter la question de durée
            return $this->createResponse(
                $this->lang === 'fr'
                    ? "✅ Noté ! J'ai pris en compte vos observations.\n\n📅 **Durée du séjour**\n\nCe service traite les demandes de visa pour les séjours de 3 mois ou plus.\n\nVotre séjour en Côte d'Ivoire sera-t-il de 3 mois ou plus ?"
                    : "✅ Noted! I've taken your observations into account.\n\n📅 **Stay Duration**\n\nThis service handles visa applications for stays of 3 months or more.\n\nWill your stay in Côte d'Ivoire be 3 months or more?",
                [
                    ['label' => $this->lang === 'fr' ? '✅ 3 mois ou plus' : '✅ 3 months or more', 'value' => 'duration_yes'],
                    ['label' => $this->lang === 'fr' ? '❌ Moins de 3 mois' : '❌ Less than 3 months', 'value' => 'duration_no']
                ],
                ['eligibility_question' => 'duration']
            );
        }

        if ($subStep === 'duration') {
            $sufficientDuration = in_array(strtolower($input), ['yes', 'oui', 'duration_yes', 'true', '1', '3 mois ou plus', '3 months or more']);
            $this->session->setCollectedField('stay_duration', $sufficientDuration ? '3months_or_more' : 'less_than_3months');

            if (!$sufficientDuration) {
                $this->session->block('insufficient_duration', [
                    'duration_selected' => 'less_than_3months',
                    'minimum_required' => '3 months'
                ]);

                // Dispatch webhook: session abandoned (short stay)
                $this->dispatchWebhook(WebhookDispatcher::EVENT_SESSION_ABANDONED, [
                    'reason' => 'stay_less_than_3_months',
                    'step' => 'eligibility'
                ]);

                return $this->createResponse(
                    $this->lang === 'fr'
                        ? "❌ Ce service traite uniquement les demandes de visa pour des séjours de 3 mois ou plus.\n\n📞 Pour les séjours de moins de 3 mois, veuillez contacter directement l'ambassade :\n• Email: contact@ambaci-addis.gov.ci\n• Tél: +251 11 xxx xxxx"
                        : "❌ This service only handles visa applications for stays of 3 months or more.\n\n📞 For shorter stays, please contact the embassy directly:\n• Email: contact@ambaci-addis.gov.ci\n• Tel: +251 11 xxx xxxx",
                    [],
                    ['blocking' => true]
                );
            }

            // Eligible - advance to photo
            $this->session->setCollectedField('eligibility_substep', null);
            $this->advanceToNextStep();

            return $this->createResponse(
                $this->lang === 'fr'
                    ? "✅ Parfait ! Vous êtes éligible pour continuer votre demande de visa.\n\n" . getMessage('photo_request', $this->lang)
                    : "✅ Perfect! You are eligible to continue your visa application.\n\n" . getMessage('photo_request', $this->lang),
                [],
                ['input_type' => 'file', 'file_type' => 'photo']
            );
        }

        // Default: restart eligibility
        $this->session->setCollectedField('eligibility_substep', 'documents_check');
        return $this->getStepInitialMessage();
    }

    /**
     * Récupère le statut des documents uploadés
     */
    private function getUploadedDocumentsStatus(): array {
        return [
            'passport' => $this->session->getCollectedField('passport_data'),
            'ticket' => $this->session->getCollectedField('extracted_ticket'),
            'hotel' => $this->session->getCollectedField('extracted_hotel'),
            'vaccination' => $this->session->getCollectedField('extracted_vaccination'),
            'invitation' => $this->session->getCollectedField('extracted_invitation')
        ];
    }

    /**
     * Construit le message de vérification des documents
     */
    private function buildDocumentsCheckMessage(array $documents): string {
        $icons = [
            'passport' => '🛂',
            'ticket' => '✈️',
            'hotel' => '🏨',
            'vaccination' => '💉',
            'invitation' => '📄'
        ];

        $labels = [
            'passport' => $this->lang === 'fr' ? 'Passeport' : 'Passport',
            'ticket' => $this->lang === 'fr' ? 'Billet d\'avion' : 'Flight ticket',
            'hotel' => $this->lang === 'fr' ? 'Réservation hôtel' : 'Hotel booking',
            'vaccination' => $this->lang === 'fr' ? 'Carnet de vaccination' : 'Vaccination card',
            'invitation' => $this->lang === 'fr' ? 'Lettre d\'invitation' : 'Invitation letter'
        ];

        $message = $this->lang === 'fr'
            ? "📋 **Vérification des documents**\n\n"
            : "📋 **Documents verification**\n\n";

        $allValid = true;
        $totalConfidence = 0;
        $docCount = 0;

        foreach ($documents as $type => $data) {
            $icon = $icons[$type] ?? '📄';
            $label = $labels[$type] ?? ucfirst($type);

            if (!empty($data)) {
                $confidence = $data['confidence'] ?? $data['_metadata']['confidence'] ?? $data['overall_confidence'] ?? 0.85;
                $totalConfidence += $confidence;
                $docCount++;
                $confPercent = round($confidence * 100);
                $message .= "• {$icon} {$label}: ✅ ({$confPercent}%)\n";
            } else {
                $message .= "• {$icon} {$label}: ⏳ " . ($this->lang === 'fr' ? 'Non fourni' : 'Not provided') . "\n";
                // L'invitation n'est pas obligatoire
                if ($type !== 'invitation') {
                    // Note: on ne bloque pas car les documents peuvent être optionnels
                }
            }
        }

        // Calculer le score global
        $avgConfidence = $docCount > 0 ? round(($totalConfidence / $docCount) * 100) : 0;

        $message .= "\n📊 " . ($this->lang === 'fr' ? 'Score de confiance moyen' : 'Average confidence score') . ": **{$avgConfidence}%**";

        return $message;
    }

    /**
     * Effectue la validation de cohérence cross-documents
     * Utilise DocumentCoherenceValidator pour détecter les incohérences
     */
    private function performCoherenceValidation(array $documents): array {
        // Construire le dossier au format attendu par DocumentCoherenceValidator
        $dossier = [];

        // Passport
        if (!empty($documents['passport'])) {
            $dossier['passport'] = $documents['passport'];
        }

        // Ticket - normaliser les clés (supporte structure fields.X.value et structure plate)
        if (!empty($documents['ticket'])) {
            $ticket = $documents['ticket'];
            $fields = $ticket['fields'] ?? $ticket;

            // Helper pour extraire la valeur (supporte fields.X.value et X direct)
            $getValue = function($key) use ($ticket, $fields) {
                // D'abord essayer fields.key.value
                if (isset($fields[$key]['value'])) {
                    return $fields[$key]['value'];
                }
                // Ensuite essayer directement dans ticket
                if (isset($ticket[$key])) {
                    return $ticket[$key];
                }
                return null;
            };

            $dossier['ticket'] = [
                'departure_date' => $getValue('departure_date') ?? $getValue('outbound_date'),
                'return_date' => $getValue('return_date') ?? $getValue('inbound_date'),
                'return_flight_number' => $getValue('return_flight_number') ?? $getValue('inbound_flight'),
                'passenger_name' => $getValue('passenger_name') ?? $getValue('full_name'),
                'arrival_city' => $getValue('arrival_city') ?? $getValue('destination') ?? 'Abidjan',
                'is_round_trip' => $getValue('is_round_trip') ?? false
            ];
        }

        // Hotel - normaliser les clés
        if (!empty($documents['hotel'])) {
            $hotel = $documents['hotel'];
            $dossier['hotel'] = [
                'check_in_date' => $hotel['check_in_date'] ?? $hotel['check_in'] ?? null,
                'check_out_date' => $hotel['check_out_date'] ?? $hotel['check_out'] ?? null,
                'guest_name' => $hotel['guest_name'] ?? $hotel['holder_name'] ?? null,
                'hotel_city' => $hotel['hotel_city'] ?? $hotel['city'] ?? null,
                'nights' => $hotel['nights'] ?? null
            ];
        }

        // Vaccination
        if (!empty($documents['vaccination'])) {
            $vacc = $documents['vaccination'];
            $dossier['vaccination'] = [
                'holder_name' => $vacc['holder_name'] ?? $vacc['patient_name'] ?? null,
                'vaccination_date' => $vacc['vaccination_date'] ?? $vacc['date'] ?? null,
                'valid' => $vacc['valid'] ?? true
            ];
        }

        // Invitation
        if (!empty($documents['invitation'])) {
            $inv = $documents['invitation'];
            $dossier['invitation'] = [
                'invitee' => [
                    'name' => $inv['invitee_name'] ?? $inv['invitee']['name'] ?? null
                ],
                'dates' => [
                    'from' => $inv['visit_from'] ?? $inv['dates']['from'] ?? $inv['arrival_date'] ?? null,
                    'to' => $inv['visit_to'] ?? $inv['dates']['to'] ?? $inv['departure_date'] ?? null
                ],
                'accommodation_provided' => $inv['accommodation_provided'] ?? false,
                'purpose' => $inv['purpose'] ?? $inv['visit_purpose'] ?? null,
                'duration_days' => $inv['duration_days'] ?? $inv['stay_duration'] ?? null
            ];
        }

        // Si pas assez de documents, retourner résultat vide
        if (count($dossier) < 2) {
            return [
                'is_coherent' => true,
                'is_blocked' => false,
                'has_warnings' => false,
                'issues' => [],
                'summary' => ['documents_count' => count($dossier)]
            ];
        }

        // Appeler DocumentCoherenceValidator
        $validator = new \VisaChatbot\Services\DocumentCoherenceValidator();
        return $validator->validateDossier($dossier);
    }

    /**
     * Construit le message pour les erreurs bloquantes de cohérence
     */
    private function buildCoherenceBlockedMessage(array $coherenceResult): string {
        $issues = array_filter($coherenceResult['issues'], fn($i) => $i['severity'] === 'error');

        $message = $this->lang === 'fr'
            ? "❌ **Problème détecté dans votre dossier**\n\nJe suis désolée, mais j'ai trouvé des incohérences critiques qui empêchent de continuer :\n\n"
            : "❌ **Issue detected in your application**\n\nI'm sorry, but I found critical inconsistencies that prevent us from continuing:\n\n";

        foreach ($issues as $issue) {
            $issueMessage = $issue['message_' . $this->lang] ?? $issue['message'] ?? '';
            $message .= "• " . $issueMessage . "\n";

            // Ajouter les détails si disponibles
            if (!empty($issue['detail'])) {
                $message .= "  _" . $issue['detail'] . "_\n";
            }
        }

        $message .= "\n" . ($this->lang === 'fr'
            ? "📞 Veuillez contacter l'ambassade pour résoudre ce problème ou corriger vos documents."
            : "📞 Please contact the embassy to resolve this issue or correct your documents.");

        return $message;
    }

    /**
     * Construit le message pour les avertissements de cohérence
     */
    private function buildCoherenceWarningsMessage(array $coherenceResult): string {
        $warnings = array_filter($coherenceResult['issues'], fn($i) => $i['severity'] === 'warning');
        $infos = array_filter($coherenceResult['issues'], fn($i) => $i['severity'] === 'info');

        $message = $this->lang === 'fr'
            ? "⚠️ **Points d'attention**\n\nJ'ai remarqué quelques éléments à vérifier :\n\n"
            : "⚠️ **Points of attention**\n\nI noticed a few things to verify:\n\n";

        // Afficher les warnings
        foreach ($warnings as $issue) {
            $issueMessage = $issue['message_' . $this->lang] ?? $issue['message'] ?? '';
            $message .= "⚠️ " . $issueMessage . "\n";

            if (!empty($issue['detail'])) {
                $message .= "   _" . $issue['detail'] . "_\n";
            }
        }

        // Afficher les infos importantes
        foreach ($infos as $issue) {
            $issueMessage = $issue['message_' . $this->lang] ?? $issue['message'] ?? '';
            $message .= "ℹ️ " . $issueMessage . "\n";
        }

        $message .= "\n" . ($this->lang === 'fr'
            ? "Ces points ne bloquent pas votre demande, mais je préfère vous en informer. Souhaitez-vous continuer ou corriger vos documents ?"
            : "These points won't block your application, but I prefer to inform you. Would you like to continue or correct your documents?");

        return $message;
    }

    /**
     * Détermine l'étape à laquelle retourner pour corriger les erreurs
     */
    private function determineStepToCorrect(array $coherenceResult): string {
        if (empty($coherenceResult['issues'])) {
            return 'passport';
        }

        // Identifier le premier document problématique
        $issueTypes = array_column($coherenceResult['issues'], 'type');

        if (in_array('RETURN_FLIGHT_MISSING', $issueTypes) || in_array('DATE_MISMATCH', $issueTypes)) {
            return 'ticket';
        }

        if (in_array('ACCOMMODATION_GAP', $issueTypes)) {
            return 'hotel';
        }

        if (in_array('VACCINATION_MISSING', $issueTypes) || in_array('VACCINATION_EXPIRED', $issueTypes)) {
            return 'vaccination';
        }

        if (in_array('PASSPORT_EXPIRY', $issueTypes) || in_array('NAME_MISMATCH', $issueTypes)) {
            return 'passport';
        }

        // Par défaut, retour au passeport
        return 'passport';
    }

    /**
     * Gère l'étape Photo
     */
    private function handlePhoto(string $input, array $metadata): array {
        if (isset($metadata['file_uploaded']) && $metadata['file_uploaded']) {
            $this->session->setCollectedField('photo_path', $metadata['file_path'] ?? 'uploaded');
            $this->advanceToNextStep();
            return $this->getStepInitialMessage();
        }
        
        return $this->createResponse(
            getMessage('photo_request', $this->lang),
            [],
            ['input_type' => 'file', 'file_type' => 'photo']
        );
    }
    
    /**
     * Gère l'étape Contact
     */
    private function handleContact(string $input, array $metadata): array {
        $subStep = $this->session->getCollectedField('contact_substep') ?? 'email';
        
        if ($subStep === 'email') {
            $email = trim($input);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->createResponse(
                    $this->lang === 'fr' 
                        ? "Cette adresse email semble invalide. Veuillez réessayer."
                        : "This email address seems invalid. Please try again.",
                    [],
                    ['input_type' => 'email']
                );
            }
            
            $this->session->setCollectedField('email', $email);
            $this->session->setCollectedField('contact_substep', 'phone');
            
            return $this->createResponse(
                getMessage('contact_phone_request', $this->lang),
                [],
                ['input_type' => 'phone']
            );
        }
        
        if ($subStep === 'phone') {
            $phone = trim($input);
            $this->session->setCollectedField('phone', $phone);
            $this->session->setCollectedField('contact_substep', 'whatsapp');
            
            return $this->createResponse(
                getMessage('contact_whatsapp', $this->lang),
                getYesNoQuickActions($this->lang)
            );
        }
        
        if ($subStep === 'whatsapp') {
            $hasWhatsapp = in_array(strtolower($input), ['yes', 'oui', 'true', '1']);
            $this->session->setCollectedField('whatsapp', $hasWhatsapp);
            $this->session->setCollectedField('contact_substep', null);
            
            $this->advanceToNextStep();
            return $this->getStepInitialMessage();
        }
        
        return $this->createResponse(
            getMessage('contact_request', $this->lang),
            [],
            ['input_type' => 'email']
        );
    }
    
    /**
     * Gère l'étape Voyage
     * ENHANCED: Pre-fill dates from ticket OCR
     */
    private function handleTrip(string $input, array $metadata): array {
        $subStep = $this->session->getCollectedField('trip_substep') ?? 'arrival';
        $workflowCategory = $this->session->getWorkflowCategory() ?? WORKFLOW_ORDINAIRE;

        // =========================================================================
        // FEATURE 2: Pre-fill dates from ticket OCR
        // =========================================================================
        $ticketData = $this->session->getCollectedField('extracted_ticket');
        $datesPrefilled = $this->session->getCollectedField('trip_dates_prefilled');

        // Check if we have ticket data and haven't shown prefill yet
        if ($ticketData && !$datesPrefilled && $subStep === 'arrival') {
            $departureDate = $ticketData['departure_date'] ?? $ticketData['departure_date']['value'] ?? null;
            $returnDate = $ticketData['arrival_date'] ?? $ticketData['return_date'] ?? $ticketData['return_date']['value'] ?? null;
            $flightNumber = $ticketData['flight_number'] ?? $ticketData['flight_number']['value'] ?? null;

            if ($departureDate || $returnDate) {
                $this->session->setCollectedField('trip_dates_prefilled', true);

                // Build prefill message
                $prefillLines = [];
                if ($departureDate) $prefillLines[] = "📅 " . ($this->lang === 'fr' ? 'Départ' : 'Departure') . ": **{$departureDate}**";
                if ($returnDate) $prefillLines[] = "📅 " . ($this->lang === 'fr' ? 'Retour' : 'Return') . ": **{$returnDate}**";
                if ($flightNumber) $prefillLines[] = "✈️ " . ($this->lang === 'fr' ? 'Vol' : 'Flight') . ": **{$flightNumber}**";

                $prefillMsg = implode("\n", $prefillLines);

                return $this->createResponse(
                    $this->lang === 'fr'
                        ? "✈️ D'après votre billet, j'ai détecté:\n\n{$prefillMsg}\n\nCes informations sont-elles correctes ?"
                        : "✈️ From your ticket, I detected:\n\n{$prefillMsg}\n\nIs this information correct?",
                    [
                        ['label' => $this->lang === 'fr' ? '✅ Oui, c\'est correct' : '✅ Yes, correct', 'value' => 'prefill_confirm'],
                        ['label' => $this->lang === 'fr' ? '🔄 Non, modifier' : '🔄 No, modify', 'value' => 'prefill_modify']
                    ],
                    [
                        'prefill_data' => [
                            'departure_date' => $departureDate,
                            'return_date' => $returnDate,
                            'flight_number' => $flightNumber
                        ]
                    ]
                );
            }
        }

        // Handle prefill confirmation
        if ($input === 'prefill_confirm' && $ticketData) {
            $departureDate = $ticketData['departure_date'] ?? $ticketData['departure_date']['value'] ?? null;
            $returnDate = $ticketData['arrival_date'] ?? $ticketData['return_date'] ?? $ticketData['return_date']['value'] ?? null;

            if ($departureDate) $this->session->setCollectedField('arrival_date', $departureDate);
            if ($returnDate) $this->session->setCollectedField('departure_date', $returnDate);

            // Skip to purpose
            $this->session->setCollectedField('trip_substep', 'purpose');
            return $this->createResponse(
                getMessage('trip_purpose_request', $this->lang),
                getTripPurposeQuickActions($this->lang)
            );
        }

        if ($input === 'prefill_modify') {
            // Continue with manual date entry
            $this->session->setCollectedField('trip_substep', 'arrival');
            return $this->createResponse(
                getMessage('trip_dates_request', $this->lang),
                [],
                ['input_type' => 'date']
            );
        }
        // =========================================================================
        // END FEATURE 2 - Continue with normal trip handling
        // =========================================================================

        switch ($subStep) {
            case 'arrival':
                $this->session->setCollectedField('arrival_date', $input);
                $this->session->setCollectedField('trip_substep', 'departure');
                return $this->createResponse(
                    getMessage('trip_departure_request', $this->lang),
                    [],
                    ['input_type' => 'date']
                );

            case 'departure':
                $this->session->setCollectedField('departure_date', $input);
                $this->session->setCollectedField('trip_substep', 'purpose');
                return $this->createResponse(
                    getMessage('trip_purpose_request', $this->lang),
                    getTripPurposeQuickActions($this->lang)
                );
                
            case 'purpose':
                $this->session->setCollectedField('trip_purpose', strtoupper($input));
                $this->session->setCollectedField('trip_substep', 'visa_type');
                return $this->createResponse(
                    getMessage('trip_visa_type_request', $this->lang),
                    getVisaTypeQuickActions($this->lang)
                );
                
            case 'visa_type':
                $this->session->setCollectedField('visa_type', strtoupper($input));
                $this->session->setCollectedField('trip_substep', 'entries');
                return $this->createResponse(
                    getMessage('trip_entries_request', $this->lang),
                    getEntryQuickActions($this->lang)
                );
                
            case 'entries':
                $this->session->setCollectedField('visa_entries', $input);
                
                // Pour ORDINAIRE: proposer option express si disponible
                if ($workflowCategory === WORKFLOW_ORDINAIRE && isExpressAvailable($this->session->getCollectedField('passport_type') ?? 'ORDINAIRE')) {
                    $this->session->setCollectedField('trip_substep', 'express');
                    return $this->createExpressQuestion();
                }
                
                // Pour ORDINAIRE: demander hébergement
                if ($workflowCategory === WORKFLOW_ORDINAIRE) {
                    $this->session->setCollectedField('trip_substep', 'accommodation');
                    return $this->createResponse(
                        getMessage('accommodation_type_request', $this->lang),
                        getAccommodationQuickActions($this->lang)
                    );
                }
                
                // DIPLOMATIQUE: passer à l'étape santé
                $this->session->setCollectedField('trip_substep', null);
                $this->advanceToNextStep();
                return $this->getStepInitialMessage();
                
            case 'express':
                $isExpress = in_array(strtolower($input), ['yes', 'oui', 'express', '1']);
                $this->session->setCollectedField('is_express', $isExpress);
                
                // Demander hébergement
                $this->session->setCollectedField('trip_substep', 'accommodation');
                return $this->createResponse(
                    getMessage('accommodation_type_request', $this->lang),
                    getAccommodationQuickActions($this->lang)
                );
                
            case 'accommodation':
                $this->session->setCollectedField('accommodation_type', strtoupper($input));
                $this->session->setCollectedField('trip_substep', null);
                $this->advanceToNextStep();
                return $this->getStepInitialMessage();
                
            default:
                return $this->createResponse(
                    getMessage('trip_dates_request', $this->lang),
                    [],
                    ['input_type' => 'date']
                );
        }
    }
    
    /**
     * Crée la question pour l'option express
     */
    private function createExpressQuestion(): array {
        $passportType = $this->session->getCollectedField('passport_type') ?? 'ORDINAIRE';
        $expressFees = calculateFees($passportType, 'COURT_SEJOUR', 'Unique', true);
        
        if ($this->lang === 'fr') {
            $message = "⚡ **Option traitement Express**\n\n";
            $message .= "Souhaitez-vous un traitement express ?\n";
            $message .= "• Standard: 5-10 jours ouvrés\n";
            $message .= "• **Express: 24-48h** (+{$expressFees['expressFee']} XOF)";
        } else {
            $message = "⚡ **Express Processing Option**\n\n";
            $message .= "Would you like express processing?\n";
            $message .= "• Standard: 5-10 business days\n";
            $message .= "• **Express: 24-48h** (+{$expressFees['expressFee']} XOF)";
        }
        
        return $this->createResponse(
            $message,
            [
                ['label' => $this->lang === 'fr' ? '⚡ Express (24-48h)' : '⚡ Express (24-48h)', 'value' => 'express'],
                ['label' => $this->lang === 'fr' ? '📅 Standard (5-10j)' : '📅 Standard (5-10d)', 'value' => 'standard']
            ]
        );
    }
    
    /**
     * Gère l'étape Santé
     */
    private function handleHealth(string $input, array $metadata): array {
        $subStep = $this->session->getCollectedField('health_substep') ?? 'vaccinated';
        
        if ($subStep === 'vaccinated') {
            $isVaccinated = in_array(strtolower($input), ['yes', 'oui', 'true', '1']);
            $this->session->setCollectedField('yellow_fever_vaccinated', $isVaccinated);
            
            if (!$isVaccinated) {
                $this->session->setCollectedField('health_substep', 'vaccination_warning');
                return $this->createResponse(
                    getMessage('health_vaccination_required', $this->lang),
                    getYesNoQuickActions($this->lang)
                );
            }
            
            $this->session->setCollectedField('health_substep', 'upload');
            return $this->createResponse(
                getMessage('health_vaccination_upload', $this->lang),
                [],
                ['input_type' => 'file', 'file_type' => 'vaccination']
            );
        }
        
        if ($subStep === 'vaccination_warning') {
            $continueAnyway = in_array(strtolower($input), ['yes', 'oui', 'true', '1']);
            if (!$continueAnyway) {
                $this->session->block('vaccination_required', [
                    'vaccine_type' => 'yellow_fever',
                    'user_choice' => 'declined_to_continue'
                ]);

                // Dispatch webhook: session abandoned (vaccination required)
                $this->dispatchWebhook(WebhookDispatcher::EVENT_SESSION_ABANDONED, [
                    'reason' => 'vaccination_required',
                    'step' => 'health'
                ]);
                
                return $this->createResponse(
                    $this->lang === 'fr'
                        ? "Nous comprenons. Revenez après avoir été vacciné. À bientôt ! 👋"
                        : "We understand. Come back after getting vaccinated. See you soon! 👋",
                    [],
                    ['blocking' => true]
                );
            }
            
            $this->session->setCollectedField('health_substep', null);
            $this->advanceToNextStep();
            return $this->getStepInitialMessage();
        }
        
        if ($subStep === 'upload' || isset($metadata['file_uploaded'])) {
            $this->session->setCollectedField('vaccination_certificate', $metadata['file_path'] ?? 'uploaded');
            $this->session->setCollectedField('health_substep', null);
            $this->advanceToNextStep();
            return $this->getStepInitialMessage();
        }
        
        return $this->createResponse(
            getMessage('health_vaccination_question', $this->lang),
            getYesNoQuickActions($this->lang)
        );
    }
    
    /**
     * Gère l'étape Douanes
     */
    private function handleCustoms(string $input, array $metadata): array {
        $hasItems = in_array(strtolower($input), ['yes', 'oui', 'true', '1']);
        $this->session->setCollectedField('customs_declaration', !$hasItems);
        $this->session->setCollectedField('customs_special_items', $hasItems);
        
        $this->advanceToNextStep();
        return $this->getConfirmationRecap();
    }
    
    /**
     * Gère l'étape Paiement
     */
    private function handlePayment(?string $input, array $metadata): array {
        // Si confirmation de paiement
        if ($input !== null && in_array(strtolower($input), ['pay', 'payer', 'pay_now', 'effectuer le paiement'])) {
            $this->advanceToNextStep();
            return $this->getStepInitialMessage();
        }

        $passportType = $this->session->getCollectedField('passport_type') ?? 'ORDINAIRE';
        $visaType = $this->session->getCollectedField('visa_type') ?? 'COURT_SEJOUR';
        $entries = $this->session->getCollectedField('visa_entries') ?? 'Unique';
        $isExpress = $this->session->getCollectedField('is_express') ?? false;

        $fees = calculateFees($passportType, $visaType, $entries, $isExpress);
        $amount = number_format($fees['total'], 0, ',', ' ');

        $message = $this->lang === 'fr'
            ? "💰 **Paiement des frais de visa**\n\nLe montant total à payer est de **{$amount} XOF**.\n\nCe montant inclut les frais de dossier et, le cas échéant, les frais d'option express.\n\nVeuillez procéder au paiement pour finaliser votre demande."
            : "💰 **Visa Fee Payment**\n\nThe total amount to pay is **{$amount} XOF**.\n\nThis amount includes processing fees and any express option fees.\n\nPlease proceed with payment to finalize your application.";

        return $this->createResponse(
            $message,
            [
                ['label' => $this->lang === 'fr' ? '💳 Payer maintenant' : '💳 Pay now', 'value' => 'pay_now']
            ],
            ['payment_amount' => $fees['total'], 'step' => 'payment']
        );
    }
    
    /**
     * Gère l'étape Confirmation
     */
    private function handleConfirmation(string $input, array $metadata): array {
        if (in_array(strtolower($input), ['confirm', 'oui', 'yes', 'confirmer'])) {
            $applicationNumber = 'VISA-' . strtoupper(substr(md5(uniqid()), 0, 8));
            $this->session->setCollectedField('application_number', $applicationNumber);
            
            $this->session->complete();
            
            $processingInfo = $this->getProcessingInfo();
            $collectedData = $this->session->getCollectedData();
            
            // Dispatch webhook: application submitted
            $this->dispatchWebhook(WebhookDispatcher::EVENT_APPLICATION_SUBMITTED, [
                'application_number' => $applicationNumber,
                'email' => $collectedData['email'] ?? null,
                'phone' => $collectedData['phone'] ?? null,
                'passport_type' => $collectedData['passport_type'] ?? 'ORDINAIRE',
                'visa_type' => $collectedData['visa_type'] ?? 'COURT_SEJOUR',
                'arrival_date' => $collectedData['arrival_date'] ?? null,
                'departure_date' => $collectedData['departure_date'] ?? null,
                'is_express' => $collectedData['is_express'] ?? false,
                'processing_time' => $processingInfo['processing_time']
            ]);
            
            // Dispatch webhook: session completed
            $this->dispatchWebhook(WebhookDispatcher::EVENT_SESSION_COMPLETED, [
                'application_number' => $applicationNumber,
                'total_steps_completed' => count($this->session->getCompletedSteps())
            ]);
            
            return $this->createResponse(
                getMessage('submission_success', $this->lang, [
                    'email' => $this->session->getCollectedField('email'),
                    'application_number' => $applicationNumber,
                    'processing_time' => $processingInfo['processing_time']
                ]),
                [],
                ['completed' => true, 'application_number' => $applicationNumber]
            );
        }
        
        if (in_array(strtolower($input), ['modify', 'non', 'no', 'modifier'])) {
            $this->session->setCurrentStep('residence');
            return $this->getStepInitialMessage();
        }
        
        return $this->getConfirmationRecap();
    }
    
    /**
     * Génère le récapitulatif de confirmation
     */
    private function getConfirmationRecap(): array {
        $data = $this->session->getCollectedData();
        $passport = $data['passport_data'] ?? [];
        $workflowCategory = $this->session->getWorkflowCategory() ?? WORKFLOW_ORDINAIRE;
        $passportType = $data['passport_type'] ?? 'ORDINAIRE';
        $isExpress = $data['is_express'] ?? false;
        
        // Calculer les frais
        $fees = calculateFees(
            $passportType,
            $data['visa_type'] ?? 'COURT_SEJOUR',
            $data['visa_entries'] ?? 'Unique',
            $isExpress
        );
        
        // Construire le récapitulatif
        $processingInfo = $this->getProcessingInfo();
        
        if ($fees['isFree']) {
            $feesDetail = $this->lang === 'fr' 
                ? '✨ GRATUIT (Passeport ' . (getPassportTypeInfo($passportType, $this->lang)['label']) . ')' 
                : '✨ FREE (' . (getPassportTypeInfo($passportType, $this->lang)['label']) . ')';
        } else {
            $feesDetail = formatPrice($fees['total']);
            if ($isExpress && $fees['expressFee'] > 0) {
                $feesDetail .= $this->lang === 'fr' 
                    ? ' (dont ' . formatPrice($fees['expressFee']) . ' express)'
                    : ' (incl. ' . formatPrice($fees['expressFee']) . ' express)';
            }
        }
        
        $message = getMessage('confirmation_recap', $this->lang, [
            'surname' => $passport['surname']['value'] ?? $data['surname'] ?? 'N/A',
            'given_names' => $passport['given_names']['value'] ?? $data['given_names'] ?? 'N/A',
            'nationality' => $passport['nationality']['value'] ?? $data['nationality'] ?? 'N/A',
            'passport_type' => getPassportTypeInfo($passportType, $this->lang)['label'],
            'passport_number' => $passport['passport_number']['value'] ?? $data['passport_number'] ?? 'N/A',
            'visa_type' => VISA_TYPES[$data['visa_type'] ?? 'COURT_SEJOUR'][$this->lang === 'fr' ? 'labelFr' : 'labelEn'],
            'entries' => $data['visa_entries'] ?? 'Unique',
            'arrival_date' => $data['arrival_date'] ?? 'N/A',
            'departure_date' => $data['departure_date'] ?? 'N/A',
            'trip_purpose' => TRIP_PURPOSES[$data['trip_purpose'] ?? 'TOURISME'][$this->lang === 'fr' ? 'labelFr' : 'labelEn'],
            'fees_detail' => $feesDetail,
            'processing_time' => $processingInfo['processing_time']
        ]);
        
        $message .= "\n\n" . getMessage('confirmation_terms', $this->lang);
        
        return $this->createResponse(
            $message,
            getConfirmModifyQuickActions($this->lang),
            ['is_confirmation' => true, 'fees' => $fees, 'processing_info' => $processingInfo]
        );
    }
    
    /**
     * Crée une réponse structurée
     */
    private function createResponse(string $message, array $quickActions = [], array $metadata = []): array {
        return [
            'message' => $message,
            'quick_actions' => $quickActions,
            'step' => $this->session->getCurrentStep(),
            'step_info' => $this->session->getStepInfo(),
            'workflow_category' => $this->session->getWorkflowCategory(),
            'workflow_type' => $this->getWorkflowType(),
            'metadata' => $metadata
        ];
    }

    // =========================================================================
    // SMART PREFILL & SUGGESTIONS (v6.0.0)
    // =========================================================================

    /**
     * Get prefill data for a document type
     *
     * @param string $docType Document type (hotel, vaccination, invitation, etc.)
     * @return array Prefill data with confidence and source documents
     */
    public function getPrefillForDocument(string $docType): array {
        $prefillService = new SmartPrefillService();
        $sessionData = $this->session->getAllData();

        return $prefillService->getPrefillForDocument($docType, $sessionData);
    }

    /**
     * Validate extracted data against prefill expectations
     *
     * @param string $docType Document type
     * @param array $extractedData Extracted data from OCR
     * @return array Validation result with discrepancies
     */
    public function validateExtractedVsPrefill(string $docType, array $extractedData): array {
        $prefillService = new SmartPrefillService();
        $sessionData = $this->session->getAllData();

        return $prefillService->validateExtractedVsPrefill($docType, $extractedData, $sessionData);
    }

    /**
     * Get proactive suggestions for current session
     *
     * @return array List of suggestions sorted by priority
     */
    public function getProactiveSuggestions(): array {
        $suggestionsService = new DocumentAnalysisSuggestions();
        $sessionData = $this->session->getAllData();

        return $suggestionsService->analyzeAndSuggest($sessionData);
    }

    /**
     * Get top priority suggestion
     *
     * @return array|null Top suggestion or null
     */
    public function getTopSuggestion(): ?array {
        $suggestionsService = new DocumentAnalysisSuggestions();
        $sessionData = $this->session->getAllData();

        return $suggestionsService->getTopSuggestion($sessionData);
    }

    /**
     * Get prefill status for all upcoming documents
     *
     * @return array Map of document type -> prefill availability
     */
    public function getUpcomingPrefillStatus(): array {
        $prefillService = new SmartPrefillService();
        $sessionData = $this->session->getAllData();
        $completedDocs = $this->getCompletedDocumentTypes();

        return $prefillService->getUpcomingPrefillStatus($sessionData, $completedDocs);
    }

    /**
     * Get list of completed document types
     *
     * @return array List of completed document type identifiers
     */
    private function getCompletedDocumentTypes(): array {
        $completed = [];
        $data = $this->session->getAllData();

        $docTypes = ['passport', 'ticket', 'hotel', 'vaccination', 'invitation', 'residence_card', 'payment', 'verbal_note'];

        foreach ($docTypes as $type) {
            if (!empty($data['extracted_data'][$type]) || !empty($data['extracted_' . $type])) {
                $completed[] = $type;
            }
        }

        return $completed;
    }

    /**
     * Generate prefill notification message for Aya persona
     *
     * @param string $docType Document type
     * @param array $prefillData Prefill data
     * @return string|null Message or null if no prefill
     */
    public function generatePrefillMessage(string $docType, array $prefillData): ?string {
        if (empty($prefillData['has_prefill']) || empty($prefillData['prefill_data'])) {
            return null;
        }

        $fieldsCount = $prefillData['fields_count'] ?? count($prefillData['prefill_data']);

        $docNames = [
            'hotel' => ['fr' => 'votre reservation hotel', 'en' => 'your hotel reservation'],
            'vaccination' => ['fr' => 'votre certificat de vaccination', 'en' => 'your vaccination certificate'],
            'invitation' => ['fr' => 'votre lettre d\'invitation', 'en' => 'your invitation letter'],
            'payment' => ['fr' => 'votre preuve de paiement', 'en' => 'your payment proof'],
            'residence_card' => ['fr' => 'votre carte de resident', 'en' => 'your residence card']
        ];

        $docName = $docNames[$docType][$this->lang] ?? $docType;

        if ($this->lang === 'fr') {
            return "✨ **Pre-remplissage intelligent**\n\n" .
                   "J'ai deja prepare **{$fieldsCount} champs** pour {$docName} " .
                   "a partir de vos documents precedents.\n\n" .
                   "Verifiez simplement que les informations sont correctes !";
        }

        return "✨ **Smart Prefill**\n\n" .
               "I've already prepared **{$fieldsCount} fields** for {$docName} " .
               "from your previous documents.\n\n" .
               "Just verify that the information is correct!";
    }

    /**
     * Enhance response with prefill and suggestions data
     *
     * @param array $response Base response
     * @param string $docType Document type for prefill
     * @return array Enhanced response
     */
    public function enhanceResponseWithPrefill(array $response, string $docType): array {
        // Add prefill data
        $prefillResult = $this->getPrefillForDocument($docType);
        if ($prefillResult['has_prefill']) {
            $response['metadata']['prefill_data'] = $prefillResult['prefill_data'];
            $response['metadata']['prefill_confidence'] = $prefillResult['confidence'];
            $response['metadata']['prefill_sources'] = $prefillResult['source_documents'];

            // Add prefill notification to message if appropriate
            $prefillMessage = $this->generatePrefillMessage($docType, $prefillResult);
            if ($prefillMessage) {
                $response['metadata']['prefill_notification'] = $prefillMessage;
            }
        }

        // Add relevant suggestions
        $suggestions = $this->getProactiveSuggestions();
        if (!empty($suggestions)) {
            $response['metadata']['suggestions'] = $suggestions;
        }

        return $response;
    }
}

