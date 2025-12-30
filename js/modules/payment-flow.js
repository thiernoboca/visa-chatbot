/**
 * Payment Flow Module
 * Handles visa fee calculation, payment link, and proof verification
 *
 * @module payment-flow
 * @version 1.0.0
 *
 * Features:
 * - Automatic fee calculation based on visa type
 * - Payment link generation
 * - Payment proof upload and OCR verification
 * - Amount validation
 */

import { PassportType, VisaTypes } from './requirements-matrix.js';

// Alias for convenience
const VisaType = VisaTypes;

// =============================================================================
// CONSTANTS
// =============================================================================

/**
 * Payment status
 */
export const PaymentStatus = {
    NOT_REQUIRED: 'not_required',
    PENDING: 'pending',
    AWAITING_PROOF: 'awaiting_proof',
    VERIFYING: 'verifying',
    VERIFIED: 'verified',
    FAILED: 'failed',
    EXEMPTED: 'exempted'
};

/**
 * Visa fee structure (in XOF - Franc CFA)
 */
export const VISA_FEES = {
    [VisaType.COURT_SEJOUR_UNIQUE]: {
        amount: 73000,
        currency: 'XOF',
        label: { fr: 'Visa Court Séjour (Entrée Unique)', en: 'Short Stay Visa (Single Entry)' },
        duration: '1-90 jours',
        processingDays: { standard: 10, express: 3 }
    },
    [VisaType.COURT_SEJOUR_MULTIPLE]: {
        amount: 120000,
        currency: 'XOF',
        label: { fr: 'Visa Court Séjour (Entrées Multiples)', en: 'Short Stay Visa (Multiple Entry)' },
        duration: '1-90 jours',
        processingDays: { standard: 10, express: 3 }
    },
    [VisaType.LONG_SEJOUR]: {
        amount: 150000,
        currency: 'XOF',
        label: { fr: 'Visa Long Séjour', en: 'Long Stay Visa' },
        duration: '> 90 jours',
        processingDays: { standard: 15, express: 5 }
    },
    [VisaType.TRANSIT]: {
        amount: 50000,
        currency: 'XOF',
        label: { fr: 'Visa Transit', en: 'Transit Visa' },
        duration: '1-7 jours',
        processingDays: { standard: 5, express: 2 }
    },
    [VisaType.AFFAIRES]: {
        amount: 73000,
        currency: 'XOF',
        label: { fr: 'Visa Affaires', en: 'Business Visa' },
        duration: '1-90 jours',
        processingDays: { standard: 10, express: 3 }
    }
};

/**
 * Express processing surcharge
 */
export const EXPRESS_SURCHARGE = {
    amount: 50000,
    currency: 'XOF',
    label: { fr: 'Supplément Express', en: 'Express Surcharge' }
};

/**
 * Passport types exempted from fees
 */
export const EXEMPTED_PASSPORT_TYPES = [
    PassportType.DIPLOMATIQUE,
    PassportType.SERVICE,
    PassportType.LP_ONU,
    PassportType.LP_UA
];

/**
 * Payment methods
 */
export const PaymentMethod = {
    BANK_TRANSFER: 'bank_transfer',
    MOBILE_MONEY: 'mobile_money',
    CASH: 'cash',
    CARD: 'card'
};

/**
 * Payment method labels
 */
const PAYMENT_METHOD_LABELS = {
    [PaymentMethod.BANK_TRANSFER]: { fr: 'Virement bancaire', en: 'Bank Transfer' },
    [PaymentMethod.MOBILE_MONEY]: { fr: 'Mobile Money', en: 'Mobile Money' },
    [PaymentMethod.CASH]: { fr: 'Espèces', en: 'Cash' },
    [PaymentMethod.CARD]: { fr: 'Carte bancaire', en: 'Bank Card' }
};

/**
 * Treasury account info (for bank transfers)
 */
const TREASURY_INFO = {
    name: 'Trésor Public de Côte d\'Ivoire',
    bank: 'Banque Centrale des États de l\'Afrique de l\'Ouest (BCEAO)',
    accountNumber: 'CI001 01001 000000012345 00',
    swiftCode: 'BCAOAOAB',
    reference: 'VISA-CI-ETH'
};

// =============================================================================
// PAYMENT FLOW CLASS
// =============================================================================

export class PaymentFlow {
    constructor(options = {}) {
        this.language = options.language || 'fr';
        this.visaType = null;
        this.passportType = null;
        this.isExpress = false;
        this.paymentProof = null;
        this.status = PaymentStatus.PENDING;
        this.onChange = options.onChange || (() => {});
    }

    /**
     * Initialize payment flow with application context
     */
    initialize(context) {
        this.visaType = context.visaType || VisaType.COURT_SEJOUR_UNIQUE;
        this.passportType = context.passportType || PassportType.ORDINAIRE;
        this.isExpress = context.isExpress || false;

        // Check if payment is required
        if (this.isExempted()) {
            this.status = PaymentStatus.EXEMPTED;
        } else {
            this.status = PaymentStatus.PENDING;
        }

        this.onChange('initialized', this.getState());
    }

    /**
     * Check if applicant is exempted from fees
     */
    isExempted() {
        return EXEMPTED_PASSPORT_TYPES.includes(this.passportType);
    }

    /**
     * Calculate total fee
     */
    calculateFee() {
        if (this.isExempted()) {
            return {
                baseFee: 0,
                expressSurcharge: 0,
                total: 0,
                currency: 'XOF',
                exemptionReason: this.getExemptionReason()
            };
        }

        const feeInfo = VISA_FEES[this.visaType] || VISA_FEES[VisaType.COURT_SEJOUR_UNIQUE];
        const baseFee = feeInfo.amount;
        const expressSurcharge = this.isExpress ? EXPRESS_SURCHARGE.amount : 0;

        return {
            baseFee,
            baseLabel: this.t(feeInfo.label),
            expressSurcharge,
            expressLabel: this.isExpress ? this.t(EXPRESS_SURCHARGE.label) : null,
            total: baseFee + expressSurcharge,
            currency: feeInfo.currency,
            processingDays: this.isExpress
                ? feeInfo.processingDays.express
                : feeInfo.processingDays.standard
        };
    }

    /**
     * Get exemption reason
     */
    getExemptionReason() {
        const reasons = {
            [PassportType.DIPLOMATIQUE]: {
                fr: 'Exempté - Passeport diplomatique',
                en: 'Exempted - Diplomatic passport'
            },
            [PassportType.SERVICE]: {
                fr: 'Exempté - Passeport de service',
                en: 'Exempted - Service passport'
            },
            [PassportType.LP_ONU]: {
                fr: 'Exempté - Laissez-passer ONU',
                en: 'Exempted - UN Laissez-passer'
            },
            [PassportType.LP_UA]: {
                fr: 'Exempté - Laissez-passer UA',
                en: 'Exempted - AU Laissez-passer'
            }
        };

        return this.t(reasons[this.passportType] || { fr: 'Exempté', en: 'Exempted' });
    }

    /**
     * Get payment instructions
     */
    getPaymentInstructions() {
        const fee = this.calculateFee();

        if (this.isExempted()) {
            return {
                exempted: true,
                reason: this.getExemptionReason(),
                message: this.t({
                    fr: 'Aucun paiement requis pour votre type de passeport.',
                    en: 'No payment required for your passport type.'
                })
            };
        }

        return {
            exempted: false,
            total: fee.total,
            currency: fee.currency,
            formattedTotal: this.formatCurrency(fee.total, fee.currency),
            breakdown: [
                { label: fee.baseLabel, amount: fee.baseFee },
                ...(this.isExpress ? [{ label: fee.expressLabel, amount: fee.expressSurcharge }] : [])
            ],
            methods: this.getPaymentMethods(),
            treasuryInfo: TREASURY_INFO,
            reference: this.generatePaymentReference(),
            message: this.t({
                fr: `Veuillez effectuer le paiement de ${this.formatCurrency(fee.total, fee.currency)} et télécharger la preuve de paiement.`,
                en: `Please make a payment of ${this.formatCurrency(fee.total, fee.currency)} and upload the proof of payment.`
            })
        };
    }

    /**
     * Get available payment methods
     */
    getPaymentMethods() {
        return Object.entries(PAYMENT_METHOD_LABELS).map(([id, labels]) => ({
            id,
            label: this.t(labels)
        }));
    }

    /**
     * Generate payment reference
     */
    generatePaymentReference() {
        const date = new Date();
        const dateStr = date.toISOString().slice(0, 10).replace(/-/g, '');
        const random = Math.random().toString(36).substring(2, 8).toUpperCase();
        return `VISA-${dateStr}-${random}`;
    }

    /**
     * Set payment proof data (from OCR)
     */
    setPaymentProof(proofData) {
        this.paymentProof = {
            ...proofData,
            addedAt: new Date().toISOString()
        };

        this.status = PaymentStatus.VERIFYING;
        this.onChange('proofAdded', this.paymentProof);

        // Verify the proof
        return this.verifyPaymentProof();
    }

    /**
     * Verify payment proof against expected amount
     */
    verifyPaymentProof() {
        if (!this.paymentProof) {
            return {
                valid: false,
                error: this.t({
                    fr: 'Aucune preuve de paiement fournie',
                    en: 'No payment proof provided'
                })
            };
        }

        const expectedFee = this.calculateFee();
        const issues = [];
        const warnings = [];

        // Check amount
        const proofAmount = this.paymentProof.amount || this.paymentProof.extracted?.amount;
        if (proofAmount) {
            const tolerance = expectedFee.total * 0.05; // 5% tolerance
            const difference = Math.abs(proofAmount - expectedFee.total);

            if (difference > tolerance) {
                issues.push({
                    type: 'AMOUNT_MISMATCH',
                    message: this.t({
                        fr: `Montant incorrect. Attendu: ${this.formatCurrency(expectedFee.total, expectedFee.currency)}, Reçu: ${this.formatCurrency(proofAmount, expectedFee.currency)}`,
                        en: `Incorrect amount. Expected: ${this.formatCurrency(expectedFee.total, expectedFee.currency)}, Received: ${this.formatCurrency(proofAmount, expectedFee.currency)}`
                    }),
                    expected: expectedFee.total,
                    received: proofAmount
                });
            }
        } else {
            issues.push({
                type: 'AMOUNT_NOT_FOUND',
                message: this.t({
                    fr: 'Montant non détecté sur la preuve de paiement',
                    en: 'Amount not found on payment proof'
                })
            });
        }

        // Check date (should be recent - within 30 days)
        const proofDate = this.paymentProof.date || this.paymentProof.extracted?.date;
        if (proofDate) {
            const paymentDate = new Date(proofDate);
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);

            if (paymentDate < thirtyDaysAgo) {
                warnings.push({
                    type: 'OLD_PAYMENT',
                    message: this.t({
                        fr: 'Le paiement date de plus de 30 jours',
                        en: 'Payment is more than 30 days old'
                    })
                });
            }
        }

        // Check reference
        const proofReference = this.paymentProof.reference || this.paymentProof.extracted?.reference;
        if (!proofReference) {
            warnings.push({
                type: 'NO_REFERENCE',
                message: this.t({
                    fr: 'Référence de transaction non détectée',
                    en: 'Transaction reference not found'
                })
            });
        }

        // Determine status
        const isValid = issues.length === 0;
        this.status = isValid ? PaymentStatus.VERIFIED : PaymentStatus.FAILED;

        const result = {
            valid: isValid,
            status: this.status,
            issues,
            warnings,
            proof: {
                amount: proofAmount,
                date: proofDate,
                reference: proofReference,
                payer: this.paymentProof.payer || this.paymentProof.extracted?.payer
            },
            expected: {
                amount: expectedFee.total,
                currency: expectedFee.currency
            }
        };

        this.onChange('verified', result);
        return result;
    }

    /**
     * Format currency for display
     */
    formatCurrency(amount, currency = 'XOF') {
        const formatted = new Intl.NumberFormat(this.language === 'fr' ? 'fr-FR' : 'en-US', {
            style: 'decimal',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(amount);

        return `${formatted} ${currency}`;
    }

    /**
     * Get fee breakdown for display
     */
    getFeeBreakdown() {
        const fee = this.calculateFee();

        if (this.isExempted()) {
            return {
                exempted: true,
                reason: this.getExemptionReason(),
                items: [],
                total: 0,
                formattedTotal: this.t({ fr: 'Gratuit', en: 'Free' })
            };
        }

        const items = [
            {
                label: fee.baseLabel,
                amount: fee.baseFee,
                formatted: this.formatCurrency(fee.baseFee, fee.currency)
            }
        ];

        if (this.isExpress) {
            items.push({
                label: fee.expressLabel,
                amount: fee.expressSurcharge,
                formatted: this.formatCurrency(fee.expressSurcharge, fee.currency)
            });
        }

        return {
            exempted: false,
            items,
            total: fee.total,
            formattedTotal: this.formatCurrency(fee.total, fee.currency),
            processingDays: fee.processingDays,
            processingLabel: this.t({
                fr: `Traitement: ${fee.processingDays} jours ouvrables`,
                en: `Processing: ${fee.processingDays} business days`
            })
        };
    }

    /**
     * Toggle express processing
     */
    setExpress(isExpress) {
        this.isExpress = isExpress;
        this.onChange('expressChanged', { isExpress, newFee: this.calculateFee() });
    }

    /**
     * Get current state
     */
    getState() {
        return {
            visaType: this.visaType,
            passportType: this.passportType,
            isExpress: this.isExpress,
            isExempted: this.isExempted(),
            status: this.status,
            fee: this.calculateFee(),
            paymentProof: this.paymentProof
        };
    }

    /**
     * Check if payment is complete
     */
    isComplete() {
        return this.status === PaymentStatus.VERIFIED ||
               this.status === PaymentStatus.EXEMPTED ||
               this.status === PaymentStatus.NOT_REQUIRED;
    }

    /**
     * Get summary for display
     */
    getSummary() {
        const fee = this.calculateFee();
        const verification = this.paymentProof ? this.verifyPaymentProof() : null;

        return {
            status: this.status,
            statusLabel: this.getStatusLabel(),
            isExempted: this.isExempted(),
            exemptionReason: this.isExempted() ? this.getExemptionReason() : null,
            total: fee.total,
            formattedTotal: this.formatCurrency(fee.total, fee.currency),
            isComplete: this.isComplete(),
            hasProof: !!this.paymentProof,
            proofValid: verification?.valid,
            issues: verification?.issues || [],
            warnings: verification?.warnings || []
        };
    }

    /**
     * Get status label
     */
    getStatusLabel() {
        const labels = {
            [PaymentStatus.NOT_REQUIRED]: { fr: 'Non requis', en: 'Not required' },
            [PaymentStatus.PENDING]: { fr: 'En attente', en: 'Pending' },
            [PaymentStatus.AWAITING_PROOF]: { fr: 'Preuve requise', en: 'Proof required' },
            [PaymentStatus.VERIFYING]: { fr: 'Vérification...', en: 'Verifying...' },
            [PaymentStatus.VERIFIED]: { fr: 'Vérifié', en: 'Verified' },
            [PaymentStatus.FAILED]: { fr: 'Échec', en: 'Failed' },
            [PaymentStatus.EXEMPTED]: { fr: 'Exempté', en: 'Exempted' }
        };

        return this.t(labels[this.status] || { fr: this.status, en: this.status });
    }

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
     * Reset flow
     */
    reset() {
        this.visaType = null;
        this.passportType = null;
        this.isExpress = false;
        this.paymentProof = null;
        this.status = PaymentStatus.PENDING;
    }

    /**
     * Export state
     */
    exportState() {
        return {
            visaType: this.visaType,
            passportType: this.passportType,
            isExpress: this.isExpress,
            paymentProof: this.paymentProof,
            status: this.status
        };
    }

    /**
     * Import state
     */
    importState(state) {
        if (state.visaType) this.visaType = state.visaType;
        if (state.passportType) this.passportType = state.passportType;
        if (state.isExpress !== undefined) this.isExpress = state.isExpress;
        if (state.paymentProof) this.paymentProof = state.paymentProof;
        if (state.status) this.status = state.status;
    }
}

// =============================================================================
// SINGLETON EXPORT
// =============================================================================

export const paymentFlow = new PaymentFlow();

export default paymentFlow;
