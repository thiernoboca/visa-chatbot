/**
 * Internationalization Module
 * French and English translations
 *
 * @version 4.0.0
 * @module I18n
 */

const translations = {
    fr: {
        welcome: {
            greeting: "Akwaba ! 👋 Bienvenue à l'Ambassade de Côte d'Ivoire en Éthiopie.",
            intro: "Je suis votre assistant virtuel pour les demandes de visa. Je vais vous guider pas à pas dans votre démarche.",
            askCountry: "Pour démarrer votre demande de visa, dans quel pays résidez-vous actuellement ?",
            otherCountry: "Autre pays",
            searchPlaceholder: "Rechercher ou sélectionner votre pays...",
            secureNote: "Vos données sont chiffrées et sécurisées."
        },
        residence: {
            confirmed: "Parfait ! Vous résidez en",
            askNationality: "Quelle est votre nationalité ?",
            sameAsResidence: "Même nationalité que le pays de résidence",
            differentNationality: "Autre nationalité",
            needJustification: "En tant que non-national, vous devrez fournir un justificatif de résidence."
        },
        passport: {
            askUpload: "📷 Veuillez prendre en photo ou scanner la page d'identité de votre passeport.",
            tips: "Conseils pour une bonne image :",
            tip1: "Éclairage uniforme, pas de reflets",
            tip2: "Passeport à plat, bien cadré",
            tip3: "Image nette et lisible",
            uploadBtn: "📷 Prendre photo",
            chooseFile: "📁 Choisir fichier",
            analyzing: "Analyse en cours...",
            detected: "J'ai analysé votre passeport. Voici les informations extraites :",
            invalidType: "Document non accepté",
            diplomaticNote: "Note verbale requise pour les passeports diplomatiques"
        },
        photo: {
            askUpload: "Maintenant, j'ai besoin d'une photo d'identité récente.",
            requirements: "La photo doit respecter les normes ICAO :",
            req1: "Fond uni clair",
            req2: "Expression neutre",
            req3: "Yeux visibles",
            req4: "Pas de lunettes"
        },
        eligibility: {
            intro: "Avant de continuer, nous devons vérifier votre éligibilité.",
            vaccinationQuestion: "Disposez-vous d'un carnet de vaccination fièvre jaune valide ?",
            vaccinationRequired: "⚠️ Le carnet de vaccination contre la fièvre jaune est OBLIGATOIRE.",
            eligible: "✅ Parfait ! Vous êtes éligible pour continuer."
        },
        health: {
            askVaccination: "Avez-vous été vacciné contre la fièvre jaune ?",
            required: "⚠️ La vaccination contre la fièvre jaune est OBLIGATOIRE pour entrer en Côte d'Ivoire.",
            uploadCard: "Veuillez uploader votre carnet de vaccination."
        },
        customs: {
            title: "Déclaration Douanière",
            intro: "Quelques questions obligatoires. Merci de répondre honnêtement.",
            currency: "Transportez-vous plus de 10 000 € ou équivalent ?",
            goods: "Transportez-vous des marchandises destinées à la vente ?",
            plants: "Alimentation, végétaux, animaux ou produits dérivés ?",
            meds: "Transportez-vous des médicaments soumis à prescription ?",
            declaration: "Je certifie l'exactitude de ces déclarations."
        },
        confirm: {
            title: "Récapitulatif",
            review: "Veuillez vérifier vos informations avant de soumettre.",
            submit: "Soumettre la demande",
            success: "🎉 Votre demande a été soumise avec succès !",
            reference: "Numéro de référence :"
        },
        common: {
            yes: "Oui",
            no: "Non",
            next: "Continuer",
            back: "Retour",
            edit: "Modifier",
            confirm: "Confirmer",
            cancel: "Annuler",
            send: "Envoyer",
            upload: "Uploader",
            step: "Étape",
            loading: "Chargement...",
            error: "Erreur",
            success: "Succès"
        },
        upload: {
            tooLarge: "Fichier trop volumineux (max 10MB)",
            unsupported: "Format non supporté. Utilisez JPG, PNG ou PDF.",
            analyzing: "Analyse en cours...",
            error: "Erreur lors de l'analyse. Veuillez réessayer.",
            retry: "Réessayer",
            dragDrop: "Glissez-déposez le fichier ici",
            clickToUpload: "Cliquez pour télécharger",
            orUseCamera: "ou",
            scanWithCamera: "Scanner avec la caméra",
            takePhoto: "Prendre une photo",
            useWebcam: "Utiliser la webcam",
            useMobile: "Scanner sur mobile"
        },
        errors: {
            network: "Erreur de connexion. Vérifiez votre connexion internet.",
            timeout: "L'analyse prend trop de temps. Essayez avec une image plus nette.",
            invalidFormat: "Ce format de fichier n'est pas accepté.",
            blurryImage: "L'image semble floue. Prenez une nouvelle photo.",
            unreadable: "Impossible de lire le document.",
            generic: "Une erreur est survenue. Veuillez réessayer."
        },
        steps: {
            welcome: "Accueil",
            residence: "Résidence",
            residence_card: "Titre de séjour",
            passport: "Passeport",
            ticket: "Billet d'avion",
            hotel: "Hébergement",
            accommodation: "Attestation hébergement",
            vaccination: "Vaccination",
            invitation: "Invitation",
            verbal_note: "Note verbale",
            eligibility: "Éligibilité",
            minor_auth: "Documents mineur",
            transit: "Transit",
            photo: "Photo",
            contact: "Contact",
            trip: "Voyage",
            health: "Santé",
            customs: "Douanes",
            payment: "Paiement",
            confirm: "Confirmation",
            signature: "Signature",
            completion: "Terminé"
        },
        ticket: {
            askUpload: "Veuillez fournir votre billet d'avion ou réservation de vol.",
            noBillet: "Je n'ai pas encore de billet",
            askDates: "Quelles sont vos dates de voyage prévues?",
            extracted: "J'ai extrait les informations de votre billet :",
            nameMismatch: "Le nom sur le billet diffère de votre passeport. Est-ce correct?"
        },
        hotel: {
            askUpload: "Veuillez fournir votre réservation d'hôtel.",
            privateHost: "Je serai hébergé par un proche",
            other: "Autre type d'hébergement",
            extracted: "Voici les informations de votre réservation :"
        },
        vaccination: {
            askUpload: "Veuillez fournir votre certificat de vaccination fièvre jaune.",
            exempt: "Vous êtes exempté de vaccination fièvre jaune.",
            exemption: "J'ai une exemption médicale",
            validSince2016: "Note: La vaccination fièvre jaune est valide à vie depuis 2016 (OMS).",
            invalid: "Votre vaccination doit être effectuée au moins 10 jours avant le voyage."
        },
        invitation: {
            askUpload: "Veuillez fournir la lettre d'invitation.",
            askHasInvitation: "Avez-vous une lettre d'invitation d'un résident?",
            extracted: "Voici les informations de l'invitation :"
        },
        verbal_note: {
            askUpload: "Veuillez fournir la Note Verbale de votre Ministère.",
            required: "Une Note Verbale est requise pour les passeports diplomatiques/service.",
            extracted: "Voici les informations de la Note Verbale :"
        },
        minor: {
            detected: "Je vois que vous avez moins de 18 ans. Des documents supplémentaires sont requis.",
            parentalAuth: "Autorisation parentale de sortie du territoire",
            birthCert: "Acte de naissance",
            parentId: "Pièce d'identité du parent/tuteur légal",
            singleParent: "Si un seul parent: attestation de garde exclusive"
        },
        transit: {
            askDestination: "Quelle est votre destination finale?",
            askDuration: "Combien de temps durera votre transit?",
            maxDuration: "Le transit est limité à 72 heures maximum.",
            askContinuation: "Veuillez fournir votre billet vers la destination finale."
        },
        payment: {
            title: "Paiement des frais de visa",
            summary: "Récapitulatif des frais",
            visaFee: "Frais de visa",
            serviceFee: "Frais de service",
            total: "Total",
            methods: "Moyens de paiement",
            card: "Carte bancaire",
            mobileMoney: "Mobile Money",
            success: "Paiement reçu!",
            reference: "Référence de paiement"
        },
        signature: {
            title: "Signature électronique",
            declaration: "Je certifie que les informations fournies sont exactes et complètes.",
            warning: "Je comprends qu'une fausse déclaration peut entraîner le refus ou l'annulation de mon visa.",
            terms: "J'accepte les conditions générales",
            sign: "Signez ici"
        },
        completion: {
            success: "Félicitations! Votre demande a été soumise avec succès!",
            reference: "Numéro de dossier",
            submitted: "Date de soumission",
            processing: "Délai de traitement estimé",
            days: "jours ouvrables",
            nextSteps: "Prochaines étapes",
            step1: "Vérification par nos services",
            step2: "Notification par email du statut",
            step3: "Téléchargement du visa électronique si approuvé",
            downloadReceipt: "Télécharger le récépissé",
            trackApplication: "Suivre ma demande"
        },
        validation: {
            checking: "Vérification de votre éligibilité...",
            eligible: "Excellente nouvelle! Vous êtes éligible au visa.",
            missingDocs: "Il manque les documents suivants:",
            inconsistency: "J'ai détecté une incohérence:",
            nameVariant: "C'est correct, juste une variante",
            correctDoc: "Corriger le document",
            coherenceScore: "Score de cohérence"
        }
    },
    en: {
        welcome: {
            greeting: "Akwaba! 👋 Welcome to the Embassy of Côte d'Ivoire in Ethiopia.",
            intro: "I'm your virtual assistant for visa applications. I'll guide you step by step through the process.",
            askCountry: "To start your visa application, which country do you currently reside in?",
            otherCountry: "Other country",
            searchPlaceholder: "Search or select your country...",
            secureNote: "Your data is encrypted and secure."
        },
        residence: {
            confirmed: "Great! You reside in",
            askNationality: "What is your nationality?",
            sameAsResidence: "Same nationality as country of residence",
            differentNationality: "Different nationality",
            needJustification: "As a non-national, you will need to provide proof of residence."
        },
        passport: {
            askUpload: "📷 Please take a photo or scan your passport's identity page.",
            tips: "Tips for a good image:",
            tip1: "Uniform lighting, no reflections",
            tip2: "Passport flat, well-framed",
            tip3: "Sharp and readable image",
            uploadBtn: "📷 Take photo",
            chooseFile: "📁 Choose file",
            analyzing: "Analyzing...",
            detected: "I've analyzed your passport. Here's the extracted information:",
            invalidType: "Document not accepted",
            diplomaticNote: "Verbal note required for diplomatic passports"
        },
        photo: {
            askUpload: "Now, I need a recent ID photo.",
            requirements: "The photo must meet ICAO standards:",
            req1: "Light plain background",
            req2: "Neutral expression",
            req3: "Eyes visible",
            req4: "No glasses"
        },
        eligibility: {
            intro: "Before continuing, we need to verify your eligibility.",
            vaccinationQuestion: "Do you have a valid yellow fever vaccination card?",
            vaccinationRequired: "⚠️ Yellow fever vaccination is MANDATORY.",
            eligible: "✅ Perfect! You are eligible to continue."
        },
        health: {
            askVaccination: "Have you been vaccinated against yellow fever?",
            required: "⚠️ Yellow fever vaccination is MANDATORY to enter Côte d'Ivoire.",
            uploadCard: "Please upload your vaccination card."
        },
        customs: {
            title: "Customs Declaration",
            intro: "A few mandatory questions. Please answer honestly.",
            currency: "Are you carrying more than €10,000 or equivalent?",
            goods: "Are you carrying goods for sale?",
            plants: "Food, plants, animals or derived products?",
            meds: "Are you carrying prescription medications?",
            declaration: "I certify the accuracy of these declarations."
        },
        confirm: {
            title: "Summary",
            review: "Please verify your information before submitting.",
            submit: "Submit application",
            success: "🎉 Your application has been successfully submitted!",
            reference: "Reference number:"
        },
        common: {
            yes: "Yes",
            no: "No",
            next: "Continue",
            back: "Back",
            edit: "Edit",
            confirm: "Confirm",
            cancel: "Cancel",
            send: "Send",
            upload: "Upload",
            step: "Step",
            loading: "Loading...",
            error: "Error",
            success: "Success"
        },
        upload: {
            tooLarge: "File too large (max 10MB)",
            unsupported: "Unsupported format. Use JPG, PNG or PDF.",
            analyzing: "Analyzing...",
            error: "Analysis error. Please try again.",
            retry: "Retry",
            dragDrop: "Drag and drop file here",
            clickToUpload: "Click to upload",
            orUseCamera: "or",
            scanWithCamera: "Scan with camera",
            takePhoto: "Take a photo",
            useWebcam: "Use webcam",
            useMobile: "Scan on mobile"
        },
        errors: {
            network: "Connection error. Check your internet connection.",
            timeout: "Analysis is taking too long. Try with a clearer image.",
            invalidFormat: "This file format is not accepted.",
            blurryImage: "The image seems blurry. Take a new photo.",
            unreadable: "Unable to read the document.",
            generic: "An error occurred. Please try again."
        },
        steps: {
            welcome: "Welcome",
            residence: "Residence",
            residence_card: "Residence permit",
            passport: "Passport",
            ticket: "Flight ticket",
            hotel: "Accommodation",
            accommodation: "Accommodation letter",
            vaccination: "Vaccination",
            invitation: "Invitation",
            verbal_note: "Verbal note",
            eligibility: "Eligibility",
            minor_auth: "Minor documents",
            transit: "Transit",
            photo: "Photo",
            contact: "Contact",
            trip: "Trip",
            health: "Health",
            customs: "Customs",
            payment: "Payment",
            confirm: "Confirmation",
            signature: "Signature",
            completion: "Complete"
        },
        ticket: {
            askUpload: "Please provide your flight ticket or booking confirmation.",
            noBillet: "I don't have a ticket yet",
            askDates: "What are your planned travel dates?",
            extracted: "I've extracted the information from your ticket:",
            nameMismatch: "The name on the ticket differs from your passport. Is this correct?"
        },
        hotel: {
            askUpload: "Please provide your hotel reservation.",
            privateHost: "I'll be staying with a relative/friend",
            other: "Other type of accommodation",
            extracted: "Here's the information from your reservation:"
        },
        vaccination: {
            askUpload: "Please provide your yellow fever vaccination certificate.",
            exempt: "You are exempt from yellow fever vaccination.",
            exemption: "I have a medical exemption",
            validSince2016: "Note: Yellow fever vaccination is valid for life since 2016 (WHO).",
            invalid: "Your vaccination must be done at least 10 days before travel."
        },
        invitation: {
            askUpload: "Please provide the invitation letter.",
            askHasInvitation: "Do you have an invitation letter from a resident?",
            extracted: "Here's the information from the invitation:"
        },
        verbal_note: {
            askUpload: "Please provide the Verbal Note from your Ministry.",
            required: "A Verbal Note is required for diplomatic/service passports.",
            extracted: "Here's the information from the Verbal Note:"
        },
        minor: {
            detected: "I see you are under 18. Additional documents are required.",
            parentalAuth: "Parental authorization for travel",
            birthCert: "Birth certificate",
            parentId: "Parent/guardian ID",
            singleParent: "If single parent: custody certificate"
        },
        transit: {
            askDestination: "What is your final destination?",
            askDuration: "How long will your transit be?",
            maxDuration: "Transit is limited to 72 hours maximum.",
            askContinuation: "Please provide your ticket to the final destination."
        },
        payment: {
            title: "Visa fee payment",
            summary: "Fee summary",
            visaFee: "Visa fee",
            serviceFee: "Service fee",
            total: "Total",
            methods: "Payment methods",
            card: "Credit/Debit card",
            mobileMoney: "Mobile Money",
            success: "Payment received!",
            reference: "Payment reference"
        },
        signature: {
            title: "Electronic signature",
            declaration: "I certify that the information provided is accurate and complete.",
            warning: "I understand that false declaration may result in visa denial or cancellation.",
            terms: "I accept the terms and conditions",
            sign: "Sign here"
        },
        completion: {
            success: "Congratulations! Your application has been submitted successfully!",
            reference: "Application number",
            submitted: "Submission date",
            processing: "Estimated processing time",
            days: "business days",
            nextSteps: "Next steps",
            step1: "Verification by our services",
            step2: "Email notification of status",
            step3: "Download electronic visa if approved",
            downloadReceipt: "Download receipt",
            trackApplication: "Track my application"
        },
        validation: {
            checking: "Checking your eligibility...",
            eligible: "Great news! You are eligible for the visa.",
            missingDocs: "The following documents are missing:",
            inconsistency: "I detected an inconsistency:",
            nameVariant: "It's correct, just a variant",
            correctDoc: "Correct the document",
            coherenceScore: "Coherence score"
        }
    }
};

/**
 * I18n class for managing translations
 */
export class I18n {
    constructor(language = 'fr') {
        this.language = language;
        this.translations = translations;
    }

    /**
     * Set current language
     * @param {string} lang - 'fr' or 'en'
     */
    setLanguage(lang) {
        if (lang === 'fr' || lang === 'en') {
            this.language = lang;
        }
    }

    /**
     * Get current language
     * @returns {string}
     */
    getLanguage() {
        return this.language;
    }

    /**
     * Toggle between languages
     * @returns {string} New language
     */
    toggleLanguage() {
        this.language = this.language === 'fr' ? 'en' : 'fr';
        return this.language;
    }

    /**
     * Get translation by key path
     * @param {string} path - Dot-separated path (e.g., 'welcome.greeting')
     * @param {Object} params - Optional parameters for interpolation
     * @returns {string}
     */
    t(path, params = {}) {
        const keys = path.split('.');
        let value = this.translations[this.language];

        for (const key of keys) {
            if (value && typeof value === 'object' && key in value) {
                value = value[key];
            } else {
                console.warn(`Translation not found: ${path}`);
                return path;
            }
        }

        // Interpolate parameters
        if (typeof value === 'string' && Object.keys(params).length > 0) {
            return value.replace(/\{(\w+)\}/g, (match, key) => {
                return params[key] !== undefined ? params[key] : match;
            });
        }

        return value;
    }

    /**
     * Get time-based greeting
     * @returns {string}
     */
    getGreeting() {
        const hour = new Date().getHours();
        if (hour >= 5 && hour < 12) {
            return this.language === 'fr' ? 'Bonjour' : 'Good morning';
        } else if (hour >= 12 && hour < 18) {
            return this.language === 'fr' ? 'Bon après-midi' : 'Good afternoon';
        } else {
            return this.language === 'fr' ? 'Bonsoir' : 'Good evening';
        }
    }

    /**
     * Format date according to language
     * @param {Date|string} date
     * @returns {string}
     */
    formatDate(date) {
        const d = new Date(date);
        return d.toLocaleDateString(this.language === 'fr' ? 'fr-FR' : 'en-US', {
            day: '2-digit',
            month: 'long',
            year: 'numeric'
        });
    }

    /**
     * Format time according to language
     * @param {Date|string} date
     * @returns {string}
     */
    formatTime(date = new Date()) {
        const d = new Date(date);
        return d.toLocaleTimeString(this.language === 'fr' ? 'fr-FR' : 'en-US', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }
}

// Export singleton instance
export const i18n = new I18n();
export default i18n;
