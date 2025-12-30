<?php
/**
 * Configuration des Webhooks - Chatbot Visa CI
 * 
 * Ce fichier définit les webhooks enregistrés et leurs paramètres.
 * Il peut être modifié dynamiquement par WebhookDispatcher::registerWebhook()
 * 
 * @package VisaChatbot
 */

return [
    /**
     * Liste des webhooks enregistrés
     * 
     * Chaque webhook peut écouter un ou plusieurs événements:
     * - session.created      : Nouvelle session créée
     * - session.resumed      : Session reprise
     * - step.started         : Étape démarrée
     * - step.completed       : Étape complétée
     * - passport.scanned     : Passeport scanné avec succès
     * - document.uploaded    : Document téléversé
     * - validation.error     : Erreur de validation
     * - application.submitted: Demande soumise
     * - session.abandoned    : Session abandonnée
     * - session.completed    : Session terminée
     */
    'webhooks' => [
        // Exemple de webhook (désactivé par défaut)
        [
            'id' => 'example_webhook',
            'url' => 'https://example.com/api/visa-events',
            'events' => [
                'application.submitted',
                'session.abandoned'
            ],
            'secret' => 'change_me_to_a_secure_secret',
            'active' => false, // Activer pour utiliser
            'created_at' => 1703174400
        ],
        
        // Webhook pour CRM (exemple)
        [
            'id' => 'crm_integration',
            'url' => 'https://crm.ambassade-ci.org/api/webhooks/visa',
            'events' => [
                'application.submitted',
                'session.completed',
                'passport.scanned'
            ],
            'secret' => 'crm_webhook_secret_key',
            'active' => false,
            'created_at' => 1703174400
        ],
        
        // Webhook pour Analytics externe (exemple)
        [
            'id' => 'analytics_external',
            'url' => 'https://analytics.example.com/collect',
            'events' => [
                'step.completed',
                'validation.error',
                'session.abandoned'
            ],
            'secret' => 'analytics_webhook_secret',
            'active' => false,
            'created_at' => 1703174400
        ]
    ],
    
    /**
     * Configuration des retries
     */
    'retry' => [
        'max_attempts' => 3,           // Nombre max de tentatives
        'delay_seconds' => 60,         // Délai initial entre tentatives
        'backoff_multiplier' => 2      // Multiplicateur pour backoff exponentiel
    ],
    
    /**
     * Timeout des requêtes HTTP (secondes)
     */
    'timeout' => 10,
    
    /**
     * Activer/désactiver globalement les webhooks
     */
    'enabled' => true,
    
    /**
     * Headers personnalisés à ajouter à toutes les requêtes
     */
    'default_headers' => [
        'X-Source' => 'VisaChatbot-CI',
        'X-Version' => '1.0.0'
    ]
];

