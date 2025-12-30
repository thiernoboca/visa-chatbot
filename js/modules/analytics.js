/**
 * Analytics Module
 * Handles analytics tracking and A/B testing
 *
 * @version 4.0.0
 * @module Analytics
 */

import { stateManager } from './state.js';

/**
 * Analytics Manager class
 */
export class AnalyticsManager {
    constructor() {
        this.isInitialized = false;
        this.sessionId = null;
        this.debug = false;
        this.stepStartTimes = {};
    }

    /**
     * Initialize analytics
     * @param {string} sessionId
     * @param {Object} options
     */
    init(sessionId, options = {}) {
        this.sessionId = sessionId;
        this.debug = options.debug || false;

        // Initialize external analytics client if available
        if (window.AnalyticsClient) {
            window.AnalyticsClient.init(sessionId, { debug: this.debug });
            this.isInitialized = true;
            this.log('Analytics initialized');
        }

        // Initialize A/B testing client if available
        if (window.ABTestingClient) {
            window.ABTestingClient.init(sessionId, { debug: this.debug });
            window.ABTestingClient.preloadVariants([
                'welcome_message',
                'passport_scan_ui',
                'quick_actions_style',
                'cta_text'
            ]);
        }

        return this;
    }

    /**
     * Track session start
     */
    trackSessionStart() {
        if (window.AnalyticsClient) {
            window.AnalyticsClient.trackSessionStart();
        }
        this.log('Session started');
    }

    /**
     * Track session end
     * @param {boolean} completed
     */
    trackSessionEnd(completed = false) {
        if (window.AnalyticsClient) {
            window.AnalyticsClient.trackSessionEnd(completed);
        }
        this.log('Session ended', { completed });
    }

    /**
     * Track step start
     * @param {string} step
     */
    trackStepStart(step) {
        this.stepStartTimes[step] = performance.now();

        if (window.AnalyticsClient) {
            window.AnalyticsClient.trackStepStart(step);
        }
        this.log('Step started', { step });
    }

    /**
     * Track step completion
     * @param {string} step
     */
    trackStepComplete(step) {
        const startTime = this.stepStartTimes[step];
        const duration = startTime ? (performance.now() - startTime) / 1000 : 0;

        if (window.AnalyticsClient) {
            window.AnalyticsClient.trackStepComplete(step, duration);
        }
        this.log('Step completed', { step, duration });
    }

    /**
     * Track document upload
     * @param {string} docType
     * @param {boolean} success
     * @param {number} duration
     */
    trackDocumentUpload(docType, success, duration = 0) {
        if (window.AnalyticsClient) {
            window.AnalyticsClient.trackDocumentUpload?.(docType, success, duration);
        }
        this.log('Document upload', { docType, success, duration });
    }

    /**
     * Track error
     * @param {string} step
     * @param {string} errorType
     * @param {string} errorMessage
     */
    trackError(step, errorType, errorMessage) {
        if (window.AnalyticsClient) {
            window.AnalyticsClient.trackError(step, errorType, errorMessage);
        }
        this.log('Error tracked', { step, errorType, errorMessage });
    }

    /**
     * Track application completion
     */
    trackApplicationComplete() {
        this.trackSessionEnd(true);

        // Track A/B test conversions
        if (window.ABTestingClient) {
            const activeTests = [
                'welcome_message',
                'passport_scan_ui',
                'quick_actions_style',
                'cta_text',
                'confirmation_layout'
            ];
            activeTests.forEach(testId => {
                window.ABTestingClient.trackConversion(testId);
            });
        }

        this.log('Application completed');
    }

    /**
     * Track custom event
     * @param {string} eventName
     * @param {Object} data
     */
    trackEvent(eventName, data = {}) {
        if (window.AnalyticsClient?.trackEvent) {
            window.AnalyticsClient.trackEvent(eventName, data);
        }
        this.log('Custom event', { eventName, ...data });
    }

    /**
     * Get A/B test variant
     * @param {string} testId
     * @returns {string|null}
     */
    getVariant(testId) {
        if (window.ABTestingClient) {
            return window.ABTestingClient.getVariant(testId);
        }
        return null;
    }

    /**
     * Track A/B test conversion
     * @param {string} testId
     */
    trackABConversion(testId) {
        if (window.ABTestingClient) {
            window.ABTestingClient.trackConversion(testId);
        }
    }

    /**
     * Internal logging
     * @param  {...any} args
     */
    log(...args) {
        if (this.debug) {
            console.log('[Analytics]', ...args);
        }
    }
}

// Export singleton
export const analyticsManager = new AnalyticsManager();
export default analyticsManager;
