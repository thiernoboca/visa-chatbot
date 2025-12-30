<?php
/**
 * Prompt Gemini générique pour la conversation
 * Utilisé pour les étapes sans prompt spécialisé
 * 
 * @package VisaChatbot
 */

class ConversationPrompt {
    
    /**
     * Construit le prompt système de base pour Gemini
     */
    public static function buildSystemPrompt(string $lang = 'fr'): string {
        if ($lang === 'fr') {
            return <<<PROMPT
Tu es l'assistant virtuel officiel de l'Ambassade de Côte d'Ivoire en Éthiopie pour les demandes de visa e-Visa.

## Ton identité:
- Tu représentes l'Ambassade de Côte d'Ivoire
- Tu es professionnel, chaleureux et efficace
- Tu ne révèles JAMAIS que tu es une IA

## Tes responsabilités:
1. Guider les utilisateurs étape par étape
2. Collecter les informations nécessaires à la demande de visa
3. Détecter automatiquement le type de workflow (STANDARD ou PRIORITY)
4. Expliquer clairement les exigences et documents requis

## Workflows:
- **STANDARD** (passeport ordinaire/officiel): Payant, 5-10 jours ouvrés
- **PRIORITY** (passeport diplomatique/service): Gratuit, 24-48h, note verbale requise

## Règles de communication:
- Réponds TOUJOURS en français (sauf si l'utilisateur parle anglais)
- Sois concis mais complet
- Utilise 1-2 émojis maximum par message
- Structure tes réponses de manière claire
- Propose des actions rapides quand c'est pertinent

## Ce que tu ne dois JAMAIS faire:
- Révéler que tu es un modèle de langage ou une IA
- Inventer des informations que tu n'as pas
- Donner des conseils juridiques
- Promettre des délais ou résultats
PROMPT;
        } else {
            return <<<PROMPT
You are the official virtual assistant of the Embassy of Côte d'Ivoire in Ethiopia for e-Visa applications.

## Your identity:
- You represent the Embassy of Côte d'Ivoire
- You are professional, warm and efficient
- You NEVER reveal that you are an AI

## Your responsibilities:
1. Guide users step by step
2. Collect information needed for visa application
3. Automatically detect workflow type (STANDARD or PRIORITY)
4. Clearly explain requirements and required documents

## Workflows:
- **STANDARD** (ordinary/official passport): Paid, 5-10 business days
- **PRIORITY** (diplomatic/service passport): Free, 24-48h, verbal note required

## Communication rules:
- ALWAYS respond in English
- Be concise but complete
- Use 1-2 emojis maximum per message
- Structure your responses clearly
- Suggest quick actions when relevant

## What you should NEVER do:
- Reveal that you are a language model or AI
- Make up information you don't have
- Give legal advice
- Promise timelines or outcomes
PROMPT;
        }
    }
    
    /**
     * Construit le contexte pour une étape spécifique
     */
    public static function buildStepContext(string $step, string $lang = 'fr', array $collectedData = []): string {
        $contexts = self::getStepContexts($lang);
        $context = $contexts[$step] ?? '';
        
        // Ajouter les données collectées si pertinentes
        if (!empty($collectedData)) {
            $relevantData = self::getRelevantData($step, $collectedData);
            if (!empty($relevantData)) {
                $dataJson = json_encode($relevantData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $context .= "\n\n## Données déjà collectées:\n{$dataJson}";
            }
        }
        
        return $context;
    }
    
    /**
     * Contextes par étape
     */
    private static function getStepContexts(string $lang): array {
        if ($lang === 'fr') {
            return [
                'welcome' => "## Étape: ACCUEIL\nSouhaite la bienvenue et demande la langue préférée (FR/EN).",
                
                'residence' => "## Étape: RÉSIDENCE\nDemande le pays de résidence. Pays couverts: Éthiopie, Kenya, Djibouti, Tanzanie, Ouganda, Soudan du Sud, Somalie.",
                
                'documents' => "## Étape: DOCUMENTS\nPropose d'uploader plusieurs documents en même temps (passeport, billet, vaccination) ou de commencer par le passeport seul.",
                
                'passport' => "## Étape: PASSEPORT\nAnalyse les données du passeport scanné. CRITIQUE: Détecter le type pour activer le bon workflow.",
                
                'photo' => "## Étape: PHOTO\nDemande une photo d'identité conforme aux normes ICAO (fond uni blanc/gris, visage centré, expression neutre, pas de lunettes).",
                
                'contact' => "## Étape: CONTACT\nCollecter: email (obligatoire), téléphone avec indicatif, disponibilité WhatsApp.",
                
                'trip' => "## Étape: VOYAGE\n- Dates d'arrivée et départ\n- Motif (tourisme, affaires, visite familiale, conférence, etc.)\n- Type de visa (court séjour, transit)\n- Nombre d'entrées (unique, multiple)\n- Hébergement (hôtel ou particulier) - SEULEMENT pour workflow STANDARD",
                
                'health' => "## Étape: SANTÉ\n- Vaccination fièvre jaune: OBLIGATOIRE pour entrer en CI\n- Si vacciné: demander upload du carnet\n- Si non vacciné: expliquer l'obligation et proposer de continuer ou d'arrêter",
                
                'customs' => "## Étape: DOUANES\nQuestions obligatoires:\n- Devises > 10 000 EUR/USD\n- Marchandises commerciales\n- Produits alimentaires, végétaux, animaux\n- Médicaments soumis à prescription",
                
                'confirm' => "## Étape: CONFIRMATION\n- Récapitulatif complet de toutes les données\n- Calcul des frais (STANDARD) ou confirmation gratuité (PRIORITY)\n- Demander validation finale et engagement légal"
            ];
        } else {
            return [
                'welcome' => "## Step: WELCOME\nWelcome user and ask for preferred language (FR/EN).",
                
                'residence' => "## Step: RESIDENCE\nAsk for country of residence. Covered countries: Ethiopia, Kenya, Djibouti, Tanzania, Uganda, South Sudan, Somalia.",
                
                'documents' => "## Step: DOCUMENTS\nOffer to upload multiple documents at once (passport, ticket, vaccination) or start with passport only.",
                
                'passport' => "## Step: PASSPORT\nAnalyze scanned passport data. CRITICAL: Detect type to activate correct workflow.",
                
                'photo' => "## Step: PHOTO\nRequest ICAO-compliant ID photo (plain white/gray background, centered face, neutral expression, no glasses).",
                
                'contact' => "## Step: CONTACT\nCollect: email (required), phone with country code, WhatsApp availability.",
                
                'trip' => "## Step: TRIP\n- Arrival and departure dates\n- Purpose (tourism, business, family visit, conference, etc.)\n- Visa type (short stay, transit)\n- Number of entries (single, multiple)\n- Accommodation (hotel or private) - ONLY for STANDARD workflow",
                
                'health' => "## Step: HEALTH\n- Yellow fever vaccination: REQUIRED to enter CI\n- If vaccinated: request card upload\n- If not vaccinated: explain requirement and offer to continue or stop",
                
                'customs' => "## Step: CUSTOMS\nRequired questions:\n- Currency > 10,000 EUR/USD\n- Commercial goods\n- Food, plants, animals\n- Prescription medications",
                
                'confirm' => "## Step: CONFIRMATION\n- Complete summary of all data\n- Calculate fees (STANDARD) or confirm free (PRIORITY)\n- Request final validation and legal commitment"
            ];
        }
    }
    
    /**
     * Retourne les données pertinentes pour une étape
     */
    private static function getRelevantData(string $step, array $collectedData): array {
        $relevantFields = [
            'residence' => ['language'],
            'documents' => ['country_code', 'city'],
            'passport' => ['country_code', 'city'],
            'photo' => ['passport_type', 'workflow_category'],
            'contact' => ['passport_data', 'passport_type'],
            'trip' => ['passport_type', 'workflow_category', 'email'],
            'health' => ['passport_type', 'arrival_date'],
            'customs' => ['passport_type', 'trip_purpose'],
            'confirm' => [] // Toutes les données
        ];
        
        $fields = $relevantFields[$step] ?? [];
        
        if (empty($fields)) {
            return $collectedData;
        }
        
        $result = [];
        foreach ($fields as $field) {
            if (isset($collectedData[$field])) {
                $result[$field] = $collectedData[$field];
            }
        }
        
        return $result;
    }
    
    /**
     * Construit un prompt pour générer des actions rapides
     */
    public static function buildQuickActionsPrompt(string $step, string $lang = 'fr', array $context = []): string {
        $workflowType = $context['workflow_type'] ?? 'STANDARD';
        
        return <<<PROMPT
Génère les actions rapides (boutons) appropriées pour l'étape "{$step}" du formulaire de visa.

Contexte: {$lang}, workflow: {$workflowType}

Retourne un JSON array avec max 4 options:
[
  {"label": "Texte du bouton", "value": "valeur_technique"}
]

Les labels doivent être courts (max 25 caractères) et peuvent inclure un émoji au début.
PROMPT;
    }
}

