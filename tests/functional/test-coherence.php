<?php
/**
 * Test du validateur de cohérence cross-documents
 * Utilise les documents en cache pour tester la validation
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/php/services/DocumentCoherenceValidator.php';

use VisaChatbot\Services\DocumentCoherenceValidator;

echo "\n\033[1m\033[36m═══════════════════════════════════════════════════════════════════\033[0m\n";
echo "\033[1mTEST VALIDATION COHÉRENCE CROSS-DOCUMENTS\033[0m\n";
echo "\033[1m\033[36m═══════════════════════════════════════════════════════════════════\033[0m\n\n";

// Charger les documents depuis le cache
$cacheDir = __DIR__ . '/cache/ocr/';

$docs = [];

// Charger passeport
$passportFiles = glob($cacheDir . 'passport_*.json');
if (!empty($passportFiles)) {
    $passportData = json_decode(file_get_contents($passportFiles[0]), true);
    $docs['passport'] = $passportData;
    echo "\033[32m✓ Passeport chargé\033[0m\n";
} else {
    echo "\033[33m⚠ Passeport non trouvé en cache\033[0m\n";
}

// Charger billet d'avion
$ticketFiles = glob($cacheDir . 'ticket_*.json');
if (!empty($ticketFiles)) {
    $docs['ticket'] = json_decode(file_get_contents($ticketFiles[0]), true);
    echo "\033[32m✓ Billet d'avion chargé\033[0m\n";
} else {
    echo "\033[33m⚠ Billet d'avion non trouvé en cache\033[0m\n";
}

// Charger hôtel
$hotelFiles = glob($cacheDir . 'hotel_*.json');
if (!empty($hotelFiles)) {
    $docs['hotel'] = json_decode(file_get_contents($hotelFiles[0]), true);
    echo "\033[32m✓ Réservation hôtel chargée\033[0m\n";
} else {
    echo "\033[33m⚠ Hôtel non trouvé en cache\033[0m\n";
}

// Charger invitation
$invitationFiles = glob($cacheDir . 'invitation_*.json');
if (!empty($invitationFiles)) {
    $docs['invitation'] = json_decode(file_get_contents($invitationFiles[0]), true);
    echo "\033[32m✓ Lettre d'invitation chargée\033[0m\n";
} else {
    echo "\033[33m⚠ Invitation non trouvée en cache\033[0m\n";
}

// Charger vaccination
$vaccinationFiles = glob($cacheDir . 'vaccination_*.json');
if (!empty($vaccinationFiles)) {
    $docs['vaccination'] = json_decode(file_get_contents($vaccinationFiles[0]), true);
    echo "\033[32m✓ Carnet vaccination chargé\033[0m\n";
} else {
    echo "\033[33m⚠ Vaccination non trouvée en cache\033[0m\n";
}

echo "\n\033[1m\033[33m─────────────────────────────────────────────────────────────────\033[0m\n";
echo "\033[1mDOCUMENTS CHARGÉS: " . count($docs) . "\033[0m\n";
echo "\033[1m\033[33m─────────────────────────────────────────────────────────────────\033[0m\n\n";

// Afficher un résumé des données clés
echo "\033[1mDonnées clés extraites:\033[0m\n\n";

if (isset($docs['passport'])) {
    $p = $docs['passport'];
    $name = ($p['fields']['surname']['value'] ?? 'N/A') . ' ' . ($p['fields']['given_names']['value'] ?? '');
    $expiry = $p['fields']['date_of_expiry']['value'] ?? 'N/A';
    echo "  \033[36mPasseport:\033[0m {$name}\n";
    echo "             Expiration: {$expiry}\n";
}

if (isset($docs['ticket'])) {
    $t = $docs['ticket'];
    echo "  \033[36mVol:\033[0m       {$t['flight_number']} - {$t['departure_city']} → {$t['arrival_city']}\n";
    echo "             Départ: {$t['departure_date']}\n";
    echo "             Retour: " . ($t['return_date'] ?? "\033[33mNON DÉTECTÉ\033[0m") . "\n";
}

if (isset($docs['hotel'])) {
    $h = $docs['hotel'];
    echo "  \033[36mHôtel:\033[0m     {$h['hotel_name']}\n";
    echo "             {$h['hotel_city']} - Check-in: {$h['check_in_date']}, Check-out: {$h['check_out_date']}\n";
}

if (isset($docs['invitation'])) {
    $i = $docs['invitation'];
    $from = $i['dates']['from'] ?? 'N/A';
    $to = $i['dates']['to'] ?? 'N/A';
    $accommodationProvided = ($i['accommodation_provided'] ?? false) ? 'OUI' : 'NON';
    echo "  \033[36mInvitation:\033[0m {$i['purpose']}\n";
    echo "             Du {$from} au {$to}\n";
    echo "             Hébergement fourni: {$accommodationProvided}\n";
}

echo "\n\033[1m\033[33m─────────────────────────────────────────────────────────────────\033[0m\n";
echo "\033[1mVALIDATION DE COHÉRENCE\033[0m\n";
echo "\033[1m\033[33m─────────────────────────────────────────────────────────────────\033[0m\n\n";

// Exécuter la validation
$validator = new DocumentCoherenceValidator();
$result = $validator->validateDossier($docs);

// Afficher les informations de séjour
echo "\033[1mInformations de séjour calculées:\033[0m\n";
$stay = $result['stay_info'];
echo "  Arrivée:        " . ($stay['arrival_date'] ?? 'Non déterminée') . "\n";
echo "  Départ:         " . ($stay['departure_date'] ?? 'Non déterminé') . "\n";
echo "  Durée séjour:   " . ($stay['stay_days'] ?? 'N/A') . " jours\n";
echo "  Hébergement:    " . ($stay['accommodation_nights'] ?? 0) . " nuit(s)\n";
echo "  Invitation:     " . ($stay['invitation_days'] ?? 'N/A') . " jours ({$stay['invitation_from']} → {$stay['invitation_to']})\n";
echo "  Héb. fourni:    " . ($stay['accommodation_provided'] ? "\033[32mOUI\033[0m" : "\033[33mNON\033[0m") . "\n";

echo "\n\033[1m\033[33m─────────────────────────────────────────────────────────────────\033[0m\n";
echo "\033[1mRÉSULTAT DE LA VALIDATION\033[0m\n";
echo "\033[1m\033[33m─────────────────────────────────────────────────────────────────\033[0m\n\n";

// Statut global
$statusColor = $result['is_coherent'] ? "\033[32m" : ($result['is_blocked'] ? "\033[31m" : "\033[33m");
$statusText = $result['is_coherent'] ? "✓ COHÉRENT" : ($result['is_blocked'] ? "✗ BLOQUÉ" : "⚠ ALERTES");
echo "  Statut: {$statusColor}{$statusText}\033[0m\n";
echo "  Issues: " . count($result['issues']) . " (" .
     $result['summary']['errors_count'] . " erreurs, " .
     $result['summary']['warnings_count'] . " avertissements)\n\n";

// Afficher les issues
if (!empty($result['issues'])) {
    echo "\033[1mProblèmes détectés:\033[0m\n\n";

    foreach ($result['issues'] as $index => $issue) {
        $severityColor = match($issue['severity']) {
            'error' => "\033[31m",
            'warning' => "\033[33m",
            'info' => "\033[36m",
            default => "\033[0m"
        };
        $severityIcon = match($issue['severity']) {
            'error' => '✗',
            'warning' => '⚠',
            'info' => 'ℹ',
            default => '•'
        };

        echo "  {$severityColor}{$severityIcon} [{$issue['type']}]\033[0m\n";
        echo "    {$issue['message_fr']}\n";

        if (!empty($issue['detail'])) {
            echo "    \033[90m→ {$issue['detail']}\033[0m\n";
        }

        // Afficher les actions disponibles
        if (!empty($issue['actions'])) {
            echo "    \033[35mActions:\033[0m\n";
            foreach ($issue['actions'] as $action) {
                $actionLabel = $action['label_fr'] ?? $action['label'] ?? 'Action';
                echo "      • {$actionLabel}";
                if (isset($action['doc_type'])) {
                    echo " \033[90m(type: {$action['doc_type']})\033[0m";
                }
                echo "\n";
            }
        }

        echo "\n";
    }
} else {
    echo "\033[32m✓ Aucun problème de cohérence détecté!\033[0m\n\n";
}

// Afficher les actions requises
if (!empty($result['required_actions'])) {
    echo "\033[1m\033[35m─────────────────────────────────────────────────────────────────\033[0m\n";
    echo "\033[1mACTIONS SUGGÉRÉES\033[0m\n";
    echo "\033[1m\033[35m─────────────────────────────────────────────────────────────────\033[0m\n\n";

    foreach ($result['required_actions'] as $action) {
        $icon = match($action['type']) {
            'upload' => '📤',
            'confirm' => '✅',
            'update' => '🔄',
            default => '•'
        };

        echo "  {$icon} {$action['label_fr']}\n";
        if (!empty($action['detail'])) {
            echo "     \033[90m{$action['detail']}\033[0m\n";
        }
    }
    echo "\n";
}

// Résumé final
echo "\033[1m\033[36m═══════════════════════════════════════════════════════════════════\033[0m\n";
echo "\033[1mRÉSUMÉ DU DOSSIER\033[0m\n";
echo "\033[1m\033[36m═══════════════════════════════════════════════════════════════════\033[0m\n\n";

$summary = $result['summary'];
echo "  Demandeur:     {$summary['applicant_name']}\n";
echo "  Destination:   {$summary['destination']}\n";
echo "  Motif:         {$summary['purpose']}\n";
echo "  Durée:         {$summary['stay_duration']}\n";
echo "  Documents:     " . implode(', ', $summary['documents_present']) . "\n";
echo "\n";

// Exporter le résultat JSON
$outputFile = __DIR__ . '/cache/coherence_result.json';
file_put_contents($outputFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\033[90mRésultat exporté: {$outputFile}\033[0m\n\n";
