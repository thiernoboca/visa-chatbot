<?php
/**
 * Classe abstraite pour tous les validateurs Claude Layer 3
 * Ambassade de Côte d'Ivoire en Éthiopie
 *
 * Layer 3: Validation asynchrone avec Claude Sonnet
 * - Détection de fraude
 * - Cohérence cross-documents
 * - Alertes agents
 *
 * @package VisaChatbot\Validators
 * @version 1.0.0
 */

namespace VisaChatbot\Validators;

use Exception;

abstract class AbstractValidator {

    /**
     * Niveaux de risque
     */
    public const RISK_LEVELS = [
        'LOW' => 0,
        'MEDIUM' => 1,
        'HIGH' => 2,
        'CRITICAL' => 3
    ];

    /**
     * Seuils de confiance
     */
    public const CONFIDENCE_THRESHOLDS = [
        'AUTO_APPROVE' => 0.95,
        'MANUAL_REVIEW' => 0.75,
        'AUTO_REJECT' => 0.50
    ];

    /**
     * Configuration
     */
    protected array $config = [];

    /**
     * Alertes générées
     */
    protected array $alerts = [];

    /**
     * Constructeur
     */
    public function __construct(array $config = []) {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Configuration par défaut
     */
    protected function getDefaultConfig(): array {
        return [
            'debug' => false,
            'strict_mode' => false,
            'generate_alerts' => true,
            'fraud_detection' => true,
            // Tolérance de dates
            'date_tolerance_days' => 2,           // Tolérance par défaut: 2 jours
            'hotel_checkin_tolerance_days' => 2,  // Tolérance hôtel check-in vs vol
            'payment_validity_days' => 30,        // Validité du paiement en jours
            // Seuils de validation
            'name_similarity_threshold' => 0.80,  // Seuil de similarité des noms
            'use_claude_validation' => false      // Validation Claude Layer 3
        ];
    }

    /**
     * Valide les données extraites avec Claude
     *
     * @param array $extractedData Données extraites des documents
     * @param array $applicationContext Contexte de la demande de visa
     * @return array Résultat de validation
     */
    abstract public function validate(array $extractedData, array $applicationContext = []): array;

    /**
     * Détecte les indicateurs de fraude potentielle
     *
     * @param array $extractedData Données à analyser
     * @return array Indicateurs de fraude
     */
    abstract public function detectFraud(array $extractedData): array;

    /**
     * Retourne le type de document validé
     */
    abstract public function getDocumentType(): string;

    /**
     * Génère le prompt de validation pour Claude
     *
     * @param array $extractedData Données extraites
     * @param array $context Contexte additionnel
     * @return string Prompt formaté
     */
    protected function generateValidationPrompt(array $extractedData, array $context = []): string {
        $dataJson = json_encode($extractedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $contextJson = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Tu es un expert en validation de documents pour visa à l'Ambassade de Côte d'Ivoire.

DONNÉES EXTRAITES:
{$dataJson}

CONTEXTE DE LA DEMANDE:
{$contextJson}

INSTRUCTIONS:
1. Valide la cohérence des données
2. Détecte les anomalies potentielles
3. Évalue le risque de fraude
4. Suggère des actions

RETOURNE UN JSON STRICT:
{
  "valid": true/false,
  "confidence": 0.0-1.0,
  "fraud_indicators": [],
  "anomalies": [],
  "recommendations": [],
  "risk_level": "LOW|MEDIUM|HIGH|CRITICAL",
  "requires_manual_review": true/false,
  "review_reasons": []
}
PROMPT;
    }

    /**
     * Ajoute une alerte
     */
    protected function addAlert(string $type, string $message, string $severity = 'INFO', array $data = []): void {
        $this->alerts[] = [
            'type' => $type,
            'message' => $message,
            'severity' => $severity,
            'data' => $data,
            'timestamp' => date('c')
        ];
    }

    /**
     * Retourne les alertes générées
     */
    public function getAlerts(): array {
        return $this->alerts;
    }

    /**
     * Efface les alertes
     */
    public function clearAlerts(): void {
        $this->alerts = [];
    }

    /**
     * Calcule le score de risque global
     */
    protected function calculateRiskScore(array $fraudIndicators, array $anomalies): float {
        $score = 0.0;

        // Pondération des indicateurs de fraude
        foreach ($fraudIndicators as $indicator) {
            $weight = $indicator['weight'] ?? 1;
            $score += 0.15 * $weight;
        }

        // Pondération des anomalies
        foreach ($anomalies as $anomaly) {
            $weight = $anomaly['weight'] ?? 0.5;
            $score += 0.1 * $weight;
        }

        return min(1.0, $score);
    }

    /**
     * Détermine le niveau de risque
     */
    protected function determineRiskLevel(float $riskScore): string {
        if ($riskScore >= 0.75) return 'CRITICAL';
        if ($riskScore >= 0.5) return 'HIGH';
        if ($riskScore >= 0.25) return 'MEDIUM';
        return 'LOW';
    }

    /**
     * Vérifie la cohérence des noms entre documents
     *
     * @param array $names Noms collectés par document
     * @return array Résultat avec consistent, variations, confidence
     */
    protected function validateNameConsistency(array $names): array {
        $result = [
            'consistent' => true,
            'variations' => [],
            'confidence' => 1.0,
            'details' => []
        ];

        if (count($names) < 2) {
            return $result;
        }

        // Récupérer le seuil configurable
        $threshold = $this->config['name_similarity_threshold'] ?? 0.80;

        $normalized = [];
        foreach ($names as $source => $name) {
            $normalized[$source] = $this->normalizeName($name);
        }

        $uniqueNormalized = array_unique(array_values($normalized));

        // Si tous les noms normalisés sont identiques
        if (count($uniqueNormalized) === 1) {
            $result['confidence'] = 1.0;
            return $result;
        }

        // Calculer les similarités entre toutes les paires
        $similarities = [];
        $sources = array_keys($normalized);
        $minSimilarity = 1.0;

        for ($i = 0; $i < count($sources) - 1; $i++) {
            for ($j = $i + 1; $j < count($sources); $j++) {
                $source1 = $sources[$i];
                $source2 = $sources[$j];
                $similarity = $this->stringSimilarity($normalized[$source1], $normalized[$source2]);
                $similarities[] = $similarity;

                $result['details'][] = [
                    'source1' => $source1,
                    'source2' => $source2,
                    'name1' => $normalized[$source1],
                    'name2' => $normalized[$source2],
                    'similarity' => round($similarity, 3),
                    'matches' => $similarity >= $threshold
                ];

                $minSimilarity = min($minSimilarity, $similarity);
            }
        }

        // Déterminer la cohérence basée sur la similarité minimale
        $avgSimilarity = array_sum($similarities) / count($similarities);
        $result['confidence'] = round($avgSimilarity, 3);
        $result['min_similarity'] = round($minSimilarity, 3);
        $result['threshold'] = $threshold;

        // Cohérent si la similarité minimale est au-dessus du seuil
        if ($minSimilarity >= $threshold) {
            $result['consistent'] = true;
        } else {
            $result['consistent'] = false;
            $result['variations'] = $uniqueNormalized;
        }

        return $result;
    }

    /**
     * Vérifie la cohérence des dates
     *
     * @param array $dates Dates collectées des documents
     * @return array Résultat de validation avec issues
     */
    protected function validateDateConsistency(array $dates): array {
        $result = [
            'consistent' => true,
            'issues' => [],
            'warnings' => []
        ];

        // Récupérer les tolérances configurées
        $hotelTolerance = $this->config['hotel_checkin_tolerance_days'] ?? 2;
        $dateTolerance = $this->config['date_tolerance_days'] ?? 2;

        // Vérifier les dates de voyage vs dates d'expiration
        if (isset($dates['travel_date']) && isset($dates['passport_expiry'])) {
            $travel = strtotime($dates['travel_date']);
            $expiry = strtotime($dates['passport_expiry']);

            if ($travel === false || $expiry === false) {
                $result['warnings'][] = 'Could not parse travel or passport expiry date';
            } else {
                if ($travel > $expiry) {
                    $result['consistent'] = false;
                    $result['issues'][] = 'Passport expires before travel date';
                }

                // +6 mois règle (règle stricte pour visas)
                $sixMonthsBeforeExpiry = strtotime($dates['passport_expiry'] . ' -6 months');
                if ($travel > $sixMonthsBeforeExpiry) {
                    $result['issues'][] = 'Passport validity less than 6 months from travel';
                }
            }
        }

        // Vérifier dates hôtel vs dates vol (avec tolérance configurable)
        if (isset($dates['check_in']) && isset($dates['arrival'])) {
            $checkIn = strtotime($dates['check_in']);
            $arrival = strtotime($dates['arrival']);

            if ($checkIn === false || $arrival === false) {
                $result['warnings'][] = 'Could not parse check-in or arrival date';
            } else {
                $diffDays = abs($checkIn - $arrival) / 86400;

                if ($diffDays > $hotelTolerance) {
                    // Écart significatif
                    $result['issues'][] = sprintf(
                        "Hotel check-in (%s) is %d days from flight arrival (%s) - tolerance is %d days",
                        $dates['check_in'],
                        (int)$diffDays,
                        $dates['arrival'],
                        $hotelTolerance
                    );
                } elseif ($diffDays > 0 && $diffDays <= $hotelTolerance) {
                    // Dans la tolérance mais signalé en warning
                    $result['warnings'][] = sprintf(
                        "Hotel check-in (%s) is %d day(s) from flight arrival (%s) - within tolerance",
                        $dates['check_in'],
                        (int)$diffDays,
                        $dates['arrival']
                    );
                }
            }
        }

        // Vérifier cohérence check-out vs check-in
        if (isset($dates['check_in']) && isset($dates['check_out'])) {
            $checkIn = strtotime($dates['check_in']);
            $checkOut = strtotime($dates['check_out']);

            if ($checkIn && $checkOut && $checkOut < $checkIn) {
                $result['consistent'] = false;
                $result['issues'][] = 'Hotel check-out date is before check-in date';
            }
        }

        return $result;
    }

    /**
     * Normalise un nom pour comparaison
     */
    protected function normalizeName(string $name): string {
        $transliteration = [
            'À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A','Æ'=>'AE',
            'Ç'=>'C','È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E',
            'Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I','Ñ'=>'N',
            'Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O',
            'Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U','Ý'=>'Y'
        ];

        $name = strtr($name, $transliteration);
        $name = strtoupper($name);
        // Convertir les séparateurs de noms en espaces (/, -, _)
        $name = preg_replace('/[\/\-_]/', ' ', $name);
        // Supprimer tous les autres caractères non-alphabétiques
        $name = preg_replace('/[^A-Z\s]/', '', $name);
        // Normaliser les espaces
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }

    /**
     * Calcule la similarité entre deux chaînes de noms
     * Supporte les correspondances partielles et les variations d'ordre
     */
    protected function stringSimilarity(string $str1, string $str2): float {
        if ($str1 === $str2) return 1.0;

        // Comparer les versions sans espaces aussi
        $noSpace1 = str_replace(' ', '', $str1);
        $noSpace2 = str_replace(' ', '', $str2);
        if ($noSpace1 === $noSpace2) return 0.98;

        // Diviser les noms en parties
        $parts1 = array_filter(explode(' ', $str1));
        $parts2 = array_filter(explode(' ', $str2));

        if (empty($parts1) || empty($parts2)) {
            return 0.0;
        }

        // Vérifier si tous les éléments du plus court sont dans le plus long
        $shorter = count($parts1) <= count($parts2) ? $parts1 : $parts2;
        $longer = count($parts1) > count($parts2) ? $parts1 : $parts2;

        $matchCount = 0;
        $usedIndices = [];

        foreach ($shorter as $part) {
            $found = false;
            // D'abord essayer correspondance mot à mot
            foreach ($longer as $idx => $longerPart) {
                if (!in_array($idx, $usedIndices) && $this->wordSimilar($part, $longerPart)) {
                    $matchCount++;
                    $usedIndices[] = $idx;
                    $found = true;
                    break;
                }
            }

            // Sinon essayer correspondance par sous-chaîne (pour noms concaténés)
            if (!$found && strlen($part) >= 4) {
                $longerConcat = implode('', $longer);
                if (strpos($longerConcat, $part) !== false || strpos($part, $longerConcat) !== false) {
                    $matchCount++;
                    $found = true;
                }
                // Ou essayer correspondance par combinaisons de mots adjacents
                if (!$found) {
                    for ($i = 0; $i < count($longer) - 1; $i++) {
                        $combined = $longer[$i] . $longer[$i + 1];
                        if ($this->wordSimilar($part, $combined)) {
                            $matchCount++;
                            $found = true;
                            break;
                        }
                    }
                }
            }
        }

        // Score basé sur le nombre de correspondances
        $matchRatio = $matchCount / count($shorter);

        // Si tous les éléments du nom court sont dans le nom long, c'est une correspondance
        if ($matchRatio >= 0.99) {
            return 0.95 + (0.05 * (count($shorter) / count($longer)));
        }

        // Similarité par Levenshtein sur les versions sans espaces
        $maxLen = max(strlen($noSpace1), strlen($noSpace2));
        if ($maxLen === 0) return 1.0;
        $distance = levenshtein($noSpace1, $noSpace2);
        $noSpaceSimilarity = 1 - ($distance / $maxLen);

        // Prendre le meilleur score
        return max($matchRatio * 0.9, $noSpaceSimilarity);
    }

    /**
     * Vérifie si deux mots sont similaires
     */
    protected function wordSimilar(string $word1, string $word2): bool {
        if ($word1 === $word2) return true;

        // Tolérer 1-2 caractères de différence pour les mots courts
        $distance = levenshtein($word1, $word2);
        $maxLen = max(strlen($word1), strlen($word2));

        if ($maxLen <= 3) {
            return $distance <= 1;
        }

        return ($distance / $maxLen) <= 0.2;  // Max 20% de différence
    }

    /**
     * Log de debug
     */
    protected function log(string $message, array $context = []): void {
        if ($this->config['debug'] ?? false) {
            error_log("[" . get_class($this) . "] {$message} " . json_encode($context));
        }
    }
}
