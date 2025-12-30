<?php
/**
 * Contrôleur principal du Chatbot e-Visa
 * Gère les requêtes et le rendu des vues
 *
 * Intègre WorkflowEngine pour pré-charger les données au chargement de la page
 */

require_once __DIR__ . '/../php/services/GeolocationService.php';
require_once __DIR__ . '/../php/session-manager.php';
require_once __DIR__ . '/../php/workflow-engine.php';

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

        // Initialiser la session (reprendre si existante via cookie)
        $sessionId = $_GET['session_id'] ?? $_COOKIE['visa_session_id'] ?? null;
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

