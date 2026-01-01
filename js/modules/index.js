/**
 * Module Index
 * Exports all chatbot modules
 *
 * @version 6.0.2
 * @description Aya Intelligence+ - Smart prefill cascade, gamification features,
 *              proactive suggestions, and enhanced user experience
 */

// ============================================================================
// CORE MODULES (Existing)
// ============================================================================

// Configuration
export * from './config.js';
export { CONFIG as config } from './config.js';

// Internationalization
export * from './i18n.js';
export { default as i18n } from './i18n.js';

// State Management
export * from './state.js';
export { default as stateManager } from './state.js';

// Messages
export * from './messages.js';
export { default as messagesManager } from './messages.js';

// UI (v6.0.1 - quick action label fix)
export * from './ui.js?v=6.0.1';
export { default as uiManager } from './ui.js?v=6.0.1';

// Upload
export * from './upload.js';
export { default as uploadManager } from './upload.js';

// Analytics
export * from './analytics.js';
export { default as analyticsManager } from './analytics.js';

// API
export * from './api.js';
export { default as apiManager } from './api.js';

// Main Chatbot (v6.0.1 - quick action label fix)
export * from './chatbot.js?v=6.0.1';
export { default as VisaChatbot } from './chatbot.js?v=6.0.1';

// ============================================================================
// PHASE 1: Requirements & Document Flow
// ============================================================================

// Requirements Matrix - Dynamic document requirements based on passport type
export * from './requirements-matrix.js';
export { default as requirementsMatrix } from './requirements-matrix.js';

// Document Flow - Adaptive workflow orchestration (19 steps)
export * from './document-flow.js';
export { default as documentFlow } from './document-flow.js';

// Validation UI - Real-time validation feedback
export * from './validation-ui.js';
export { default as validationUI } from './validation-ui.js';

// ============================================================================
// PHASE 2: OCR Fallback
// ============================================================================

// OCR Fallback - Manual entry when OCR fails or confidence is low
export * from './ocr-fallback.js';
export { default as ocrFallback } from './ocr-fallback.js';

// ============================================================================
// PHASE 3: Optional Documents & Accompanists
// ============================================================================

// Optional Documents - Explicit questions for optional documents
export * from './optional-docs.js';
export { default as optionalDocsManager } from './optional-docs.js';

// Accompanist - Traveling companions and minors management
export * from './accompanist.js';
export { default as accompanistManager } from './accompanist.js';

// ============================================================================
// PHASE 4: Health Declaration & Payment
// ============================================================================

// Health Declaration - Symptoms, travel history, vaccination
export * from './health-declaration.js';
export { default as healthDeclaration } from './health-declaration.js';

// Payment Flow - Fee calculation, payment, and proof verification
export * from './payment-flow.js';
export { default as paymentFlow } from './payment-flow.js';

// ============================================================================
// PHASE 5: Signature & PDF Generation
// ============================================================================

// Signature - Electronic signature capture via canvas
export * from './signature.js';
export { default as signatureManager } from './signature.js';

// PDF Generator - Receipt/application PDF creation
export * from './pdf-generator.js';
export { default as pdfGenerator } from './pdf-generator.js';

// ============================================================================
// PHASE 6: Flow Integration
// ============================================================================

// Flow Integration - Orchestrates all modules
export * from './flow-integration.js';
export { default as flowIntegration } from './flow-integration.js';

// ============================================================================
// UX ENHANCEMENTS
// ============================================================================

// UX Enhancements - Premium user experience features
export * from './ux-enhancements.js';
export { default as uxEnhancements } from './ux-enhancements.js';

// ============================================================================
// CROSS-DOCUMENT VALIDATION
// ============================================================================

// Cross-Document Validation - Validates consistency between documents
export * from './cross-document-validation.js';
export { default as crossDocumentValidation } from './cross-document-validation.js';

// ============================================================================
// PHASE 6.0: GAMIFICATION & SMART PREFILL
// ============================================================================

// Progress Tracker - Visual progress bar with animated step indicators
export * from './progress-tracker.js';
export { default as ProgressTracker } from './progress-tracker.js';

// Celebration Manager - Confetti, toasts, badge animations for milestones
export * from './celebrations.js';
export { default as CelebrationManager } from './celebrations.js';

// Time Estimator - Dynamic time remaining calculation based on user pace
export * from './time-estimator.js';
export { default as TimeEstimator } from './time-estimator.js';

// ============================================================================
// MODULE METADATA
// ============================================================================

/**
 * Module version and metadata
 */
export const MODULE_VERSION = {
    version: '6.0.2',
    modules: {
        core: ['config', 'i18n', 'state', 'messages', 'ui', 'upload', 'analytics', 'api', 'chatbot'],
        phase1: ['requirements-matrix', 'document-flow', 'validation-ui'],
        phase2: ['ocr-fallback'],
        phase3: ['optional-docs', 'accompanist'],
        phase4: ['health-declaration', 'payment-flow'],
        phase5: ['signature', 'pdf-generator'],
        phase6: ['flow-integration'],
        gamification: ['progress-tracker', 'celebrations', 'time-estimator'],
        ux: ['ux-enhancements'],
        validation: ['cross-document-validation']
    },
    lastUpdated: '2025-12-27',
    changelog: {
        '6.0.0': 'Aya Intelligence+ - Smart prefill cascade (8 documents), gamification (progress tracker, celebrations, time estimator), proactive suggestions, cross-document validation',
        '5.3.0': 'Added persona Aya enhancements: IP geolocation (ipinfo.io), vaccination warnings, travel dates prefill',
        '5.2.0': 'Integrated flow-integration, document-flow, requirements-matrix into chatbot.js for client-side flow management'
    }
};
