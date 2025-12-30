#!/usr/bin/env php
<?php
/**
 * Script de test des personas - Chatbot Visa CI
 *
 * Usage:
 *   php test-personas.php              # Exécuter tous les tests
 *   php test-personas.php --list       # Lister les personas disponibles
 *   php test-personas.php --persona=X  # Tester une persona spécifique
 *   php test-personas.php --json       # Sortie JSON
 */

require_once __DIR__ . '/tests/personas/PersonaTestRunner.php';

// Couleurs terminal
$RED = "\033[31m";
$GREEN = "\033[32m";
$YELLOW = "\033[33m";
$BLUE = "\033[34m";
$CYAN = "\033[36m";
$RESET = "\033[0m";
$BOLD = "\033[1m";

// Parse arguments
$options = getopt('', ['list', 'persona:', 'json', 'help', 'verbose']);

if (isset($options['help'])) {
    echo <<<HELP

{$BOLD}🧪 PERSONA TEST RUNNER - Chatbot Visa CI{$RESET}

Usage:
  php test-personas.php [options]

Options:
  --list          Afficher la liste des personas disponibles
  --persona=ID    Tester une persona spécifique (ex: --persona=ethiopian_business)
  --json          Sortie au format JSON
  --verbose       Affichage détaillé
  --help          Afficher cette aide

Personas disponibles:
  - ethiopian_business  : Homme d'affaires éthiopien (happy path)
  - kenyan_diplomat     : Diplomate kenyan (workflow diplomatique)
  - ethiopian_student   : Étudiante éthiopienne (long séjour)
  - one_way_traveler    : Voyageur sans billet retour (warning)
  - expired_passport    : Passeport expiré (erreur bloquante)
  - accommodation_gap   : Hébergement insuffisant (warning)
  - non_jurisdiction    : Nationalité hors juridiction (redirection)
  - name_mismatch       : Incohérence de noms (warning)


HELP;
    exit(0);
}

$runner = new PersonaTestRunner();

// Liste des personas
if (isset($options['list'])) {
    echo "\n{$BOLD}📋 PERSONAS DISPONIBLES{$RESET}\n\n";

    foreach ($runner->getPersonas() as $id => $persona) {
        $workflow = $persona['expected_workflow'];
        $workflowColor = match($workflow) {
            'STANDARD' => GREEN,
            'DIPLOMATIC' => CYAN,
            'BLOCKED' => RED,
            'REDIRECT' => YELLOW,
            default => RESET
        };

        echo "{$BOLD}$id{$RESET}\n";
        echo "  👤 {$persona['name']}\n";
        echo "  📝 {$persona['description']}\n";
        echo "  🔄 Workflow: {$workflowColor}{$workflow}{$RESET}\n";
        echo "  🎯 Scénario: {$persona['scenario']}\n";

        if (!empty($persona['expected_issues'])) {
            echo "  ⚠️  Issues attendues: " . implode(', ', $persona['expected_issues']) . "\n";
        }
        echo "\n";
    }
    exit(0);
}

// Test d'une persona spécifique
if (isset($options['persona'])) {
    $personaId = $options['persona'];
    $personas = $runner->getPersonas();

    if (!isset($personas[$personaId])) {
        echo "{$RED}❌ Persona '$personaId' non trouvée.{$RESET}\n";
        echo "Utilisez --list pour voir les personas disponibles.\n";
        exit(1);
    }

    $result = $runner->testPersona($personaId, $personas[$personaId]);

    if (isset($options['json'])) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    exit($result['passed'] ? 0 : 1);
}

// Exécuter tous les tests
$results = $runner->runAllTests();

if (isset($options['json'])) {
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// Code de sortie basé sur les résultats
$allPassed = array_reduce($results, fn($carry, $r) => $carry && $r['passed'], true);
exit($allPassed ? 0 : 1);
