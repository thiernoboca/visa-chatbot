<?php
/**
 * API Endpoints Registry
 * Central registry of all API endpoints
 *
 * Usage:
 *   This file maps API actions to their handler files.
 *   Include this in new api.php or use the existing handlers.
 *
 * @package VisaChatbot\API
 * @version 1.0.0
 */

namespace VisaChatbot\API;

/**
 * API Endpoints Configuration
 *
 * Structure:
 * - category: Logical grouping
 * - endpoints: Array of endpoints with:
 *   - path: URL path or action name
 *   - method: HTTP method
 *   - handler: File or class method
 *   - description: What the endpoint does
 */

return [
    'chat' => [
        'description' => 'Chat conversation endpoints',
        'handler_file' => 'chat-handler.php',
        'endpoints' => [
            [
                'action' => 'init',
                'method' => 'GET',
                'description' => 'Initialize chat session'
            ],
            [
                'action' => 'message',
                'method' => 'POST',
                'description' => 'Send user message'
            ],
            [
                'action' => 'navigate',
                'method' => 'POST',
                'description' => 'Navigate to step'
            ],
            [
                'action' => 'passport_ocr',
                'method' => 'POST',
                'description' => 'Process passport OCR data'
            ],
            [
                'action' => 'extract_document',
                'method' => 'POST',
                'description' => 'Extract document data'
            ],
            [
                'action' => 'validate_documents',
                'method' => 'POST',
                'description' => 'Cross-validate documents'
            ],
            [
                'action' => 'submit_application',
                'method' => 'POST',
                'description' => 'Submit visa application'
            ]
        ]
    ],

    'session' => [
        'description' => 'Session management',
        'handler_file' => 'session-manager.php',
        'endpoints' => [
            [
                'action' => 'create',
                'method' => 'POST',
                'description' => 'Create new session'
            ],
            [
                'action' => 'get',
                'method' => 'GET',
                'description' => 'Get session data'
            ],
            [
                'action' => 'update',
                'method' => 'POST',
                'description' => 'Update session data'
            ],
            [
                'action' => 'destroy',
                'method' => 'POST',
                'description' => 'Destroy session'
            ]
        ]
    ],

    'documents' => [
        'description' => 'Document handling',
        'handler_file' => 'document-upload-handler-v2.php',
        'endpoints' => [
            [
                'action' => 'upload',
                'method' => 'POST',
                'description' => 'Upload document'
            ],
            [
                'action' => 'extract',
                'method' => 'POST',
                'description' => 'Extract document data'
            ]
        ]
    ],

    'coherence' => [
        'description' => 'Document coherence validation',
        'handler_file' => 'coherence-validator-api.php',
        'endpoints' => [
            [
                'action' => 'validate',
                'method' => 'POST',
                'description' => 'Validate document coherence'
            ],
            [
                'action' => 'report',
                'method' => 'GET',
                'description' => 'Get validation report'
            ]
        ]
    ],

    'admin' => [
        'description' => 'Admin operations',
        'handler_file' => 'admin-api.php',
        'endpoints' => [
            [
                'action' => 'stats',
                'method' => 'GET',
                'description' => 'Get statistics'
            ],
            [
                'action' => 'sessions',
                'method' => 'GET',
                'description' => 'List sessions'
            ],
            [
                'action' => 'clear_cache',
                'method' => 'POST',
                'description' => 'Clear cache'
            ]
        ]
    ],

    'analytics' => [
        'description' => 'Analytics tracking',
        'handler_file' => 'analytics-service.php',
        'endpoints' => [
            [
                'action' => 'track',
                'method' => 'POST',
                'description' => 'Track event'
            ],
            [
                'action' => 'report',
                'method' => 'GET',
                'description' => 'Get analytics report'
            ]
        ]
    ],

    'drafts' => [
        'description' => 'Draft management',
        'handler_file' => 'draft-manager.php',
        'endpoints' => [
            [
                'action' => 'save',
                'method' => 'POST',
                'description' => 'Save draft'
            ],
            [
                'action' => 'load',
                'method' => 'GET',
                'description' => 'Load draft'
            ],
            [
                'action' => 'delete',
                'method' => 'DELETE',
                'description' => 'Delete draft'
            ]
        ]
    ],

    'cache' => [
        'description' => 'Cache operations',
        'handler_file' => 'cache-api.php',
        'endpoints' => [
            [
                'action' => 'clear',
                'method' => 'POST',
                'description' => 'Clear cache'
            ],
            [
                'action' => 'stats',
                'method' => 'GET',
                'description' => 'Cache statistics'
            ]
        ]
    ]
];
