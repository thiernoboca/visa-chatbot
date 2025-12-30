/**
 * ProgressTracker - Visual Progress Bar and Step Indicators
 *
 * Provides animated progress visualization:
 * - Step indicators (completed, current, pending)
 * - Progress bar with shine animation
 * - Document count and validation status
 * - Time remaining estimation
 *
 * @version 6.0.0
 * @author Visa Chatbot Team
 */

import { CONFIG } from './config.js';
import stateManager from './state.js';

/**
 * Workflow steps configuration
 */
const WORKFLOW_STEPS = [
    { id: 'passport', icon: 'badge', label: { fr: 'Passeport', en: 'Passport' } },
    { id: 'residence', icon: 'location_on', label: { fr: 'Résidence', en: 'Residence' } },
    { id: 'ticket', icon: 'flight', label: { fr: 'Vol', en: 'Flight' } },
    { id: 'hotel', icon: 'hotel', label: { fr: 'Hôtel', en: 'Hotel' } },
    { id: 'vaccination', icon: 'vaccines', label: { fr: 'Vaccin', en: 'Vaccine' } },
    { id: 'photo', icon: 'photo_camera', label: { fr: 'Photo', en: 'Photo' } },
    { id: 'contact', icon: 'contact_mail', label: { fr: 'Contact', en: 'Contact' } },
    { id: 'trip', icon: 'calendar_month', label: { fr: 'Voyage', en: 'Trip' } },
    { id: 'confirm', icon: 'check_circle', label: { fr: 'Confirmation', en: 'Confirm' } }
];

/**
 * Optional steps that may be added dynamically
 */
const OPTIONAL_STEPS = {
    invitation: { id: 'invitation', icon: 'mail', label: { fr: 'Invitation', en: 'Invitation' } },
    residence_card: { id: 'residence_card', icon: 'credit_card', label: { fr: 'Carte résident', en: 'Residence Card' } },
    payment: { id: 'payment', icon: 'payments', label: { fr: 'Paiement', en: 'Payment' } },
    verbal_note: { id: 'verbal_note', icon: 'description', label: { fr: 'Note verbale', en: 'Verbal Note' } }
};

export class ProgressTracker {

    /**
     * Constructor
     * @param {Object} options Configuration options
     */
    constructor(options = {}) {
        this.container = options.container || document.getElementById('progress-tracker');
        this.language = options.language || 'fr';
        this.steps = [...WORKFLOW_STEPS];
        this.currentStepIndex = 0;
        this.completedSteps = new Set();
        this.documentsValidated = 0;
        this.totalDocuments = 0;
        this.isVisible = false;

        this.init();
    }

    /**
     * Initialize the progress tracker
     */
    init() {
        if (!this.container) {
            this.createContainer();
        }

        this.render();
        this.bindEvents();
    }

    /**
     * Create the container element if not exists
     */
    createContainer() {
        this.container = document.createElement('div');
        this.container.id = 'progress-tracker';
        this.container.className = 'progress-tracker-wrapper hidden';

        // Insert before chat messages
        const chatHistory = document.getElementById('chat-history');
        if (chatHistory) {
            chatHistory.parentNode.insertBefore(this.container, chatHistory);
        }
    }

    /**
     * Render the progress tracker
     */
    render() {
        const progress = this.calculateProgress();
        const lang = this.language;

        this.container.innerHTML = `
            <div class="progress-tracker" role="progressbar" aria-valuenow="${progress}" aria-valuemin="0" aria-valuemax="100">
                <!-- Header -->
                <div class="progress-header">
                    <div class="progress-title">
                        <span class="progress-flag">🇨🇮</span>
                        <span class="progress-text">${lang === 'fr' ? 'Votre voyage vers la Côte d\'Ivoire' : 'Your journey to Côte d\'Ivoire'}</span>
                    </div>
                    <div class="progress-stats">
                        <span class="docs-count">
                            <span class="material-symbols-outlined">check_circle</span>
                            ${this.documentsValidated}/${this.totalDocuments} ${lang === 'fr' ? 'docs' : 'docs'}
                        </span>
                    </div>
                </div>

                <!-- Step indicators -->
                <div class="progress-steps">
                    ${this.renderSteps()}
                </div>

                <!-- Progress bar -->
                <div class="progress-bar-container">
                    <div class="progress-bar">
                        <div class="progress-bar-fill" style="width: ${progress}%"></div>
                    </div>
                    <span class="progress-percentage">${progress}%</span>
                </div>

                <!-- Time estimate -->
                <div class="progress-time" id="time-estimate">
                    <span class="material-symbols-outlined time-icon">schedule</span>
                    <span class="time-text">${this.getTimeEstimateText()}</span>
                </div>
            </div>
        `;

        // Animate the progress bar
        setTimeout(() => {
            const fill = this.container.querySelector('.progress-bar-fill');
            if (fill) {
                fill.style.transition = 'width 0.5s ease-out';
            }
        }, 100);
    }

    /**
     * Render step indicators
     */
    renderSteps() {
        return this.steps.map((step, index) => {
            let status = 'pending';
            if (this.completedSteps.has(step.id)) {
                status = 'completed';
            } else if (index === this.currentStepIndex) {
                status = 'current';
            }

            const label = step.label[this.language] || step.label.en;

            return `
                <div class="step-item ${status}" data-step="${step.id}" title="${label}">
                    <div class="step-indicator">
                        ${status === 'completed'
                            ? '<span class="material-symbols-outlined">check</span>'
                            : `<span class="material-symbols-outlined">${step.icon}</span>`
                        }
                    </div>
                    <span class="step-label">${label}</span>
                    ${index < this.steps.length - 1 ? '<div class="step-connector"></div>' : ''}
                </div>
            `;
        }).join('');
    }

    /**
     * Calculate progress percentage
     */
    calculateProgress() {
        if (this.steps.length === 0) return 0;
        return Math.round((this.completedSteps.size / this.steps.length) * 100);
    }

    /**
     * Get time estimate text
     */
    getTimeEstimateText() {
        const remainingSteps = this.steps.length - this.completedSteps.size;
        const avgTimePerStep = 1.5; // minutes
        const remaining = Math.ceil(remainingSteps * avgTimePerStep);

        if (remaining <= 0) {
            return this.language === 'fr' ? 'Terminé !' : 'Complete!';
        }

        if (remaining === 1) {
            return this.language === 'fr' ? '~1 minute restante' : '~1 minute remaining';
        }

        return this.language === 'fr'
            ? `~${remaining} minutes restantes`
            : `~${remaining} minutes remaining`;
    }

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Listen for step changes from state manager
        document.addEventListener('workflow-step-change', (e) => {
            this.setCurrentStep(e.detail.step);
        });

        // Listen for document validation
        document.addEventListener('document-validated', (e) => {
            this.onDocumentValidated(e.detail);
        });
    }

    /**
     * Set current step
     * @param {string} stepId Step identifier
     */
    setCurrentStep(stepId) {
        const index = this.steps.findIndex(s => s.id === stepId);
        if (index !== -1) {
            // Mark previous steps as completed
            for (let i = 0; i < index; i++) {
                this.completedSteps.add(this.steps[i].id);
            }

            this.currentStepIndex = index;
            this.render();
            this.show();
        }
    }

    /**
     * Mark step as completed
     * @param {string} stepId Step identifier
     */
    completeStep(stepId) {
        this.completedSteps.add(stepId);

        // Move to next step if current
        const currentStep = this.steps[this.currentStepIndex];
        if (currentStep && currentStep.id === stepId) {
            this.currentStepIndex = Math.min(this.currentStepIndex + 1, this.steps.length - 1);
        }

        this.render();
        this.checkMilestones();
    }

    /**
     * Add optional step to workflow
     * @param {string} stepId Optional step identifier
     * @param {number} afterIndex Insert after this index
     */
    addOptionalStep(stepId, afterIndex = null) {
        if (OPTIONAL_STEPS[stepId] && !this.steps.find(s => s.id === stepId)) {
            const step = OPTIONAL_STEPS[stepId];
            if (afterIndex !== null) {
                this.steps.splice(afterIndex + 1, 0, step);
            } else {
                // Insert before confirm step
                const confirmIndex = this.steps.findIndex(s => s.id === 'confirm');
                this.steps.splice(confirmIndex, 0, step);
            }
            this.render();
        }
    }

    /**
     * Remove optional step from workflow
     * @param {string} stepId Step identifier
     */
    removeOptionalStep(stepId) {
        const index = this.steps.findIndex(s => s.id === stepId);
        if (index !== -1) {
            this.steps.splice(index, 1);
            this.completedSteps.delete(stepId);
            this.render();
        }
    }

    /**
     * Handle document validation
     * @param {Object} detail Validation details
     */
    onDocumentValidated(detail) {
        this.documentsValidated++;
        this.totalDocuments = Math.max(this.totalDocuments, this.documentsValidated);
        this.render();

        // Trigger celebration
        document.dispatchEvent(new CustomEvent('trigger-celebration', {
            detail: {
                type: 'document',
                docType: detail.docType
            }
        }));
    }

    /**
     * Update document counts
     * @param {number} validated Number of validated documents
     * @param {number} total Total expected documents
     */
    updateDocumentCounts(validated, total) {
        this.documentsValidated = validated;
        this.totalDocuments = total;
        this.render();
    }

    /**
     * Check and trigger milestone celebrations
     */
    checkMilestones() {
        const progress = this.calculateProgress();

        if (progress === 50 && !this._milestone50) {
            this._milestone50 = true;
            document.dispatchEvent(new CustomEvent('trigger-celebration', {
                detail: { type: 'milestone', milestone: '50%' }
            }));
        }

        if (progress === 75 && !this._milestone75) {
            this._milestone75 = true;
            document.dispatchEvent(new CustomEvent('trigger-celebration', {
                detail: { type: 'milestone', milestone: '75%' }
            }));
        }

        if (progress === 100 && !this._milestone100) {
            this._milestone100 = true;
            document.dispatchEvent(new CustomEvent('trigger-celebration', {
                detail: { type: 'milestone', milestone: '100%' }
            }));
        }
    }

    /**
     * Show the progress tracker
     */
    show() {
        if (!this.isVisible) {
            this.isVisible = true;
            this.container.classList.remove('hidden');
            this.container.classList.add('animate-fade-in');
        }
    }

    /**
     * Hide the progress tracker
     */
    hide() {
        this.isVisible = false;
        this.container.classList.add('hidden');
    }

    /**
     * Set language
     * @param {string} lang Language code (fr/en)
     */
    setLanguage(lang) {
        this.language = lang;
        this.render();
    }

    /**
     * Reset progress tracker
     */
    reset() {
        this.currentStepIndex = 0;
        this.completedSteps.clear();
        this.documentsValidated = 0;
        this.steps = [...WORKFLOW_STEPS];
        this._milestone50 = false;
        this._milestone75 = false;
        this._milestone100 = false;
        this.render();
        this.hide();
    }

    /**
     * Get current progress data
     */
    getProgressData() {
        return {
            currentStep: this.steps[this.currentStepIndex]?.id,
            completedSteps: Array.from(this.completedSteps),
            progress: this.calculateProgress(),
            documentsValidated: this.documentsValidated,
            totalDocuments: this.totalDocuments,
            totalSteps: this.steps.length
        };
    }
}

// Default export
export default ProgressTracker;
