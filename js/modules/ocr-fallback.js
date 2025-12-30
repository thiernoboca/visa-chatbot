/**
 * OCR Fallback Module
 * Handles OCR failures with manual entry or re-scan options
 *
 * @module ocr-fallback
 * @version 1.0.0
 *
 * Features:
 * - Modal for OCR failure/low confidence scenarios
 * - Document-specific manual entry forms
 * - Pre-fill forms with partial OCR data
 * - Image quality tips for re-scan
 * - Validation of manual entries
 */

import { DocumentTypes } from './requirements-matrix.js';

// =============================================================================
// CONSTANTS
// =============================================================================

/**
 * Minimum OCR confidence threshold
 */
export const OCR_CONFIDENCE_THRESHOLD = 0.7;

/**
 * Fallback options
 */
export const FallbackOption = {
    RESCAN: 'rescan',
    MANUAL: 'manual'
};

/**
 * Document field configurations for manual entry
 */
const DOCUMENT_FIELDS = {
    [DocumentTypes.PASSPORT]: {
        title: { fr: 'Passeport', en: 'Passport' },
        fields: [
            { name: 'surname', label: { fr: 'Nom de famille', en: 'Surname' }, type: 'text', required: true },
            { name: 'given_names', label: { fr: 'Prénoms', en: 'Given Names' }, type: 'text', required: true },
            { name: 'nationality', label: { fr: 'Nationalité', en: 'Nationality' }, type: 'text', required: true },
            { name: 'passport_number', label: { fr: 'Numéro de passeport', en: 'Passport Number' }, type: 'text', required: true },
            { name: 'date_of_birth', label: { fr: 'Date de naissance', en: 'Date of Birth' }, type: 'date', required: true },
            { name: 'sex', label: { fr: 'Sexe', en: 'Sex' }, type: 'select', options: ['M', 'F'], required: true },
            { name: 'place_of_birth', label: { fr: 'Lieu de naissance', en: 'Place of Birth' }, type: 'text', required: false },
            { name: 'date_of_issue', label: { fr: 'Date d\'émission', en: 'Date of Issue' }, type: 'date', required: true },
            { name: 'date_of_expiry', label: { fr: 'Date d\'expiration', en: 'Date of Expiry' }, type: 'date', required: true },
            { name: 'issuing_authority', label: { fr: 'Autorité émettrice', en: 'Issuing Authority' }, type: 'text', required: false }
        ]
    },

    [DocumentTypes.TICKET]: {
        title: { fr: 'Billet d\'avion', en: 'Flight Ticket' },
        fields: [
            { name: 'passenger_name', label: { fr: 'Nom du passager', en: 'Passenger Name' }, type: 'text', required: true },
            { name: 'flight_number', label: { fr: 'Numéro de vol', en: 'Flight Number' }, type: 'text', required: true },
            { name: 'departure_city', label: { fr: 'Ville de départ', en: 'Departure City' }, type: 'text', required: true },
            { name: 'arrival_city', label: { fr: 'Ville d\'arrivée', en: 'Arrival City' }, type: 'text', required: true },
            { name: 'departure_date', label: { fr: 'Date de départ', en: 'Departure Date' }, type: 'date', required: true },
            { name: 'departure_time', label: { fr: 'Heure de départ', en: 'Departure Time' }, type: 'time', required: false },
            { name: 'arrival_date', label: { fr: 'Date d\'arrivée', en: 'Arrival Date' }, type: 'date', required: false },
            { name: 'booking_reference', label: { fr: 'Référence de réservation', en: 'Booking Reference' }, type: 'text', required: false },
            { name: 'airline', label: { fr: 'Compagnie aérienne', en: 'Airline' }, type: 'text', required: false }
        ]
    },

    [DocumentTypes.HOTEL]: {
        title: { fr: 'Réservation d\'hôtel', en: 'Hotel Reservation' },
        fields: [
            { name: 'guest_name', label: { fr: 'Nom du client', en: 'Guest Name' }, type: 'text', required: true },
            { name: 'hotel_name', label: { fr: 'Nom de l\'hôtel', en: 'Hotel Name' }, type: 'text', required: true },
            { name: 'hotel_address', label: { fr: 'Adresse de l\'hôtel', en: 'Hotel Address' }, type: 'text', required: false },
            { name: 'check_in_date', label: { fr: 'Date d\'arrivée', en: 'Check-in Date' }, type: 'date', required: true },
            { name: 'check_out_date', label: { fr: 'Date de départ', en: 'Check-out Date' }, type: 'date', required: true },
            { name: 'confirmation_number', label: { fr: 'Numéro de confirmation', en: 'Confirmation Number' }, type: 'text', required: false },
            { name: 'room_type', label: { fr: 'Type de chambre', en: 'Room Type' }, type: 'text', required: false }
        ]
    },

    [DocumentTypes.VACCINATION]: {
        title: { fr: 'Certificat de vaccination', en: 'Vaccination Certificate' },
        fields: [
            { name: 'patient_name', label: { fr: 'Nom du patient', en: 'Patient Name' }, type: 'text', required: true },
            { name: 'date_of_birth', label: { fr: 'Date de naissance', en: 'Date of Birth' }, type: 'date', required: false },
            { name: 'vaccine_type', label: { fr: 'Type de vaccin', en: 'Vaccine Type' }, type: 'text', required: true, placeholder: 'Yellow Fever / Fièvre Jaune' },
            { name: 'vaccination_date', label: { fr: 'Date de vaccination', en: 'Vaccination Date' }, type: 'date', required: true },
            { name: 'valid_from', label: { fr: 'Valide à partir de', en: 'Valid From' }, type: 'date', required: false },
            { name: 'batch_number', label: { fr: 'Numéro de lot', en: 'Batch Number' }, type: 'text', required: false },
            { name: 'certificate_number', label: { fr: 'Numéro du certificat', en: 'Certificate Number' }, type: 'text', required: false },
            { name: 'issuing_authority', label: { fr: 'Autorité émettrice', en: 'Issuing Authority' }, type: 'text', required: false }
        ]
    },

    [DocumentTypes.INVITATION]: {
        title: { fr: 'Lettre d\'invitation', en: 'Invitation Letter' },
        fields: [
            { name: 'inviter_name', label: { fr: 'Nom de l\'invitant', en: 'Inviter Name' }, type: 'text', required: true },
            { name: 'inviter_address', label: { fr: 'Adresse de l\'invitant', en: 'Inviter Address' }, type: 'text', required: true },
            { name: 'inviter_phone', label: { fr: 'Téléphone de l\'invitant', en: 'Inviter Phone' }, type: 'tel', required: false },
            { name: 'invitee_name', label: { fr: 'Nom de l\'invité', en: 'Invitee Name' }, type: 'text', required: true },
            { name: 'relationship', label: { fr: 'Lien de parenté', en: 'Relationship' }, type: 'text', required: false },
            { name: 'purpose_of_visit', label: { fr: 'Motif de la visite', en: 'Purpose of Visit' }, type: 'text', required: true },
            { name: 'duration_of_stay', label: { fr: 'Durée du séjour', en: 'Duration of Stay' }, type: 'text', required: false },
            { name: 'start_date', label: { fr: 'Date de début', en: 'Start Date' }, type: 'date', required: false },
            { name: 'end_date', label: { fr: 'Date de fin', en: 'End Date' }, type: 'date', required: false }
        ]
    },

    [DocumentTypes.VERBAL_NOTE]: {
        title: { fr: 'Note verbale', en: 'Verbal Note' },
        fields: [
            { name: 'issuing_ministry', label: { fr: 'Ministère émetteur', en: 'Issuing Ministry' }, type: 'text', required: true },
            { name: 'note_number', label: { fr: 'Numéro de la note', en: 'Note Number' }, type: 'text', required: true },
            { name: 'note_date', label: { fr: 'Date de la note', en: 'Note Date' }, type: 'date', required: true },
            { name: 'diplomat_name', label: { fr: 'Nom du diplomate', en: 'Diplomat Name' }, type: 'text', required: true },
            { name: 'diplomat_title', label: { fr: 'Titre/Fonction', en: 'Title/Position' }, type: 'text', required: false },
            { name: 'mission_purpose', label: { fr: 'Objet de la mission', en: 'Mission Purpose' }, type: 'textarea', required: true },
            { name: 'requested_visa_validity', label: { fr: 'Validité du visa demandée', en: 'Requested Visa Validity' }, type: 'text', required: false }
        ]
    },

    [DocumentTypes.RESIDENCE_CARD]: {
        title: { fr: 'Carte de résidence', en: 'Residence Card' },
        fields: [
            { name: 'holder_name', label: { fr: 'Nom du titulaire', en: 'Holder Name' }, type: 'text', required: true },
            { name: 'card_number', label: { fr: 'Numéro de carte', en: 'Card Number' }, type: 'text', required: true },
            { name: 'nationality', label: { fr: 'Nationalité', en: 'Nationality' }, type: 'text', required: true },
            { name: 'residence_country', label: { fr: 'Pays de résidence', en: 'Country of Residence' }, type: 'text', required: true },
            { name: 'issue_date', label: { fr: 'Date d\'émission', en: 'Issue Date' }, type: 'date', required: true },
            { name: 'expiry_date', label: { fr: 'Date d\'expiration', en: 'Expiry Date' }, type: 'date', required: true },
            { name: 'card_type', label: { fr: 'Type de carte', en: 'Card Type' }, type: 'select', options: [
                { value: 'permanent', label: { fr: 'Permanent', en: 'Permanent' } },
                { value: 'temporary', label: { fr: 'Temporaire', en: 'Temporary' } },
                { value: 'student', label: { fr: 'Étudiant', en: 'Student' } },
                { value: 'work', label: { fr: 'Travail', en: 'Work' } }
            ], required: false },
            { name: 'address', label: { fr: 'Adresse', en: 'Address' }, type: 'text', required: false }
        ]
    },

    [DocumentTypes.PHOTO]: {
        title: { fr: 'Photo d\'identité', en: 'ID Photo' },
        fields: [
            // Photo requires re-upload, no manual fields
        ]
    },

    [DocumentTypes.PAYMENT_PROOF]: {
        title: { fr: 'Preuve de paiement', en: 'Payment Proof' },
        fields: [
            { name: 'amount', label: { fr: 'Montant (XOF)', en: 'Amount (XOF)' }, type: 'number', required: true },
            { name: 'payment_date', label: { fr: 'Date de paiement', en: 'Payment Date' }, type: 'date', required: true },
            { name: 'transaction_reference', label: { fr: 'Référence de transaction', en: 'Transaction Reference' }, type: 'text', required: true },
            { name: 'payment_method', label: { fr: 'Mode de paiement', en: 'Payment Method' }, type: 'text', required: false },
            { name: 'payer_name', label: { fr: 'Nom du payeur', en: 'Payer Name' }, type: 'text', required: false }
        ]
    }
};

/**
 * Image quality tips for re-scan
 */
const RESCAN_TIPS = {
    fr: [
        'Assurez-vous d\'avoir une bonne luminosité',
        'Évitez les reflets et les ombres',
        'Placez le document sur une surface plane',
        'Cadrez le document entièrement dans l\'image',
        'Assurez-vous que le texte est bien net et lisible',
        'Utilisez la fonction de scanner si disponible'
    ],
    en: [
        'Ensure good lighting conditions',
        'Avoid reflections and shadows',
        'Place the document on a flat surface',
        'Frame the entire document in the image',
        'Make sure the text is sharp and readable',
        'Use the scanner function if available'
    ]
};

// =============================================================================
// OCR FALLBACK CLASS
// =============================================================================

export class OcrFallback {
    constructor(options = {}) {
        this.language = options.language || 'fr';
        this.container = options.container || document.body;
        this.onComplete = options.onComplete || (() => {});
        this.onCancel = options.onCancel || (() => {});
        this.currentModal = null;
    }

    /**
     * Show fallback options when OCR fails
     * @param {string} documentType - Type of document
     * @param {Object} partialData - Partial OCR data if any
     * @param {number} confidence - OCR confidence score (0-1)
     * @param {string} errorMessage - Error message if OCR failed completely
     * @returns {Promise<Object>} - Resolved with user-entered data
     */
    async showFallback(documentType, partialData = null, confidence = 0, errorMessage = null) {
        return new Promise((resolve, reject) => {
            this.createModal(documentType, partialData, confidence, errorMessage, resolve, reject);
        });
    }

    /**
     * Create and show the fallback modal
     */
    createModal(documentType, partialData, confidence, errorMessage, resolve, reject) {
        // Remove existing modal if any
        this.closeModal();

        const docConfig = DOCUMENT_FIELDS[documentType];
        if (!docConfig) {
            reject(new Error(`Unknown document type: ${documentType}`));
            return;
        }

        const modal = document.createElement('div');
        modal.className = 'ocr-fallback-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'ocr-fallback-title');

        const isPartialSuccess = partialData && confidence > 0.3;
        const headerText = this.getHeaderText(documentType, isPartialSuccess, errorMessage);

        modal.innerHTML = `
            <div class="ocr-fallback-overlay" data-action="close"></div>
            <div class="ocr-fallback-content">
                <header class="ocr-fallback-header">
                    <div class="ocr-fallback-icon ${isPartialSuccess ? 'icon--warning' : 'icon--error'}">
                        ${isPartialSuccess ? '⚠️' : '❌'}
                    </div>
                    <h2 id="ocr-fallback-title">${headerText.title}</h2>
                    <p>${headerText.description}</p>
                    ${confidence > 0 ? `<div class="ocr-confidence-badge">Confiance: ${Math.round(confidence * 100)}%</div>` : ''}
                </header>

                <div class="ocr-fallback-options">
                    <button class="ocr-option-btn ocr-option-btn--rescan" data-option="rescan">
                        <span class="option-icon">📷</span>
                        <span class="option-text">
                            <strong>${this.t('Réessayer avec une meilleure image', 'Retry with a better image')}</strong>
                            <small>${this.t('Suivez les conseils pour une meilleure qualité', 'Follow tips for better quality')}</small>
                        </span>
                    </button>

                    ${docConfig.fields.length > 0 ? `
                    <button class="ocr-option-btn ocr-option-btn--manual" data-option="manual">
                        <span class="option-icon">✏️</span>
                        <span class="option-text">
                            <strong>${this.t('Saisir manuellement', 'Enter manually')}</strong>
                            <small>${this.t('Remplir les informations à la main', 'Fill in the information by hand')}</small>
                        </span>
                    </button>
                    ` : ''}
                </div>

                <div class="ocr-fallback-panel ocr-panel-rescan hidden">
                    <h3>${this.t('Conseils pour une meilleure image', 'Tips for a better image')}</h3>
                    <ul class="rescan-tips">
                        ${RESCAN_TIPS[this.language].map(tip => `<li>${tip}</li>`).join('')}
                    </ul>
                    <div class="rescan-upload-zone">
                        <input type="file" id="rescan-file-input" accept="image/*,.pdf" hidden>
                        <label for="rescan-file-input" class="rescan-upload-label">
                            <span class="upload-icon">📄</span>
                            <span>${this.t('Cliquer pour télécharger', 'Click to upload')}</span>
                        </label>
                    </div>
                </div>

                <div class="ocr-fallback-panel ocr-panel-manual hidden">
                    <h3>${docConfig.title[this.language]} - ${this.t('Saisie manuelle', 'Manual Entry')}</h3>
                    <form class="manual-entry-form" novalidate>
                        ${this.renderFormFields(docConfig.fields, partialData)}
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">
                                ${this.t('Valider', 'Validate')}
                            </button>
                        </div>
                    </form>
                </div>

                <footer class="ocr-fallback-footer">
                    <button class="btn-secondary" data-action="cancel">
                        ${this.t('Annuler', 'Cancel')}
                    </button>
                </footer>
            </div>
        `;

        // Add event listeners
        this.attachEventListeners(modal, documentType, resolve, reject);

        // Append to container
        this.container.appendChild(modal);
        this.currentModal = modal;

        // Focus trap
        requestAnimationFrame(() => {
            modal.querySelector('.ocr-option-btn--rescan').focus();
        });
    }

    /**
     * Get header text based on OCR result
     */
    getHeaderText(documentType, isPartialSuccess, errorMessage) {
        const docConfig = DOCUMENT_FIELDS[documentType];
        const docName = docConfig ? docConfig.title[this.language] : documentType;

        if (errorMessage) {
            return {
                title: this.t('Échec de la lecture', 'Reading failed'),
                description: this.t(
                    `Nous n'avons pas pu lire votre ${docName}. ${errorMessage}`,
                    `We couldn't read your ${docName}. ${errorMessage}`
                )
            };
        }

        if (isPartialSuccess) {
            return {
                title: this.t('Lecture partielle', 'Partial reading'),
                description: this.t(
                    `Certaines informations de votre ${docName} n'ont pas pu être lues correctement.`,
                    `Some information from your ${docName} couldn't be read correctly.`
                )
            };
        }

        return {
            title: this.t('Qualité insuffisante', 'Insufficient quality'),
            description: this.t(
                `L'image de votre ${docName} n'est pas assez claire pour être lue automatiquement.`,
                `The image of your ${docName} is not clear enough to be read automatically.`
            )
        };
    }

    /**
     * Render form fields for manual entry
     */
    renderFormFields(fields, prefillData = {}) {
        if (!fields || fields.length === 0) {
            return `<p class="no-fields-message">${this.t(
                'Ce document ne peut pas être saisi manuellement. Veuillez télécharger une meilleure image.',
                'This document cannot be entered manually. Please upload a better image.'
            )}</p>`;
        }

        return fields.map(field => {
            const value = prefillData && prefillData[field.name] ? prefillData[field.name] : '';
            const label = field.label[this.language] || field.label.fr;
            const requiredMark = field.required ? '<span class="required-mark">*</span>' : '';

            let inputHtml = '';

            switch (field.type) {
                case 'textarea':
                    inputHtml = `
                        <textarea
                            id="field-${field.name}"
                            name="${field.name}"
                            ${field.required ? 'required' : ''}
                            rows="3"
                        >${this.escapeHtml(value)}</textarea>
                    `;
                    break;

                case 'select':
                    const options = field.options.map(opt => {
                        const optValue = typeof opt === 'object' ? opt.value : opt;
                        const optLabel = typeof opt === 'object' ? (opt.label[this.language] || opt.label.fr) : opt;
                        const selected = value === optValue ? 'selected' : '';
                        return `<option value="${optValue}" ${selected}>${optLabel}</option>`;
                    }).join('');
                    inputHtml = `
                        <select
                            id="field-${field.name}"
                            name="${field.name}"
                            ${field.required ? 'required' : ''}
                        >
                            <option value="">${this.t('Sélectionner...', 'Select...')}</option>
                            ${options}
                        </select>
                    `;
                    break;

                default:
                    inputHtml = `
                        <input
                            type="${field.type}"
                            id="field-${field.name}"
                            name="${field.name}"
                            value="${this.escapeHtml(value)}"
                            ${field.required ? 'required' : ''}
                            ${field.placeholder ? `placeholder="${field.placeholder}"` : ''}
                        >
                    `;
            }

            return `
                <div class="form-field ${field.required ? 'field-required' : ''}">
                    <label for="field-${field.name}">${label}${requiredMark}</label>
                    ${inputHtml}
                </div>
            `;
        }).join('');
    }

    /**
     * Attach event listeners to modal
     */
    attachEventListeners(modal, documentType, resolve, reject) {
        // Close on overlay click
        modal.querySelector('.ocr-fallback-overlay').addEventListener('click', () => {
            this.closeModal();
            reject(new Error('User cancelled'));
        });

        // Cancel button
        modal.querySelector('[data-action="cancel"]').addEventListener('click', () => {
            this.closeModal();
            reject(new Error('User cancelled'));
        });

        // Option buttons
        const rescanBtn = modal.querySelector('[data-option="rescan"]');
        const manualBtn = modal.querySelector('[data-option="manual"]');
        const rescanPanel = modal.querySelector('.ocr-panel-rescan');
        const manualPanel = modal.querySelector('.ocr-panel-manual');

        rescanBtn.addEventListener('click', () => {
            rescanBtn.classList.add('active');
            manualBtn?.classList.remove('active');
            rescanPanel.classList.remove('hidden');
            manualPanel?.classList.add('hidden');
        });

        manualBtn?.addEventListener('click', () => {
            manualBtn.classList.add('active');
            rescanBtn.classList.remove('active');
            manualPanel.classList.remove('hidden');
            rescanPanel.classList.add('hidden');
            // Focus first field
            const firstInput = manualPanel.querySelector('input, select, textarea');
            if (firstInput) firstInput.focus();
        });

        // File input for rescan
        const fileInput = modal.querySelector('#rescan-file-input');
        fileInput?.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (file) {
                this.closeModal();
                resolve({
                    action: FallbackOption.RESCAN,
                    file: file,
                    documentType: documentType
                });
            }
        });

        // Manual form submission
        const form = modal.querySelector('.manual-entry-form');
        form?.addEventListener('submit', (e) => {
            e.preventDefault();

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const formData = new FormData(form);
            const data = {};
            for (const [key, value] of formData.entries()) {
                data[key] = value;
            }

            this.closeModal();
            resolve({
                action: FallbackOption.MANUAL,
                data: data,
                documentType: documentType,
                source: 'manual_entry'
            });
        });

        // Escape key to close
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                this.closeModal();
                reject(new Error('User cancelled'));
                document.removeEventListener('keydown', handleEscape);
            }
        };
        document.addEventListener('keydown', handleEscape);
    }

    /**
     * Close the modal
     */
    closeModal() {
        if (this.currentModal) {
            this.currentModal.classList.add('closing');
            setTimeout(() => {
                this.currentModal?.remove();
                this.currentModal = null;
            }, 200);
        }
    }

    /**
     * Translation helper
     */
    t(fr, en) {
        return this.language === 'fr' ? fr : en;
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Set language
     */
    setLanguage(lang) {
        this.language = lang === 'en' ? 'en' : 'fr';
    }
}

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

/**
 * Check if OCR result needs fallback
 * @param {Object} ocrResult - OCR result object
 * @returns {boolean}
 */
export function needsFallback(ocrResult) {
    if (!ocrResult) return true;
    if (!ocrResult.success) return true;
    if (ocrResult.confidence !== undefined && ocrResult.confidence < OCR_CONFIDENCE_THRESHOLD) return true;
    if (!ocrResult.data || Object.keys(ocrResult.data).length === 0) return true;
    return false;
}

/**
 * Get partial data from failed OCR result
 * @param {Object} ocrResult - OCR result object
 * @returns {Object|null}
 */
export function getPartialData(ocrResult) {
    if (!ocrResult || !ocrResult.data) return null;

    // Filter out empty or null values
    const partialData = {};
    for (const [key, value] of Object.entries(ocrResult.data)) {
        if (value && value !== '' && value !== null) {
            partialData[key] = value;
        }
    }

    return Object.keys(partialData).length > 0 ? partialData : null;
}

/**
 * Merge OCR data with manual corrections
 * @param {Object} ocrData - Original OCR data
 * @param {Object} manualData - Manual corrections
 * @returns {Object}
 */
export function mergeData(ocrData, manualData) {
    return {
        ...ocrData,
        ...manualData,
        _source: 'merged',
        _manual_fields: Object.keys(manualData)
    };
}

// =============================================================================
// SINGLETON EXPORT
// =============================================================================

export const ocrFallback = new OcrFallback();

export default ocrFallback;
