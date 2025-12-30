/**
 * TimeEstimator - Dynamic Time Remaining Estimation
 *
 * Calculates and displays estimated time remaining:
 * - Based on remaining steps
 * - Considers document upload times
 * - Adapts to user pace
 * - Shows encouraging messages
 *
 * @version 6.0.0
 * @author Visa Chatbot Team
 */

/**
 * Default time estimates per step type (in seconds)
 */
const DEFAULT_STEP_TIMES = {
    welcome: 30,
    passport: 60,      // Document upload + OCR
    residence: 20,     // Simple confirmation
    ticket: 60,        // Document upload + OCR
    hotel: 60,         // Document upload + OCR
    vaccination: 45,   // Document upload + OCR
    invitation: 45,    // Document upload + OCR
    photo: 30,         // Photo upload
    contact: 45,       // Form filling
    trip: 60,          // Multiple sub-steps
    customs: 20,       // Simple declaration
    payment: 90,       // Payment process
    confirm: 30        // Final review
};

/**
 * Time adjustments based on conditions
 */
const TIME_ADJUSTMENTS = {
    hasDocument: -15,      // Reduces time if document already uploaded
    ocrFailed: 60,         // Extra time for manual entry
    hasPrefill: -20,       // Reduces time if prefilled
    isReturning: -10,      // Faster for returning users
    isExpress: 30          // Extra steps for express
};

export class TimeEstimator {

    /**
     * Constructor
     * @param {Object} options Configuration options
     */
    constructor(options = {}) {
        this.language = options.language || 'fr';
        this.startTime = Date.now();
        this.stepTimes = { ...DEFAULT_STEP_TIMES };
        this.completedSteps = [];
        this.currentStep = null;
        this.actualTimes = {};
        this.container = options.container || null;
        this.updateInterval = null;

        this.init();
    }

    /**
     * Initialize the estimator
     */
    init() {
        this.bindEvents();
        this.loadHistoricalData();
    }

    /**
     * Bind event listeners
     */
    bindEvents() {
        document.addEventListener('workflow-step-change', (e) => {
            this.onStepChange(e.detail);
        });

        document.addEventListener('document-validated', (e) => {
            this.onDocumentValidated(e.detail);
        });

        document.addEventListener('prefill-applied', (e) => {
            this.onPrefillApplied(e.detail);
        });
    }

    /**
     * Load historical timing data from localStorage
     */
    loadHistoricalData() {
        try {
            const stored = localStorage.getItem('timeEstimatorHistory');
            if (stored) {
                const history = JSON.parse(stored);
                // Merge with defaults, preferring historical data
                Object.keys(history).forEach(step => {
                    if (history[step] > 0) {
                        this.stepTimes[step] = Math.round(
                            (this.stepTimes[step] + history[step]) / 2
                        );
                    }
                });
            }
        } catch (e) {
            console.warn('Failed to load time estimator history');
        }
    }

    /**
     * Save historical timing data
     */
    saveHistoricalData() {
        try {
            localStorage.setItem('timeEstimatorHistory', JSON.stringify(this.actualTimes));
        } catch (e) {
            console.warn('Failed to save time estimator history');
        }
    }

    /**
     * Handle step change
     * @param {Object} detail Step change details
     */
    onStepChange(detail) {
        const { step, previousStep } = detail;

        // Record time for previous step
        if (previousStep && this.stepStartTime) {
            const duration = (Date.now() - this.stepStartTime) / 1000;
            this.actualTimes[previousStep] = duration;
            this.completedSteps.push(previousStep);

            // Update estimate for future steps based on actual performance
            this.adjustEstimates(previousStep, duration);
        }

        // Start timing new step
        this.currentStep = step;
        this.stepStartTime = Date.now();

        // Update display
        this.updateDisplay();
    }

    /**
     * Handle document validated
     * @param {Object} detail Document details
     */
    onDocumentValidated(detail) {
        // Reduce remaining time estimate
        if (this.currentStep && this.stepTimes[this.currentStep]) {
            this.stepTimes[this.currentStep] += TIME_ADJUSTMENTS.hasDocument;
        }
        this.updateDisplay();
    }

    /**
     * Handle prefill applied
     * @param {Object} detail Prefill details
     */
    onPrefillApplied(detail) {
        // Reduce time estimate for prefilled steps
        const { docType, fieldsCount } = detail;
        if (docType && this.stepTimes[docType]) {
            // More fields prefilled = more time saved
            const reduction = Math.min(fieldsCount * 5, TIME_ADJUSTMENTS.hasPrefill);
            this.stepTimes[docType] += reduction;
        }
        this.updateDisplay();
    }

    /**
     * Adjust estimates based on actual performance
     * @param {string} step Completed step
     * @param {number} duration Actual duration in seconds
     */
    adjustEstimates(step, duration) {
        const estimated = DEFAULT_STEP_TIMES[step] || 60;
        const ratio = duration / estimated;

        // Adjust all remaining step estimates based on user's pace
        if (ratio !== 1) {
            Object.keys(this.stepTimes).forEach(s => {
                if (!this.completedSteps.includes(s) && s !== this.currentStep) {
                    this.stepTimes[s] = Math.round(this.stepTimes[s] * (0.7 + 0.3 * ratio));
                }
            });
        }
    }

    /**
     * Get remaining time in seconds
     * @param {Array} remainingSteps Steps yet to complete
     * @returns {number} Estimated seconds remaining
     */
    getRemainingTime(remainingSteps = null) {
        if (!remainingSteps) {
            remainingSteps = this.getRemainingSteps();
        }

        let total = 0;
        remainingSteps.forEach(step => {
            total += this.stepTimes[step] || DEFAULT_STEP_TIMES[step] || 60;
        });

        return Math.max(0, total);
    }

    /**
     * Get remaining steps from workflow
     * @returns {Array} Remaining step IDs
     */
    getRemainingSteps() {
        const allSteps = Object.keys(DEFAULT_STEP_TIMES);
        const currentIndex = allSteps.indexOf(this.currentStep);

        if (currentIndex === -1) {
            return allSteps;
        }

        return allSteps.slice(currentIndex);
    }

    /**
     * Format time for display
     * @param {number} seconds Time in seconds
     * @returns {string} Formatted time string
     */
    formatTime(seconds) {
        if (seconds <= 0) {
            return this.language === 'fr' ? 'Termine !' : 'Complete!';
        }

        const minutes = Math.ceil(seconds / 60);

        if (minutes <= 1) {
            return this.language === 'fr'
                ? '~1 minute restante'
                : '~1 minute remaining';
        }

        return this.language === 'fr'
            ? `~${minutes} minutes restantes`
            : `~${minutes} minutes remaining`;
    }

    /**
     * Get encouraging message based on progress
     * @param {number} progress Progress percentage (0-100)
     * @returns {string} Encouraging message
     */
    getEncouragingMessage(progress) {
        const messages = {
            fr: {
                start: 'C\'est parti ! 🚀',
                quarter: 'Bon debut ! 💪',
                halfway: 'Mi-parcours atteint ! 🌟',
                almostDone: 'Presque fini ! 🔥',
                complete: 'Felicitations ! 🎉'
            },
            en: {
                start: 'Let\'s go! 🚀',
                quarter: 'Great start! 💪',
                halfway: 'Halfway there! 🌟',
                almostDone: 'Almost done! 🔥',
                complete: 'Congratulations! 🎉'
            }
        };

        const lang = this.language === 'fr' ? messages.fr : messages.en;

        if (progress >= 100) return lang.complete;
        if (progress >= 75) return lang.almostDone;
        if (progress >= 50) return lang.halfway;
        if (progress >= 25) return lang.quarter;
        return lang.start;
    }

    /**
     * Update the display
     */
    updateDisplay() {
        const remaining = this.getRemainingTime();
        const formatted = this.formatTime(remaining);

        // Update container if exists
        if (this.container) {
            this.container.textContent = formatted;
        }

        // Update dedicated element
        const timeElement = document.getElementById('time-estimate');
        if (timeElement) {
            const textElement = timeElement.querySelector('.time-text');
            if (textElement) {
                textElement.textContent = formatted;
            }
        }

        // Dispatch event for other components
        document.dispatchEvent(new CustomEvent('time-estimate-updated', {
            detail: {
                remainingSeconds: remaining,
                formattedTime: formatted,
                currentStep: this.currentStep
            }
        }));
    }

    /**
     * Start auto-update interval
     * @param {number} intervalMs Update interval in milliseconds
     */
    startAutoUpdate(intervalMs = 30000) {
        this.stopAutoUpdate();
        this.updateInterval = setInterval(() => {
            this.updateDisplay();
        }, intervalMs);
    }

    /**
     * Stop auto-update interval
     */
    stopAutoUpdate() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
            this.updateInterval = null;
        }
    }

    /**
     * Get time estimate data
     * @returns {Object} Current estimate data
     */
    getEstimateData() {
        const remainingSeconds = this.getRemainingTime();
        const progress = this.calculateProgress();

        return {
            remainingSeconds,
            remainingMinutes: Math.ceil(remainingSeconds / 60),
            formattedTime: this.formatTime(remainingSeconds),
            currentStep: this.currentStep,
            completedSteps: this.completedSteps.length,
            totalSteps: Object.keys(DEFAULT_STEP_TIMES).length,
            progress,
            encouragingMessage: this.getEncouragingMessage(progress),
            elapsedSeconds: (Date.now() - this.startTime) / 1000
        };
    }

    /**
     * Calculate progress percentage
     * @returns {number} Progress 0-100
     */
    calculateProgress() {
        const total = Object.keys(DEFAULT_STEP_TIMES).length;
        const completed = this.completedSteps.length;
        return Math.round((completed / total) * 100);
    }

    /**
     * Set language
     * @param {string} lang Language code (fr/en)
     */
    setLanguage(lang) {
        this.language = lang;
        this.updateDisplay();
    }

    /**
     * Reset the estimator
     */
    reset() {
        this.startTime = Date.now();
        this.stepTimes = { ...DEFAULT_STEP_TIMES };
        this.completedSteps = [];
        this.currentStep = null;
        this.actualTimes = {};
        this.stepStartTime = null;
    }

    /**
     * Complete session and save data
     */
    complete() {
        this.stopAutoUpdate();
        this.saveHistoricalData();

        // Log final statistics
        const totalTime = (Date.now() - this.startTime) / 1000;
        console.log('Application completed in', Math.round(totalTime), 'seconds');
    }

    /**
     * Destroy the estimator
     */
    destroy() {
        this.stopAutoUpdate();
    }
}

// Default export
export default TimeEstimator;
