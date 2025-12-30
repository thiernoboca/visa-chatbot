<?php
/**
 * Test d'intégration complet du flux chatbot
 * Simule un utilisateur complet parcourant toutes les étapes
 * 
 * @package VisaChatbot
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__) . '/php/config.php';
require_once dirname(__DIR__) . '/php/document-extractor.php';
require_once dirname(__DIR__) . '/php/session-manager.php';

class IntegrationFlowTest {
    
    private array $results = [];
    private int $passed = 0;
    private int $failed = 0;
    
    public function runAll(): void {
        echo "\n=== TEST D'INTÉGRATION COMPLET ===\n\n";
        
        // Test 1: Détection type passeport ordinaire
        $this->testOrdinaryPassportFlow();
        
        // Test 2: Détection type passeport diplomatique
        $this->testDiplomaticPassportFlow();
        
        // Test 3: Matrice d'exigences complète
        $this->testRequirementsMatrix();
        
        // Test 4: Extraction documents multiples
        $this->testMultipleDocumentExtraction();
        
        // Test 5: Vérification complétude
        $this->testDocumentCompleteness();
        
        // Test 6: Workflow dynamique
        $this->testDynamicWorkflow();
        
        // Résumé
        $this->summary();
    }
    
    private function testOrdinaryPassportFlow(): void {
        echo "📋 Test 1: Flux Passeport Ordinaire\n";
        echo str_repeat("-", 50) . "\n";
        
        $passportData = [
            'fields' => [
                'surname' => ['value' => 'TEST', 'confidence' => 0.95],
                'given_names' => ['value' => 'USER', 'confidence' => 0.95],
                'passport_number' => ['value' => 'C12345678', 'confidence' => 0.99],
                'passport_type' => ['value' => 'ORDINAIRE', 'confidence' => 0.90]
            ]
        ];
        
        $detection = DocumentExtractor::detectPassportType($passportData);
        
        $this->assert(
            $detection['type'] === 'ORDINAIRE',
            "Type détecté: ORDINAIRE",
            "Type incorrect: {$detection['type']}"
        );
        
        $this->assert(
            $detection['workflow'] === 'STANDARD',
            "Workflow: STANDARD",
            "Workflow incorrect: {$detection['workflow']}"
        );
        
        $requirements = $detection['requirements'];
        $this->assert(
            $requirements['fees'] === true,
            "Frais: PAYANT",
            "Frais incorrects"
        );
        
        $this->assert(
            !in_array('verbal_note', $requirements['required']),
            "Note verbale: NON REQUISE",
            "Note verbale ne devrait pas être requise"
        );
        
        echo "\n";
    }
    
    private function testDiplomaticPassportFlow(): void {
        echo "📋 Test 2: Flux Passeport Diplomatique\n";
        echo str_repeat("-", 50) . "\n";
        
        $passportData = [
            'fields' => [
                'surname' => ['value' => 'AMBASSADOR', 'confidence' => 0.95],
                'given_names' => ['value' => 'DIPLOMATIC', 'confidence' => 0.95],
                'passport_number' => ['value' => 'D00123456', 'confidence' => 0.99],
                'passport_type' => ['value' => 'DIPLOMATIQUE', 'confidence' => 0.95]
            ],
            'mrz' => [
                'line1' => 'PD<KENAMBASSADOR<<MARIE<CLAIRE<<<<<<<<<<<<',
                'detected' => true
            ]
        ];
        
        $detection = DocumentExtractor::detectPassportType($passportData);
        
        $this->assert(
            $detection['type'] === 'DIPLOMATIQUE',
            "Type détecté: DIPLOMATIQUE",
            "Type incorrect: {$detection['type']}"
        );
        
        $this->assert(
            $detection['workflow'] === 'PRIORITY',
            "Workflow: PRIORITY",
            "Workflow incorrect: {$detection['workflow']}"
        );
        
        $requirements = $detection['requirements'];
        $this->assert(
            in_array('verbal_note', $requirements['required']),
            "Note verbale: REQUISE",
            "Note verbale devrait être requise"
        );
        
        $this->assert(
            $requirements['fees'] === false,
            "Frais: GRATUIT",
            "Devrait être gratuit"
        );
        
        echo "\n";
    }
    
    private function testRequirementsMatrix(): void {
        echo "📋 Test 3: Matrice d'Exigences\n";
        echo str_repeat("-", 50) . "\n";
        
        $types = ['ORDINAIRE', 'DIPLOMATIQUE', 'LP_ONU', 'SERVICE'];
        
        foreach ($types as $type) {
            $req = DocumentExtractor::getRequiredDocuments($type);
            
            $this->assert(
                isset($req['workflow']),
                "{$type}: Workflow défini",
                "{$type}: Workflow manquant"
            );
            
            $this->assert(
                in_array('passport', $req['required']),
                "{$type}: Passeport requis",
                "{$type}: Passeport devrait être requis"
            );
            
            $this->assert(
                in_array('ticket', $req['required']),
                "{$type}: Billet requis",
                "{$type}: Billet devrait être requis"
            );
        }
        
        echo "\n";
    }
    
    private function testMultipleDocumentExtraction(): void {
        echo "📋 Test 4: Extraction Documents Multiples\n";
        echo str_repeat("-", 50) . "\n";
        
        // Simuler des données OCR pour différents documents
        $testDocuments = [
            'ticket' => [
                'passenger_name' => 'TEST USER',
                'flight_number' => 'ET302',
                'departure_date' => '2025-06-01'
            ],
            'hotel' => [
                'hotel_name' => 'Test Hotel',
                'check_in_date' => '2025-06-01',
                'check_out_date' => '2025-06-05'
            ]
        ];
        
        // Vérifier que les types sont supportés
        foreach (array_keys($testDocuments) as $type) {
            $this->assert(
                DocumentExtractor::isTypeSupported($type),
                "Type {$type}: Supporté",
                "Type {$type}: Non supporté"
            );
        }
        
        echo "\n";
    }
    
    private function testDocumentCompleteness(): void {
        echo "📋 Test 5: Vérification Complétude\n";
        echo str_repeat("-", 50) . "\n";
        
        // Cas ordinaire complet
        $uploaded1 = [
            'passport' => ['success' => true],
            'ticket' => ['success' => true],
            'vaccination' => ['success' => true],
            'hotel' => ['success' => true]
        ];
        
        $complete1 = DocumentExtractor::checkDocumentCompleteness('ORDINAIRE', $uploaded1);
        $this->assert(
            $complete1['complete'] === true,
            "ORDINAIRE complet: OUI",
            "Devrait être complet"
        );
        
        // Cas ordinaire incomplet
        $uploaded2 = [
            'passport' => ['success' => true],
            'ticket' => ['success' => true]
        ];
        
        $complete2 = DocumentExtractor::checkDocumentCompleteness('ORDINAIRE', $uploaded2);
        $this->assert(
            $complete2['complete'] === false,
            "ORDINAIRE incomplet: OUI",
            "Devrait être incomplet"
        );
        
        // Cas diplomatique avec note verbale
        $uploaded3 = [
            'passport' => ['success' => true],
            'ticket' => ['success' => true],
            'verbal_note' => ['success' => true],
            'vaccination' => ['success' => true]
        ];
        
        $complete3 = DocumentExtractor::checkDocumentCompleteness('DIPLOMATIQUE', $uploaded3);
        $this->assert(
            $complete3['complete'] === true,
            "DIPLOMATIQUE complet: OUI",
            "Devrait être complet"
        );
        
        echo "\n";
    }
    
    private function testDynamicWorkflow(): void {
        echo "📋 Test 6: Workflow Dynamique\n";
        echo str_repeat("-", 50) . "\n";
        
        // Test résident hors nationalité
        $req1 = DocumentExtractor::getRequiredDocuments('ORDINAIRE', true);
        $this->assert(
            in_array('residence_card', $req1['required']),
            "Résident hors nationalité: Carte requise",
            "Carte de séjour devrait être requise"
        );
        
        // Test résident national
        $req2 = DocumentExtractor::getRequiredDocuments('ORDINAIRE', false);
        $this->assert(
            !in_array('residence_card', $req2['required']),
            "Résident national: Carte non requise",
            "Carte ne devrait pas être requise"
        );
        
        echo "\n";
    }
    
    private function assert(bool $condition, string $success, string $fail): void {
        if ($condition) {
            $this->passed++;
            echo "  ✅ {$success}\n";
        } else {
            $this->failed++;
            echo "  ❌ {$fail}\n";
        }
    }
    
    private function summary(): void {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "RÉSUMÉ\n";
        echo str_repeat("=", 50) . "\n";
        echo "✅ Réussis: {$this->passed}\n";
        echo "❌ Échoués: {$this->failed}\n";
        echo "📊 Total: " . ($this->passed + $this->failed) . "\n\n";
        
        if ($this->failed === 0) {
            echo "🎉 TOUS LES TESTS ONT RÉUSSI!\n";
        } else {
            echo "⚠️ CERTAINS TESTS ONT ÉCHOUÉ\n";
        }
    }
}

// Exécution
if (php_sapi_name() === 'cli') {
    $test = new IntegrationFlowTest();
    $test->runAll();
    exit(0);
}

