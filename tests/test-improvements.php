<?php
/**
 * Tests Unitaires - 5 Améliorations
 * Tests du CacheManager, WebhookDispatcher, AnalyticsService, SyncService, ABTestingService
 * 
 * @package VisaChatbot
 */

// Charger les dépendances
require_once __DIR__ . '/../php/config.php';
require_once __DIR__ . '/../php/cache-manager.php';
require_once __DIR__ . '/../php/webhook-dispatcher.php';
require_once __DIR__ . '/../php/analytics-service.php';
require_once __DIR__ . '/../php/sync-service.php';
require_once __DIR__ . '/../php/ab-testing-service.php';

class ImprovementsTestSuite {
    
    private array $results = [];
    private int $passed = 0;
    private int $failed = 0;
    
    public function run(): void {
        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║     TESTS UNITAIRES - 5 AMÉLIORATIONS                        ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n\n";
        
        $this->testCacheManager();
        $this->testWebhookDispatcher();
        $this->testAnalyticsService();
        $this->testSyncService();
        $this->testABTestingService();
        
        $this->printSummary();
    }
    
    // ========================================
    // CACHE MANAGER TESTS
    // ========================================
    
    private function testCacheManager(): void {
        echo "━━━ 1. CACHE MANAGER ━━━\n";
        
        $cache = CacheManager::getInstance();
        
        // Test 1: Set and Get
        $testKey = 'test_key_' . uniqid();
        $testValue = ['name' => 'Test', 'data' => [1, 2, 3]];
        
        $cache->set($testKey, $testValue, 60);
        $retrieved = $cache->get($testKey);
        
        $this->assert(
            'Set and Get',
            $retrieved === $testValue,
            'La valeur récupérée doit correspondre à la valeur stockée'
        );
        
        // Test 2: Has
        $this->assert(
            'Has (exists)',
            $cache->has($testKey) === true,
            'has() doit retourner true pour une clé existante'
        );
        
        $this->assert(
            'Has (not exists)',
            $cache->has('nonexistent_key_xyz') === false,
            'has() doit retourner false pour une clé inexistante'
        );
        
        // Test 3: Delete
        $cache->delete($testKey);
        $this->assert(
            'Delete',
            $cache->get($testKey) === null,
            'La valeur doit être null après suppression'
        );
        
        // Test 4: Remember (cache-aside)
        $testKey2 = 'remember_test_' . uniqid();
        $callCount = 0;
        
        $value1 = $cache->remember($testKey2, function() use (&$callCount) {
            $callCount++;
            return 'computed_value';
        }, 60);
        
        $value2 = $cache->remember($testKey2, function() use (&$callCount) {
            $callCount++;
            return 'computed_value_2';
        }, 60);
        
        $this->assert(
            'Remember (cache-aside)',
            $callCount === 1 && $value1 === 'computed_value' && $value2 === 'computed_value',
            'Le callback ne doit être appelé qu\'une fois'
        );
        
        // Test 5: Increment
        $counterKey = 'counter_test_' . uniqid();
        $cache->set($counterKey, 0, 60);
        $cache->increment($counterKey);
        $cache->increment($counterKey, 5);
        
        $this->assert(
            'Increment',
            $cache->get($counterKey) === 6,
            'Le compteur doit être à 6 après incréments'
        );
        
        // Test 6: Stats
        $stats = $cache->getStats();
        
        $this->assert(
            'Stats',
            isset($stats['entries']) && isset($stats['total_size']),
            'Les statistiques doivent contenir entries et total_size'
        );
        
        // Cleanup
        $cache->delete($testKey2);
        $cache->delete($counterKey);
        
        echo "\n";
    }
    
    // ========================================
    // WEBHOOK DISPATCHER TESTS
    // ========================================
    
    private function testWebhookDispatcher(): void {
        echo "━━━ 2. WEBHOOK DISPATCHER ━━━\n";
        
        $webhooks = WebhookDispatcher::getInstance();
        
        // Test 1: Constants exist
        $this->assert(
            'Event constants',
            defined('WebhookDispatcher::EVENT_SESSION_CREATED') && 
            defined('WebhookDispatcher::EVENT_APPLICATION_SUBMITTED'),
            'Les constantes d\'événements doivent être définies'
        );
        
        // Test 2: List webhooks
        $list = $webhooks->listWebhooks();
        
        $this->assert(
            'List webhooks',
            is_array($list),
            'listWebhooks() doit retourner un tableau'
        );
        
        // Test 3: Stats
        $stats = $webhooks->getStats();
        
        $this->assert(
            'Stats structure',
            isset($stats['total_webhooks']) && isset($stats['active_webhooks']) && isset($stats['queue_size']),
            'Les statistiques doivent contenir total_webhooks, active_webhooks, queue_size'
        );
        
        // Test 4: Dispatch (no error)
        try {
            $result = $webhooks->dispatch(WebhookDispatcher::EVENT_SESSION_CREATED, [
                'test' => true,
                'session_id' => 'test_123'
            ]);
            
            $this->assert(
                'Dispatch event',
                is_array($result),
                'dispatch() doit retourner un tableau de résultats'
            );
        } catch (Exception $e) {
            $this->assert(
                'Dispatch event',
                false,
                'dispatch() ne doit pas lever d\'exception: ' . $e->getMessage()
            );
        }
        
        // Test 5: Signature verification
        $payload = '{"test": "data"}';
        $secret = 'test_secret';
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        
        $this->assert(
            'Verify signature',
            WebhookDispatcher::verifySignature($payload, $signature, $secret) === true,
            'La vérification de signature doit réussir pour une signature valide'
        );
        
        $this->assert(
            'Verify invalid signature',
            WebhookDispatcher::verifySignature($payload, 'invalid', $secret) === false,
            'La vérification doit échouer pour une signature invalide'
        );
        
        echo "\n";
    }
    
    // ========================================
    // ANALYTICS SERVICE TESTS
    // ========================================
    
    private function testAnalyticsService(): void {
        echo "━━━ 3. ANALYTICS SERVICE ━━━\n";
        
        $analytics = AnalyticsService::getInstance();
        
        // Test 1: Track event
        $testSessionId = 'test_session_' . uniqid();
        
        try {
            $analytics->trackEvent($testSessionId, AnalyticsService::EVENT_SESSION_START, [
                'test' => true
            ]);
            
            $this->assert(
                'Track event',
                true,
                'trackEvent() doit s\'exécuter sans erreur'
            );
        } catch (Exception $e) {
            $this->assert(
                'Track event',
                false,
                'Erreur: ' . $e->getMessage()
            );
        }
        
        // Test 2: Track step complete
        try {
            $analytics->trackStepComplete($testSessionId, 'welcome', 5.5);
            
            $this->assert(
                'Track step complete',
                true,
                'trackStepComplete() doit s\'exécuter sans erreur'
            );
        } catch (Exception $e) {
            $this->assert(
                'Track step complete',
                false,
                'Erreur: ' . $e->getMessage()
            );
        }
        
        // Test 3: Get aggregate metrics
        $metrics = $analytics->getAggregateMetrics();
        
        $this->assert(
            'Get aggregate metrics',
            is_array($metrics) && isset($metrics['total_sessions']),
            'getAggregateMetrics() doit retourner un tableau avec total_sessions'
        );
        
        // Test 4: Get step metrics
        $stepMetrics = $analytics->getStepMetrics('welcome');
        
        $this->assert(
            'Get step metrics',
            isset($stepMetrics['step']) && isset($stepMetrics['completion_rate']),
            'getStepMetrics() doit retourner step et completion_rate'
        );
        
        // Test 5: Get abandonment rate
        $rate = $analytics->getAbandonmentRate();
        
        $this->assert(
            'Get abandonment rate',
            is_numeric($rate) && $rate >= 0 && $rate <= 100,
            'getAbandonmentRate() doit retourner un pourcentage valide'
        );
        
        // Test 6: Get dashboard summary
        $summary = $analytics->getDashboardSummary();
        
        $this->assert(
            'Get dashboard summary',
            isset($summary['overview']) && isset($summary['passport_scans']) && isset($summary['steps_performance']),
            'getDashboardSummary() doit retourner overview, passport_scans, steps_performance'
        );
        
        echo "\n";
    }
    
    // ========================================
    // SYNC SERVICE TESTS
    // ========================================
    
    private function testSyncService(): void {
        echo "━━━ 4. SYNC SERVICE ━━━\n";
        
        $sync = SyncService::getInstance();
        
        // Test 1: Generate sync token
        $testSessionId = 'test_session_' . uniqid();
        $tokenData = $sync->generateSyncToken($testSessionId);
        
        $this->assert(
            'Generate sync token',
            isset($tokenData['token']) && strlen($tokenData['token']) === 32,
            'Le token généré doit avoir 32 caractères hexadécimaux'
        );
        
        $this->assert(
            'Token has expiry',
            isset($tokenData['expires_at']) && $tokenData['expires_at'] > time(),
            'Le token doit avoir une date d\'expiration future'
        );
        
        $this->assert(
            'Token has sync URL',
            isset($tokenData['sync_url']) && strpos($tokenData['sync_url'], $tokenData['token']) !== false,
            'L\'URL de sync doit contenir le token'
        );
        
        // Test 2: Validate token
        $validatedSessionId = $sync->validateToken($tokenData['token']);
        
        $this->assert(
            'Validate token',
            $validatedSessionId === $testSessionId,
            'Le token validé doit retourner le session ID original'
        );
        
        // Test 3: Mark token used
        $sync->markTokenUsed($tokenData['token']);
        
        // Validate again (should still work within 5 min grace period)
        $validatedAgain = $sync->validateToken($tokenData['token']);
        
        $this->assert(
            'Token grace period',
            $validatedAgain === $testSessionId,
            'Le token doit rester valide dans la période de grâce de 5 minutes'
        );
        
        // Test 4: Invalid token
        $invalidResult = $sync->validateToken('invalid_token_xyz');
        
        $this->assert(
            'Invalid token',
            $invalidResult === null,
            'Un token invalide doit retourner null'
        );
        
        // Test 5: QR code data
        $qrData = $sync->getQRCodeData($tokenData['token']);
        
        $this->assert(
            'QR code data',
            isset($qrData['url']) && isset($qrData['qr_api_url']),
            'getQRCodeData() doit retourner url et qr_api_url'
        );
        
        // Test 6: Stats
        $stats = $sync->getStats();
        
        $this->assert(
            'Stats',
            isset($stats['total']) && isset($stats['active']),
            'getStats() doit retourner total et active'
        );
        
        // Cleanup
        $sync->invalidateToken($tokenData['token']);
        
        echo "\n";
    }
    
    // ========================================
    // A/B TESTING SERVICE TESTS
    // ========================================
    
    private function testABTestingService(): void {
        echo "━━━ 5. A/B TESTING SERVICE ━━━\n";
        
        $ab = ABTestingService::getInstance();
        
        // Test 1: Get active tests
        $activeTests = $ab->getActiveTests();
        
        $this->assert(
            'Get active tests',
            is_array($activeTests),
            'getActiveTests() doit retourner un tableau'
        );
        
        // Test 2: Get variant for session
        $testSessionId = 'test_ab_session_' . uniqid();
        $variant = $ab->getVariant($testSessionId, 'welcome_message');
        
        $this->assert(
            'Get variant',
            in_array($variant, ['control', 'friendly']),
            'getVariant() doit retourner un variant valide (control ou friendly)'
        );
        
        // Test 3: Same session gets same variant
        $variant2 = $ab->getVariant($testSessionId, 'welcome_message');
        
        $this->assert(
            'Consistent variant',
            $variant === $variant2,
            'La même session doit toujours recevoir le même variant'
        );
        
        // Test 4: Get variant config
        $config = $ab->getVariantConfig('welcome_message', $variant);
        
        $this->assert(
            'Get variant config',
            is_array($config),
            'getVariantConfig() doit retourner un tableau de configuration'
        );
        
        // Test 5: Track conversion
        $converted = $ab->trackConversion($testSessionId, 'welcome_message');
        
        $this->assert(
            'Track conversion',
            $converted === true,
            'trackConversion() doit retourner true pour une première conversion'
        );
        
        // Test 6: Double conversion prevented
        $converted2 = $ab->trackConversion($testSessionId, 'welcome_message');
        
        $this->assert(
            'Prevent double conversion',
            $converted2 === false,
            'trackConversion() doit retourner false pour une double conversion'
        );
        
        // Test 7: Get test results
        $results = $ab->getTestResults('welcome_message');
        
        $this->assert(
            'Get test results',
            isset($results['test_id']) && isset($results['variants']),
            'getTestResults() doit retourner test_id et variants'
        );
        
        // Test 8: Get all results
        $allResults = $ab->getAllTestResults();
        
        $this->assert(
            'Get all test results',
            is_array($allResults) && count($allResults) > 0,
            'getAllTestResults() doit retourner un tableau non vide'
        );
        
        echo "\n";
    }
    
    // ========================================
    // HELPERS
    // ========================================
    
    private function assert(string $name, bool $condition, string $description): void {
        if ($condition) {
            echo "  ✅ {$name}\n";
            $this->passed++;
        } else {
            echo "  ❌ {$name}\n";
            echo "     └─ {$description}\n";
            $this->failed++;
        }
        
        $this->results[] = [
            'name' => $name,
            'passed' => $condition,
            'description' => $description
        ];
    }
    
    private function printSummary(): void {
        $total = $this->passed + $this->failed;
        $percentage = $total > 0 ? round($this->passed / $total * 100) : 0;
        
        echo "══════════════════════════════════════════════════════════════\n";
        echo "                        RÉSUMÉ\n";
        echo "══════════════════════════════════════════════════════════════\n";
        echo "\n";
        echo "  Total:    {$total} tests\n";
        echo "  Passés:   {$this->passed} ✅\n";
        echo "  Échoués:  {$this->failed} ❌\n";
        echo "  Taux:     {$percentage}%\n";
        echo "\n";
        
        if ($this->failed === 0) {
            echo "  🎉 TOUS LES TESTS PASSENT!\n";
        } else {
            echo "  ⚠️  Certains tests ont échoué\n";
        }
        
        echo "\n";
    }
}

// Exécuter les tests
$suite = new ImprovementsTestSuite();
$suite->run();

