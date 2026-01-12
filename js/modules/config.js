/**
 * Chatbot Configuration Module
 * Centralized configuration and constants
 *
 * @version 4.0.0
 * @module Config
 */

export const CONFIG = {
    // API Endpoints
    endpoints: {
        api: 'index.php?action=api',
        upload: 'index.php?action=upload',
        session: 'index.php?action=api', // Session is managed via API
        // Use new OCR Integration Service with fixed extractors
        ocr: 'index.php?action=upload',
        coherence: 'index.php?action=validate'
    },

    // Default settings
    defaults: {
        language: 'fr',
        darkMode: false,
        debug: false,
        enableTypewriter: true,
        typewriterSpeed: 15,
        enableMicroInteractions: true
    },

    // Feature flags for redesign integration
    features: {
        inlineEditing: {
            enabled: true,  // Activé pour tests
            abTestVariant: 'inline', // 'control' ou 'inline'
            rolloutPercentage: 100,  // 0% → 10% → 25% → 50% → 100%
            // NEW: Mode de confirmation après OCR
            // 'inline' = boutons dans le chat, 'actionArea' = boutons dans zone d'action, 'modal' = modal VerificationModal
            confirmationMode: 'actionArea',
            // NEW: Mode d'édition des données OCR
            // 'inline' = formulaire dans le chat, 'modal' = modal VerificationModal
            editMode: 'inline'
        },
        glassmorphismUI: {
            enabled: false,
            modernStyling: true
        },
        innovatricsCamera: {
            enabled: true,  // Activé pour utiliser Innovatrics
            desktopCapture: true,
            mobileQRCapture: true,
            // NEW: Utiliser openChoiceModal() au lieu de l'overlay
            useChoiceModal: true
        },
        // NEW: Configuration du suivi de progression
        progressTracking: {
            // 'steps' = basé sur les étapes, 'percentage' = pourcentage direct
            mode: 'percentage',
            showPercentage: true,
            showStepIndicators: true
        }
    },

    // File upload limits
    upload: {
        maxSize: 10 * 1024 * 1024, // 10MB
        acceptedTypes: ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        acceptedExtensions: '.pdf,.jpg,.jpeg,.png,.webp'
    }
};

/**
 * Persona configuration for the chatbot
 */
export const PERSONA = {
    name: 'Aya',
    avatar: '🇨🇮',
    greetings: {
        fr: ['Akwaba !', 'Bienvenue !', 'Salut !'],
        en: ['Akwaba!', 'Welcome!', 'Hello!']
    },
    encouragements: {
        fr: ['C\'est super !', 'On avance bien !', 'Parfait !'],
        en: ['Great!', 'Good progress!', 'Perfect!']
    },
    celebrations: {
        fr: ['🎉 Bravo !', '✨ Excellent !', '🌟 Magnifique !'],
        en: ['🎉 Great job!', '✨ Excellent!', '🌟 Wonderful!']
    }
};

/**
 * Workflow steps configuration
 */
export const STEPS = [
    { id: 'welcome', labelFr: 'Accueil', labelEn: 'Welcome', icon: 'waving_hand', progress: 10 },
    { id: 'passport', labelFr: 'Passeport', labelEn: 'Passport', icon: 'badge', progress: 20 },
    { id: 'residence', labelFr: 'Résidence', labelEn: 'Residence', icon: 'location_on', progress: 30 },
    { id: 'eligibility', labelFr: 'Éligibilité', labelEn: 'Eligibility', icon: 'verified_user', progress: 40 },
    { id: 'photo', labelFr: 'Photo', labelEn: 'Photo', icon: 'photo_camera', progress: 50 },
    { id: 'contact', labelFr: 'Contact', labelEn: 'Contact', icon: 'contact_mail', progress: 60 },
    { id: 'trip', labelFr: 'Voyage', labelEn: 'Trip', icon: 'flight', progress: 70 },
    { id: 'health', labelFr: 'Santé', labelEn: 'Health', icon: 'vaccines', progress: 80 },
    { id: 'customs', labelFr: 'Douanes', labelEn: 'Customs', icon: 'inventory_2', progress: 90 },
    { id: 'confirm', labelFr: 'Confirmation', labelEn: 'Confirm', icon: 'task_alt', progress: 100 }
];

/**
 * Jurisdiction countries covered by the embassy
 */
export const JURISDICTION_COUNTRIES = [
    { code: 'ETH', nameFr: 'Éthiopie', nameEn: 'Ethiopia', flag: '🇪🇹', consulate: 'ADDIS_ABEBA' },
    { code: 'KEN', nameFr: 'Kenya', nameEn: 'Kenya', flag: '🇰🇪', consulate: 'NAIROBI' },
    { code: 'DJI', nameFr: 'Djibouti', nameEn: 'Djibouti', flag: '🇩🇯', consulate: 'DJIBOUTI' },
    { code: 'TZA', nameFr: 'Tanzanie', nameEn: 'Tanzania', flag: '🇹🇿', consulate: 'NAIROBI' },
    { code: 'UGA', nameFr: 'Ouganda', nameEn: 'Uganda', flag: '🇺🇬', consulate: 'ADDIS_ABEBA' },
    { code: 'SSD', nameFr: 'Soudan du Sud', nameEn: 'South Sudan', flag: '🇸🇸', consulate: 'ADDIS_ABEBA' },
    { code: 'SOM', nameFr: 'Somalie', nameEn: 'Somalia', flag: '🇸🇴', consulate: 'DJIBOUTI' }
];

/**
 * Document types configuration
 * supportsCamera: true = permet la capture via Innovatrics DOT
 * cameraType: 'document' = capture document, 'face' = capture visage
 */
export const DOCUMENT_TYPES = {
    passport: { nameFr: 'Passeport', nameEn: 'Passport', icon: '🛂', priority: 1, required: true, supportsCamera: true, cameraType: 'document' },
    ticket: { nameFr: 'Billet d\'avion', nameEn: 'Flight Ticket', icon: '✈️', priority: 2, required: true, supportsCamera: true, cameraType: 'document' },
    hotel: { nameFr: 'Réservation hôtel', nameEn: 'Hotel Reservation', icon: '🏨', priority: 3, required: true, supportsCamera: true, cameraType: 'document' },
    vaccination: { nameFr: 'Carnet vaccinal', nameEn: 'Vaccination Card', icon: '💉', priority: 4, required: true, supportsCamera: true, cameraType: 'document' },
    invitation: { nameFr: 'Lettre d\'invitation', nameEn: 'Invitation Letter', icon: '📄', priority: 5, required: false, supportsCamera: true, cameraType: 'document' },
    verbal_note: { nameFr: 'Note verbale', nameEn: 'Verbal Note', icon: '💼', priority: 6, required: false, supportsCamera: true, cameraType: 'document' },
    residence_card: { nameFr: 'Carte de séjour', nameEn: 'Residence Card', icon: '🪪', priority: 7, required: false, supportsCamera: true, cameraType: 'document' },
    accommodation: { nameFr: 'Attestation d\'hébergement', nameEn: 'Accommodation Certificate', icon: '🏠', priority: 8, required: false, supportsCamera: true, cameraType: 'document' },
    financial_proof: { nameFr: 'Justificatif de ressources', nameEn: 'Financial Proof', icon: '💰', priority: 9, required: false, supportsCamera: true, cameraType: 'document' },
    photo: { nameFr: 'Photo d\'identité', nameEn: 'ID Photo', icon: '📷', priority: 10, required: true, supportsCamera: true, cameraType: 'face' }
};

/**
 * Passport requirements matrix by passport type
 */
export const PASSPORT_REQUIREMENTS = {
    ORDINAIRE: {
        workflow: 'STANDARD',
        required: ['passport', 'ticket', 'vaccination', 'accommodation', 'financial_proof', 'invitation'],
        conditional: ['hotel'],
        optional: ['residence_card'],
        fees: true,
        feeAmount: '73,000 XOF',
        processingDays: '5-10 jours'
    },
    OFFICIEL: {
        workflow: 'STANDARD',
        required: ['passport', 'ticket', 'vaccination', 'accommodation', 'financial_proof', 'invitation'],
        conditional: ['hotel'],
        optional: ['residence_card'],
        fees: true,
        feeAmount: '73,000 XOF',
        processingDays: '5-10 jours'
    },
    DIPLOMATIQUE: {
        workflow: 'PRIORITY',
        required: ['passport', 'ticket', 'verbal_note', 'vaccination'],
        conditional: [],
        optional: [],
        fees: false,
        feeAmount: 'GRATUIT',
        processingDays: '24-48h'
    },
    SERVICE: {
        workflow: 'PRIORITY',
        required: ['passport', 'ticket', 'verbal_note', 'vaccination'],
        conditional: [],
        optional: [],
        fees: false,
        feeAmount: 'GRATUIT',
        processingDays: '24-48h'
    },
    LP_ONU: {
        workflow: 'PRIORITY',
        required: ['passport', 'ticket', 'verbal_note'],
        conditional: [],
        optional: ['vaccination'],
        fees: false,
        feeAmount: 'GRATUIT',
        processingDays: '24-48h'
    },
    LP_UA: {
        workflow: 'PRIORITY',
        required: ['passport', 'ticket', 'verbal_note'],
        conditional: [],
        optional: ['vaccination'],
        fees: false,
        feeAmount: 'GRATUIT',
        processingDays: '24-48h'
    },
    LP_INTERNATIONAL: {
        workflow: 'PRIORITY',
        required: ['passport', 'ticket', 'verbal_note'],
        conditional: [],
        optional: ['vaccination'],
        fees: false,
        feeAmount: 'GRATUIT',
        processingDays: '24-48h'
    }
};

/**
 * Extraction progress stages
 */
export const EXTRACTION_STAGES = [
    { progress: 10, textFr: 'Envoi du document...', textEn: 'Uploading document...', icon: '📤' },
    { progress: 30, textFr: 'Lecture du document...', textEn: 'Reading document...', icon: '🔍' },
    { progress: 60, textFr: 'Extraction des données...', textEn: 'Extracting data...', icon: '⚙️' },
    { progress: 85, textFr: 'Validation...', textEn: 'Validating...', icon: '✅' },
    { progress: 100, textFr: 'Terminé !', textEn: 'Complete!', icon: '🎉' }
];

// Default export
export default CONFIG;
