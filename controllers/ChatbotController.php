<?php
/**
 * Contrôleur principal du Chatbot e-Visa
 * Gère les requêtes et le rendu des vues
 *
 * Intègre WorkflowEngine pour pré-charger les données au chargement de la page
 */

require_once __DIR__ . '/../php/services/GeolocationService.php';
require_once __DIR__ . '/../php/session-manager.php';
require_once __DIR__ . '/../php/proactive-suggestions.php';
require_once __DIR__ . '/../php/workflow-engine.php';
require_once __DIR__ . '/../php/chat-handler.php';
require_once __DIR__ . '/../php/services/OCRIntegrationService.php';
require_once __DIR__ . '/../php/services/DocumentCoherenceValidator.php';

use VisaChatbot\Services\OCRIntegrationService;
use VisaChatbot\Services\DocumentCoherenceValidator;

class ChatbotController {

    /**
     * Données partagées entre les vues
     */
    protected $data = [];

    /**
     * Service de géolocalisation
     */
    protected $geoService;

    /**
     * Gestionnaire de session
     */
    protected $session;

    /**
     * Moteur de workflow
     */
    protected $workflow;

    /**
     * Constructeur
     */
    public function __construct() {
        // Initialiser le service de géolocalisation
        $this->geoService = new GeolocationService();

        // Get session ID from URL param or cookie (reset is handled in index.php)
        $sessionId = $_GET['session_id'] ?? $_COOKIE['visa_session_id'] ?? null;

        // Initialiser la session (reprendre si existante via cookie)
        $this->session = new SessionManager($sessionId);

        // Initialiser le moteur de workflow
        $this->workflow = new WorkflowEngine($this->session);

        // Définir le cookie de session pour persistance
        $this->setSessionCookie();

        // Initialiser les données par défaut
        $this->data = [
            'title' => getConfig('app.name'),
            'embassy' => getConfig('app.embassy'),
            'language' => getCurrentLanguage(),
        ];
    }

    /**
     * Définit le cookie de session pour persistance
     */
    protected function setSessionCookie(): void {
        $sessionId = $this->session->getSessionId();
        $existingCookie = $_COOKIE['visa_session_id'] ?? null;

        if ($existingCookie !== $sessionId) {
            setcookie(
                'visa_session_id',
                $sessionId,
                [
                    'expires' => time() + 86400, // 24 heures
                    'path' => '/',
                    'secure' => isset($_SERVER['HTTPS']),
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
        }
    }

    /**
     * Action principale - Affiche le chatbot
     */
    public function index() {
        // Détecter la géolocalisation du demandeur via IP
        $geolocation = $this->detectGeolocation();

        // Préparer les données du workflow pré-chargées
        $workflowData = $this->prepareWorkflowData($geolocation);

        $this->render('pages/home', [
            'title' => getConfig('app.name'),
            'embassy' => getConfig('app.embassy'),
            'geolocation' => $geolocation,
            'workflowData' => $workflowData,
        ]);
    }

    /**
     * Prépare toutes les données du workflow pour le rendu initial
     *
     * @param array $geolocation Données de géolocalisation
     * @return array Données du workflow
     */
    protected function prepareWorkflowData(array $geolocation): array {
        try {
            // Synchroniser la géolocalisation avec la session si détectée
            if ($geolocation['detected'] && $geolocation['in_jurisdiction']) {
                $this->session->setCollectedField('detected_country', $geolocation['country_code']);
                $this->session->setCollectedField('detected_country_name', $geolocation['country_name']);
            }

            // Vérifier si la session a déjà un historique (reprise de session)
            $existingHistory = $this->session->getChatHistory();
            if (!empty($existingHistory)) {
                // Session existante - retourner les données sans réinitialiser
                $lastMessage = end($existingHistory);
                return [
                    'session_id' => $this->session->getSessionId(),
                    'language' => $this->session->getLanguage(),
                    'current_step' => $this->session->getCurrentStep(),
                    'step_info' => $this->session->getStepInfo(),
                    'initial_message' => [
                        'message' => $lastMessage['content'] ?? '',
                        'quick_actions' => $lastMessage['metadata']['quick_actions'] ?? [],
                        'step' => $lastMessage['metadata']['step'] ?? $this->session->getCurrentStep(),
                        'metadata' => $lastMessage['metadata'] ?? []
                    ],
                    'suggestions' => $this->workflow->getProactiveSuggestions(),
                    'processing_info' => $this->workflow->getProcessingInfo(),
                    'workflow_category' => $this->session->getWorkflowCategory(),
                    'status' => $this->session->getStatus(),
                    'collected_data' => $this->session->getCollectedData(),
                    'preloaded' => true,
                    'resumed' => true,
                ];
            }

            // Nouvelle session - obtenir le message initial de l'étape courante
            $initialMessage = $this->workflow->getStepInitialMessage();

            // Ajouter le message initial à l'historique de session
            // (pour que chat-handler.php détecte que la session est déjà initialisée)
            $this->session->addMessage('assistant', $initialMessage['message'], [
                'quick_actions' => $initialMessage['quick_actions'] ?? [],
                'step' => $initialMessage['step'] ?? 'welcome',
                'preloaded' => true
            ]);

            // Obtenir les suggestions proactives
            $suggestions = $this->workflow->getProactiveSuggestions();

            // Obtenir les infos de traitement (délais)
            $processingInfo = $this->workflow->getProcessingInfo();

            // Obtenir l'état de la session
            $stepInfo = $this->session->getStepInfo();

            return [
                'session_id' => $this->session->getSessionId(),
                'language' => $this->session->getLanguage(),
                'current_step' => $this->session->getCurrentStep(),
                'step_info' => $stepInfo,
                'initial_message' => $initialMessage,
                'suggestions' => $suggestions,
                'processing_info' => $processingInfo,
                'workflow_category' => $this->session->getWorkflowCategory(),
                'status' => $this->session->getStatus(),
                'collected_data' => $this->session->getCollectedData(),
                'preloaded' => true,
                'resumed' => false,
            ];
        } catch (Exception $e) {
            error_log("WorkflowData preparation failed: " . $e->getMessage());
            return [
                'preloaded' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Détecte la géolocalisation du demandeur via IP
     *
     * @return array Données de géolocalisation
     */
    protected function detectGeolocation(): array {
        try {
            $geoData = $this->geoService->detectCountry();

            return [
                'detected' => $geoData['success'] ?? false,
                'country_code' => $geoData['country_code'] ?? null,
                'country_name' => $geoData['country_name'] ?? null,
                'city' => $geoData['city'] ?? null,
                'in_jurisdiction' => $geoData['in_jurisdiction'] ?? false,
                'ip' => $geoData['ip'] ?? null,
            ];
        } catch (Exception $e) {
            error_log("Geolocation detection failed: " . $e->getMessage());
            return [
                'detected' => false,
                'country_code' => null,
                'country_name' => null,
                'city' => null,
                'in_jurisdiction' => false,
                'ip' => null,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Rendu d'une vue avec le layout principal
     * 
     * @param string $view Chemin de la vue relative à views/
     * @param array $data Données à passer à la vue
     */
    protected function render($view, $data = []) {
        // Fusionner les données par défaut avec les données spécifiques
        $data = array_merge($this->data, $data);
        
        // Extraire les données pour les rendre accessibles dans les vues
        extract($data);
        
        // Inclure le layout principal
        require VIEWS_PATH . '/layouts/main.php';
    }
    
    /**
     * Rendu d'une vue partielle (sans layout)
     * 
     * @param string $partial Nom du partial
     * @param array $data Données à passer
     * @return string HTML généré
     */
    protected function partial($partial, $data = []) {
        extract(array_merge($this->data, $data));
        
        ob_start();
        include VIEWS_PATH . '/partials/' . $partial . '.php';
        return ob_get_clean();
    }
    
    /**
     * Réponse JSON pour les requêtes AJAX
     * 
     * @param array $data Données à encoder
     * @param int $statusCode Code HTTP
     */
    protected function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Redirection vers une URL
     * 
     * @param string $url URL de destination
     */
    protected function redirect($url) {
        header('Location: ' . $url);
        exit;
    }
    
    /**
     * Vérifie si la requête est AJAX
     * 
     * @return bool
     */
    protected function isAjax() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * API Chatbot - Point d'entrée pour le chat
     * 
     * @route ?action=api
     */
    public function api() {
        if ($this->isAjax()) {
            ini_set('display_errors', 0);
        }
        
        // Le ChatHandler gère déjà tout (CORS, JSON, Logic)
        $handler = new ChatHandler();
        $handler->handle();
        exit;
    }

    /**
     * API Upload - Point d'entrée pour l'upload de documents
     * 
     * @route ?action=upload
     */
    public function upload() {
        // Configuration erreurs spécifique API
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);

        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        try {
            // Check for JSON input (from legacy API format)
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $isJsonInput = strpos($contentType, 'application/json') !== false;

            if ($isJsonInput) {
                $jsonInput = json_decode(file_get_contents('php://input'), true);
                if (!$jsonInput || empty($jsonInput['image'])) {
                    throw new Exception('No image data in JSON input');
                }

                $base64Content = $jsonInput['image'];
                $mimeType = $jsonInput['mime_type'] ?? 'image/jpeg';
                $documentType = $jsonInput['action'] === 'extract_passport' ? 'passport' : ($jsonInput['document_type'] ?? 'passport');
                $sessionId = $jsonInput['session_id'] ?? null;
                $validateWithClaude = $jsonInput['validate_with_claude'] ?? false;
            } else {
                if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('No file uploaded or upload error');
                }

                $file = $_FILES['file'];
                $documentType = $_POST['document_type'] ?? 'passport';
                $sessionId = $_POST['session_id'] ?? null;
                $validateWithClaude = filter_var($_POST['validate_with_claude'] ?? false, FILTER_VALIDATE_BOOLEAN);

                // Validation taille max 10MB
                if ($file['size'] > 10485760) {
                    throw new Exception("Fichier trop volumineux. Taille max: 10MB");
                }

                $fileContent = file_get_contents($file['tmp_name']);
                if ($fileContent === false) {
                    throw new Exception('Impossible de lire le fichier');
                }

                $base64Content = base64_encode($fileContent);
                $mimeType = $file['type'];
            }

            // Mapping types
            $typeMapping = [
                'passport' => 'passport', 'ticket' => 'ticket', 'hotel' => 'hotel',
                'vaccination' => 'vaccination', 'invitation' => 'invitation',
                'verbal_note' => 'verbal_note', 'residence_card' => 'residence_card',
                'payment' => 'payment', 'payment_proof' => 'payment'
            ];
            $normalizedType = $typeMapping[$documentType] ?? $documentType;

            // Service OCR
            $integrationService = new OCRIntegrationService([
                'debug' => defined('DEBUG_MODE') && DEBUG_MODE,
                'use_claude_validation' => $validateWithClaude,
                'cross_validation' => true
            ]);

            $result = $integrationService->processDocument($normalizedType, $base64Content, $mimeType, [
                'validate_with_claude' => $validateWithClaude
            ]);

            $response = [
                'success' => $result['success'] ?? false,
                'document_type' => $documentType,
                'fields' => $result['fields'] ?? [],
                'validations' => $result['validations'] ?? [],
                'confidence' => $result['confidence'] ?? 0,
                'session_id' => $sessionId,
                'extracted_data' => [
                    'fields' => $result['fields'] ?? [],
                    'passport_type' => $result['passport_type'] ?? 'ORDINAIRE',
                    'validations' => $result['validations'] ?? [],
                    'confidence' => $result['confidence'] ?? 0
                ]
            ];

            // Specific fields mapping
            if ($normalizedType === 'passport') {
                $response['mrz'] = $result['mrz'] ?? null;
                $response['passport_type'] = $result['passport_type'] ?? 'ORDINAIRE';
                $response['extracted_data']['mrz'] = $result['mrz'] ?? null;
            } elseif ($normalizedType === 'payment') {
                $response['amount_analysis'] = $result['amount_analysis'] ?? [];
                $response['payment_validated'] = $result['validations']['amount_matches_expected'] ?? false;
            } elseif ($normalizedType === 'ticket') {
                $response['is_round_trip'] = $result['fields']['return_flight']['value'] ?? false;
            } elseif ($normalizedType === 'vaccination') {
                $response['yellow_fever_valid'] = $result['validations']['yellow_fever_valid'] ?? false;
            }

            $this->json($response);

        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (Error $e) {
            $this->json(['success' => false, 'error' => 'Erreur serveur: ' . $e->getMessage()], 500);
        }
    }

    /**
     * API Validation Cohérence
     * 
     * @route ?action=validate
     */
    public function validate() {
        if (!$this->isAjax()) {
            $this->redirect('index.php');
            return;
        }

        try {
             // Récupérer le corps de la requête JSON
            $inputJSON = file_get_contents('php://input');
            $input = json_decode($inputJSON, true);
            
            // Récupérer la session via input ou POST
            $sessionId = $input['session_id'] ?? $this->post('session_id') ?? $_SESSION['visa_session_id'] ?? null;
            
            if (!$sessionId) {
                throw new Exception("ID de session manquant");
            }
            
            // Initialiser le validateur
            $validator = new DocumentCoherenceValidator($sessionId);
            
            // Charger les documents déjà extraits (simulation ou depuis DB/Session)
            // Dans une version réelle, on récupérerait les résultats OCR stockés en session/base
            $documents = $this->loadDocumentsFromCache($sessionId);
            $validator->loadDocuments($documents);
            
            // Exécuter la validation
            $report = $validator->validateAll();
            
            $this->json([
                'success' => true,
                'data' => $report
            ]);
            
        } catch (Exception $e) {
            error_log("Validation API Error: " . $e->getMessage());
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API Géolocalisation
     * 
     * @route ?action=geolocation
     */
    public function geolocation() {
        if (!$this->isAjax()) {
            $this->redirect('index.php');
            return;
        }

        $result = $this->detectGeolocation();
        $this->json([
            'success' => true,
            'data' => $result
        ]);
    }

    /**
     * Helper: Charge les documents depuis le cache par session_id
     */
    protected function loadDocumentsFromCache($sessionId) {
        $cacheDir = __DIR__ . '/../cache/sessions/' . $sessionId . '/';
        $documents = [];
        $docTypes = ['passport', 'ticket', 'hotel', 'invitation', 'vaccination', 'residence_card', 'verbal_note', 'payment'];

        if (is_dir($cacheDir)) {
            foreach ($docTypes as $type) {
                $files = glob($cacheDir . $type . '_*.json');
                if (!empty($files)) {
                    usort($files, function ($a, $b) { return filemtime($b) - filemtime($a); });
                    $data = json_decode(file_get_contents($files[0]), true);
                    if ($data) $documents[$type] = $data;
                }
            }
        }
        return $documents;
    }

    /**
     * Récupère un paramètre GET
     * 
     * @param string $key Clé du paramètre
     * @param mixed $default Valeur par défaut
     * @return mixed
     */
    protected function get($key, $default = null) {
        return $_GET[$key] ?? $default;
    }
    
    /**
     * Récupère un paramètre POST
     * 
     * @param string $key Clé du paramètre
     * @param mixed $default Valeur par défaut
     * @return mixed
     */
    protected function post($key, $default = null) {
        return $_POST[$key] ?? $default;
    }
}

