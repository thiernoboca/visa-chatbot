<?php
/**
 * Claude Diplomatic Validator - Layer 3 Supervisor
 * Validation spécialisée pour les cas diplomatiques
 * 
 * Ce module implémente la validation approfondie Claude (Layer 3) pour:
 * - Passeports diplomatiques/service
 * - Laissez-passer ONU/UA
 * - Notes verbales
 * - Cohérence des données critiques
 * 
 * @package VisaChatbot
 * @version 1.0.0
 */

require_once __DIR__ . '/config.php';

class ClaudeDiplomaticValidator {
    
    /**
     * Clé API Claude
     */
    private string $apiKey;
    
    /**
     * Modèle Claude à utiliser
     */
    private string $model;
    
    /**
     * Configuration
     */
    private array $config;
    
    /**
     * Règles de validation par type de passeport
     */
    private const VALIDATION_RULES = [
        'DIPLOMATIQUE' => [
            'required_checks' => [
                'mrz_type_code' => 'PD', // Prefix MRZ pour diplomatique
                'verbal_note_required' => true,
                'holder_status_verification' => true,
                'ministry_validation' => true
            ],
            'severity' => 'high',
            'manual_review_threshold' => 0.8
        ],
        'SERVICE' => [
            'required_checks' => [
                'mrz_type_code' => 'PS',
                'verbal_note_required' => true,
                'holder_status_verification' => true,
                'ministry_validation' => true
            ],
            'severity' => 'high',
            'manual_review_threshold' => 0.8
        ],
        'LP_ONU' => [
            'required_checks' => [
                'un_affiliation' => true,
                'verbal_note_required' => true,
                'mission_verification' => true
            ],
            'severity' => 'critical',
            'manual_review_threshold' => 0.9
        ],
        'LP_UA' => [
            'required_checks' => [
                'au_affiliation' => true,
                'verbal_note_required' => true,
                'mission_verification' => true
            ],
            'severity' => 'critical',
            'manual_review_threshold' => 0.9
        ]
    ];
    
    /**
     * Constructeur
     */
    public function __construct(array $options = []) {
        $this->apiKey = $options['api_key'] ?? getenv('CLAUDE_API_KEY') ?? '';
        $this->model = $options['model'] ?? 'claude-3-5-haiku-20241022';
        
        $this->config = [
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'timeout' => $options['timeout'] ?? 60,
            'debug' => $options['debug'] ?? (defined('DEBUG_MODE') && DEBUG_MODE)
        ];
        
        if (empty($this->apiKey)) {
            throw new Exception('Clé API Claude non configurée pour le validateur diplomatique');
        }
    }
    
    /**
     * Valide un passeport diplomatique complet
     * 
     * @param array $passportData Données extraites du passeport
     * @param array $verbalNoteData Données de la note verbale (optionnel)
     * @param array $sessionContext Contexte de la session
     * @return array Résultat de validation détaillé
     */
    public function validateDiplomaticPassport(
        array $passportData, 
        ?array $verbalNoteData = null, 
        array $sessionContext = []
    ): array {
        $startTime = microtime(true);
        
        // Détecter le type de passeport
        $passportType = $this->detectPassportType($passportData);
        
        if (!isset(self::VALIDATION_RULES[$passportType])) {
            return [
                'valid' => true,
                'passport_type' => $passportType,
                'is_diplomatic' => false,
                'validation_skipped' => true,
                'reason' => 'Passeport ordinaire - validation diplomatique non requise'
            ];
        }
        
        $rules = self::VALIDATION_RULES[$passportType];
        
        // Construire le prompt de validation
        $prompt = $this->buildDiplomaticValidationPrompt(
            $passportData,
            $verbalNoteData,
            $passportType,
            $rules,
            $sessionContext
        );
        
        // Appeler Claude pour la validation
        $claudeResponse = $this->callClaude($prompt);
        
        // Parser la réponse
        $validation = $this->parseValidationResponse($claudeResponse);
        
        // Enrichir avec les métadonnées
        $validation['_metadata'] = [
            'passport_type' => $passportType,
            'validation_rules' => $rules,
            'processing_time' => round(microtime(true) - $startTime, 3),
            'model' => $this->model,
            'timestamp' => date('c'),
            'validator' => 'claude_layer3'
        ];
        
        // Déterminer si une revue manuelle est nécessaire
        $validation['needs_manual_review'] = 
            ($validation['confidence_score'] ?? 1) < $rules['manual_review_threshold'];
        
        $this->log("Validation diplomatique complétée: " . ($validation['valid'] ? 'VALIDE' : 'INVALIDE'));
        
        return $validation;
    }
    
    /**
     * Valide la cohérence entre le passeport et la note verbale
     */
    public function validatePassportVerbalNoteCoherence(
        array $passportData,
        array $verbalNoteData
    ): array {
        $prompt = <<<PROMPT
Tu es un superviseur de qualité pour les demandes de visa diplomatiques à l'Ambassade de Côte d'Ivoire.

TÂCHE: Valider la cohérence entre un passeport diplomatique et sa note verbale.

## DONNÉES DU PASSEPORT:
```json
{json_encode($passportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)}
```

## DONNÉES DE LA NOTE VERBALE:
```json
{json_encode($verbalNoteData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)}
```

## VÉRIFICATIONS REQUISES:
1. Le nom sur le passeport correspond-il à un nom dans la note verbale?
2. Le numéro de passeport mentionné dans la note verbale (si présent) correspond-il?
3. Le titre/fonction mentionné est-il cohérent avec le type de passeport?
4. Les dates de voyage mentionnées sont-elles cohérentes?
5. Le ministère émetteur est-il cohérent avec la nationalité du passeport?

## ANOMALIES À DÉTECTER:
- Discordances de noms ou numéros
- Note verbale expirée ou antérieure
- Ministère émetteur invalide
- Personnes non mentionnées dans la note

Retourne UNIQUEMENT un JSON valide avec cette structure:
{
  "coherent": true,
  "confidence_score": 0.95,
  "name_match": {
    "match": true,
    "passport_name": "NOM PRÉNOM",
    "verbal_note_name": "NOM PRÉNOM",
    "similarity_score": 0.98
  },
  "passport_number_match": {
    "verified": true,
    "matches": true
  },
  "title_coherence": {
    "valid": true,
    "passport_type": "DIPLOMATIQUE",
    "mentioned_title": "TITRE"
  },
  "date_coherence": {
    "valid": true,
    "issues": []
  },
  "ministry_valid": true,
  "anomalies": [],
  "warnings": [],
  "recommendation": "approved|manual_review|rejected",
  "rejection_reason": null
}
PROMPT;
        
        $response = $this->callClaude($prompt);
        return $this->parseValidationResponse($response);
    }
    
    /**
     * Valide une note verbale isolée
     */
    public function validateVerbalNote(array $verbalNoteData): array {
        $dataJson = json_encode($verbalNoteData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $prompt = <<<PROMPT
Tu es un expert en protocole diplomatique à l'Ambassade de Côte d'Ivoire.

TÂCHE: Valider l'authenticité et la conformité d'une note verbale.

## DONNÉES EXTRAITES:
```json
{$dataJson}
```

## CRITÈRES DE VALIDATION:
1. **Format officiel**: La note provient-elle d'un ministère des Affaires étrangères?
2. **Référence valide**: La note a-t-elle un numéro de référence?
3. **Date de validité**: La note est-elle récente (< 6 mois)?
4. **Personnes identifiées**: Au moins une personne est-elle clairement identifiée?
5. **Objet clair**: Le but du voyage est-il mentionné?
6. **Destinataire correct**: L'Ambassade de Côte d'Ivoire est-elle bien le destinataire?

## SIGNAUX D'ALERTE (Red Flags):
- Pas d'en-tête ministériel
- Référence manquante
- Date absente ou invalide
- Formulation non-diplomatique
- Erreurs grammaticales importantes

Retourne UNIQUEMENT un JSON valide:
{
  "valid": true,
  "confidence_score": 0.90,
  "format_check": {
    "has_official_header": true,
    "has_reference": true,
    "has_date": true,
    "date_valid": true,
    "date_value": "YYYY-MM-DD"
  },
  "content_check": {
    "persons_identified": true,
    "person_count": 1,
    "purpose_stated": true,
    "purpose": "OBJET",
    "recipient_correct": true
  },
  "red_flags": [],
  "warnings": [],
  "recommendation": "approved|manual_review|rejected",
  "notes": "Observations supplémentaires"
}
PROMPT;
        
        $response = $this->callClaude($prompt);
        return $this->parseValidationResponse($response);
    }
    
    /**
     * Validation batch pour un dossier diplomatique complet
     */
    public function validateCompleteDiplomaticDossier(array $documents): array {
        $validations = [];
        $overallScore = 0;
        $criticalIssues = [];
        
        // Valider chaque document individuellement
        if (isset($documents['passport'])) {
            $validations['passport'] = $this->validateDiplomaticPassport(
                $documents['passport'],
                $documents['verbal_note'] ?? null
            );
            $overallScore += $validations['passport']['confidence_score'] ?? 0;
        }
        
        if (isset($documents['verbal_note'])) {
            $validations['verbal_note'] = $this->validateVerbalNote($documents['verbal_note']);
            $overallScore += $validations['verbal_note']['confidence_score'] ?? 0;
        }
        
        // Valider la cohérence si les deux documents sont présents
        if (isset($documents['passport']) && isset($documents['verbal_note'])) {
            $validations['coherence'] = $this->validatePassportVerbalNoteCoherence(
                $documents['passport'],
                $documents['verbal_note']
            );
            $overallScore += $validations['coherence']['confidence_score'] ?? 0;
        }
        
        // Calculer le score moyen
        $validationCount = count($validations);
        $averageScore = $validationCount > 0 ? $overallScore / $validationCount : 0;
        
        // Collecter les issues critiques
        foreach ($validations as $type => $validation) {
            if (isset($validation['anomalies']) && !empty($validation['anomalies'])) {
                foreach ($validation['anomalies'] as $anomaly) {
                    if (($anomaly['severity'] ?? 'low') === 'critical') {
                        $criticalIssues[] = [
                            'document' => $type,
                            'issue' => $anomaly
                        ];
                    }
                }
            }
        }
        
        // Déterminer la recommandation globale
        $recommendation = 'approved';
        if (!empty($criticalIssues)) {
            $recommendation = 'rejected';
        } elseif ($averageScore < 0.8) {
            $recommendation = 'manual_review';
        }
        
        return [
            'overall_valid' => empty($criticalIssues) && $averageScore >= 0.7,
            'overall_score' => round($averageScore, 2),
            'validations' => $validations,
            'critical_issues' => $criticalIssues,
            'recommendation' => $recommendation,
            'requires_manual_review' => $recommendation === 'manual_review',
            '_metadata' => [
                'timestamp' => date('c'),
                'validator' => 'claude_layer3_batch',
                'documents_validated' => array_keys($validations)
            ]
        ];
    }
    
    /**
     * Détecte le type de passeport
     */
    private function detectPassportType(array $passportData): string {
        $possibleFields = [
            $passportData['fields']['passport_type']['value'] ?? null,
            $passportData['passport_type'] ?? null,
            $passportData['type'] ?? null
        ];
        
        foreach ($possibleFields as $value) {
            if (!$value) continue;
            
            $value = strtoupper($value);
            
            if (preg_match('/DIPLOM|DIPLOMAT/i', $value)) return 'DIPLOMATIQUE';
            if (preg_match('/SERVICE/i', $value)) return 'SERVICE';
            if (preg_match('/ONU|UN|UNITED NATIONS/i', $value)) return 'LP_ONU';
            if (preg_match('/UA|AU|AFRICAN UNION|UNION AFRICAINE/i', $value)) return 'LP_UA';
        }
        
        // Vérifier le code MRZ
        if (isset($passportData['mrz']['line1'])) {
            $mrzType = substr($passportData['mrz']['line1'], 0, 2);
            switch ($mrzType) {
                case 'PD': return 'DIPLOMATIQUE';
                case 'PS': return 'SERVICE';
            }
        }
        
        return 'ORDINAIRE';
    }
    
    /**
     * Construit le prompt de validation diplomatique
     */
    private function buildDiplomaticValidationPrompt(
        array $passportData,
        ?array $verbalNoteData,
        string $passportType,
        array $rules,
        array $sessionContext
    ): string {
        $passportJson = json_encode($passportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $verbalNoteJson = $verbalNoteData 
            ? json_encode($verbalNoteData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) 
            : 'Non fournie';
        $contextJson = json_encode($sessionContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $rulesJson = json_encode($rules, JSON_PRETTY_PRINT);
        
        return <<<PROMPT
Tu es le superviseur Layer 3 (Claude) de l'architecture Triple Layer pour les demandes de visa diplomatiques à l'Ambassade de Côte d'Ivoire en Éthiopie.

## RÔLE:
Valider en profondeur les demandes de visa diplomatiques pour détecter:
- Fraudes ou usurpations d'identité
- Documents falsifiés ou incohérents
- Non-conformités protocolaires

## TYPE DE PASSEPORT DÉTECTÉ: {$passportType}

## RÈGLES DE VALIDATION APPLICABLES:
```json
{$rulesJson}
```

## DONNÉES DU PASSEPORT (Layer 1+2):
```json
{$passportJson}
```

## NOTE VERBALE (si fournie):
{$verbalNoteJson}

## CONTEXTE SESSION:
```json
{$contextJson}
```

## VALIDATIONS REQUISES:

### 1. Authenticité du passeport
- Le type MRZ correspond-il au type déclaré?
- Les dates sont-elles cohérentes (émission < expiration)?
- La nationalité est-elle cohérente avec le pays émetteur?

### 2. Statut diplomatique
- Le titulaire a-t-il un statut diplomatique valide?
- Le ministère des Affaires étrangères a-t-il accrédité cette mission?

### 3. Cohérence des documents (si note verbale fournie)
- Le nom correspond-il?
- Le numéro de passeport est-il mentionné et correct?
- La fonction/titre est-elle cohérente?

### 4. Anomalies et Red Flags
- Incohérences temporelles
- Données suspectes
- Patterns de fraude connus

Retourne UNIQUEMENT un JSON valide avec cette structure:
{
  "valid": true,
  "confidence_score": 0.95,
  "passport_type_verified": true,
  "mrz_validation": {
    "valid": true,
    "type_code_correct": true,
    "checksum_valid": true
  },
  "diplomatic_status": {
    "verified": true,
    "status": "DIPLOMAT",
    "accreditation": "VALID"
  },
  "document_coherence": {
    "name_match": true,
    "dates_coherent": true,
    "passport_number_match": true
  },
  "anomalies": [
    {
      "type": "TYPE_ANOMALIE",
      "severity": "low|medium|high|critical",
      "description": "Description",
      "field": "champ_concerné"
    }
  ],
  "warnings": ["Avertissement si nécessaire"],
  "recommendation": "approved|manual_review|rejected",
  "rejection_reason": null,
  "notes": "Observations du superviseur"
}
PROMPT;
    }
    
    /**
     * Appelle l'API Claude
     */
    private function callClaude(string $prompt): string {
        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->config['max_tokens'],
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ]
        ];
        
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => $this->config['timeout']
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        if ($curlError) {
            throw new Exception("Erreur cURL: {$curlError}");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("Erreur API Claude (HTTP {$httpCode}): {$response}");
        }
        
        $data = json_decode($response, true);
        return $data['content'][0]['text'] ?? '';
    }
    
    /**
     * Parse la réponse de validation
     */
    private function parseValidationResponse(string $response): array {
        // Extraire le JSON de la réponse
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $response, $matches)) {
            $jsonString = trim($matches[1]);
        } elseif (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $jsonString = $matches[0];
        } else {
            return [
                'valid' => false,
                'error' => 'Format de réponse invalide',
                'raw_response' => substr($response, 0, 500)
            ];
        }
        
        $data = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'valid' => false,
                'error' => 'JSON invalide: ' . json_last_error_msg(),
                'raw_response' => substr($jsonString, 0, 500)
            ];
        }
        
        return $data;
    }
    
    /**
     * Log conditionnel
     */
    private function log(string $message, string $level = 'info'): void {
        if ($this->config['debug']) {
            error_log("[ClaudeDiplomaticValidator] {$level}: {$message}");
        }
    }
}

