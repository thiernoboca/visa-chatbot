<?php
/**
 * Configuration des Tests A/B - Chatbot Visa CI
 * 
 * Chaque test définit des variants avec des poids (pourcentage de trafic)
 * et une métrique cible pour mesurer le succès.
 * 
 * @package VisaChatbot
 */

return [
    /**
     * Activer/désactiver globalement les tests A/B
     */
    'enabled' => true,
    
    /**
     * Tests configurés
     */
    'tests' => [
        /**
         * Test du message de bienvenue
         * Objectif: Maximiser le taux de complétion de l'étape welcome
         */
        'welcome_message' => [
            'name' => 'Message de bienvenue',
            'description' => 'Test différents messages d\'accueil pour maximiser l\'engagement',
            'metric' => 'step_completion_welcome',
            'active' => true,
            'variants' => [
                'control' => [
                    'weight' => 50,
                    'message_key' => 'welcome',
                    'description' => 'Message original'
                ],
                'friendly' => [
                    'weight' => 50,
                    'message_key' => 'welcome_friendly',
                    'description' => 'Message plus chaleureux',
                    'custom_message' => [
                        'fr' => "👋 Bienvenue à l'Ambassade de Côte d'Ivoire !\n\nJe suis votre assistant personnel pour votre demande de visa. En quelques minutes, nous allons préparer votre dossier ensemble.\n\nQuelle langue préférez-vous ?",
                        'en' => "👋 Welcome to the Embassy of Côte d'Ivoire!\n\nI'm your personal assistant for your visa application. In just a few minutes, we'll prepare your file together.\n\nWhich language do you prefer?"
                    ]
                ]
            ]
        ],
        
        /**
         * Test de l'interface de scan passeport
         * Objectif: Maximiser le taux de succès des scans
         */
        'passport_scan_ui' => [
            'name' => 'Interface de scan passeport',
            'description' => 'Test différentes interfaces pour le scan de passeport',
            'metric' => 'passport_scan_success',
            'active' => true,
            'variants' => [
                'upload_first' => [
                    'weight' => 50,
                    'ui_mode' => 'upload',
                    'description' => 'Upload de fichier en premier (classique)'
                ],
                'camera_first' => [
                    'weight' => 50,
                    'ui_mode' => 'camera',
                    'description' => 'Caméra en premier (mobile-first)'
                ]
            ]
        ],
        
        /**
         * Test des quick actions
         * Objectif: Maximiser l'utilisation des boutons rapides
         */
        'quick_actions_style' => [
            'name' => 'Style des actions rapides',
            'description' => 'Test différents styles de boutons d\'action',
            'metric' => 'quick_action_clicks',
            'active' => true,
            'variants' => [
                'text_only' => [
                    'weight' => 34,
                    'style' => 'text',
                    'description' => 'Texte seul'
                ],
                'with_emoji' => [
                    'weight' => 33,
                    'style' => 'emoji',
                    'description' => 'Avec emojis'
                ],
                'with_icons' => [
                    'weight' => 33,
                    'style' => 'icons',
                    'description' => 'Avec icônes SVG'
                ]
            ]
        ],
        
        /**
         * Test de l'option Express
         * Objectif: Maximiser les conversions express
         */
        'express_option_placement' => [
            'name' => 'Placement option Express',
            'description' => 'Test différents moments pour proposer l\'option express',
            'metric' => 'express_conversion',
            'active' => false, // Désactivé par défaut
            'variants' => [
                'during_trip' => [
                    'weight' => 50,
                    'placement' => 'trip_step',
                    'description' => 'Pendant l\'étape voyage'
                ],
                'at_confirmation' => [
                    'weight' => 50,
                    'placement' => 'confirmation_step',
                    'description' => 'À la confirmation'
                ]
            ]
        ],
        
        /**
         * Test du récapitulatif
         * Objectif: Maximiser les confirmations finales
         */
        'confirmation_layout' => [
            'name' => 'Layout de confirmation',
            'description' => 'Test différentes présentations du récapitulatif',
            'metric' => 'final_confirmation',
            'active' => true,
            'variants' => [
                'compact' => [
                    'weight' => 50,
                    'layout' => 'compact',
                    'description' => 'Vue compacte'
                ],
                'detailed' => [
                    'weight' => 50,
                    'layout' => 'detailed',
                    'description' => 'Vue détaillée avec toutes les infos'
                ]
            ]
        ],
        
        /**
         * Test du CTA principal
         * Objectif: Maximiser le clic sur le bouton de confirmation
         */
        'cta_text' => [
            'name' => 'Texte du bouton de confirmation',
            'description' => 'Test différents libellés pour le bouton final',
            'metric' => 'final_confirmation',
            'active' => true,
            'variants' => [
                'confirm' => [
                    'weight' => 25,
                    'text' => ['fr' => 'Confirmer', 'en' => 'Confirm'],
                    'description' => 'Texte simple'
                ],
                'submit_application' => [
                    'weight' => 25,
                    'text' => ['fr' => 'Soumettre ma demande', 'en' => 'Submit my application'],
                    'description' => 'Action claire'
                ],
                'validate_continue' => [
                    'weight' => 25,
                    'text' => ['fr' => 'Valider et continuer', 'en' => 'Validate and continue'],
                    'description' => 'Progression'
                ],
                'finish' => [
                    'weight' => 25,
                    'text' => ['fr' => '✓ Terminer ma demande', 'en' => '✓ Finish my application'],
                    'description' => 'Avec checkmark'
                ]
            ]
        ]
    ],
    
    /**
     * Configuration globale
     */
    'settings' => [
        // Nombre minimum d'expositions avant d'afficher les résultats
        'min_exposures_for_results' => 30,
        
        // Durée de conservation des assignations (jours)
        'assignment_retention_days' => 30,
        
        // Exclure certaines sessions (admin, test, etc.)
        'excluded_session_patterns' => [
            '/^test_/',
            '/^admin_/'
        ]
    ]
];

