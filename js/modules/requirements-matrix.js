/**
 * Requirements Matrix Module
 * Defines document requirements based on passport type and conditions
 *
 * @version 1.0.0
 * Based on PRD Section 7 - Matrice des Exigences par Type de Passeport
 */

/**
 * Document requirement statuses
 */
export const RequirementStatus = {
    REQUIRED: 'required',      // Must be provided
    OPTIONAL: 'optional',      // Can be skipped with explicit confirmation
    CONDITIONAL: 'conditional', // Required if condition is met
    NOT_APPLICABLE: 'not_applicable', // Not needed for this workflow
    RECOMMENDED: 'recommended'  // Suggested but not required
};

/**
 * Passport types
 */
export const PassportType = {
    ORDINAIRE: 'ORDINAIRE',
    DIPLOMATIQUE: 'DIPLOMATIQUE',
    SERVICE: 'SERVICE',
    LP_ONU: 'LP_ONU',       // Laissez-passer ONU
    LP_UA: 'LP_UA',         // Laissez-passer UA
    SPECIAL: 'SPECIAL',
    REFUGIE: 'REFUGIE',
    APATRIDE: 'APATRIDE'
};

/**
 * Workflow categories
 */
export const WorkflowCategory = {
    STANDARD: 'STANDARD',   // 5-10 days, paid
    PRIORITY: 'PRIORITY'    // 24-48h, free
};

/**
 * Document types with metadata
 */
export const DocumentTypes = {
    PASSPORT: {
        code: 'passport',
        icon: '🛂',
        priority: 1,
        nameKey: 'doc_passport',
        formats: ['image/jpeg', 'image/png', 'application/pdf', 'image/webp'],
        maxSize: 10 * 1024 * 1024, // 10MB
        ocrSupported: true
    },
    PHOTO: {
        code: 'photo',
        icon: '📷',
        priority: 2,
        nameKey: 'doc_photo',
        formats: ['image/jpeg', 'image/png'],
        maxSize: 5 * 1024 * 1024, // 5MB
        ocrSupported: false,
        dimensions: { minWidth: 400, minHeight: 500, ratio: 0.8 }
    },
    TICKET: {
        code: 'ticket',
        icon: '✈️',
        priority: 3,
        nameKey: 'doc_ticket',
        formats: ['application/pdf', 'image/jpeg', 'image/png'],
        maxSize: 10 * 1024 * 1024,
        ocrSupported: true
    },
    HOTEL: {
        code: 'hotel',
        icon: '🏨',
        priority: 4,
        nameKey: 'doc_hotel',
        formats: ['application/pdf', 'image/jpeg', 'image/png'],
        maxSize: 10 * 1024 * 1024,
        ocrSupported: true
    },
    VACCINATION: {
        code: 'vaccination',
        icon: '💉',
        priority: 5,
        nameKey: 'doc_vaccination',
        formats: ['image/jpeg', 'image/png', 'application/pdf'],
        maxSize: 10 * 1024 * 1024,
        ocrSupported: true
    },
    INVITATION: {
        code: 'invitation',
        icon: '📄',
        priority: 6,
        nameKey: 'doc_invitation',
        formats: ['application/pdf', 'image/jpeg', 'image/png'],
        maxSize: 10 * 1024 * 1024,
        ocrSupported: true
    },
    VERBAL_NOTE: {
        code: 'verbal_note',
        icon: '💼',
        priority: 7,
        nameKey: 'doc_verbal_note',
        formats: ['application/pdf', 'image/jpeg', 'image/png'],
        maxSize: 10 * 1024 * 1024,
        ocrSupported: true
    },
    RESIDENCE_CARD: {
        code: 'residence_card',
        icon: '🪪',
        priority: 8,
        nameKey: 'doc_residence_card',
        formats: ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        maxSize: 10 * 1024 * 1024,
        ocrSupported: true
    },
    FINANCIAL_PROOF: {
        code: 'financial_proof',
        icon: '💰',
        priority: 9,
        nameKey: 'doc_financial_proof',
        formats: ['application/pdf', 'image/jpeg', 'image/png'],
        maxSize: 10 * 1024 * 1024,
        ocrSupported: true
    },
    ACCOMMODATION: {
        code: 'accommodation',
        icon: '🏠',
        priority: 10,
        nameKey: 'doc_accommodation',
        formats: ['application/pdf', 'image/jpeg', 'image/png'],
        maxSize: 10 * 1024 * 1024,
        ocrSupported: true
    },
    PAYMENT_PROOF: {
        code: 'payment_proof',
        icon: '🧾',
        priority: 11,
        nameKey: 'doc_payment_proof',
        formats: ['application/pdf', 'image/jpeg', 'image/png'],
        maxSize: 10 * 1024 * 1024,
        ocrSupported: true
    },
    PARENTAL_AUTH: {
        code: 'parental_auth',
        icon: '👨‍👩‍👧',
        priority: 12,
        nameKey: 'doc_parental_auth',
        formats: ['application/pdf', 'image/jpeg', 'image/png'],
        maxSize: 10 * 1024 * 1024,
        ocrSupported: true
    },
    BIRTH_CERTIFICATE: {
        code: 'birth_certificate',
        icon: '📜',
        priority: 13,
        nameKey: 'doc_birth_certificate',
        formats: ['application/pdf', 'image/jpeg', 'image/png'],
        maxSize: 10 * 1024 * 1024,
        ocrSupported: true
    },
    PARENT_ID: {
        code: 'parent_id',
        icon: '🪪',
        priority: 14,
        nameKey: 'doc_parent_id',
        formats: ['image/jpeg', 'image/png', 'application/pdf'],
        maxSize: 10 * 1024 * 1024,
        ocrSupported: true
    },
    HOST_ID: {
        code: 'host_id',
        icon: '🪪',
        priority: 15,
        nameKey: 'doc_host_id',
        formats: ['image/jpeg', 'image/png', 'application/pdf'],
        maxSize: 10 * 1024 * 1024,
        ocrSupported: true
    }
};

/**
 * Base requirements matrix by passport type
 * Based on PRD Section 7.1
 */
const BASE_REQUIREMENTS_MATRIX = {
    // ORDINAIRE passport - Standard workflow, paid
    [PassportType.ORDINAIRE]: {
        workflowCategory: WorkflowCategory.STANDARD,
        processingDays: { min: 5, max: 10 },
        expressPossible: true,
        expressProcessingDays: { min: 1, max: 2 },
        requiresPayment: true,
        documents: {
            [DocumentTypes.PASSPORT.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.PHOTO.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.TICKET.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.HOTEL.code]: RequirementStatus.CONDITIONAL, // OR accommodation
            [DocumentTypes.ACCOMMODATION.code]: RequirementStatus.CONDITIONAL, // OR hotel
            [DocumentTypes.INVITATION.code]: RequirementStatus.OPTIONAL,
            [DocumentTypes.VACCINATION.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.FINANCIAL_PROOF.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.VERBAL_NOTE.code]: RequirementStatus.NOT_APPLICABLE,
            [DocumentTypes.RESIDENCE_CARD.code]: RequirementStatus.CONDITIONAL,
            [DocumentTypes.PAYMENT_PROOF.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.PARENTAL_AUTH.code]: RequirementStatus.CONDITIONAL,
            [DocumentTypes.BIRTH_CERTIFICATE.code]: RequirementStatus.CONDITIONAL,
            [DocumentTypes.PARENT_ID.code]: RequirementStatus.CONDITIONAL,
            [DocumentTypes.HOST_ID.code]: RequirementStatus.CONDITIONAL
        }
    },

    // DIPLOMATIQUE passport - Priority workflow, free
    [PassportType.DIPLOMATIQUE]: {
        workflowCategory: WorkflowCategory.PRIORITY,
        processingDays: { min: 1, max: 2 },
        expressPossible: false, // Already priority
        requiresPayment: false,
        documents: {
            [DocumentTypes.PASSPORT.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.PHOTO.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.TICKET.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.HOTEL.code]: RequirementStatus.NOT_APPLICABLE,
            [DocumentTypes.ACCOMMODATION.code]: RequirementStatus.NOT_APPLICABLE,
            [DocumentTypes.INVITATION.code]: RequirementStatus.OPTIONAL,
            [DocumentTypes.VACCINATION.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.FINANCIAL_PROOF.code]: RequirementStatus.NOT_APPLICABLE,
            [DocumentTypes.VERBAL_NOTE.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.RESIDENCE_CARD.code]: RequirementStatus.CONDITIONAL,
            [DocumentTypes.PAYMENT_PROOF.code]: RequirementStatus.NOT_APPLICABLE,
            [DocumentTypes.PARENTAL_AUTH.code]: RequirementStatus.CONDITIONAL,
            [DocumentTypes.BIRTH_CERTIFICATE.code]: RequirementStatus.CONDITIONAL,
            [DocumentTypes.PARENT_ID.code]: RequirementStatus.CONDITIONAL,
            [DocumentTypes.HOST_ID.code]: RequirementStatus.NOT_APPLICABLE
        }
    },

    // SERVICE passport - Priority workflow, free
    [PassportType.SERVICE]: {
        workflowCategory: WorkflowCategory.PRIORITY,
        processingDays: { min: 1, max: 2 },
        expressPossible: false,
        requiresPayment: false,
        documents: {
            [DocumentTypes.PASSPORT.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.PHOTO.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.TICKET.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.HOTEL.code]: RequirementStatus.NOT_APPLICABLE,
            [DocumentTypes.ACCOMMODATION.code]: RequirementStatus.NOT_APPLICABLE,
            [DocumentTypes.INVITATION.code]: RequirementStatus.OPTIONAL,
            [DocumentTypes.VACCINATION.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.FINANCIAL_PROOF.code]: RequirementStatus.NOT_APPLICABLE,
            [DocumentTypes.VERBAL_NOTE.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.RESIDENCE_CARD.code]: RequirementStatus.CONDITIONAL,
            [DocumentTypes.PAYMENT_PROOF.code]: RequirementStatus.NOT_APPLICABLE,
            [DocumentTypes.PARENTAL_AUTH.code]: RequirementStatus.CONDITIONAL,
            [DocumentTypes.BIRTH_CERTIFICATE.code]: RequirementStatus.CONDITIONAL,
            [DocumentTypes.PARENT_ID.code]: RequirementStatus.CONDITIONAL,
            [DocumentTypes.HOST_ID.code]: RequirementStatus.NOT_APPLICABLE
        }
    },

    // LP_ONU - Laissez-passer ONU
    [PassportType.LP_ONU]: {
        workflowCategory: WorkflowCategory.PRIORITY,
        processingDays: { min: 1, max: 2 },
        expressPossible: false,
        requiresPayment: false,
        documents: {
            [DocumentTypes.PASSPORT.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.PHOTO.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.TICKET.code]: RequirementStatus.OPTIONAL, // Per PRD R27
            [DocumentTypes.HOTEL.code]: RequirementStatus.NOT_APPLICABLE,
            [DocumentTypes.ACCOMMODATION.code]: RequirementStatus.NOT_APPLICABLE,
            [DocumentTypes.INVITATION.code]: RequirementStatus.OPTIONAL,
            [DocumentTypes.VACCINATION.code]: RequirementStatus.OPTIONAL, // Per PRD R29
            [DocumentTypes.FINANCIAL_PROOF.code]: RequirementStatus.NOT_APPLICABLE,
            [DocumentTypes.VERBAL_NOTE.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.RESIDENCE_CARD.code]: RequirementStatus.CONDITIONAL,
            [DocumentTypes.PAYMENT_PROOF.code]: RequirementStatus.NOT_APPLICABLE,
            [DocumentTypes.PARENTAL_AUTH.code]: RequirementStatus.CONDITIONAL,
            [DocumentTypes.BIRTH_CERTIFICATE.code]: RequirementStatus.CONDITIONAL,
            [DocumentTypes.PARENT_ID.code]: RequirementStatus.CONDITIONAL,
            [DocumentTypes.HOST_ID.code]: RequirementStatus.NOT_APPLICABLE
        }
    },

    // LP_UA - Laissez-passer Union Africaine
    [PassportType.LP_UA]: {
        workflowCategory: WorkflowCategory.PRIORITY,
        processingDays: { min: 1, max: 2 },
        expressPossible: false,
        requiresPayment: false,
        documents: {
            [DocumentTypes.PASSPORT.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.PHOTO.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.TICKET.code]: RequirementStatus.OPTIONAL,
            [DocumentTypes.HOTEL.code]: RequirementStatus.NOT_APPLICABLE,
            [DocumentTypes.ACCOMMODATION.code]: RequirementStatus.NOT_APPLICABLE,
            [DocumentTypes.INVITATION.code]: RequirementStatus.OPTIONAL,
            [DocumentTypes.VACCINATION.code]: RequirementStatus.OPTIONAL,
            [DocumentTypes.FINANCIAL_PROOF.code]: RequirementStatus.NOT_APPLICABLE,
            [DocumentTypes.VERBAL_NOTE.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.RESIDENCE_CARD.code]: RequirementStatus.CONDITIONAL,
            [DocumentTypes.PAYMENT_PROOF.code]: RequirementStatus.NOT_APPLICABLE,
            [DocumentTypes.PARENTAL_AUTH.code]: RequirementStatus.CONDITIONAL,
            [DocumentTypes.BIRTH_CERTIFICATE.code]: RequirementStatus.CONDITIONAL,
            [DocumentTypes.PARENT_ID.code]: RequirementStatus.CONDITIONAL,
            [DocumentTypes.HOST_ID.code]: RequirementStatus.NOT_APPLICABLE
        }
    }
};

/**
 * Conditional rules for document requirements
 * Rule format: { condition: (context) => boolean, effect: { docCode: newStatus } }
 */
const CONDITIONAL_RULES = [
    // R28: Residence card required if nationality != residence country
    {
        id: 'R28',
        description: 'Carte de résidence si nationalité différente du pays de résidence',
        condition: (ctx) => ctx.nationality !== ctx.residenceCountry,
        effect: { [DocumentTypes.RESIDENCE_CARD.code]: RequirementStatus.REQUIRED }
    },

    // R06: Hotel optional if has invitation
    {
        id: 'R06',
        description: 'Hôtel optionnel si invitation fournie',
        condition: (ctx) => ctx.hasInvitation === true,
        effect: { [DocumentTypes.HOTEL.code]: RequirementStatus.OPTIONAL }
    },

    // Hotel or Accommodation: One of the two required for ORDINAIRE
    {
        id: 'R_ACCOMMODATION',
        description: 'Hôtel OU attestation hébergement requis',
        condition: (ctx) => ctx.passportType === PassportType.ORDINAIRE && ctx.hasHotel !== true,
        effect: { [DocumentTypes.ACCOMMODATION.code]: RequirementStatus.REQUIRED }
    },

    // Minor documents required if age < 18
    {
        id: 'R_MINOR',
        description: 'Documents mineurs si âge < 18 ans',
        condition: (ctx) => ctx.isMinor === true,
        effect: {
            [DocumentTypes.PARENTAL_AUTH.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.BIRTH_CERTIFICATE.code]: RequirementStatus.REQUIRED,
            [DocumentTypes.PARENT_ID.code]: RequirementStatus.REQUIRED
        }
    },

    // Host ID required if staying with private host
    {
        id: 'R_HOST',
        description: 'ID de l\'hôte requis si hébergement particulier',
        condition: (ctx) => ctx.accommodationType === 'private_host',
        effect: { [DocumentTypes.HOST_ID.code]: RequirementStatus.REQUIRED }
    },

    // Yellow fever vaccination based on nationality
    {
        id: 'R_VACCINATION_EXEMPT',
        description: 'Vaccination optionnelle pour pays exemptés',
        condition: (ctx) => {
            // Import dynamique non possible ici, on utilise une liste inline
            const exemptCountries = [
                'FRA', 'DEU', 'GBR', 'ITA', 'ESP', 'PRT', 'NLD', 'BEL', 'CHE', 'AUT',
                'SWE', 'NOR', 'DNK', 'FIN', 'IRL', 'USA', 'CAN', 'AUS', 'NZL',
                'JPN', 'KOR', 'SGP', 'POL', 'CZE', 'HUN', 'ROU', 'GRC'
            ];
            return ctx.nationality && exemptCountries.includes(ctx.nationality);
        },
        effect: { [DocumentTypes.VACCINATION.code]: RequirementStatus.NOT_APPLICABLE }
    },

    // Transit visa specific requirements
    {
        id: 'R_TRANSIT',
        description: 'Documents allégés pour visa transit',
        condition: (ctx) => ctx.visaType === 'transit' || ctx.tripPurpose === 'transit',
        effect: {
            [DocumentTypes.HOTEL.code]: RequirementStatus.NOT_APPLICABLE,
            [DocumentTypes.ACCOMMODATION.code]: RequirementStatus.NOT_APPLICABLE,
            [DocumentTypes.FINANCIAL_PROOF.code]: RequirementStatus.NOT_APPLICABLE
        }
    },

    // Ticket required for continuation journey in transit
    {
        id: 'R_TRANSIT_CONTINUATION',
        description: 'Billet de continuation requis pour transit',
        condition: (ctx) => ctx.visaType === 'transit' || ctx.tripPurpose === 'transit',
        effect: { [DocumentTypes.TICKET.code]: RequirementStatus.REQUIRED }
    }
];

/**
 * Visa types and fees
 */
export const VisaTypes = {
    COURT_SEJOUR_UNIQUE: {
        code: 'court_sejour_unique',
        nameKey: 'visa_court_sejour_unique',
        maxDays: 90,
        fee: 73000, // XOF
        expressFee: 50000,
        currency: 'XOF'
    },
    COURT_SEJOUR_MULTIPLE: {
        code: 'court_sejour_multiple',
        nameKey: 'visa_court_sejour_multiple',
        maxDays: 90,
        fee: 120000,
        expressFee: 50000,
        currency: 'XOF'
    },
    TRANSIT: {
        code: 'transit',
        nameKey: 'visa_transit',
        maxDays: 3, // 72 heures max
        maxHours: 72,
        fee: 35000,
        expressFee: 0,
        currency: 'XOF'
    }
};

/**
 * Pays exemptés de vaccination fièvre jaune
 * (Europe, Amérique du Nord, Australie, NZ, Japon, Corée, Singapour)
 */
export const YELLOW_FEVER_EXEMPT_COUNTRIES = [
    // Europe occidentale
    'FRA', 'DEU', 'GBR', 'ITA', 'ESP', 'PRT', 'NLD', 'BEL', 'CHE', 'AUT',
    'SWE', 'NOR', 'DNK', 'FIN', 'IRL', 'LUX', 'MCO', 'AND', 'LIE', 'ISL',
    // Europe centrale et orientale
    'POL', 'CZE', 'HUN', 'ROU', 'BGR', 'SVK', 'SVN', 'HRV', 'SRB', 'MNE',
    'MKD', 'ALB', 'BIH', 'XKX', 'EST', 'LVA', 'LTU', 'BLR', 'UKR', 'MDA',
    // Europe du Sud
    'GRC', 'CYP', 'MLT',
    // Amérique du Nord
    'USA', 'CAN',
    // Océanie
    'AUS', 'NZL',
    // Asie (exemptés - pas de fièvre jaune)
    'JPN', 'KOR', 'SGP', 'HKG', 'MAC', 'TWN',
    // Moyen-Orient (exemptés)
    'ARE', 'QAT', 'KWT', 'BHR', 'OMN', 'SAU', 'ISR', 'JOR', 'LBN'
];

/**
 * Pays africains nécessitant vaccination fièvre jaune
 */
export const YELLOW_FEVER_REQUIRED_COUNTRIES = [
    // Afrique de l'Ouest
    'SEN', 'MLI', 'BFA', 'NER', 'NGA', 'GHA', 'TGO', 'BEN', 'CIV',
    'GIN', 'GNB', 'GMB', 'SLE', 'LBR', 'MRT',
    // Afrique centrale
    'CMR', 'TCD', 'CAF', 'COG', 'COD', 'GAB', 'GNQ', 'STP',
    // Afrique de l'Est
    'ETH', 'KEN', 'UGA', 'TZA', 'RWA', 'BDI', 'SSD', 'SDN', 'ERI', 'SOM', 'DJI',
    // Amérique du Sud (zones endémiques)
    'BRA', 'COL', 'VEN', 'ECU', 'PER', 'BOL', 'PRY', 'GUY', 'SUR', 'GUF',
    'ARG', 'PAN', 'TTO'
];

/**
 * Covered jurisdiction countries
 */
export const JurisdictionCountries = {
    ETH: { code: 'ETH', name: 'Éthiopie', capital: 'Addis-Abeba', consulate: 'ADDIS_ABEBA' },
    KEN: { code: 'KEN', name: 'Kenya', capital: 'Nairobi', consulate: 'NAIROBI' },
    DJI: { code: 'DJI', name: 'Djibouti', capital: 'Djibouti', consulate: 'DJIBOUTI' },
    TZA: { code: 'TZA', name: 'Tanzanie', capital: 'Dar es Salaam', consulate: 'NAIROBI' },
    UGA: { code: 'UGA', name: 'Ouganda', capital: 'Kampala', consulate: 'ADDIS_ABEBA' },
    SSD: { code: 'SSD', name: 'Soudan du Sud', capital: 'Juba', consulate: 'ADDIS_ABEBA' },
    SOM: { code: 'SOM', name: 'Somalie', capital: 'Mogadiscio', consulate: 'DJIBOUTI' }
};

/**
 * RequirementsMatrix class
 * Handles dynamic calculation of document requirements based on context
 */
export class RequirementsMatrix {
    constructor(options = {}) {
        this.baseMatrix = BASE_REQUIREMENTS_MATRIX;
        this.conditionalRules = CONDITIONAL_RULES;
        this.context = {};
        this.calculatedRequirements = null;

        // Event emitter for requirement changes
        this.listeners = new Map();
    }

    /**
     * Initialize or update context
     * @param {Object} context - Application context
     */
    setContext(context) {
        this.context = { ...this.context, ...context };
        this.recalculate();
        this.emit('contextChanged', this.context);
    }

    /**
     * Get current context
     * @returns {Object}
     */
    getContext() {
        return { ...this.context };
    }

    /**
     * Calculate requirements based on current context
     * @returns {Object} Calculated requirements
     */
    recalculate() {
        const passportType = this.context.passportType || PassportType.ORDINAIRE;
        const baseRequirements = this.baseMatrix[passportType] || this.baseMatrix[PassportType.ORDINAIRE];

        // Clone base requirements
        this.calculatedRequirements = {
            ...baseRequirements,
            documents: { ...baseRequirements.documents }
        };

        // Apply conditional rules
        for (const rule of this.conditionalRules) {
            try {
                if (rule.condition(this.context)) {
                    for (const [docCode, status] of Object.entries(rule.effect)) {
                        // Only upgrade status (e.g., optional -> required), never downgrade
                        const currentStatus = this.calculatedRequirements.documents[docCode];
                        if (this._shouldUpgradeStatus(currentStatus, status)) {
                            this.calculatedRequirements.documents[docCode] = status;
                        }
                    }
                }
            } catch (error) {
                console.warn(`Rule ${rule.id} evaluation failed:`, error);
            }
        }

        this.emit('requirementsChanged', this.calculatedRequirements);
        return this.calculatedRequirements;
    }

    /**
     * Check if status should be upgraded
     * @private
     */
    _shouldUpgradeStatus(current, newStatus) {
        const priority = {
            [RequirementStatus.NOT_APPLICABLE]: 0,
            [RequirementStatus.OPTIONAL]: 1,
            [RequirementStatus.RECOMMENDED]: 2,
            [RequirementStatus.CONDITIONAL]: 3,
            [RequirementStatus.REQUIRED]: 4
        };
        return (priority[newStatus] || 0) > (priority[current] || 0);
    }

    /**
     * Get requirements for a specific document
     * @param {string} docCode - Document code
     * @returns {string} Requirement status
     */
    getDocumentRequirement(docCode) {
        if (!this.calculatedRequirements) {
            this.recalculate();
        }
        return this.calculatedRequirements.documents[docCode] || RequirementStatus.NOT_APPLICABLE;
    }

    /**
     * Get all required documents
     * @returns {Array} Array of required document codes
     */
    getRequiredDocuments() {
        if (!this.calculatedRequirements) {
            this.recalculate();
        }
        return Object.entries(this.calculatedRequirements.documents)
            .filter(([_, status]) => status === RequirementStatus.REQUIRED)
            .map(([code]) => code);
    }

    /**
     * Get all optional documents
     * @returns {Array}
     */
    getOptionalDocuments() {
        if (!this.calculatedRequirements) {
            this.recalculate();
        }
        return Object.entries(this.calculatedRequirements.documents)
            .filter(([_, status]) => status === RequirementStatus.OPTIONAL)
            .map(([code]) => code);
    }

    /**
     * Get all conditional documents
     * @returns {Array}
     */
    getConditionalDocuments() {
        if (!this.calculatedRequirements) {
            this.recalculate();
        }
        return Object.entries(this.calculatedRequirements.documents)
            .filter(([_, status]) => status === RequirementStatus.CONDITIONAL)
            .map(([code]) => code);
    }

    /**
     * Check if a document is required
     * @param {string} docCode
     * @returns {boolean}
     */
    isRequired(docCode) {
        return this.getDocumentRequirement(docCode) === RequirementStatus.REQUIRED;
    }

    /**
     * Check if a document is applicable (required or optional)
     * @param {string} docCode
     * @returns {boolean}
     */
    isApplicable(docCode) {
        const status = this.getDocumentRequirement(docCode);
        return status !== RequirementStatus.NOT_APPLICABLE;
    }

    /**
     * Get workflow information
     * @returns {Object}
     */
    getWorkflowInfo() {
        if (!this.calculatedRequirements) {
            this.recalculate();
        }
        return {
            category: this.calculatedRequirements.workflowCategory,
            processingDays: this.calculatedRequirements.processingDays,
            expressPossible: this.calculatedRequirements.expressPossible,
            expressProcessingDays: this.calculatedRequirements.expressProcessingDays,
            requiresPayment: this.calculatedRequirements.requiresPayment
        };
    }

    /**
     * Calculate visa fee based on context
     * @returns {Object} Fee information
     */
    calculateFee() {
        const workflowInfo = this.getWorkflowInfo();

        if (!workflowInfo.requiresPayment) {
            return {
                total: 0,
                baseFee: 0,
                expressFee: 0,
                currency: 'XOF',
                isFree: true
            };
        }

        const visaType = this.context.visaType || 'court_sejour_unique';
        const visaInfo = Object.values(VisaTypes).find(v => v.code === visaType) || VisaTypes.COURT_SEJOUR_UNIQUE;
        const isExpress = this.context.isExpress === true && workflowInfo.expressPossible;

        const baseFee = visaInfo.fee;
        const expressFee = isExpress ? visaInfo.expressFee : 0;

        return {
            total: baseFee + expressFee,
            baseFee,
            expressFee,
            currency: visaInfo.currency,
            isFree: false,
            isExpress
        };
    }

    /**
     * Check if residence country is in jurisdiction
     * @param {string} countryCode
     * @returns {boolean}
     */
    isInJurisdiction(countryCode) {
        return Object.keys(JurisdictionCountries).includes(countryCode?.toUpperCase());
    }

    /**
     * Get consulate for a country
     * @param {string} countryCode
     * @returns {string|null}
     */
    getConsulateFor(countryCode) {
        const country = JurisdictionCountries[countryCode?.toUpperCase()];
        return country ? country.consulate : null;
    }

    /**
     * Check if nationality requires yellow fever vaccination
     * @param {string} nationalityCode
     * @returns {boolean}
     */
    requiresYellowFeverVaccination(nationalityCode) {
        if (!nationalityCode) return true; // Default to required
        return !YELLOW_FEVER_EXEMPT_COUNTRIES.includes(nationalityCode.toUpperCase());
    }

    /**
     * Check if applicant is from yellow fever endemic zone
     * @param {string} nationalityCode
     * @returns {boolean}
     */
    isFromYellowFeverZone(nationalityCode) {
        if (!nationalityCode) return false;
        return YELLOW_FEVER_REQUIRED_COUNTRIES.includes(nationalityCode.toUpperCase());
    }

    /**
     * Check if this is a transit visa context
     * @returns {boolean}
     */
    isTransitVisa() {
        return this.context.visaType === 'transit' || this.context.tripPurpose === 'transit';
    }

    /**
     * Get estimated processing time based on passport type
     * @returns {Object}
     */
    getProcessingTime() {
        const workflowInfo = this.getWorkflowInfo();
        const isExpress = this.context.isExpress === true && workflowInfo.expressPossible;

        if (workflowInfo.category === WorkflowCategory.PRIORITY) {
            return {
                min: 1,
                max: 2,
                unit: 'days',
                isPriority: true
            };
        }

        if (isExpress) {
            return {
                min: 1,
                max: 2,
                unit: 'days',
                isExpress: true
            };
        }

        return {
            min: workflowInfo.processingDays?.min || 5,
            max: workflowInfo.processingDays?.max || 10,
            unit: 'days',
            isStandard: true
        };
    }

    /**
     * Get document metadata
     * @param {string} docCode
     * @returns {Object|null}
     */
    getDocumentMeta(docCode) {
        return Object.values(DocumentTypes).find(d => d.code === docCode) || null;
    }

    /**
     * Get all documents with their status and metadata
     * @returns {Array}
     */
    getAllDocumentsWithStatus() {
        if (!this.calculatedRequirements) {
            this.recalculate();
        }

        return Object.entries(this.calculatedRequirements.documents)
            .map(([code, status]) => {
                const meta = this.getDocumentMeta(code);
                return {
                    code,
                    status,
                    ...meta,
                    isRequired: status === RequirementStatus.REQUIRED,
                    isOptional: status === RequirementStatus.OPTIONAL,
                    isApplicable: status !== RequirementStatus.NOT_APPLICABLE
                };
            })
            .filter(doc => doc.isApplicable)
            .sort((a, b) => (a.priority || 99) - (b.priority || 99));
    }

    /**
     * Get completion status
     * @param {Object} collectedData - Data collected so far
     * @returns {Object}
     */
    getCompletionStatus(collectedData = {}) {
        const required = this.getRequiredDocuments();
        const optional = this.getOptionalDocuments();

        const completedRequired = required.filter(code =>
            collectedData[code] && !collectedData[code].skipped
        );
        const completedOptional = optional.filter(code =>
            collectedData[code] && !collectedData[code].skipped
        );
        const skippedOptional = optional.filter(code =>
            collectedData[code]?.skipped
        );

        return {
            required: {
                total: required.length,
                completed: completedRequired.length,
                pending: required.filter(code => !collectedData[code]).map(code => code),
                percentage: required.length > 0
                    ? Math.round((completedRequired.length / required.length) * 100)
                    : 100
            },
            optional: {
                total: optional.length,
                completed: completedOptional.length,
                skipped: skippedOptional.length,
                pending: optional.filter(code => !collectedData[code]).map(code => code)
            },
            overallPercentage: required.length > 0
                ? Math.round((completedRequired.length / required.length) * 100)
                : 100,
            isComplete: completedRequired.length === required.length
        };
    }

    // Event emitter methods
    on(event, callback) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, new Set());
        }
        this.listeners.get(event).add(callback);
        return () => this.off(event, callback);
    }

    off(event, callback) {
        if (this.listeners.has(event)) {
            this.listeners.get(event).delete(callback);
        }
    }

    emit(event, data) {
        if (this.listeners.has(event)) {
            for (const callback of this.listeners.get(event)) {
                try {
                    callback(data);
                } catch (error) {
                    console.error(`Error in ${event} listener:`, error);
                }
            }
        }
    }
}

// Export singleton instance
export const requirementsMatrix = new RequirementsMatrix();

// Export all for module usage
export default {
    RequirementsMatrix,
    requirementsMatrix,
    RequirementStatus,
    PassportType,
    WorkflowCategory,
    DocumentTypes,
    VisaTypes,
    JurisdictionCountries,
    YELLOW_FEVER_EXEMPT_COUNTRIES,
    YELLOW_FEVER_REQUIRED_COUNTRIES
};
