<?php
/**
 * A/B Testing Service - Chatbot Visa CI
 * Framework de tests A/B pour optimiser les conversions
 * 
 * @package VisaChatbot
 * @version 1.0.0
 */

require_once __DIR__ . '/config.php';

class ABTestingService {
    
    /**
     * Répertoire des données A/B
     */
    private const DATA_DIR = __DIR__ . '/../data/ab-tests/';
    
    /**
     * Fichier des assignations (session -> variant)
     */
    private const ASSIGNMENTS_FILE = 'assignments.json';
    
    /**
     * Fichier des résultats
     */
    private const RESULTS_FILE = 'results.json';
    
    /**
     * Configuration des tests
     */
    private array $config = [];
    
    /**
     * Assignations en mémoire
     */
    private array $assignments = [];
    
    /**
     * Résultats en mémoire
     */
    private array $results = [];
    
    /**
     * Singleton instance
     */
    private static ?ABTestingService $instance = null;
    
    /**
     * Constructeur privé (singleton)
     */
    private function __construct() {
        $this->ensureDataDirectory();
        $this->loadConfig();
        $this->loadAssignments();
        $this->loadResults();
    }
    
    /**
     * Obtenir l'instance singleton
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * S'assure que le répertoire de données existe
     */
    private function ensureDataDirectory(): void {
        if (!is_dir(self::DATA_DIR)) {
            mkdir(self::DATA_DIR, 0755, true);
        }
    }
    
    /**
     * Charge la configuration des tests
     */
    private function loadConfig(): void {
        $configFile = __DIR__ . '/data/ab-tests-config.php';
        
        if (file_exists($configFile)) {
            $this->config = require $configFile;
        } else {
            $this->config = ['tests' => [], 'enabled' => true];
        }
    }
    
    /**
     * Charge les assignations
     */
    private function loadAssignments(): void {
        $filePath = self::DATA_DIR . self::ASSIGNMENTS_FILE;
        
        if (file_exists($filePath)) {
            $this->assignments = json_decode(file_get_contents($filePath), true) ?? [];
        } else {
            $this->assignments = [];
        }
    }
    
    /**
     * Sauvegarde les assignations
     */
    private function saveAssignments(): void {
        $filePath = self::DATA_DIR . self::ASSIGNMENTS_FILE;
        file_put_contents($filePath, json_encode($this->assignments, JSON_PRETTY_PRINT), LOCK_EX);
    }
    
    /**
     * Charge les résultats
     */
    private function loadResults(): void {
        $filePath = self::DATA_DIR . self::RESULTS_FILE;
        
        if (file_exists($filePath)) {
            $this->results = json_decode(file_get_contents($filePath), true) ?? [];
        } else {
            $this->results = [];
        }
    }
    
    /**
     * Sauvegarde les résultats
     */
    private function saveResults(): void {
        $filePath = self::DATA_DIR . self::RESULTS_FILE;
        file_put_contents($filePath, json_encode($this->results, JSON_PRETTY_PRINT), LOCK_EX);
    }
    
    /**
     * Retourne le variant assigné à une session pour un test
     * Assigne un variant si la session n'en a pas encore
     * 
     * @param string $sessionId ID de session
     * @param string $testId ID du test
     * @return string|null Nom du variant ou null si test inactif
     */
    public function getVariant(string $sessionId, string $testId): ?string {
        // Vérifier si le A/B testing est activé
        if (!($this->config['enabled'] ?? true)) {
            return 'control';
        }
        
        // Vérifier si le test existe et est actif
        $test = $this->config['tests'][$testId] ?? null;
        
        if (!$test || !($test['active'] ?? true)) {
            return 'control';
        }
        
        // Vérifier si la session a déjà un variant assigné pour ce test
        $assignmentKey = $sessionId . ':' . $testId;
        
        if (isset($this->assignments[$assignmentKey])) {
            return $this->assignments[$assignmentKey]['variant'];
        }
        
        // Assigner un variant basé sur les poids
        $variant = $this->selectVariant($test['variants']);
        
        // Sauvegarder l'assignation
        $this->assignments[$assignmentKey] = [
            'variant' => $variant,
            'assigned_at' => time(),
            'converted' => false
        ];
        
        $this->saveAssignments();
        
        // Incrémenter le compteur d'expositions
        $this->trackExposure($testId, $variant);
        
        return $variant;
    }
    
    /**
     * Sélectionne un variant basé sur les poids
     * 
     * @param array $variants Configuration des variants
     * @return string Nom du variant sélectionné
     */
    private function selectVariant(array $variants): string {
        $totalWeight = 0;
        foreach ($variants as $name => $config) {
            $totalWeight += $config['weight'] ?? 50;
        }
        
        $random = mt_rand(1, $totalWeight);
        $cumulative = 0;
        
        foreach ($variants as $name => $config) {
            $cumulative += $config['weight'] ?? 50;
            if ($random <= $cumulative) {
                return $name;
            }
        }
        
        // Fallback au premier variant
        return array_key_first($variants) ?? 'control';
    }
    
    /**
     * Track une exposition à un variant
     */
    private function trackExposure(string $testId, string $variant): void {
        if (!isset($this->results[$testId])) {
            $this->results[$testId] = [];
        }
        
        if (!isset($this->results[$testId][$variant])) {
            $this->results[$testId][$variant] = [
                'exposures' => 0,
                'conversions' => 0
            ];
        }
        
        $this->results[$testId][$variant]['exposures']++;
        $this->saveResults();
    }
    
    /**
     * Track une conversion pour un test
     * 
     * @param string $sessionId ID de session
     * @param string $testId ID du test
     * @return bool Succès
     */
    public function trackConversion(string $sessionId, string $testId): bool {
        $assignmentKey = $sessionId . ':' . $testId;
        
        if (!isset($this->assignments[$assignmentKey])) {
            return false;
        }
        
        // Éviter les doubles comptages
        if ($this->assignments[$assignmentKey]['converted']) {
            return false;
        }
        
        $variant = $this->assignments[$assignmentKey]['variant'];
        
        // Marquer comme converti
        $this->assignments[$assignmentKey]['converted'] = true;
        $this->assignments[$assignmentKey]['converted_at'] = time();
        $this->saveAssignments();
        
        // Incrémenter le compteur de conversions
        if (!isset($this->results[$testId][$variant])) {
            $this->results[$testId][$variant] = ['exposures' => 0, 'conversions' => 0];
        }
        
        $this->results[$testId][$variant]['conversions']++;
        $this->saveResults();
        
        return true;
    }
    
    /**
     * Retourne les résultats d'un test
     * 
     * @param string $testId ID du test
     * @return array Résultats avec statistiques
     */
    public function getTestResults(string $testId): array {
        $test = $this->config['tests'][$testId] ?? null;
        
        if (!$test) {
            return ['error' => 'Test not found'];
        }
        
        $results = $this->results[$testId] ?? [];
        $variantResults = [];
        
        foreach ($test['variants'] as $variantName => $variantConfig) {
            $data = $results[$variantName] ?? ['exposures' => 0, 'conversions' => 0];
            
            $exposures = $data['exposures'];
            $conversions = $data['conversions'];
            $conversionRate = $exposures > 0 ? round($conversions / $exposures * 100, 2) : 0;
            
            $variantResults[$variantName] = [
                'exposures' => $exposures,
                'conversions' => $conversions,
                'conversion_rate' => $conversionRate,
                'config' => $variantConfig
            ];
        }
        
        // Calculer le gagnant
        $winner = null;
        $bestRate = 0;
        
        foreach ($variantResults as $name => $data) {
            if ($data['conversion_rate'] > $bestRate && $data['exposures'] >= 30) {
                $bestRate = $data['conversion_rate'];
                $winner = $name;
            }
        }
        
        return [
            'test_id' => $testId,
            'test_name' => $test['name'] ?? $testId,
            'metric' => $test['metric'] ?? 'conversion',
            'active' => $test['active'] ?? true,
            'variants' => $variantResults,
            'winner' => $winner,
            'statistical_significance' => $this->calculateSignificance($variantResults)
        ];
    }
    
    /**
     * Calcule la significance statistique (simplifié)
     */
    private function calculateSignificance(array $variantResults): string {
        if (count($variantResults) < 2) {
            return 'insufficient_data';
        }
        
        // Vérifier le minimum d'échantillons
        $minExposures = 100;
        foreach ($variantResults as $data) {
            if ($data['exposures'] < $minExposures) {
                return 'insufficient_data';
            }
        }
        
        // Calcul simplifié de la différence
        $rates = array_column($variantResults, 'conversion_rate');
        sort($rates);
        
        $difference = end($rates) - reset($rates);
        
        if ($difference < 2) {
            return 'not_significant';
        } elseif ($difference < 5) {
            return 'low_confidence';
        } elseif ($difference < 10) {
            return 'medium_confidence';
        } else {
            return 'high_confidence';
        }
    }
    
    /**
     * Retourne tous les tests actifs
     */
    public function getActiveTests(): array {
        $activeTests = [];
        
        foreach ($this->config['tests'] as $testId => $test) {
            if ($test['active'] ?? true) {
                $activeTests[$testId] = [
                    'id' => $testId,
                    'name' => $test['name'] ?? $testId,
                    'metric' => $test['metric'] ?? 'conversion',
                    'variants' => array_keys($test['variants'])
                ];
            }
        }
        
        return $activeTests;
    }
    
    /**
     * Retourne un résumé de tous les tests
     */
    public function getAllTestResults(): array {
        $summary = [];
        
        foreach ($this->config['tests'] as $testId => $test) {
            $summary[$testId] = $this->getTestResults($testId);
        }
        
        return $summary;
    }
    
    /**
     * Retourne la configuration d'un variant
     * 
     * @param string $testId ID du test
     * @param string $variant Nom du variant
     * @return array Configuration du variant
     */
    public function getVariantConfig(string $testId, string $variant): array {
        return $this->config['tests'][$testId]['variants'][$variant] ?? [];
    }
    
    /**
     * Active ou désactive un test
     */
    public function setTestActive(string $testId, bool $active): bool {
        if (!isset($this->config['tests'][$testId])) {
            return false;
        }
        
        $this->config['tests'][$testId]['active'] = $active;
        
        // Sauvegarder la config mise à jour
        $this->saveConfig();
        
        return true;
    }
    
    /**
     * Sauvegarde la configuration
     */
    private function saveConfig(): void {
        $configFile = __DIR__ . '/data/ab-tests-config.php';
        $content = "<?php\n// Auto-generated A/B tests configuration\nreturn " . var_export($this->config, true) . ";\n";
        file_put_contents($configFile, $content);
    }
    
    /**
     * Nettoie les anciennes assignations
     * 
     * @param int $olderThanDays Supprimer les assignations plus vieilles que X jours
     * @return int Nombre d'assignations supprimées
     */
    public function cleanupOldAssignments(int $olderThanDays = 30): int {
        $cutoff = time() - ($olderThanDays * 86400);
        $deleted = 0;
        
        foreach ($this->assignments as $key => $assignment) {
            if (($assignment['assigned_at'] ?? 0) < $cutoff) {
                unset($this->assignments[$key]);
                $deleted++;
            }
        }
        
        if ($deleted > 0) {
            $this->saveAssignments();
        }
        
        return $deleted;
    }
}

/**
 * API endpoint
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'ab-testing-service.php') {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    $abService = ABTestingService::getInstance();
    $action = $_GET['action'] ?? $_POST['action'] ?? 'tests';
    
    $response = ['success' => false, 'error' => 'Action non reconnue'];
    
    switch ($action) {
        case 'tests':
            $response = [
                'success' => true,
                'data' => $abService->getActiveTests()
            ];
            break;
            
        case 'variant':
            $sessionId = $_GET['session_id'] ?? '';
            $testId = $_GET['test_id'] ?? '';
            
            if (empty($sessionId) || empty($testId)) {
                $response = ['success' => false, 'error' => 'session_id et test_id requis'];
            } else {
                $variant = $abService->getVariant($sessionId, $testId);
                $config = $abService->getVariantConfig($testId, $variant);
                
                $response = [
                    'success' => true,
                    'variant' => $variant,
                    'config' => $config
                ];
            }
            break;
            
        case 'convert':
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $sessionId = $input['session_id'] ?? $_GET['session_id'] ?? '';
            $testId = $input['test_id'] ?? $_GET['test_id'] ?? '';
            
            if (empty($sessionId) || empty($testId)) {
                $response = ['success' => false, 'error' => 'session_id et test_id requis'];
            } else {
                $converted = $abService->trackConversion($sessionId, $testId);
                $response = ['success' => true, 'converted' => $converted];
            }
            break;
            
        case 'results':
            $testId = $_GET['test_id'] ?? '';
            
            if (empty($testId)) {
                $response = [
                    'success' => true,
                    'data' => $abService->getAllTestResults()
                ];
            } else {
                $response = [
                    'success' => true,
                    'data' => $abService->getTestResults($testId)
                ];
            }
            break;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

