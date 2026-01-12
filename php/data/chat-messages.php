<?php
/**
 * Données de référence - Messages conversationnels bilingues
 * Ambassade de Côte d'Ivoire à Addis-Abeba
 * 
 * @package VisaChatbot
 */

// Messages pré-définis pour chaque étape
// Persona: Aya - Assistante virtuelle chaleureuse et professionnelle
const CHAT_MESSAGES = [
    // Message bilingue pour l'accueil AVANT sélection de langue
    'welcome_bilingual' => [
        'fr' => "Akwaba ! 👋 / Welcome! 👋

Moi c'est **Aya**, votre assistante e-Visa.
I'm **Aya**, your e-Visa assistant.

🇨🇮 Ambassade de Côte d'Ivoire - Addis-Abeba

**Choisissez votre langue / Choose your language:**",
        'en' => "Akwaba ! 👋 / Welcome! 👋

Moi c'est **Aya**, votre assistante e-Visa.
I'm **Aya**, your e-Visa assistant.

🇨🇮 Ambassade de Côte d'Ivoire - Addis-Abeba

**Choisissez votre langue / Choose your language:**"
    ],

    'welcome' => [
        'fr' => "Akwaba ! 👋 Moi c'est **Aya**, votre assistante pour les visas de l'Ambassade de Côte d'Ivoire à Addis-Abeba.

Je vais vous accompagner pas à pas. C'est simple, rapide, et je suis là pour vous aider ! ✨

Le processus prend environ 8-10 minutes. Dans quelle langue préférez-vous continuer ?",
        'en' => "Akwaba! 👋 I'm **Aya**, your visa assistant from the Embassy of Côte d'Ivoire in Addis Ababa.

I'll guide you step by step. It's simple, fast, and I'm here to help! ✨

The process takes about 8-10 minutes. Which language would you prefer?"
    ],
    
    'residence_question' => [
        'fr' => 'Parfait ! 🌍 Dans quel pays résidez-vous actuellement ?',
        'en' => 'Perfect! 🌍 In which country do you currently reside?'
    ],
    
    'residence_city_question' => [
        'fr' => 'Super ! Dans quelle ville résidez-vous en {country} ?',
        'en' => 'Great! In which city do you live in {country}?'
    ],
    
    'residence_confirmed' => [
        'fr' => "✅ Parfait ! {city}, {country} - c'est noté !

Notre ambassade couvre bien votre territoire. On continue ensemble ! 💪",
        'en' => "✅ Perfect! {city}, {country} - noted!

Our embassy does cover your territory. Let's continue together! 💪"
    ],
    
    'residence_not_in_jurisdiction' => [
        'fr' => "😔 Désolée, mais votre pays de résidence n'est pas couvert par notre ambassade à Addis-Abeba.

Je ne peux traiter que les demandes pour : **Éthiopie, Kenya, Djibouti, Tanzanie, Ouganda, Soudan du Sud et Somalie**.

Contactez l'ambassade de Côte d'Ivoire de votre pays - ils pourront vous aider ! 🌍",
        'en' => "😔 Sorry, but your country of residence is not covered by our embassy in Addis Ababa.

I can only process applications for: **Ethiopia, Kenya, Djibouti, Tanzania, Uganda, South Sudan and Somalia**.

Contact the Embassy of Côte d'Ivoire in your country - they can help you! 🌍"
    ],
    
    'passport_scan_request' => [
        'fr' => "Maintenant, le moment clé : votre passeport ! 📸

Notre IA va lire automatiquement vos informations - fini la saisie manuelle !

**Conseils pour un scan parfait** :
• Page d'identité bien éclairée
• Évitez les reflets
• Zone MRZ (les 2 lignes en bas) bien visible

C'est parti ! ✨",
        'en' => "Now, the key moment: your passport! 📸

Our AI will automatically read your information - no more manual entry!

**Tips for a perfect scan**:
• Well-lit identity page
• Avoid reflections
• MRZ zone (2 lines at bottom) clearly visible

Let's go! ✨"
    ],
    
    'passport_diplomatic_detected' => [
        'fr' => "🎖️ Wow, passeport **{type}** ! Bienvenue VIP !

Excellente nouvelle {given_names} ! Vous bénéficiez d'un traitement **prioritaire** et **gratuit**. On est ensemble ! 🤝

J'aurai juste besoin de :
✓ Note verbale de votre Ministère/Organisation
✓ Photo d'identité
✓ Billet d'avion
✓ Certificat vaccination fièvre jaune

**Pas besoin** de justificatif d'hébergement ni de ressources.

On confirme ces infos ensemble ?",
        'en' => "🎖️ Wow, **{type}** passport! VIP welcome!

Great news {given_names}! You get **priority processing** and it's **free**. We're in this together! 🤝

I'll just need:
✓ Verbal note from your Ministry/Organization
✓ Passport photo
✓ Flight ticket
✓ Yellow fever vaccination certificate

**No need** for accommodation proof or financial resources.

Shall we confirm this info together?"
    ],
    
    'passport_ordinary_detected' => [
        'fr' => "✨ Super {given_names} ! J'ai bien lu votre passeport **{type}**.

📋 **Voici ce que j'ai trouvé :**
• Nom: **{surname}**
• Prénoms: **{given_names}**
• N° Passeport: **{passport_number}**
• Expire le: **{expiry_date}**
• Nationalité: **{nationality}**

Pour la suite, je vous demanderai :
✓ Lettre d'invitation légalisée
✓ Justificatif d'hébergement
✓ Preuve de ressources
✓ Billet d'avion
✓ Photo d'identité
✓ Certificat vaccination fièvre jaune

💰 Frais de visa : **{fees}**

Tout est correct ? On continue ? 🚀",
        'en' => "✨ Great {given_names}! I've read your **{type}** passport.

📋 **Here's what I found:**
• Surname: **{surname}**
• Given names: **{given_names}**
• Passport No: **{passport_number}**
• Expires: **{expiry_date}**
• Nationality: **{nationality}**

For the next steps, I'll need:
✓ Legalized invitation letter
✓ Accommodation proof
✓ Proof of resources
✓ Flight ticket
✓ Passport photo
✓ Yellow fever vaccination certificate

💰 Visa fees: **{fees}**

All correct? Shall we continue? 🚀"
    ],
    
    'passport_data_confirm' => [
        'fr' => 'Tout est correct ? On continue ? 🚀',
        'en' => 'All correct? Shall we continue? 🚀'
    ],
    
    'photo_request' => [
        'fr' => "Passons à votre **photo d'identité** ! 📸

Vous pouvez :
📷 Prendre une photo avec votre webcam
📤 Télécharger une photo existante

**Mes conseils pour une photo parfaite** :
• Fond blanc ou clair
• Visage bien centré et sourire léger 😊
• Regard vers l'objectif
• Pas de lunettes de soleil
• Photo récente (moins de 6 mois)",
        'en' => "Now let's get your **passport photo**! 📸

You can:
📷 Take a photo with your webcam
📤 Upload an existing photo

**My tips for a perfect photo**:
• White or light background
• Face well centered with a slight smile 😊
• Looking at the camera
• No sunglasses
• Recent photo (less than 6 months old)"
    ],
    
    'contact_request' => [
        'fr' => "J'ai maintenant besoin de vos **coordonnées**.

Quelle est votre adresse email ?",
        'en' => "I now need your **contact information**.

What is your email address?"
    ],
    
    'contact_phone_request' => [
        'fr' => "Quel est votre numéro de téléphone ? (avec indicatif pays, ex: +251...)",
        'en' => "What is your phone number? (with country code, e.g. +251...)"
    ],
    
    'contact_whatsapp' => [
        'fr' => "Ce numéro est-il joignable sur WhatsApp ?",
        'en' => "Is this number reachable on WhatsApp?"
    ],
    
    'trip_dates_request' => [
        'fr' => "Parlons de votre voyage en Côte d'Ivoire.

📅 Quelle est votre **date d'arrivée** prévue ?",
        'en' => "Let's talk about your trip to Côte d'Ivoire.

📅 What is your planned **arrival date**?"
    ],
    
    'trip_departure_request' => [
        'fr' => "Et votre **date de départ** prévue ?",
        'en' => "And your planned **departure date**?"
    ],
    
    'trip_purpose_request' => [
        'fr' => "Quel est le **motif** de votre voyage ?",
        'en' => "What is the **purpose** of your trip?"
    ],
    
    'trip_visa_type_request' => [
        'fr' => "Quel type de visa souhaitez-vous ?",
        'en' => "What type of visa would you like?"
    ],
    
    'trip_entries_request' => [
        'fr' => "Souhaitez-vous un visa à entrée unique ou multiple ?",
        'en' => "Would you like a single or multiple entry visa?"
    ],
    
    'accommodation_type_request' => [
        'fr' => "Où serez-vous hébergé(e) pendant votre séjour ?",
        'en' => "Where will you be staying during your visit?"
    ],
    
    'health_vaccination_question' => [
        'fr' => "⚠️ **Important** : La vaccination contre la **fièvre jaune** est **OBLIGATOIRE** pour entrer en Côte d'Ivoire.

Êtes-vous vacciné(e) contre la fièvre jaune ?",
        'en' => "⚠️ **Important**: **Yellow fever** vaccination is **MANDATORY** to enter Côte d'Ivoire.

Are you vaccinated against yellow fever?"
    ],
    
    'health_vaccination_required' => [
        'fr' => "❌ Sans vaccination contre la fièvre jaune, vous ne pourrez pas entrer en Côte d'Ivoire.

Je vous recommande de :
1. Vous faire vacciner dans un centre agréé
2. Attendre 10 jours après la vaccination (délai d'efficacité)
3. Revenir ensuite compléter votre demande de visa

Souhaitez-vous quand même continuer ?",
        'en' => "❌ Without yellow fever vaccination, you cannot enter Côte d'Ivoire.

I recommend you:
1. Get vaccinated at an approved center
2. Wait 10 days after vaccination (effectiveness period)
3. Come back to complete your visa application

Do you still want to continue?"
    ],
    
    'health_vaccination_upload' => [
        'fr' => "Parfait ! Veuillez télécharger votre **carnet de vaccination** (page avec le cachet fièvre jaune).",
        'en' => "Perfect! Please upload your **vaccination booklet** (page with yellow fever stamp)."
    ],
    
    'customs_declaration' => [
        'fr' => "Avant de finaliser, quelques questions sur les **douanes**.

Prévoyez-vous de transporter l'un des éléments suivants ?
• Animaux ou plantes
• Plus de 5 000 USD en devises
• Alcool ou tabac au-delà des franchises
• Marchandises à des fins commerciales",
        'en' => "Before finalizing, some questions about **customs**.

Do you plan to transport any of the following?
• Animals or plants
• More than 5,000 USD in currency
• Alcohol or tobacco beyond duty-free limits
• Goods for commercial purposes"
    ],
    
    'confirmation_recap' => [
        'fr' => "📋 **Récapitulatif de votre demande**

**Identité**
• Nom: {surname} {given_names}
• Nationalité: {nationality}
• Passeport: {passport_type} N°{passport_number}

**Voyage**
• Type de visa: {visa_type}
• Entrées: {entries}
• Du {arrival_date} au {departure_date}
• Motif: {trip_purpose}

**Frais**
{fees_detail}

Veuillez vérifier ces informations et confirmer votre demande.",
        'en' => "📋 **Application Summary**

**Identity**
• Name: {surname} {given_names}
• Nationality: {nationality}
• Passport: {passport_type} No.{passport_number}

**Travel**
• Visa type: {visa_type}
• Entries: {entries}
• From {arrival_date} to {departure_date}
• Purpose: {trip_purpose}

**Fees**
{fees_detail}

Please review this information and confirm your application."
    ],
    
    'confirmation_terms' => [
        'fr' => "Pour finaliser, veuillez accepter les conditions suivantes :

☐ Je certifie l'exactitude des informations fournies
☐ Je m'engage à ne pas exercer d'activité professionnelle non autorisée
☐ Je m'engage à quitter le territoire à l'expiration de mon visa",
        'en' => "To finalize, please accept the following terms:

☐ I certify the accuracy of the information provided
☐ I commit not to engage in unauthorized professional activities
☐ I commit to leave the territory upon visa expiration"
    ],
    
    'submission_success' => [
        'fr' => "🎉 **Bravo {given_names} ! Votre demande est soumise !**

C'est fait ! Vous avez réussi ! ✨

📧 Récépissé envoyé à **{email}**
📋 **Numéro de dossier : {application_number}**
⏱️ Délai estimé : **{processing_time}**

Suivez votre demande sur notre site avec ce numéro.

Ce fut un plaisir de vous accompagner ! À bientôt en Côte d'Ivoire... **Akwaba !** 🇨🇮🌴

— Aya, votre assistante visa",
        'en' => "🎉 **Well done {given_names}! Your application is submitted!**

You did it! Great job! ✨

📧 Receipt sent to **{email}**
📋 **File number: {application_number}**
⏱️ Estimated time: **{processing_time}**

Track your application on our website with this number.

It was a pleasure helping you! See you soon in Côte d'Ivoire... **Akwaba!** 🇨🇮🌴

— Aya, your visa assistant"
    ],
    
    'error_generic' => [
        'fr' => "Une erreur s'est produite. Veuillez réessayer ou contacter l'ambassade.",
        'en' => "An error occurred. Please try again or contact the embassy."
    ],
    
    'error_ocr_failed' => [
        'fr' => "Je n'ai pas pu lire automatiquement votre passeport. Cela peut arriver si :
• L'image est floue
• La zone MRZ est partiellement cachée
• L'éclairage est insuffisant

Souhaitez-vous :
🔄 Réessayer avec une nouvelle photo
✍️ Saisir les informations manuellement",
        'en' => "I couldn't automatically read your passport. This can happen if:
• The image is blurry
• The MRZ zone is partially hidden
• The lighting is insufficient

Would you like to:
🔄 Try again with a new photo
✍️ Enter the information manually"
    ],
    
    'quick_yes' => [
        'fr' => 'Oui',
        'en' => 'Yes'
    ],
    
    'quick_no' => [
        'fr' => 'Non',
        'en' => 'No'
    ],
    
    'quick_confirm' => [
        'fr' => '✅ Confirmer',
        'en' => '✅ Confirm'
    ],
    
    'quick_modify' => [
        'fr' => '✏️ Modifier',
        'en' => '✏️ Modify'
    ],
    
    'quick_retry' => [
        'fr' => '🔄 Réessayer',
        'en' => '🔄 Retry'
    ],
    
    'quick_manual' => [
        'fr' => '✍️ Saisie manuelle',
        'en' => '✍️ Manual entry'
    ],
    
    'entry_single' => [
        'fr' => 'Entrée unique',
        'en' => 'Single entry'
    ],
    
    'entry_multiple' => [
        'fr' => 'Entrées multiples',
        'en' => 'Multiple entries'
    ],
    
    'accommodation_hotel' => [
        'fr' => '🏨 Hôtel',
        'en' => '🏨 Hotel'
    ],
    
    'accommodation_private' => [
        'fr' => '🏠 Chez un particulier',
        'en' => '🏠 Private host'
    ],
    
    'language_french' => [
        'fr' => '🇫🇷 Français',
        'en' => '🇫🇷 French'
    ],
    
    'language_english' => [
        'fr' => '🇬🇧 English',
        'en' => '🇬🇧 English'
    ],
    
    // === Messages pour l'étape Documents Multi-Upload ===
    'documents_intro' => [
        'fr' => "📁 **Téléchargez vos documents**

Pour accélérer votre demande, vous pouvez télécharger tous vos documents en une seule fois. Notre IA les analysera automatiquement.

Cliquez sur le bouton ci-dessous pour ouvrir l'interface de téléchargement.",
        'en' => "📁 **Upload your documents**

To speed up your application, you can upload all your documents at once. Our AI will analyze them automatically.

Click the button below to open the upload interface."
    ],
    
    'documents_analysis_complete' => [
        'fr' => "✅ **Analyse terminée !**

J'ai extrait les données de vos documents :
{documents_summary}

**Score de cohérence global : {coherence_score}%**

Veuillez vérifier les informations extraites avant de continuer.",
        'en' => "✅ **Analysis complete!**

I've extracted data from your documents:
{documents_summary}

**Overall coherence score: {coherence_score}%**

Please review the extracted information before continuing."
    ],
    
    'documents_validation_warning' => [
        'fr' => "⚠️ **Attention** : Des incohérences ont été détectées entre vos documents :

{warnings}

Veuillez vérifier et corriger ces informations.",
        'en' => "⚠️ **Warning**: Inconsistencies were detected between your documents:

{warnings}

Please review and correct this information."
    ],
    
    'documents_validation_error' => [
        'fr' => "❌ **Problème détecté** :

{errors}

Veuillez corriger ces problèmes avant de continuer.",
        'en' => "❌ **Problem detected**:

{errors}

Please fix these issues before continuing."
    ],
    
    'documents_missing' => [
        'fr' => "📎 Il manque encore des documents requis :
{missing_list}

Veuillez les télécharger pour continuer.",
        'en' => "📎 Some required documents are still missing:
{missing_list}

Please upload them to continue."
    ],
    
    'documents_upload_button' => [
        'fr' => '📁 Télécharger mes documents',
        'en' => '📁 Upload my documents'
    ],
    
    'documents_verify_button' => [
        'fr' => '✅ Vérifier les données',
        'en' => '✅ Verify data'
    ],
    
    'documents_confirm' => [
        'fr' => '→ Confirmer et continuer',
        'en' => '→ Confirm and continue'
    ]
];

/**
 * Retourne un message traduit avec remplacement des placeholders
 */
function getMessage(string $key, string $lang = 'fr', array $replacements = []): string {
    $message = CHAT_MESSAGES[$key][$lang] ?? CHAT_MESSAGES[$key]['fr'] ?? '';
    
    foreach ($replacements as $placeholder => $value) {
        $message = str_replace('{' . $placeholder . '}', $value, $message);
    }
    
    return $message;
}

/**
 * Retourne les quick actions pour la sélection de langue
 */
function getLanguageQuickActions(): array {
    return [
        ['label' => '🇫🇷 Français', 'value' => 'fr'],
        ['label' => '🇬🇧 English', 'value' => 'en']
    ];
}

/**
 * Retourne les quick actions Oui/Non
 */
function getYesNoQuickActions(string $lang = 'fr'): array {
    return [
        ['label' => getMessage('quick_yes', $lang), 'value' => 'yes'],
        ['label' => getMessage('quick_no', $lang), 'value' => 'no']
    ];
}

/**
 * Retourne les quick actions Confirmer/Modifier
 */
function getConfirmModifyQuickActions(string $lang = 'fr'): array {
    return [
        ['label' => getMessage('quick_confirm', $lang), 'value' => 'confirm'],
        ['label' => getMessage('quick_modify', $lang), 'value' => 'modify']
    ];
}

/**
 * Retourne les quick actions pour les entrées visa
 */
function getEntryQuickActions(string $lang = 'fr'): array {
    return [
        ['label' => getMessage('entry_single', $lang), 'value' => 'Unique'],
        ['label' => getMessage('entry_multiple', $lang), 'value' => 'Multiple']
    ];
}

/**
 * Retourne les quick actions pour l'hébergement
 */
function getAccommodationQuickActions(string $lang = 'fr'): array {
    return [
        ['label' => getMessage('accommodation_hotel', $lang), 'value' => 'HOTEL'],
        ['label' => getMessage('accommodation_private', $lang), 'value' => 'PARTICULIER']
    ];
}

