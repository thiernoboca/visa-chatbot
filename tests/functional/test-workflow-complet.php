<?php
/**
 * Test Workflow Complet - Simulation Chatbot Visa CI
 * Teste tous les documents du dossier test avec le Triple Layer OCR
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(600);

require_once __DIR__ . '/php/config.php';
require_once __DIR__ . '/php/document-extractor.php';

// Couleurs terminal
define('GREEN', "\033[32m");
define('RED', "\033[31m");
define('YELLOW', "\033[33m");
define('BLUE', "\033[34m");
define('CYAN', "\033[36m");
define('BOLD', "\033[1m");
define('RESET', "\033[0m");

$testDir = '/Users/cheickmouhamedelhadykane/Downloads/test/';

$documents = [
    'passport' => [
        'file' => 'passportpassport-scan.pdf',
        'name' => 'Passeport (EJIGU GEZAHEGN MOGES)',
        'step' => 2
    ],
    'ticket' => [
        'file' => 'billetelectronic-ticket-receipt-december-28-for-mr-gezahegn-mogesejigu.pdf',
        'name' => 'Billet Avion Ethiopian Airlines',
        'step' => 6
    ],
    'hotel' => [
        'file' => 'hotelgmail-thanks-your-booking-is-confirmed-at-appartement-1-a-3-pieces-equipe-cosy-calme-aigle.pdf',
        'name' => 'Réservation Hébergement',
        'step' => 6
    ],
    'vaccination' => [
        'file' => 'vaccinationyellow-faver-certificate-gezahegn-moges.pdf',
        'name' => 'Carnet Vaccination Fièvre Jaune',
        'step' => 7
    ],
    'invitation' => [
        'file' => 'ordremissioninvitation-letter-gezahegn-moges-ejigu.pdf',
        'name' => 'Lettre d\'Invitation / Ordre de Mission',
        'step' => 6
    ]
];

echo BOLD . CYAN . "
╔══════════════════════════════════════════════════════════════════╗
║     🇨🇮 TEST WORKFLOW COMPLET - e-VISA CÔTE D'IVOIRE 🇨🇮          ║
║         Triple Layer: Google Vision → Gemini 3 → Claude          ║
╚══════════════════════════════════════════════════════════════════╝
" . RESET . "\n";

// Vérifier configuration
echo BOLD . "📋 CONFIGURATION\n" . RESET;
echo "─────────────────────────────────────────────────────────────────\n";

$checks = [
    'Google Vision' => file_exists(getenv('GOOGLE_CREDENTIALS_PATH')),
    'Gemini API' => strlen(getenv('GEMINI_API_KEY')) > 10,
    'Claude API' => strlen(getenv('CLAUDE_API_KEY')) > 10,
    'Gemini Model' => getenv('GEMINI_MODEL') ?: 'gemini-3-flash-preview'
];

foreach ($checks as $name => $status) {
    if (is_bool($status)) {
        echo ($status ? GREEN . "✓" : RED . "✗") . RESET . " $name\n";
    } else {
        echo BLUE . "◉" . RESET . " $name: " . YELLOW . $status . RESET . "\n";
    }
}

// Créer l'extracteur
$extractor = new DocumentExtractor(['debug' => false]);
echo GREEN . "✓" . RESET . " DocumentExtractor initialisé\n";
echo GREEN . "✓" . RESET . " Gemini Thinking Mode: " . YELLOW . "MEDIUM (équilibré)" . RESET . "\n";
echo "\n";

// Résultats globaux
$results = [];
$totalTime = 0;

// ═══════════════════════════════════════════════════════════════════
// ÉTAPE 1: ACCUEIL (simulé)
// ═══════════════════════════════════════════════════════════════════
echo BOLD . CYAN . "═══════════════════════════════════════════════════════════════════\n";
echo "ÉTAPE 1: ACCUEIL                                              10%\n";
echo "═══════════════════════════════════════════════════════════════════" . RESET . "\n";
echo GREEN . "✓" . RESET . " Langue sélectionnée: Français\n";
echo GREEN . "✓" . RESET . " Session initialisée\n\n";

// ═══════════════════════════════════════════════════════════════════
// ÉTAPE 2: PASSEPORT
// ═══════════════════════════════════════════════════════════════════
echo BOLD . CYAN . "═══════════════════════════════════════════════════════════════════\n";
echo "ÉTAPE 2: SCAN PASSEPORT                                       20%\n";
echo "═══════════════════════════════════════════════════════════════════" . RESET . "\n";

$doc = $documents['passport'];
$filePath = $testDir . $doc['file'];

if (file_exists($filePath)) {
    echo "📄 Fichier: " . YELLOW . basename($filePath) . RESET . "\n";
    echo "   Taille: " . round(filesize($filePath)/1024, 1) . " KB\n";
    echo "   Traitement Triple Layer en cours...\n";

    $startTime = microtime(true);
    $content = file_get_contents($filePath);
    $base64 = base64_encode($content);
    $mimeType = mime_content_type($filePath);

    try {
        $result = $extractor->extract('passport', $base64, $mimeType, false);
        $duration = round(microtime(true) - $startTime, 2);
        $totalTime += $duration;

        $results['passport'] = $result;

        echo GREEN . "✓" . RESET . " Extraction réussie en " . YELLOW . "{$duration}s" . RESET . "\n\n";

        // Afficher les données extraites
        $fields = $result['fields'] ?? [];
        $class = $result['document_classification'] ?? [];
        $elig = $result['visa_eligibility'] ?? [];

        echo BOLD . "📋 DONNÉES EXTRAITES:\n" . RESET;
        echo "┌─────────────────────────────────────────────────────────────────┐\n";
        printf("│ %-20s │ %-40s │\n", "Nom", $fields['surname']['value'] ?? 'N/A');
        printf("│ %-20s │ %-40s │\n", "Prénoms", $fields['given_names']['value'] ?? 'N/A');
        printf("│ %-20s │ %-40s │\n", "N° Passeport", $fields['passport_number']['value'] ?? 'N/A');
        printf("│ %-20s │ %-40s │\n", "Nationalité", $fields['nationality']['value'] ?? 'N/A');
        printf("│ %-20s │ %-40s │\n", "Date naissance", $fields['date_of_birth']['value'] ?? 'N/A');
        printf("│ %-20s │ %-40s │\n", "Lieu naissance", $fields['place_of_birth']['value'] ?? 'N/A');
        printf("│ %-20s │ %-40s │\n", "Date expiration", $fields['date_of_expiry']['value'] ?? 'N/A');
        printf("│ %-20s │ %-40s │\n", "Sexe", $fields['sex']['value'] ?? 'N/A');
        echo "└─────────────────────────────────────────────────────────────────┘\n\n";

        echo BOLD . "🎫 CLASSIFICATION & ÉLIGIBILITÉ:\n" . RESET;
        $category = $class['category'] ?? 'N/A';
        $subcat = $class['subcategory'] ?? 'N/A';
        $workflow = $elig['workflow'] ?? 'N/A';
        $isValid = $elig['is_valid'] ?? false;

        echo "   Type: " . CYAN . "$category / $subcat" . RESET . "\n";
        echo "   Pays: " . CYAN . ($class['issuing_country'] ?? 'N/A') . RESET . "\n";
        echo "   Éligible: " . ($isValid ? GREEN . "✓ OUI" : RED . "✗ NON") . RESET . "\n";
        echo "   Workflow: " . YELLOW . $workflow . RESET;
        if ($workflow === 'PRIORITY') {
            echo " ⚡ (Gratuit, 24-48h)";
        } elseif ($workflow === 'STANDARD') {
            echo " (73,000 XOF, 5-10 jours)";
        }
        echo "\n";

    } catch (Exception $e) {
        echo RED . "✗ Erreur: " . $e->getMessage() . RESET . "\n";
        $results['passport'] = ['error' => $e->getMessage()];
    }
} else {
    echo RED . "✗ Fichier non trouvé: $filePath" . RESET . "\n";
}

echo "\n";

// ═══════════════════════════════════════════════════════════════════
// ÉTAPE 3: RÉSIDENCE (simulé)
// ═══════════════════════════════════════════════════════════════════
echo BOLD . CYAN . "═══════════════════════════════════════════════════════════════════\n";
echo "ÉTAPE 3: PAYS DE RÉSIDENCE                                    30%\n";
echo "═══════════════════════════════════════════════════════════════════" . RESET . "\n";

$nationality = $results['passport']['fields']['nationality']['value'] ?? 'ETH';
echo GREEN . "✓" . RESET . " Nationalité détectée: " . YELLOW . $nationality . RESET . "\n";
echo GREEN . "✓" . RESET . " Pays de résidence: " . YELLOW . "ÉTHIOPIE" . RESET . " (circonscription couverte)\n";
echo GREEN . "✓" . RESET . " Ambassade compétente: Addis-Abeba\n\n";

// ═══════════════════════════════════════════════════════════════════
// ÉTAPE 4: ÉLIGIBILITÉ (simulé)
// ═══════════════════════════════════════════════════════════════════
echo BOLD . CYAN . "═══════════════════════════════════════════════════════════════════\n";
echo "ÉTAPE 4: VÉRIFICATION ÉLIGIBILITÉ                             40%\n";
echo "═══════════════════════════════════════════════════════════════════" . RESET . "\n";
echo GREEN . "✓" . RESET . " Passeport valide (+6 mois)\n";
echo GREEN . "✓" . RESET . " Nationalité éligible au e-Visa\n";
echo GREEN . "✓" . RESET . " Workflow: " . YELLOW . ($results['passport']['visa_eligibility']['workflow'] ?? 'STANDARD') . RESET . "\n\n";

// ═══════════════════════════════════════════════════════════════════
// ÉTAPE 5: PHOTO (simulé)
// ═══════════════════════════════════════════════════════════════════
echo BOLD . CYAN . "═══════════════════════════════════════════════════════════════════\n";
echo "ÉTAPE 5: PHOTO D'IDENTITÉ                                     50%\n";
echo "═══════════════════════════════════════════════════════════════════" . RESET . "\n";
echo GREEN . "✓" . RESET . " Photo d'identité: " . YELLOW . "gezahegn-moges-passport-photo.jpg" . RESET . "\n";
echo GREEN . "✓" . RESET . " Format: conforme (fond uni, visage centré)\n\n";

// ═══════════════════════════════════════════════════════════════════
// ÉTAPE 6: VOYAGE (Billet + Hôtel + Invitation)
// ═══════════════════════════════════════════════════════════════════
echo BOLD . CYAN . "═══════════════════════════════════════════════════════════════════\n";
echo "ÉTAPE 6: INFORMATIONS VOYAGE                                  60%\n";
echo "═══════════════════════════════════════════════════════════════════" . RESET . "\n";

// Billet d'avion
$doc = $documents['ticket'];
$filePath = $testDir . $doc['file'];

if (file_exists($filePath)) {
    echo "\n" . BOLD . "✈️  BILLET D'AVION\n" . RESET;
    echo "📄 Fichier: " . YELLOW . basename($filePath) . RESET . "\n";

    $startTime = microtime(true);
    $content = file_get_contents($filePath);
    $base64 = base64_encode($content);
    $mimeType = mime_content_type($filePath);

    try {
        $result = $extractor->extract('ticket', $base64, $mimeType, false);
        $duration = round(microtime(true) - $startTime, 2);
        $totalTime += $duration;
        $results['ticket'] = $result;

        echo GREEN . "✓" . RESET . " Extraction en " . YELLOW . "{$duration}s" . RESET . "\n";

        // Les champs sont au niveau racine pour ticket
        echo "   Passager: " . CYAN . ($result['passenger_name'] ?? 'N/A') . RESET . "\n";
        echo "   Référence: " . CYAN . ($result['booking_reference'] ?? 'N/A') . RESET . "\n";
        echo "   Vol: " . CYAN . ($result['flight_number'] ?? 'N/A') . RESET . "\n";
        echo "   Trajet: " . CYAN . ($result['departure_city'] ?? '?') . " → " . ($result['arrival_city'] ?? '?') . RESET . "\n";
        echo "   Date: " . CYAN . ($result['departure_date'] ?? 'N/A') . RESET . "\n";

    } catch (Exception $e) {
        echo RED . "✗ Erreur: " . $e->getMessage() . RESET . "\n";
    }
}

// Hôtel
$doc = $documents['hotel'];
$filePath = $testDir . $doc['file'];

if (file_exists($filePath)) {
    echo "\n" . BOLD . "🏨 RÉSERVATION HÉBERGEMENT\n" . RESET;
    echo "📄 Fichier: " . YELLOW . basename($filePath) . RESET . "\n";

    $startTime = microtime(true);
    $content = file_get_contents($filePath);
    $base64 = base64_encode($content);
    $mimeType = mime_content_type($filePath);

    try {
        $result = $extractor->extract('hotel', $base64, $mimeType, false);
        $duration = round(microtime(true) - $startTime, 2);
        $totalTime += $duration;
        $results['hotel'] = $result;

        echo GREEN . "✓" . RESET . " Extraction en " . YELLOW . "{$duration}s" . RESET . "\n";

        // Champs au niveau racine pour hotel
        echo "   Client: " . CYAN . ($result['guest_name'] ?? 'N/A') . RESET . "\n";
        echo "   Hôtel: " . CYAN . ($result['hotel_name'] ?? 'N/A') . RESET . "\n";
        echo "   Ville: " . CYAN . ($result['hotel_city'] ?? 'N/A') . RESET . "\n";
        echo "   Check-in: " . CYAN . ($result['check_in_date'] ?? 'N/A') . RESET . "\n";
        echo "   Check-out: " . CYAN . ($result['check_out_date'] ?? 'N/A') . RESET . "\n";

    } catch (Exception $e) {
        echo RED . "✗ Erreur: " . $e->getMessage() . RESET . "\n";
    }
}

// Invitation
$doc = $documents['invitation'];
$filePath = $testDir . $doc['file'];

if (file_exists($filePath)) {
    echo "\n" . BOLD . "📨 LETTRE D'INVITATION\n" . RESET;
    echo "📄 Fichier: " . YELLOW . basename($filePath) . RESET . "\n";

    $startTime = microtime(true);
    $content = file_get_contents($filePath);
    $base64 = base64_encode($content);
    $mimeType = mime_content_type($filePath);

    try {
        $result = $extractor->extract('invitation', $base64, $mimeType, false);
        $duration = round(microtime(true) - $startTime, 2);
        $totalTime += $duration;
        $results['invitation'] = $result;

        echo GREEN . "✓" . RESET . " Extraction en " . YELLOW . "{$duration}s" . RESET . "\n";

        // Champs au niveau racine pour invitation
        $inviter = $result['inviter'] ?? [];
        $invitee = $result['invitee'] ?? [];

        echo "   Invitant: " . CYAN . ($inviter['organization'] ?? $inviter['name'] ?? 'N/A') . RESET . "\n";
        echo "   Invité: " . CYAN . ($invitee['name'] ?? 'N/A') . RESET . "\n";
        echo "   Motif: " . CYAN . ($result['purpose'] ?? 'N/A') . RESET . "\n";

    } catch (Exception $e) {
        echo RED . "✗ Erreur: " . $e->getMessage() . RESET . "\n";
    }
}

echo "\n";

// ═══════════════════════════════════════════════════════════════════
// ÉTAPE 7: SANTÉ (Vaccination)
// ═══════════════════════════════════════════════════════════════════
echo BOLD . CYAN . "═══════════════════════════════════════════════════════════════════\n";
echo "ÉTAPE 7: INFORMATIONS SANTÉ                                   70%\n";
echo "═══════════════════════════════════════════════════════════════════" . RESET . "\n";

$doc = $documents['vaccination'];
$filePath = $testDir . $doc['file'];

if (file_exists($filePath)) {
    echo BOLD . "💉 CARNET DE VACCINATION\n" . RESET;
    echo "📄 Fichier: " . YELLOW . basename($filePath) . RESET . "\n";

    $startTime = microtime(true);
    $content = file_get_contents($filePath);
    $base64 = base64_encode($content);
    $mimeType = mime_content_type($filePath);

    try {
        $result = $extractor->extract('vaccination', $base64, $mimeType, false);
        $duration = round(microtime(true) - $startTime, 2);
        $totalTime += $duration;
        $results['vaccination'] = $result;

        echo GREEN . "✓" . RESET . " Extraction en " . YELLOW . "{$duration}s" . RESET . "\n";

        // Champs au niveau racine pour vaccination
        echo "   Titulaire: " . CYAN . ($result['holder_name'] ?? 'N/A') . RESET . "\n";
        echo "   Vaccin: " . CYAN . ($result['vaccine_type'] ?? 'N/A') . RESET . "\n";
        echo "   Date: " . CYAN . ($result['vaccination_date'] ?? 'N/A') . RESET . "\n";
        echo "   N° Certificat: " . CYAN . ($result['certificate_number'] ?? 'N/A') . RESET . "\n";

        $isValid = $result['valid'] ?? false;
        echo "   Statut: " . ($isValid ? GREEN . "✓ VALIDE" : YELLOW . "⚠ À VÉRIFIER") . RESET . "\n";

    } catch (Exception $e) {
        echo RED . "✗ Erreur: " . $e->getMessage() . RESET . "\n";
    }
}

echo "\n";

// ═══════════════════════════════════════════════════════════════════
// ÉTAPE 8: DOUANES (simulé)
// ═══════════════════════════════════════════════════════════════════
echo BOLD . CYAN . "═══════════════════════════════════════════════════════════════════\n";
echo "ÉTAPE 8: DÉCLARATION DOUANIÈRE                                80%\n";
echo "═══════════════════════════════════════════════════════════════════" . RESET . "\n";
echo GREEN . "✓" . RESET . " Devises > 10,000€: Non\n";
echo GREEN . "✓" . RESET . " Marchandises commerciales: Non\n";
echo GREEN . "✓" . RESET . " Produits sensibles: Non\n";
echo GREEN . "✓" . RESET . " Médicaments spéciaux: Non\n\n";

// ═══════════════════════════════════════════════════════════════════
// ÉTAPE 9: RÉCAPITULATIF
// ═══════════════════════════════════════════════════════════════════
echo BOLD . CYAN . "═══════════════════════════════════════════════════════════════════\n";
echo "ÉTAPE 9: RÉCAPITULATIF & CONFIRMATION                         90%\n";
echo "═══════════════════════════════════════════════════════════════════" . RESET . "\n";

// Passeport a une structure 'fields', les autres sont au niveau racine
$passport = $results['passport']['fields'] ?? [];
$ticket = $results['ticket'] ?? [];
$hotel = $results['hotel'] ?? [];
$vaccination = $results['vaccination'] ?? [];
$invitation = $results['invitation'] ?? [];
$workflow = $results['passport']['visa_eligibility']['workflow'] ?? 'STANDARD';

echo BOLD . "\n📋 RÉCAPITULATIF DE LA DEMANDE:\n" . RESET;
echo "┌─────────────────────────────────────────────────────────────────┐\n";
echo "│ " . BOLD . "DEMANDEUR" . RESET . "                                                     │\n";
printf("│   Nom complet: %-47s │\n", ($passport['surname']['value'] ?? '') . ' ' . ($passport['given_names']['value'] ?? ''));
printf("│   Passeport: %-49s │\n", $passport['passport_number']['value'] ?? 'N/A');
printf("│   Nationalité: %-47s │\n", $passport['nationality']['value'] ?? 'N/A');
echo "├─────────────────────────────────────────────────────────────────┤\n";
echo "│ " . BOLD . "VOYAGE" . RESET . "                                                         │\n";
printf("│   Destination: %-47s │\n", "CÔTE D'IVOIRE");
printf("│   Vol: %-55s │\n", ($ticket['flight_number'] ?? 'N/A') . ' - ' . ($ticket['departure_date'] ?? 'N/A'));
printf("│   Trajet: %-52s │\n", ($ticket['departure_city'] ?? '?') . ' → ' . ($ticket['arrival_city'] ?? '?'));
printf("│   Hébergement: %-47s │\n", substr($hotel['hotel_name'] ?? 'N/A', 0, 47));
printf("│   Ville: %-53s │\n", $hotel['hotel_city'] ?? 'N/A');
echo "├─────────────────────────────────────────────────────────────────┤\n";
echo "│ " . BOLD . "INVITATION" . RESET . "                                                     │\n";
printf("│   Organisation: %-46s │\n", substr($invitation['inviter']['organization'] ?? $invitation['inviter']['name'] ?? 'N/A', 0, 46));
printf("│   Motif: %-53s │\n", substr($invitation['purpose'] ?? 'N/A', 0, 53));
echo "├─────────────────────────────────────────────────────────────────┤\n";
echo "│ " . BOLD . "SANTÉ" . RESET . "                                                          │\n";
printf("│   Fièvre Jaune: %-46s │\n", ($vaccination['vaccine_type'] ?? 'N/A') . ' ✓');
printf("│   N° Certificat: %-45s │\n", $vaccination['certificate_number'] ?? 'N/A');
echo "├─────────────────────────────────────────────────────────────────┤\n";
echo "│ " . BOLD . "TARIFICATION" . RESET . "                                                   │\n";
if ($workflow === 'PRIORITY') {
    echo "│   Type: PRIORITY (Diplomatique/Service)                        │\n";
    echo "│   Frais: " . GREEN . "GRATUIT" . RESET . "                                                 │\n";
    echo "│   Délai: 24-48 heures                                          │\n";
} else {
    echo "│   Type: STANDARD                                               │\n";
    echo "│   Frais: 73,000 XOF                                            │\n";
    echo "│   Délai: 5-10 jours ouvrés                                     │\n";
}
echo "└─────────────────────────────────────────────────────────────────┘\n";

// ═══════════════════════════════════════════════════════════════════
// CROSS-VALIDATION
// ═══════════════════════════════════════════════════════════════════
echo BOLD . "\n🔄 CROSS-VALIDATION DOCUMENTS:\n" . RESET;

$passportName = strtoupper(($passport['surname']['value'] ?? '') . ' ' . ($passport['given_names']['value'] ?? ''));
$ticketName = strtoupper($ticket['passenger_name'] ?? '');
$hotelName = strtoupper($hotel['guest_name'] ?? '');
$vaccName = strtoupper($vaccination['holder_name'] ?? '');
$inviteeName = strtoupper($invitation['invitee']['name'] ?? '');

echo "   Passeport:   " . CYAN . $passportName . RESET . "\n";

if (!empty($ticketName)) {
    $match = strpos($ticketName, substr($passportName, 0, 5)) !== false ||
             strpos($passportName, substr($ticketName, 0, 5)) !== false;
    echo "   Billet:      " . ($match ? GREEN . "✓ " : YELLOW . "⚠ ") . $ticketName . RESET . "\n";
}
if (!empty($hotelName)) {
    $match = strpos(strtoupper($hotelName), substr($passportName, 0, 5)) !== false ||
             strpos($passportName, strtoupper(substr($hotelName, 0, 5))) !== false;
    echo "   Hôtel:       " . ($match ? GREEN . "✓ " : YELLOW . "⚠ ") . $hotelName . RESET . "\n";
}
if (!empty($inviteeName)) {
    $match = strpos($inviteeName, substr($passportName, 0, 5)) !== false ||
             strpos($passportName, substr($inviteeName, 0, 5)) !== false;
    echo "   Invitation:  " . ($match ? GREEN . "✓ " : YELLOW . "⚠ ") . $inviteeName . RESET . "\n";
}
if (!empty($vaccName)) {
    $match = strpos($vaccName, substr($passportName, 0, 5)) !== false ||
             strpos($passportName, substr($vaccName, 0, 5)) !== false;
    echo "   Vaccination: " . ($match ? GREEN . "✓ " : YELLOW . "⚠ ") . $vaccName . RESET . "\n";
}

// ═══════════════════════════════════════════════════════════════════
// RÉSULTAT FINAL
// ═══════════════════════════════════════════════════════════════════
echo BOLD . CYAN . "\n═══════════════════════════════════════════════════════════════════\n";
echo "ÉTAPE 10: SOUMISSION                                         100%\n";
echo "═══════════════════════════════════════════════════════════════════" . RESET . "\n";

$successCount = count(array_filter($results, fn($r) => !isset($r['error'])));
$totalCount = count($results);

echo "\n" . BOLD . "📊 STATISTIQUES:\n" . RESET;
echo "   Documents traités: " . GREEN . "$successCount/$totalCount" . RESET . "\n";
echo "   Temps total OCR: " . YELLOW . round($totalTime, 2) . "s" . RESET . "\n";
echo "   Temps moyen/doc: " . YELLOW . round($totalTime / max($successCount, 1), 2) . "s" . RESET . "\n";
echo "   Architecture: " . CYAN . "Google Vision → Gemini 3 Flash (MEDIUM) → Claude" . RESET . "\n";

echo "\n" . BOLD;
if ($successCount === $totalCount) {
    echo GREEN . "╔══════════════════════════════════════════════════════════════════╗\n";
    echo "║  ✅ DEMANDE PRÊTE À ÊTRE SOUMISE                                 ║\n";
    echo "║     Tous les documents ont été vérifiés avec succès!            ║\n";
    echo "╚══════════════════════════════════════════════════════════════════╝" . RESET . "\n";
} else {
    echo YELLOW . "╔══════════════════════════════════════════════════════════════════╗\n";
    echo "║  ⚠️  DEMANDE INCOMPLÈTE                                          ║\n";
    echo "║     Certains documents nécessitent une vérification manuelle    ║\n";
    echo "╚══════════════════════════════════════════════════════════════════╝" . RESET . "\n";
}

echo "\n";
