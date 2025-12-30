<?php
/**
 * Tests fonctionnels unitaires - Chatbot Visa CI
 * Tests avec différents personas
 * 
 * @package VisaChatbot
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Base URL de l'API
define('API_BASE', 'http://localhost:8888/hunyuanocr/visa-chatbot/php/chat-handler.php');

/**
 * Classe de test
 */
class ChatbotTester {
    
    private array $results = [];
    private int $passed = 0;
    private int $failed = 0;
    
    /**
     * Appelle l'API
     */
    private function callApi(string $method, array $params = [], ?string $sessionId = null): array {
        $url = API_BASE;
        
        if ($method === 'GET') {
            $url .= '?' . http_build_query($params);
            $context = stream_context_create([
                'http' => ['method' => 'GET', 'timeout' => 10]
            ]);
        } else {
            if ($sessionId) {
                $params['session_id'] = $sessionId;
            }
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n" . 
                               ($sessionId ? "X-Session-ID: $sessionId\r\n" : ''),
                    'content' => json_encode($params),
                    'timeout' => 10
                ]
            ]);
        }
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return ['success' => false, 'error' => 'Connection failed'];
        }
        
        return json_decode($response, true) ?? ['success' => false, 'error' => 'Invalid JSON'];
    }
    
    /**
     * Envoie un message
     */
    private function sendMessage(string $sessionId, string $message): array {
        return $this->callApi('POST', [
            'action' => 'message',
            'message' => $message
        ], $sessionId);
    }
    
    /**
     * Envoie des données OCR de passeport
     */
    private function sendPassportOCR(string $sessionId, string $passportType, array $fields): array {
        $mrzFirstChar = match($passportType) {
            'DIPLOMATIQUE' => 'D',
            'SERVICE' => 'S',
            'LP_ONU' => 'U',
            'LP_UA' => 'A',
            default => 'P'
        };
        
        return $this->callApi('POST', [
            'action' => 'passport_ocr',
            'ocr_data' => [
                'mrz' => [
                    'line1' => $mrzFirstChar . '<ETHTEST<<' . strtoupper($fields['given_names'] ?? 'JOHN') . '<<<<<<<<<<<<<<<<<<<<<<',
                    'line2' => ($fields['passport_number'] ?? 'AB1234567') . 'ETH9001015M2712312<<<<<<<<<<<<<<08'
                ],
                'fields' => [
                    'surname' => ['value' => $fields['surname'] ?? 'TEST', 'confidence' => 0.95],
                    'given_names' => ['value' => $fields['given_names'] ?? 'JOHN', 'confidence' => 0.93],
                    'passport_number' => ['value' => $fields['passport_number'] ?? 'AB1234567', 'confidence' => 0.98],
                    'nationality' => ['value' => $fields['nationality'] ?? 'ETH', 'confidence' => 0.97],
                    'date_of_birth' => ['value' => $fields['date_of_birth'] ?? '1990-01-01', 'confidence' => 0.95],
                    'date_of_expiry' => ['value' => $fields['date_of_expiry'] ?? '2027-12-31', 'confidence' => 0.96]
                ]
            ]
        ], $sessionId);
    }
    
    /**
     * Assert
     */
    private function assert(string $testName, bool $condition, string $message = ''): void {
        if ($condition) {
            $this->passed++;
            $this->results[] = ['test' => $testName, 'status' => 'PASS', 'message' => $message];
            echo "✅ PASS: $testName\n";
        } else {
            $this->failed++;
            $this->results[] = ['test' => $testName, 'status' => 'FAIL', 'message' => $message];
            echo "❌ FAIL: $testName - $message\n";
        }
    }
    
    /**
     * PERSONA 1: Touriste éthiopien avec passeport ordinaire
     */
    public function testPersona1_TouristeEthiopien(): void {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "🧪 PERSONA 1: Touriste éthiopien (Passeport ordinaire)\n";
        echo str_repeat('=', 60) . "\n";
        
        // Init session
        $init = $this->callApi('GET', ['action' => 'init']);
        $this->assert('P1: Init session', $init['success'] ?? false, 'Session initialization');
        
        $sessionId = $init['data']['session_id'] ?? null;
        $this->assert('P1: Session ID exists', !empty($sessionId), 'Got: ' . ($sessionId ?? 'null'));
        
        if (!$sessionId) return;
        
        // Sélectionner français
        $lang = $this->sendMessage($sessionId, 'fr');
        $this->assert('P1: Language selection', $lang['success'] ?? false);
        $this->assert('P1: Step changed to residence', 
            ($lang['data']['step_info']['current'] ?? '') === 'residence',
            'Current: ' . ($lang['data']['step_info']['current'] ?? 'unknown'));
        
        // Sélectionner pays (Éthiopie)
        $country = $this->sendMessage($sessionId, 'ETH');
        $this->assert('P1: Country selection', $country['success'] ?? false);
        $this->assert('P1: City question asked', 
            strpos($country['data']['bot_message']['content'] ?? '', 'ville') !== false,
            'Expected city question');
        
        // Entrer ville
        $city = $this->sendMessage($sessionId, 'Addis Abeba');
        $this->assert('P1: City entry', $city['success'] ?? false);
        $this->assert('P1: Step changed to passport',
            ($city['data']['step_info']['current'] ?? '') === 'passport',
            'Current: ' . ($city['data']['step_info']['current'] ?? 'unknown'));
        
        // Scan passeport ORDINAIRE
        $passport = $this->sendPassportOCR($sessionId, 'ORDINAIRE', [
            'surname' => 'BEKELE',
            'given_names' => 'ABEBE',
            'passport_number' => 'EP123456',
            'nationality' => 'ETH'
        ]);
        $this->assert('P1: Passport OCR', $passport['success'] ?? false);
        $this->assert('P1: Workflow ORDINAIRE', 
            ($passport['data']['workflow_category'] ?? '') === 'ORDINAIRE',
            'Got: ' . ($passport['data']['workflow_category'] ?? 'null'));
        $this->assert('P1: Not free (ORDINAIRE)',
            ($passport['data']['is_free'] ?? true) === false,
            'is_free should be false');
        
        // Vérifier que les frais sont mentionnés
        $content = $passport['data']['bot_message']['content'] ?? '';
        $this->assert('P1: Fees mentioned', 
            strpos($content, '73 000 XOF') !== false || strpos($content, '73000') !== false,
            'Should mention fees');
        $this->assert('P1: Express option mentioned',
            strpos($content, 'Express') !== false,
            'Should mention express option');
        $this->assert('P1: Processing time standard',
            strpos($content, '5-10') !== false,
            'Should mention 5-10 days');
    }
    
    /**
     * PERSONA 2: Diplomate kenyan
     */
    public function testPersona2_DiplomateKenyan(): void {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "🧪 PERSONA 2: Diplomate kenyan (Passeport diplomatique)\n";
        echo str_repeat('=', 60) . "\n";
        
        // Init
        $init = $this->callApi('GET', ['action' => 'init']);
        $sessionId = $init['data']['session_id'] ?? null;
        $this->assert('P2: Init session', !empty($sessionId));
        
        if (!$sessionId) return;
        
        // Workflow rapide vers passeport
        $this->sendMessage($sessionId, 'en'); // English
        $this->sendMessage($sessionId, 'KEN'); // Kenya
        $city = $this->sendMessage($sessionId, 'Nairobi');
        
        $this->assert('P2: Reached passport step',
            ($city['data']['step_info']['current'] ?? '') === 'passport');
        
        // Scan passeport DIPLOMATIQUE
        $passport = $this->sendPassportOCR($sessionId, 'DIPLOMATIQUE', [
            'surname' => 'OCHIENG',
            'given_names' => 'JAMES',
            'passport_number' => 'DP987654',
            'nationality' => 'KEN'
        ]);
        
        $this->assert('P2: Passport OCR success', $passport['success'] ?? false);
        $this->assert('P2: Workflow DIPLOMATIQUE',
            ($passport['data']['workflow_category'] ?? '') === 'DIPLOMATIQUE',
            'Got: ' . ($passport['data']['workflow_category'] ?? 'null'));
        $this->assert('P2: Is FREE',
            ($passport['data']['is_free'] ?? false) === true,
            'Diplomatic should be free');
        
        $content = $passport['data']['bot_message']['content'] ?? '';
        $this->assert('P2: PRIORITY workflow mentioned',
            strpos($content, 'PRIORITY') !== false,
            'Should mention PRIORITY');
        $this->assert('P2: FREE mentioned',
            strpos($content, 'FREE') !== false || strpos($content, 'GRATUIT') !== false,
            'Should mention FREE');
        $this->assert('P2: 24-48h processing',
            strpos($content, '24-48h') !== false,
            'Should mention 24-48h');
        $this->assert('P2: Verbal note required',
            strpos($content, 'verbal') !== false || strpos($content, 'Verbal') !== false,
            'Should mention verbal note');
    }
    
    /**
     * PERSONA 3: Homme d'affaires tanzanien (entrées multiples)
     */
    public function testPersona3_BusinessTanzanien(): void {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "🧪 PERSONA 3: Business tanzanien (Entrées multiples)\n";
        echo str_repeat('=', 60) . "\n";
        
        $init = $this->callApi('GET', ['action' => 'init']);
        $sessionId = $init['data']['session_id'] ?? null;
        $this->assert('P3: Init session', !empty($sessionId));
        
        if (!$sessionId) return;
        
        // Workflow vers passeport
        $this->sendMessage($sessionId, 'fr');
        $this->sendMessage($sessionId, 'TZA');
        $city = $this->sendMessage($sessionId, 'Dar es Salaam');
        
        $this->assert('P3: Reached passport step',
            ($city['data']['step_info']['current'] ?? '') === 'passport');
        
        // Passeport ordinaire
        $passport = $this->sendPassportOCR($sessionId, 'ORDINAIRE', [
            'surname' => 'MWAMBA',
            'given_names' => 'FRANCIS',
            'passport_number' => 'TZ456789',
            'nationality' => 'TZA'
        ]);
        
        $this->assert('P3: Workflow ORDINAIRE', 
            ($passport['data']['workflow_category'] ?? '') === 'ORDINAIRE');
        
        // Confirmer le passeport
        $confirm = $this->sendMessage($sessionId, 'confirm');
        $this->assert('P3: Confirm passport -> photo step',
            ($confirm['data']['step_info']['current'] ?? '') === 'photo',
            'Current: ' . ($confirm['data']['step_info']['current'] ?? 'unknown'));
    }
    
    /**
     * PERSONA 4: Officier ONU ougandais
     */
    public function testPersona4_OfficierONU(): void {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "🧪 PERSONA 4: Officier ONU ougandais (LP_ONU)\n";
        echo str_repeat('=', 60) . "\n";
        
        $init = $this->callApi('GET', ['action' => 'init']);
        $sessionId = $init['data']['session_id'] ?? null;
        $this->assert('P4: Init session', !empty($sessionId));
        
        if (!$sessionId) return;
        
        $this->sendMessage($sessionId, 'en');
        $this->sendMessage($sessionId, 'UGA');
        $this->sendMessage($sessionId, 'Kampala');
        
        // Passeport LP_ONU (laissez-passer ONU)
        $passport = $this->sendPassportOCR($sessionId, 'LP_ONU', [
            'surname' => 'NAKATO',
            'given_names' => 'GRACE',
            'passport_number' => 'UN123456',
            'nationality' => 'UGA'
        ]);
        
        $this->assert('P4: LP_ONU detected',
            $passport['success'] ?? false,
            'OCR should succeed');
        
        // LP_ONU devrait être gratuit et prioritaire
        $this->assert('P4: Is FREE (LP_ONU)',
            ($passport['data']['is_free'] ?? false) === true,
            'LP_ONU should be free');
        
        $content = $passport['data']['bot_message']['content'] ?? '';
        $this->assert('P4: PRIORITY mentioned',
            strpos($content, 'PRIORITY') !== false,
            'Should be priority workflow');
    }
    
    /**
     * PERSONA 5: Personne hors juridiction (France)
     */
    public function testPersona5_HorsJuridiction(): void {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "🧪 PERSONA 5: Personne hors juridiction (France)\n";
        echo str_repeat('=', 60) . "\n";
        
        $init = $this->callApi('GET', ['action' => 'init']);
        $sessionId = $init['data']['session_id'] ?? null;
        $this->assert('P5: Init session', !empty($sessionId));
        
        if (!$sessionId) return;
        
        $this->sendMessage($sessionId, 'fr');
        
        // Essayer la France (hors juridiction)
        $country = $this->sendMessage($sessionId, 'France');
        
        $this->assert('P5: Country rejected', $country['success'] ?? false);
        
        $content = $country['data']['bot_message']['content'] ?? '';
        $blocking = $country['data']['metadata']['blocking'] ?? false;
        
        $this->assert('P5: Blocking response',
            $blocking === true || strpos($content, 'juridiction') !== false,
            'Should indicate out of jurisdiction');
        $this->assert('P5: Session blocked',
            ($country['data']['status'] ?? '') === 'blocked',
            'Status: ' . ($country['data']['status'] ?? 'unknown'));
    }
    
    /**
     * PERSONA 6: Test de navigation bidirectionnelle
     */
    public function testPersona6_NavigationBidirectionnelle(): void {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "🧪 PERSONA 6: Navigation bidirectionnelle\n";
        echo str_repeat('=', 60) . "\n";
        
        $init = $this->callApi('GET', ['action' => 'init']);
        $sessionId = $init['data']['session_id'] ?? null;
        $this->assert('P6: Init session', !empty($sessionId));
        
        if (!$sessionId) return;
        
        // Avancer jusqu'à passeport
        $this->sendMessage($sessionId, 'fr');
        $this->sendMessage($sessionId, 'ETH');
        $this->sendMessage($sessionId, 'Addis Abeba');
        
        // Vérifier highest_reached
        $passport = $this->sendPassportOCR($sessionId, 'ORDINAIRE', [
            'surname' => 'NAV', 'given_names' => 'TEST'
        ]);
        
        $highestReached = $passport['data']['step_info']['highest_reached'] ?? 0;
        $this->assert('P6: Highest step tracked',
            $highestReached >= 2,
            'Highest: ' . $highestReached);
        
        // Tester navigation retour vers résidence
        $navigate = $this->callApi('POST', [
            'action' => 'navigate',
            'target_step' => 'residence'
        ], $sessionId);
        
        $this->assert('P6: Navigate back success', $navigate['success'] ?? false);
        $this->assert('P6: Now at residence step',
            ($navigate['data']['step_info']['current'] ?? '') === 'residence',
            'Current: ' . ($navigate['data']['step_info']['current'] ?? 'unknown'));
        
        // Tester navigation vers étape non accessible
        $navigateFail = $this->callApi('POST', [
            'action' => 'navigate',
            'target_step' => 'confirm'
        ], $sessionId);
        
        // Devrait échouer ou montrer un message d'erreur
        $content = $navigateFail['data']['bot_message']['content'] ?? '';
        $this->assert('P6: Cannot navigate to future step',
            strpos($content, 'Impossible') !== false || strpos($content, 'Cannot') !== false,
            'Should block navigation to unvisited step');
    }
    
    /**
     * PERSONA 7: Test reset session
     */
    public function testPersona7_ResetSession(): void {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "🧪 PERSONA 7: Reset session\n";
        echo str_repeat('=', 60) . "\n";
        
        $init = $this->callApi('GET', ['action' => 'init']);
        $sessionId = $init['data']['session_id'] ?? null;
        $this->assert('P7: Init session', !empty($sessionId));
        
        if (!$sessionId) return;
        
        // Avancer un peu
        $this->sendMessage($sessionId, 'fr');
        $this->sendMessage($sessionId, 'ETH');
        
        // Reset
        $reset = $this->callApi('POST', ['action' => 'reset'], $sessionId);
        
        $this->assert('P7: Reset success', $reset['success'] ?? false);
        
        $newSessionId = $reset['data']['session_id'] ?? null;
        $this->assert('P7: New session created',
            !empty($newSessionId) && $newSessionId !== $sessionId,
            'Old: ' . substr($sessionId, 0, 20) . '... New: ' . substr($newSessionId ?? '', 0, 20) . '...');
        
        $this->assert('P7: Back to welcome step',
            ($reset['data']['step_info']['current'] ?? '') === 'welcome',
            'Current: ' . ($reset['data']['step_info']['current'] ?? 'unknown'));
    }
    
    /**
     * Affiche le résumé
     */
    public function printSummary(): void {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "📊 RÉSUMÉ DES TESTS\n";
        echo str_repeat('=', 60) . "\n";
        echo "✅ Passés: {$this->passed}\n";
        echo "❌ Échoués: {$this->failed}\n";
        echo "Total: " . ($this->passed + $this->failed) . "\n";
        
        $successRate = $this->passed + $this->failed > 0 
            ? round(($this->passed / ($this->passed + $this->failed)) * 100, 1) 
            : 0;
        echo "Taux de réussite: {$successRate}%\n";
        
        if ($this->failed > 0) {
            echo "\n❌ Tests échoués:\n";
            foreach ($this->results as $result) {
                if ($result['status'] === 'FAIL') {
                    echo "  - {$result['test']}: {$result['message']}\n";
                }
            }
        }
        
        echo str_repeat('=', 60) . "\n";
    }
    
    /**
     * Exécute tous les tests
     */
    public function runAll(): void {
        echo "\n🚀 DÉMARRAGE DES TESTS FONCTIONNELS\n";
        echo "📅 " . date('Y-m-d H:i:s') . "\n";
        
        $this->testPersona1_TouristeEthiopien();
        $this->testPersona2_DiplomateKenyan();
        $this->testPersona3_BusinessTanzanien();
        $this->testPersona4_OfficierONU();
        $this->testPersona5_HorsJuridiction();
        $this->testPersona6_NavigationBidirectionnelle();
        $this->testPersona7_ResetSession();
        
        $this->printSummary();
    }
}

// Exécution
$tester = new ChatbotTester();
$tester->runAll();

