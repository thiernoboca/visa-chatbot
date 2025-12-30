<?php
/**
 * Tests d'intégration - Scénarios par type de passeport
 * 
 * Ce script teste les 3 scénarios principaux selon la matrice d'exigences:
 * 1. Passeport ORDINAIRE → Workflow STANDARD
 * 2. Passeport DIPLOMATIQUE → Workflow PRIORITY
 * 3. Laissez-Passer ONU → Workflow PRIORITY
 * 
 * Usage: php passport-type-scenarios-test.php [--verbose]
 * 
 * @package VisaChatbot
 * @version 1.0.0
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Options CLI
$verbose = in_array('--verbose', $argv) || in_array('-v', $argv);

// Charger les dépendances
require_once dirname(__DIR__) . '/php/config.php';
require_once dirname(__DIR__) . '/php/document-extractor.php';

/**
 * Classe de test
 */
class PassportTypeScenarioTest {
    
    private bool $verbose;
    private int $passed = 0;
    private int $failed = 0;
    private array $results = [];
    
    public function __construct(bool $verbose = false) {
        $this->verbose = $verbose;
    }
    
    /**
     * Exécute tous les tests
     */
    public function runAll(): array {
        $this->header("Tests des Scénarios par Type de Passeport");
        
        // Test 1: Passeport Ordinaire
        $this->testOrdinaryPassportScenario();
        
        // Test 2: Passeport Diplomatique
        $this->testDiplomaticPassportScenario();
        
        // Test 3: Laissez-Passer ONU
        $this->testUNLaissezPasserScenario();
        
        // Test 4: Détection automatique du type
        $this->testPassportTypeDetection();
        
        // Test 5: Vérification de la complétude des documents
        $this->testDocumentCompleteness();
        
        // Test 6: Matrice des exigences
        $this->testRequirementsMatrix();
        
        // Résumé
        $this->summary();
        
        return [
            'passed' => $this->passed,
            'failed' => $this->failed,
            'total' => $this->passed + $this->failed,
            'results' => $this->results
        ];
    }
    
    // ==================== SCÉNARIO 1: PASSEPORT ORDINAIRE ====================
    
    private function testOrdinaryPassportScenario(): void {
        $this->section("Scénario 1: Passeport ORDINAIRE");
        
        // Simuler les données d'un passeport ordinaire
        $passportData = [
            'fields' => [
                'surname' => ['value' => 'KONAN', 'confidence' => 0.95],
                'given_names' => ['value' => 'JEAN MARC', 'confidence' => 0.95],
                'passport_number' => ['value' => 'C12345678', 'confidence' => 0.99],
                'nationality' => ['value' => 'ETH', 'confidence' => 0.95],
                'date_of_birth' => ['value' => '1985-06-15', 'confidence' => 0.92],
                'date_of_expiry' => ['value' => '2028-06-14', 'confidence' => 0.95],
                'passport_type' => ['value' => 'ORDINAIRE', 'confidence' => 0.90]
            ],
            'mrz' => [
                'line1' => 'P<ETHKONAN<<JEAN<MARC<<<<<<<<<<<<<<<<<<<<<',
                'line2' => 'C123456788ETH8506154M2806149<<<<<<<<<<<<<<8',
                'detected' => true
            ]
        ];
        
        // Test 1.1: Détection du type
        $detection = DocumentExtractor::detectPassportType($passportData);
        $this->assert(
            $detection['type'] === 'ORDINAIRE',
            "Type détecté: ORDINAIRE",
            "Type détecté: {$detection['type']} (attendu: ORDINAIRE)"
        );
        
        // Test 1.2: Workflow assigné
        $this->assert(
            $detection['workflow'] === 'STANDARD',
            "Workflow: STANDARD",
            "Workflow: {$detection['workflow']} (attendu: STANDARD)"
        );
        
        // Test 1.3: Documents requis
        $requirements = $detection['requirements'];
        $this->assert(
            in_array('ticket', $requirements['required']),
            "Billet requis: OUI",
            "Billet requis manquant"
        );
        
        $this->assert(
            in_array('vaccination', $requirements['required']),
            "Vaccination requise: OUI",
            "Vaccination requise manquante"
        );
        
        $this->assert(
            !empty($requirements['conditional']),
            "Documents conditionnels (hotel/invitation): OUI",
            "Documents conditionnels manquants"
        );
        
        // Test 1.4: Frais
        $this->assert(
            $requirements['fees'] === true,
            "Frais de visa: PAYANT",
            "Frais incorrects"
        );
        
        // Test 1.5: Note verbale non requise
        $this->assert(
            $requirements['verbal_note'] === false,
            "Note verbale: NON REQUISE",
            "Note verbale ne devrait pas être requise"
        );
        
        $this->log("✓ Scénario ORDINAIRE validé");
    }
    
    // ==================== SCÉNARIO 2: PASSEPORT DIPLOMATIQUE ====================
    
    private function testDiplomaticPassportScenario(): void {
        $this->section("Scénario 2: Passeport DIPLOMATIQUE");
        
        // Simuler les données d'un passeport diplomatique
        $passportData = [
            'fields' => [
                'surname' => ['value' => 'AMBASSADOR', 'confidence' => 0.95],
                'given_names' => ['value' => 'MARIE CLAIRE', 'confidence' => 0.95],
                'passport_number' => ['value' => 'D00123456', 'confidence' => 0.99],
                'nationality' => ['value' => 'KEN', 'confidence' => 0.95],
                'date_of_birth' => ['value' => '1970-03-20', 'confidence' => 0.92],
                'date_of_expiry' => ['value' => '2027-03-19', 'confidence' => 0.95],
                'passport_type' => ['value' => 'DIPLOMATIQUE', 'confidence' => 0.95]
            ],
            'mrz' => [
                'line1' => 'PD<KENAMBASSADOR<<MARIE<CLAIRE<<<<<<<<<<<<',
                'line2' => 'D001234567KEN7003204F2703199<<<<<<<<<<<<<<2',
                'detected' => true
            ]
        ];
        
        // Test 2.1: Détection du type
        $detection = DocumentExtractor::detectPassportType($passportData);
        $this->assert(
            $detection['type'] === 'DIPLOMATIQUE',
            "Type détecté: DIPLOMATIQUE",
            "Type détecté: {$detection['type']} (attendu: DIPLOMATIQUE)"
        );
        
        // Test 2.2: Workflow assigné
        $this->assert(
            $detection['workflow'] === 'PRIORITY',
            "Workflow: PRIORITY",
            "Workflow: {$detection['workflow']} (attendu: PRIORITY)"
        );
        
        // Test 2.3: Note verbale requise
        $requirements = $detection['requirements'];
        $this->assert(
            in_array('verbal_note', $requirements['required']),
            "Note verbale: REQUISE",
            "Note verbale devrait être requise"
        );
        
        // Test 2.4: Pas de documents conditionnels (hotel/invitation)
        $this->assert(
            empty($requirements['conditional']),
            "Hébergement: NON REQUIS (diplomatique)",
            "Hébergement ne devrait pas être requis pour diplomatique"
        );
        
        // Test 2.5: Gratuit
        $this->assert(
            $requirements['fees'] === false,
            "Frais de visa: GRATUIT",
            "Devrait être gratuit pour diplomatique"
        );
        
        // Test 2.6: Délai prioritaire
        $this->assert(
            $requirements['processing_days'] === '24-48h',
            "Délai: 24-48h",
            "Délai: {$requirements['processing_days']} (attendu: 24-48h)"
        );
        
        $this->log("✓ Scénario DIPLOMATIQUE validé");
    }
    
    // ==================== SCÉNARIO 3: LAISSEZ-PASSER ONU ====================
    
    private function testUNLaissezPasserScenario(): void {
        $this->section("Scénario 3: Laissez-Passer ONU");
        
        // Simuler les données d'un LP ONU
        $passportData = [
            'fields' => [
                'surname' => ['value' => 'UNITED', 'confidence' => 0.95],
                'given_names' => ['value' => 'NATIONS OFFICER', 'confidence' => 0.95],
                'passport_number' => ['value' => 'UN00987654', 'confidence' => 0.99],
                'nationality' => ['value' => 'UNO', 'confidence' => 0.95],
                'date_of_birth' => ['value' => '1982-11-08', 'confidence' => 0.92],
                'date_of_expiry' => ['value' => '2026-11-07', 'confidence' => 0.95],
                'passport_type' => ['value' => 'LAISSEZ-PASSER ONU', 'confidence' => 0.90]
            ],
            'mrz' => null // LP ONU peut ne pas avoir de MRZ standard
        ];
        
        // Test 3.1: Détection du type
        $detection = DocumentExtractor::detectPassportType($passportData);
        $this->assert(
            $detection['type'] === 'LP_ONU',
            "Type détecté: LP_ONU",
            "Type détecté: {$detection['type']} (attendu: LP_ONU)"
        );
        
        // Test 3.2: Workflow assigné
        $this->assert(
            $detection['workflow'] === 'PRIORITY',
            "Workflow: PRIORITY",
            "Workflow: {$detection['workflow']} (attendu: PRIORITY)"
        );
        
        // Test 3.3: Note verbale requise
        $requirements = $detection['requirements'];
        $this->assert(
            in_array('verbal_note', $requirements['required']),
            "Note verbale: REQUISE",
            "Note verbale devrait être requise pour LP ONU"
        );
        
        // Test 3.4: Vaccination optionnelle
        $this->assert(
            in_array('vaccination', $requirements['optional']),
            "Vaccination: OPTIONNELLE",
            "Vaccination devrait être optionnelle pour LP ONU"
        );
        
        // Test 3.5: Gratuit
        $this->assert(
            $requirements['fees'] === false,
            "Frais de visa: GRATUIT",
            "Devrait être gratuit pour LP ONU"
        );
        
        $this->log("✓ Scénario LP_ONU validé");
    }
    
    // ==================== TESTS COMPLÉMENTAIRES ====================
    
    private function testPassportTypeDetection(): void {
        $this->section("Test: Détection automatique des types");
        
        $testCases = [
            ['value' => 'ORDINARY', 'expected' => 'ORDINAIRE'],
            ['value' => 'DIPLOMATIC', 'expected' => 'DIPLOMATIQUE'],
            ['value' => 'SERVICE', 'expected' => 'SERVICE'],
            ['value' => 'UN LAISSEZ-PASSER', 'expected' => 'LP_ONU'],
            ['value' => 'AFRICAN UNION', 'expected' => 'LP_UA'],
            ['value' => 'OFFICIAL', 'expected' => 'OFFICIEL'],
            ['value' => 'TRAVEL DOCUMENT', 'expected' => 'EMERGENCY'],
        ];
        
        foreach ($testCases as $case) {
            $data = [
                'fields' => [
                    'passport_type' => ['value' => $case['value'], 'confidence' => 0.90]
                ]
            ];
            
            $detection = DocumentExtractor::detectPassportType($data);
            $this->assert(
                $detection['type'] === $case['expected'],
                "'{$case['value']}' → {$case['expected']}",
                "'{$case['value']}' → {$detection['type']} (attendu: {$case['expected']})"
            );
        }
    }
    
    private function testDocumentCompleteness(): void {
        $this->section("Test: Vérification complétude documents");
        
        // Cas 1: Passeport ordinaire avec tous les documents
        $uploadedDocs = [
            'passport' => ['success' => true],
            'ticket' => ['success' => true],
            'vaccination' => ['success' => true],
            'hotel' => ['success' => true]
        ];
        
        $completeness = DocumentExtractor::checkDocumentCompleteness('ORDINAIRE', $uploadedDocs);
        $this->assert(
            $completeness['complete'] === true,
            "ORDINAIRE complet avec hotel: OUI",
            "Devrait être complet"
        );
        
        // Cas 2: Passeport ordinaire sans hébergement
        $uploadedDocs2 = [
            'passport' => ['success' => true],
            'ticket' => ['success' => true],
            'vaccination' => ['success' => true]
        ];
        
        $completeness2 = DocumentExtractor::checkDocumentCompleteness('ORDINAIRE', $uploadedDocs2);
        $this->assert(
            $completeness2['complete'] === false,
            "ORDINAIRE sans hébergement: INCOMPLET",
            "Devrait être incomplet sans hotel/invitation"
        );
        
        // Cas 3: Diplomatique avec note verbale
        $uploadedDocs3 = [
            'passport' => ['success' => true],
            'ticket' => ['success' => true],
            'verbal_note' => ['success' => true],
            'vaccination' => ['success' => true]
        ];
        
        $completeness3 = DocumentExtractor::checkDocumentCompleteness('DIPLOMATIQUE', $uploadedDocs3);
        $this->assert(
            $completeness3['complete'] === true,
            "DIPLOMATIQUE avec note verbale: COMPLET",
            "Devrait être complet"
        );
        
        // Cas 4: Diplomatique sans note verbale
        $uploadedDocs4 = [
            'passport' => ['success' => true],
            'ticket' => ['success' => true],
            'vaccination' => ['success' => true]
        ];
        
        $completeness4 = DocumentExtractor::checkDocumentCompleteness('DIPLOMATIQUE', $uploadedDocs4);
        $this->assert(
            $completeness4['complete'] === false,
            "DIPLOMATIQUE sans note verbale: INCOMPLET",
            "Devrait être incomplet sans note verbale"
        );
        
        $this->assert(
            in_array('verbal_note', $completeness4['missing']),
            "Note verbale dans manquants: OUI",
            "Note verbale devrait être dans les manquants"
        );
    }
    
    private function testRequirementsMatrix(): void {
        $this->section("Test: Matrice d'exigences complète");
        
        $allTypes = ['ORDINAIRE', 'OFFICIEL', 'DIPLOMATIQUE', 'SERVICE', 'LP_ONU', 'LP_UA', 'EMERGENCY'];
        
        foreach ($allTypes as $type) {
            $requirements = DocumentExtractor::getRequiredDocuments($type);
            
            $this->assert(
                isset($requirements['workflow']),
                "{$type}: workflow défini",
                "{$type}: workflow manquant"
            );
            
            $this->assert(
                isset($requirements['required']) && is_array($requirements['required']),
                "{$type}: documents requis définis",
                "{$type}: documents requis manquants"
            );
            
            $this->assert(
                in_array('passport', $requirements['required']),
                "{$type}: passeport toujours requis",
                "{$type}: passeport devrait être requis"
            );
            
            if ($this->verbose) {
                $this->log("  {$type}:");
                $this->log("    - Workflow: {$requirements['workflow']}");
                $this->log("    - Requis: " . implode(', ', $requirements['required']));
                $this->log("    - Frais: " . ($requirements['fees'] ? 'OUI' : 'NON'));
            }
        }
    }
    
    // ==================== HELPERS ====================
    
    private function assert(bool $condition, string $successMsg, string $failMsg): void {
        if ($condition) {
            $this->passed++;
            $this->results[] = ['status' => 'passed', 'message' => $successMsg];
            if ($this->verbose) {
                echo "  ✅ {$successMsg}\n";
            }
        } else {
            $this->failed++;
            $this->results[] = ['status' => 'failed', 'message' => $failMsg];
            echo "  ❌ {$failMsg}\n";
        }
    }
    
    private function header(string $title): void {
        echo "\n";
        echo str_repeat("=", 60) . "\n";
        echo " {$title}\n";
        echo str_repeat("=", 60) . "\n\n";
    }
    
    private function section(string $title): void {
        echo "\n📋 {$title}\n";
        echo str_repeat("-", 50) . "\n";
    }
    
    private function log(string $message): void {
        echo "{$message}\n";
    }
    
    private function summary(): void {
        echo "\n";
        echo str_repeat("=", 60) . "\n";
        echo " RÉSUMÉ DES TESTS\n";
        echo str_repeat("=", 60) . "\n";
        echo "\n";
        echo "  ✅ Réussis: {$this->passed}\n";
        echo "  ❌ Échoués: {$this->failed}\n";
        echo "  📊 Total: " . ($this->passed + $this->failed) . "\n";
        echo "\n";
        
        if ($this->failed === 0) {
            echo "🎉 TOUS LES TESTS ONT RÉUSSI!\n";
        } else {
            echo "⚠️ CERTAINS TESTS ONT ÉCHOUÉ\n";
        }
    }
}

// Exécution
if (php_sapi_name() === 'cli') {
    $test = new PassportTypeScenarioTest($verbose);
    $results = $test->runAll();
    
    // Code de sortie
    exit($results['failed'] > 0 ? 1 : 0);
}

