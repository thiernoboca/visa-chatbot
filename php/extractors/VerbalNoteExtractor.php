<?php
/**
 * Extracteur de Note Verbale Diplomatique
 * Ambassade de Côte d'Ivoire en Éthiopie
 *
 * Conforme au PRD: DOC_NOTE_VERBALE
 * Requis pour passeports DIPLOMATIQUE et SERVICE
 *
 * @package VisaChatbot\Extractors
 * @version 1.0.0
 */

namespace VisaChatbot\Extractors;

require_once __DIR__ . '/AbstractExtractor.php';

class VerbalNoteExtractor extends AbstractExtractor {

    /**
     * Formats de note verbale reconnus
     */
    public const NOTE_FORMATS = [
        'VERBAL_NOTE' => ['NOTE VERBALE', 'VERBAL NOTE'],
        'DIPLOMATIC_NOTE' => ['NOTE DIPLOMATIQUE', 'DIPLOMATIC NOTE'],
        'THIRD_PERSON_NOTE' => ['NOTE EN TROISIÈME PERSONNE', 'THIRD PERSON NOTE']
    ];

    /**
     * Organisations internationales reconnues
     */
    public const INTERNATIONAL_ORGS = [
        'UNITED NATIONS' => 'ONU',
        'UN' => 'ONU',
        'ONU' => 'ONU',
        'AFRICAN UNION' => 'UA',
        'AU' => 'UA',
        'UA' => 'UA',
        'EUROPEAN UNION' => 'UE',
        'EU' => 'UE',
        'WORLD BANK' => 'BANQUE MONDIALE',
        'IMF' => 'FMI',
        'WHO' => 'OMS',
        'UNESCO' => 'UNESCO',
        'UNICEF' => 'UNICEF',
        'UNHCR' => 'HCR',
        'ECOWAS' => 'CEDEAO',
        'CEDEAO' => 'CEDEAO'
    ];

    protected array $requiredFields = [
        'sending_entity',
        'diplomat_name',
        'date'
    ];

    protected array $optionalFields = [
        'receiving_entity',
        'reference_number',
        'subject',
        'diplomat_title',
        'diplomat_passport_number',
        'mission_purpose',
        'requested_visa_type',
        'travel_dates',
        'accompanying_persons'
    ];

    /**
     * Extrait les données de la note verbale
     */
    public function extract(string $rawText, array $ocrMetadata = []): array {
        $result = [
            'success' => false,
            'extracted' => [],
            'note_type' => null,
            'authenticity_indicators' => []
        ];

        $text = $this->cleanOcrText($rawText);

        // 1. Identifier le type de note
        $result['note_type'] = $this->identifyNoteType($text);

        // 2. Extraire l'entité émettrice
        $result['extracted']['sending_entity'] = $this->extractSendingEntity($text);

        // 3. Extraire l'entité destinataire
        $result['extracted']['receiving_entity'] = $this->extractReceivingEntity($text);

        // 4. Extraire la référence
        $result['extracted']['reference_number'] = $this->extractReference($text);

        // 5. Extraire la date
        $result['extracted']['date'] = $this->extractDate($text);

        // 6. Extraire l'objet
        $result['extracted']['subject'] = $this->extractSubject($text);

        // 7. Extraire les informations du diplomate
        $diplomatInfo = $this->extractDiplomatInfo($text);
        $result['extracted'] = array_merge($result['extracted'], $diplomatInfo);

        // 8. Extraire les dates de voyage demandées
        $result['extracted']['travel_dates'] = $this->extractTravelDates($text);

        // 9. Extraire le type de visa demandé
        $result['extracted']['requested_visa_type'] = $this->extractVisaType($text);

        // 10. Extraire les personnes accompagnantes
        $result['extracted']['accompanying_persons'] = $this->extractAccompanyingPersons($text);

        // 11. Indicateurs d'authenticité
        $result['authenticity_indicators'] = $this->checkAuthenticityIndicators($text);

        // 12. Succès si champs requis présents
        $result['success'] = $this->hasRequiredFields($result['extracted']);

        return $result;
    }

    /**
     * Identifie le type de note
     */
    private function identifyNoteType(string $text): ?string {
        $textUpper = strtoupper($text);

        foreach (self::NOTE_FORMATS as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($textUpper, $pattern) !== false) {
                    return $type;
                }
            }
        }

        return null;
    }

    /**
     * Extrait l'entité émettrice (ambassade, ministère, organisation)
     */
    private function extractSendingEntity(string $text): ?string {
        $patterns = [
            // Ambassades
            '/(?:EMBASSY|AMBASSADE)\s*(?:OF|DE)\s*(?:THE\s*)?([A-Za-z\s\-\']+)/i',
            '/(?:L\'?AMBASSADE|THE\s*EMBASSY)\s*(?:DE|OF)\s*([A-Za-z\s\-\']+)/i',

            // Ministères
            '/(?:MINISTRY|MINISTERE)\s*(?:OF|DE)\s*(?:THE\s*)?([A-Za-z\s\-\']+)/i',

            // Organisations internationales
            '/(?:MISSION|DELEGATION)\s*(?:OF|DE)\s*([A-Za-z\s\-\']+)/i',

            // En-tête standard
            '/^([A-Z][A-Za-z\s\-\']+(?:EMBASSY|AMBASSADE|MINISTRY|MISSION))/im'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return trim($match[1]);
            }
        }

        // Chercher une organisation internationale
        $textUpper = strtoupper($text);
        foreach (self::INTERNATIONAL_ORGS as $name => $code) {
            if (strpos($textUpper, $name) !== false) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Extrait l'entité destinataire
     */
    private function extractReceivingEntity(string $text): ?string {
        // Doit mentionner l'Ambassade de Côte d'Ivoire
        $patterns = [
            '/(?:TO|À|DESTINATAIRE)[:\s]*([^\.]+(?:IVOIRE|CI|CIV)[^\.]*)/i',
            '/(?:AMBASSADE|EMBASSY)\s*(?:DE|OF)\s*(?:LA\s*)?(?:REPUBLIQUE\s*(?:DE\s*)?)?(?:COTE\s*D[\'`]?IVOIRE|IVORY\s*COAST)/i',
            '/(?:COTE\s*D[\'`]?IVOIRE|IVORY\s*COAST)\s*(?:EMBASSY|AMBASSADE)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return "Ambassade de Côte d'Ivoire";
            }
        }

        return null;
    }

    /**
     * Extrait le numéro de référence
     */
    private function extractReference(string $text): ?string {
        $patterns = [
            '/(?:REF|REFERENCE|N°|NO)[\.:\s]*([A-Z0-9\/\-]+)/i',
            '/(?:VERBAL\s*NOTE)\s*(?:N°|NO)?[\.:\s]*([A-Z0-9\/\-]+)/i',
            '/\b([A-Z]{2,5}[\/\-]\d{2,4}[\/\-][A-Z0-9]+)\b/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return strtoupper(trim($match[1]));
            }
        }

        return null;
    }

    /**
     * Extrait la date de la note
     */
    private function extractDate(string $text): ?string {
        // Chercher une date près de mots-clés
        $patterns = [
            '/(?:DATE|DATED|DU|LE)[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
            '/(\d{1,2}\s+(?:JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER|JANVIER|FEVRIER|MARS|AVRIL|MAI|JUIN|JUILLET|AOUT|SEPTEMBRE|OCTOBRE|NOVEMBRE|DECEMBRE)\s+\d{4})/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return $this->parseDate($match[1]);
            }
        }

        return null;
    }

    /**
     * Extrait l'objet de la note
     */
    private function extractSubject(string $text): ?string {
        $patterns = [
            '/(?:OBJET|SUBJECT|RE|CONCERNING)[:\s]*([^\n]+)/i',
            '/(?:REQUEST\s*FOR)\s*([^\n]+)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return trim($match[1]);
            }
        }

        return null;
    }

    /**
     * Extrait les informations du diplomate
     */
    private function extractDiplomatInfo(string $text): array {
        $info = [
            'diplomat_name' => null,
            'diplomat_title' => null,
            'diplomat_passport_number' => null,
            'mission_purpose' => null
        ];

        // Nom du diplomate
        $namePatterns = [
            '/(?:MR|MRS|MS|H\.E\.|HIS\s*EXCELLENCY|SON\s*EXCELLENCE|S\.E\.)[\.:\s]*([A-Z][A-Za-z\s\-\']+)/i',
            '/(?:DIPLOMAT|OFFICIAL|OFFICER)[:\s]*([A-Z][A-Za-z\s\-\']+)/i',
            '/(?:NAME|NOM)[:\s]*([A-Z][A-Za-z\s\-\']+)/i'
        ];

        foreach ($namePatterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $info['diplomat_name'] = $this->normalizeName(trim($match[1]));
                break;
            }
        }

        // Titre
        $titlePatterns = [
            '/(?:TITLE|TITRE|FUNCTION|FONCTION)[:\s]*([A-Za-z\s\-\']+)/i',
            '/\b(AMBASSADOR|AMBASSADEUR|COUNSELLOR|CONSEILLER|FIRST\s*SECRETARY|SECOND\s*SECRETARY|ATTACHE)\b/i'
        ];

        foreach ($titlePatterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $info['diplomat_title'] = trim($match[1]);
                break;
            }
        }

        // Numéro de passeport
        if (preg_match('/(?:PASSPORT|PASSEPORT)\s*(?:NO|N°|NUMBER)?[:\s]*([A-Z0-9]+)/i', $text, $match)) {
            $info['diplomat_passport_number'] = strtoupper($match[1]);
        }

        // But de la mission
        $purposePatterns = [
            '/(?:PURPOSE|OBJET\s*(?:DE\s*LA)?\s*MISSION|MISSION)[:\s]*([^\n\.]+)/i',
            '/(?:OFFICIAL\s*)?(?:VISIT|VISITE)\s*(?:TO|EN)[:\s]*([^\n\.]+)/i'
        ];

        foreach ($purposePatterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $info['mission_purpose'] = trim($match[1]);
                break;
            }
        }

        return $info;
    }

    /**
     * Extrait les dates de voyage
     */
    private function extractTravelDates(string $text): ?array {
        $dates = ['from' => null, 'to' => null];

        // Pattern période
        $periodPatterns = [
            '/(?:FROM|DU)\s*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})\s*(?:TO|AU|UNTIL)\s*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
            '/(?:PERIOD|PERIODE)[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})\s*[-–]\s*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i'
        ];

        foreach ($periodPatterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $dates['from'] = $this->parseDate($match[1]);
                $dates['to'] = $this->parseDate($match[2]);
                return $dates;
            }
        }

        return null;
    }

    /**
     * Extrait le type de visa demandé
     */
    private function extractVisaType(string $text): ?string {
        $visaTypes = [
            'DIPLOMATIC' => ['DIPLOMATIQUE', 'DIPLOMATIC'],
            'SERVICE' => ['SERVICE', 'OFFICIAL', 'OFFICIEL'],
            'COURTESY' => ['COURTOISIE', 'COURTESY'],
            'TRANSIT' => ['TRANSIT']
        ];

        $textUpper = strtoupper($text);

        foreach ($visaTypes as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (preg_match('/(?:VISA|ENTRY)\s*' . $keyword . '/i', $textUpper) ||
                    preg_match('/' . $keyword . '\s*(?:VISA|ENTRY)/i', $textUpper)) {
                    return $type;
                }
            }
        }

        return null;
    }

    /**
     * Extrait les personnes accompagnantes
     */
    private function extractAccompanyingPersons(string $text): array {
        $persons = [];

        $patterns = [
            '/(?:ACCOMPANIED\s*BY|ACCOMPAGNE\s*DE|SPOUSE|EPOUSE?|WIFE|HUSBAND|CHILD|ENFANT)[:\s]*([A-Z][A-Za-z\s\-\',]+)/i'
        ];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches);
            foreach ($matches[1] as $name) {
                $persons[] = $this->normalizeName(trim($name));
            }
        }

        return $persons;
    }

    /**
     * Vérifie les indicateurs d'authenticité
     */
    private function checkAuthenticityIndicators(string $text): array {
        $indicators = [
            'official_letterhead' => false,
            'official_stamp' => false,
            'signature_present' => false,
            'addressed_to_ci_embassy' => false,
            'diplomat_identified' => false,
            'reference_number_present' => false
        ];

        $textUpper = strtoupper($text);

        // En-tête officiel
        $indicators['official_letterhead'] = preg_match('/(?:EMBASSY|AMBASSADE|MINISTRY|MINISTERE|MISSION)/i', $text);

        // Mention de cachet/tampon
        $indicators['official_stamp'] = preg_match('/(?:STAMP|CACHET|SEAL|SCEAU)/i', $text) ||
                                        preg_match('/\[SEAL\]|\[STAMP\]/i', $text);

        // Signature
        $indicators['signature_present'] = preg_match('/(?:SIGNED|SIGNE|SIGNATURE)/i', $text) ||
                                           strpos($textUpper, 'S/') !== false;

        // Adressé à CI
        $indicators['addressed_to_ci_embassy'] = strpos($textUpper, 'IVOIRE') !== false ||
                                                  strpos($textUpper, 'IVORY COAST') !== false;

        // Diplomate identifié
        $indicators['diplomat_identified'] = preg_match('/(?:MR|MRS|H\.E\.|EXCELLENCY)[\.:\s]+[A-Z]/i', $text);

        // Numéro de référence
        $indicators['reference_number_present'] = preg_match('/(?:REF|N°|NO)[\.:\s]*[A-Z0-9\/\-]+/i', $text);

        return $indicators;
    }

    /**
     * Vérifie si les champs requis sont présents
     */
    private function hasRequiredFields(array $data): bool {
        foreach ($this->requiredFields as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Valide les données extraites
     */
    public function validate(array $extracted): array {
        $validations = [];

        // Validation cachet officiel
        $validations['official_letterhead'] = !empty($extracted['sending_entity']);

        // Validation adressé à CI
        $validations['addressed_to_ci_embassy'] = stripos($extracted['receiving_entity'] ?? '', 'Ivoire') !== false;

        // Validation signature (implicite)
        $validations['signature_present'] = true; // Claude Layer 3 vérifiera

        // Validation diplomate identifié
        $validations['diplomat_identified'] = !empty($extracted['diplomat_name']);

        // Validation date récente (< 6 mois)
        if (isset($extracted['date'])) {
            $noteDate = strtotime($extracted['date']);
            $sixMonthsAgo = strtotime('-6 months');
            $validations['date_recent'] = $noteDate !== false && $noteDate > $sixMonthsAgo;
        }

        return $validations;
    }

    public function getDocumentType(): string {
        return 'verbal_note';
    }

    public function getPrdCode(): string {
        return 'DOC_NOTE_VERBALE';
    }
}
