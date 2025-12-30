<?php
/**
 * Tests fonctionnels avancés - Chatbot Visa CI
 * Tests de parcours complets et edge cases
 * 
 * @package VisaChatbot
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('API_BASE', 'http://localhost:8888/hunyuanocr/visa-chatbot/php/chat-handler.php');

class AdvancedTester {
    
    private array $results = [];
    private int $passed = 0;
    private int $failed = 0;
    private array $improvements = [];
    
    private function callApi(string $method, array $params = [], ?string $sessionId = null): array {
        $url = API_BASE;
        
        if ($method === 'GET') {
            $url .= '?' . http_build_query($params);
            $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 10]]);
        } else {
            if ($sessionId) $params['session_id'] = $sessionId;
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n" . ($sessionId ? "X-Session-ID: $sessionId\r\n" : ''),
                    'content' => json_encode($params),
                    'timeout' => 10
                ]
            ]);
        }
        
        $response = @file_get_contents($url, false, $context);
        return $response === false 
            ? ['success' => false, 'error' => 'Connection failed']
            : (json_decode($response, true) ?? ['success' => false, 'error' => 'Invalid JSON']);
    }
    
    private function sendMessage(string $sessionId, string $message): array {
        return $this->callApi('POST', ['action' => 'message', 'message' => $message], $sessionId);
    }
    
    private function sendPassportOCR(string $sessionId, string $passportType, array $fields): array {
        $mrzFirstChar = match($passportType) {
            'DIPLOMATIQUE' => 'D', 'SERVICE' => 'S', 'LP_ONU' => 'U', 'LP_UA' => 'L', default => 'P'
        };
        
        return $this->callApi('POST', [
            'action' => 'passport_ocr',
            'ocr_data' => [
                'mrz' => [
                    'line1' => $mrzFirstChar . '<ETHTEST<<' . strtoupper($fields['given_names'] ?? 'JOHN') . '<<<<',
                    'line2' => ($fields['passport_number'] ?? 'AB1234567') . 'ETH9001015M2712312<<<<<'
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
    
    private function assert(string $testName, bool $condition, string $message = ''): void {
        if ($condition) {
            $this->passed++;
            echo "✅ PASS: $testName\n";
        } else {
            $this->failed++;
            echo "❌ FAIL: $testName - $message\n";
        }
        $this->results[] = ['test' => $testName, 'status' => $condition ? 'PASS' : 'FAIL', 'message' => $message];
    }
    
    private function suggest(string $improvement): void {
        $this->improvements[] = $improvement;
        echo "💡 AMÉLIORATION: $improvement\n";
    }
    
    /**
     * TEST 1: Parcours complet ORDINAIRE jusqu'à confirmation
     */
    public function testParcoursCompletOrdinaire(): void {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "🧪 TEST: Parcours complet ORDINAIRE\n";
        echo str_repeat('=', 60) . "\n";
        
        $init = $this->callApi('GET', ['action' => 'init']);
        $sessionId = $init['data']['session_id'] ?? null;
        $this->assert('Init', !empty($sessionId));
        if (!$sessionId) return;
        
        // Étape 1: Langue
        $this->sendMessage($sessionId, 'fr');
        
        // Étape 2: Résidence
        $this->sendMessage($sessionId, 'ETH');
        $this->sendMessage($sessionId, 'Addis Abeba');
        
        // Étape 3: Passeport
        $passport = $this->sendPassportOCR($sessionId, 'ORDINAIRE', [
            'surname' => 'COMPLET',
            'given_names' => 'TEST',
            'passport_number' => 'TEST123456'
        ]);
        $this->assert('Passeport détecté', $passport['success'] ?? false);
        
        // Confirmer le passeport
        $confirm = $this->sendMessage($sessionId, 'confirm');
        $this->assert('Confirm -> Photo', ($confirm['data']['step_info']['current'] ?? '') === 'photo');
        
        // Étape 4: Photo (simulation upload)
        $photo = $this->callApi('POST', [
            'action' => 'file_upload',
            'file_type' => 'photo',
            'file_path' => 'test-photo.jpg'
        ], $sessionId);
        $this->assert('Photo upload', $photo['success'] ?? false);
        $this->assert('Photo -> Contact', ($photo['data']['step_info']['current'] ?? '') === 'contact');
        
        // Étape 5: Contact
        $email = $this->sendMessage($sessionId, 'test@example.com');
        $this->assert('Email accepté', $email['success'] ?? false);
        
        $phone = $this->sendMessage($sessionId, '+251911234567');
        $this->assert('Phone accepté', $phone['success'] ?? false);
        
        $whatsapp = $this->sendMessage($sessionId, 'oui');
        $this->assert('WhatsApp -> Trip', ($whatsapp['data']['step_info']['current'] ?? '') === 'trip');
        
        // Étape 6: Voyage
        $arrival = $this->sendMessage($sessionId, '2025-03-01');
        $this->assert('Date arrivée', $arrival['success'] ?? false);
        
        $departure = $this->sendMessage($sessionId, '2025-03-15');
        $this->assert('Date départ', $departure['success'] ?? false);
        
        $purpose = $this->sendMessage($sessionId, 'TOURISME');
        $this->assert('Motif voyage', $purpose['success'] ?? false);
        
        $visaType = $this->sendMessage($sessionId, 'COURT_SEJOUR');
        $this->assert('Type visa', $visaType['success'] ?? false);
        
        $entries = $this->sendMessage($sessionId, 'Unique');
        $this->assert('Entrées visa', $entries['success'] ?? false);
        
        // Option express (nouveau)
        $expressContent = $entries['data']['bot_message']['content'] ?? '';
        if (strpos($expressContent, 'Express') !== false || strpos($expressContent, 'express') !== false) {
            $this->assert('Option express proposée', true);
            $express = $this->sendMessage($sessionId, 'standard');
            $this->assert('Express -> Accommodation', ($express['data']['step_info']['current'] ?? '') === 'trip' || strpos($express['data']['bot_message']['content'] ?? '', 'hébergé') !== false);
        }
        
        // Hébergement
        $accommodation = $this->sendMessage($sessionId, 'HOTEL');
        $this->assert('Hébergement -> Health', ($accommodation['data']['step_info']['current'] ?? '') === 'health');
        
        // Étape 7: Santé
        $vaccinated = $this->sendMessage($sessionId, 'oui');
        $this->assert('Vaccination oui', $vaccinated['success'] ?? false);
        
        $vaccinationCert = $this->callApi('POST', [
            'action' => 'file_upload',
            'file_type' => 'vaccination',
            'file_path' => 'vaccination.pdf'
        ], $sessionId);
        $this->assert('Vaccination cert -> Customs', ($vaccinationCert['data']['step_info']['current'] ?? '') === 'customs');
        
        // Étape 8: Douanes
        $customs = $this->sendMessage($sessionId, 'non');
        $this->assert('Customs -> Confirm', ($customs['data']['step_info']['current'] ?? '') === 'confirm');
        
        // Étape 9: Confirmation
        $confirmContent = $customs['data']['bot_message']['content'] ?? '';
        $this->assert('Récapitulatif affiché', strpos($confirmContent, 'COMPLET') !== false && strpos($confirmContent, 'TEST') !== false);
        $this->assert('Frais mentionnés', strpos($confirmContent, 'XOF') !== false);
        
        // Soumettre
        $submit = $this->sendMessage($sessionId, 'confirm');
        $this->assert('Soumission réussie', ($submit['data']['metadata']['completed'] ?? false) === true);
        $this->assert('Numéro demande généré', !empty($submit['data']['metadata']['application_number'] ?? null));
        
        // Vérifier le statut
        $this->assert('Session terminée', ($submit['data']['status'] ?? '') === 'completed');
    }
    
    /**
     * TEST 2: Parcours DIPLOMATIQUE (raccourci)
     */
    public function testParcoursDiplomatique(): void {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "🧪 TEST: Parcours DIPLOMATIQUE (simplifié)\n";
        echo str_repeat('=', 60) . "\n";
        
        $init = $this->callApi('GET', ['action' => 'init']);
        $sessionId = $init['data']['session_id'] ?? null;
        if (!$sessionId) return;
        
        $this->sendMessage($sessionId, 'en');
        $this->sendMessage($sessionId, 'DJI');
        $this->sendMessage($sessionId, 'Djibouti City');
        
        $passport = $this->sendPassportOCR($sessionId, 'DIPLOMATIQUE', [
            'surname' => 'AMBASSADOR',
            'given_names' => 'JANE',
            'passport_number' => 'DIPLO123'
        ]);
        
        // Vérifications DIPLOMATIQUE
        $content = $passport['data']['bot_message']['content'] ?? '';
        $this->assert('DIPLO: FREE mentioned', 
            strpos($content, 'FREE') !== false || strpos($content, 'GRATUIT') !== false);
        $this->assert('DIPLO: Verbal note mentioned', 
            strpos($content, 'verbal') !== false || strpos($content, 'Verbal') !== false);
        $this->assert('DIPLO: No express option',
            strpos($content, 'Express') === false); // Express ne devrait pas apparaître
        
        // Les diplomates ne paient pas - pas d'étape de paiement
        $confirm = $this->sendMessage($sessionId, 'confirm');
        $this->assert('DIPLO: Photo step', ($confirm['data']['step_info']['current'] ?? '') === 'photo');
    }
    
    /**
     * TEST 3: Validation email
     */
    public function testValidationEmail(): void {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "🧪 TEST: Validation email\n";
        echo str_repeat('=', 60) . "\n";
        
        $init = $this->callApi('GET', ['action' => 'init']);
        $sessionId = $init['data']['session_id'] ?? null;
        if (!$sessionId) return;
        
        // Aller jusqu'à l'étape contact
        $this->sendMessage($sessionId, 'fr');
        $this->sendMessage($sessionId, 'SOM');
        $this->sendMessage($sessionId, 'Mogadishu');
        $this->sendPassportOCR($sessionId, 'ORDINAIRE', ['surname' => 'EMAIL', 'given_names' => 'TEST']);
        $this->sendMessage($sessionId, 'confirm');
        $this->callApi('POST', ['action' => 'file_upload', 'file_type' => 'photo', 'file_path' => 'test.jpg'], $sessionId);
        
        // Tester email invalide
        $invalidEmail = $this->sendMessage($sessionId, 'invalid-email');
        $content = $invalidEmail['data']['bot_message']['content'] ?? '';
        $this->assert('Email invalide rejeté', 
            strpos($content, 'invalide') !== false || strpos($content, 'invalid') !== false);
        
        // Tester email valide
        $validEmail = $this->sendMessage($sessionId, 'valid@test.com');
        $this->assert('Email valide accepté', 
            strpos($validEmail['data']['bot_message']['content'] ?? '', 'téléphone') !== false ||
            strpos($validEmail['data']['bot_message']['content'] ?? '', 'phone') !== false);
    }
    
    /**
     * TEST 4: Vaccination obligatoire bloquante
     */
    public function testVaccinationBloquante(): void {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "🧪 TEST: Vaccination obligatoire (blocage)\n";
        echo str_repeat('=', 60) . "\n";
        
        $init = $this->callApi('GET', ['action' => 'init']);
        $sessionId = $init['data']['session_id'] ?? null;
        if (!$sessionId) return;
        
        // Aller jusqu'à l'étape santé
        $this->sendMessage($sessionId, 'fr');
        $this->sendMessage($sessionId, 'SSD'); // Soudan du Sud
        $this->sendMessage($sessionId, 'Juba');
        $this->sendPassportOCR($sessionId, 'ORDINAIRE', ['surname' => 'VACC', 'given_names' => 'TEST']);
        $this->sendMessage($sessionId, 'confirm');
        $this->callApi('POST', ['action' => 'file_upload', 'file_type' => 'photo', 'file_path' => 'test.jpg'], $sessionId);
        $this->sendMessage($sessionId, 'test@vacc.com');
        $this->sendMessage($sessionId, '+211912345678');
        $this->sendMessage($sessionId, 'oui');
        $this->sendMessage($sessionId, '2025-04-01');
        $this->sendMessage($sessionId, '2025-04-10');
        $this->sendMessage($sessionId, 'AFFAIRES');
        $this->sendMessage($sessionId, 'COURT_SEJOUR');
        $this->sendMessage($sessionId, 'Unique');
        $this->sendMessage($sessionId, 'standard');
        $this->sendMessage($sessionId, 'HOTEL');
        
        // Maintenant à l'étape santé, dire non à la vaccination
        $noVacc = $this->sendMessage($sessionId, 'non');
        $content = $noVacc['data']['bot_message']['content'] ?? '';
        $this->assert('Avertissement vaccination', 
            strpos($content, 'vaccination') !== false || strpos($content, 'Vaccination') !== false);
        $this->assert('Blocage entrée mentionné', 
            strpos($content, 'ne pourrez pas entrer') !== false || 
            strpos($content, 'cannot enter') !== false ||
            strpos($content, 'recommande') !== false);
        
        // Refuser de continuer
        $refuse = $this->sendMessage($sessionId, 'non');
        $this->assert('Session bloquée', ($refuse['data']['status'] ?? '') === 'blocked');
        $this->assert('Message de blocage', 
            strpos($refuse['data']['bot_message']['content'] ?? '', 'bientôt') !== false ||
            strpos($refuse['data']['bot_message']['content'] ?? '', 'soon') !== false);
    }
    
    /**
     * TEST 5: Calcul des frais selon options
     */
    public function testCalculFrais(): void {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "🧪 TEST: Calcul dynamique des frais\n";
        echo str_repeat('=', 60) . "\n";
        
        require_once __DIR__ . '/../php/data/passport-types.php';
        
        // Test frais ORDINAIRE standard
        $fees1 = calculateFees('ORDINAIRE', 'COURT_SEJOUR', 'Unique', false);
        $this->assert('Frais base ORDINAIRE', $fees1['baseFee'] === 73000);
        $this->assert('Pas de frais express', $fees1['expressFee'] === 0);
        $this->assert('Total correct', $fees1['total'] === 73000);
        
        // Test avec express
        $fees2 = calculateFees('ORDINAIRE', 'COURT_SEJOUR', 'Unique', true);
        $this->assert('Frais express ajoutés', $fees2['expressFee'] === 50000);
        $this->assert('Total avec express', $fees2['total'] === 123000);
        
        // Test entrées multiples (120000 XOF selon tarification officielle)
        $fees3 = calculateFees('ORDINAIRE', 'COURT_SEJOUR', 'Multiple', false);
        $this->assert('Frais multiples', $fees3['total'] === 120000); // Tarif officiel entrées multiples
        
        // Test DIPLOMATIQUE (gratuit)
        $fees4 = calculateFees('DIPLOMATIQUE', 'COURT_SEJOUR', 'Multiple', true);
        $this->assert('Diplomatique gratuit', $fees4['total'] === 0);
        $this->assert('Diplomatique isFree', $fees4['isFree'] === true);
        
        // Test LP_ONU (gratuit)
        $fees5 = calculateFees('LP_ONU', 'COURT_SEJOUR', 'Unique', false);
        $this->assert('LP_ONU gratuit', $fees5['total'] === 0);
        
        // Test TRANSIT (moins cher)
        $fees6 = calculateFees('ORDINAIRE', 'TRANSIT', 'Unique', false);
        $this->assert('Transit moins cher', $fees6['baseFee'] < $fees1['baseFee']);
    }
    
    /**
     * TEST 6: Règles d'affichage conditionnel
     */
    public function testDisplayRules(): void {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "🧪 TEST: Règles d'affichage conditionnel\n";
        echo str_repeat('=', 60) . "\n";
        
        require_once __DIR__ . '/../php/data/workflow-rules.php';
        
        // Test contexte ORDINAIRE
        $contextOrdinaire = [
            'passport_type' => 'ORDINAIRE',
            'workflow_category' => 'ORDINAIRE',
            'is_express' => false,
            'lang' => 'fr'
        ];
        
        $rules1 = evaluateDisplayRules($contextOrdinaire);
        $this->assert('Express visible pour ORDINAIRE', isset($rules1['is_express']) && $rules1['is_express']['action'] === 'SHOW');
        $this->assert('Payment visible pour ORDINAIRE', !isset($rules1['payment_method']) || $rules1['payment_method']['action'] !== 'HIDE');
        
        // Test contexte DIPLOMATIQUE
        $contextDiplo = [
            'passport_type' => 'DIPLOMATIQUE',
            'workflow_category' => 'DIPLOMATIQUE',
            'lang' => 'fr'
        ];
        
        $rules2 = evaluateDisplayRules($contextDiplo);
        $this->assert('Express masqué pour DIPLO', isset($rules2['is_express']) && $rules2['is_express']['action'] === 'HIDE');
        $this->assert('Verbal note required pour DIPLO', 
            isset($rules2['verbal_note']) && 
            in_array($rules2['verbal_note']['action'], ['SHOW', 'REQUIRED'])); // Peut être SHOW ou REQUIRED
        
        // Test contexte hébergement
        $contextHotel = ['accommodation_type' => 'HOTEL', 'lang' => 'fr'];
        $rules3 = evaluateDisplayRules($contextHotel);
        $this->assert('Réservation hôtel visible', isset($rules3['hotel_reservation']) && $rules3['hotel_reservation']['action'] === 'SHOW');
        
        $contextParticulier = ['accommodation_type' => 'PARTICULIER', 'lang' => 'fr'];
        $rules4 = evaluateDisplayRules($contextParticulier);
        $this->assert('Lettre invitation visible', isset($rules4['personal_invitation_letter']) && $rules4['personal_invitation_letter']['action'] === 'SHOW');
    }
    
    /**
     * Suggestions d'améliorations
     */
    public function analyzeImprovements(): void {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "💡 ANALYSE DES AMÉLIORATIONS POSSIBLES\n";
        echo str_repeat('=', 60) . "\n";
        
        $this->suggest("Validation téléphone avec regex internationaux (+XXX...XXX)");
        $this->suggest("Validation dates: arrivée min J+7 pour visa standard, J+2 pour express");
        $this->suggest("Vérification passeport: expiration 6 mois après date de départ");
        $this->suggest("Ajout de tests d'intégration pour les API Google Vision et Claude");
        $this->suggest("Cache des sessions en mémoire (Redis/APCu) pour meilleures performances");
        $this->suggest("Rate limiting par IP pour éviter les abus");
        $this->suggest("Logs structurés pour monitoring en production");
        $this->suggest("Webhooks pour notifications temps réel de changement de statut");
        $this->suggest("Support multi-devises (USD, EUR en plus de XOF)");
        $this->suggest("Paiement en ligne intégré (Stripe/PayPal/Mobile Money)");
    }
    
    public function printSummary(): void {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "📊 RÉSUMÉ DES TESTS AVANCÉS\n";
        echo str_repeat('=', 60) . "\n";
        echo "✅ Passés: {$this->passed}\n";
        echo "❌ Échoués: {$this->failed}\n";
        echo "Total: " . ($this->passed + $this->failed) . "\n";
        
        $rate = $this->passed + $this->failed > 0 
            ? round(($this->passed / ($this->passed + $this->failed)) * 100, 1) : 0;
        echo "Taux de réussite: {$rate}%\n";
        
        if ($this->failed > 0) {
            echo "\n❌ Tests échoués:\n";
            foreach ($this->results as $r) {
                if ($r['status'] === 'FAIL') echo "  - {$r['test']}: {$r['message']}\n";
            }
        }
        
        if (!empty($this->improvements)) {
            echo "\n💡 Améliorations suggérées: " . count($this->improvements) . "\n";
        }
    }
    
    public function runAll(): void {
        echo "\n🚀 DÉMARRAGE DES TESTS AVANCÉS\n";
        echo "📅 " . date('Y-m-d H:i:s') . "\n";
        
        $this->testParcoursCompletOrdinaire();
        $this->testParcoursDiplomatique();
        $this->testValidationEmail();
        $this->testVaccinationBloquante();
        $this->testCalculFrais();
        $this->testDisplayRules();
        $this->analyzeImprovements();
        $this->printSummary();
    }
}

$tester = new AdvancedTester();
$tester->runAll();

