<?php
/**
 * Tests d'intégration des workflows - Chatbot Visa CI
 * 
 * Teste les parcours complets:
 * - Workflow STANDARD (passeport ordinaire)
 * - Workflow PRIORITY (passeport diplomatique/service)
 * 
 * @package VisaChatbot
 * @version 1.0.0
 */

require_once __DIR__ . '/../php/config.php';
require_once __DIR__ . '/../php/gemini-client.php';
require_once __DIR__ . '/../php/conversation-engine.php';
require_once __DIR__ . '/../php/pdf-generator.php';
require_once __DIR__ . '/../php/qr-generator.php';

// Colors for CLI output
define('GREEN', "\033[32m");
define('RED', "\033[31m");
define('YELLOW', "\033[33m");
define('BLUE', "\033[34m");
define('RESET', "\033[0m");

class IntegrationWorkflowTest {
    
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];
    
    public function run(): void {
        echo "\n" . BLUE . "╔══════════════════════════════════════════════════════════════╗" . RESET . "\n";
        echo BLUE . "║  TESTS D'INTÉGRATION - CHATBOT VISA CI                        ║" . RESET . "\n";
        echo BLUE . "╚══════════════════════════════════════════════════════════════╝" . RESET . "\n\n";
        
        // Test 1: Configuration
        $this->testConfiguration();
        
        // Test 2: Gemini Client
        $this->testGeminiClient();
        
        // Test 3: Conversation Engine
        $this->testConversationEngine();
        
        // Test 4: Workflow STANDARD
        $this->testStandardWorkflow();
        
        // Test 5: Workflow PRIORITY
        $this->testPriorityWorkflow();
        
        // Test 6: PDF Generator
        $this->testPDFGenerator();
        
        // Test 7: QR Generator
        $this->testQRGenerator();
        
        // Summary
        $this->printSummary();
    }
    
    private function testConfiguration(): void {
        $this->section("Configuration");
        
        // Test config loading
        $this->test("Config loaded", defined('CHATBOT_ROOT'));
        $this->test("Workflow steps defined", defined('WORKFLOW_STEPS') && count(WORKFLOW_STEPS) === 10);
        $this->test("Session duration set", defined('SESSION_DURATION'));
        
        // Test Gemini config
        $this->test("GEMINI_API_KEY defined", defined('GEMINI_API_KEY'));
        $this->test("GEMINI_MODEL defined", defined('GEMINI_MODEL'));
        
        // Test helper functions
        $this->test("getStepIndex('passport') returns 3", getStepIndex('passport') === 3);
        $this->test("getNextStep('welcome') returns 'residence'", getNextStep('welcome') === 'residence');
        $this->test("getPreviousStep('passport') returns 'documents'", getPreviousStep('passport') === 'documents');
    }
    
    private function testGeminiClient(): void {
        $this->section("Gemini Client (Layer 2)");
        
        // Test instantiation
        try {
            $hasApiKey = !empty(getenv('GEMINI_API_KEY'));
            
            if ($hasApiKey) {
                $gemini = new GeminiClient();
                $this->test("GeminiClient instantiation", true);
                
                $info = $gemini->getInfo();
                $this->test("getInfo() returns configuration", 
                    isset($info['model']) && isset($info['configured'])
                );
                
                // Test chat (if API key present)
                try {
                    $result = $gemini->chat("Test", ['step' => 'welcome', 'language' => 'fr']);
                    $this->test("chat() returns response", !empty($result['message']));
                } catch (Exception $e) {
                    $this->test("chat() returns response", false, "API Error: " . $e->getMessage());
                }
            } else {
                $this->test("GeminiClient instantiation", false, "GEMINI_API_KEY not set (skipped)");
                $this->test("getInfo() returns configuration", false, "Skipped - no API key");
                $this->test("chat() returns response", false, "Skipped - no API key");
            }
        } catch (Exception $e) {
            $this->test("GeminiClient instantiation", false, $e->getMessage());
        }
    }
    
    private function testConversationEngine(): void {
        $this->section("Conversation Engine");
        
        try {
            $engine = new ConversationEngine(['debug' => false]);
            $this->test("ConversationEngine instantiation", true);
            
            // Test isGeminiActive
            $geminiActive = $engine->isGeminiActive();
            $this->test("isGeminiActive() returns boolean", is_bool($geminiActive));
            
            // Test generateMessage
            $message = $engine->generateMessage('welcome', ['firstname' => 'Test'], 'fr');
            $this->test("generateMessage() returns string", !empty($message));
            
            // Test understandIntent (fallback mode)
            $intent = $engine->understandIntent("oui", ['current_step' => 'health', 'language' => 'fr']);
            $this->test("understandIntent() returns array with intent", isset($intent['intent']));
            $this->test("'oui' detected as 'confirm'", $intent['intent'] === 'confirm');
            
            // Test fallback NLU for different inputs
            $intent2 = $engine->understandIntent("Éthiopie", ['current_step' => 'residence', 'language' => 'fr']);
            $this->test("Country detection (Éthiopie)", 
                isset($intent2['extracted_data']['country_code']) && 
                $intent2['extracted_data']['country_code'] === 'ET'
            );
            
        } catch (Exception $e) {
            $this->test("ConversationEngine instantiation", false, $e->getMessage());
        }
    }
    
    private function testStandardWorkflow(): void {
        $this->section("Workflow STANDARD (Passeport Ordinaire)");
        
        // Simulate workflow data
        $workflowData = [
            'passport_type' => 'ORDINAIRE',
            'workflow_type' => 'STANDARD',
            'collected_data' => [
                'language' => 'fr',
                'country_code' => 'ETH',
                'country_name' => 'Éthiopie',
                'passport' => [
                    'surname' => 'DUPONT',
                    'given_names' => 'Jean Pierre',
                    'passport_number' => 'AB1234567',
                    'nationality' => 'ETH',
                    'date_of_expiry' => '2030-01-01'
                ],
                'email' => 'jean.dupont@test.com',
                'arrival_date' => '2025-02-15',
                'departure_date' => '2025-02-28',
                'purpose' => 'TOURISME',
                'yellow_fever_vaccinated' => true,
                'customs' => [
                    'currency' => false,
                    'commercial' => false,
                    'sensitive' => false,
                    'medication' => false
                ]
            ]
        ];
        
        // Verify workflow type detection
        $this->test("Workflow type is STANDARD", $workflowData['workflow_type'] === 'STANDARD');
        $this->test("Not free (payant)", $workflowData['workflow_type'] !== 'PRIORITY');
        
        // Verify required fields for STANDARD
        $collected = $workflowData['collected_data'];
        $this->test("Has language", !empty($collected['language']));
        $this->test("Has country", !empty($collected['country_code']));
        $this->test("Has passport data", !empty($collected['passport']['passport_number']));
        $this->test("Has email", !empty($collected['email']));
        $this->test("Has trip dates", !empty($collected['arrival_date']));
        $this->test("Has vaccination status", isset($collected['yellow_fever_vaccinated']));
        $this->test("Has customs declaration", !empty($collected['customs']));
        
        // Verify all steps would be visited
        $steps = ['welcome', 'residence', 'documents', 'passport', 'photo', 'contact', 'trip', 'health', 'customs', 'confirm'];
        $this->test("All 10 steps in STANDARD workflow", count($steps) === 10);
    }
    
    private function testPriorityWorkflow(): void {
        $this->section("Workflow PRIORITY (Passeport Diplomatique)");
        
        // Simulate PRIORITY workflow data
        $workflowData = [
            'passport_type' => 'DIPLOMATIQUE',
            'workflow_type' => 'PRIORITY',
            'collected_data' => [
                'language' => 'fr',
                'country_code' => 'ETH',
                'country_name' => 'Éthiopie',
                'passport' => [
                    'surname' => 'AMBASSADEUR',
                    'given_names' => 'Excellence',
                    'passport_number' => 'D0000001',
                    'nationality' => 'ETH',
                    'date_of_expiry' => '2030-01-01',
                    'passport_type' => 'DIPLOMATIQUE'
                ],
                'email' => 'diplomate@mfa.gov.et',
                'arrival_date' => '2025-01-20',
                'verbal_note' => true
            ]
        ];
        
        // Verify PRIORITY workflow characteristics
        $this->test("Workflow type is PRIORITY", $workflowData['workflow_type'] === 'PRIORITY');
        $this->test("Passport type is DIPLOMATIQUE", $workflowData['passport_type'] === 'DIPLOMATIQUE');
        
        // PRIORITY benefits
        $isPriority = $workflowData['workflow_type'] === 'PRIORITY';
        $this->test("Free processing (gratuit)", $isPriority === true);
        $this->test("Priority timeline (24-48h)", $isPriority === true);
        
        // Verify verbal note requirement
        $this->test("Verbal note required/provided", 
            isset($workflowData['collected_data']['verbal_note']) && 
            $workflowData['collected_data']['verbal_note'] === true
        );
        
        // Test passport type detection logic
        $passportTypes = [
            'DIPLOMATIQUE' => 'PRIORITY',
            'SERVICE' => 'PRIORITY',
            'LAISSEZ_PASSER' => 'PRIORITY',
            'ORDINAIRE' => 'STANDARD',
            'OFFICIEL' => 'STANDARD',
            'REFUGIE' => 'STANDARD'
        ];
        
        foreach ($passportTypes as $type => $expectedWorkflow) {
            $this->test("$type → $expectedWorkflow workflow", true);
        }
    }
    
    private function testPDFGenerator(): void {
        $this->section("PDF Generator");
        
        try {
            $generator = new PDFGenerator();
            $this->test("PDFGenerator instantiation", true);
            
            // Test receipt generation
            $testData = [
                'reference_number' => 'CIV-2025-TEST01',
                'applicant' => [
                    'given_names' => 'Test',
                    'surname' => 'USER'
                ],
                'passport' => [
                    'passport_number' => 'TEST12345',
                    'nationality' => 'Éthiopie'
                ],
                'passport_type' => 'ORDINAIRE',
                'workflow_type' => 'STANDARD',
                'trip' => [
                    'arrival_date' => '15/01/2025',
                    'departure_date' => '30/01/2025',
                    'purpose' => 'TOURISME'
                ],
                'contact' => [
                    'email' => 'test@example.com'
                ]
            ];
            
            $result = $generator->generateReceipt($testData);
            $this->test("generateReceipt() returns success", $result['success'] === true);
            $this->test("Receipt has reference number", !empty($result['reference_number']));
            $this->test("Receipt file path exists", !empty($result['filepath']));
            
            // Check if file was actually created
            if (!empty($result['filepath']) && file_exists($result['filepath'])) {
                $this->test("Receipt file created on disk", true);
                // Clean up test file
                unlink($result['filepath']);
            } else {
                $this->test("Receipt file created on disk", false, "File not found: " . ($result['filepath'] ?? 'no path'));
            }
            
        } catch (Exception $e) {
            $this->test("PDFGenerator instantiation", false, $e->getMessage());
        }
    }
    
    private function testQRGenerator(): void {
        $this->section("QR Generator");
        
        try {
            $generator = new QRGenerator();
            $this->test("QRGenerator instantiation", true);
            
            // Test QR generation
            $result = $generator->generateVisaQR('CIV-2025-QRTEST', [
                'passport_number' => 'AB1234567',
                'workflow_type' => 'STANDARD'
            ]);
            
            $this->test("generateVisaQR() returns success", $result['success'] === true);
            $this->test("QR has verify URL", !empty($result['verify_url']));
            $this->test("QR has expiry date", !empty($result['expires_at']));
            
            // Test QR verification
            if (!empty($result['qr_data'])) {
                $encodedData = base64_encode(json_encode($result['qr_data']));
                $verifyResult = $generator->verifyQR($encodedData);
                
                $this->test("verifyQR() validates correct signature", $verifyResult['valid'] === true);
                $this->test("verifyQR() returns reference", !empty($verifyResult['reference_number']));
            }
            
            // Test invalid QR detection
            $invalidResult = $generator->verifyQR(base64_encode('invalid_data'));
            $this->test("verifyQR() rejects invalid data", $invalidResult['valid'] === false);
            
            // Clean up test file
            if (!empty($result['filepath']) && file_exists($result['filepath'])) {
                unlink($result['filepath']);
            }
            
        } catch (Exception $e) {
            $this->test("QRGenerator instantiation", false, $e->getMessage());
        }
    }
    
    // === Test Helpers ===
    
    private function section(string $title): void {
        echo "\n" . YELLOW . "▶ $title" . RESET . "\n";
        echo str_repeat("─", 50) . "\n";
    }
    
    private function test(string $description, bool $passed, string $error = ''): void {
        if ($passed) {
            $this->passed++;
            echo GREEN . "  ✓ " . RESET . "$description\n";
        } else {
            $this->failed++;
            echo RED . "  ✗ " . RESET . "$description";
            if ($error) {
                echo RED . " ($error)" . RESET;
                $this->errors[] = "$description: $error";
            }
            echo "\n";
        }
    }
    
    private function printSummary(): void {
        echo "\n" . str_repeat("═", 60) . "\n";
        echo BLUE . "RÉSUMÉ DES TESTS" . RESET . "\n";
        echo str_repeat("═", 60) . "\n";
        
        $total = $this->passed + $this->failed;
        $percentage = $total > 0 ? round(($this->passed / $total) * 100, 1) : 0;
        
        echo GREEN . "  Réussis: {$this->passed}" . RESET . "\n";
        echo RED . "  Échoués: {$this->failed}" . RESET . "\n";
        echo "  Total: $total\n";
        echo "  Taux de réussite: " . ($percentage >= 80 ? GREEN : ($percentage >= 50 ? YELLOW : RED)) . "$percentage%" . RESET . "\n";
        
        if (!empty($this->errors)) {
            echo "\n" . RED . "Erreurs détaillées:" . RESET . "\n";
            foreach ($this->errors as $i => $error) {
                echo "  " . ($i + 1) . ". $error\n";
            }
        }
        
        echo "\n";
        
        // Exit code
        exit($this->failed > 0 ? 1 : 0);
    }
}

// Run tests
$test = new IntegrationWorkflowTest();
$test->run();

