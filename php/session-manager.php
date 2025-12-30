<?php
/**
 * Gestionnaire de session - Chatbot Visa CI
 * Stockage persistant via fichiers JSON + cache optimisé
 * 
 * Inclut les traces du workflow Triple Layer:
 * - Layer 1: Google Vision (OCR)
 * - Layer 2: Gemini Flash (Conversation)
 * - Layer 3: Claude Sonnet (Superviseur)
 * 
 * @package VisaChatbot
 * @version 2.0.0 - Triple Layer Architecture
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cache-manager.php';

class SessionManager {
    
    /**
     * Instance du cache manager
     */
    private ?CacheManager $cache = null;
    
    /**
     * Préfixe pour les clés de cache session
     */
    private const CACHE_PREFIX = 'session:';
    
    /**
     * Répertoire de stockage des sessions
     */
    private const SESSIONS_DIR = __DIR__ . '/../data/sessions/';
    
    /**
     * Clés de session
     */
    private const KEY_SESSION_ID = 'session_id';
    private const KEY_LANGUAGE = 'language';
    private const KEY_CURRENT_STEP = 'current_step';
    private const KEY_WORKFLOW_CATEGORY = 'workflow_category';
    private const KEY_COLLECTED_DATA = 'collected_data';
    private const KEY_CHAT_HISTORY = 'chat_history';
    private const KEY_CREATED_AT = 'created_at';
    private const KEY_UPDATED_AT = 'updated_at';
    private const KEY_STATUS = 'status';
    private const KEY_COMPLETED_STEPS = 'completed_steps';
    private const KEY_HIGHEST_STEP = 'highest_step_reached';
    
    // Triple Layer keys
    private const KEY_TRIPLE_LAYER_TRACES = 'triple_layer_traces';
    private const KEY_CLAUDE_VALIDATIONS = 'claude_validations';
    private const KEY_GEMINI_USAGE = 'gemini_usage';
    
    /**
     * Statuts possibles
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ABANDONED = 'abandoned';
    public const STATUS_BLOCKED = 'blocked';
    
    /**
     * ID de session unique
     */
    private string $sessionId;
    
    /**
     * Données de session en cache mémoire
     */
    private array $sessionData = [];
    
    /**
     * Constructeur - Initialise ou reprend une session
     */
    public function __construct(?string $sessionId = null) {
        // Initialiser le cache manager
        $this->cache = CacheManager::getInstance();
        
        // Créer le répertoire de sessions si nécessaire
        $this->ensureSessionsDirectory();
        
        if ($sessionId && $this->sessionExists($sessionId)) {
            $this->sessionId = $sessionId;
            $this->loadSession();
        } else {
            $this->sessionId = $this->createSession();
        }
    }
    
    /**
     * Retourne la clé de cache pour une session
     */
    private function getCacheKey(string $sessionId): string {
        return self::CACHE_PREFIX . $sessionId;
    }
    
    /**
     * S'assure que le répertoire de sessions existe
     */
    private function ensureSessionsDirectory(): void {
        if (!is_dir(self::SESSIONS_DIR)) {
            mkdir(self::SESSIONS_DIR, 0755, true);
        }
    }
    
    /**
     * Retourne le chemin du fichier de session
     */
    private function getSessionFilePath(string $sessionId): string {
        return self::SESSIONS_DIR . $sessionId . '.json';
    }
    
    /**
     * Crée une nouvelle session de chat
     */
    private function createSession(): string {
        $sessionId = $this->generateSessionId();
        
        // Initialiser le sessionId AVANT d'appeler saveSession
        $this->sessionId = $sessionId;
        
        $this->sessionData = [
            self::KEY_SESSION_ID => $sessionId,
            self::KEY_LANGUAGE => DEFAULT_LANGUAGE,
            self::KEY_CURRENT_STEP => WORKFLOW_STEPS[0], // 'welcome'
            self::KEY_WORKFLOW_CATEGORY => null,
            self::KEY_COLLECTED_DATA => [],
            self::KEY_CHAT_HISTORY => [],
            self::KEY_COMPLETED_STEPS => [],
            self::KEY_HIGHEST_STEP => 0,
            self::KEY_CREATED_AT => time(),
            self::KEY_UPDATED_AT => time(),
            self::KEY_STATUS => self::STATUS_ACTIVE,
            // Triple Layer Architecture
            self::KEY_TRIPLE_LAYER_TRACES => [],
            self::KEY_CLAUDE_VALIDATIONS => [],
            self::KEY_GEMINI_USAGE => [
                'total_calls' => 0,
                'nlu_calls' => 0,
                'response_calls' => 0,
                'extraction_calls' => 0
            ]
        ];
        
        $this->saveSession();
        
        return $sessionId;
    }
    
    /**
     * Génère un ID de session unique
     */
    private function generateSessionId(): string {
        return 'chat_' . bin2hex(random_bytes(16));
    }
    
    /**
     * Vérifie si une session existe et est valide
     */
    private function sessionExists(string $sessionId): bool {
        // Valider le format de l'ID
        if (!preg_match('/^chat_[a-f0-9]{32}$/', $sessionId)) {
            return false;
        }
        
        $filePath = $this->getSessionFilePath($sessionId);
        
        if (!file_exists($filePath)) {
            return false;
        }
        
        // Charger et vérifier l'expiration
        $data = json_decode(file_get_contents($filePath), true);
        
        if (!$data || !isset($data[self::KEY_CREATED_AT])) {
            return false;
        }
        
        // Vérifier si la session n'a pas expiré
        if (time() - $data[self::KEY_CREATED_AT] > SESSION_DURATION) {
            @unlink($filePath);
            return false;
        }
        
        return true;
    }
    
    /**
     * Charge une session depuis le cache ou le fichier
     */
    private function loadSession(): void {
        $cacheKey = $this->getCacheKey($this->sessionId);
        
        // Essayer le cache d'abord (accès rapide)
        $cachedData = $this->cache->get($cacheKey);
        
        if ($cachedData !== null) {
            $this->sessionData = $cachedData;
            return;
        }
        
        // Fallback sur le fichier
        $filePath = $this->getSessionFilePath($this->sessionId);
        
        if (file_exists($filePath)) {
            $this->sessionData = json_decode(file_get_contents($filePath), true) ?? [];
            
            // Mettre en cache pour les prochains accès
            $this->cache->set($cacheKey, $this->sessionData, SESSION_DURATION);
        }
    }
    
    /**
     * Sauvegarde la session dans le cache et le fichier
     */
    private function saveSession(): void {
        $this->sessionData[self::KEY_UPDATED_AT] = time();
        
        // Sauvegarder dans le fichier (persistance)
        $filePath = $this->getSessionFilePath($this->sessionId);
        file_put_contents($filePath, json_encode($this->sessionData, JSON_PRETTY_PRINT));
        
        // Mettre à jour le cache (performance)
        $cacheKey = $this->getCacheKey($this->sessionId);
        $this->cache->set($cacheKey, $this->sessionData, SESSION_DURATION);
    }
    
    /**
     * Invalide le cache d'une session
     */
    public function invalidateCache(): void {
        $cacheKey = $this->getCacheKey($this->sessionId);
        $this->cache->delete($cacheKey);
    }
    
    /**
     * Retourne l'ID de session
     */
    public function getSessionId(): string {
        return $this->sessionId;
    }
    
    /**
     * Retourne les données de session
     */
    private function getSessionData(): array {
        return $this->sessionData;
    }
    
    /**
     * Met à jour les données de session
     */
    private function setSessionData(array $data): void {
        $this->sessionData = $data;
        $this->saveSession();
    }
    
    /**
     * Retourne la langue actuelle
     */
    public function getLanguage(): string {
        return $this->sessionData[self::KEY_LANGUAGE] ?? DEFAULT_LANGUAGE;
    }
    
    /**
     * Définit la langue
     */
    public function setLanguage(string $lang): void {
        $this->sessionData[self::KEY_LANGUAGE] = in_array($lang, ['fr', 'en']) ? $lang : DEFAULT_LANGUAGE;
        $this->saveSession();
    }
    
    /**
     * Retourne l'étape actuelle
     */
    public function getCurrentStep(): string {
        return $this->sessionData[self::KEY_CURRENT_STEP] ?? WORKFLOW_STEPS[0];
    }
    
    /**
     * Définit l'étape actuelle
     */
    public function setCurrentStep(string $step): void {
        if (in_array($step, WORKFLOW_STEPS)) {
            $this->sessionData[self::KEY_CURRENT_STEP] = $step;
            $this->saveSession();
        }
    }
    
    /**
     * Passe à l'étape suivante
     */
    public function nextStep(): ?string {
        $currentStep = $this->getCurrentStep();
        $nextStep = getNextStep($currentStep);
        
        if ($nextStep) {
            $this->setCurrentStep($nextStep);
        }
        
        return $nextStep;
    }
    
    /**
     * Retourne à l'étape précédente
     */
    public function previousStep(): ?string {
        $currentStep = $this->getCurrentStep();
        $prevStep = getPreviousStep($currentStep);
        
        if ($prevStep) {
            $this->setCurrentStep($prevStep);
        }
        
        return $prevStep;
    }
    
    /**
     * Navigue vers une étape spécifique (si autorisé)
     */
    public function goToStep(string $targetStep): bool {
        // Vérifier si l'étape existe
        if (!in_array($targetStep, WORKFLOW_STEPS)) {
            return false;
        }
        
        // Vérifier si on peut y aller (étape complétée ou actuelle)
        $targetIndex = getStepIndex($targetStep);
        $highestReached = $this->getHighestStepReached();
        
        // On peut aller vers une étape déjà atteinte
        if ($targetIndex <= $highestReached) {
            $this->setCurrentStep($targetStep);
            return true;
        }
        
        return false;
    }
    
    /**
     * Retourne la liste des étapes complétées
     */
    public function getCompletedSteps(): array {
        return $this->sessionData[self::KEY_COMPLETED_STEPS] ?? [];
    }
    
    /**
     * Marque une étape comme complétée
     */
    public function markStepCompleted(string $step): void {
        $completedSteps = $this->sessionData[self::KEY_COMPLETED_STEPS] ?? [];
        
        if (!in_array($step, $completedSteps)) {
            $completedSteps[] = $step;
            $this->sessionData[self::KEY_COMPLETED_STEPS] = $completedSteps;
            $this->saveSession();
        }
    }
    
    /**
     * Vérifie si une étape est accessible (complétée ou courante)
     */
    public function isStepAccessible(string $step): bool {
        $targetIndex = getStepIndex($step);
        $highestReached = $this->getHighestStepReached();
        
        return $targetIndex <= $highestReached;
    }
    
    /**
     * Retourne le plus haut index d'étape atteint
     */
    public function getHighestStepReached(): int {
        $highest = $this->sessionData[self::KEY_HIGHEST_STEP] ?? 0;
        $currentIndex = getStepIndex($this->getCurrentStep());
        
        return max($highest, $currentIndex);
    }
    
    /**
     * Met à jour le plus haut index atteint
     */
    public function updateHighestStep(): void {
        $currentIndex = getStepIndex($this->getCurrentStep());
        $highest = $this->sessionData[self::KEY_HIGHEST_STEP] ?? 0;
        
        if ($currentIndex > $highest) {
            $this->sessionData[self::KEY_HIGHEST_STEP] = $currentIndex;
            $this->saveSession();
        }
    }
    
    /**
     * Retourne la catégorie de workflow
     */
    public function getWorkflowCategory(): ?string {
        return $this->sessionData[self::KEY_WORKFLOW_CATEGORY] ?? null;
    }
    
    /**
     * Définit la catégorie de workflow
     */
    public function setWorkflowCategory(string $category): void {
        $this->sessionData[self::KEY_WORKFLOW_CATEGORY] = $category;
        $this->saveSession();
    }
    
    /**
     * Retourne les données collectées
     */
    public function getCollectedData(): array {
        return $this->sessionData[self::KEY_COLLECTED_DATA] ?? [];
    }

    /**
     * Retourne toutes les données de session (pour SmartPrefill)
     * Alias pour getCollectedData() avec données additionnelles
     */
    public function getAllData(): array {
        return array_merge(
            $this->sessionData[self::KEY_COLLECTED_DATA] ?? [],
            [
                'session_id' => $this->sessionId,
                'language' => $this->sessionData[self::KEY_LANGUAGE] ?? 'fr',
                'current_step' => $this->sessionData[self::KEY_CURRENT_STEP] ?? 'welcome',
                'workflow_category' => $this->sessionData[self::KEY_WORKFLOW_CATEGORY] ?? null
            ]
        );
    }

    /**
     * Ajoute des données collectées
     */
    public function addCollectedData(array $newData): void {
        $this->sessionData[self::KEY_COLLECTED_DATA] = array_merge(
            $this->sessionData[self::KEY_COLLECTED_DATA] ?? [],
            $newData
        );
        $this->saveSession();
    }
    
    /**
     * Met à jour une donnée spécifique
     */
    public function setCollectedField(string $field, $value): void {
        $this->sessionData[self::KEY_COLLECTED_DATA][$field] = $value;
        $this->saveSession();
    }
    
    /**
     * Retourne une donnée collectée spécifique
     */
    public function getCollectedField(string $field, $default = null) {
        return $this->getCollectedData()[$field] ?? $default;
    }
    
    /**
     * Retourne l'historique du chat
     */
    public function getChatHistory(): array {
        return $this->sessionData[self::KEY_CHAT_HISTORY] ?? [];
    }
    
    /**
     * Ajoute un message à l'historique
     */
    public function addMessage(string $role, string $content, array $metadata = []): array {
        $message = [
            'id' => 'msg_' . uniqid(),
            'role' => $role, // 'assistant', 'user', 'system'
            'content' => $content,
            'timestamp' => time(),
            'metadata' => $metadata
        ];
        
        $this->sessionData[self::KEY_CHAT_HISTORY][] = $message;
        $this->saveSession();
        
        return $message;
    }
    
    /**
     * Retourne les N derniers messages
     */
    public function getRecentMessages(int $count = 10): array {
        $history = $this->getChatHistory();
        return array_slice($history, -$count);
    }
    
    /**
     * Retourne le statut de la session
     */
    public function getStatus(): string {
        return $this->sessionData[self::KEY_STATUS] ?? self::STATUS_ACTIVE;
    }
    
    /**
     * Définit le statut de la session
     */
    public function setStatus(string $status): void {
        $this->sessionData[self::KEY_STATUS] = $status;
        $this->saveSession();
    }
    
    /**
     * Marque la session comme terminée
     */
    public function complete(): void {
        $this->setStatus(self::STATUS_COMPLETED);
    }
    
    /**
     * Marque la session comme bloquée
     *
     * @param string $reason Raison du blocage
     * @param array $details Détails additionnels
     */
    public function block(string $reason = 'unknown', array $details = []): void {
        $this->setStatus(self::STATUS_BLOCKED);
        $this->sessionData['block_info'] = [
            'reason' => $reason,
            'details' => $details,
            'blocked_at' => time(),
            'blocked_step' => $this->getCurrentStep()
        ];
        $this->saveSession();
    }

    /**
     * Débloque une session bloquée
     *
     * @param string $unblockReason Raison du déblocage
     * @param string|null $returnToStep Étape vers laquelle retourner (null = étape de blocage)
     * @return bool True si déblocage réussi
     */
    public function unblock(string $unblockReason = 'user_corrected', ?string $returnToStep = null): bool {
        if ($this->getStatus() !== self::STATUS_BLOCKED) {
            return false; // Pas bloquée
        }

        $blockInfo = $this->sessionData['block_info'] ?? [];

        // Enregistrer l'historique de déblocage
        if (!isset($this->sessionData['unblock_history'])) {
            $this->sessionData['unblock_history'] = [];
        }
        $this->sessionData['unblock_history'][] = [
            'unblocked_at' => time(),
            'reason' => $unblockReason,
            'previous_block_info' => $blockInfo
        ];

        // Déterminer l'étape de retour
        if ($returnToStep && in_array($returnToStep, WORKFLOW_STEPS)) {
            $this->setCurrentStep($returnToStep);
        } elseif (isset($blockInfo['blocked_step'])) {
            // Retourner à l'étape où le blocage s'est produit
            $this->setCurrentStep($blockInfo['blocked_step']);
        }

        // Réactiver la session
        $this->setStatus(self::STATUS_ACTIVE);
        unset($this->sessionData['block_info']);
        $this->saveSession();

        return true;
    }

    /**
     * Retourne les informations de blocage
     */
    public function getBlockInfo(): ?array {
        if ($this->getStatus() !== self::STATUS_BLOCKED) {
            return null;
        }
        return $this->sessionData['block_info'] ?? null;
    }

    /**
     * Vérifie si la session peut être débloquée
     *
     * @return array ['can_unblock' => bool, 'reason' => string, 'suggested_action' => string]
     */
    public function canUnblock(): array {
        if ($this->getStatus() !== self::STATUS_BLOCKED) {
            return [
                'can_unblock' => false,
                'reason' => 'Session is not blocked',
                'suggested_action' => null
            ];
        }

        $blockInfo = $this->getBlockInfo();
        $blockReason = $blockInfo['reason'] ?? 'unknown';

        // Raisons qui permettent un déblocage automatique
        $unblockableReasons = [
            'coherence_warning' => 'User can correct documents',
            'document_mismatch' => 'User can re-upload corrected document',
            'missing_information' => 'User can provide missing information',
            'validation_failed' => 'User can correct invalid data'
        ];

        // Raisons qui nécessitent une intervention admin
        $adminOnlyReasons = [
            'fraud_detected' => 'Requires admin review',
            'jurisdiction_violation' => 'Cannot apply from this location',
            'document_expired' => 'Requires new document'
        ];

        if (isset($unblockableReasons[$blockReason])) {
            return [
                'can_unblock' => true,
                'reason' => $unblockableReasons[$blockReason],
                'suggested_action' => 'correct_and_retry'
            ];
        }

        if (isset($adminOnlyReasons[$blockReason])) {
            return [
                'can_unblock' => false,
                'reason' => $adminOnlyReasons[$blockReason],
                'suggested_action' => 'contact_embassy'
            ];
        }

        // Par défaut, permettre le déblocage avec correction
        return [
            'can_unblock' => true,
            'reason' => 'User may attempt correction',
            'suggested_action' => 'review_and_correct'
        ];
    }

    /**
     * Retourne l'historique des déblocages
     */
    public function getUnblockHistory(): array {
        return $this->sessionData['unblock_history'] ?? [];
    }
    
    /**
     * Retourne le contexte complet pour Claude
     */
    public function getContextForClaude(): array {
        return [
            'session_id' => $this->sessionId,
            'language' => $this->getLanguage(),
            'current_step' => $this->getCurrentStep(),
            'workflow_category' => $this->getWorkflowCategory(),
            'collected_data' => $this->getCollectedData(),
            'status' => $this->getStatus(),
            'triple_layer_summary' => $this->getTripleLayerSummary()
        ];
    }
    
    // === Triple Layer Workflow Methods ===
    
    /**
     * Ajoute une trace au workflow Triple Layer
     * 
     * @param string $layer Layer concerné (layer1, layer2, layer3)
     * @param string $action Action effectuée
     * @param array $metadata Données additionnelles
     */
    public function addTripleLayerTrace(string $layer, string $action, array $metadata = []): void {
        $trace = [
            'layer' => $layer,
            'action' => $action,
            'timestamp' => microtime(true),
            'datetime' => date('c'),
            'step' => $this->getCurrentStep(),
            'metadata' => $metadata
        ];
        
        $this->sessionData[self::KEY_TRIPLE_LAYER_TRACES][] = $trace;
        
        // Limiter à 100 traces pour éviter fichiers trop gros
        if (count($this->sessionData[self::KEY_TRIPLE_LAYER_TRACES]) > 100) {
            array_shift($this->sessionData[self::KEY_TRIPLE_LAYER_TRACES]);
        }
        
        $this->saveSession();
    }
    
    /**
     * Retourne les traces du workflow Triple Layer
     */
    public function getTripleLayerTraces(int $limit = 50): array {
        $traces = $this->sessionData[self::KEY_TRIPLE_LAYER_TRACES] ?? [];
        return array_slice($traces, -$limit);
    }
    
    /**
     * Enregistre une utilisation de Gemini
     */
    public function recordGeminiUsage(string $type = 'general'): void {
        $usage = $this->sessionData[self::KEY_GEMINI_USAGE] ?? [
            'total_calls' => 0,
            'nlu_calls' => 0,
            'response_calls' => 0,
            'extraction_calls' => 0
        ];
        
        $usage['total_calls']++;
        
        switch ($type) {
            case 'nlu':
                $usage['nlu_calls']++;
                break;
            case 'response':
                $usage['response_calls']++;
                break;
            case 'extraction':
                $usage['extraction_calls']++;
                break;
        }
        
        $this->sessionData[self::KEY_GEMINI_USAGE] = $usage;
        $this->saveSession();
    }
    
    /**
     * Retourne les statistiques d'utilisation de Gemini
     */
    public function getGeminiUsage(): array {
        return $this->sessionData[self::KEY_GEMINI_USAGE] ?? [
            'total_calls' => 0,
            'nlu_calls' => 0,
            'response_calls' => 0,
            'extraction_calls' => 0
        ];
    }
    
    /**
     * Ajoute une validation Claude à la session
     */
    public function addClaudeValidation(string $type, array $result): void {
        $validation = [
            'type' => $type,
            'result' => $result,
            'timestamp' => time(),
            'datetime' => date('c'),
            'step' => $this->getCurrentStep()
        ];
        
        $this->sessionData[self::KEY_CLAUDE_VALIDATIONS][] = $validation;
        $this->saveSession();
    }
    
    /**
     * Retourne les validations Claude de la session
     */
    public function getClaudeValidations(): array {
        return $this->sessionData[self::KEY_CLAUDE_VALIDATIONS] ?? [];
    }
    
    /**
     * Vérifie si la session a des alertes Claude non résolues
     */
    public function hasUnresolvedClaudeAlerts(): bool {
        $validations = $this->getClaudeValidations();
        
        foreach ($validations as $validation) {
            $result = $validation['result'] ?? [];
            if (isset($result['recommendation']) && $result['recommendation'] === 'rejected') {
                return true;
            }
            if (!empty($result['fraud_indicators'])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Retourne un résumé du workflow Triple Layer
     */
    public function getTripleLayerSummary(): array {
        $traces = $this->getTripleLayerTraces();
        
        // Compter les actions par layer
        $layerCounts = ['layer1' => 0, 'layer2' => 0, 'layer3' => 0];
        foreach ($traces as $trace) {
            $layer = $trace['layer'] ?? 'unknown';
            if (isset($layerCounts[$layer])) {
                $layerCounts[$layer]++;
            }
        }
        
        return [
            'gemini_usage' => $this->getGeminiUsage(),
            'claude_validations_count' => count($this->getClaudeValidations()),
            'has_unresolved_alerts' => $this->hasUnresolvedClaudeAlerts(),
            'traces_count' => count($traces),
            'layer_activity' => $layerCounts,
            'last_trace' => !empty($traces) ? end($traces) : null
        ];
    }
    
    /**
     * Retourne le score de confiance global basé sur les validations
     */
    public function getOverallConfidenceScore(): float {
        $validations = $this->getClaudeValidations();
        
        if (empty($validations)) {
            return 1.0; // Pas de validation = confiance par défaut
        }
        
        $scores = [];
        foreach ($validations as $validation) {
            if (isset($validation['result']['confidence_score'])) {
                $scores[] = $validation['result']['confidence_score'];
            }
        }
        
        if (empty($scores)) {
            return 0.8;
        }
        
        // Moyenne pondérée (plus récent = plus important)
        $total = 0;
        $weight = 0;
        foreach ($scores as $i => $score) {
            $w = $i + 1;
            $total += $score * $w;
            $weight += $w;
        }
        
        return $weight > 0 ? round($total / $weight, 2) : 0.8;
    }
    
    /**
     * Retourne la progression (pourcentage)
     */
    public function getProgress(): int {
        $currentStep = $this->getCurrentStep();
        $stepIndex = getStepIndex($currentStep);
        $totalSteps = count(WORKFLOW_STEPS);
        
        return (int) round(($stepIndex / ($totalSteps - 1)) * 100);
    }
    
    /**
     * Retourne les informations d'étape pour le frontend
     */
    public function getStepInfo(): array {
        $currentStep = $this->getCurrentStep();
        $stepIndex = getStepIndex($currentStep);
        $highestReached = $this->getHighestStepReached();
        
        // Générer les infos d'accessibilité pour chaque étape
        $stepsInfo = [];
        foreach (WORKFLOW_STEPS as $index => $step) {
            $stepsInfo[$step] = [
                'index' => $index,
                'accessible' => $index <= $highestReached,
                'completed' => $index < $stepIndex,
                'current' => $step === $currentStep
            ];
        }
        
        return [
            'current' => $currentStep,
            'index' => $stepIndex,
            'total' => count(WORKFLOW_STEPS),
            'progress' => $this->getProgress(),
            'steps' => WORKFLOW_STEPS,
            'steps_info' => $stepsInfo,
            'highest_reached' => $highestReached,
            'can_go_back' => $stepIndex > 0,
            'can_go_forward' => $stepIndex < $highestReached
        ];
    }
    
    /**
     * Exporte l'état complet de la session
     */
    public function export(): array {
        return [
            'session_id' => $this->sessionId,
            'language' => $this->sessionData[self::KEY_LANGUAGE],
            'current_step' => $this->sessionData[self::KEY_CURRENT_STEP],
            'step_info' => $this->getStepInfo(),
            'workflow_category' => $this->sessionData[self::KEY_WORKFLOW_CATEGORY],
            'collected_data' => $this->sessionData[self::KEY_COLLECTED_DATA],
            'chat_history' => $this->sessionData[self::KEY_CHAT_HISTORY],
            'status' => $this->sessionData[self::KEY_STATUS],
            'created_at' => $this->sessionData[self::KEY_CREATED_AT],
            'updated_at' => $this->sessionData[self::KEY_UPDATED_AT],
            // Triple Layer Architecture data
            'triple_layer' => [
                'summary' => $this->getTripleLayerSummary(),
                'confidence_score' => $this->getOverallConfidenceScore(),
                'traces' => $this->getTripleLayerTraces(20), // Last 20 traces
                'validations' => $this->getClaudeValidations()
            ]
        ];
    }
    
    /**
     * Réinitialise la session
     */
    public function reset(): void {
        // Invalider le cache
        $this->invalidateCache();
        
        // Supprimer le fichier
        $filePath = $this->getSessionFilePath($this->sessionId);
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
        $this->sessionId = $this->createSession();
    }
    
    /**
     * Nettoie les sessions expirées (à appeler périodiquement)
     */
    public static function cleanupExpiredSessions(): int {
        $sessionsDir = self::SESSIONS_DIR;
        $deleted = 0;
        
        if (!is_dir($sessionsDir)) {
            return 0;
        }
        
        // Nettoyer le cache aussi
        $cache = CacheManager::getInstance();
        $cache->cleanup();
        
        foreach (glob($sessionsDir . '*.json') as $file) {
            $data = json_decode(file_get_contents($file), true);
            
            if ($data && isset($data['created_at'])) {
                if (time() - $data['created_at'] > SESSION_DURATION) {
                    // Invalider le cache correspondant
                    $sessionId = $data['session_id'] ?? '';
                    if ($sessionId) {
                        $cache->delete(self::CACHE_PREFIX . $sessionId);
                    }
                    
                    @unlink($file);
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
    
    /**
     * Retourne les statistiques du cache de session
     */
    public function getCacheStats(): array {
        return $this->cache->getStats();
    }
}

