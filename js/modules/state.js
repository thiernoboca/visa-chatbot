/**
 * State Management Module
 * Centralized state for the chatbot application
 *
 * @version 4.0.0
 * @module State
 */

/**
 * Initial state factory
 * @returns {Object} Fresh state object
 */
export function createInitialState() {
    return {
        // Session
        sessionId: null,
        isInitialized: false,

        // Current workflow position
        currentStep: 'welcome',
        stepIndex: 0,
        highestStepReached: 0,
        stepsInfo: {},

        // Workflow type
        workflowType: 'STANDARD', // STANDARD or PRIORITY
        workflowCategory: null,

        // User information extracted
        userFirstName: null,
        userFullName: null,
        nationality: null,
        residenceCountry: null,
        passportType: 'ORDINAIRE',

        // Collected form data
        collectedData: {
            passport: null,
            photo: null,
            contact: null,
            trip: null,
            health: null,
            customs: null,
            documents: {}
        },

        // Document tracking
        requiredDocuments: [],
        uploadedDocuments: {},
        documentQueue: [],

        // UI state
        isTyping: false,
        isProcessingQueue: false,
        messageQueue: [],
        pendingInputType: null,
        pendingDocumentAccept: null,

        // Files being processed
        passportFile: null,
        currentUploadFile: null,

        // Settings
        language: 'fr',
        darkMode: false,

        // Tracking
        stepStartTime: null,
        messages: []
    };
}

/**
 * State Manager class with event emitter pattern
 */
export class StateManager {
    constructor() {
        this.state = createInitialState();
        this.listeners = new Map();
        this.history = [];
        this.maxHistoryLength = 50;
    }

    /**
     * Get current state or a specific path
     * @param {string} path - Optional dot-separated path
     * @returns {*}
     */
    get(path = null) {
        if (!path) return { ...this.state };

        const keys = path.split('.');
        let value = this.state;

        for (const key of keys) {
            if (value && typeof value === 'object' && key in value) {
                value = value[key];
            } else {
                return undefined;
            }
        }

        return value;
    }

    /**
     * Set state value at path
     * @param {string} path - Dot-separated path
     * @param {*} value - New value
     * @param {boolean} silent - Skip event emission
     */
    set(path, value, silent = false) {
        const keys = path.split('.');
        const lastKey = keys.pop();
        let target = this.state;

        for (const key of keys) {
            if (!(key in target)) {
                target[key] = {};
            }
            target = target[key];
        }

        const oldValue = target[lastKey];
        target[lastKey] = value;

        // Track history
        this.history.push({ path, oldValue, newValue: value, timestamp: Date.now() });
        if (this.history.length > this.maxHistoryLength) {
            this.history.shift();
        }

        if (!silent) {
            this.emit(path, value, oldValue);
            this.emit('change', { path, value, oldValue });
        }
    }

    /**
     * Update multiple state values
     * @param {Object} updates - Key-value pairs to update
     */
    update(updates) {
        for (const [path, value] of Object.entries(updates)) {
            this.set(path, value, true);
        }
        this.emit('change', { updates });
    }

    /**
     * Reset state to initial values
     */
    reset() {
        this.state = createInitialState();
        this.history = [];
        this.emit('reset');
    }

    /**
     * Subscribe to state changes
     * @param {string} event - Event name or path
     * @param {Function} callback
     * @returns {Function} Unsubscribe function
     */
    on(event, callback) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, new Set());
        }
        this.listeners.get(event).add(callback);

        return () => {
            this.listeners.get(event)?.delete(callback);
        };
    }

    /**
     * Emit event to listeners
     * @param {string} event
     * @param  {...any} args
     */
    emit(event, ...args) {
        const listeners = this.listeners.get(event);
        if (listeners) {
            listeners.forEach(callback => {
                try {
                    callback(...args);
                } catch (error) {
                    console.error(`Error in state listener for ${event}:`, error);
                }
            });
        }
    }

    /**
     * Get session ID
     * @returns {string|null}
     */
    getSessionId() {
        return this.state.sessionId;
    }

    /**
     * Set session ID
     * @param {string} id
     */
    setSessionId(id) {
        this.set('sessionId', id);
    }

    /**
     * Get current step
     * @returns {string}
     */
    getCurrentStep() {
        return this.state.currentStep;
    }

    /**
     * Set current step
     * @param {string} step
     */
    setCurrentStep(step) {
        this.set('currentStep', step);
    }

    /**
     * Check if passport is diplomatic/priority
     * @returns {boolean}
     */
    isPriorityWorkflow() {
        return this.state.workflowType === 'PRIORITY';
    }

    /**
     * Set document as uploaded
     * @param {string} docType
     * @param {Object} data
     */
    setDocumentUploaded(docType, data) {
        const docs = { ...this.state.uploadedDocuments };
        docs[docType] = data;
        this.set('uploadedDocuments', docs);
    }

    /**
     * Check if document is uploaded
     * @param {string} docType
     * @returns {boolean}
     */
    isDocumentUploaded(docType) {
        return !!this.state.uploadedDocuments[docType];
    }

    /**
     * Get collected data
     * @returns {Object}
     */
    getCollectedData() {
        return { ...this.state.collectedData };
    }

    /**
     * Add message to history
     * @param {Object} message
     */
    addMessage(message) {
        const messages = [...this.state.messages, message];
        this.set('messages', messages);
    }

    /**
     * Export state for persistence
     * @returns {Object}
     */
    export() {
        return JSON.parse(JSON.stringify(this.state));
    }

    /**
     * Import state from persistence
     * @param {Object} savedState
     */
    import(savedState) {
        if (savedState && typeof savedState === 'object') {
            this.state = { ...createInitialState(), ...savedState };
            this.emit('import', this.state);
        }
    }
}

// Export singleton instance
export const stateManager = new StateManager();
export default stateManager;
