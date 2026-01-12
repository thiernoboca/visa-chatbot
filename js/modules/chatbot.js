/**
 * Chatbot Main Class
 * Integrates all modules into a cohesive chatbot experience
 *
 * @version 6.0.1 - Fixed i18n.updateDOM fallback
 * @module Chatbot
 *
 * Now integrates:
 * - flowIntegration: Orchestrates all modules
 * - documentFlow: Step progression with conditions
 * - requirementsMatrix: Dynamic requirements based on passport type
 * - validationUI: Real-time validation feedback
 * - crossDocumentValidation: Cross-document consistency checks
 *
 * v6.0 Gamification:
 * - ProgressTracker: Visual progress bar with step indicators
 * - CelebrationManager: Confetti, toasts, badge animations
 * - TimeEstimator: Dynamic time remaining calculation
 * - Smart Prefill: Cascade data from passport to all documents
 * - Proactive Suggestions: AI-powered contextual tips
 */

import { CONFIG, PERSONA, STEPS, JURISDICTION_COUNTRIES, PASSPORT_REQUIREMENTS } from './config.js';
import { I18n, i18n } from './i18n.js';
import { stateManager } from './state.js';
import { messagesManager } from './messages.js';
import { uiManager } from './ui.js';
import { uploadManager } from './upload.js';
import { analyticsManager } from './analytics.js';
import { apiManager } from './api.js';
import { uxManager, ConfettiCelebration, DocumentChecklist, EnhancedUpload, ToastNotification } from './ux-enhancements.js';

// Phase 1-6 Integration Modules
import { flowIntegration, IntegrationEvents, ApplicationState } from './flow-integration.js';
import { documentFlow, StepType, StepStatus, FLOW_STEPS } from './document-flow.js';
import { requirementsMatrix, PassportType, DocumentTypes, WorkflowCategory } from './requirements-matrix.js';
import { validationUI } from './validation-ui.js';
import { ocrFallback, needsFallback } from './ocr-fallback.js';
import { crossDocumentValidator } from './cross-document-validation.js';

// Phase 6.0 - Gamification Modules
import { ProgressTracker } from './progress-tracker.js';
import { CelebrationManager } from './celebrations.js';
import { TimeEstimator } from './time-estimator.js';

// Phase 7.0 - Redesign Integration
import { InlineEditingManager } from './inline-editing.js';

/**
 * Main VisaChatbot class
 */
export class VisaChatbot {
    /**
     * Constructor
     * @param {Object} options
     */
    constructor(options = {}) {
        // Merge config
        this.config = {
            ...CONFIG.defaults,
            apiEndpoint: CONFIG.endpoints.api,
            ocrEndpoint: CONFIG.endpoints.ocr,
            initialSessionId: null,
            ...options
        };

        // Initialize core modules
        this.state = stateManager;
        this.messages = messagesManager;
        this.ui = uiManager;
        this.upload = uploadManager;
        this.analytics = analyticsManager;
        this.api = apiManager;
        this.i18n = i18n;
        this.ux = uxManager;

        // Phase 1-6 modules (frontend flow management)
        this.flow = flowIntegration;
        this.documentFlow = documentFlow;
        this.requirements = requirementsMatrix;
        this.validation = validationUI;
        this.ocrFallback = ocrFallback;
        this.crossValidation = crossDocumentValidator;

        // Elements cache
        this.elements = {};

        // Internal state
        this._coherenceUI = null;
        this._multiUploader = null;
        this._verificationModal = null;
        this._flowInitialized = false;

        // Phase 6.0 - Gamification modules
        this.progressTracker = null;
        this.celebrations = null;
        this.timeEstimator = null;

        // Phase 7.0 - Redesign integration
        this.inlineEditor = null;
        this.currentExtractedData = null; // Stores data during inline confirmation flow

        // Initialize
        this.init();
    }

    /**
     * Initialize chatbot
     */
    async init() {
        this.log('Initializing VisaChatbot v5.0...');

        // Check for reset parameter - clear all local storage for fresh start
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('reset') === '1') {
            this.log('Reset requested - clearing local storage');
            localStorage.removeItem('visa_session_id');
            localStorage.removeItem('visa_chat_state');
            localStorage.removeItem('visa_onboarding_seen');
            // Remove reset param from URL without reload
            window.history.replaceState({}, '', window.location.pathname);
        }

        // Bind DOM elements
        this.bindElements();

        // Initialize core modules
        this.messages.init(this.elements.chatMessages);
        this.ui.init({
            progressFill: 'progress-bar',
            mobileProgressFill: 'mobile-progress-bar',
            progressPercent: 'progress-percent'
        });
        this.upload.init({
            apiEndpoint: this.config.apiEndpoint,
            ocrEndpoint: this.config.ocrEndpoint
        });
        this.api.init({ endpoints: CONFIG.endpoints });

        // Initialize UX enhancements
        this.ux.init(this.elements.chatMessages);

        // Connect typing indicator to messages module
        if (this.ux.typing) {
            this.messages.setTypingIndicator(this.ux.typing);
        }

        // Set language
        this.i18n.setLanguage(this.config.language);
        this.state.set('language', this.config.language);

        // Load theme
        this.ui.loadSavedTheme();

        // Bind events
        this.bindEvents();

        // Set up module callbacks
        this.setupModuleCallbacks();

        // Initialize Flow Integration (Phase 1-6 modules)
        await this.initFlowIntegration();

        // Initialize session (use preloaded data if available)
        if (window.WORKFLOW_PRELOADED && window.APP_CONFIG?.workflow?.preloaded) {
            this.log('Using preloaded workflow data');
            await this.handlePreloadedSession(window.APP_CONFIG.workflow);
        } else {
            this.log('Fetching session via AJAX');
            await this.initSession();
        }

        // Initialize geolocation from IP detection (server-side)
        this.initGeolocation();

        // Initialize CoherenceUI if available
        this.initCoherenceUI();

        // Initialize Gamification modules (v6.0)
        this.initGamification();

        this.log('VisaChatbot v6.0 initialized with gamification');
    }

    /**
     * Initialize Flow Integration modules
     * Connects frontend modules for client-side flow management
     */
    async initFlowIntegration() {
        try {
            // Initialize flow integration with config
            await this.flow.initialize({
                language: this.config.language,
                debug: this.config.debug,
                validationContainer: this.elements.chatMessages
            });

            // Connect flow events to chatbot handlers
            this.bindFlowEvents();

            // Sync documentFlow with requirements matrix
            this.documentFlow.updateContext({
                language: this.config.language
            });

            this._flowInitialized = true;
            this.log('Flow integration initialized');

        } catch (error) {
            this.log('Flow integration error (non-blocking):', error.message);
            // Non-blocking error - continue with backend-only flow
            this._flowInitialized = false;
        }
    }

    /**
     * Bind flow integration events
     */
    bindFlowEvents() {
        // Step changed event
        this.flow.on(IntegrationEvents.STEP_CHANGED, ({ step }) => {
            this.log(`Flow step changed: ${step}`);
            this.syncFlowWithUI(step);
        });

        // Step completed event
        this.flow.on(IntegrationEvents.STEP_COMPLETED, ({ step, data }) => {
            this.log(`Flow step completed: ${step}`);
            this.onFlowStepCompleted(step, data);
        });

        // Document uploaded event
        this.flow.on(IntegrationEvents.DOCUMENT_UPLOADED, ({ docType, data }) => {
            this.log(`Document uploaded via flow: ${docType}`);
        });

        // Document validated event
        this.flow.on(IntegrationEvents.DOCUMENT_VALIDATED, ({ docType, validation }) => {
            this.log(`Document validated: ${docType}`, validation);
            this.showValidationFeedback(docType, validation);
        });

        // Validation error event
        this.flow.on(IntegrationEvents.VALIDATION_ERROR, ({ step, errors }) => {
            this.log(`Validation errors at ${step}:`, errors);
            this.showValidationErrors(errors);
        });

        // OCR fallback triggered event
        this.flow.on(IntegrationEvents.OCR_FALLBACK_TRIGGERED, ({ docType, ocrResult }) => {
            this.log(`OCR fallback triggered for ${docType}`);
            this.handleOcrFallbackNeeded(docType, ocrResult);
        });

        // Payment required event
        this.flow.on(IntegrationEvents.PAYMENT_REQUIRED, (data) => {
            this.log('Payment required:', data);
        });

        // Flow completed event
        this.flow.on(IntegrationEvents.FLOW_COMPLETED, ({ reference, pdf }) => {
            this.log(`Flow completed! Reference: ${reference}`);
            this.onFlowCompleted(reference, pdf);
        });

        // Flow error event
        this.flow.on(IntegrationEvents.FLOW_ERROR, ({ error }) => {
            this.log('Flow error:', error);
            this.ui.showNotification('Erreur', error, 'error');
        });

        // DocumentFlow events
        this.documentFlow.on('stepChanged', ({ previous, current }) => {
            this.log(`DocumentFlow: ${previous} -> ${current}`);
        });

        this.documentFlow.on('flowUpdated', (flowState) => {
            this.updateProgressFromFlow(flowState);
        });
    }

    /**
     * Initialize Coherence UI module
     */
    initCoherenceUI() {
        try {
            // Check if CoherenceUI is available globally
            if (window.CoherenceUI) {
                this._coherenceUI = new window.CoherenceUI();
                this.log('CoherenceUI initialized');
            }
        } catch (error) {
            this.log('CoherenceUI initialization failed (non-blocking):', error.message);
        }
    }

    /**
     * Initialize Gamification modules (v6.0)
     * - ProgressTracker: Visual progress visualization
     * - CelebrationManager: Confetti and milestone celebrations
     * - TimeEstimator: Dynamic completion time estimation
     */
    initGamification() {
        try {
            // Initialize ProgressTracker
            const progressContainer = document.getElementById('step-timeline');
            if (progressContainer && ProgressTracker) {
                this.progressTracker = new ProgressTracker({
                    container: progressContainer,
                    language: this.config.language || 'fr'
                });
                this.log('ProgressTracker initialized');
            }

            // Initialize CelebrationManager
            if (CelebrationManager) {
                this.celebrations = new CelebrationManager({
                    language: this.config.language || 'fr'
                });
                this.log('CelebrationManager initialized');
            }

            // Initialize TimeEstimator
            if (TimeEstimator) {
                this.timeEstimator = new TimeEstimator({
                    language: this.config.language || 'fr'
                });
                this.log('TimeEstimator initialized');
            }

            // Phase 7.0 - Initialize InlineEditingManager EARLY
            if (InlineEditingManager && CONFIG.features.inlineEditing.enabled) {
                this.inlineEditor = new InlineEditingManager({
                    messagesManager: this.messages,
                    uiManager: this.ui,
                    apiManager: this.api,
                    onConfirm: this.handleInlineDataConfirmed.bind(this),
                    onEdit: this.handleInlineDataEdit.bind(this)
                });
                this.log('InlineEditingManager initialized (early)');
            }

        } catch (error) {
            this.log('Gamification initialization error (non-blocking):', error.message);
        }
    }

    /**
     * Sync flow state with UI
     * @param {string} step - Current step
     */
    syncFlowWithUI(step) {
        // Update UI progress based on flow state
        const flowState = this.documentFlow.getFlowState();
        this.updateProgressFromFlow(flowState);
    }

    /**
     * Update progress UI from flow state
     * @param {Object} flowState - Flow state object
     */
    updateProgressFromFlow(flowState) {
        if (!flowState) return;

        const stepInfo = {
            current: flowState.currentStep?.id,
            total: flowState.totalSteps,
            completed: flowState.completedSteps,
            percentage: flowState.progress
        };

        this.ui.updateProgress(stepInfo);
    }

    /**
     * Update progress bar directly by percentage
     * Uses ProgressTracker.setProgressPercent() when in percentage mode
     * @param {number} percent - Progress percentage (0-100)
     */
    updateProgressPercent(percent) {
        const mode = CONFIG.features?.progressTracking?.mode || 'steps';

        if (mode === 'percentage' && this.progressTracker) {
            this.progressTracker.setProgressPercent(percent);
        } else {
            // Fallback to step-based update
            this.ui.updateProgress({ percentage: percent });
        }

        this.log(`Progress updated to ${percent}%`);
    }

    /**
     * Handle flow step completed
     * @param {string} step - Completed step
     * @param {Object} data - Step data
     */
    onFlowStepCompleted(step, data) {
        // Update context based on step data
        if (step === StepType.PASSPORT && data) {
            this.updateRequirementsContext(data);
        }
    }

    /**
     * Update requirements context from passport data
     * @param {Object} passportData - Passport data
     */
    updateRequirementsContext(passportData) {
        const passportType = passportData.passportType || PassportType.ORDINAIRE;
        const nationality = passportData.nationality || passportData.country_code;

        // Update requirements matrix
        this.requirements.setPassportType(passportType);

        // Determine workflow category
        const priorityTypes = [
            PassportType.DIPLOMATIQUE,
            PassportType.SERVICE,
            PassportType.LP_ONU,
            PassportType.LP_UA
        ];
        const workflowCategory = priorityTypes.includes(passportType)
            ? WorkflowCategory.PRIORITY
            : WorkflowCategory.STANDARD;

        this.requirements.setWorkflowCategory(workflowCategory);

        // Update document flow context
        this.documentFlow.updateContext({
            passportType,
            nationality,
            workflowCategory,
            isMinor: passportData.isMinor || false
        });

        this.log(`Requirements updated: ${passportType}, workflow: ${workflowCategory}`);

        // Show required documents to user
        this.showRequiredDocuments();
    }

    /**
     * Show required documents based on current context
     */
    showRequiredDocuments() {
        const requiredDocs = this.requirements.getRequiredDocuments();
        const lang = this.state.get('language');

        if (requiredDocs.length > 0) {
            const docNames = requiredDocs.map(code => {
                const meta = this.requirements.getDocumentMeta(code);
                return lang === 'fr' ? meta?.label?.fr : meta?.label?.en;
            }).filter(Boolean);

            this.log('Required documents:', docNames);
        }
    }

    /**
     * Show validation feedback in UI
     * @param {string} docType - Document type
     * @param {Object} validation - Validation result
     */
    showValidationFeedback(docType, validation) {
        const lang = this.state.get('language');

        if (validation.valid) {
            this.ui.showNotification(
                lang === 'fr' ? 'Validé' : 'Validated',
                lang === 'fr' ? 'Document vérifié avec succès' : 'Document verified successfully',
                'success'
            );
        } else {
            const errorCount = validation.checks?.filter(c => !c.valid).length || 0;
            this.ui.showNotification(
                lang === 'fr' ? 'Attention' : 'Warning',
                `${errorCount} ${lang === 'fr' ? 'problème(s) détecté(s)' : 'issue(s) detected'}`,
                'warning'
            );
        }
    }

    /**
     * Show validation errors
     * @param {Array} errors - Error messages
     */
    showValidationErrors(errors) {
        const lang = this.state.get('language');

        errors.forEach(error => {
            const msg = typeof error === 'string' ? error : error.message;
            this.ui.showNotification(
                lang === 'fr' ? 'Erreur' : 'Error',
                msg,
                'error'
            );
        });
    }

    /**
     * Handle OCR fallback needed
     * @param {string} docType - Document type
     * @param {Object} ocrResult - OCR result with low confidence
     */
    async handleOcrFallbackNeeded(docType, ocrResult) {
        const lang = this.state.get('language');

        // Show notification about manual entry needed
        this.ui.showNotification(
            lang === 'fr' ? 'Saisie manuelle requise' : 'Manual entry required',
            lang === 'fr'
                ? 'Certaines informations n\'ont pas pu être extraites automatiquement'
                : 'Some information could not be automatically extracted',
            'info'
        );

        // The ocrFallback modal will be triggered by the flow
    }

    /**
     * Handle flow completed
     * @param {string} reference - Application reference
     * @param {Object} pdf - Generated PDF
     */
    onFlowCompleted(reference, pdf) {
        const lang = this.state.get('language');

        // Celebrate!
        this.ui.celebrateSuccess(
            lang === 'fr'
                ? `Félicitations ! Votre demande ${reference} a été soumise !`
                : `Congratulations! Your application ${reference} has been submitted!`
        );

        this.ui.showNotification(
            lang === 'fr' ? 'Succès' : 'Success',
            lang === 'fr' ? 'Demande soumise avec succès' : 'Application submitted successfully',
            'success'
        );
    }

    /**
     * Get current flow requirements
     * @returns {Object} Current requirements based on context
     */
    getFlowRequirements() {
        if (!this._flowInitialized) return null;

        const passportType = this.documentFlow.getContext().passportType || PassportType.ORDINAIRE;
        return this.requirements.getRequirements(passportType);
    }

    /**
     * Get pending documents from flow
     * @returns {Array} List of pending documents
     */
    getPendingDocuments() {
        if (!this._flowInitialized) return [];
        return this.documentFlow.getPendingDocuments();
    }

    /**
     * Validate document data client-side
     * @param {string} docType - Document type
     * @param {Object} data - Document data
     * @returns {Object} Validation result
     */
    async validateDocumentClientSide(docType, data) {
        if (!this._flowInitialized) {
            return { valid: true, checks: [] };
        }

        // Get passport data for cross-validation
        const passportData = this.documentFlow.getStepData(StepType.PASSPORT);

        // Run cross-document validation if passport exists
        if (passportData && Object.keys(passportData).length > 0) {
            const crossValidationResult = this.crossValidation.validate({
                passport: passportData,
                [docType]: data
            });

            return crossValidationResult;
        }

        return { valid: true, checks: [] };
    }

    /**
     * Bind DOM elements
     */
    bindElements() {
        this.elements = {
            chatMessages: document.getElementById('chatMessages'),
            chatWelcome: document.getElementById('chatWelcome'),
            quickActions: document.getElementById('quickActions'),
            chatInput: document.getElementById('chatInput'),
            btnSend: document.getElementById('btnSend'),
            btnAttachment: document.getElementById('btnAttachment'),
            stepNav: document.getElementById('stepNav'),
            stepLabel: document.getElementById('stepLabel'),
            stepCount: document.getElementById('stepCount'),
            progressFill: document.getElementById('progressFill'),
            notificationContainer: document.getElementById('notificationContainer'),
            generalFileInput: document.getElementById('generalFileInput'),
            scrollToBottomBtn: document.getElementById('scrollToBottomBtn'),
            // Passport scanner
            passportScannerOverlay: document.getElementById('passportScannerOverlay'),
            btnCloseScanner: document.getElementById('btnCloseScanner'),
            btnCancelScanner: document.getElementById('btnCancelScanner'),
            btnConfirmPassport: document.getElementById('btnConfirmPassport'),
            btnRemovePassport: document.getElementById('btnRemovePassport'),
            passportUploadZone: document.getElementById('passportUploadZone'),
            passportFileInput: document.getElementById('passportFileInput'),
            passportPreviewArea: document.getElementById('passportPreviewArea'),
            passportPreviewImage: document.getElementById('passportPreviewImage'),
            passportProcessing: document.getElementById('passportProcessing')
        };
    }

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Send message
        this.elements.btnSend?.addEventListener('click', () => this.sendMessage());
        this.elements.chatInput?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Attachments
        this.elements.btnAttachment?.addEventListener('click', () => {
            if (this.state.get('currentStep') === 'passport') {
                this.openPassportScanner();
            } else {
                this.elements.generalFileInput?.click();
            }
        });

        this.elements.generalFileInput?.addEventListener('change', (e) => {
            this.handleFileUpload(e.target.files[0]);
        });

        // Passport scanner
        this.elements.btnCloseScanner?.addEventListener('click', () => this.closePassportScanner());
        this.elements.btnCancelScanner?.addEventListener('click', () => this.closePassportScanner());
        this.elements.passportFileInput?.addEventListener('change', (e) => {
            this.previewPassport(e.target.files[0]);
        });
        this.elements.btnConfirmPassport?.addEventListener('click', () => this.scanPassport());
        this.elements.btnRemovePassport?.addEventListener('click', () => this.resetScanner());

        // Click on upload zone to trigger file input
        this.elements.passportUploadZone?.addEventListener('click', () => {
            this.elements.passportFileInput?.click();
        });

        // Drag & drop for scanner
        this.elements.passportUploadZone?.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.currentTarget.classList.add('drag-over');
        });
        this.elements.passportUploadZone?.addEventListener('dragleave', (e) => {
            e.currentTarget.classList.remove('drag-over');
        });
        this.elements.passportUploadZone?.addEventListener('drop', (e) => {
            e.preventDefault();
            e.currentTarget.classList.remove('drag-over');
            const file = e.dataTransfer.files[0];
            if (file) this.previewPassport(file);
        });

        // Step navigation
        this.elements.stepNav?.addEventListener('click', (e) => {
            const stepDot = e.target.closest('.step-dot');
            if (stepDot && !stepDot.disabled) {
                const targetStep = stepDot.dataset.step;
                if (targetStep && targetStep !== this.state.get('currentStep')) {
                    this.navigateToStep(targetStep);
                }
            }
        });

        // Keyboard navigation for steps
        this.elements.stepNav?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                const stepDot = e.target.closest('.step-dot');
                if (stepDot && !stepDot.disabled) {
                    e.preventDefault();
                    const targetStep = stepDot.dataset.step;
                    if (targetStep) {
                        this.navigateToStep(targetStep);
                    }
                }
            }
        });
    }

    /**
     * Setup module callbacks
     */
    setupModuleCallbacks() {
        // Quick action handler - receives both value and label for proper display
        this.ui.setQuickActionHandler((value, label) => {
            this.sendMessage(value, label);
        });

        // Document extracted handler
        this.upload.setOnDocumentExtracted((docType, data, fileName) => {
            this.handleDocumentExtracted(docType, data, fileName);
        });
    }

    /**
     * Initialize session via AJAX (fallback when preloaded data not available)
     */
    async initSession() {
        try {
            const data = await this.api.initSession(this.config.initialSessionId);

            if (data.success) {
                this.state.setSessionId(data.data.session_id);
                this.state.setCurrentStep(data.data.step_info?.current || 'welcome');

                // Initialize analytics
                this.analytics.init(data.data.session_id, { debug: this.config.debug });
                this.analytics.trackSessionStart();

                // Hide welcome screen
                if (this.elements.chatWelcome) {
                    this.elements.chatWelcome.style.display = 'none';
                }

                // Display initial message
                await this.messages.addBotMessage(data.data.message.content);

                // Show quick actions
                this.ui.showQuickActions(data.data.quick_actions || []);

                // Update progress
                this.ui.updateProgress(data.data.step_info);

                // Track step start
                this.analytics.trackStepStart(this.state.get('currentStep'));

                this.log('Session initialized:', data.data.session_id);
            } else {
                this.ui.showNotification('Erreur', data.error || 'Erreur d\'initialisation', 'error');
            }
        } catch (error) {
            this.log('Init error:', error);
            this.ui.showNotification('Erreur', 'Impossible de se connecter au serveur', 'error');
        }
    }

    /**
     * Handle preloaded session data from server-side rendering
     * Uses data from window.APP_CONFIG.workflow
     *
     * @param {Object} workflowData - Preloaded workflow data from PHP
     */
    async handlePreloadedSession(workflowData) {
        try {
            // Store session ID
            this.state.setSessionId(workflowData.session_id);
            this.state.setCurrentStep(workflowData.current_step || 'welcome');

            // Store workflow data in state
            this.state.set('workflowCategory', workflowData.workflow_category);
            this.state.set('processingInfo', workflowData.processing_info);
            this.state.set('language', workflowData.language);

            // Initialize analytics
            this.analytics.init(workflowData.session_id, { debug: this.config.debug });
            this.analytics.trackSessionStart();
            this.analytics.trackEvent('session_preloaded', {
                current_step: workflowData.current_step,
                has_suggestions: (workflowData.suggestions?.length || 0) > 0
            });

            // Hide welcome screen
            if (this.elements.chatWelcome) {
                this.elements.chatWelcome.style.display = 'none';
            }

            // Display initial message
            if (workflowData.initial_message) {
                const msg = workflowData.initial_message;
                await this.messages.addBotMessage(
                    msg.message,
                    msg.quick_actions,
                    msg.metadata
                );

                // Show quick actions if present
                if (msg.quick_actions?.length > 0) {
                    this.ui.showQuickActions(msg.quick_actions);

                    // Auto-scroll to show language buttons on welcome step
                    setTimeout(() => {
                        this.scrollToQuickActions();
                    }, 300);
                }
            }

            // Update progress
            if (workflowData.step_info) {
                this.ui.updateProgress(workflowData.step_info);
            }

            // Display proactive suggestions
            if (workflowData.suggestions?.length > 0) {
                this.displayProactiveSuggestions(workflowData.suggestions);
            }

            // Track step start
            this.analytics.trackStepStart(workflowData.current_step || 'welcome');

            // Set session cookie for persistence
            document.cookie = `visa_session_id=${workflowData.session_id}; path=/; max-age=86400; SameSite=Lax`;

            this.log('Session preloaded:', workflowData.session_id);
        } catch (error) {
            this.log('Preload error, falling back to AJAX:', error);
            // Fallback to AJAX initialization
            await this.initSession();
        }
    }

    /**
     * Display proactive suggestions from preloaded data
     *
     * @param {Array} suggestions - Array of suggestion objects
     */
    displayProactiveSuggestions(suggestions) {
        if (!suggestions || suggestions.length === 0) return;

        const lang = this.state.get('language') || 'fr';

        // Filter high priority suggestions
        const highPriority = suggestions.filter(s => s.priority <= 2);

        // Display as notifications
        highPriority.forEach(suggestion => {
            const type = suggestion.type === 'warning' ? 'warning' :
                        suggestion.type === 'error' ? 'error' : 'info';

            this.ui.showNotification(
                lang === 'fr' ? 'Conseil' : 'Tip',
                suggestion.message,
                type
            );
        });

        this.log(`Displayed ${highPriority.length} proactive suggestions`);
    }

    /**
     * Initialize geolocation from server-side IP detection
     * Uses data passed via window.APP_CONFIG.geolocation
     */
    initGeolocation() {
        const geoData = window.APP_CONFIG?.geolocation;

        if (!geoData || !geoData.detected) {
            this.log('Geolocation: not detected or unavailable');
            return;
        }

        // Store geolocation data in state
        this.state.set('geolocation', {
            country_code: geoData.country_code,
            country_name: geoData.country_name,
            city: geoData.city,
            in_jurisdiction: geoData.in_jurisdiction
        });

        // Track geolocation event in analytics
        this.analytics.track('geolocation_detected', {
            country_code: geoData.country_code,
            country_name: geoData.country_name,
            in_jurisdiction: geoData.in_jurisdiction
        });

        this.log(`Geolocation: ${geoData.country_name} (${geoData.country_code}), in_jurisdiction: ${geoData.in_jurisdiction}`);

        // If applicant is in jurisdiction, update requirements context
        if (geoData.in_jurisdiction && geoData.country_code) {
            // Pre-set nationality for form prefilling
            this.state.set('detected_nationality', geoData.country_code);

            // Log for debug
            if (this.config.debug) {
                console.log('🌍 Applicant location detected:', {
                    country: geoData.country_name,
                    code: geoData.country_code,
                    city: geoData.city,
                    inJurisdiction: geoData.in_jurisdiction
                });
            }
        }
    }

    /**
     * Initialize CoherenceUI
     */
    initCoherenceUI() {
        if (window.CoherenceUI) {
            this._coherenceUI = new CoherenceUI({
                container: this.elements.chatMessages,
                apiEndpoint: CONFIG.endpoints.coherence,
                debug: this.config.debug,
                onAction: (actionType, docType) => {
                    this.log('Coherence action:', actionType, docType);
                    if (actionType === 'upload' && docType) {
                        this.triggerDocumentUpload(docType);
                    }
                }
            });
            window.coherenceUI = this._coherenceUI;
            this.log('CoherenceUI initialized');
        }
    }

    /**
     * Initialize Gamification modules (v6.0)
     */
    initGamification() {
        try {
            // Progress Tracker
            this.progressTracker = new ProgressTracker({
                language: this.config.language
            });
            this.log('ProgressTracker initialized');

            // Celebration Manager
            this.celebrations = new CelebrationManager({
                language: this.config.language,
                soundEnabled: localStorage.getItem('soundEnabled') !== 'false'
            });
            this.log('CelebrationManager initialized');

            // Time Estimator
            this.timeEstimator = new TimeEstimator({
                language: this.config.language
            });
            this.timeEstimator.startAutoUpdate();
            this.log('TimeEstimator initialized');

            // Phase 7.0 - Inline Editing Manager
            if (InlineEditingManager && CONFIG.features.inlineEditing.enabled) {
                this.inlineEditor = new InlineEditingManager({
                    messagesManager: this.messages,
                    uiManager: this.ui,
                    apiManager: this.api,
                    onConfirm: this.handleInlineDataConfirmed.bind(this),
                    onEdit: this.handleInlineDataEdit.bind(this)
                });
                this.log('InlineEditingManager initialized');
            }

            // Bind gamification events
            this.bindGamificationEvents();

        } catch (error) {
            this.log('Gamification init error (non-blocking):', error.message);
        }
    }

    /**
     * Bind gamification-related events
     */
    bindGamificationEvents() {
        // Listen for document validation to trigger celebrations
        document.addEventListener('document-validated', (e) => {
            const { docType } = e.detail;
            if (this.celebrations) {
                this.celebrations.documentValidated(docType);
            }
            if (this.progressTracker) {
                this.progressTracker.completeStep(docType);
            }
        });

        // Listen for cross-validation success
        document.addEventListener('cross-validation-success', (e) => {
            if (this.celebrations) {
                this.celebrations.crossValidationSuccess();
            }
        });

        // Listen for prefill applied
        document.addEventListener('prefill-applied', (e) => {
            const { fieldsCount } = e.detail;
            if (this.celebrations && fieldsCount > 0) {
                this.celebrations.prefillSuccess(fieldsCount);
            }
        });

        // Listen for milestone triggers
        document.addEventListener('trigger-celebration', (e) => {
            const { type, milestone, docType } = e.detail;
            if (this.celebrations) {
                switch (type) {
                    case 'milestone':
                        this.celebrations.milestoneReached(milestone);
                        break;
                    case 'document':
                        this.celebrations.documentValidated(docType);
                        break;
                    case 'cross-validation':
                        this.celebrations.crossValidationSuccess();
                        break;
                }
            }
        });
    }

    /**
     * Handle celebration triggers from API response
     * @param {Object} metadata Response metadata
     */
    handleCelebrationTrigger(metadata) {
        if (!metadata || !this.celebrations) return;

        // Document validation celebration
        if (metadata.trigger_celebration === 'document' && metadata.document_type) {
            document.dispatchEvent(new CustomEvent('document-validated', {
                detail: { docType: metadata.document_type }
            }));
        }

        // Cross-validation celebration
        if (metadata.cross_validation?.overall_score >= 85) {
            document.dispatchEvent(new CustomEvent('cross-validation-success', {
                detail: metadata.cross_validation
            }));
        }

        // Prefill celebration
        if (metadata.prefill_applied && metadata.prefill_fields_count > 0) {
            document.dispatchEvent(new CustomEvent('prefill-applied', {
                detail: { fieldsCount: metadata.prefill_fields_count }
            }));
        }
    }

    /**
     * Update gamification modules on step change
     * @param {string} step New step
     */
    updateGamificationStep(step) {
        if (this.progressTracker) {
            this.progressTracker.setCurrentStep(step);
        }

        // Dispatch step change event for time estimator
        document.dispatchEvent(new CustomEvent('workflow-step-change', {
            detail: {
                step,
                previousStep: this.state.get('currentStep')
            }
        }));
    }

    /**
     * Send message
     * @param {string} text - The value to send to the API
     * @param {string} displayText - Optional text to show in the user message bubble (e.g., label with emoji)
     */
    async sendMessage(text = null, displayText = null) {
        const message = text || this.elements.chatInput?.value.trim();
        if (!message) return;

        // Display user message - use displayText if provided (e.g., "🇫🇷 Français"), otherwise use message
        this.messages.addUserMessage(displayText || message);

        // Clear input
        if (this.elements.chatInput) {
            this.elements.chatInput.value = '';
        }

        // Hide quick actions
        this.ui.hideQuickActions();

        // Mark as dirty for autosave
        window.autosave?.markDirty();

        // Show typing
        this.messages.showTyping();

        try {
            const data = await this.api.sendMessage(message);

            this.messages.hideTyping();

            if (data.success) {
                await this.handleSuccessResponse(data.data);
            } else {
                await this.messages.addBotMessage(data.error || 'Une erreur est survenue');
                this.analytics.trackError(this.state.get('currentStep'), 'api_error', data.error);
            }
        } catch (error) {
            this.messages.hideTyping();
            this.log('Send error:', error);
            const errorMessage = this.getLocalizedErrorMessage(error);
            await this.messages.addBotMessage(errorMessage);
            this.analytics.trackError(this.state.get('currentStep'), 'connection_error', error.message);
        }
    }

    /**
     * Handle successful API response
     * @param {Object} data
     */
    async handleSuccessResponse(data) {
        // Update session if changed
        if (data.session_id && data.session_id !== this.state.getSessionId()) {
            this.state.setSessionId(data.session_id);
        }

        // Detect step change
        const previousStep = this.state.get('currentStep');
        const newStep = data.step_info?.current;

        // Update state
        this.state.update({
            currentStep: newStep,
            workflowCategory: data.workflow_category
        });

        // Update language if changed (from welcome step selection)
        if (data.language && data.language !== this.state.get('language')) {
            this.state.set('language', data.language);
            this.i18n.setLanguage(data.language);

            // Update DOM with new language (fallback if method doesn't exist)
            if (typeof this.i18n.updateDOM === 'function') {
                this.i18n.updateDOM();
            } else {
                // Inline fallback for DOM update
                const lang = data.language;
                const langAttr = lang === 'fr' ? 'data-i18n-fr' : 'data-i18n-en';
                document.querySelectorAll('[data-i18n]').forEach(el => {
                    const translation = el.getAttribute(langAttr);
                    if (translation) el.textContent = translation;
                });
                document.documentElement.lang = lang;
                window.dispatchEvent(new CustomEvent('languageChanged', { detail: { language: lang } }));
            }
            this.log(`Language changed to: ${data.language}`);

            // Update ProgressTracker language for step labels translation
            if (this.progressTracker) {
                this.progressTracker.setLanguage(data.language);
            }
        }

        // Track step changes
        if (previousStep !== newStep && previousStep) {
            this.analytics.trackStepComplete(previousStep);
            this.analytics.trackStepStart(newStep);

            // Update gamification modules on step change
            this.updateGamificationStep(newStep);

            if (previousStep === 'confirm' && data.metadata?.completed) {
                this.analytics.trackApplicationComplete();
            }
        }

        // Sync with frontend flow (Phase 1-6 integration)
        await this.syncBackendWithFlow(data, previousStep, newStep);

        // Display bot message
        await this.messages.addBotMessage(data.bot_message.content);

        // Show quick actions
        this.ui.showQuickActions(data.quick_actions || []);

        // Update progress
        this.ui.updateProgress(data.step_info);

        // Handle metadata
        this.handleMetadata(data.metadata);
    }

    /**
     * Sync backend response with frontend flow modules
     * Keeps documentFlow, requirementsMatrix in sync with backend state
     * @param {Object} data - API response data
     * @param {string} previousStep - Previous step ID
     * @param {string} newStep - New step ID
     */
    async syncBackendWithFlow(data, previousStep, newStep) {
        if (!this._flowInitialized) return;

        try {
            // Store extracted data in documentFlow
            if (data.extracted_data) {
                const docType = data.metadata?.document_type || previousStep;
                this.documentFlow.setStepData(docType, data.extracted_data);
            }

            // Update context from passport data
            if (data.passport_data || data.extracted_data?.passport) {
                const passportData = data.passport_data || data.extracted_data?.passport;
                this.updateRequirementsContext(passportData);
            }

            // Update residence context
            if (data.residence_country || data.metadata?.residence_country) {
                const residenceCountry = data.residence_country || data.metadata?.residence_country;
                this.documentFlow.updateContext({
                    residenceCountry,
                    isInJurisdiction: data.metadata?.is_in_jurisdiction
                });
            }

            // Sync step status in documentFlow
            if (previousStep && previousStep !== newStep) {
                // Mark previous step as completed if moving forward
                const prevStepConfig = this.documentFlow.getStep(previousStep);
                if (prevStepConfig) {
                    this.documentFlow.setStepStatus(previousStep, StepStatus.COMPLETED);
                }

                // Set new step as active
                const newStepConfig = this.documentFlow.getStep(newStep);
                if (newStepConfig) {
                    this.documentFlow.setStepStatus(newStep, StepStatus.ACTIVE);
                }
            }

            // Update workflow category from backend
            if (data.workflow_category) {
                const category = data.workflow_category === 'priority'
                    ? WorkflowCategory.PRIORITY
                    : WorkflowCategory.STANDARD;
                this.requirements.setWorkflowCategory(category);
                this.documentFlow.updateContext({ workflowCategory: category });
            }

            // Sync collected data to flow
            if (data.collected_data) {
                for (const [key, value] of Object.entries(data.collected_data)) {
                    this.documentFlow.setStepData(key, value);
                }
            }

            this.log('Flow synced with backend:', { previousStep, newStep });

        } catch (error) {
            this.log('Flow sync error (non-blocking):', error.message);
        }
    }

    /**
     * Handle response metadata
     * @param {Object} metadata
     */
    handleMetadata(metadata) {
        if (!metadata) return;

        // Open passport scanner if needed
        if (metadata.input_type === 'file' && metadata.file_type === 'passport') {
            this.state.set('pendingInputType', 'passport');
            this.addScanButton();
        }

        // Handle document upload
        if (metadata.input_type === 'file' && metadata.document_type) {
            const docType = metadata.document_type;
            const acceptTypes = metadata.accept || '.pdf,.jpg,.jpeg,.png';
            this.state.set('pendingInputType', docType);
            this.state.set('pendingDocumentAccept', acceptTypes);
            this.upload.showDocumentUploader(docType, acceptTypes);
        }

        // Multi-document uploader
        if (metadata.input_type === 'multi_document' || metadata.show_uploader) {
            this.addDocumentsUploadButton();
        }

        // Blocking state
        if (metadata.blocking) {
            this.elements.chatInput?.setAttribute('disabled', 'true');
            this.elements.btnSend?.setAttribute('disabled', 'true');
        } else {
            this.elements.chatInput?.removeAttribute('disabled');
            this.elements.btnSend?.removeAttribute('disabled');
        }

        // Proactive suggestions
        if (metadata.proactive_tip) {
            setTimeout(() => {
                this.ui.showProactiveSuggestion(metadata.proactive_tip, 'tip');
            }, 1000);
        }

        if (metadata.proactive_warning) {
            setTimeout(() => {
                this.ui.showProactiveSuggestion(metadata.proactive_warning, 'warning');
            }, 500);
        }

        // Milestones
        if (metadata.milestone) {
            this.ui.celebrateSuccess(metadata.milestone_message);
        }

        // User info
        if (metadata.user_firstname) {
            this.state.set('userFirstName', metadata.user_firstname);
        }

        // Workflow type
        if (metadata.is_diplomatic || metadata.is_priority) {
            const msg = metadata.is_diplomatic
                ? '🎖️ Passeport diplomatique détecté - Traitement prioritaire et gratuit !'
                : '⚡ Traitement prioritaire activé';
            this.ui.showProactiveSuggestion(msg, 'celebration');
        }

        // Completion
        if (metadata.completed) {
            this.ui.celebrateSuccess('Votre demande a été soumise avec succès !');
            this.ui.showNotification('Succès', 'Votre demande a été soumise !', 'success');
        }

        // Progress milestones
        if (metadata.progress_milestone) {
            const lang = this.state.get('language');
            const messages = {
                0.25: lang === 'fr' ? "Un quart du chemin parcouru ! 🚀" : "Quarter way there! 🚀",
                0.5: lang === 'fr' ? "À mi-chemin ! Vous êtes au top ! 🌟" : "Halfway there! You're doing great! 🌟",
                0.75: lang === 'fr' ? "Plus que quelques étapes ! 💪" : "Almost there! 💪",
                0.9: lang === 'fr' ? "Dernière ligne droite ! 🏁" : "Final stretch! 🏁"
            };
            const msg = messages[metadata.progress_milestone];
            if (msg) {
                this.ui.showProactiveSuggestion(msg, 'celebration');
            }
        }

        // =========================================================================
        // PERSONA ENHANCEMENTS: IP Geolocation, Vaccination Warning, Date Prefill
        // =========================================================================

        // IP Geolocation detected
        if (metadata.geolocation_detected) {
            const lang = this.state.get('language');
            const msg = lang === 'fr'
                ? '🌍 Localisation détectée automatiquement'
                : '🌍 Location automatically detected';
            this.ui.showProactiveSuggestion(msg, 'tip');

            // Store detected country for analytics
            if (metadata.detected_country) {
                this.state.set('detectedCountry', metadata.detected_country);
                this.analytics.trackEvent('geolocation_detected', {
                    country: metadata.detected_country,
                    in_jurisdiction: !metadata.out_of_jurisdiction
                });
            }
        }

        // Vaccination warning from passport nationality
        if (metadata.vaccination_warning) {
            const warning = metadata.vaccination_warning;
            const lang = this.state.get('language');

            // Store warning for later reference
            this.state.set('vaccinationWarning', warning);

            // Show visual notification after a short delay
            setTimeout(() => {
                const title = lang === 'fr' ? 'Vaccination requise' : 'Vaccination Required';
                this.ui.showNotification(
                    title,
                    warning.vaccinations_list ? warning.vaccinations_list.join(', ') : 'Fièvre jaune',
                    'warning'
                );
            }, 1500);

            // Track for analytics
            this.analytics.trackEvent('vaccination_warning_shown', {
                nationality: warning.nationality_code,
                vaccinations: warning.vaccinations_required
            });
        }

        // Date prefill from ticket OCR
        if (metadata.prefill_data) {
            const prefill = metadata.prefill_data;
            this.state.set('prefillData', prefill);

            // Show tip about auto-detection
            const lang = this.state.get('language');
            const msg = lang === 'fr'
                ? '✈️ Dates détectées depuis votre billet'
                : '✈️ Dates detected from your ticket';
            this.ui.showProactiveSuggestion(msg, 'tip');

            this.analytics.trackEvent('dates_prefilled', {
                has_departure: !!prefill.departure_date,
                has_return: !!prefill.return_date
            });
        }

        // =========================================================================
        // GAMIFICATION v6.0: Celebration triggers and progress updates
        // =========================================================================

        // Handle celebration triggers from backend
        this.handleCelebrationTrigger(metadata);

        // Handle prefill celebrations
        if (metadata.prefill_data && metadata.prefill_fields_count > 0) {
            document.dispatchEvent(new CustomEvent('prefill-applied', {
                detail: {
                    docType: metadata.document_type,
                    fieldsCount: metadata.prefill_fields_count
                }
            }));
        }

        // Handle proactive suggestions from backend
        if (metadata.proactive_suggestions && Array.isArray(metadata.proactive_suggestions)) {
            metadata.proactive_suggestions.forEach((suggestion, index) => {
                setTimeout(() => {
                    const suggestionType = suggestion.severity === 'high' ? 'warning' : 'tip';
                    const lang = this.state.get('language') || 'fr';
                    const message = suggestion.messages?.[lang] || suggestion.message;
                    this.ui.showProactiveSuggestion(message, suggestionType);
                }, index * 1500);
            });
        }
    }

    /**
     * Navigate to specific step
     * @param {string} targetStep
     */
    async navigateToStep(targetStep) {
        if (this.state.get('isTyping')) return;

        this.log(`Navigating to: ${targetStep}`);

        const lang = this.state.get('language');
        const stepConfig = STEPS.find(s => s.id === targetStep);
        const stepLabel = lang === 'fr' ? stepConfig?.labelFr : stepConfig?.labelEn;

        this.messages.addSystemMessage(`🔄 ${lang === 'fr' ? 'Retour à l\'étape' : 'Returning to step'} "${stepLabel}"...`);
        this.messages.showTyping();

        try {
            const data = await this.api.navigateToStep(targetStep);

            this.messages.hideTyping();

            if (data.success) {
                await this.handleSuccessResponse(data.data);
                this.ui.showNotification(
                    lang === 'fr' ? 'Navigation' : 'Navigation',
                    `${lang === 'fr' ? 'Étape' : 'Step'} "${stepLabel}"`,
                    'info'
                );
            } else {
                await this.messages.addBotMessage(data.error || 'Impossible de naviguer vers cette étape.');
            }
        } catch (error) {
            this.messages.hideTyping();
            this.log('Navigation error:', error);
            await this.messages.addBotMessage('Erreur de navigation. Veuillez réessayer.');
        }
    }

    /**
     * Handle document extracted
     * @param {string} docType
     * @param {Object} extractedData
     * @param {string} fileName
     */
    async handleDocumentExtracted(docType, extractedData, fileName) {
        this.messages.showTyping();

        try {
            const data = await this.api.sendMessage('document_uploaded', {
                document_uploaded: true,
                document_type: docType,
                extracted_data: extractedData,
                file_name: fileName
            });

            this.messages.hideTyping();

            if (data.success) {
                // Phase 7.0 - Inline editing flow
                if (CONFIG.features.inlineEditing.enabled && this.inlineEditor) {
                    // Store extracted data for later use
                    this.currentExtractedData = {
                        docType,
                        extractedData,
                        fileName,
                        serverResponse: data.data
                    };

                    // Show inline confirmation instead of immediate success response
                    this.inlineEditor.showInlineConfirmation(extractedData, docType);
                } else {
                    // Traditional flow: immediate success response
                    await this.handleSuccessResponse(data.data);

                    // Display coherence report if available
                    await this.displayCoherenceReport();
                }
            } else {
                await this.messages.addBotMessage(data.error || 'Une erreur est survenue.');
            }
        } catch (error) {
            this.messages.hideTyping();
            this.log('Document handling error:', error);
            await this.messages.addBotMessage('Erreur de communication avec le serveur.');
        }
    }

    /**
     * Handle inline data confirmed (Phase 7.0)
     * Called when user clicks "Oui, c'est correct"
     * @param {Object} confirmedData - Data confirmed by user
     * @param {string} docType - Document type
     */
    async handleInlineDataConfirmed(confirmedData, docType) {
        this.log('Inline data confirmed', { docType, confirmedData });

        try {
            // Resume normal flow: show success response and continue
            if (this.currentExtractedData?.serverResponse) {
                await this.handleSuccessResponse(this.currentExtractedData.serverResponse);

                // Display coherence report if available
                await this.displayCoherenceReport();
            }

            // Clear stored data
            this.currentExtractedData = null;
        } catch (error) {
            this.log('Inline confirmation error:', error);
            await this.messages.addBotMessage('Erreur lors de la confirmation. Veuillez réessayer.');
        }
    }

    /**
     * Handle inline data edit (Phase 7.0)
     * Called when user clicks "Non, modifier"
     * @param {Object} dataToEdit - Data to be edited
     * @param {string} docType - Document type
     * @param {Object} callbacks - Callbacks for modal (onConfirm, onCancel)
     */
    async handleInlineDataEdit(dataToEdit, docType, callbacks) {
        this.log('Inline data edit requested', { docType, dataToEdit });

        try {
            // Initialize verification modal if not already done
            if (!this._verificationModal && window.VerificationModal) {
                this._verificationModal = new VerificationModal({
                    debug: this.config.debug
                });
            }

            // Open modal with callbacks
            if (this._verificationModal) {
                this._verificationModal.open(dataToEdit, {
                    onConfirm: callbacks?.onConfirm || (() => {}),
                    onCancel: callbacks?.onCancel || (() => {})
                });
            } else {
                this.log('Error: VerificationModal not available');
                await this.messages.addBotMessage('Erreur : impossible d\'ouvrir le formulaire de modification.');
            }
        } catch (error) {
            this.log('Error opening edit modal:', error);
            await this.messages.addBotMessage('Erreur lors de l\'ouverture du formulaire.');
        }
    }

    /**
     * Display coherence validation report
     */
    async displayCoherenceReport() {
        if (!this._coherenceUI) return;

        try {
            const reportElement = await this._coherenceUI.validateAndDisplay();

            if (reportElement) {
                const lang = this.state.get('language');
                const msgEl = document.createElement('div');
                msgEl.className = 'message bot';
                msgEl.innerHTML = `
                    <div class="message-avatar">
                        <span class="avatar-emoji">${PERSONA.avatar}</span>
                    </div>
                    <div class="message-content coherence-report-container">
                        <p style="margin-bottom: var(--space-3);">
                            <strong>📋 ${lang === 'fr' ? 'Analyse de votre dossier' : 'File Analysis'}</strong><br>
                            ${lang === 'fr' ? 'Voici le résumé de cohérence de vos documents :' : 'Here is the coherence summary of your documents:'}
                        </p>
                    </div>
                `;

                const contentEl = msgEl.querySelector('.message-content');
                contentEl.appendChild(reportElement);
                this.elements.chatMessages.appendChild(msgEl);
                this.messages.scrollToBottom();

                this.log('Coherence report displayed');
            }
        } catch (error) {
            this.log('Error displaying coherence report:', error);
        }
    }

    /**
     * Add scan passport button
     */
    addScanButton() {
        const lang = this.state.get('language');
        const btn = document.createElement('button');
        btn.className = 'quick-action-btn';
        btn.innerHTML = `📷 ${lang === 'fr' ? 'Scanner mon passeport' : 'Scan my passport'}`;
        btn.addEventListener('click', () => this.openPassportScanner());
        this.elements.quickActions?.appendChild(btn);
    }

    /**
     * Add documents upload button
     */
    addDocumentsUploadButton() {
        const lang = this.state.get('language');

        const btn = document.createElement('button');
        btn.className = 'quick-action-btn primary';
        btn.innerHTML = `📁 ${lang === 'fr' ? 'Télécharger mes documents' : 'Upload my documents'}`;
        btn.addEventListener('click', () => this.openMultiUploader());
        this.elements.quickActions?.appendChild(btn);

        const skipBtn = document.createElement('button');
        skipBtn.className = 'quick-action-btn';
        skipBtn.innerHTML = `${lang === 'fr' ? 'Scanner passeport seul →' : 'Scan passport only →'}`;
        skipBtn.addEventListener('click', () => this.sendMessage('passport_only'));
        this.elements.quickActions?.appendChild(skipBtn);
    }

    /**
     * Trigger document upload
     * @param {string} docType
     */
    triggerDocumentUpload(docType) {
        this.log('Triggering upload for:', docType);

        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*,application/pdf';
        input.onchange = (e) => {
            const file = e.target.files[0];
            if (file) {
                const uploadZone = document.querySelector('.document-upload-zone');
                if (uploadZone) {
                    this.upload.handleDocumentUpload(file, docType, uploadZone);
                }
            }
        };
        input.click();
    }

    // =========== PASSPORT SCANNER ===========

    /**
     * Open passport scanner
     * Uses Innovatrics openChoiceModal() when feature flag is enabled
     */
    openPassportScanner() {
        // Check if we should use Innovatrics choice modal
        const useChoiceModal = CONFIG.features?.innovatricsCamera?.useChoiceModal;

        if (useChoiceModal) {
            this.openInnovatricsChoiceModal('passport');
            return;
        }

        // Fallback to legacy overlay
        if (this.elements.passportScannerOverlay) {
            this.elements.passportScannerOverlay.hidden = false;
        }
    }

    /**
     * Open Innovatrics camera choice modal (Desktop/Mobile)
     * @param {string} docType - Document type (passport, ticket, etc.)
     */
    openInnovatricsChoiceModal(docType = 'passport') {
        this.log(`Opening Innovatrics choice modal for: ${docType}`);

        // Check if Innovatrics is available
        if (typeof window.InnovatricsCameraCapture === 'undefined') {
            this.log('InnovatricsCameraCapture not available, falling back to file upload');
            this.ui.showNotification(
                'Info',
                this.state.get('language') === 'fr'
                    ? 'Module caméra non disponible. Utilisez l\'upload de fichier.'
                    : 'Camera module not available. Use file upload.',
                'warning'
            );
            return;
        }

        const lang = this.state.get('language') || 'fr';

        // Create Innovatrics instance
        const cameraCapture = new window.InnovatricsCameraCapture({
            type: docType,
            language: lang,
            debug: this.config.debug,
            assetsPath: window.APP_CONFIG?.baseUrl || '/hunyuanocr/visa-chatbot',

            onCapture: async (captureData) => {
                this.log('Camera capture received:', captureData);
                await this.handleCapturedDocument(captureData, docType);
            },

            onError: (error) => {
                this.log('Camera error:', error);
                this.ui.showNotification(
                    lang === 'fr' ? 'Erreur' : 'Error',
                    error?.message || error,
                    'error'
                );
            },

            onClose: () => {
                this.log('Camera modal closed');
            }
        });

        // Open the Desktop/Mobile choice modal
        cameraCapture.openChoiceModal();
    }

    /**
     * Handle captured document from Innovatrics camera
     * @param {Object} captureData - Capture data with file/blob
     * @param {string} docType - Document type
     */
    async handleCapturedDocument(captureData, docType) {
        const lang = this.state.get('language') || 'fr';

        try {
            // Extract the file from capture data
            let file = captureData?.file || captureData?.blob;

            if (!file) {
                throw new Error(lang === 'fr'
                    ? 'Aucune image capturée'
                    : 'No image captured');
            }

            // Convert Blob to File if needed
            if (file instanceof Blob && !(file instanceof File)) {
                file = new File([file], `${docType}-capture.jpg`, {
                    type: 'image/jpeg'
                });
            }

            // Show processing message
            this.messages.addUserMessage(
                lang === 'fr'
                    ? `[📷 ${docType.charAt(0).toUpperCase() + docType.slice(1)} capturé]`
                    : `[📷 ${docType.charAt(0).toUpperCase() + docType.slice(1)} captured]`
            );

            // Process via OCR
            this.log(`Processing captured ${docType} via OCR...`);
            const ocrResult = await this.upload.processDocument(file, docType);

            if (ocrResult?.success && ocrResult?.data) {
                // Store and display extracted data
                this.state.set(`${docType}Data`, ocrResult.data);

                // Use inline editing to show data and get confirmation
                if (this.inlineEditing) {
                    this.inlineEditing.showExtractedData(ocrResult.data, docType, lang);
                } else {
                    // Fallback: send success message
                    this.messages.addBotMessage(
                        lang === 'fr'
                            ? `✅ ${docType} traité avec succès !`
                            : `✅ ${docType} processed successfully!`
                    );
                }
            } else {
                throw new Error(ocrResult?.error || 'OCR processing failed');
            }

        } catch (error) {
            this.log('Error processing captured document:', error);
            this.ui.showNotification(
                lang === 'fr' ? 'Erreur' : 'Error',
                error?.message || (lang === 'fr'
                    ? 'Erreur lors du traitement du document'
                    : 'Error processing document'),
                'error'
            );
        }
    }

    /**
     * Close passport scanner
     */
    closePassportScanner() {
        if (this.elements.passportScannerOverlay) {
            this.elements.passportScannerOverlay.hidden = true;
        }
        this.resetScanner();
    }

    /**
     * Preview passport image
     * @param {File} file
     */
    previewPassport(file) {
        if (!file) return;

        this.state.set('passportFile', file);

        const reader = new FileReader();
        reader.onload = (e) => {
            if (this.elements.passportPreviewImage) {
                this.elements.passportPreviewImage.src = e.target.result;
            }
            if (this.elements.passportUploadZone) {
                this.elements.passportUploadZone.classList.add('hidden');
            }
            if (this.elements.passportPreviewArea) {
                this.elements.passportPreviewArea.classList.remove('hidden');
            }
            // Enable confirm button
            if (this.elements.btnConfirmPassport) {
                this.elements.btnConfirmPassport.disabled = false;
            }
        };
        reader.readAsDataURL(file);
    }

    /**
     * Scan passport via OCR API
     */
    async scanPassport() {
        const passportFile = this.state.get('passportFile');
        if (!passportFile) return;

        // Show processing
        if (this.elements.passportPreviewArea) {
            this.elements.passportPreviewArea.classList.add('hidden');
        }
        if (this.elements.passportProcessing) {
            this.elements.passportProcessing.classList.remove('hidden');
        }
        // Disable confirm button during processing
        if (this.elements.btnConfirmPassport) {
            this.elements.btnConfirmPassport.disabled = true;
        }

        try {
            const ocrData = await this.upload.scanPassport(passportFile);

            this.closePassportScanner();
            await this.sendPassportData(ocrData);
        } catch (error) {
            this.log('OCR error:', error);
            const lang = this.state.get('language');
            this.ui.showNotification(
                'Erreur',
                lang === 'fr'
                    ? 'Impossible de lire le passeport. Réessayez avec une meilleure image.'
                    : 'Unable to read passport. Try with a better image.',
                'error'
            );
            this.resetScanner();
        }
    }

    /**
     * Send passport data to backend
     * @param {Object} ocrData
     */
    async sendPassportData(ocrData) {
        const lang = this.state.get('language');
        this.messages.addUserMessage(lang === 'fr' ? '[Passeport scanné]' : '[Passport scanned]');
        this.messages.showTyping();

        try {
            const data = await this.api.sendPassportData(ocrData);

            this.messages.hideTyping();

            if (data.success) {
                // Phase 7.0 - Inline editing flow for passport
                console.log('[DEBUG sendPassportData] CONFIG.features:', CONFIG?.features);
                console.log('[DEBUG sendPassportData] inlineEditing.enabled:', CONFIG?.features?.inlineEditing?.enabled);
                console.log('[DEBUG sendPassportData] this.inlineEditor:', !!this.inlineEditor);
                console.log('[DEBUG sendPassportData] condition:', CONFIG?.features?.inlineEditing?.enabled && !!this.inlineEditor);
                if (CONFIG.features.inlineEditing.enabled && this.inlineEditor) {
                    // Store extracted data for later use
                    this.currentExtractedData = {
                        docType: 'passport',
                        extractedData: ocrData.fields || ocrData,
                        fileName: 'passport',
                        serverResponse: data.data
                    };

                    // Show inline confirmation instead of immediate success response
                    this.inlineEditor.showInlineConfirmation(ocrData.fields || ocrData, 'passport');

                    // Show diplomatic passport notification if applicable
                    if (data.data.is_free) {
                        this.ui.showNotification(
                            lang === 'fr' ? 'Passeport diplomatique' : 'Diplomatic passport',
                            lang === 'fr' ? 'Traitement prioritaire et gratuit !' : 'Priority and free processing!',
                            'success'
                        );
                    }
                } else {
                    // Traditional flow: immediate success response
                    await this.handleSuccessResponse(data.data);

                    if (data.data.is_free) {
                        this.ui.showNotification(
                            lang === 'fr' ? 'Passeport diplomatique' : 'Diplomatic passport',
                            lang === 'fr' ? 'Traitement prioritaire et gratuit !' : 'Priority and free processing!',
                            'success'
                        );
                    }
                }
            } else {
                await this.messages.addBotMessage(data.error || 'Erreur lors du traitement du passeport');
            }
        } catch (error) {
            this.messages.hideTyping();
            this.log('Passport error:', error);
            await this.messages.addBotMessage('Erreur de connexion. Veuillez réessayer.');
        }
    }

    /**
     * Reset scanner to initial state
     */
    resetScanner() {
        this.state.set('passportFile', null);

        if (this.elements.passportUploadZone) {
            this.elements.passportUploadZone.classList.remove('hidden');
        }
        if (this.elements.passportPreviewArea) {
            this.elements.passportPreviewArea.classList.add('hidden');
        }
        if (this.elements.passportProcessing) {
            this.elements.passportProcessing.classList.add('hidden');
        }
        if (this.elements.passportFileInput) {
            this.elements.passportFileInput.value = '';
        }
        // Disable confirm button
        if (this.elements.btnConfirmPassport) {
            this.elements.btnConfirmPassport.disabled = true;
        }
    }

    // =========== MULTI UPLOADER ===========

    /**
     * Open multi-document uploader
     */
    openMultiUploader() {
        if (!window.MultiDocumentUploader) {
            this.ui.showNotification('Erreur', 'Composant d\'upload non chargé', 'error');
            return;
        }

        const modal = document.getElementById('multiUploadModal');
        if (modal) {
            modal.hidden = false;

            if (!this._multiUploader) {
                this._multiUploader = new MultiDocumentUploader({
                    endpoint: this.config.apiEndpoint,
                    debug: this.config.debug,
                    onProgress: (progress) => {
                        this.log('Upload progress:', Math.round(progress * 100) + '%');
                    },
                    onDocumentComplete: (type, result) => {
                        this.log('Document complete:', type, result);
                    },
                    onAllComplete: (results) => {
                        this.handleDocumentsExtracted(results);
                    },
                    onError: (type, error) => {
                        this.ui.showNotification('Erreur', `Erreur ${type}: ${error.message}`, 'error');
                    }
                });

                this._multiUploader.mount('#multiUploadModalBody');
            }
        }
    }

    /**
     * Close multi-document uploader
     */
    closeMultiUploader() {
        const modal = document.getElementById('multiUploadModal');
        if (modal) {
            modal.hidden = true;
        }
    }

    /**
     * Handle documents extracted from multi-uploader
     * @param {Object} results
     */
    async handleDocumentsExtracted(results) {
        this.closeMultiUploader();

        // Get cross-validation
        let validations = null;
        try {
            const validationData = await this.api.validateDocuments(results);
            if (validationData.success) {
                validations = validationData.data;
            }
        } catch (error) {
            this.log('Validation error:', error);
        }

        // Open verification modal
        if (window.VerificationModal) {
            if (!this._verificationModal) {
                this._verificationModal = new VerificationModal({
                    debug: this.config.debug,
                    onConfirm: (finalData, editedFields) => {
                        this.handleDocumentsConfirmed(finalData, validations);
                    },
                    onModify: () => {
                        this.openMultiUploader();
                    },
                    onFieldEdit: (type, field, newValue, oldValue) => {
                        this.log('Field edited:', type, field, oldValue, '->', newValue);
                    }
                });
            }

            this._verificationModal.open(results, validations?.validations || []);
        } else {
            this.handleDocumentsConfirmed(results, validations);
        }
    }

    /**
     * Handle documents confirmed
     * @param {Object} extractedData
     * @param {Object} validations
     */
    async handleDocumentsConfirmed(extractedData, validations) {
        const lang = this.state.get('language');
        this.messages.addUserMessage(lang === 'fr' ? '[Documents analysés]' : '[Documents analyzed]');
        this.messages.showTyping();

        try {
            const data = await this.api.sendMessage('confirm', {
                documents_extracted: true,
                extracted_data: extractedData,
                validations: validations
            });

            this.messages.hideTyping();

            if (data.success) {
                await this.handleSuccessResponse(data.data);

                const docCount = Object.keys(extractedData).length;
                this.ui.showNotification(
                    lang === 'fr' ? 'Documents analysés' : 'Documents analyzed',
                    `${docCount} document(s) ${lang === 'fr' ? 'extrait(s) avec succès' : 'successfully extracted'}`,
                    'success'
                );

                await this.displayCoherenceReport();
            } else {
                await this.messages.addBotMessage(data.error || 'Erreur lors du traitement des documents');
            }
        } catch (error) {
            this.messages.hideTyping();
            this.log('Documents error:', error);
            await this.messages.addBotMessage('Erreur de connexion. Veuillez réessayer.');
        }
    }

    // =========== FILE UPLOAD ===========

    /**
     * Handle generic file upload
     * @param {File} file
     */
    async handleFileUpload(file) {
        if (!file) return;

        const lang = this.state.get('language');
        this.messages.addUserMessage(`[${file.name} ${lang === 'fr' ? 'uploadé' : 'uploaded'}]`);
        this.messages.showTyping();

        try {
            const data = await this.api.sendMessage('file_upload', {
                file_type: this.state.get('pendingInputType') || 'document',
                file_path: file.name
            });

            this.messages.hideTyping();

            if (data.success) {
                await this.handleSuccessResponse(data.data);
            }
        } catch (error) {
            this.messages.hideTyping();
            this.log('Upload error:', error);
        }

        if (this.elements.generalFileInput) {
            this.elements.generalFileInput.value = '';
        }
    }

    // =========== UTILITIES ===========

    /**
     * Get session ID
     * @returns {string|null}
     */
    get sessionId() {
        return this.state.getSessionId();
    }

    /**
     * Get localized error message based on error type
     * @param {Error} error - The error object
     * @returns {string} - User-friendly error message
     */
    getLocalizedErrorMessage(error) {
        const lang = this.state.get('language') || 'fr';
        const errorType = this.classifyError(error);

        const errorMessages = {
            network: {
                fr: '🔌 Problème de connexion internet. Vérifiez votre réseau et réessayez.',
                en: '🔌 Internet connection issue. Check your network and try again.'
            },
            timeout: {
                fr: '⏱️ La requête a pris trop de temps. Veuillez réessayer.',
                en: '⏱️ Request timed out. Please try again.'
            },
            server: {
                fr: '🛠️ Le serveur rencontre un problème temporaire. Réessayez dans quelques instants.',
                en: '🛠️ Server is experiencing a temporary issue. Please try again shortly.'
            },
            auth: {
                fr: '🔐 Votre session a expiré. Actualisez la page pour continuer.',
                en: '🔐 Your session has expired. Refresh the page to continue.'
            },
            validation: {
                fr: '⚠️ Les données envoyées sont invalides. Vérifiez les informations saisies.',
                en: '⚠️ The submitted data is invalid. Please check your input.'
            },
            file_too_large: {
                fr: '📁 Le fichier est trop volumineux (max 10 Mo). Veuillez le compresser.',
                en: '📁 File is too large (max 10 MB). Please compress it.'
            },
            unsupported_format: {
                fr: '📄 Format de fichier non supporté. Utilisez PDF, JPG ou PNG.',
                en: '📄 Unsupported file format. Please use PDF, JPG, or PNG.'
            },
            default: {
                fr: '❌ Une erreur inattendue s\'est produite. Veuillez réessayer.',
                en: '❌ An unexpected error occurred. Please try again.'
            }
        };

        return errorMessages[errorType]?.[lang] || errorMessages.default[lang];
    }

    /**
     * Classify error type from error object
     * @param {Error} error - The error object
     * @returns {string} - Error classification
     */
    classifyError(error) {
        const message = (error.message || '').toLowerCase();
        const name = (error.name || '').toLowerCase();

        // Network errors
        if (name === 'typeerror' && message.includes('fetch')) return 'network';
        if (message.includes('network') || message.includes('connexion')) return 'network';
        if (message.includes('failed to fetch')) return 'network';

        // Timeout
        if (message.includes('timeout') || message.includes('aborted')) return 'timeout';

        // Server errors
        if (message.includes('500') || message.includes('502') || message.includes('503')) return 'server';
        if (message.includes('internal server')) return 'server';

        // Auth errors
        if (message.includes('401') || message.includes('403')) return 'auth';
        if (message.includes('session') || message.includes('token')) return 'auth';

        // Validation errors
        if (message.includes('400') || message.includes('422')) return 'validation';
        if (message.includes('invalid') || message.includes('validation')) return 'validation';

        // File errors
        if (message.includes('too large') || message.includes('size')) return 'file_too_large';
        if (message.includes('format') || message.includes('type')) return 'unsupported_format';

        return 'default';
    }

    /**
     * Scroll chat to show quick actions area
     * Used to ensure language buttons are visible on welcome step
     */
    scrollToQuickActions() {
        if (this.elements.quickActions && this.elements.chatMessages) {
            // Scroll to bottom to show quick actions
            this.messages.scrollToBottom();

            // Also ensure the quick actions area is in view
            this.elements.quickActions.scrollIntoView({
                behavior: 'smooth',
                block: 'end'
            });
        }
    }

    /**
     * Log helper
     * @param  {...any} args
     */
    log(...args) {
        if (this.config.debug) {
            console.log('[VisaChatbot]', ...args);
        }
    }
}

export default VisaChatbot;
