<?php
/**
 * Messages d'erreur empathiques et contextuels
 * Transforme les erreurs techniques en messages humains et rassurants
 * 
 * @package VisaChatbot
 * @version 1.0.0
 */

class EmpathicErrors {
    
    /**
     * Persona pour les messages d'erreur
     */
    private const PERSONA = 'Aya';
    
    /**
     * Messages d'erreur par catégorie
     */
    private const ERROR_MESSAGES = [
        // Erreurs de validation
        'validation' => [
            'email_invalid' => [
                'fr' => "Hmm, cet email ne semble pas correct 🤔\n\nVérifie qu'il contient bien un @ et un domaine valide.\n**Exemple** : prenom.nom@email.com",
                'en' => "Hmm, this email doesn't look right 🤔\n\nMake sure it contains an @ and a valid domain.\n**Example**: firstname.lastname@email.com"
            ],
            'phone_invalid' => [
                'fr' => "Ce numéro de téléphone ne semble pas valide 📱\n\nMerci d'inclure l'indicatif pays (+XXX).\n**Exemple** : +251 912 345 678",
                'en' => "This phone number doesn't seem valid 📱\n\nPlease include the country code (+XXX).\n**Example**: +251 912 345 678"
            ],
            'date_invalid' => [
                'fr' => "Cette date n'est pas au bon format 📅\n\nUtilise le format JJ/MM/AAAA.\n**Exemple** : 25/12/2025",
                'en' => "This date isn't in the right format 📅\n\nUse the format DD/MM/YYYY.\n**Example**: 25/12/2025"
            ],
            'date_past' => [
                'fr' => "Oops ! Cette date est dans le passé ⏳\n\nTu prévois bien un voyage futur, non ? Choisis une date à venir.",
                'en' => "Oops! This date is in the past ⏳\n\nYou're planning a future trip, right? Choose an upcoming date."
            ],
            'date_too_far' => [
                'fr' => "C'est un peu loin ! 🚀\n\nLes demandes de visa sont valables 3 mois. Choisis une date plus proche.",
                'en' => "That's a bit far! 🚀\n\nVisa applications are valid for 3 months. Choose a closer date."
            ],
            'passport_expired' => [
                'fr' => "⚠️ Attention, ton passeport semble expiré ou expire bientôt.\n\nIl doit être valide au moins 6 mois après la date de retour prévue.\n\n💡 **Conseil** : Renouvelle ton passeport avant de faire la demande de visa.",
                'en' => "⚠️ Attention, your passport seems expired or expires soon.\n\nIt must be valid at least 6 months after your planned return date.\n\n💡 **Tip**: Renew your passport before applying for a visa."
            ],
            'passport_number_invalid' => [
                'fr' => "Ce numéro de passeport ne semble pas correct 🔍\n\nIl devrait contenir 8-9 caractères alphanumériques.\nVérifie sur la page d'identité de ton passeport.",
                'en' => "This passport number doesn't seem right 🔍\n\nIt should contain 8-9 alphanumeric characters.\nCheck on your passport's identity page."
            ],
            'file_too_large' => [
                'fr' => "Ce fichier est un peu trop lourd 📦\n\nLa taille maximum est de 5 Mo.\n\n💡 **Astuce** : Compresse l'image ou prends une photo de moindre résolution.",
                'en' => "This file is a bit too large 📦\n\nMaximum size is 5 MB.\n\n💡 **Tip**: Compress the image or take a lower resolution photo."
            ],
            'file_type_invalid' => [
                'fr' => "Ce type de fichier n'est pas accepté 📄\n\nFormats acceptés : JPG, PNG, PDF\n\nConvertis ton document et réessaie !",
                'en' => "This file type is not accepted 📄\n\nAccepted formats: JPG, PNG, PDF\n\nConvert your document and try again!"
            ],
            'required_field' => [
                'fr' => "J'ai besoin de cette information pour continuer 📝\n\nMerci de la renseigner.",
                'en' => "I need this information to continue 📝\n\nPlease fill it in."
            ]
        ],
        
        // Erreurs métier
        'business' => [
            'country_not_covered' => [
                'fr' => "Désolée, notre ambassade à Addis-Abeba ne couvre pas ce pays 🌍\n\nNous traitons les demandes pour :\n• Éthiopie\n• Kenya\n• Djibouti\n• Tanzanie\n• Ouganda\n• Soudan du Sud\n• Somalie\n\nContacte l'ambassade de ton pays de résidence.",
                'en' => "Sorry, our embassy in Addis Ababa doesn't cover this country 🌍\n\nWe process applications for:\n• Ethiopia\n• Kenya\n• Djibouti\n• Tanzania\n• Uganda\n• South Sudan\n• Somalia\n\nContact the embassy in your country of residence."
            ],
            'vaccination_required' => [
                'fr' => "🛑 **Attention importante**\n\nLa vaccination contre la fièvre jaune est **obligatoire** pour entrer en Côte d'Ivoire.\n\nSans vaccin valide, l'entrée sur le territoire te sera refusée.\n\n💡 Rends-toi dans un centre de vaccination agréé. Le vaccin est efficace 10 jours après l'injection.",
                'en' => "🛑 **Important Notice**\n\nYellow fever vaccination is **mandatory** to enter Côte d'Ivoire.\n\nWithout a valid vaccine, you will be denied entry.\n\n💡 Visit an approved vaccination center. The vaccine is effective 10 days after injection."
            ],
            'verbal_note_required' => [
                'fr' => "📋 Pour un passeport diplomatique/de service, une **Note Verbale** est requise.\n\nCe document officiel doit être émis par ton ministère des Affaires étrangères.\n\nContacte ton employeur ou ministère pour l'obtenir.",
                'en' => "📋 For a diplomatic/service passport, a **Verbal Note** is required.\n\nThis official document must be issued by your Ministry of Foreign Affairs.\n\nContact your employer or ministry to obtain it."
            ],
            'session_expired' => [
                'fr' => "Ta session a expiré pour des raisons de sécurité ⏱️\n\nPas de panique ! Tes données ont été sauvegardées.\n\n🔄 Rafraîchis la page pour reprendre où tu en étais.",
                'en' => "Your session has expired for security reasons ⏱️\n\nDon't panic! Your data has been saved.\n\n🔄 Refresh the page to continue where you left off."
            ],
            'application_duplicate' => [
                'fr' => "Il semble que tu aies déjà une demande en cours 📋\n\nVérifie ton email pour le numéro de dossier existant.\n\nSouhaites-tu :\n• Consulter ta demande existante\n• Annuler et recommencer",
                'en' => "It seems you already have an application in progress 📋\n\nCheck your email for the existing file number.\n\nWould you like to:\n• View your existing application\n• Cancel and start over"
            ]
        ],
        
        // Erreurs techniques
        'technical' => [
            'network_error' => [
                'fr' => "Oups ! Problème de connexion 📡\n\nVérifie ta connexion internet et réessaie.\n\nSi le problème persiste, attends quelques minutes.",
                'en' => "Oops! Connection problem 📡\n\nCheck your internet connection and try again.\n\nIf the problem persists, wait a few minutes."
            ],
            'ocr_failed' => [
                'fr' => "Je n'arrive pas à lire ce document 🔍\n\nQuelques conseils :\n• Assure-toi que l'image est bien éclairée\n• Évite les reflets et ombres\n• Cadre bien la page entière\n• La zone MRZ (2 lignes en bas) doit être visible\n\nRéessaie avec une meilleure photo !",
                'en' => "I can't read this document 🔍\n\nSome tips:\n• Make sure the image is well-lit\n• Avoid reflections and shadows\n• Frame the entire page\n• The MRZ zone (2 lines at bottom) must be visible\n\nTry again with a better photo!"
            ],
            'upload_failed' => [
                'fr' => "L'upload a échoué 😕\n\nÇa peut arriver ! Réessaie, et si ça persiste :\n• Vérifie ta connexion\n• Réduis la taille du fichier\n• Essaie un autre navigateur",
                'en' => "Upload failed 😕\n\nIt happens! Try again, and if it persists:\n• Check your connection\n• Reduce file size\n• Try another browser"
            ],
            'server_error' => [
                'fr' => "Hmm, quelque chose ne va pas de notre côté 🔧\n\nNos équipes sont informées. Réessaie dans quelques minutes.\n\nTes données sont sauvegardées, pas d'inquiétude !",
                'en' => "Hmm, something's wrong on our end 🔧\n\nOur team has been notified. Try again in a few minutes.\n\nYour data is saved, don't worry!"
            ],
            'timeout' => [
                'fr' => "La requête a pris trop de temps ⏳\n\nC'est peut-être un problème temporaire. Réessaie !",
                'en' => "The request took too long ⏳\n\nIt might be a temporary issue. Try again!"
            ]
        ],
        
        // Erreurs de navigation
        'navigation' => [
            'step_not_accessible' => [
                'fr' => "Tu ne peux pas encore accéder à cette étape 🚧\n\nComplete d'abord les étapes précédentes.\n\nJe suis là pour te guider !",
                'en' => "You can't access this step yet 🚧\n\nComplete the previous steps first.\n\nI'm here to guide you!"
            ],
            'back_not_allowed' => [
                'fr' => "Impossible de revenir en arrière à ce stade 🔒\n\nCertaines informations ont déjà été validées.\n\nContacte-nous si tu as besoin de modifier quelque chose.",
                'en' => "Can't go back at this stage 🔒\n\nSome information has already been validated.\n\nContact us if you need to change something."
            ]
        ],
        
        // Clarifications
        'clarification' => [
            'not_understood' => [
                'fr' => "Je ne suis pas sûre de comprendre 🤔\n\nPeux-tu reformuler ou choisir une des options proposées ?",
                'en' => "I'm not sure I understand 🤔\n\nCan you rephrase or choose one of the proposed options?"
            ],
            'ambiguous_input' => [
                'fr' => "J'ai besoin d'une précision 💭\n\nTa réponse peut être interprétée de plusieurs façons.\n\nPeux-tu être plus spécifique ?",
                'en' => "I need clarification 💭\n\nYour answer can be interpreted in several ways.\n\nCan you be more specific?"
            ],
            'missing_context' => [
                'fr' => "Il me manque une information pour te répondre correctement 📋\n\nPeux-tu compléter ?",
                'en' => "I'm missing some information to answer correctly 📋\n\nCan you complete it?"
            ]
        ]
    ];
    
    /**
     * Suffixes encourageants
     */
    private const ENCOURAGEMENTS = [
        'fr' => [
            "Je suis là pour t'aider ! 💪",
            "On va y arriver ensemble ! ✨",
            "Pas de panique, c'est normal ! 😊",
            "Une petite correction et on continue ! 🚀"
        ],
        'en' => [
            "I'm here to help! 💪",
            "We'll get through this together! ✨",
            "Don't panic, it's normal! 😊",
            "A small fix and we continue! 🚀"
        ]
    ];
    
    /**
     * Obtient un message d'erreur empathique
     * 
     * @param string $category Catégorie d'erreur
     * @param string $key Clé du message
     * @param string $lang Langue
     * @param bool $addEncouragement Ajouter un encouragement
     * @return string Message formaté
     */
    public static function get(string $category, string $key, string $lang = 'fr', bool $addEncouragement = true): string {
        $messages = self::ERROR_MESSAGES[$category][$key] ?? null;
        
        if (!$messages) {
            return self::getGenericError($lang);
        }
        
        $message = $messages[$lang] ?? $messages['fr'];
        
        if ($addEncouragement) {
            $encouragements = self::ENCOURAGEMENTS[$lang] ?? self::ENCOURAGEMENTS['fr'];
            $encouragement = $encouragements[array_rand($encouragements)];
            $message .= "\n\n" . $encouragement;
        }
        
        return $message;
    }
    
    /**
     * Obtient une erreur générique
     */
    public static function getGenericError(string $lang = 'fr'): string {
        $messages = [
            'fr' => "Hmm, quelque chose ne va pas 🤔\n\nPeux-tu réessayer ou reformuler ?\n\nJe suis là pour t'aider !",
            'en' => "Hmm, something's not right 🤔\n\nCan you try again or rephrase?\n\nI'm here to help!"
        ];
        
        return $messages[$lang] ?? $messages['fr'];
    }
    
    /**
     * Transforme une erreur technique en message empathique
     */
    public static function fromException(\Exception $e, string $lang = 'fr'): string {
        $message = strtolower($e->getMessage());
        
        // Mapper les messages d'erreur aux catégories
        $mappings = [
            'network' => ['curl', 'connection', 'timeout', 'socket'],
            'validation' => ['invalid', 'required', 'format', 'empty'],
            'file' => ['upload', 'file', 'size', 'type'],
            'ocr' => ['ocr', 'extract', 'read', 'parse']
        ];
        
        foreach ($mappings as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($message, $keyword)) {
                    switch ($type) {
                        case 'network':
                            return self::get('technical', 'network_error', $lang);
                        case 'file':
                            return self::get('technical', 'upload_failed', $lang);
                        case 'ocr':
                            return self::get('technical', 'ocr_failed', $lang);
                        default:
                            return self::get('technical', 'server_error', $lang);
                    }
                }
            }
        }
        
        return self::get('technical', 'server_error', $lang);
    }
    
    /**
     * Obtient un message pour un champ de validation spécifique
     */
    public static function forField(string $field, string $errorType, string $lang = 'fr'): string {
        $fieldMappings = [
            'email' => 'email_invalid',
            'phone' => 'phone_invalid',
            'date' => 'date_invalid',
            'arrival_date' => 'date_invalid',
            'departure_date' => 'date_invalid',
            'passport_number' => 'passport_number_invalid',
            'passport_expiry' => 'passport_expired'
        ];
        
        $key = $fieldMappings[$field] ?? 'required_field';
        
        if ($errorType === 'past') {
            $key = 'date_past';
        } elseif ($errorType === 'future_too_far') {
            $key = 'date_too_far';
        } elseif ($errorType === 'expired') {
            $key = 'passport_expired';
        }
        
        return self::get('validation', $key, $lang);
    }
    
    /**
     * Crée un message d'aide contextuel
     */
    public static function helpFor(string $context, string $lang = 'fr'): string {
        $helpMessages = [
            'passport_scan' => [
                'fr' => "📸 **Comment scanner ton passeport ?**\n\n1. Ouvre la page avec ta photo\n2. Place-la sur une surface plane et bien éclairée\n3. Évite les reflets et les ombres\n4. Cadre toute la page, y compris la zone MRZ (les 2 lignes de code en bas)\n5. Prends la photo bien droite\n\nL'image doit être nette pour que je puisse la lire !",
                'en' => "📸 **How to scan your passport?**\n\n1. Open the page with your photo\n2. Place it on a flat, well-lit surface\n3. Avoid reflections and shadows\n4. Frame the entire page, including the MRZ zone (2 code lines at bottom)\n5. Take the photo straight\n\nThe image must be clear for me to read it!"
            ],
            'vaccination' => [
                'fr' => "💉 **Vaccination Fièvre Jaune**\n\nLa Côte d'Ivoire exige cette vaccination pour tous les voyageurs.\n\n• Efficace 10 jours après l'injection\n• Valide à vie (anciennement 10 ans)\n• Certificat international jaune (OMS)\n\nOù se faire vacciner ?\n→ Centre de vaccination agréé\n→ Hôpitaux internationaux",
                'en' => "💉 **Yellow Fever Vaccination**\n\nCôte d'Ivoire requires this vaccination for all travelers.\n\n• Effective 10 days after injection\n• Valid for life (formerly 10 years)\n• International yellow certificate (WHO)\n\nWhere to get vaccinated?\n→ Approved vaccination center\n→ International hospitals"
            ],
            'processing_time' => [
                'fr' => "⏱️ **Délais de traitement**\n\n• Passeport ordinaire : 5-10 jours ouvrés\n• Express (+20€) : 2-3 jours ouvrés\n• Diplomatique/Service : 24-48h (gratuit)\n\n💡 Prévois de soumettre ta demande au moins 2 semaines avant ton voyage !",
                'en' => "⏱️ **Processing times**\n\n• Ordinary passport: 5-10 business days\n• Express (+€20): 2-3 business days\n• Diplomatic/Service: 24-48h (free)\n\n💡 Plan to submit your application at least 2 weeks before your trip!"
            ],
            'fees' => [
                'fr' => "💰 **Frais de visa**\n\n• Visa ordinaire : 73 000 FCFA (~111€)\n• Entrées multiples : +47 000 FCFA\n• Express : +13 000 FCFA\n• Diplomatique/Service : Gratuit\n\nPaiement accepté : Espèces, Carte bancaire",
                'en' => "💰 **Visa fees**\n\n• Ordinary visa: 73,000 FCFA (~€111)\n• Multiple entries: +47,000 FCFA\n• Express: +13,000 FCFA\n• Diplomatic/Service: Free\n\nPayment accepted: Cash, Credit card"
            ]
        ];
        
        return $helpMessages[$context][$lang] ?? $helpMessages[$context]['fr'] ?? self::getGenericError($lang);
    }
}

