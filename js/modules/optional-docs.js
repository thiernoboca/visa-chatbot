/**
 * Optional Documents Manager
 * Handles explicit yes/no questions for optional documents
 *
 * @module optional-docs
 * @version 1.0.0
 *
 * Features:
 * - Explicit questions for optional documents
 * - Conditional document requirements
 * - User decision tracking
 * - Skip/provide logic
 */

import { DocumentTypes, PassportType, requirementsMatrix } from './requirements-matrix.js';

// =============================================================================
// CONSTANTS
// =============================================================================

/**
 * Optional document decision states
 */
export const OptionalDocDecision = {
    PENDING: 'pending',
    WILL_PROVIDE: 'will_provide',
    SKIPPED: 'skipped',
    PROVIDED: 'provided',
    NOT_APPLICABLE: 'not_applicable'
};

/**
 * Question configurations for optional documents (bilingual)
 */
const OPTIONAL_DOC_QUESTIONS = {
    [DocumentTypes.INVITATION]: {
        id: 'invitation',
        priority: 1,
        question: {
            fr: "Avez-vous une lettre d'invitation d'un résident ivoirien ?",
            en: "Do you have an invitation letter from an Ivorian resident?"
        },
        yesLabel: {
            fr: "Oui, j'ai une invitation",
            en: "Yes, I have an invitation"
        },
        noLabel: {
            fr: "Non, je n'ai pas d'invitation",
            en: "No, I don't have an invitation"
        },
        impact: {
            fr: "Si vous avez une invitation, la réservation d'hôtel devient optionnelle.",
            en: "If you have an invitation, hotel reservation becomes optional."
        },
        affects: [DocumentTypes.HOTEL],
        skipInfo: {
            fr: "Vous devrez alors fournir une réservation d'hôtel.",
            en: "You will then need to provide a hotel reservation."
        }
    },

    [DocumentTypes.HOTEL]: {
        id: 'hotel',
        priority: 2,
        question: {
            fr: "Avez-vous une réservation d'hôtel confirmée ?",
            en: "Do you have a confirmed hotel reservation?"
        },
        yesLabel: {
            fr: "Oui, j'ai une réservation",
            en: "Yes, I have a reservation"
        },
        noLabel: {
            fr: "Non, je serai hébergé ailleurs",
            en: "No, I'll stay elsewhere"
        },
        impact: {
            fr: "Une réservation d'hôtel ou une lettre d'invitation est requise.",
            en: "A hotel reservation or invitation letter is required."
        },
        alternativeDoc: DocumentTypes.INVITATION,
        skipInfo: {
            fr: "Vous devrez alors fournir une lettre d'invitation.",
            en: "You will then need to provide an invitation letter."
        }
    },

    [DocumentTypes.VERBAL_NOTE]: {
        id: 'verbal_note',
        priority: 1,
        question: {
            fr: "Votre mission dispose-t-elle d'une note verbale du ministère ?",
            en: "Does your mission have a verbal note from the ministry?"
        },
        yesLabel: {
            fr: "Oui, j'ai la note verbale",
            en: "Yes, I have the verbal note"
        },
        noLabel: {
            fr: "Non, pas encore",
            en: "No, not yet"
        },
        impact: {
            fr: "La note verbale est obligatoire pour les passeports diplomatiques et de service.",
            en: "Verbal note is mandatory for diplomatic and service passports."
        },
        applicableTo: [PassportType.DIPLOMATIQUE, PassportType.SERVICE],
        isActuallyRequired: true, // Not truly optional for these passport types
        skipInfo: {
            fr: "La note verbale est requise. Vous devrez la fournir pour continuer.",
            en: "Verbal note is required. You will need to provide it to continue."
        }
    },

    [DocumentTypes.RESIDENCE_CARD]: {
        id: 'residence_card',
        priority: 0, // Highest priority
        question: {
            fr: "Avez-vous un titre de séjour/carte de résidence ?",
            en: "Do you have a residence permit/card?"
        },
        yesLabel: {
            fr: "Oui, j'ai une carte de résidence",
            en: "Yes, I have a residence card"
        },
        noLabel: {
            fr: "Non, je suis en transit/visite",
            en: "No, I'm in transit/visiting"
        },
        impact: {
            fr: "Si votre nationalité est différente de votre pays de résidence, un titre de séjour est requis.",
            en: "If your nationality differs from your country of residence, a residence permit is required."
        },
        condition: 'nationality_differs_from_residence',
        skipInfo: {
            fr: "Veuillez fournir un justificatif de présence (billet d'arrivée, visa, etc.)",
            en: "Please provide proof of presence (arrival ticket, visa, etc.)"
        }
    },

    [DocumentTypes.FINANCIAL_PROOF]: {
        id: 'financial_proof',
        priority: 3,
        question: {
            fr: "Pouvez-vous fournir un justificatif de ressources financières ?",
            en: "Can you provide proof of financial resources?"
        },
        yesLabel: {
            fr: "Oui, j'ai un relevé bancaire",
            en: "Yes, I have a bank statement"
        },
        noLabel: {
            fr: "Non, pas nécessaire",
            en: "No, not necessary"
        },
        impact: {
            fr: "Recommandé pour les demandes de visa ordinaire.",
            en: "Recommended for regular visa applications."
        },
        applicableTo: [PassportType.ORDINAIRE],
        skipInfo: {
            fr: "Un justificatif de ressources peut accélérer le traitement de votre demande.",
            en: "Proof of resources may speed up your application processing."
        }
    }
};

/**
 * Accompanist-related questions
 */
const ACCOMPANIST_QUESTIONS = {
    hasAccompanists: {
        question: {
            fr: "Voyagez-vous avec d'autres personnes ?",
            en: "Are you traveling with other people?"
        },
        yesLabel: {
            fr: "Oui, avec des accompagnants",
            en: "Yes, with companions"
        },
        noLabel: {
            fr: "Non, je voyage seul",
            en: "No, I'm traveling alone"
        }
    },
    accompanistDetails: {
        header: {
            fr: "Informations sur l'accompagnant",
            en: "Companion Information"
        },
        fields: {
            name: { fr: "Nom complet", en: "Full Name" },
            dateOfBirth: { fr: "Date de naissance", en: "Date of Birth" },
            relationship: { fr: "Lien de parenté", en: "Relationship" },
            passportNumber: { fr: "Numéro de passeport", en: "Passport Number" }
        },
        relationshipOptions: [
            { value: 'spouse', label: { fr: 'Conjoint(e)', en: 'Spouse' } },
            { value: 'child', label: { fr: 'Enfant', en: 'Child' } },
            { value: 'parent', label: { fr: 'Parent', en: 'Parent' } },
            { value: 'sibling', label: { fr: 'Frère/Soeur', en: 'Sibling' } },
            { value: 'other', label: { fr: 'Autre', en: 'Other' } }
        ]
    }
};

// =============================================================================
// OPTIONAL DOCS MANAGER CLASS
// =============================================================================

export class OptionalDocsManager {
    constructor(options = {}) {
        this.language = options.language || 'fr';
        this.decisions = {};
        this.accompanists = [];
        this.applicationContext = {};
        this.onDecision = options.onDecision || (() => {});
    }

    /**
     * Set application context (passport type, nationality, residence, etc.)
     */
    setContext(context) {
        this.applicationContext = {
            ...this.applicationContext,
            ...context
        };
    }

    /**
     * Get questions for optional documents based on context
     * @returns {Array} Sorted list of applicable questions
     */
    getApplicableQuestions() {
        const questions = [];
        const passportType = this.applicationContext.passportType || PassportType.ORDINAIRE;
        const nationality = this.applicationContext.nationality;
        const residenceCountry = this.applicationContext.residenceCountry;

        for (const [docType, config] of Object.entries(OPTIONAL_DOC_QUESTIONS)) {
            // Check if applicable to passport type
            if (config.applicableTo && !config.applicableTo.includes(passportType)) {
                continue;
            }

            // Check special conditions
            if (config.condition === 'nationality_differs_from_residence') {
                if (nationality && residenceCountry && nationality === residenceCountry) {
                    continue; // Skip if nationality matches residence
                }
            }

            // Check if already decided
            if (this.decisions[docType] && this.decisions[docType] !== OptionalDocDecision.PENDING) {
                continue;
            }

            questions.push({
                docType,
                ...config
            });
        }

        // Sort by priority (lower = higher priority)
        return questions.sort((a, b) => a.priority - b.priority);
    }

    /**
     * Get a specific question for a document type
     */
    getQuestion(docType) {
        const config = OPTIONAL_DOC_QUESTIONS[docType];
        if (!config) return null;

        return {
            docType,
            question: this.t(config.question),
            yesLabel: this.t(config.yesLabel),
            noLabel: this.t(config.noLabel),
            impact: config.impact ? this.t(config.impact) : null,
            skipInfo: config.skipInfo ? this.t(config.skipInfo) : null
        };
    }

    /**
     * Record user's decision for an optional document
     */
    recordDecision(docType, willProvide) {
        const decision = willProvide ? OptionalDocDecision.WILL_PROVIDE : OptionalDocDecision.SKIPPED;
        this.decisions[docType] = decision;

        // Handle document dependencies
        const config = OPTIONAL_DOC_QUESTIONS[docType];
        if (config && config.affects) {
            // If user will provide this doc, affected docs become optional
            // If user skips, affected docs may become required
            for (const affectedDoc of config.affects) {
                if (willProvide) {
                    // This doc provided, affected become truly optional
                    if (!this.decisions[affectedDoc]) {
                        this.decisions[affectedDoc] = OptionalDocDecision.NOT_APPLICABLE;
                    }
                }
                // Note: if skipped, the affected doc will be asked about separately
            }
        }

        // Trigger callback
        this.onDecision(docType, decision, this.decisions);

        return {
            docType,
            decision,
            nextQuestion: this.getNextQuestion()
        };
    }

    /**
     * Mark a document as provided (after upload)
     */
    markAsProvided(docType) {
        this.decisions[docType] = OptionalDocDecision.PROVIDED;
    }

    /**
     * Get the next pending question
     */
    getNextQuestion() {
        const questions = this.getApplicableQuestions();
        return questions.length > 0 ? questions[0] : null;
    }

    /**
     * Check if a document should be collected based on decisions
     */
    shouldCollectDocument(docType) {
        const decision = this.decisions[docType];

        // If explicitly decided
        if (decision === OptionalDocDecision.WILL_PROVIDE) return true;
        if (decision === OptionalDocDecision.SKIPPED) return false;
        if (decision === OptionalDocDecision.NOT_APPLICABLE) return false;
        if (decision === OptionalDocDecision.PROVIDED) return false; // Already done

        // Check requirements matrix
        const requirements = requirementsMatrix.getRequirements(
            this.applicationContext.passportType || PassportType.ORDINAIRE,
            this.applicationContext
        );

        const docReq = requirements[docType];
        if (!docReq) return false;

        return docReq.status === 'required' || docReq.status === 'conditional';
    }

    /**
     * Get list of documents user decided to skip
     */
    getSkippedDocuments() {
        return Object.entries(this.decisions)
            .filter(([_, decision]) => decision === OptionalDocDecision.SKIPPED)
            .map(([docType, _]) => docType);
    }

    /**
     * Get list of documents user will provide
     */
    getDocumentsToProvide() {
        return Object.entries(this.decisions)
            .filter(([_, decision]) =>
                decision === OptionalDocDecision.WILL_PROVIDE ||
                decision === OptionalDocDecision.PROVIDED
            )
            .map(([docType, _]) => docType);
    }

    /**
     * Check if hotel OR invitation rule is satisfied
     */
    hasAccommodationProof() {
        const hotelDecision = this.decisions[DocumentTypes.HOTEL];
        const invitationDecision = this.decisions[DocumentTypes.INVITATION];

        return (
            hotelDecision === OptionalDocDecision.WILL_PROVIDE ||
            hotelDecision === OptionalDocDecision.PROVIDED ||
            invitationDecision === OptionalDocDecision.WILL_PROVIDE ||
            invitationDecision === OptionalDocDecision.PROVIDED
        );
    }

    /**
     * Validate all required optional decisions have been made
     */
    validateDecisions() {
        const issues = [];
        const passportType = this.applicationContext.passportType || PassportType.ORDINAIRE;

        // For ORDINAIRE, need either hotel or invitation
        if (passportType === PassportType.ORDINAIRE && !this.hasAccommodationProof()) {
            const hotelDecision = this.decisions[DocumentTypes.HOTEL];
            const invitationDecision = this.decisions[DocumentTypes.INVITATION];

            if (hotelDecision === OptionalDocDecision.SKIPPED &&
                invitationDecision === OptionalDocDecision.SKIPPED) {
                issues.push({
                    type: 'MISSING_ACCOMMODATION',
                    message: this.t({
                        fr: "Vous devez fournir soit une réservation d'hôtel, soit une lettre d'invitation.",
                        en: "You must provide either a hotel reservation or an invitation letter."
                    })
                });
            }
        }

        // For DIPLOMATIQUE/SERVICE, verbal note is required
        if ([PassportType.DIPLOMATIQUE, PassportType.SERVICE].includes(passportType)) {
            const verbalNoteDecision = this.decisions[DocumentTypes.VERBAL_NOTE];
            if (verbalNoteDecision === OptionalDocDecision.SKIPPED) {
                issues.push({
                    type: 'MISSING_VERBAL_NOTE',
                    message: this.t({
                        fr: "La note verbale est obligatoire pour les passeports diplomatiques et de service.",
                        en: "Verbal note is mandatory for diplomatic and service passports."
                    })
                });
            }
        }

        // For different nationality/residence, need residence card or justification
        const nationality = this.applicationContext.nationality;
        const residence = this.applicationContext.residenceCountry;
        if (nationality && residence && nationality !== residence) {
            const residenceCardDecision = this.decisions[DocumentTypes.RESIDENCE_CARD];
            if (residenceCardDecision === OptionalDocDecision.SKIPPED) {
                issues.push({
                    type: 'MISSING_RESIDENCE_PROOF',
                    message: this.t({
                        fr: "Un justificatif de résidence est requis car votre nationalité diffère de votre pays de résidence.",
                        en: "Proof of residence is required as your nationality differs from your country of residence."
                    })
                });
            }
        }

        return {
            valid: issues.length === 0,
            issues
        };
    }

    // ==========================================================================
    // ACCOMPANIST MANAGEMENT
    // ==========================================================================

    /**
     * Get accompanist question
     */
    getAccompanistQuestion() {
        return {
            question: this.t(ACCOMPANIST_QUESTIONS.hasAccompanists.question),
            yesLabel: this.t(ACCOMPANIST_QUESTIONS.hasAccompanists.yesLabel),
            noLabel: this.t(ACCOMPANIST_QUESTIONS.hasAccompanists.noLabel)
        };
    }

    /**
     * Get accompanist form fields
     */
    getAccompanistFormFields() {
        const fields = ACCOMPANIST_QUESTIONS.accompanistDetails.fields;
        const options = ACCOMPANIST_QUESTIONS.accompanistDetails.relationshipOptions;

        return {
            header: this.t(ACCOMPANIST_QUESTIONS.accompanistDetails.header),
            fields: Object.entries(fields).map(([name, labels]) => ({
                name,
                label: this.t(labels)
            })),
            relationshipOptions: options.map(opt => ({
                value: opt.value,
                label: this.t(opt.label)
            }))
        };
    }

    /**
     * Add an accompanist
     */
    addAccompanist(accompanist) {
        const isMinor = this.calculateAge(accompanist.dateOfBirth) < 18;

        this.accompanists.push({
            ...accompanist,
            id: `acc_${Date.now()}_${this.accompanists.length}`,
            isMinor
        });

        return this.accompanists;
    }

    /**
     * Remove an accompanist
     */
    removeAccompanist(id) {
        this.accompanists = this.accompanists.filter(a => a.id !== id);
        return this.accompanists;
    }

    /**
     * Get all accompanists
     */
    getAccompanists() {
        return this.accompanists;
    }

    /**
     * Check if any accompanist is a minor
     */
    hasMinorAccompanists() {
        return this.accompanists.some(a => a.isMinor);
    }

    /**
     * Get minor accompanists (for additional document requirements)
     */
    getMinorAccompanists() {
        return this.accompanists.filter(a => a.isMinor);
    }

    /**
     * Calculate age from date of birth
     */
    calculateAge(dateOfBirth) {
        if (!dateOfBirth) return null;

        const dob = new Date(dateOfBirth);
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const monthDiff = today.getMonth() - dob.getMonth();

        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
            age--;
        }

        return age;
    }

    // ==========================================================================
    // UTILITY METHODS
    // ==========================================================================

    /**
     * Translation helper
     */
    t(obj) {
        if (typeof obj === 'string') return obj;
        return obj[this.language] || obj.fr || obj.en || '';
    }

    /**
     * Set language
     */
    setLanguage(lang) {
        this.language = lang === 'en' ? 'en' : 'fr';
    }

    /**
     * Reset all decisions
     */
    reset() {
        this.decisions = {};
        this.accompanists = [];
        this.applicationContext = {};
    }

    /**
     * Export state for persistence
     */
    exportState() {
        return {
            decisions: { ...this.decisions },
            accompanists: [...this.accompanists],
            applicationContext: { ...this.applicationContext }
        };
    }

    /**
     * Import state from persistence
     */
    importState(state) {
        if (state.decisions) this.decisions = state.decisions;
        if (state.accompanists) this.accompanists = state.accompanists;
        if (state.applicationContext) this.applicationContext = state.applicationContext;
    }

    /**
     * Get summary of decisions for display
     */
    getSummary() {
        const summary = {
            documentsToProvide: [],
            skippedDocuments: [],
            accompanists: this.accompanists.length,
            minors: this.getMinorAccompanists().length
        };

        for (const [docType, decision] of Object.entries(this.decisions)) {
            const config = OPTIONAL_DOC_QUESTIONS[docType];
            const docName = config ? this.t(config.yesLabel).replace(/^(Oui, |Yes, )/, '') : docType;

            if (decision === OptionalDocDecision.WILL_PROVIDE || decision === OptionalDocDecision.PROVIDED) {
                summary.documentsToProvide.push({
                    type: docType,
                    name: docName,
                    provided: decision === OptionalDocDecision.PROVIDED
                });
            } else if (decision === OptionalDocDecision.SKIPPED) {
                summary.skippedDocuments.push({
                    type: docType,
                    name: docName
                });
            }
        }

        return summary;
    }
}

// =============================================================================
// SINGLETON EXPORT
// =============================================================================

export const optionalDocsManager = new OptionalDocsManager();

export default optionalDocsManager;
