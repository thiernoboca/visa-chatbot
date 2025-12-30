<?php
/**
 * Prompt Gemini pour l'étape de résidence
 * Thinking Level: LOW
 * 
 * @package VisaChatbot
 */

class ResidencePrompt {
    
    /**
     * Pays couverts par la circonscription
     */
    const JURISDICTION_COUNTRIES = [
        'ETH' => ['fr' => 'Éthiopie', 'en' => 'Ethiopia', 'capital' => 'Addis-Abeba'],
        'KEN' => ['fr' => 'Kenya', 'en' => 'Kenya', 'capital' => 'Nairobi'],
        'DJI' => ['fr' => 'Djibouti', 'en' => 'Djibouti', 'capital' => 'Djibouti'],
        'TZA' => ['fr' => 'Tanzanie', 'en' => 'Tanzania', 'capital' => 'Dodoma'],
        'UGA' => ['fr' => 'Ouganda', 'en' => 'Uganda', 'capital' => 'Kampala'],
        'SSD' => ['fr' => 'Soudan du Sud', 'en' => 'South Sudan', 'capital' => 'Juba'],
        'SOM' => ['fr' => 'Somalie', 'en' => 'Somalia', 'capital' => 'Mogadiscio']
    ];
    
    /**
     * Construit le prompt pour demander le pays de résidence
     */
    public static function buildAskCountry(string $lang = 'fr'): string {
        $countries = self::getCountryList($lang);
        
        if ($lang === 'fr') {
            return <<<PROMPT
Tu dois demander à l'utilisateur son pays de résidence actuel.

CONTEXTE:
- L'Ambassade de Côte d'Ivoire en Éthiopie couvre UNIQUEMENT ces pays:
{$countries}

INSTRUCTIONS:
1. Demande poliment dans quel pays l'utilisateur réside actuellement
2. Mentionne que c'est pour vérifier la compétence territoriale
3. Propose les pays couverts comme suggestions

MESSAGE COURT ET DIRECT (2-3 phrases max)
PROMPT;
        } else {
            return <<<PROMPT
You need to ask the user for their current country of residence.

CONTEXT:
- The Embassy of Côte d'Ivoire in Ethiopia covers ONLY these countries:
{$countries}

INSTRUCTIONS:
1. Politely ask which country the user currently lives in
2. Mention this is to verify territorial jurisdiction
3. Suggest the covered countries as options

SHORT AND DIRECT MESSAGE (2-3 sentences max)
PROMPT;
        }
    }
    
    /**
     * Construit le prompt pour traiter la réponse pays
     */
    public static function buildProcessCountry(string $userInput, string $lang = 'fr'): string {
        $countriesJson = json_encode(self::JURISDICTION_COUNTRIES, JSON_PRETTY_PRINT);
        
        return <<<PROMPT
L'utilisateur a indiqué son pays de résidence. Analyse sa réponse.

RÉPONSE UTILISATEUR: "{$userInput}"

PAYS COUVERTS PAR L'AMBASSADE:
{$countriesJson}

TÂCHE:
1. Détermine si l'utilisateur a mentionné un des pays couverts
2. Retourne un JSON avec:

```json
{
  "detected_country_code": "ETH|KEN|DJI|TZA|UGA|SSD|SOM|null",
  "detected_country_name": "Nom du pays",
  "is_in_jurisdiction": true|false,
  "confidence": 0.95,
  "message": "Message de confirmation ou de redirection"
}
```

RÈGLES:
- Si le pays est couvert: confirmer et proposer de continuer
- Si le pays n'est pas couvert: expliquer poliment que l'ambassade ne couvre pas ce pays
- Être flexible dans la détection (ex: "éthiopie", "Ethiopia", "Addis" → ETH)
PROMPT;
    }
    
    /**
     * Construit le prompt pour le blocage hors juridiction
     */
    public static function buildOutOfJurisdiction(string $country, string $lang = 'fr'): string {
        if ($lang === 'fr') {
            return <<<PROMPT
L'utilisateur réside dans un pays NON COUVERT par cette ambassade ({$country}).

Génère un message poli et empathique qui:
1. Reconnaît sa demande
2. Explique que l'Ambassade à Addis-Abeba ne couvre pas ce pays
3. Suggère de contacter l'ambassade compétente pour son pays
4. Donne le lien: https://diplomatie.gouv.ci/ambassades

MESSAGE COURT, EMPATHIQUE, PROFESSIONNEL
PROMPT;
        } else {
            return <<<PROMPT
The user lives in a country NOT COVERED by this embassy ({$country}).

Generate a polite and empathetic message that:
1. Acknowledges their request
2. Explains that the Embassy in Addis Ababa doesn't cover this country
3. Suggests contacting the appropriate embassy for their country
4. Provides the link: https://diplomatie.gouv.ci/ambassades

SHORT, EMPATHETIC, PROFESSIONAL MESSAGE
PROMPT;
        }
    }
    
    /**
     * Messages prédéfinis pour accélérer
     */
    public static function getQuickResponses(): array {
        return [
            'ask_country' => [
                'fr' => "Parfait !\n\nPour démarrer votre demande de visa, nous aimerions connaître dans quel pays résidez-vous actuellement ?",
                'en' => "Great!\n\nTo start your visa application, we would like to know in which country do you currently reside?"
            ],
            'confirm_country' => [
                'fr' => "✅ Excellent ! Vous résidez en {country}, qui est bien couvert par notre ambassade.\n\nDans quelle ville habitez-vous ?",
                'en' => "✅ Excellent! You reside in {country}, which is covered by our embassy.\n\nWhich city do you live in?"
            ],
            'out_of_jurisdiction' => [
                'fr' => "Je suis désolé, l'Ambassade de Côte d'Ivoire à Addis-Abeba ne couvre pas {country}.\n\n📞 Veuillez contacter l'ambassade compétente pour votre région:\nhttps://diplomatie.gouv.ci/ambassades\n\nPuis-je vous aider avec autre chose ?",
                'en' => "I'm sorry, the Embassy of Côte d'Ivoire in Addis Ababa doesn't cover {country}.\n\n📞 Please contact the appropriate embassy for your region:\nhttps://diplomatie.gouv.ci/ambassades\n\nCan I help you with anything else?"
            ]
        ];
    }
    
    /**
     * Actions rapides pour la sélection de pays
     */
    public static function getQuickActions(string $lang = 'fr'): array {
        $actions = [];
        
        foreach (self::JURISDICTION_COUNTRIES as $code => $names) {
            $flag = self::getCountryFlag($code);
            $name = $names[$lang] ?? $names['en'];
            $actions[] = ['label' => "{$flag} {$name}", 'value' => $code];
        }
        
        $otherLabel = $lang === 'fr' ? '🌍 Autre pays' : '🌍 Other country';
        $actions[] = ['label' => $otherLabel, 'value' => 'OTHER'];
        
        return $actions;
    }
    
    /**
     * Retourne la liste des pays formatée
     */
    private static function getCountryList(string $lang): string {
        $list = [];
        foreach (self::JURISDICTION_COUNTRIES as $code => $names) {
            $name = $names[$lang] ?? $names['en'];
            $capital = $names['capital'];
            $list[] = "- {$name} ({$capital})";
        }
        return implode("\n", $list);
    }
    
    /**
     * Retourne le drapeau emoji d'un pays
     */
    private static function getCountryFlag(string $code): string {
        $flags = [
            'ETH' => '🇪🇹',
            'KEN' => '🇰🇪',
            'DJI' => '🇩🇯',
            'TZA' => '🇹🇿',
            'UGA' => '🇺🇬',
            'SSD' => '🇸🇸',
            'SOM' => '🇸🇴'
        ];
        return $flags[$code] ?? '🏳️';
    }
}

