/**
 * Validation UI Module
 * Displays real-time validation results for documents
 *
 * @version 1.0.0
 * Handles inline validation display, cross-validation status, and progress indicators
 */

import { requirementsMatrix, DocumentTypes } from './requirements-matrix.js';

/**
 * Validation status types
 */
export const ValidationStatus = {
    PENDING: 'pending',
    VALIDATING: 'validating',
    SUCCESS: 'success',
    WARNING: 'warning',
    ERROR: 'error'
};

/**
 * Validation check types
 */
export const CheckType = {
    NAME_MATCH: 'name_match',
    DATE_VALID: 'date_valid',
    EXPIRATION: 'expiration',
    FORMAT: 'format',
    DESTINATION: 'destination',
    COHERENCE: 'coherence',
    OCR_CONFIDENCE: 'ocr_confidence',
    REQUIRED_FIELD: 'required_field'
};

/**
 * Document-specific validators
 */
const DocumentValidators = {
    passport: {
        checks: [
            {
                type: CheckType.EXPIRATION,
                validate: (data, ctx) => {
                    if (!data.expiration_date) return null;
                    const expDate = new Date(data.expiration_date);
                    const sixMonthsFromNow = new Date();
                    sixMonthsFromNow.setMonth(sixMonthsFromNow.getMonth() + 6);
                    return expDate > sixMonthsFromNow;
                },
                successKey: 'validation_passport_valid',
                errorKey: 'validation_passport_expires_soon'
            },
            {
                type: CheckType.REQUIRED_FIELD,
                field: 'passport_number',
                validate: (data) => !!data.passport_number,
                successKey: 'validation_passport_number_found',
                errorKey: 'validation_passport_number_missing'
            },
            {
                type: CheckType.REQUIRED_FIELD,
                field: 'full_name',
                validate: (data) => !!data.full_name || (!!data.surname && !!data.given_names),
                successKey: 'validation_name_found',
                errorKey: 'validation_name_missing'
            },
            {
                type: CheckType.OCR_CONFIDENCE,
                validate: (data) => (data.confidence || 0) >= 70,
                successKey: 'validation_ocr_good',
                warningKey: 'validation_ocr_low',
                threshold: 70
            }
        ]
    },

    ticket: {
        checks: [
            {
                type: CheckType.NAME_MATCH,
                validate: (data, ctx) => {
                    if (!data.passenger_name || !ctx.passport?.full_name) return null;
                    return fuzzyNameMatch(data.passenger_name, ctx.passport.full_name) >= 0.8;
                },
                successKey: 'validation_name_matches_passport',
                errorKey: 'validation_name_mismatch'
            },
            {
                type: CheckType.DATE_VALID,
                validate: (data) => {
                    if (!data.departure_date) return null;
                    return new Date(data.departure_date) > new Date();
                },
                successKey: 'validation_date_future',
                errorKey: 'validation_date_past'
            },
            {
                type: CheckType.DESTINATION,
                validate: (data) => {
                    if (!data.arrival_city && !data.arrival_code) return null;
                    const ciCodes = ['ABJ', 'Abidjan', 'BYK', 'Bouake', 'CI', 'CIV'];
                    return ciCodes.some(code =>
                        (data.arrival_city?.toLowerCase().includes(code.toLowerCase())) ||
                        (data.arrival_code?.toUpperCase() === code.toUpperCase())
                    );
                },
                successKey: 'validation_destination_ci',
                errorKey: 'validation_destination_not_ci'
            }
        ]
    },

    hotel: {
        checks: [
            {
                type: CheckType.NAME_MATCH,
                validate: (data, ctx) => {
                    if (!data.guest_name || !ctx.passport?.full_name) return null;
                    return fuzzyNameMatch(data.guest_name, ctx.passport.full_name) >= 0.8;
                },
                successKey: 'validation_guest_matches',
                errorKey: 'validation_guest_mismatch'
            },
            {
                type: CheckType.DATE_VALID,
                validate: (data, ctx) => {
                    if (!data.check_in_date || !ctx.ticket?.arrival_date) return null;
                    return new Date(data.check_in_date) >= new Date(ctx.ticket.arrival_date);
                },
                successKey: 'validation_dates_coherent',
                errorKey: 'validation_checkin_before_arrival'
            },
            {
                type: CheckType.REQUIRED_FIELD,
                field: 'confirmation_number',
                validate: (data) => !!data.confirmation_number,
                successKey: 'validation_confirmation_found',
                warningKey: 'validation_no_confirmation'
            }
        ]
    },

    vaccination: {
        checks: [
            {
                type: CheckType.NAME_MATCH,
                validate: (data, ctx) => {
                    if (!data.patient_name || !ctx.passport?.full_name) return null;
                    return fuzzyNameMatch(data.patient_name, ctx.passport.full_name) >= 0.8;
                },
                successKey: 'validation_patient_matches',
                errorKey: 'validation_patient_mismatch'
            },
            {
                type: CheckType.REQUIRED_FIELD,
                field: 'vaccine_type',
                validate: (data) => {
                    if (!data.vaccine_type) return false;
                    const yellowFeverTerms = ['yellow fever', 'fièvre jaune', 'amaril', 'yellow'];
                    return yellowFeverTerms.some(term =>
                        data.vaccine_type.toLowerCase().includes(term)
                    );
                },
                successKey: 'validation_yellow_fever_found',
                errorKey: 'validation_yellow_fever_missing'
            },
            {
                type: CheckType.DATE_VALID,
                validate: (data) => {
                    if (!data.vaccination_date) return null;
                    const vaccDate = new Date(data.vaccination_date);
                    const tenDaysAgo = new Date();
                    tenDaysAgo.setDate(tenDaysAgo.getDate() - 10);
                    return vaccDate <= tenDaysAgo; // Must be at least 10 days old
                },
                successKey: 'validation_vaccination_effective',
                warningKey: 'validation_vaccination_too_recent'
            }
        ]
    },

    verbal_note: {
        checks: [
            {
                type: CheckType.NAME_MATCH,
                validate: (data, ctx) => {
                    if (!data.diplomat_name || !ctx.passport?.full_name) return null;
                    return fuzzyNameMatch(data.diplomat_name, ctx.passport.full_name) >= 0.8;
                },
                successKey: 'validation_diplomat_matches',
                errorKey: 'validation_diplomat_mismatch'
            },
            {
                type: CheckType.DATE_VALID,
                validate: (data) => {
                    if (!data.date_issued) return null;
                    const issueDate = new Date(data.date_issued);
                    const threeMonthsAgo = new Date();
                    threeMonthsAgo.setMonth(threeMonthsAgo.getMonth() - 3);
                    return issueDate >= threeMonthsAgo;
                },
                successKey: 'validation_note_recent',
                errorKey: 'validation_note_too_old'
            },
            {
                type: CheckType.REQUIRED_FIELD,
                field: 'reference_number',
                validate: (data) => !!data.reference_number,
                successKey: 'validation_reference_found',
                warningKey: 'validation_no_reference'
            }
        ]
    },

    residence_card: {
        checks: [
            {
                type: CheckType.NAME_MATCH,
                validate: (data, ctx) => {
                    if (!data.holder_name || !ctx.passport?.full_name) return null;
                    return fuzzyNameMatch(data.holder_name, ctx.passport.full_name) >= 0.8;
                },
                successKey: 'validation_holder_matches',
                errorKey: 'validation_holder_mismatch'
            },
            {
                type: CheckType.EXPIRATION,
                validate: (data) => {
                    if (!data.expiry_date) return null;
                    const expDate = new Date(data.expiry_date);
                    const threeMonthsFromNow = new Date();
                    threeMonthsFromNow.setMonth(threeMonthsFromNow.getMonth() + 3);
                    return expDate > threeMonthsFromNow;
                },
                successKey: 'validation_card_valid',
                errorKey: 'validation_card_expires_soon'
            },
            {
                type: CheckType.COHERENCE,
                validate: (data, ctx) => {
                    if (!data.residence_country || !ctx.residenceCountry) return null;
                    return data.residence_country.toUpperCase() === ctx.residenceCountry.toUpperCase();
                },
                successKey: 'validation_country_matches',
                errorKey: 'validation_country_mismatch'
            }
        ]
    }
};

/**
 * Fuzzy name matching function
 */
function fuzzyNameMatch(name1, name2) {
    if (!name1 || !name2) return 0;

    const normalize = (str) => str
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z\s]/g, '')
        .trim()
        .split(/\s+/)
        .sort()
        .join(' ');

    const n1 = normalize(name1);
    const n2 = normalize(name2);

    if (n1 === n2) return 1;

    // Check if all words in shorter name are in longer name
    const words1 = n1.split(' ');
    const words2 = n2.split(' ');
    const shorter = words1.length <= words2.length ? words1 : words2;
    const longer = words1.length > words2.length ? words1 : words2;

    const matchedWords = shorter.filter(word =>
        longer.some(w => w.includes(word) || word.includes(w))
    );

    return matchedWords.length / shorter.length;
}

/**
 * ValidationUI class
 * Manages validation display and updates
 */
export class ValidationUI {
    constructor(options = {}) {
        this.container = options.container || null;
        this.i18n = options.i18n || ((key) => key);
        this.validators = DocumentValidators;
        this.validationResults = new Map();
        this.listeners = new Map();
    }

    /**
     * Set i18n function
     */
    setI18n(i18nFunc) {
        this.i18n = i18nFunc;
    }

    /**
     * Validate document data
     * @param {string} docType - Document type code
     * @param {Object} data - Extracted data
     * @param {Object} context - Application context
     * @returns {Object} Validation result
     */
    validateDocument(docType, data, context = {}) {
        const validator = this.validators[docType];
        if (!validator) {
            return {
                status: ValidationStatus.SUCCESS,
                checks: [],
                errors: [],
                warnings: []
            };
        }

        const checks = [];
        const errors = [];
        const warnings = [];

        for (const check of validator.checks) {
            try {
                const result = check.validate(data, context);

                if (result === null) {
                    // Inconclusive - missing data
                    checks.push({
                        type: check.type,
                        field: check.field,
                        status: 'inconclusive',
                        message: this.i18n('validation_data_missing')
                    });
                } else if (result === true) {
                    checks.push({
                        type: check.type,
                        field: check.field,
                        status: 'success',
                        message: this.i18n(check.successKey)
                    });
                } else {
                    const isWarning = !!check.warningKey;
                    checks.push({
                        type: check.type,
                        field: check.field,
                        status: isWarning ? 'warning' : 'error',
                        message: this.i18n(isWarning ? check.warningKey : check.errorKey)
                    });

                    if (isWarning) {
                        warnings.push(check);
                    } else {
                        errors.push(check);
                    }
                }
            } catch (error) {
                console.warn(`Validation check failed for ${docType}:`, error);
            }
        }

        const status = errors.length > 0 ? ValidationStatus.ERROR :
            warnings.length > 0 ? ValidationStatus.WARNING :
                ValidationStatus.SUCCESS;

        const result = { status, checks, errors, warnings, data, docType };
        this.validationResults.set(docType, result);
        this.emit('validationComplete', result);

        return result;
    }

    /**
     * Render inline validation result
     * @param {string} docType - Document type
     * @param {Object} result - Validation result
     * @returns {HTMLElement}
     */
    renderValidationCard(docType, result) {
        const card = document.createElement('div');
        card.className = `validation-card validation-${result.status}`;
        card.dataset.docType = docType;

        const meta = requirementsMatrix.getDocumentMeta(docType);
        const icon = meta?.icon || '📄';
        const title = this.i18n(meta?.nameKey || docType);

        card.innerHTML = `
            <div class="validation-header">
                <span class="validation-icon">${icon}</span>
                <span class="validation-title">${title}</span>
                <span class="validation-status-badge ${result.status}">
                    ${this.getStatusIcon(result.status)}
                </span>
            </div>
            <div class="validation-checks">
                ${result.checks.map(check => this.renderCheck(check)).join('')}
            </div>
            ${result.errors.length > 0 ? `
                <div class="validation-actions">
                    <button class="btn-retry" data-doc="${docType}">
                        ${this.i18n('btn_retry_upload')}
                    </button>
                    <button class="btn-manual" data-doc="${docType}">
                        ${this.i18n('btn_enter_manually')}
                    </button>
                </div>
            ` : ''}
        `;

        // Bind action buttons
        const retryBtn = card.querySelector('.btn-retry');
        const manualBtn = card.querySelector('.btn-manual');

        if (retryBtn) {
            retryBtn.addEventListener('click', () => this.emit('retryUpload', docType));
        }
        if (manualBtn) {
            manualBtn.addEventListener('click', () => this.emit('enterManually', docType));
        }

        return card;
    }

    /**
     * Render single check item
     */
    renderCheck(check) {
        const statusIcon = {
            success: '✓',
            warning: '⚠',
            error: '✗',
            inconclusive: '?'
        }[check.status] || '?';

        return `
            <div class="validation-check ${check.status}">
                <span class="check-icon">${statusIcon}</span>
                <span class="check-message">${check.message}</span>
            </div>
        `;
    }

    /**
     * Get status icon
     */
    getStatusIcon(status) {
        const icons = {
            [ValidationStatus.SUCCESS]: '✓',
            [ValidationStatus.WARNING]: '⚠',
            [ValidationStatus.ERROR]: '✗',
            [ValidationStatus.PENDING]: '○',
            [ValidationStatus.VALIDATING]: '◌'
        };
        return icons[status] || '?';
    }

    /**
     * Show inline validation in container
     */
    showInlineValidation(docType, result) {
        if (!this.container) return;

        // Remove existing validation for this doc
        const existing = this.container.querySelector(`[data-doc-type="${docType}"]`);
        if (existing) {
            existing.remove();
        }

        const card = this.renderValidationCard(docType, result);
        card.classList.add('slide-up');
        this.container.appendChild(card);
    }

    /**
     * Render document progress summary
     * @param {Object} completionStatus - From requirementsMatrix
     * @returns {HTMLElement}
     */
    renderProgressSummary(completionStatus) {
        const container = document.createElement('div');
        container.className = 'validation-progress-summary';

        container.innerHTML = `
            <div class="progress-header">
                <span class="progress-title">${this.i18n('documents_progress')}</span>
                <span class="progress-count">
                    ${completionStatus.required.completed}/${completionStatus.required.total}
                </span>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar-fill" style="width: ${completionStatus.overallPercentage}%"></div>
            </div>
            <div class="progress-details">
                ${completionStatus.required.pending.length > 0 ? `
                    <div class="pending-docs">
                        <span class="pending-label">${this.i18n('documents_pending')}:</span>
                        ${completionStatus.required.pending.map(code => {
            const meta = requirementsMatrix.getDocumentMeta(code);
            return `<span class="pending-doc">${meta?.icon || '📄'} ${this.i18n(meta?.nameKey || code)}</span>`;
        }).join('')}
                    </div>
                ` : `
                    <div class="all-complete">
                        ${this.i18n('all_documents_complete')}
                    </div>
                `}
            </div>
        `;

        return container;
    }

    /**
     * Render cross-validation summary
     * @param {Object} coherenceResult - From coherence API
     * @returns {HTMLElement}
     */
    renderCoherenceReport(coherenceResult) {
        const container = document.createElement('div');
        container.className = `coherence-report ${this.getScoreClass(coherenceResult.score)}`;

        container.innerHTML = `
            <div class="coherence-header">
                <div class="coherence-score-circle">
                    <svg viewBox="0 0 36 36">
                        <path class="score-bg"
                            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                        <path class="score-fill"
                            stroke-dasharray="${coherenceResult.score}, 100"
                            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                    </svg>
                    <span class="score-value">${Math.round(coherenceResult.score)}%</span>
                </div>
                <div class="coherence-title">
                    <h3>${this.i18n('coherence_title')}</h3>
                    <p class="score-label">${this.getScoreLabel(coherenceResult.score)}</p>
                </div>
            </div>

            <div class="coherence-validations">
                ${(coherenceResult.validations || []).map(v => `
                    <div class="validation-item ${v.status}">
                        <span class="validation-icon">${v.status === 'success' ? '✓' : v.status === 'warning' ? '⚠' : '✗'}</span>
                        <span class="validation-text">${v.message}</span>
                    </div>
                `).join('')}
            </div>

            ${coherenceResult.issues?.length > 0 ? `
                <div class="coherence-issues">
                    <h4>${this.i18n('issues_found')}</h4>
                    <ul>
                        ${coherenceResult.issues.map(issue => `
                            <li class="${issue.severity}">${issue.message}</li>
                        `).join('')}
                    </ul>
                </div>
            ` : ''}
        `;

        return container;
    }

    /**
     * Get score class for styling
     */
    getScoreClass(score) {
        if (score >= 90) return 'score-excellent';
        if (score >= 80) return 'score-good';
        if (score >= 60) return 'score-warning';
        return 'score-error';
    }

    /**
     * Get score label
     */
    getScoreLabel(score) {
        if (score >= 90) return this.i18n('score_excellent');
        if (score >= 80) return this.i18n('score_good');
        if (score >= 60) return this.i18n('score_needs_review');
        return this.i18n('score_issues_detected');
    }

    /**
     * Clear all validation displays
     */
    clearAll() {
        if (this.container) {
            this.container.innerHTML = '';
        }
        this.validationResults.clear();
    }

    /**
     * Get validation result for document
     */
    getValidationResult(docType) {
        return this.validationResults.get(docType);
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
export const validationUI = new ValidationUI();

// Export all
export default {
    ValidationUI,
    validationUI,
    ValidationStatus,
    CheckType,
    fuzzyNameMatch
};
