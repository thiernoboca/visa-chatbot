<?php
/**
 * Moteur de Suggestions Proactives
 * Anticipe les besoins de l'utilisateur et propose des actions contextuelles
 *
 * @package VisaChatbot
 * @version 1.0.0
 */

// Éviter conflit avec services/ProactiveSuggestions.php
if (class_exists('ProactiveSuggestions')) {
    return;
}

class ProactiveSuggestions {
    
    /**
     * Règles de suggestions par contexte
     */
    private const SUGGESTION_RULES = [
        // Suggestions basées sur le temps
        'time_based' => [
            'morning' => [
                'condition' => ['hour_range' => [5, 12]],
                'message_fr' => "Bon matin ! ☀️ Parfait moment pour commencer votre demande.",
                'message_en' => "Good morning! ☀️ Perfect time to start your application."
            ],
            'evening' => [
                'condition' => ['hour_range' => [18, 23]],
                'message_fr' => "Bonsoir ! Prenez votre temps, je suis là toute la nuit. 🌙",
                'message_en' => "Good evening! Take your time, I'm here all night. 🌙"
            ],
            'weekend' => [
                'condition' => ['day_of_week' => [0, 6]], // Dimanche = 0, Samedi = 6
                'message_fr' => "C'est le week-end ! L'ambassade répondra lundi, mais vous pouvez tout préparer maintenant. 📋",
                'message_en' => "It's the weekend! Embassy will respond Monday, but you can prepare everything now. 📋"
            ]
        ],
        
        // Suggestions basées sur la progression
        'progress_based' => [
            'slow_progress' => [
                'condition' => ['time_on_step_minutes' => 5],
                'message_fr' => "Besoin d'aide sur cette étape ? 🤔 Cliquez sur 'Aide' ou dites-moi ce qui vous bloque.",
                'message_en' => "Need help with this step? 🤔 Click 'Help' or tell me what's blocking you."
            ],
            'fast_progress' => [
                'condition' => ['steps_per_minute' => 2],
                'message_fr' => "Wow, vous allez vite ! 🚀 Vérifiez bien chaque info tout de même.",
                'message_en' => "Wow, you're fast! 🚀 Make sure to verify each info though."
            ]
        ],
        
        // Suggestions basées sur l'étape
        'step_based' => [
            'passport' => [
                'tips' => [
                    'fr' => [
                        "💡 **Astuce** : Une photo nette de la page d'identité suffit !",
                        "📱 Si la caméra ne fonctionne pas, téléversez simplement un fichier.",
                        "🔍 La zone MRZ (2 lignes de code en bas) est essentielle."
                    ],
                    'en' => [
                        "💡 **Tip**: A clear photo of the identity page is enough!",
                        "📱 If camera doesn't work, simply upload a file.",
                        "🔍 The MRZ zone (2 code lines at bottom) is essential."
                    ]
                ]
            ],
            'contact' => [
                'tips' => [
                    'fr' => [
                        "📧 L'email recevra votre confirmation et le suivi.",
                        "📱 Le numéro WhatsApp est idéal pour les notifications rapides !"
                    ],
                    'en' => [
                        "📧 This email will receive your confirmation and tracking.",
                        "📱 A WhatsApp number is ideal for quick notifications!"
                    ]
                ]
            ],
            'trip' => [
                'tips' => [
                    'fr' => [
                        "✈️ Prévoyez 5-10 jours ouvrés pour le traitement standard.",
                        "📅 Le visa est valide 3 mois à partir de la date d'émission.",
                        "🏨 Vous aurez besoin d'une preuve de réservation d'hébergement."
                    ],
                    'en' => [
                        "✈️ Allow 5-10 business days for standard processing.",
                        "📅 Visa is valid 3 months from issue date.",
                        "🏨 You'll need proof of accommodation booking."
                    ]
                ]
            ],
            'health' => [
                'tips' => [
                    'fr' => [
                        "💉 La vaccination fièvre jaune est OBLIGATOIRE.",
                        "⏰ Le vaccin est efficace 10 jours après l'injection.",
                        "📜 Le carnet jaune OMS est le document officiel."
                    ],
                    'en' => [
                        "💉 Yellow fever vaccination is MANDATORY.",
                        "⏰ Vaccine is effective 10 days after injection.",
                        "📜 The WHO yellow card is the official document."
                    ]
                ]
            ]
        ],
        
        // Suggestions basées sur le type de passeport
        'passport_type_based' => [
            'DIPLOMATIQUE' => [
                'message_fr' => "🎖️ En tant que diplomate, vous bénéficiez d'un traitement prioritaire gratuit !",
                'message_en' => "🎖️ As a diplomat, you benefit from free priority processing!"
            ],
            'SERVICE' => [
                'message_fr' => "🏛️ Passeport de service détecté. Procédure simplifiée activée !",
                'message_en' => "🏛️ Service passport detected. Simplified procedure activated!"
            ],
            'LP_ONU' => [
                'message_fr' => "🇺🇳 Laissez-passer ONU reconnu. Traitement prioritaire !",
                'message_en' => "🇺🇳 UN Laissez-passer recognized. Priority processing!"
            ]
        ],
        
        // Suggestions basées sur les erreurs courantes
        'error_prevention' => [
            'passport_expiry' => [
                'condition' => ['days_until_expiry_less_than' => 180],
                'message_fr' => "⚠️ Votre passeport expire dans moins de 6 mois. Assurez-vous qu'il sera valide pendant tout votre séjour !",
                'message_en' => "⚠️ Your passport expires in less than 6 months. Make sure it will be valid throughout your stay!"
            ],
            'same_dates' => [
                'condition' => ['arrival_equals_departure' => true],
                'message_fr' => "🤔 Arrivée et départ le même jour ? Confirmez-vous ces dates ?",
                'message_en' => "🤔 Arrival and departure same day? Do you confirm these dates?"
            ]
        ]
    ];
    
    /**
     * Quick actions par étape
     */
    private const QUICK_ACTIONS = [
        'welcome' => [
            ['label' => '🇫🇷 Français', 'value' => 'fr', 'type' => 'language'],
            ['label' => '🇬🇧 English', 'value' => 'en', 'type' => 'language']
        ],
        'residence' => [
            ['label' => '🇪🇹 Éthiopie', 'value' => 'ET', 'type' => 'country'],
            ['label' => '🇰🇪 Kenya', 'value' => 'KE', 'type' => 'country'],
            ['label' => '🇩🇯 Djibouti', 'value' => 'DJ', 'type' => 'country'],
            ['label' => '🇹🇿 Tanzanie', 'value' => 'TZ', 'type' => 'country'],
            ['label' => '🇺🇬 Ouganda', 'value' => 'UG', 'type' => 'country'],
            ['label' => '🇸🇸 Soudan du Sud', 'value' => 'SS', 'type' => 'country'],
            ['label' => '🇸🇴 Somalie', 'value' => 'SO', 'type' => 'country']
        ],
        'documents' => [
            ['label' => '📸 Scanner passeport', 'value' => 'scan_passport', 'type' => 'action'],
            ['label' => '📄 Téléverser documents', 'value' => 'upload_documents', 'type' => 'action'],
            ['label' => '⏭️ Saisir manuellement', 'value' => 'manual_entry', 'type' => 'action']
        ],
        'passport' => [
            ['label' => '📷 Prendre photo', 'value' => 'camera', 'type' => 'action'],
            ['label' => '📁 Choisir fichier', 'value' => 'file', 'type' => 'action'],
            ['label' => '❓ Aide', 'value' => 'help', 'type' => 'help']
        ],
        'trip_purpose' => [
            ['label' => '🏖️ Tourisme', 'value' => 'TOURISME', 'type' => 'purpose'],
            ['label' => '💼 Affaires', 'value' => 'AFFAIRES', 'type' => 'purpose'],
            ['label' => '👨‍👩‍👧 Famille', 'value' => 'FAMILIAL', 'type' => 'purpose'],
            ['label' => '🏛️ Officiel', 'value' => 'OFFICIEL', 'type' => 'purpose'],
            ['label' => '🏥 Médical', 'value' => 'MEDICAL', 'type' => 'purpose'],
            ['label' => '🎓 Études', 'value' => 'ETUDES', 'type' => 'purpose']
        ],
        'health' => [
            ['label' => '✅ Oui, vacciné(e)', 'value' => 'yes', 'type' => 'confirm'],
            ['label' => '❌ Non', 'value' => 'no', 'type' => 'confirm'],
            ['label' => '❓ C\'est quoi ?', 'value' => 'help', 'type' => 'help']
        ],
        'confirm' => [
            ['label' => '✅ Tout est correct', 'value' => 'confirm', 'type' => 'confirm'],
            ['label' => '✏️ Modifier', 'value' => 'edit', 'type' => 'action'],
            ['label' => '📋 Récapitulatif PDF', 'value' => 'pdf', 'type' => 'action']
        ]
    ];
    
    /**
     * Contexte utilisateur actuel
     */
    private array $context;
    
    /**
     * Constructeur
     */
    public function __construct(array $context = []) {
        $this->context = $context;
    }
    
    /**
     * Met à jour le contexte
     */
    public function setContext(array $context): void {
        $this->context = $context;
    }
    
    /**
     * Obtient les suggestions pour l'étape actuelle
     * 
     * @param string $step Étape actuelle
     * @param string $lang Langue
     * @return array Suggestions avec messages et actions
     */
    public function getSuggestions(string $step, string $lang = 'fr'): array {
        $suggestions = [];
        
        // 1. Suggestions basées sur le temps
        $timeSuggestion = $this->getTimeSuggestion($lang);
        if ($timeSuggestion) {
            $suggestions['time'] = $timeSuggestion;
        }
        
        // 2. Tips pour l'étape actuelle
        $stepTips = $this->getStepTips($step, $lang);
        if ($stepTips) {
            $suggestions['tips'] = $stepTips;
        }
        
        // 3. Suggestions basées sur le type de passeport
        if (isset($this->context['passport_type'])) {
            $passportSuggestion = $this->getPassportTypeSuggestion($this->context['passport_type'], $lang);
            if ($passportSuggestion) {
                $suggestions['passport_type'] = $passportSuggestion;
            }
        }
        
        // 4. Alertes de prévention d'erreurs
        $errorAlerts = $this->getErrorPreventionAlerts($lang);
        if ($errorAlerts) {
            $suggestions['alerts'] = $errorAlerts;
        }
        
        // 5. Quick actions pour l'étape
        $suggestions['quick_actions'] = $this->getQuickActions($step, $lang);
        
        return $suggestions;
    }
    
    /**
     * Obtient la suggestion basée sur l'heure
     */
    private function getTimeSuggestion(string $lang): ?string {
        $hour = (int) date('H');
        $dayOfWeek = (int) date('w');
        
        $rules = self::SUGGESTION_RULES['time_based'];
        
        // Check weekend
        if (in_array($dayOfWeek, $rules['weekend']['condition']['day_of_week'])) {
            return $lang === 'en' ? $rules['weekend']['message_en'] : $rules['weekend']['message_fr'];
        }
        
        // Check morning
        $morningRange = $rules['morning']['condition']['hour_range'];
        if ($hour >= $morningRange[0] && $hour < $morningRange[1]) {
            return $lang === 'en' ? $rules['morning']['message_en'] : $rules['morning']['message_fr'];
        }
        
        // Check evening
        $eveningRange = $rules['evening']['condition']['hour_range'];
        if ($hour >= $eveningRange[0] && $hour <= $eveningRange[1]) {
            return $lang === 'en' ? $rules['evening']['message_en'] : $rules['evening']['message_fr'];
        }
        
        return null;
    }
    
    /**
     * Obtient les tips pour une étape
     */
    private function getStepTips(string $step, string $lang): ?array {
        $stepRules = self::SUGGESTION_RULES['step_based'][$step] ?? null;
        
        if (!$stepRules || !isset($stepRules['tips'][$lang])) {
            return null;
        }
        
        // Retourner un tip aléatoire
        $tips = $stepRules['tips'][$lang];
        return [$tips[array_rand($tips)]];
    }
    
    /**
     * Obtient la suggestion basée sur le type de passeport
     */
    private function getPassportTypeSuggestion(string $passportType, string $lang): ?string {
        $rules = self::SUGGESTION_RULES['passport_type_based'];
        
        if (!isset($rules[$passportType])) {
            return null;
        }
        
        return $lang === 'en' ? $rules[$passportType]['message_en'] : $rules[$passportType]['message_fr'];
    }
    
    /**
     * Obtient les alertes de prévention d'erreurs
     */
    private function getErrorPreventionAlerts(string $lang): array {
        $alerts = [];
        $rules = self::SUGGESTION_RULES['error_prevention'];
        
        // Vérifier expiration du passeport
        if (isset($this->context['passport_expiry'])) {
            try {
                $expiryDate = new DateTime($this->context['passport_expiry']);
                $now = new DateTime();
                $daysUntilExpiry = $now->diff($expiryDate)->days;
                
                if ($daysUntilExpiry < 180) {
                    $alerts[] = $lang === 'en' 
                        ? $rules['passport_expiry']['message_en'] 
                        : $rules['passport_expiry']['message_fr'];
                }
            } catch (Exception $e) {
                // Ignore date parsing errors
            }
        }
        
        // Vérifier dates identiques
        if (isset($this->context['arrival_date']) && isset($this->context['departure_date'])) {
            if ($this->context['arrival_date'] === $this->context['departure_date']) {
                $alerts[] = $lang === 'en'
                    ? $rules['same_dates']['message_en']
                    : $rules['same_dates']['message_fr'];
            }
        }
        
        return $alerts;
    }
    
    /**
     * Obtient les quick actions pour une étape
     */
    public function getQuickActions(string $step, string $lang = 'fr'): array {
        $actions = self::QUICK_ACTIONS[$step] ?? [];
        
        // Adapter les labels selon la langue
        if ($lang === 'en') {
            $translations = [
                'Scanner passeport' => 'Scan passport',
                'Téléverser documents' => 'Upload documents',
                'Saisir manuellement' => 'Enter manually',
                'Prendre photo' => 'Take photo',
                'Choisir fichier' => 'Choose file',
                'Aide' => 'Help',
                'Tourisme' => 'Tourism',
                'Affaires' => 'Business',
                'Famille' => 'Family',
                'Officiel' => 'Official',
                'Médical' => 'Medical',
                'Études' => 'Studies',
                'Oui, vacciné(e)' => 'Yes, vaccinated',
                'Non' => 'No',
                'C\'est quoi ?' => 'What\'s this?',
                'Tout est correct' => 'Everything is correct',
                'Modifier' => 'Edit',
                'Récapitulatif PDF' => 'PDF Summary'
            ];
            
            foreach ($actions as &$action) {
                $labelWithoutEmoji = preg_replace('/^[\p{So}\p{Sc}]\s*/u', '', $action['label']);
                $emoji = str_replace($labelWithoutEmoji, '', $action['label']);
                
                if (isset($translations[$labelWithoutEmoji])) {
                    $action['label'] = $emoji . $translations[$labelWithoutEmoji];
                }
            }
        }
        
        // Ajouter des actions contextuelles
        $actions = $this->addContextualActions($actions, $step, $lang);
        
        return $actions;
    }
    
    /**
     * Ajoute des actions contextuelles selon le contexte
     */
    private function addContextualActions(array $actions, string $step, string $lang): array {
        // Si on a un brouillon, proposer de le restaurer
        if ($step === 'welcome' && isset($this->context['has_draft']) && $this->context['has_draft']) {
            $actions[] = [
                'label' => $lang === 'en' ? '📋 Resume previous' : '📋 Reprendre précédent',
                'value' => 'resume_draft',
                'type' => 'action',
                'highlight' => true
            ];
        }
        
        // Toujours proposer l'aide sauf sur welcome
        if ($step !== 'welcome' && !in_array('help', array_column($actions, 'type'))) {
            $actions[] = [
                'label' => $lang === 'en' ? '❓ Help' : '❓ Aide',
                'value' => 'help',
                'type' => 'help'
            ];
        }
        
        return $actions;
    }
    
    /**
     * Génère une suggestion de complétion automatique
     * 
     * @param string $partialInput Saisie partielle
     * @param string $field Type de champ
     * @return array Suggestions de complétion
     */
    public function getAutocomplete(string $partialInput, string $field): array {
        $suggestions = [];
        $partialLower = strtolower($partialInput);
        
        switch ($field) {
            case 'country':
                $countries = [
                    'ethiopia' => ['code' => 'ET', 'fr' => 'Éthiopie', 'en' => 'Ethiopia'],
                    'éthiopie' => ['code' => 'ET', 'fr' => 'Éthiopie', 'en' => 'Ethiopia'],
                    'kenya' => ['code' => 'KE', 'fr' => 'Kenya', 'en' => 'Kenya'],
                    'djibouti' => ['code' => 'DJ', 'fr' => 'Djibouti', 'en' => 'Djibouti'],
                    'tanzania' => ['code' => 'TZ', 'fr' => 'Tanzanie', 'en' => 'Tanzania'],
                    'tanzanie' => ['code' => 'TZ', 'fr' => 'Tanzanie', 'en' => 'Tanzania'],
                    'uganda' => ['code' => 'UG', 'fr' => 'Ouganda', 'en' => 'Uganda'],
                    'ouganda' => ['code' => 'UG', 'fr' => 'Ouganda', 'en' => 'Uganda'],
                    'south sudan' => ['code' => 'SS', 'fr' => 'Soudan du Sud', 'en' => 'South Sudan'],
                    'soudan du sud' => ['code' => 'SS', 'fr' => 'Soudan du Sud', 'en' => 'South Sudan'],
                    'somalia' => ['code' => 'SO', 'fr' => 'Somalie', 'en' => 'Somalia'],
                    'somalie' => ['code' => 'SO', 'fr' => 'Somalie', 'en' => 'Somalia']
                ];
                
                foreach ($countries as $key => $data) {
                    if (str_starts_with($key, $partialLower) || str_contains($key, $partialLower)) {
                        $suggestions[] = $data;
                    }
                }
                break;
                
            case 'city':
                $cities = [
                    'ET' => ['Addis Ababa', 'Dire Dawa', 'Gondar', 'Hawassa'],
                    'KE' => ['Nairobi', 'Mombasa', 'Kisumu', 'Nakuru'],
                    'DJ' => ['Djibouti Ville', 'Ali Sabieh', 'Tadjoura'],
                    'TZ' => ['Dar es Salaam', 'Dodoma', 'Arusha', 'Zanzibar'],
                    'UG' => ['Kampala', 'Entebbe', 'Jinja', 'Gulu'],
                    'SS' => ['Juba', 'Wau', 'Malakal'],
                    'SO' => ['Mogadiscio', 'Hargeisa', 'Kismayo']
                ];
                
                $countryCode = $this->context['country_code'] ?? null;
                $citiesForCountry = $countryCode ? ($cities[$countryCode] ?? []) : 
                    array_merge(...array_values($cities));
                
                foreach ($citiesForCountry as $city) {
                    if (str_starts_with(strtolower($city), $partialLower)) {
                        $suggestions[] = $city;
                    }
                }
                break;
        }
        
        return array_slice($suggestions, 0, 5);
    }
    
    /**
     * Obtient un message d'encouragement basé sur la progression
     */
    public function getProgressEncouragement(float $progress, string $lang = 'fr'): ?string {
        // Clés en pourcentage (int) pour éviter les problèmes de conversion float
        $messages = [
            25 => [
                'fr' => "🚀 Un quart du chemin parcouru !",
                'en' => "🚀 Quarter of the way done!"
            ],
            50 => [
                'fr' => "🌟 À mi-chemin ! Vous êtes au top !",
                'en' => "🌟 Halfway there! You're doing great!"
            ],
            75 => [
                'fr' => "💪 Plus que quelques étapes !",
                'en' => "💪 Just a few more steps!"
            ],
            90 => [
                'fr' => "🏁 Dernière ligne droite !",
                'en' => "🏁 Final stretch!"
            ]
        ];
        
        // Convertir la progression en pourcentage
        $progressPercent = $progress <= 1 ? $progress * 100 : $progress;
        
        foreach ($messages as $threshold => $msg) {
            if (abs($progressPercent - $threshold) < 5) {
                return $msg[$lang];
            }
        }
        
        return null;
    }

    /**
     * Analyze session and suggest (compatibility with services/ProactiveSuggestions)
     * @param array $session Session data
     * @return array Suggestions
     */
    public function analyzeAndSuggest(array $session): array {
        // Stub implementation - return empty array for compatibility
        // The actual implementation is in services/ProactiveSuggestions.php
        return [];
    }

    /**
     * Get top suggestion (compatibility with services/ProactiveSuggestions)
     * @param array $session Session data
     * @return array|null Top suggestion or null
     */
    public function getTopSuggestion(array $session): ?array {
        // Stub implementation
        return null;
    }
}

