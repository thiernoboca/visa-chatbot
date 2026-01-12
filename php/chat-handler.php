<?php
/**
 * API Handler - Chatbot Visa CI
 * Point d'entrée principal pour les requêtes du frontend
 *
 * ARCHITECTURE TRIPLE LAYER:
 * - Layer 1: Google Vision (OCR) - Extraction texte brut
 * - Layer 2: Gemini Flash (Conversation) - Réponses synchrones rapides
 * - Layer 3: Claude Sonnet (Superviseur) - Validation asynchrone
 *
 * @package VisaChatbot
 * @version 2.0.0 - Triple Layer Architecture
 */

// Augmenter la limite mémoire pour les gros PDFs/images
ini_set('memory_limit', '256M');

// Headers CORS et JSON - UNIQUEMENT si appelé directement
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Session-ID, X-Enable-Claude-Validation');

    // Gérer les requêtes OPTIONS (preflight)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// Charger les dépendances
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session-manager.php';

require_once __DIR__ . '/proactive-suggestions.php';

require_once __DIR__ . '/workflow-engine.php';
require_once __DIR__ . '/document-extractor.php';  // Pour DOCUMENT_TYPES (constantes)
require_once __DIR__ . '/services/OCRIntegrationService.php';  // Triple Layer OCR
require_once __DIR__ . '/cross-validator.php';
require_once __DIR__ . '/conversation-engine.php';

use VisaChatbot\Services\OCRIntegrationService;

/**
 * Classe principale du handler
 * Orchestre le flux Triple Layer pour chaque requête
 */
class ChatHandler {
    
    private SessionManager $session;
    private WorkflowEngine $workflow;
    private ConversationEngine $conversationEngine;
    private ProactiveSuggestions $suggestions;
    
    /**
     * Layer utilisé pour la requête courante
     */
    private string $currentLayer = 'none';
    
    /**
     * Traces du workflow pour la requête courante
     */
    private array $workflowTrace = [];
    
    /**
     * Validation Claude en attente (Layer 3 async)
     */
    private ?array $pendingClaudeValidation = null;
    
    /**
     * Traite la requête avec orchestration Triple Layer
     */
    public function handle(): void {
        $startTime = microtime(true);
        
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            
            $this->addWorkflowTrace('request_start', "Handling {$method} request");
            
            if ($method === 'GET') {
                $this->handleGet();
            } elseif ($method === 'POST') {
                $this->handlePost();
            } else {
                $this->error('Méthode non supportée', 405);
            }
        } catch (Exception $e) {
            $this->addWorkflowTrace('error', $e->getMessage());
            $this->error($e->getMessage(), 500);
        } finally {
            // Layer 3: Exécuter la validation Claude en arrière-plan après la réponse
            $this->executeAsyncClaudeValidation();
        }
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
     * Définit le layer actif
     */
    private function setCurrentLayer(string $layer): void {
        $this->currentLayer = $layer;
        $this->addWorkflowTrace('layer_switch', "Switched to {$layer}");
    }
    
    /**
     * Programme une validation Claude asynchrone (Layer 3)
     *
     * IMPORTANT: La validation async est pour AUDIT uniquement.
     * Elle ne doit PAS modifier le statut de blocage de la session.
     * La validation sync (DocumentCoherenceValidator) est AUTORITATIVE.
     *
     * @param string $type Type de validation
     * @param array $data Données à valider
     * @param bool $auditOnly Si true, ne peut pas changer le statut (défaut: true)
     */
    private function scheduleClaudeValidation(string $type, array $data, bool $auditOnly = true): void {
        // Récupérer le résultat sync s'il existe
        $syncResult = $this->session->getCollectedField('coherence_result');

        $this->pendingClaudeValidation = [
            'type' => $type,
            'data' => $data,
            'session_id' => $this->session->getSessionId(),
            'timestamp' => date('c'),
            // Validation sync/async sync
            'validation_mode' => $auditOnly ? 'audit_only' : 'authoritative',
            'sync_validation_performed' => $syncResult !== null,
            'sync_validation_result' => $syncResult ? [
                'is_blocked' => $syncResult['is_blocked'] ?? false,
                'has_warnings' => $syncResult['has_warnings'] ?? false,
                'issues_count' => count($syncResult['issues'] ?? [])
            ] : null,
            // Priorité: sync est toujours prioritaire
            'priority' => 'sync_authoritative'
        ];
        $this->addWorkflowTrace('layer3_scheduled', "Claude validation scheduled for {$type} (mode: " . ($auditOnly ? 'audit' : 'authoritative') . ")");
    }

    /**
     * Exécute la validation Claude après la réponse HTTP (async-like)
     */
    private function executeAsyncClaudeValidation(): void {
        if ($this->pendingClaudeValidation === null) {
            return;
        }

        // En PHP/Apache, on peut utiliser fastcgi_finish_request() ou register_shutdown_function()
        // pour exécuter du code après avoir envoyé la réponse
        if (function_exists('fastcgi_finish_request')) {
            // Fermer la connexion HTTP avant de continuer
            fastcgi_finish_request();
        }

        // Stocker la validation pour traitement ultérieur (ou traitement immédiat si temps le permet)
        $this->storeClaudeValidationTask($this->pendingClaudeValidation);
    }

    /**
     * Stocke une tâche de validation Claude pour traitement async
     *
     * NOTE: Les tâches marquées 'audit_only' ne doivent PAS modifier le statut
     * de blocage de la session. Elles servent uniquement pour:
     * - Génération de rapports d'audit
     * - Détection de fraude pour analyse manuelle
     * - Amélioration continue des règles de validation
     */
    private function storeClaudeValidationTask(array $task): void {
        $alertsDir = dirname(__DIR__) . '/data/pending_validations';
        if (!is_dir($alertsDir)) {
            @mkdir($alertsDir, 0755, true);
        }

        // Ajouter métadonnées de traitement
        $task['processing_instructions'] = [
            'can_block_session' => $task['validation_mode'] !== 'audit_only',
            'sync_is_authoritative' => true,
            'on_conflict' => 'log_and_alert_admin', // Ne pas changer le statut sync
            'created_by' => 'chat-handler-v6.1'
        ];

        $filename = $alertsDir . '/' . $task['session_id'] . '_' . time() . '.json';
        file_put_contents($filename, json_encode($task, JSON_PRETTY_PRINT));

        $this->addWorkflowTrace('layer3_stored', "Validation task stored: {$filename}");
    }
    
    /**
     * Gère les requêtes GET (initialisation, état)
     */
    private function handleGet(): void {
        $action = $_GET['action'] ?? 'init';
        $sessionId = $_GET['session_id'] ?? $_SERVER['HTTP_X_SESSION_ID'] ?? null;

        // Reset session if requested (for testing/prototyping)
        if (isset($_GET['reset']) && $_GET['reset'] === '1') {
            if ($sessionId) {
                SessionManager::destroy($sessionId);
            }
            $sessionId = null; // Force new session
        }

        $this->session = new SessionManager($sessionId);
        $this->workflow = new WorkflowEngine($this->session);
        $this->conversationEngine = new ConversationEngine(['debug' => false]);
        $this->suggestions = new ProactiveSuggestions($this->session->getCollectedData());

        switch ($action) {
            case 'init':
                $this->initSession();
                break;
                
            case 'status':
                $this->getStatus();
                break;
                
            case 'history':
                $this->getHistory();
                break;
                
            case 'suggestions':
                $this->getSuggestions();
                break;
                
            default:
                $this->error('Action non reconnue', 400);
        }
    }
    
    /**
     * Retourne les suggestions proactives pour l'étape actuelle
     */
    private function getSuggestions(): void {
        $step = $this->session->getCurrentStep();
        $lang = $this->session->getLanguage();
        
        $this->suggestions->setContext($this->session->getCollectedData());
        $suggestions = $this->suggestions->getSuggestions($step, $lang);
        
        $this->success([
            'session_id' => $this->session->getSessionId(),
            'step' => $step,
            'suggestions' => $suggestions
        ]);
    }
    
    /**
     * Gère les requêtes POST (messages, fichiers)
     */
    private function handlePost(): void {
        // Récupérer les données
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?? [];
        
        $action = $data['action'] ?? 'message';
        $sessionId = $data['session_id'] ?? $_SERVER['HTTP_X_SESSION_ID'] ?? null;
        
        $this->session = new SessionManager($sessionId);
        $this->workflow = new WorkflowEngine($this->session);
        $this->conversationEngine = new ConversationEngine(['debug' => false]);
        $this->suggestions = new ProactiveSuggestions($this->session->getCollectedData());
        
        switch ($action) {
            case 'message':
                $this->processMessage($data);
                break;
                
            case 'passport_ocr':
                $this->processPassportOCR($data);
                break;
                
            case 'file_upload':
                $this->processFileUpload($data);
                break;
                
            case 'navigate':
                $this->navigateToStep($data);
                break;
                
            case 'reset':
                $this->resetSession();
                break;
                
            case 'extract_document':
                $this->extractDocument($data);
                break;
                
            case 'extract_batch':
                $this->extractBatch($data);
                break;
                
            case 'validate_documents':
                $this->validateDocuments($data);
                break;

            case 'unblock':
                $this->unblockSession($data);
                break;

            case 'get_block_info':
                $this->getBlockInfo();
                break;

            default:
                $this->error('Action non reconnue', 400);
        }
    }

    /**
     * Débloque une session bloquée
     */
    private function unblockSession(array $data): void {
        $reason = $data['reason'] ?? 'user_requested';
        $returnToStep = $data['return_to_step'] ?? null;

        // Vérifier si la session peut être débloquée
        $canUnblock = $this->session->canUnblock();

        if (!$canUnblock['can_unblock']) {
            $this->error($this->session->getLanguage() === 'fr'
                ? "Cette session ne peut pas être débloquée: " . $canUnblock['reason']
                : "This session cannot be unblocked: " . $canUnblock['reason'],
                403
            );
            return;
        }

        // Tenter le déblocage
        $success = $this->session->unblock($reason, $returnToStep);

        if ($success) {
            $this->success([
                'session_id' => $this->session->getSessionId(),
                'status' => 'active',
                'current_step' => $this->session->getCurrentStep(),
                'message' => $this->session->getLanguage() === 'fr'
                    ? "Session réactivée. Vous pouvez continuer votre demande."
                    : "Session reactivated. You can continue your application.",
                'step_info' => $this->session->getStepInfo()
            ]);
        } else {
            $this->error($this->session->getLanguage() === 'fr'
                ? "Impossible de débloquer la session"
                : "Unable to unblock session",
                500
            );
        }
    }

    /**
     * Retourne les informations de blocage de la session
     */
    private function getBlockInfo(): void {
        $blockInfo = $this->session->getBlockInfo();
        $canUnblock = $this->session->canUnblock();

        $this->success([
            'session_id' => $this->session->getSessionId(),
            'status' => $this->session->getStatus(),
            'is_blocked' => $this->session->getStatus() === SessionManager::STATUS_BLOCKED,
            'block_info' => $blockInfo,
            'can_unblock' => $canUnblock,
            'unblock_history' => $this->session->getUnblockHistory()
        ]);
    }
    
    /**
     * Initialise une nouvelle session ou retourne la session existante
     * Si la session a été pré-créée par ChatbotController, on ne réinitialise pas
     */
    private function initSession(): void {
        // Set language from URL parameter if provided
        $lang = $_GET['lang'] ?? null;
        if ($lang && in_array($lang, ['fr', 'en'])) {
            $this->session->setLanguage($lang);
            $this->workflow = new WorkflowEngine($this->session); // Reinit with new language
        }

        // Vérifier si la session a déjà été initialisée (pré-chargée par le contrôleur)
        $chatHistory = $this->session->getChatHistory();
        if (!empty($chatHistory)) {
            // Session déjà active - retourner les données existantes
            $lastMessage = end($chatHistory);
            $this->success([
                'session_id' => $this->session->getSessionId(),
                'messages' => $this->session->getRecentMessages(10),
                'message' => $lastMessage,
                'quick_actions' => $lastMessage['metadata']['quick_actions'] ?? [],
                'step_info' => $this->session->getStepInfo(),
                'status' => $this->session->getStatus(),
                'already_initialized' => true,
                'preloaded' => true
            ]);
            return;
        }

        // Nouvelle session - obtenir le message initial
        $response = $this->workflow->getStepInitialMessage();

        // Ajouter le message à l'historique
        $message = $this->session->addMessage('assistant', $response['message'], [
            'quick_actions' => $response['quick_actions'],
            'step' => $response['step']
        ]);

        $this->success([
            'session_id' => $this->session->getSessionId(),
            'message' => $message,
            'quick_actions' => $response['quick_actions'],
            'step_info' => $response['step_info'],
            'status' => $this->session->getStatus(),
            'already_initialized' => false
        ]);
    }
    
    /**
     * Retourne le statut de la session
     */
    private function getStatus(): void {
        $this->success([
            'session_id' => $this->session->getSessionId(),
            'status' => $this->session->getStatus(),
            'step_info' => $this->session->getStepInfo(),
            'workflow_category' => $this->session->getWorkflowCategory(),
            'language' => $this->session->getLanguage()
        ]);
    }
    
    /**
     * Retourne l'historique des messages
     */
    private function getHistory(): void {
        $limit = (int)($_GET['limit'] ?? 50);
        
        $this->success([
            'session_id' => $this->session->getSessionId(),
            'messages' => $this->session->getRecentMessages($limit),
            'step_info' => $this->session->getStepInfo()
        ]);
    }
    
    /**
     * Traite un message utilisateur
     * Layer 2 (Gemini): NLU + Génération de réponse (sync)
     * Layer 3 (Claude): Validation anomalies si détectées (async)
     */
    private function processMessage(array $data): void {
        $userMessage = $data['message'] ?? '';
        $metadata = $data['metadata'] ?? [];
        
        if (empty($userMessage) && empty($metadata)) {
            $this->error('Message vide', 400);
            return;
        }
        
        $this->addWorkflowTrace('message_processing', 'Processing user message', [
            'message_length' => strlen($userMessage)
        ]);
        
        // Ajouter le message utilisateur à l'historique
        $userMsg = $this->session->addMessage('user', $userMessage, $metadata);
        
        // Layer 2: Utiliser Gemini/NLU pour comprendre l'intention
        $nluResult = null;
        if (!empty($userMessage) && strlen($userMessage) > 2) {
            $this->setCurrentLayer('layer2_nlu');
            $nluResult = $this->conversationEngine->understandIntent($userMessage, [
                'current_step' => $this->session->getCurrentStep(),
                'language' => $this->session->getLanguage()
            ]);
            
            // Record Gemini usage in session
            if (($nluResult['source'] ?? '') === 'gemini') {
                $this->session->recordGeminiUsage('nlu');
            }
            
            // Add trace to session
            $this->session->addTripleLayerTrace('layer2', 'nlu_analysis', [
                'intent' => $nluResult['intent'] ?? 'unknown',
                'confidence' => $nluResult['confidence'] ?? 0,
                'source' => $nluResult['source'] ?? 'claude_fallback'
            ]);
            
            $this->addWorkflowTrace('nlu_complete', 'Intent understood', [
                'intent' => $nluResult['intent'] ?? 'unknown',
                'confidence' => $nluResult['confidence'] ?? 0,
                'source' => $nluResult['source'] ?? 'claude_fallback'
            ]);
            
            // Layer 3: Si confiance faible, programmer validation Claude
            if (($nluResult['confidence'] ?? 1) < 0.7 || ($nluResult['needs_clarification'] ?? false)) {
                $this->scheduleClaudeValidation('nlu_low_confidence', [
                    'user_message' => $userMessage,
                    'nlu_result' => $nluResult,
                    'step' => $this->session->getCurrentStep()
                ]);
            }
        }
        
        // Layer 2: Traiter avec le workflow engine (réponse Gemini)
        $this->setCurrentLayer('layer2_response');
        $response = $this->workflow->processUserInput($userMessage, array_merge($metadata, [
            'nlu_result' => $nluResult
        ]));
        
        $this->addWorkflowTrace('workflow_response', 'Response generated', [
            'step' => $response['step'],
            'has_quick_actions' => !empty($response['quick_actions'])
        ]);
        
        // Ajouter la réponse du bot à l'historique
        $botMsg = $this->session->addMessage('assistant', $response['message'], [
            'quick_actions' => $response['quick_actions'],
            'step' => $response['step'],
            'metadata' => $response['metadata']
        ]);
        
        // Enrichir les metadata avec les suggestions proactives
        $enrichedMetadata = $this->enrichMetadataWithSuggestions($response);
        
        // Ajouter info Triple Layer
        $enrichedMetadata['nlu_source'] = $nluResult['source'] ?? 'none';
        
        $this->success([
            'session_id' => $this->session->getSessionId(),
            'user_message' => $userMsg,
            'bot_message' => $botMsg,
            'quick_actions' => $response['quick_actions'],
            'step_info' => $response['step_info'],
            'workflow_category' => $response['workflow_category'],
            'metadata' => $enrichedMetadata,
            'status' => $this->session->getStatus(),
            'language' => $this->session->getLanguage()
        ]);
    }

    /**
     * Enrichit les metadata avec les suggestions proactives
     */
    private function enrichMetadataWithSuggestions(array $response): array {
        $metadata = $response['metadata'] ?? [];
        $step = $response['step'] ?? $this->session->getCurrentStep();
        $lang = $this->session->getLanguage();
        $progress = $response['step_info']['progress'] ?? 0;
        
        // Mettre à jour le contexte des suggestions
        $this->suggestions->setContext($this->session->getCollectedData());
        $suggestions = $this->suggestions->getSuggestions($step, $lang);
        
        // Ajouter un tip proactif si disponible
        if (!empty($suggestions['tips']) && count($suggestions['tips']) > 0) {
            $metadata['proactive_tip'] = $suggestions['tips'][0];
        }
        
        // Ajouter une alerte si nécessaire
        if (!empty($suggestions['alerts']) && count($suggestions['alerts']) > 0) {
            $metadata['proactive_warning'] = $suggestions['alerts'][0];
        }
        
        // Détecter les milestones de progression
        $progressMilestones = [0.25, 0.5, 0.75, 0.9];
        foreach ($progressMilestones as $milestone) {
            if (abs($progress / 100 - $milestone) < 0.05) {
                $metadata['progress_milestone'] = $milestone;
                break;
            }
        }
        
        // Extraire le prénom si disponible
        $firstName = $this->session->getCollectedField('given_names');
        if ($firstName) {
            $metadata['user_firstname'] = explode(' ', $firstName)[0];
        }
        
        // Détecter les milestones spéciaux
        $workflowCategory = $response['workflow_category'] ?? $this->session->getWorkflowCategory();
        if ($workflowCategory === 'DIPLOMATIQUE' && !isset($metadata['is_diplomatic'])) {
            $metadata['is_diplomatic'] = true;
        }
        if (in_array($workflowCategory, ['DIPLOMATIQUE', 'LP_ONU', 'LP_UA'])) {
            $metadata['is_priority'] = true;
        }
        
        return $metadata;
    }
    
    /**
     * Traite les données OCR du passeport
     * Critical: Détection du type de passeport détermine le workflow
     * Layer 3 validation automatique pour les passeports diplomatiques
     */
    private function processPassportOCR(array $data): void {
        $ocrData = $data['ocr_data'] ?? [];
        
        if (empty($ocrData)) {
            $this->error('Données OCR manquantes', 400);
            return;
        }
        
        $this->addWorkflowTrace('passport_ocr_processing', 'Processing passport OCR data', [
            'has_mrz' => isset($ocrData['mrz']),
            'passport_type' => $ocrData['fields']['passport_type']['value'] ?? 'unknown'
        ]);
        
        // Ajouter un message système pour l'upload
        $this->session->addMessage('user', '[Passeport scanné]', [
            'type' => 'file_upload',
            'file_type' => 'passport'
        ]);
        
        // Layer 2: Traiter les données OCR via le workflow
        $this->setCurrentLayer('layer2_workflow');
        $response = $this->workflow->processPassportData($ocrData);
        
        // Layer 3: Validation critique pour passeports diplomatiques/service
        $passportType = $response['metadata']['passport_type'] ?? 'ORDINAIRE';
        $isDiplomatic = in_array($passportType, ['DIPLOMATIQUE', 'SERVICE', 'LP_ONU', 'LP_UA']);
        
        // Add session traces for passport processing
        $this->session->addTripleLayerTrace('layer2', 'passport_processing', [
            'passport_type' => $passportType,
            'workflow_category' => $response['workflow_category'],
            'is_diplomatic' => $isDiplomatic
        ]);
        
        if ($isDiplomatic) {
            $this->scheduleClaudeValidation('diplomatic_passport_verification', [
                'passport_type' => $passportType,
                'workflow_category' => $response['workflow_category'],
                'ocr_confidence' => $ocrData['_metadata']['ocr_confidence'] ?? 0,
                'mrz_data' => $ocrData['mrz'] ?? null
            ]);
            $this->addWorkflowTrace('layer3_diplomatic_check', 'Claude validation scheduled for diplomatic passport');
            
            // Add Layer 3 trace to session
            $this->session->addTripleLayerTrace('layer3', 'diplomatic_validation_scheduled', [
                'passport_type' => $passportType
            ]);
        }
        
        // Ajouter la réponse du bot
        $botMsg = $this->session->addMessage('assistant', $response['message'], [
            'quick_actions' => $response['quick_actions'],
            'step' => $response['step'],
            'metadata' => $response['metadata']
        ]);
        
        $this->success([
            'session_id' => $this->session->getSessionId(),
            'bot_message' => $botMsg,
            'quick_actions' => $response['quick_actions'],
            'step_info' => $response['step_info'],
            'workflow_category' => $response['workflow_category'],
            'passport_type' => $passportType,
            'is_free' => $response['metadata']['is_free'] ?? false,
            'is_diplomatic' => $isDiplomatic,
            'status' => $this->session->getStatus()
        ]);
    }
    
    /**
     * Traite un upload de fichier générique
     */
    private function processFileUpload(array $data): void {
        $fileType = $data['file_type'] ?? 'document';
        $filePath = $data['file_path'] ?? null;
        
        // Ajouter un message pour l'upload
        $fileLabels = [
            'photo' => 'Photo d\'identité',
            'vaccination' => 'Carnet de vaccination',
            'invitation' => 'Lettre d\'invitation',
            'accommodation' => 'Justificatif d\'hébergement',
            'financial' => 'Justificatif de ressources'
        ];
        
        $label = $fileLabels[$fileType] ?? 'Document';
        
        $this->session->addMessage('user', "[$label uploadé]", [
            'type' => 'file_upload',
            'file_type' => $fileType
        ]);
        
        // Continuer le workflow
        $response = $this->workflow->processUserInput('', [
            'file_uploaded' => true,
            'file_type' => $fileType,
            'file_path' => $filePath
        ]);
        
        $botMsg = $this->session->addMessage('assistant', $response['message'], [
            'quick_actions' => $response['quick_actions'],
            'step' => $response['step']
        ]);
        
        $this->success([
            'session_id' => $this->session->getSessionId(),
            'bot_message' => $botMsg,
            'quick_actions' => $response['quick_actions'],
            'step_info' => $response['step_info'],
            'status' => $this->session->getStatus()
        ]);
    }
    
    /**
     * Navigue vers une étape spécifique
     */
    private function navigateToStep(array $data): void {
        $targetStep = $data['target_step'] ?? null;
        
        if (empty($targetStep)) {
            $this->error('Étape cible manquante', 400);
            return;
        }
        
        // Naviguer vers l'étape
        $response = $this->workflow->navigateToStep($targetStep);
        
        // Ajouter un message système pour la navigation
        $this->session->addMessage('system', "[Navigation vers: $targetStep]", [
            'type' => 'navigation',
            'target_step' => $targetStep
        ]);
        
        // Ajouter la réponse du bot
        $botMsg = $this->session->addMessage('assistant', $response['message'], [
            'quick_actions' => $response['quick_actions'],
            'step' => $response['step']
        ]);
        
        $this->success([
            'session_id' => $this->session->getSessionId(),
            'bot_message' => $botMsg,
            'quick_actions' => $response['quick_actions'],
            'step_info' => $response['step_info'],
            'workflow_category' => $response['workflow_category'],
            'status' => $this->session->getStatus()
        ]);
    }
    
    /**
     * Réinitialise la session
     */
    private function resetSession(): void {
        $this->session->reset();
        $this->workflow = new WorkflowEngine($this->session);
        
        // Obtenir le message initial
        $response = $this->workflow->getStepInitialMessage();
        
        $message = $this->session->addMessage('assistant', $response['message'], [
            'quick_actions' => $response['quick_actions'],
            'step' => $response['step']
        ]);
        
        $this->success([
            'session_id' => $this->session->getSessionId(),
            'message' => $message,
            'quick_actions' => $response['quick_actions'],
            'step_info' => $response['step_info'],
            'status' => $this->session->getStatus()
        ]);
    }
    
    /**
     * Extrait les données d'un document unique
     * Pipeline Triple Layer:
     * - Layer 1: Google Vision OCR (sync)
     * - Layer 2: Gemini structuration (sync)
     * - Layer 3: Claude validation (async, optionnel)
     */
    private function extractDocument(array $data): void {
        // Support both parameter formats (legacy and new conversational)
        $type = $data['type'] ?? $data['document_type'] ?? null;
        $content = $data['content'] ?? $data['file_data'] ?? null;
        $mimeType = $data['mime_type'] ?? null;
        $enableClaudeValidation = $data['validate'] ?? ($_SERVER['HTTP_X_ENABLE_CLAUDE_VALIDATION'] ?? false);

        // Retirer le préfixe data URI si présent
        if ($content && preg_match('/^data:[^;]+;base64,(.+)$/', $content, $matches)) {
            $content = $matches[1];
        }

        if (empty($type) || empty($content) || empty($mimeType)) {
            $this->error('Paramètres manquants: type/document_type, content/file_data, mime_type requis', 400);
            return;
        }
        
        try {
            $this->addWorkflowTrace('extraction_start', "Starting {$type} extraction");

            // Triple Layer OCR via OCRIntegrationService
            $this->setCurrentLayer('layer1_layer2');
            $ocrService = new OCRIntegrationService([
                'debug' => false,
                'use_claude_validation' => (bool)$enableClaudeValidation
            ]);
            $result = $ocrService->processDocument($type, $content, $mimeType, [
                'validate_with_claude' => (bool)$enableClaudeValidation
            ]);

            // Récupérer les traces Triple Layer
            $processingInfo = $result['_processing'] ?? [];
            $this->addWorkflowTrace('extraction_complete', 'Document extracted', [
                'triple_layer_traces' => $processingInfo['triple_layer'] ?? []
            ]);

            // Add Triple Layer traces to session
            $layers = $processingInfo['triple_layer'] ?? [];
            $layer2Provider = $layers['layer2']['provider'] ?? 'unknown';
            $this->session->addTripleLayerTrace('layer1', 'ocr_extraction', [
                'document_type' => $type,
                'confidence' => $result['confidence'] ?? 0
            ]);
            $this->session->addTripleLayerTrace('layer2', 'document_structuration', [
                'document_type' => $type,
                'source' => $layer2Provider
            ]);

            if ($layer2Provider === 'gemini_flash') {
                $this->session->recordGeminiUsage('extraction');
            }

            // Sauvegarder dans la session
            $this->session->setCollectedField("extracted_{$type}", $result);

            // Layer 3: Programmer validation Claude async pour documents critiques
            if ($type === 'passport' && !$enableClaudeValidation) {
                $this->scheduleClaudeValidation('passport_type_detection', [
                    'passport_type' => $result['fields']['passport_type']['value'] ?? null,
                    'mrz' => $result['mrz'] ?? null,
                    'layer2_source' => $layer2Provider
                ]);
            }

            // Ajouter un message système
            $docInfo = DocumentExtractor::DOCUMENT_TYPES[$type] ?? ['name' => $type];
            $this->session->addMessage('system', "[{$docInfo['name']} extrait]", [
                'type' => 'document_extraction',
                'document_type' => $type,
                'confidence' => $result['confidence'] ?? 0,
                'triple_layer' => $processingInfo['triple_layer'] ?? null
            ]);

            $this->success([
                'session_id' => $this->session->getSessionId(),
                'document_type' => $type,
                'extracted_data' => $result,
                'triple_layer_info' => $processingInfo['triple_layer'] ?? null,
                'status' => 'success'
            ]);
        } catch (Exception $e) {
            $this->addWorkflowTrace('extraction_error', $e->getMessage());
            $this->error('Erreur extraction: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Extrait les données de plusieurs documents en batch
     * Pipeline Triple Layer pour chaque document
     */
    private function extractBatch(array $data): void {
        $documents = $data['documents'] ?? [];
        $enableClaudeValidation = $data['validate'] ?? false;
        
        if (empty($documents) || !is_array($documents)) {
            $this->error('Paramètre documents manquant ou invalide', 400);
            return;
        }
        
        try {
            $this->addWorkflowTrace('batch_extraction_start', 'Starting batch extraction', [
                'document_count' => count($documents)
            ]);

            $this->setCurrentLayer('layer1_layer2');
            $ocrService = new OCRIntegrationService(['debug' => false]);

            // Traiter chaque document via Triple Layer OCR
            $batchResults = ['results' => [], 'success_count' => 0, 'error_count' => 0];
            foreach ($documents as $doc) {
                $docType = $doc['type'] ?? $doc['document_type'] ?? 'unknown';
                $content = $doc['content'] ?? $doc['file_data'] ?? '';
                $docMimeType = $doc['mime_type'] ?? 'image/jpeg';

                try {
                    $result = $ocrService->processDocument($docType, $content, $docMimeType);
                    $batchResults['results'][$docType] = $result;
                    $batchResults['success_count']++;
                } catch (Exception $docError) {
                    $batchResults['results'][$docType] = ['error' => $docError->getMessage()];
                    $batchResults['error_count']++;
                }
            }

            // Sauvegarder chaque résultat dans la session
            $tripleLayerSummary = [];
            foreach ($batchResults['results'] as $type => $result) {
                if (!isset($result['error'])) {
                    $this->session->setCollectedField("extracted_{$type}", $result);
                    $tripleLayerSummary[$type] = $result['_processing']['triple_layer'] ?? null;
                }
            }

            $this->addWorkflowTrace('batch_extraction_complete', 'Batch extraction done', [
                'summary' => $tripleLayerSummary
            ]);

            // Effectuer les validations croisées automatiquement
            $validator = new CrossValidator($batchResults['results']);
            $validations = $validator->validateAll();

            // Sauvegarder les validations
            $this->session->setCollectedField('cross_validations', $validations);

            // Layer 3: Programmer validation Claude async pour le batch complet
            if (count($batchResults['results']) > 0 && !$enableClaudeValidation) {
                $this->scheduleClaudeValidation('batch_coherence', [
                    'documents_extracted' => array_keys($batchResults['results']),
                    'cross_validation_score' => $validations['coherence_score'] ?? 0,
                    'summary' => $validations['summary'] ?? []
                ]);
            }

            $this->success([
                'session_id' => $this->session->getSessionId(),
                'extraction_results' => $batchResults,
                'cross_validation' => $validations,
                'triple_layer_summary' => $tripleLayerSummary,
                'status' => 'success'
            ]);
        } catch (Exception $e) {
            $this->addWorkflowTrace('batch_extraction_error', $e->getMessage());
            $this->error('Erreur extraction batch: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Valide la cohérence entre les documents extraits
     */
    private function validateDocuments(array $data): void {
        // Récupérer les documents depuis les données ou la session
        $documents = $data['documents'] ?? [];
        
        // Si pas de documents fournis, utiliser ceux de la session
        if (empty($documents)) {
            $documentTypes = ['passport', 'ticket', 'hotel', 'vaccination', 'invitation', 'verbal_note'];
            foreach ($documentTypes as $type) {
                $extracted = $this->session->getCollectedField("extracted_{$type}");
                if ($extracted) {
                    $documents[$type] = $extracted;
                }
            }
        }
        
        if (empty($documents)) {
            $this->error('Aucun document à valider', 400);
            return;
        }
        
        try {
            $validator = new CrossValidator($documents);
            $validations = $validator->validateAll();
            
            // Sauvegarder les validations dans la session
            $this->session->setCollectedField('cross_validations', $validations);
            
            $this->success([
                'session_id' => $this->session->getSessionId(),
                'validations' => $validations['validations'],
                'coherence_score' => $validations['coherence_score'],
                'summary' => $validations['summary'],
                'status' => 'success'
            ]);
        } catch (Exception $e) {
            $this->error('Erreur validation: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Retourne les types de documents supportés
     */
    public static function getDocumentTypes(): array {
        return DocumentExtractor::DOCUMENT_TYPES;
    }
    
    /**
     * Retourne une réponse de succès avec metadata Triple Layer
     */
    private function success(array $data): void {
        // Ajouter le header X-AI-Layer pour identifier la couche utilisée
        header('X-AI-Layer: ' . $this->currentLayer);
        header('X-Triple-Layer-Enabled: true');
        
        // Enrichir la réponse avec les traces du workflow
        $response = [
            'success' => true,
            'data' => $data,
            '_triple_layer' => [
                'current_layer' => $this->currentLayer,
                'gemini_available' => $this->conversationEngine->isGeminiActive(),
                'claude_validation_scheduled' => $this->pendingClaudeValidation !== null,
                'workflow_trace_count' => count($this->workflowTrace)
            ]
        ];
        
        // Inclure les traces complètes en mode debug
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $response['_workflow_trace'] = $this->workflowTrace;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Retourne une erreur
     */
    private function error(string $message, int $code = 400): void {
        http_response_code($code);
        header('X-AI-Layer: error');
        
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => $code,
            '_triple_layer' => [
                'current_layer' => $this->currentLayer,
                'workflow_trace_count' => count($this->workflowTrace)
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Exécuter le handler
// Exécuter le handler uniquement si appelé directement (pas via include)
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $handler = new ChatHandler();
    $handler->handle();
}

