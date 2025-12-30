<?php
/**
 * Prompt Gemini pour l'étape passeport
 * Thinking Level: HIGH (détection critique du type)
 * 
 * @package VisaChatbot
 */

class PassportPrompt {
    
    /**
     * Types de passeports et leurs workflows
     */
    const PASSPORT_TYPES = [
        'ORDINAIRE' => ['workflow' => 'STANDARD', 'free' => false, 'priority' => false],
        'OFFICIEL' => ['workflow' => 'STANDARD', 'free' => false, 'priority' => false],
        'DIPLOMATIQUE' => ['workflow' => 'PRIORITY', 'free' => true, 'priority' => true],
        'SERVICE' => ['workflow' => 'PRIORITY', 'free' => true, 'priority' => true],
        'LAISSEZ_PASSER' => ['workflow' => 'PRIORITY', 'free' => true, 'priority' => true],
        'SPECIAL' => ['workflow' => 'PRIORITY', 'free' => true, 'priority' => true],
        'REFUGIE' => ['workflow' => 'STANDARD', 'free' => false, 'priority' => false],
        'APATRIDE' => ['workflow' => 'STANDARD', 'free' => false, 'priority' => false]
    ];
    
    /**
     * Construit le prompt pour demander le scan du passeport
     */
    public static function buildAskScan(string $lang = 'fr'): string {
        if ($lang === 'fr') {
            return <<<PROMPT
Tu dois demander à l'utilisateur de scanner ou photographier son passeport.

INSTRUCTIONS:
1. Demande de scanner la PAGE D'IDENTITÉ du passeport (page avec photo)
2. Donne des conseils pour une bonne image:
   - Éclairage uniforme, pas de reflets
   - Passeport à plat, bien cadré
   - Image nette et lisible
3. Rassure sur la sécurité des données

MESSAGE COURT ET PRATIQUE (3-4 phrases + conseils en liste)
PROMPT;
        } else {
            return <<<PROMPT
You need to ask the user to scan or photograph their passport.

INSTRUCTIONS:
1. Ask to scan the IDENTITY PAGE of the passport (page with photo)
2. Give tips for a good image:
   - Even lighting, no glare
   - Passport flat, well framed
   - Clear and readable image
3. Reassure about data security

SHORT AND PRACTICAL MESSAGE (3-4 sentences + tips as list)
PROMPT;
        }
    }
    
    /**
     * Construit le prompt pour analyser les données OCR du passeport
     * CRITIQUE: Détection du type pour workflow adaptatif
     */
    public static function buildAnalyzeOCR(array $ocrData, string $lang = 'fr'): string {
        $ocrJson = json_encode($ocrData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $typesJson = json_encode(self::PASSPORT_TYPES, JSON_PRETTY_PRINT);
        
        return <<<PROMPT
ANALYSE CRITIQUE DE PASSEPORT - Données OCR reçues.

DONNÉES OCR:
{$ocrJson}

TYPES DE PASSEPORTS ET WORKFLOWS:
{$typesJson}

TÂCHE CRITIQUE:
1. DÉTECTER LE TYPE DE PASSEPORT:
   - Chercher dans le MRZ ligne 1: P<XXX (P=ordinaire), D<XXX (diplomatique), S<XXX (service)
   - Chercher dans le texte visuel: "DIPLOMATIC", "DIPLOMATIQUE", "SERVICE", "OFFICIAL"
   - Par défaut: ORDINAIRE

2. DÉTERMINER LE WORKFLOW:
   - DIPLOMATIQUE, SERVICE, LAISSEZ_PASSER, SPECIAL → PRIORITY (gratuit, 24-48h)
   - ORDINAIRE, OFFICIEL, REFUGIE, APATRIDE → STANDARD (payant, 5-10j)

3. VÉRIFIER LA VALIDITÉ:
   - Date d'expiration > aujourd'hui + 6 mois

4. RETOURNER UN JSON:
```json
{
  "passport_type": "ORDINAIRE|DIPLOMATIQUE|SERVICE|...",
  "workflow": "STANDARD|PRIORITY",
  "is_free": false,
  "is_priority": false,
  "requires_verbal_note": false,
  "fields_summary": {
    "surname": "NOM",
    "given_names": "PRENOMS",
    "passport_number": "XX0000000",
    "nationality": "ETH",
    "date_of_expiry": "YYYY-MM-DD",
    "expiry_valid": true
  },
  "detection_confidence": 0.95,
  "message": "Message de confirmation adapté au type détecté"
}
```

RÈGLES:
- Si DIPLOMATIQUE/SERVICE détecté: Mentionner les avantages (gratuit, prioritaire)
- Si ORDINAIRE: Mentionner les frais à venir
- Si expiration < 6 mois: AVERTISSEMENT, recommander renouvellement
- Si expiré: BLOCAGE
PROMPT;
    }
    
    /**
     * Construit le prompt pour confirmer les données avec l'utilisateur
     */
    public static function buildConfirmation(array $extractedData, string $lang = 'fr'): string {
        $dataJson = json_encode($extractedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if ($lang === 'fr') {
            return <<<PROMPT
Génère un message de confirmation des données du passeport pour l'utilisateur.

DONNÉES EXTRAITES:
{$dataJson}

INSTRUCTIONS:
1. Afficher les données extraites de manière claire et lisible
2. Utiliser des émojis pour la structure (📋, ✅, ⚠️)
3. Si workflow PRIORITY: Mettre en avant les avantages (gratuit, rapide)
4. Si workflow STANDARD: Mentionner le délai et les frais à venir
5. Demander confirmation ou possibilité de corriger

FORMAT:
✅ **Passeport détecté: [TYPE]**

📋 **Informations extraites:**
• Nom: XXX
• Prénoms: XXX
• N° Passeport: XXX
• Nationalité: XXX
• Expiration: XXX ✅/⚠️

[Si PRIORITY]
🌟 **Workflow PRIORITY activé:**
• ✨ GRATUIT
• ⚡ Traitement prioritaire: 24-48h
• 📝 Note verbale requise (si applicable)

[Si STANDARD]
📊 **Workflow STANDARD:**
• 💰 Frais: à calculer
• ⏱️ Délai: 5-10 jours ouvrés

Ces informations sont-elles correctes ?
PROMPT;
        } else {
            return <<<PROMPT
Generate a passport data confirmation message for the user.

EXTRACTED DATA:
{$dataJson}

INSTRUCTIONS:
1. Display extracted data clearly and readably
2. Use emojis for structure (📋, ✅, ⚠️)
3. If PRIORITY workflow: Highlight benefits (free, fast)
4. If STANDARD workflow: Mention timeline and upcoming fees
5. Ask for confirmation or ability to correct

FORMAT:
✅ **Passport detected: [TYPE]**

📋 **Extracted information:**
• Surname: XXX
• Given names: XXX
• Passport No: XXX
• Nationality: XXX
• Expiry: XXX ✅/⚠️

[If PRIORITY]
🌟 **PRIORITY Workflow activated:**
• ✨ FREE
• ⚡ Priority processing: 24-48h
• 📝 Verbal note required (if applicable)

[If STANDARD]
📊 **STANDARD Workflow:**
• 💰 Fees: to be calculated
• ⏱️ Timeline: 5-10 business days

Is this information correct?
PROMPT;
        }
    }
    
    /**
     * Messages prédéfinis
     */
    public static function getQuickResponses(): array {
        return [
            'ask_scan' => [
                'fr' => "📷 Maintenant, veuillez scanner ou photographier la **page d'identité** de votre passeport (page avec votre photo).\n\n💡 **Conseils pour une bonne image:**\n• Éclairage uniforme, pas de reflets\n• Passeport à plat et bien cadré\n• Image nette et lisible\n\n🔒 Vos données sont chiffrées et sécurisées.",
                'en' => "📷 Now, please scan or photograph the **identity page** of your passport (page with your photo).\n\n💡 **Tips for a good image:**\n• Even lighting, no glare\n• Passport flat and well framed\n• Clear and readable image\n\n🔒 Your data is encrypted and secure."
            ],
            'processing' => [
                'fr' => "⏳ Analyse de votre passeport en cours...",
                'en' => "⏳ Analyzing your passport..."
            ],
            'expired' => [
                'fr' => "❌ **Passeport expiré**\n\nVotre passeport a expiré le {date}. Vous devez le renouveler avant de faire une demande de visa.\n\nSouhaitez-vous des informations sur le renouvellement ?",
                'en' => "❌ **Expired passport**\n\nYour passport expired on {date}. You must renew it before applying for a visa.\n\nWould you like information about renewal?"
            ],
            'expiring_soon' => [
                'fr' => "⚠️ **Attention**: Votre passeport expire dans moins de 6 mois ({date}).\n\nNous recommandons de le renouveler pour éviter tout problème à l'entrée en Côte d'Ivoire.\n\nSouhaitez-vous continuer malgré tout ?",
                'en' => "⚠️ **Warning**: Your passport expires in less than 6 months ({date}).\n\nWe recommend renewing it to avoid any issues entering Côte d'Ivoire.\n\nWould you like to continue anyway?"
            ]
        ];
    }
    
    /**
     * Actions rapides pour confirmation
     */
    public static function getConfirmActions(string $lang = 'fr'): array {
        if ($lang === 'fr') {
            return [
                ['label' => '✅ Oui, c\'est correct', 'value' => 'confirm'],
                ['label' => '✏️ Corriger une information', 'value' => 'modify']
            ];
        } else {
            return [
                ['label' => '✅ Yes, this is correct', 'value' => 'confirm'],
                ['label' => '✏️ Correct an information', 'value' => 'modify']
            ];
        }
    }
    
    /**
     * Détecte le type de passeport depuis le code MRZ
     */
    public static function detectTypeFromMRZ(string $mrzLine1): string {
        $mrzLine1 = strtoupper(trim($mrzLine1));
        
        // Premier caractère du MRZ
        $typeCode = substr($mrzLine1, 0, 1);
        
        switch ($typeCode) {
            case 'D':
                return 'DIPLOMATIQUE';
            case 'S':
                return 'SERVICE';
            case 'O':
                return 'OFFICIEL';
            case 'P':
            default:
                // Vérifier le texte pour plus de contexte
                if (strpos($mrzLine1, 'DIPLOMATIC') !== false) {
                    return 'DIPLOMATIQUE';
                }
                if (strpos($mrzLine1, 'SERVICE') !== false) {
                    return 'SERVICE';
                }
                return 'ORDINAIRE';
        }
    }
}

