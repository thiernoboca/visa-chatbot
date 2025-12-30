<?php
/**
 * Tests unitaires - Moteur Conversationnel Aya
 * Vérifie le persona, les messages enrichis, le NLU et les suggestions proactives
 * 
 * @package VisaChatbot
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../php/config.php';
require_once __DIR__ . '/../php/conversation-engine.php';
require_once __DIR__ . '/../php/proactive-suggestions.php';
require_once __DIR__ . '/../php/empathic-errors.php';
require_once __DIR__ . '/../php/data/chat-messages.php';

$passed = 0;
$failed = 0;

function test($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "✅ PASS: $name\n";
        $passed++;
    } else {
        echo "❌ FAIL: $name\n";
        $failed++;
    }
}

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║     TESTS - MOTEUR CONVERSATIONNEL AYA                       ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// ===== 1. PERSONA AYA =====
echo "━━━ 1. PERSONA AYA ━━━\n";

$engine = new ConversationEngine(['debug' => false]);

test("Persona name is Aya", ConversationEngine::PERSONA['name'] === 'Aya');
test("Persona has cultural expressions", isset(ConversationEngine::PERSONA['cultural_expressions']));
test("Akwaba in greetings", in_array('Akwaba !', ConversationEngine::PERSONA['cultural_expressions']['greeting']));
test("Persona has emoji signature", ConversationEngine::PERSONA['emoji_signature'] === '🇨🇮');

// ===== 2. MESSAGES ENRICHIS =====
echo "\n━━━ 2. MESSAGES ENRICHIS AVEC AYA ━━━\n";

$welcomeMsg = getMessage('welcome', 'fr');
test("Welcome message contains Aya", str_contains($welcomeMsg, 'Aya'));
test("Welcome message contains Akwaba", str_contains($welcomeMsg, 'Akwaba'));
test("Welcome message has emoji", str_contains($welcomeMsg, '👋'));

$passportMsg = getMessage('passport_scan_request', 'fr');
test("Passport message is friendly", str_contains($passportMsg, '✨') || str_contains($passportMsg, '📸'));

$successMsg = getMessage('submission_success', 'fr');
test("Success message has celebration", str_contains($successMsg, '🎉'));
test("Success message signed by Aya", str_contains($successMsg, 'Aya'));

// ===== 3. PROACTIVE SUGGESTIONS =====
echo "\n━━━ 3. SUGGESTIONS PROACTIVES ━━━\n";

$suggestions = new ProactiveSuggestions([]);

// Test quick actions
$welcomeActions = $suggestions->getQuickActions('welcome', 'fr');
test("Welcome has language actions", count($welcomeActions) >= 2);
test("Actions include Français", 
    count(array_filter($welcomeActions, fn($a) => str_contains($a['label'], 'Français'))) > 0
);

$residenceActions = $suggestions->getQuickActions('residence', 'fr');
test("Residence has country actions", count($residenceActions) >= 5);
test("Actions include Éthiopie",
    count(array_filter($residenceActions, fn($a) => str_contains($a['label'], 'Éthiopie'))) > 0
);

$tripActions = $suggestions->getQuickActions('trip_purpose', 'fr');
test("Trip has purpose actions", count($tripActions) >= 4);

// Test progress encouragement
$encourage25 = $suggestions->getProgressEncouragement(0.25, 'fr');
test("25% progress encouragement", $encourage25 !== null && str_contains($encourage25, '🚀'));

$encourage50 = $suggestions->getProgressEncouragement(0.50, 'fr');
test("50% progress encouragement", $encourage50 !== null);

$encourage75 = $suggestions->getProgressEncouragement(0.75, 'fr');
test("75% progress encouragement", $encourage75 !== null);

// Test suggestions with context
$suggestions->setContext(['passport_type' => 'DIPLOMATIQUE']);
$suggestionsData = $suggestions->getSuggestions('passport', 'fr');
test("Passport type suggestion exists", isset($suggestionsData['passport_type']));
test("Diplomatic suggestion is VIP", str_contains($suggestionsData['passport_type'] ?? '', 'prioritaire'));

// ===== 4. EMPATHIC ERRORS =====
echo "\n━━━ 4. MESSAGES D'ERREUR EMPATHIQUES ━━━\n";

$emailError = EmpathicErrors::get('validation', 'email_invalid', 'fr');
test("Email error is friendly", str_contains($emailError, '🤔') || str_contains($emailError, 'Exemple'));

$passportError = EmpathicErrors::get('validation', 'passport_expired', 'fr');
test("Passport error has advice", str_contains($passportError, 'Conseil') || str_contains($passportError, '💡'));

$networkError = EmpathicErrors::get('technical', 'network_error', 'fr');
test("Network error is reassuring", str_contains($networkError, 'réessaie') || str_contains($networkError, 'Vérifie'));

$vaccinationError = EmpathicErrors::get('business', 'vaccination_required', 'fr');
test("Vaccination error explains", str_contains($vaccinationError, 'OBLIGATOIRE') || str_contains($vaccinationError, 'obligatoire'));

$helpPassport = EmpathicErrors::helpFor('passport_scan', 'fr');
test("Help for passport is detailed", str_contains($helpPassport, 'MRZ') && str_contains($helpPassport, 'éclairée'));

$helpVaccination = EmpathicErrors::helpFor('vaccination', 'fr');
test("Help for vaccination explains", str_contains($helpVaccination, '10 jours'));

// ===== 5. NLU FALLBACK =====
echo "\n━━━ 5. NLU FALLBACK (SANS API) ━━━\n";

// Test intent detection fallback
$context = ['current_step' => 'welcome', 'language' => 'fr'];

$resultConfirm = $engine->understandIntent('oui', $context);
test("Confirm intent detected", $resultConfirm['intent'] === 'confirm');

$resultDeny = $engine->understandIntent('non', $context);
test("Deny intent detected", $resultDeny['intent'] === 'deny');

$resultHelp = $engine->understandIntent('aide svp comment faire', $context);
test("Help intent detected", $resultHelp['intent'] === 'ask_help');

// Test data extraction fallback
$contextResidence = ['current_step' => 'residence', 'language' => 'fr'];
$resultCountry = $engine->understandIntent('je vis en Éthiopie', $contextResidence);
test("Country extracted from text", 
    isset($resultCountry['extracted_data']['country_code']) && 
    $resultCountry['extracted_data']['country_code'] === 'ET'
);

$resultContact = ['current_step' => 'contact', 'language' => 'fr'];
$resultEmail = $engine->understandIntent('mon email est test@example.com', $resultContact);
test("Email extracted from text",
    isset($resultEmail['extracted_data']['email']) &&
    $resultEmail['extracted_data']['email'] === 'test@example.com'
);

// ===== 6. CONTEXTUAL HELP =====
echo "\n━━━ 6. AIDE CONTEXTUELLE ━━━\n";

$helpWelcome = $engine->generateHelp('welcome', [], 'fr');
test("Welcome help exists", strlen($helpWelcome) > 20);

$helpResidence = $engine->generateHelp('residence', [], 'fr');
test("Residence help lists countries", str_contains($helpResidence, 'Éthiopie') || str_contains($helpResidence, 'Kenya'));

$helpPassport = $engine->generateHelp('passport', [], 'fr');
test("Passport help has steps", str_contains($helpPassport, 'MRZ') || str_contains($helpPassport, 'photo'));

$helpHealth = $engine->generateHelp('health', [], 'fr');
test("Health help explains vaccination", str_contains($helpHealth, 'fièvre jaune') || str_contains($helpHealth, 'vaccin'));

// ===== RÉSUMÉ =====
echo "\n══════════════════════════════════════════════════════════════\n";
echo "                        RÉSUMÉ\n";
echo "══════════════════════════════════════════════════════════════\n\n";
echo "  Total:    " . ($passed + $failed) . " tests\n";
echo "  Passés:   $passed ✅\n";
echo "  Échoués:  $failed ❌\n";
echo "  Taux:     " . round($passed / ($passed + $failed) * 100) . "%\n\n";

if ($failed === 0) {
    echo "  🎉 TOUS LES TESTS PASSENT!\n";
} else {
    echo "  ⚠️  Certains tests ont échoué.\n";
}

