<?php
/**
 * Draft Manager - Chatbot Visa CI
 * Gère la sauvegarde et récupération des brouillons
 * 
 * @package VisaChatbot
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session-manager.php';

class DraftManager {
    
    /**
     * Répertoire de stockage des brouillons
     */
    private const DRAFT_DIR = __DIR__ . '/../data/drafts/';
    
    /**
     * Durée de validité d'un brouillon (7 jours)
     */
    private const DRAFT_EXPIRY = 7 * 24 * 60 * 60;
    
    /**
     * Préfixe pour l'ID de pré-demande
     */
    private const PRE_ID_PREFIX = 'PRE-';
    
    /**
     * Session manager
     */
    private SessionManager $session;
    
    /**
     * Constructeur
     */
    public function __construct(SessionManager $session) {
        $this->session = $session;
        $this->ensureDraftDir();
    }
    
    /**
     * Assure que le répertoire des brouillons existe
     */
    private function ensureDraftDir(): void {
        if (!is_dir(self::DRAFT_DIR)) {
            mkdir(self::DRAFT_DIR, 0755, true);
        }
    }
    
    /**
     * Génère un ID de pré-demande unique
     */
    public function generatePreApplicationId(): string {
        $id = self::PRE_ID_PREFIX . strtoupper(substr(uniqid(), -6)) . '-' . date('ymd');
        return $id;
    }
    
    /**
     * Sauvegarde le brouillon actuel
     */
    public function saveDraft(?string $preApplicationId = null): array {
        $sessionId = $this->session->getSessionId();
        
        // Générer un ID de pré-demande si pas fourni
        if (!$preApplicationId) {
            $preApplicationId = $this->getPreApplicationId() ?? $this->generatePreApplicationId();
        }
        
        $draft = [
            'pre_application_id' => $preApplicationId,
            'session_id' => $sessionId,
            'language' => $this->session->getLanguage(),
            'current_step' => $this->session->getCurrentStep(),
            'workflow_category' => $this->session->getWorkflowCategory(),
            'collected_data' => $this->session->getCollectedData(),
            'completed_steps' => $this->session->getCompletedSteps(),
            'highest_step_reached' => $this->session->getHighestStepReached(),
            'saved_at' => time(),
            'expires_at' => time() + self::DRAFT_EXPIRY
        ];
        
        // Sauvegarder dans un fichier
        $filename = $this->getDraftFilename($preApplicationId);
        file_put_contents($filename, json_encode($draft, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // Stocker l'ID dans la session
        $this->session->setCollectedField('pre_application_id', $preApplicationId);
        
        return [
            'success' => true,
            'pre_application_id' => $preApplicationId,
            'saved_at' => $draft['saved_at'],
            'expires_at' => $draft['expires_at']
        ];
    }
    
    /**
     * Charge un brouillon
     */
    public function loadDraft(string $preApplicationId): ?array {
        $filename = $this->getDraftFilename($preApplicationId);
        
        if (!file_exists($filename)) {
            return null;
        }
        
        $draft = json_decode(file_get_contents($filename), true);
        
        if (!$draft) {
            return null;
        }
        
        // Vérifier l'expiration
        if (time() > ($draft['expires_at'] ?? 0)) {
            $this->deleteDraft($preApplicationId);
            return null;
        }
        
        return $draft;
    }
    
    /**
     * Restaure un brouillon dans la session
     */
    public function restoreDraft(string $preApplicationId): bool {
        $draft = $this->loadDraft($preApplicationId);
        
        if (!$draft) {
            return false;
        }
        
        // Restaurer les données dans la session
        $this->session->setLanguage($draft['language']);
        $this->session->setCurrentStep($draft['current_step']);
        
        if ($draft['workflow_category']) {
            $this->session->setWorkflowCategory($draft['workflow_category']);
        }
        
        // Restaurer les données collectées
        foreach ($draft['collected_data'] as $field => $value) {
            $this->session->setCollectedField($field, $value);
        }
        
        // Marquer les étapes complétées
        foreach ($draft['completed_steps'] ?? [] as $step) {
            $this->session->markStepCompleted($step);
        }
        
        return true;
    }
    
    /**
     * Supprime un brouillon
     */
    public function deleteDraft(string $preApplicationId): bool {
        $filename = $this->getDraftFilename($preApplicationId);
        
        if (file_exists($filename)) {
            return unlink($filename);
        }
        
        return false;
    }
    
    /**
     * Retourne l'ID de pré-demande de la session actuelle
     */
    public function getPreApplicationId(): ?string {
        return $this->session->getCollectedField('pre_application_id');
    }
    
    /**
     * Retourne le chemin du fichier de brouillon
     */
    private function getDraftFilename(string $preApplicationId): string {
        $safeId = preg_replace('/[^a-zA-Z0-9\-]/', '', $preApplicationId);
        return self::DRAFT_DIR . $safeId . '.json';
    }
    
    /**
     * Nettoie les brouillons expirés
     */
    public function cleanupExpiredDrafts(): int {
        $count = 0;
        $files = glob(self::DRAFT_DIR . '*.json');
        
        foreach ($files as $file) {
            $draft = json_decode(file_get_contents($file), true);
            
            if (!$draft || time() > ($draft['expires_at'] ?? 0)) {
                unlink($file);
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Retourne les informations du brouillon pour le frontend
     */
    public function getDraftInfo(): array {
        $preApplicationId = $this->getPreApplicationId();
        
        if (!$preApplicationId) {
            // Générer un nouvel ID
            $preApplicationId = $this->generatePreApplicationId();
            $this->session->setCollectedField('pre_application_id', $preApplicationId);
        }
        
        $draft = $this->loadDraft($preApplicationId);
        
        return [
            'pre_application_id' => $preApplicationId,
            'has_draft' => $draft !== null,
            'saved_at' => $draft['saved_at'] ?? null,
            'expires_at' => $draft['expires_at'] ?? null,
            'expires_in_days' => $draft ? max(0, floor(($draft['expires_at'] - time()) / 86400)) : null
        ];
    }
}

/**
 * API endpoint pour la gestion des brouillons
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'draft-manager.php') {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Session-ID');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?? [];
    
    $action = $data['action'] ?? $_GET['action'] ?? 'info';
    $sessionId = $data['session_id'] ?? $_GET['session_id'] ?? $_SERVER['HTTP_X_SESSION_ID'] ?? null;
    
    $session = new SessionManager($sessionId);
    $draftManager = new DraftManager($session);
    
    $response = ['success' => false];
    
    switch ($action) {
        case 'save':
            $result = $draftManager->saveDraft();
            $response = ['success' => true, 'data' => $result];
            break;
            
        case 'load':
            $preAppId = $data['pre_application_id'] ?? $_GET['pre_application_id'] ?? null;
            if ($preAppId) {
                $draft = $draftManager->loadDraft($preAppId);
                if ($draft) {
                    $response = ['success' => true, 'data' => $draft];
                } else {
                    $response = ['success' => false, 'error' => 'Brouillon non trouvé ou expiré'];
                }
            }
            break;
            
        case 'restore':
            $preAppId = $data['pre_application_id'] ?? null;
            if ($preAppId && $draftManager->restoreDraft($preAppId)) {
                $response = ['success' => true, 'message' => 'Brouillon restauré'];
            } else {
                $response = ['success' => false, 'error' => 'Impossible de restaurer le brouillon'];
            }
            break;
            
        case 'info':
        default:
            $response = ['success' => true, 'data' => $draftManager->getDraftInfo()];
            break;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

