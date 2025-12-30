<?php
/**
 * Prompt Gemini pour l'étape d'accueil
 * Thinking Level: MINIMAL
 * 
 * @package VisaChatbot
 */

class GreetingPrompt {
    
    /**
     * Construit le prompt d'accueil
     * 
     * @param string $lang Langue détectée ou préférée
     * @param array $context Contexte additionnel
     * @return string Prompt formaté
     */
    public static function build(string $lang = 'fr', array $context = []): string {
        $timeOfDay = self::getTimeOfDay();
        
        if ($lang === 'fr') {
            return <<<PROMPT
Tu es l'assistant officiel de l'Ambassade de Côte d'Ivoire en Éthiopie.

CONTEXTE:
- C'est le {$timeOfDay}
- L'utilisateur arrive sur le portail e-Visa
- Tu dois l'accueillir chaleureusement et lui demander sa langue préférée

INSTRUCTIONS:
1. Salue l'utilisateur avec un message de bienvenue professionnel mais chaleureux
2. Présente brièvement le service (demande de visa en ligne)
3. Demande la langue préférée: Français ou English
4. Utilise 1-2 émojis maximum (drapeau 🇨🇮, main 👋)

RÉPONSE ATTENDUE:
Un message court (3-4 phrases max) qui:
- Souhaite la bienvenue
- Mentionne que c'est le service officiel
- Propose le choix de langue

NE PAS:
- Demander d'autres informations
- Mentionner que tu es une IA
- Faire un message trop long
PROMPT;
        } else {
            return <<<PROMPT
You are the official assistant of the Embassy of Côte d'Ivoire in Ethiopia.

CONTEXT:
- It's {$timeOfDay}
- User is arriving on the e-Visa portal
- You need to welcome them warmly and ask for their preferred language

INSTRUCTIONS:
1. Greet the user with a professional but warm welcome message
2. Briefly introduce the service (online visa application)
3. Ask for preferred language: Français or English
4. Use 1-2 emojis max (flag 🇨🇮, wave 👋)

EXPECTED RESPONSE:
A short message (3-4 sentences max) that:
- Welcomes the user
- Mentions this is the official service
- Offers language choice

DO NOT:
- Ask for other information
- Mention you're an AI
- Write a long message
PROMPT;
        }
    }
    
    /**
     * Messages de réponse prédéfinis (pour accélérer)
     */
    public static function getQuickResponses(): array {
        return [
            'fr' => [
                'default' => "Bienvenue à l'Ambassade de Côte d'Ivoire en Éthiopie ! 🇨🇮\n\nJe suis votre assistant pour les demandes de visa électronique. Ce service officiel vous permet d'obtenir votre e-Visa en quelques minutes.\n\n🌐 Dans quelle langue souhaitez-vous continuer ?",
                'morning' => "Bonjour et bienvenue à l'Ambassade de Côte d'Ivoire ! 🇨🇮\n\nJe suis ici pour vous guider dans votre demande de visa électronique.\n\n🌐 Préférez-vous continuer en Français ou en English ?",
                'evening' => "Bonsoir et bienvenue à l'Ambassade de Côte d'Ivoire ! 🇨🇮\n\nJe vais vous accompagner dans votre demande de visa en ligne.\n\n🌐 Dans quelle langue souhaitez-vous continuer ?"
            ],
            'en' => [
                'default' => "Welcome to the Embassy of Côte d'Ivoire in Ethiopia! 🇨🇮\n\nI'm your assistant for electronic visa applications. This official service allows you to obtain your e-Visa in just a few minutes.\n\n🌐 What language would you like to continue in?",
                'morning' => "Good morning and welcome to the Embassy of Côte d'Ivoire! 🇨🇮\n\nI'm here to guide you through your e-Visa application.\n\n🌐 Would you prefer to continue in Français or English?",
                'evening' => "Good evening and welcome to the Embassy of Côte d'Ivoire! 🇨🇮\n\nI'll assist you with your online visa application.\n\n🌐 What language would you like to continue in?"
            ]
        ];
    }
    
    /**
     * Actions rapides pour cette étape
     */
    public static function getQuickActions(): array {
        return [
            ['label' => '🇫🇷 Français', 'value' => 'fr'],
            ['label' => '🇬🇧 English', 'value' => 'en']
        ];
    }
    
    /**
     * Déterminer le moment de la journée
     */
    private static function getTimeOfDay(): string {
        $hour = (int) date('H');
        
        if ($hour >= 5 && $hour < 12) {
            return 'matin';
        } elseif ($hour >= 12 && $hour < 18) {
            return 'après-midi';
        } else {
            return 'soir';
        }
    }
}

