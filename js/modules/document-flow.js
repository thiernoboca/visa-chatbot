/**
 * Document Flow Module
 * Orchestrates the document collection flow based on requirements matrix
 *
 * @version 1.0.0
 * Handles step-by-step progression, conditional branching, and document validation
 */

import {
    requirementsMatrix,
    RequirementStatus,
    PassportType,
    DocumentTypes,
    JurisdictionCountries,
    YELLOW_FEVER_EXEMPT_COUNTRIES
} from './requirements-matrix.js';

/**
 * Flow step types
 */
export const StepType = {
    WELCOME: 'welcome',
    PASSPORT: 'passport',
    RESIDENCE: 'residence',
    RESIDENCE_CARD: 'residence_card',
    TICKET: 'ticket',
    HOTEL: 'hotel',
    ACCOMMODATION: 'accommodation',
    VACCINATION: 'vaccination',
    INVITATION: 'invitation',
    VERBAL_NOTE: 'verbal_note',
    ELIGIBILITY: 'eligibility',
    MINOR_AUTH: 'minor_auth',      // NEW: Documents mineurs
    TRANSIT_INFO: 'transit_info',  // NEW: Infos transit
    PHOTO: 'photo',
    CONTACT: 'contact',
    TRIP: 'trip',
    HEALTH: 'health',
    CUSTOMS: 'customs',
    PAYMENT: 'payment',
    CONFIRM: 'confirm',
    SIGNATURE: 'signature',
    COMPLETION: 'completion'       // NEW: Confirmation finale
};

// Re-export for backwards compatibility (canonical definition is in requirements-matrix.js)
export { YELLOW_FEVER_EXEMPT_COUNTRIES };

/**
 * Step status
 */
export const StepStatus = {
    PENDING: 'pending',
    ACTIVE: 'active',
    COMPLETED: 'completed',
    SKIPPED: 'skipped',
    BLOCKED: 'blocked'
};

/**
 * Flow steps configuration
 * Each step has conditions for visibility and required state
 */
export const FLOW_STEPS = [
    {
        id: StepType.WELCOME,
        order: 0,
        nameKey: 'step_welcome',
        icon: '👋',
        progress: 5,
        isVisible: () => true,
        isRequired: () => true,
        documentType: null,
        collectsData: ['language']
    },
    {
        id: StepType.PASSPORT,
        order: 1,
        nameKey: 'step_passport',
        icon: '🛂',
        progress: 15,
        isVisible: () => true,
        isRequired: () => true,
        documentType: DocumentTypes.PASSPORT.code,
        collectsData: ['passport_data', 'passport_type', 'nationality']
    },
    {
        id: StepType.RESIDENCE,
        order: 2,
        nameKey: 'step_residence',
        icon: '🏠',
        progress: 20,
        isVisible: () => true,
        isRequired: () => true,
        documentType: null,
        collectsData: ['residence_country', 'is_in_jurisdiction']
    },
    {
        id: StepType.RESIDENCE_CARD,
        order: 3,
        nameKey: 'step_residence_card',
        icon: '🪪',
        progress: 25,
        isVisible: (ctx) => ctx.nationality !== ctx.residenceCountry,
        isRequired: (ctx) => ctx.nationality !== ctx.residenceCountry,
        documentType: DocumentTypes.RESIDENCE_CARD.code,
        collectsData: ['residence_card_data']
    },
    {
        id: StepType.TICKET,
        order: 4,
        nameKey: 'step_ticket',
        icon: '✈️',
        progress: 30,
        isVisible: () => true,
        isRequired: (ctx) => requirementsMatrix.isRequired(DocumentTypes.TICKET.code),
        documentType: DocumentTypes.TICKET.code,
        collectsData: ['ticket_data', 'arrival_date', 'departure_date']
    },
    {
        id: StepType.HOTEL,
        order: 5,
        nameKey: 'step_hotel',
        icon: '🏨',
        progress: 35,
        isVisible: (ctx) => ctx.passportType === PassportType.ORDINAIRE && !ctx.hasInvitation,
        isRequired: (ctx) => ctx.passportType === PassportType.ORDINAIRE && !ctx.hasInvitation && !ctx.hasAccommodation,
        documentType: DocumentTypes.HOTEL.code,
        collectsData: ['hotel_data'],
        alternativeTo: StepType.ACCOMMODATION
    },
    {
        id: StepType.ACCOMMODATION,
        order: 6,
        nameKey: 'step_accommodation',
        icon: '🏠',
        progress: 35,
        isVisible: (ctx) => ctx.passportType === PassportType.ORDINAIRE && !ctx.hasHotel,
        isRequired: (ctx) => ctx.passportType === PassportType.ORDINAIRE && !ctx.hasHotel && !ctx.hasInvitation,
        documentType: DocumentTypes.ACCOMMODATION.code,
        collectsData: ['accommodation_data', 'host_data'],
        alternativeTo: StepType.HOTEL
    },
    {
        id: StepType.VACCINATION,
        order: 7,
        nameKey: 'step_vaccination',
        icon: '💉',
        progress: 45,
        isVisible: (ctx) => {
            // Pas visible si pays exempté de fièvre jaune
            if (ctx.nationality && YELLOW_FEVER_EXEMPT_COUNTRIES.includes(ctx.nationality)) {
                return false;
            }
            return true;
        },
        isRequired: (ctx) => {
            // Non requis pour pays exemptés
            if (ctx.nationality && YELLOW_FEVER_EXEMPT_COUNTRIES.includes(ctx.nationality)) {
                return false;
            }
            const type = ctx.passportType;
            // LP_ONU and LP_UA have optional vaccination
            return type !== PassportType.LP_ONU && type !== PassportType.LP_UA;
        },
        documentType: DocumentTypes.VACCINATION.code,
        collectsData: ['vaccination_data'],
        helpText: {
            fr: 'La vaccination fièvre jaune est valide à vie depuis 2016 (OMS)',
            en: 'Yellow fever vaccination is valid for life since 2016 (WHO)'
        }
    },
    {
        id: StepType.INVITATION,
        order: 8,
        nameKey: 'step_invitation',
        icon: '📄',
        progress: 50,
        isVisible: (ctx) => ctx.passportType === PassportType.ORDINAIRE,
        isRequired: () => false, // Always optional for ORDINAIRE
        documentType: DocumentTypes.INVITATION.code,
        collectsData: ['invitation_data'],
        isOptionalQuestion: true,
        optionalQuestionKey: 'question_has_invitation'
    },
    {
        id: StepType.VERBAL_NOTE,
        order: 9,
        nameKey: 'step_verbal_note',
        icon: '💼',
        progress: 50,
        isVisible: (ctx) => [PassportType.DIPLOMATIQUE, PassportType.SERVICE, PassportType.LP_ONU, PassportType.LP_UA].includes(ctx.passportType),
        isRequired: (ctx) => [PassportType.DIPLOMATIQUE, PassportType.SERVICE, PassportType.LP_ONU, PassportType.LP_UA].includes(ctx.passportType),
        documentType: DocumentTypes.VERBAL_NOTE.code,
        collectsData: ['verbal_note_data']
    },
    {
        id: StepType.ELIGIBILITY,
        order: 10,
        nameKey: 'step_eligibility',
        icon: '✓',
        progress: 55,
        isVisible: () => true,
        isRequired: () => true,
        documentType: null,
        collectsData: ['coherence_score', 'validation_report', 'cross_validation_results'],
        isCheckpoint: true,
        validations: [
            'cross_document_names',      // Cohérence noms entre documents
            'cross_document_dates',      // Cohérence dates voyage
            'passport_expiry_check',     // Passeport expire > 6 mois après retour
            'visa_duration_check',       // Durée séjour ≤ 90 jours
            'document_completeness'      // Tous documents requis présents
        ]
    },
    {
        id: StepType.MINOR_AUTH,
        order: 11,
        nameKey: 'step_minor_auth',
        icon: '👨‍👩‍👧',
        progress: 58,
        isVisible: (ctx) => ctx.isMinor === true,
        isRequired: (ctx) => ctx.isMinor === true,
        documentType: DocumentTypes.PARENTAL_AUTH?.code || 'parental_auth',
        collectsData: ['parental_auth_data', 'birth_certificate_data', 'parent_id_data'],
        helpText: {
            fr: 'Documents requis: autorisation parentale signée, acte de naissance, pièce d\'identité parent/tuteur',
            en: 'Required: signed parental authorization, birth certificate, parent/guardian ID'
        }
    },
    {
        id: StepType.TRANSIT_INFO,
        order: 12,
        nameKey: 'step_transit',
        icon: '🔄',
        progress: 58,
        isVisible: (ctx) => ctx.tripPurpose === 'transit' || ctx.visaType === 'transit',
        isRequired: (ctx) => ctx.tripPurpose === 'transit' || ctx.visaType === 'transit',
        documentType: null,
        collectsData: ['final_destination', 'transit_duration', 'continuation_ticket'],
        helpText: {
            fr: 'Transit maximum 72h. Billet vers destination finale requis.',
            en: 'Maximum 72h transit. Ticket to final destination required.'
        },
        maxTransitHours: 72
    },
    {
        id: StepType.PHOTO,
        order: 13,
        nameKey: 'step_photo',
        icon: '📷',
        progress: 60,
        isVisible: () => true,
        isRequired: () => true,
        documentType: DocumentTypes.PHOTO.code,
        collectsData: ['photo_data'],
        biometricValidation: true,
        faceMatchWithPassport: true
    },
    {
        id: StepType.CONTACT,
        order: 14,
        nameKey: 'step_contact',
        icon: '📞',
        progress: 65,
        isVisible: () => true,
        isRequired: () => true,
        documentType: null,
        collectsData: ['email', 'phone', 'address', 'emergency_contact']
    },
    {
        id: StepType.TRIP,
        order: 15,
        nameKey: 'step_trip',
        icon: '🗓️',
        progress: 70,
        isVisible: () => true,
        isRequired: () => true,
        documentType: null,
        collectsData: ['trip_purpose', 'visa_type', 'entry_count', 'companions', 'is_first_visit']
    },
    {
        id: StepType.HEALTH,
        order: 16,
        nameKey: 'step_health',
        icon: '🏥',
        progress: 75,
        isVisible: () => true,
        isRequired: () => true,
        documentType: null,
        collectsData: ['symptoms', 'recent_countries', 'medical_treatment']
    },
    {
        id: StepType.CUSTOMS,
        order: 17,
        nameKey: 'step_customs',
        icon: '🛃',
        progress: 80,
        isVisible: () => true,
        isRequired: () => true,
        documentType: null,
        collectsData: ['customs_declaration', 'currency_amount', 'goods_to_declare']
    },
    {
        id: StepType.PAYMENT,
        order: 18,
        nameKey: 'step_payment',
        icon: '💳',
        progress: 85,
        isVisible: (ctx) => ctx.passportType === PassportType.ORDINAIRE,
        isRequired: (ctx) => ctx.passportType === PassportType.ORDINAIRE,
        documentType: DocumentTypes.PAYMENT_PROOF.code,
        collectsData: ['payment_proof', 'payment_reference', 'payment_method'],
        paymentMethods: ['card', 'mobile_money']
    },
    {
        id: StepType.CONFIRM,
        order: 19,
        nameKey: 'step_confirm',
        icon: '📋',
        progress: 90,
        isVisible: () => true,
        isRequired: () => true,
        documentType: null,
        collectsData: ['summary_approved'],
        isCheckpoint: true,
        showsSummary: true
    },
    {
        id: StepType.SIGNATURE,
        order: 20,
        nameKey: 'step_signature',
        icon: '✍️',
        progress: 95,
        isVisible: () => true,
        isRequired: () => true,
        documentType: null,
        collectsData: ['signature', 'terms_accepted', 'application_reference'],
        legalDeclaration: true
    },
    {
        id: StepType.COMPLETION,
        order: 21,
        nameKey: 'step_completion',
        icon: '🎉',
        progress: 100,
        isVisible: () => true,
        isRequired: () => true,
        documentType: null,
        collectsData: ['submission_timestamp', 'reference_number', 'estimated_processing_days'],
        isFinal: true,
        showsConfirmation: true
    }
];

/**
 * DocumentFlow class
 * Manages the flow of document collection and step progression
 */
export class DocumentFlow {
    constructor(options = {}) {
        this.steps = FLOW_STEPS;
        this.context = {};
        this.collectedData = {};
        this.stepStatuses = new Map();
        this.currentStepId = StepType.WELCOME;
        this.history = [];
        this.listeners = new Map();

        // Initialize step statuses
        for (const step of this.steps) {
            this.stepStatuses.set(step.id, StepStatus.PENDING);
        }
        this.stepStatuses.set(StepType.WELCOME, StepStatus.ACTIVE);

        // Link to requirements matrix
        requirementsMatrix.on('contextChanged', (ctx) => {
            this.updateContext(ctx);
        });
    }

    /**
     * Update context and recalculate visible steps
     */
    updateContext(newContext) {
        this.context = { ...this.context, ...newContext };
        requirementsMatrix.setContext(this.context);
        this.emit('contextUpdated', this.context);
        this.emit('flowUpdated', this.getFlowState());
    }

    /**
     * Get current context
     */
    getContext() {
        return { ...this.context };
    }

    /**
     * Set collected data for a step
     */
    setStepData(stepId, data) {
        this.collectedData[stepId] = { ...this.collectedData[stepId], ...data };
        this.emit('dataCollected', { stepId, data });
    }

    /**
     * Get collected data for a step
     */
    getStepData(stepId) {
        return this.collectedData[stepId] || {};
    }

    /**
     * Get all collected data
     */
    getAllCollectedData() {
        return { ...this.collectedData };
    }

    /**
     * Get visible steps based on context
     */
    getVisibleSteps() {
        return this.steps.filter(step => {
            try {
                return step.isVisible(this.context);
            } catch (e) {
                console.warn(`Error evaluating visibility for step ${step.id}:`, e);
                return true; // Default to visible on error
            }
        });
    }

    /**
     * Get required steps
     */
    getRequiredSteps() {
        return this.getVisibleSteps().filter(step => {
            try {
                return step.isRequired(this.context);
            } catch (e) {
                console.warn(`Error evaluating requirement for step ${step.id}:`, e);
                return true;
            }
        });
    }

    /**
     * Get current step
     */
    getCurrentStep() {
        return this.steps.find(s => s.id === this.currentStepId);
    }

    /**
     * Get step by ID
     */
    getStep(stepId) {
        return this.steps.find(s => s.id === stepId);
    }

    /**
     * Get step status
     */
    getStepStatus(stepId) {
        return this.stepStatuses.get(stepId) || StepStatus.PENDING;
    }

    /**
     * Set step status
     */
    setStepStatus(stepId, status) {
        this.stepStatuses.set(stepId, status);
        this.emit('stepStatusChanged', { stepId, status });
    }

    /**
     * Complete current step and advance
     * @param {Object} data - Data collected in this step
     * @returns {Object} Next step info
     */
    completeCurrentStep(data = {}) {
        const currentStep = this.getCurrentStep();
        if (!currentStep) {
            throw new Error('No current step to complete');
        }

        // Store collected data
        if (data && Object.keys(data).length > 0) {
            this.setStepData(currentStep.id, data);

            // Update context with relevant data
            for (const key of currentStep.collectsData || []) {
                if (data[key] !== undefined) {
                    this.context[key] = data[key];
                }
            }
        }

        // Mark current step as completed
        this.setStepStatus(currentStep.id, StepStatus.COMPLETED);

        // Record in history
        this.history.push({
            stepId: currentStep.id,
            action: 'completed',
            timestamp: Date.now(),
            data: { ...data }
        });

        // Find next step
        const nextStep = this.findNextStep(currentStep);

        if (nextStep) {
            this.currentStepId = nextStep.id;
            this.setStepStatus(nextStep.id, StepStatus.ACTIVE);
            this.emit('stepChanged', { previous: currentStep.id, current: nextStep.id });
        } else {
            this.emit('flowCompleted', this.getAllCollectedData());
        }

        return nextStep ? { step: nextStep, isLast: nextStep.isFinal } : null;
    }

    /**
     * Skip current step (only for optional steps)
     * @param {string} reason - Reason for skipping
     */
    skipCurrentStep(reason = 'user_declined') {
        const currentStep = this.getCurrentStep();
        if (!currentStep) {
            throw new Error('No current step to skip');
        }

        // Check if step can be skipped
        if (currentStep.isRequired(this.context)) {
            throw new Error('Cannot skip required step');
        }

        // Mark as skipped
        this.setStepStatus(currentStep.id, StepStatus.SKIPPED);
        this.setStepData(currentStep.id, { skipped: true, skipReason: reason });

        // Update context
        if (currentStep.documentType) {
            this.context[`has${this.capitalize(currentStep.documentType)}`] = false;
        }

        // Record in history
        this.history.push({
            stepId: currentStep.id,
            action: 'skipped',
            timestamp: Date.now(),
            reason
        });

        // Find next step
        const nextStep = this.findNextStep(currentStep);

        if (nextStep) {
            this.currentStepId = nextStep.id;
            this.setStepStatus(nextStep.id, StepStatus.ACTIVE);
            this.emit('stepChanged', { previous: currentStep.id, current: nextStep.id });
        }

        return nextStep;
    }

    /**
     * Go back to previous step
     */
    goBack() {
        const visibleSteps = this.getVisibleSteps();
        const currentIndex = visibleSteps.findIndex(s => s.id === this.currentStepId);

        if (currentIndex <= 0) {
            return null; // Can't go back from first step
        }

        const previousStep = visibleSteps[currentIndex - 1];
        const currentStep = this.getCurrentStep();

        // Mark current as pending again
        this.setStepStatus(currentStep.id, StepStatus.PENDING);

        // Go to previous
        this.currentStepId = previousStep.id;
        this.setStepStatus(previousStep.id, StepStatus.ACTIVE);

        this.emit('stepChanged', { previous: currentStep.id, current: previousStep.id, direction: 'back' });

        return previousStep;
    }

    /**
     * Navigate to specific step (if allowed)
     * @param {string} stepId
     */
    navigateTo(stepId) {
        const targetStep = this.getStep(stepId);
        if (!targetStep) {
            throw new Error(`Step ${stepId} not found`);
        }

        // Check if step is accessible (completed or current)
        const status = this.getStepStatus(stepId);
        if (status !== StepStatus.COMPLETED && stepId !== this.currentStepId) {
            // Can only navigate to completed steps
            const visibleSteps = this.getVisibleSteps();
            const targetIndex = visibleSteps.findIndex(s => s.id === stepId);
            const currentIndex = visibleSteps.findIndex(s => s.id === this.currentStepId);

            if (targetIndex > currentIndex) {
                throw new Error('Cannot navigate to future steps');
            }
        }

        const previousStepId = this.currentStepId;
        this.currentStepId = stepId;
        this.setStepStatus(stepId, StepStatus.ACTIVE);

        this.emit('stepChanged', { previous: previousStepId, current: stepId, direction: 'navigate' });

        return targetStep;
    }

    /**
     * Find the next visible step after current
     * @private
     */
    findNextStep(currentStep) {
        const visibleSteps = this.getVisibleSteps();
        const currentIndex = visibleSteps.findIndex(s => s.id === currentStep.id);

        if (currentIndex < 0 || currentIndex >= visibleSteps.length - 1) {
            return null;
        }

        // Find next visible step that isn't blocked
        for (let i = currentIndex + 1; i < visibleSteps.length; i++) {
            const step = visibleSteps[i];

            // Skip alternatives if main is completed
            if (step.alternativeTo) {
                const alternativeStatus = this.getStepStatus(step.alternativeTo);
                if (alternativeStatus === StepStatus.COMPLETED) {
                    continue;
                }
            }

            return step;
        }

        return null;
    }

    /**
     * Get flow state for UI
     */
    getFlowState() {
        const visibleSteps = this.getVisibleSteps();
        const currentStep = this.getCurrentStep();
        const completion = this.getCompletionPercentage();

        return {
            steps: visibleSteps.map(step => ({
                ...step,
                status: this.getStepStatus(step.id),
                isCurrent: step.id === this.currentStepId,
                isAccessible: this.isStepAccessible(step.id),
                data: this.getStepData(step.id)
            })),
            currentStep: currentStep ? {
                ...currentStep,
                status: this.getStepStatus(currentStep.id)
            } : null,
            progress: completion.percentage,
            completedSteps: completion.completed,
            totalSteps: completion.total,
            isComplete: completion.isComplete,
            context: this.context
        };
    }

    /**
     * Check if step is accessible for navigation
     */
    isStepAccessible(stepId) {
        const status = this.getStepStatus(stepId);
        return status === StepStatus.COMPLETED || stepId === this.currentStepId;
    }

    /**
     * Get completion percentage
     */
    getCompletionPercentage() {
        const requiredSteps = this.getRequiredSteps();
        const completed = requiredSteps.filter(step =>
            this.getStepStatus(step.id) === StepStatus.COMPLETED ||
            this.getStepStatus(step.id) === StepStatus.SKIPPED
        );

        const currentStep = this.getCurrentStep();
        const percentage = currentStep ? currentStep.progress : 0;

        return {
            percentage,
            completed: completed.length,
            total: requiredSteps.length,
            isComplete: completed.length === requiredSteps.length
        };
    }

    /**
     * Get pending documents based on requirements matrix
     */
    getPendingDocuments() {
        const requiredDocs = requirementsMatrix.getRequiredDocuments();
        const pending = [];

        for (const docCode of requiredDocs) {
            const step = this.steps.find(s => s.documentType === docCode);
            if (step) {
                const status = this.getStepStatus(step.id);
                if (status !== StepStatus.COMPLETED) {
                    pending.push({
                        docCode,
                        stepId: step.id,
                        meta: requirementsMatrix.getDocumentMeta(docCode)
                    });
                }
            }
        }

        return pending;
    }

    /**
     * Validate current step data
     * @param {Object} data - Data to validate
     * @returns {Object} Validation result
     */
    validateStepData(stepId, data) {
        const step = this.getStep(stepId);
        if (!step) {
            return { valid: false, errors: ['Step not found'] };
        }

        const errors = [];
        const warnings = [];

        // Check required fields based on collectsData
        for (const field of step.collectsData || []) {
            if (data[field] === undefined || data[field] === null || data[field] === '') {
                // Check if this specific field is required
                if (step.isRequired(this.context)) {
                    errors.push({ field, message: `${field} is required` });
                }
            }
        }

        // Document-specific validation would go here
        // This will be extended by validation-ui.js

        return {
            valid: errors.length === 0,
            errors,
            warnings
        };
    }

    /**
     * Reset flow to beginning
     */
    reset() {
        this.context = {};
        this.collectedData = {};
        this.history = [];
        this.currentStepId = StepType.WELCOME;

        for (const step of this.steps) {
            this.stepStatuses.set(step.id, StepStatus.PENDING);
        }
        this.stepStatuses.set(StepType.WELCOME, StepStatus.ACTIVE);

        requirementsMatrix.setContext({});

        this.emit('flowReset');
    }

    /**
     * Export flow state for saving/resuming
     */
    exportState() {
        return {
            context: this.context,
            collectedData: this.collectedData,
            stepStatuses: Object.fromEntries(this.stepStatuses),
            currentStepId: this.currentStepId,
            history: this.history,
            timestamp: Date.now()
        };
    }

    /**
     * Import flow state for resuming
     */
    importState(state) {
        if (!state) return;

        this.context = state.context || {};
        this.collectedData = state.collectedData || {};
        this.currentStepId = state.currentStepId || StepType.WELCOME;
        this.history = state.history || [];

        if (state.stepStatuses) {
            for (const [stepId, status] of Object.entries(state.stepStatuses)) {
                this.stepStatuses.set(stepId, status);
            }
        }

        requirementsMatrix.setContext(this.context);
        this.emit('stateImported', state);
    }

    // Helper methods
    capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
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
export const documentFlow = new DocumentFlow();

// Export all
export default {
    DocumentFlow,
    documentFlow,
    StepType,
    StepStatus,
    FLOW_STEPS
};
