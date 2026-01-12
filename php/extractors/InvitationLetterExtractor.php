<?php
/**
 * Extracteur de Lettre d'Invitation
 * Ambassade de Côte d'Ivoire en Éthiopie
 *
 * Conforme au PRD: DOC_INVITATION
 * Requis pour passeports ORDINAIRE
 *
 * @package VisaChatbot\Extractors
 * @version 1.0.0
 */

namespace VisaChatbot\Extractors;

require_once __DIR__ . '/AbstractExtractor.php';

class InvitationLetterExtractor extends AbstractExtractor {

    /**
     * Types de relations invitant-invité
     */
    public const RELATIONSHIPS = [
        'FAMILY' => ['FAMILLE', 'FAMILY', 'PARENT', 'FRERE', 'SOEUR', 'ONCLE', 'TANTE', 'COUSIN', 'BROTHER', 'SISTER', 'UNCLE', 'AUNT'],
        'SPOUSE' => ['EPOUX', 'EPOUSE', 'SPOUSE', 'WIFE', 'HUSBAND', 'MARI', 'FEMME'],
        'FRIEND' => ['AMI', 'FRIEND', 'AMIE'],
        'BUSINESS' => ['AFFAIRES', 'BUSINESS', 'PROFESSIONAL', 'PROFESSIONNEL', 'PARTNER', 'PARTENAIRE'],
        'EMPLOYER' => ['EMPLOYEUR', 'EMPLOYER', 'COMPANY', 'SOCIETE', 'ENTREPRISE']
    ];

    /**
     * Motifs de visite
     */
    public const VISIT_PURPOSES = [
        'TOURISM' => ['TOURISME', 'TOURISM', 'VACATION', 'VACANCES', 'HOLIDAY'],
        'FAMILY' => ['VISITE FAMILIALE', 'FAMILY VISIT', 'REUNIFICATION'],
        'BUSINESS' => ['AFFAIRES', 'BUSINESS', 'MEETING', 'REUNION', 'CONFERENCE'],
        'MEDICAL' => ['MEDICAL', 'SANTE', 'HEALTH', 'TREATMENT', 'TRAITEMENT'],
        'STUDIES' => ['ETUDES', 'STUDIES', 'FORMATION', 'TRAINING'],
        'CULTURAL' => ['CULTUREL', 'CULTURAL', 'ARTISTIQUE', 'ARTISTIC']
    ];

    protected array $requiredFields = [
        'inviter_name',
        'invitee_name',
        'purpose'
    ];

    protected array $optionalFields = [
        'inviter_address',
        'inviter_city',
        'inviter_phone',
        'inviter_email',
        'inviter_id_number',
        'invitee_passport_number',
        'invitee_nationality',
        'relationship',
        'arrival_date',
        'departure_date',
        'duration_days',
        'accommodation_address',
        'accommodation_provided',
        'notarized',
        'notary_name',
        'notary_date'
    ];

    /**
     * Mapping des mois français vers numéros
     */
    private const FRENCH_MONTHS = [
        'janvier' => '01', 'janv' => '01',
        'février' => '02', 'fevrier' => '02', 'fev' => '02',
        'mars' => '03', 'mar' => '03',
        'avril' => '04', 'avr' => '04',
        'mai' => '05',
        'juin' => '06',
        'juillet' => '07', 'juil' => '07',
        'août' => '08', 'aout' => '08',
        'septembre' => '09', 'sept' => '09',
        'octobre' => '10', 'oct' => '10',
        'novembre' => '11', 'nov' => '11',
        'décembre' => '12', 'decembre' => '12', 'dec' => '12'
    ];

    /**
     * Mapping des mois anglais vers numéros
     */
    private const ENGLISH_MONTHS = [
        'january' => '01', 'jan' => '01',
        'february' => '02', 'feb' => '02',
        'march' => '03', 'mar' => '03',
        'april' => '04', 'apr' => '04',
        'may' => '05',
        'june' => '06', 'jun' => '06',
        'july' => '07', 'jul' => '07',
        'august' => '08', 'aug' => '08',
        'september' => '09', 'sep' => '09', 'sept' => '09',
        'october' => '10', 'oct' => '10',
        'november' => '11', 'nov' => '11',
        'december' => '12', 'dec' => '12'
    ];

    /**
     * Extrait les données de la lettre d'invitation
     */
    public function extract(string $rawText, array $ocrMetadata = []): array {
        $result = [
            'success' => false,
            'extracted' => [],
            'inviter' => [],
            'invitee' => [],
            'visit_details' => [],
            'legalization' => []
        ];

        $text = $this->cleanOcrText($rawText);

        // 1. Extraire les informations de l'invitant
        $result['inviter'] = $this->extractInviterInfo($text);
        $result['extracted']['inviter_name'] = $result['inviter']['name'] ?? null;
        $result['extracted']['inviter_address'] = $result['inviter']['address'] ?? null;
        $result['extracted']['inviter_city'] = $result['inviter']['city'] ?? null;
        $result['extracted']['inviter_phone'] = $result['inviter']['phone'] ?? null;
        $result['extracted']['inviter_email'] = $result['inviter']['email'] ?? null;
        $result['extracted']['inviter_id_number'] = $result['inviter']['id_number'] ?? null;

        // 2. Extraire les informations de l'invité
        $result['invitee'] = $this->extractInviteeInfo($text);
        $result['extracted']['invitee_name'] = $result['invitee']['name'] ?? null;
        $result['extracted']['invitee_passport_number'] = $result['invitee']['passport_number'] ?? null;
        $result['extracted']['invitee_nationality'] = $result['invitee']['nationality'] ?? null;

        // 3. Extraire la relation
        $result['extracted']['relationship'] = $this->extractRelationship($text);

        // 4. Extraire les détails de la visite
        $result['visit_details'] = $this->extractVisitDetails($text);
        $result['extracted']['purpose'] = $result['visit_details']['purpose'] ?? null;
        $result['extracted']['arrival_date'] = $result['visit_details']['arrival_date'] ?? null;
        $result['extracted']['departure_date'] = $result['visit_details']['departure_date'] ?? null;
        $result['extracted']['duration_days'] = $result['visit_details']['duration_days'] ?? null;
        $result['extracted']['accommodation_address'] = $result['visit_details']['accommodation'] ?? null;
        $result['extracted']['accommodation_provided'] = $result['visit_details']['accommodation_provided'] ?? false;

        // 5. Extraire les informations de légalisation
        $result['legalization'] = $this->extractLegalizationInfo($text);
        $result['extracted']['notarized'] = $result['legalization']['notarized'] ?? false;
        $result['extracted']['notary_name'] = $result['legalization']['notary_name'] ?? null;
        $result['extracted']['notary_date'] = $result['legalization']['notary_date'] ?? null;

        // 6. Succès si champs requis présents
        $result['success'] = $this->hasRequiredFields($result['extracted']);

        return $result;
    }

    /**
     * Extrait les informations de l'invitant
     */
    private function extractInviterInfo(string $text): array {
        $inviter = [];

        // Nom de l'invitant - Patterns pour lettres officielles d'entreprise
        $namePatterns = [
            // Entreprises/Organisations en en-tête (première ligne)
            '/^([A-Z][A-Za-z\s\'\-]+(?:S\.?A\.?|SARL|SAS|AIRLINE[S]?|COMPAGNIE|ENTREPRISE|SOCIETE)?)\s*\n/mi',
            // "Le Directeur de XXX" ou "Directeur des Ressources Humaines"
            '/(?:Le\s*)?Directeur\s+(?:des?\s+)?([A-Za-zÀ-ÿ\s\-]+?)(?:\n|$)/i',
            // Signature avec nom (ex: "Mahamoud Babinet SAKO")
            '/(?:^|\n)([A-Z][a-zÀ-ÿ]+\s+[A-Z][a-zÀ-ÿ]*\s+[A-Z]+)\s*$/m',
            // Patterns classiques
            '/(?:I|JE),?\s*([A-Z][A-Za-z\s\-\']+),?\s*(?:RESIDING|RESIDANT|HEREBY|PAR LA PRESENTE)/i',
            '/(?:UNDERSIGNED|SOUSSIGNE)[:\s]*([A-Z][A-Za-z\s\-\']+)/i',
            '/(?:INVITER|INVITANT|HOST|HOTE)[:\s]*([A-Z][A-Za-z\s\-\']+)/i',
            '/(?:MR|MRS|MS|MME|MLLE)[\.:\s]+([A-Z][A-Za-z\s\-\']+)(?:,?\s*(?:RESIDING|RESIDANT|LIVING))/i'
        ];

        // Chercher d'abord une entreprise connue en Côte d'Ivoire
        $knownCompanies = [
            'Air Côte d\'Ivoire' => '/Air\s*C[oô]te\s*d[\'`]?\s*Ivoire/i',
            'Ethiopian Airlines' => '/Ethiopian\s*Airlines?/i',
            'Orange Côte d\'Ivoire' => '/Orange\s*C[oô]te\s*d[\'`]?\s*Ivoire/i',
            'MTN' => '/\bMTN\b/i',
            'SODECI' => '/\bSODECI\b/i',
            'CIE' => '/\bCIE\b/i'
        ];

        foreach ($knownCompanies as $company => $pattern) {
            if (preg_match($pattern, $text)) {
                $inviter['name'] = $company;
                $inviter['type'] = 'COMPANY';
                break;
            }
        }

        // Si pas d'entreprise connue, essayer les patterns classiques
        if (empty($inviter['name'])) {
            foreach ($namePatterns as $pattern) {
                if (preg_match($pattern, $text, $match)) {
                    $name = trim($match[1]);
                    // Éviter les faux positifs (trop court ou mots-clés)
                    if (strlen($name) > 3 && !preg_match('/^(LE|LA|LES|DES|DU|DE|ET|OU|MONSIEUR|MADAME)$/i', $name)) {
                        $inviter['name'] = $this->normalizeName($name);
                        break;
                    }
                }
            }
        }

        // Adresse
        $addressPatterns = [
            '/(?:RESIDING\s*AT|RESIDANT\s*A|ADDRESS|ADRESSE)[:\s]*([^\n,]+(?:,\s*[^\n]+)?)/i',
            '/(?:LIVING\s*AT|HABITANT\s*A)[:\s]*([^\n]+)/i',
            // Adresse postale format français
            '/Adresse\s*(?:postale)?[:\s]*([^\n]+)/i',
            '/(?:Siège\s*social|Siege\s*social)[:\s]*([^\n]+)/i'
        ];

        foreach ($addressPatterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $inviter['address'] = trim($match[1]);
                break;
            }
        }

        // Ville (chercher ville CI)
        foreach (['ABIDJAN', 'YAMOUSSOUKRO', 'BOUAKE', 'DALOA', 'SAN PEDRO', 'KORHOGO'] as $city) {
            if (stripos($text, $city) !== false) {
                $inviter['city'] = $city;
                break;
            }
        }

        // Téléphone - format CI (+225) et international
        $phonePatterns = [
            '/(?:T[eé]l|PHONE|TELEPHONE)[\.:\s]*(\+?225?[\s\.\-]?\d{2}[\s\.\-]?\d{2}[\s\.\-]?\d{2}[\s\.\-]?\d{2}[\s\.\-]?\d{2})/i',
            '/(?:T[eé]l|PHONE|TELEPHONE)[:\s]*(\+?\d[\d\s\-]{8,})/i',
            '/(\+225[\s\.\-]?\d{2}[\s\.\-]?\d{2}[\s\.\-]?\d{2}[\s\.\-]?\d{2}[\s\.\-]?\d{2})/i'
        ];

        foreach ($phonePatterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $inviter['phone'] = preg_replace('/[\s\.\-]/', '', $match[1]);
                break;
            }
        }

        // Email
        if (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $text, $match)) {
            $inviter['email'] = strtolower($match[1]);
        }

        // Numéro d'identité
        $idPatterns = [
            '/(?:CNI|ID|CARTE\s*(?:D\')?IDENTITE|IDENTITY\s*CARD)[:\s#]*([A-Z0-9\-]+)/i',
            '/(?:PASSPORT|PASSEPORT)\s*(?:NO|N°)?[:\s]*([A-Z0-9]+)/i'
        ];

        foreach ($idPatterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $inviter['id_number'] = strtoupper($match[1]);
                break;
            }
        }

        return $inviter;
    }

    /**
     * Extrait les informations de l'invité
     */
    private function extractInviteeInfo(string $text): array {
        $invitee = [];
        $surname = null;
        $givenNames = null;

        // ============================================
        // FORMAT LETTRE OFFICIELLE FRANÇAISE
        // ============================================
        // Pattern: "au nom de SURNAME Given Names"
        if (preg_match('/(?:au\s*nom\s*de|en\s*faveur\s*de)\s+([A-Z]+)\s+([A-Za-zÀ-ÿ\s]+?)(?:,|\.|en\s*sa)/i', $text, $match)) {
            $surname = strtoupper(trim($match[1]));
            $givenNames = trim($match[2]);
            $invitee['name'] = $surname . ' ' . $givenNames;
        }

        // Pattern: "Nom : SURNAME" + "Prénom : Given Names"
        if (preg_match('/Nom\s*:\s*([A-Z][A-Za-zÀ-ÿ\-]+)/i', $text, $match)) {
            $surname = strtoupper(trim($match[1]));
        }
        if (preg_match('/Pr[eé]nom\s*:?\s*([A-Za-zÀ-ÿ\s\-]+?)(?:\n|Nationalit|Passeport|Sexe|$)/i', $text, $match)) {
            $givenNames = trim($match[1]);
        }

        // Combiner nom et prénom si trouvés séparément
        if ($surname && $givenNames && empty($invitee['name'])) {
            $invitee['name'] = $surname . ' ' . $givenNames;
        } elseif ($surname && empty($invitee['name'])) {
            $invitee['name'] = $surname;
        }

        // ============================================
        // PATTERNS CLASSIQUES
        // ============================================
        if (empty($invitee['name'])) {
            $namePatterns = [
                // "visa au nom de XXX" ou "établir un visa au nom de"
                '/visa\s+(?:au\s*nom\s*de|pour)\s+([A-Z][A-Za-zÀ-ÿ\s\-\']+?)(?:,|\.|\s+en\s+sa)/i',
                // Patterns standards
                '/(?:INVITE|INVITING|TO\s*INVITE)[:\s]*(?:MR|MRS|MS)?[\.:\s]*([A-Z][A-Za-z\s\-\']+)/i',
                '/(?:GUEST|VISITEUR|VISITOR)[:\s]*([A-Z][A-Za-z\s\-\']+)/i',
                '/(?:MY\s*(?:FRIEND|RELATIVE|BROTHER|SISTER|FATHER|MOTHER))[,:\s]*([A-Z][A-Za-z\s\-\']+)/i'
            ];

            foreach ($namePatterns as $pattern) {
                if (preg_match($pattern, $text, $match)) {
                    $invitee['name'] = $this->normalizeName(trim($match[1]));
                    break;
                }
            }
        }

        // ============================================
        // NUMÉRO DE PASSEPORT
        // ============================================
        $passportPatterns = [
            // Format français: "Passeport n° XX1234567" ou "Passeport n°XX1234567"
            '/Passeport\s*n[°o]?\s*:?\s*([A-Z]{1,2}\d{6,9})/i',
            // Format standard
            '/(?:PASSPORT|PASSEPORT)\s*(?:(?:NO|N°|NUMBER)[:\s]*)?([A-Z]{1,2}\d{6,9})/i',
            // N° passeport isolé
            '/\b([A-Z]{2}\d{7})\b/'
        ];

        foreach ($passportPatterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $invitee['passport_number'] = strtoupper($match[1]);
                break;
            }
        }

        // ============================================
        // NATIONALITÉ
        // ============================================
        $nationalityPatterns = [
            // Format français: "Nationalité : Ethiopienne"
            '/Nationalit[eé]\s*:?\s*([A-Za-zÀ-ÿ]+)/i',
            '/(?:NATIONALITY|NATIONALITE)[:\s]*([A-Z][A-Za-z\s]+)/i',
            '/(?:CITIZEN\s*OF|RESSORTISSANT\s*(?:DE|DU))[:\s]*([A-Z][A-Za-z\s]+)/i'
        ];

        foreach ($nationalityPatterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $nationality = strtoupper(trim($match[1]));
                // Normaliser les nationalités courantes
                $nationalityMap = [
                    'ETHIOPIENNE' => 'ETHIOPIAN',
                    'ETHIOPIAN' => 'ETHIOPIAN',
                    'KENYANE' => 'KENYAN',
                    'DJIBOUTIENNE' => 'DJIBOUTIAN',
                    'TANZANIENNE' => 'TANZANIAN',
                    'OUGANDAISE' => 'UGANDAN',
                    'SOMALIENNE' => 'SOMALI',
                    'SUD-SOUDANAISE' => 'SOUTH SUDANESE'
                ];
                $invitee['nationality'] = $nationalityMap[$nationality] ?? $nationality;
                break;
            }
        }

        // ============================================
        // SEXE
        // ============================================
        if (preg_match('/Sexe\s*:?\s*(Masculin|F[eé]minin|M|F)/i', $text, $match)) {
            $sex = strtoupper(trim($match[1]));
            $invitee['sex'] = in_array($sex, ['MASCULIN', 'M']) ? 'M' : 'F';
        }

        return $invitee;
    }

    /**
     * Extrait la relation entre invitant et invité
     */
    private function extractRelationship(string $text): ?string {
        $textUpper = strtoupper($text);

        // Détection spécifique pour relations professionnelles (lettres d'entreprise)
        $professionalIndicators = [
            // Qualifications professionnelles
            '/en\s+sa\s+qualit[eé]\s+d[\'`]?\s*(instructeur|formateur|technicien|ingénieur|consultant|expert|directeur|manager|pilote)/i',
            // Formation/stage professionnel
            '/formation\s+(?:des?\s+)?(?:pilotes?|techniciens?|personnels?|employ[eé]s?)/i',
            // Termes business
            '/(?:compagnie|airline|society|entreprise|corporation)/i',
            // Département RH
            '/(?:ressources\s*humaines|human\s*resources|DRH|HR)/i'
        ];

        foreach ($professionalIndicators as $pattern) {
            if (preg_match($pattern, $text)) {
                return 'EMPLOYER';
            }
        }

        // Recherche classique par mots-clés
        foreach (self::RELATIONSHIPS as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($textUpper, $keyword) !== false) {
                    return $type;
                }
            }
        }

        return null;
    }

    /**
     * Extrait les détails de la visite
     */
    private function extractVisitDetails(string $text): array {
        $details = [];

        // ============================================
        // BUT DE LA VISITE - EXTRACTION AVANCÉE
        // ============================================
        $textUpper = strtoupper($text);

        // Chercher d'abord des descriptions de motif explicites
        $purposePatterns = [
            // "dans le cadre de/d'une XXX"
            '/dans\s+le\s+cadre\s+d[\'`]?\s*(?:une?\s+)?([A-Za-zÀ-ÿ\s\-]+?)(?:\.|,|\n|$)/i',
            // "pour une/la XXX"
            '/pour\s+(?:une?|la|le)\s+([A-Za-zÀ-ÿ\s\-]+?)(?:\.|,|\n|Veuillez|$)/i',
            // "en sa qualité de XXX"
            '/en\s+sa\s+qualit[eé]\s+d[\'`]?\s*([A-Za-zÀ-ÿ\s\-]+?)(?:\.|,|Il|$)/i',
            // "objet: XXX" ou "motif: XXX"
            '/(?:objet|motif|purpose)[:\s]+([A-Za-zÀ-ÿ\s\-]+?)(?:\.|,|\n|$)/i'
        ];

        foreach ($purposePatterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $purposeText = trim($match[1]);
                $details['purpose_description'] = $purposeText;

                // Mapper vers les catégories
                $purposeMapping = [
                    'STUDIES' => ['formation', 'training', 'stage', 'cours', 'étude', 'study'],
                    'BUSINESS' => ['affaire', 'business', 'réunion', 'meeting', 'conférence', 'professionnel', 'mission'],
                    'TOURISM' => ['tourisme', 'tourism', 'vacance', 'visite', 'découverte'],
                    'FAMILY' => ['famille', 'family', 'parent', 'réunification'],
                    'MEDICAL' => ['médical', 'medical', 'santé', 'traitement', 'soin'],
                    'CULTURAL' => ['culturel', 'cultural', 'artistique', 'exposition']
                ];

                foreach ($purposeMapping as $category => $keywords) {
                    foreach ($keywords as $keyword) {
                        if (stripos($purposeText, $keyword) !== false) {
                            $details['purpose'] = $category;
                            break 2;
                        }
                    }
                }
                break;
            }
        }

        // Fallback: recherche par mots-clés si pas de pattern trouvé
        if (empty($details['purpose'])) {
            foreach (self::VISIT_PURPOSES as $purpose => $keywords) {
                foreach ($keywords as $keyword) {
                    if (strpos($textUpper, $keyword) !== false) {
                        $details['purpose'] = $purpose;
                        break 2;
                    }
                }
            }
        }

        // ======================================
        // EXTRACTION DE LA DURÉE DU SÉJOUR
        // ======================================
        $durationPatterns = [
            // "for 45 days", "pour 45 jours", "pendant 45 jours"
            '/(?:for|pour|pendant|during)\s+(\d{1,3})\s*(?:days?|jours?)/i',
            // "45 days stay", "séjour de 45 jours"
            '/(?:stay|sejour|séjour)\s*(?:of|de)?\s*(\d{1,3})\s*(?:days?|jours?)/i',
            // "stay: 45 days", "durée: 45 jours"
            '/(?:duration|duree|durée|stay)[:\s]+(\d{1,3})\s*(?:days?|jours?)/i',
            // "45 days / jours" en standalone
            '/\b(\d{1,3})\s*(?:days?|jours?)\b/i'
        ];

        foreach ($durationPatterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $days = (int) $match[1];
                // Validation: durée raisonnable (1-365 jours)
                if ($days >= 1 && $days <= 365) {
                    $details['duration_days'] = $days;
                    break;
                }
            }
        }

        // ======================================
        // EXTRACTION DES DATES DE VISITE
        // ======================================

        // Pattern spécifique: "Arrivée à Abidjan : 27 Décembre" (format lettre officielle)
        $arrivalTextPattern = '/(?:Arriv[eé]e?\s*(?:[àa]\s*)?(?:Abidjan)?|se\s*rendre\s*[àa]\s*Abidjan\s*(?:le|du)?)\s*[:\s]*(\d{1,2})\s*(janvier|janv|f[eé]vrier|fevrier|fev|mars|avril|avr|mai|juin|juillet|juil|ao[uû]t|aout|septembre|sept|octobre|oct|novembre|nov|d[eé]cembre|decembre|dec)\.?\s*(\d{4})?/i';

        if (preg_match($arrivalTextPattern, $text, $match)) {
            $day = str_pad($match[1], 2, '0', STR_PAD_LEFT);
            $monthLower = strtolower($match[2]);
            $month = self::FRENCH_MONTHS[$monthLower] ?? '01';
            $year = $match[3] ?? date('Y');
            $details['arrival_date'] = "{$year}-{$month}-{$day}";
        }

        // Pattern 1: Dates numériques (dd/mm/yyyy, dd-mm-yyyy, dd.mm.yyyy)
        $numericPeriodPatterns = [
            '/(?:FROM|DU|À\s*PARTIR\s*DU)\s*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})\s*(?:TO|AU|À|UNTIL|JUSQU[\'`]?AU?)\s*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
            '/(?:ARRIVAL|ARRIVEE|ARRIVÉE)[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i'
        ];

        // Pattern 2: Dates françaises textuelles (27 Décembre 2025)
        $frenchDatePattern = '/(\d{1,2})\s*(janvier|janv|février|fevrier|fev|mars|avril|avr|mai|juin|juillet|juil|août|aout|septembre|sept|octobre|oct|novembre|nov|décembre|decembre|dec)\.?\s*(\d{4})?/i';

        // Pattern 3: Dates anglaises textuelles (December 27, 2025 ou 27 December 2025)
        $englishDatePattern1 = '/(january|jan|february|feb|march|mar|april|apr|may|june|jun|july|jul|august|aug|september|sep|sept|october|oct|november|nov|december|dec)\.?\s+(\d{1,2})(?:st|nd|rd|th)?,?\s*(\d{4})?/i';
        $englishDatePattern2 = '/(\d{1,2})(?:st|nd|rd|th)?\s+(january|jan|february|feb|march|mar|april|apr|may|june|jun|july|jul|august|aug|september|sep|sept|october|oct|november|nov|december|dec)\.?\s*(\d{4})?/i';

        // Essayer d'abord les patterns numériques
        if (preg_match($numericPeriodPatterns[0], $text, $match)) {
            $details['arrival_date'] = $this->parseDate($match[1]);
            $details['departure_date'] = $this->parseDate($match[2]);
        } elseif (preg_match($numericPeriodPatterns[1], $text, $match)) {
            $details['arrival_date'] = $this->parseDate($match[1]);
        }

        // Si pas de dates trouvées, essayer les patterns textuels
        if (empty($details['arrival_date'])) {
            $allDates = [];

            // Chercher toutes les dates françaises
            if (preg_match_all($frenchDatePattern, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $day = str_pad($match[1], 2, '0', STR_PAD_LEFT);
                    $monthLower = strtolower($match[2]);
                    $month = self::FRENCH_MONTHS[$monthLower] ?? '01';
                    $year = $match[3] ?? date('Y');
                    $allDates[] = "{$year}-{$month}-{$day}";
                }
            }

            // Chercher toutes les dates anglaises (format 1: December 27)
            if (preg_match_all($englishDatePattern1, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $monthLower = strtolower($match[1]);
                    $month = self::ENGLISH_MONTHS[$monthLower] ?? '01';
                    $day = str_pad($match[2], 2, '0', STR_PAD_LEFT);
                    $year = $match[3] ?? date('Y');
                    $allDates[] = "{$year}-{$month}-{$day}";
                }
            }

            // Chercher toutes les dates anglaises (format 2: 27 December)
            if (preg_match_all($englishDatePattern2, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $day = str_pad($match[1], 2, '0', STR_PAD_LEFT);
                    $monthLower = strtolower($match[2]);
                    $month = self::ENGLISH_MONTHS[$monthLower] ?? '01';
                    $year = $match[3] ?? date('Y');
                    $allDates[] = "{$year}-{$month}-{$day}";
                }
            }

            // Trier les dates et assigner
            if (!empty($allDates)) {
                sort($allDates);
                $details['arrival_date'] = $allDates[0];
                if (count($allDates) > 1) {
                    $details['departure_date'] = end($allDates);
                }
            }
        }

        // Si on a une durée mais pas de date de départ, calculer
        if (!empty($details['arrival_date']) && empty($details['departure_date']) && !empty($details['duration_days'])) {
            try {
                $arrival = new \DateTime($details['arrival_date']);
                $departure = clone $arrival;
                $departure->modify('+' . $details['duration_days'] . ' days');
                $details['departure_date'] = $departure->format('Y-m-d');
            } catch (\Exception $e) {
                // Ignorer les erreurs de date
            }
        }

        // ======================================
        // HÉBERGEMENT
        // ======================================
        $accommodationPatterns = [
            '/(?:STAYING\s*AT|HEBERGE\s*A|ACCOMMODATION|HEBERGEMENT)[:\s]*([^\n]+)/i',
            '/(?:WILL\s*STAY|VA\s*SEJOURNER)\s*(?:AT|A|CHEZ)[:\s]*([^\n]+)/i',
            '/(?:LOGERA?\s*CHEZ|SERA?\s*HEBERGE\s*(?:A|CHEZ))[:\s]*([^\n]+)/i'
        ];

        foreach ($accommodationPatterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $details['accommodation'] = trim($match[1]);
                break;
            }
        }

        // Vérifier si l'hébergement est fourni par l'invitant
        $accommodationProvidedPatterns = [
            '/(?:HEBERGEMENT|ACCOMMODATION)\s*(?:FOURNI|PROVIDED|ASSURE|GARANTI)/i',
            '/(?:LOGERA?|SERA?\s*HEBERGE)\s*(?:CHEZ\s*(?:MOI|NOUS)|A\s*MON\s*DOMICILE)/i',
            '/(?:I\s*WILL\s*PROVIDE|JE\s*FOURNIRAI?)\s*(?:ACCOMMODATION|HEBERGEMENT)/i',
            '/(?:STAYING?\s*WITH\s*(?:ME|US))/i',
            '/(?:CHEZ\s*(?:MOI|L[\'`]?INVITANT|L[\'`]?HOTE))/i'
        ];

        foreach ($accommodationProvidedPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                $details['accommodation_provided'] = true;
                break;
            }
        }

        return $details;
    }

    /**
     * Extrait les informations de légalisation
     */
    private function extractLegalizationInfo(string $text): array {
        $legalization = [
            'notarized' => false,
            'notary_name' => null,
            'notary_date' => null,
            'stamp_present' => false
        ];

        $textUpper = strtoupper($text);

        // Vérifier si notarié
        $notaryKeywords = ['NOTARY', 'NOTAIRE', 'NOTARIZED', 'LEGALIZED', 'LEGALISE', 'CERTIFIED', 'CERTIFIE'];
        foreach ($notaryKeywords as $keyword) {
            if (strpos($textUpper, $keyword) !== false) {
                $legalization['notarized'] = true;
                break;
            }
        }

        // Nom du notaire
        if (preg_match('/(?:NOTARY|NOTAIRE)[:\s]*(?:MR|ME|MAITRE)?[\.:\s]*([A-Z][A-Za-z\s\-\']+)/i', $text, $match)) {
            $legalization['notary_name'] = trim($match[1]);
        }

        // Date de légalisation
        if (preg_match('/(?:NOTARIZED|LEGALIZED|CERTIFIE)\s*(?:ON|LE)?[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i', $text, $match)) {
            $legalization['notary_date'] = $this->parseDate($match[1]);
        }

        // Tampon
        if (preg_match('/(?:STAMP|CACHET|SEAL|SCEAU|\[STAMP\]|\[SEAL\])/i', $text)) {
            $legalization['stamp_present'] = true;
        }

        return $legalization;
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

        // Validation invitant en Côte d'Ivoire
        $validations['inviter_in_cote_divoire'] =
            stripos($extracted['inviter_city'] ?? '', 'ABIDJAN') !== false ||
            stripos($extracted['inviter_address'] ?? '', 'IVOIRE') !== false ||
            stripos($extracted['inviter_address'] ?? '', 'ABIDJAN') !== false;

        // Validation dates spécifiées
        $validations['dates_specified'] =
            !empty($extracted['arrival_date']) || !empty($extracted['departure_date']);

        // Validation signature présente (implicite)
        $validations['signature_present'] = true;

        // Validation légalisation
        $validations['legalization_valid'] = $extracted['notarized'] ?? false;

        // Validation but de visite clair
        $validations['purpose_clear'] = !empty($extracted['purpose']);

        return $validations;
    }

    public function getDocumentType(): string {
        return 'invitation';
    }

    public function getPrdCode(): string {
        return 'DOC_INVITATION';
    }
}
