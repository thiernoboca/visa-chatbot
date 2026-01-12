/**
 * Inline Editing Manager - Redesign Integration
 *
 * Manages the hybrid inline editing flow:
 * 1. Display extracted data in chat
 * 2. Show "Oui, c'est correct" / "Non, modifier" buttons
 * 3. If "Oui": proceed to next step
 * 4. If "Non": open glassmorphism modal for editing
 *
 * @version 1.0.0
 * @module InlineEditing
 */

import { CONFIG } from './config.js';
import stateManager from './state.js';

/**
 * Field labels for different document types
 */
const FIELD_LABELS = {
    passport: {
        fr: {
            surname: 'Nom',
            given_names: 'Prénoms',
            date_of_birth: 'Date de naissance',
            place_of_birth: 'Lieu de naissance',
            nationality: 'Nationalité',
            passport_number: 'N° Passeport',
            date_of_expiry: 'Date d\'expiration',
            date_of_issue: 'Date de délivrance',
            sex: 'Sexe',
            passport_type: 'Type de passeport',
            country: 'Pays émetteur',
            issuing_country: 'Pays émetteur',
            issuing_authority: 'Autorité de délivrance',
            mrz: 'MRZ'
        },
        en: {
            surname: 'Surname',
            given_names: 'Given names',
            date_of_birth: 'Date of birth',
            place_of_birth: 'Place of birth',
            nationality: 'Nationality',
            passport_number: 'Passport No.',
            date_of_expiry: 'Expiry date',
            date_of_issue: 'Date of issue',
            sex: 'Sex',
            passport_type: 'Passport type',
            country: 'Issuing country',
            issuing_country: 'Issuing country',
            issuing_authority: 'Issuing authority',
            mrz: 'MRZ'
        }
    },
    ticket: {
        fr: {
            airline: 'Compagnie aérienne',
            flight_number: 'N° de vol',
            departure_date: 'Date de départ',
            departure_airport: 'Aéroport de départ',
            arrival_airport: 'Aéroport d\'arrivée',
            passenger_name: 'Nom du passager',
            booking_reference: 'Référence de réservation'
        },
        en: {
            airline: 'Airline',
            flight_number: 'Flight number',
            departure_date: 'Departure date',
            departure_airport: 'Departure airport',
            arrival_airport: 'Arrival airport',
            passenger_name: 'Passenger name',
            booking_reference: 'Booking reference'
        }
    },
    hotel: {
        fr: {
            hotel_name: 'Nom de l\'hôtel',
            check_in_date: 'Date d\'arrivée',
            check_out_date: 'Date de départ',
            guest_name: 'Nom du client',
            confirmation_number: 'N° de confirmation',
            address: 'Adresse'
        },
        en: {
            hotel_name: 'Hotel name',
            check_in_date: 'Check-in date',
            check_out_date: 'Check-out date',
            guest_name: 'Guest name',
            confirmation_number: 'Confirmation number',
            address: 'Address'
        }
    }
};

/**
 * Critical fields that should always be displayed even if empty
 */
const CRITICAL_FIELDS = {
    passport: ['passport_number', 'surname', 'given_names', 'date_of_birth', 'nationality'],
    ticket: ['flight_number', 'departure_date', 'passenger_name'],
    hotel: ['hotel_name', 'check_in_date', 'check_out_date', 'guest_name']
};

/**
 * Confidence threshold for warnings
 */
const LOW_CONFIDENCE_THRESHOLD = 0.7;

export class InlineEditingManager {
    /**
     * Constructor
     * @param {Object} options Configuration options
     */
    constructor(options = {}) {
        this.messagesManager = options.messagesManager;
        this.uiManager = options.uiManager;
        this.apiManager = options.apiManager;
        this.onConfirm = options.onConfirm || (() => {});
        this.onEdit = options.onEdit || (() => {});

        this.currentData = null;
        this.currentDocType = null;

        this.log('InlineEditingManager initialized');
    }

    /**
     * Log helper
     */
    log(...args) {
        if (CONFIG.defaults.debug) {
            console.log('[InlineEditing]', ...args);
        }
    }

    /**
     * Show inline confirmation flow
     * @param {Object} extractedData OCR extracted data
     * @param {string} docType Document type (passport, ticket, hotel, etc.)
     */
    showInlineConfirmation(extractedData, docType) {
        this.log('Showing inline confirmation', { docType, extractedData });

        this.currentData = extractedData;
        this.currentDocType = docType;

        // Get current language
        const lang = stateManager.get('language') || CONFIG.defaults.language;

        // Build extracted data HTML
        const dataHtml = this.buildDataDisplay(extractedData, docType, lang);

        // Get success message
        const successMessages = {
            passport: { fr: '✅ Passeport lu avec succès !', en: '✅ Passport read successfully!' },
            ticket: { fr: '✅ Billet d\'avion lu avec succès !', en: '✅ Flight ticket read successfully!' },
            hotel: { fr: '✅ Réservation d\'hôtel lue avec succès !', en: '✅ Hotel reservation read successfully!' },
            vaccination: { fr: '✅ Carnet vaccinal lu avec succès !', en: '✅ Vaccination card read successfully!' }
        };

        const defaultSuccess = { fr: '✅ Document lu avec succès !', en: '✅ Document read successfully!' };
        const successMsg = (successMessages[docType] || defaultSuccess)[lang];

        const confirmQuestion = lang === 'fr'
            ? 'Ces informations sont-elles correctes ?'
            : 'Is this information correct?';

        // Add bot message with extracted data
        const messageHtml = `
            <strong>${successMsg}</strong>
            <div class="extracted-data-message">
                ${dataHtml}
            </div>
            <p class="mt-4 font-medium">${confirmQuestion}</p>
        `;

        this.messagesManager.addBotMessage(messageHtml);

        // Add confirmation buttons
        this.addConfirmationButtons(lang);
    }

    /**
     * Build HTML display for extracted data
     * @param {Object} extractedData Extracted data
     * @param {string} docType Document type
     * @param {string} lang Language code
     * @returns {string} HTML string
     */
    buildDataDisplay(extractedData, docType, lang) {
        const fields = extractedData.fields || extractedData;
        const labels = (FIELD_LABELS[docType] || FIELD_LABELS.passport)[lang];
        const criticalFields = CRITICAL_FIELDS[docType] || CRITICAL_FIELDS.passport;

        let html = '';

        Object.keys(fields).forEach(key => {
            const field = fields[key];

            // Extract value and confidence
            let value = '';
            let confidence = 1.0;

            if (field && typeof field === 'object' && field.value !== undefined) {
                value = String(field.value || '');
                confidence = field.confidence || 1.0;
            } else if (field && (typeof field === 'string' || typeof field === 'number')) {
                value = String(field);
            }

            // Determine if should display
            const shouldDisplay = value.trim() !== '' || criticalFields.includes(key);

            if (shouldDisplay) {
                const displayValue = value.trim() !== '' ? value : '-';
                const lowConfidence = confidence < LOW_CONFIDENCE_THRESHOLD;
                const fieldClass = lowConfidence ? 'extracted-field low-confidence' : 'extracted-field';

                html += `
                    <div class="${fieldClass}" data-field="${key}">
                        <span class="extracted-field-label">${labels[key] || key}</span>
                        <span class="extracted-field-value">${displayValue}</span>
                    </div>
                `;
            }
        });

        return html;
    }

    /**
     * Add confirmation buttons based on confirmationMode config
     * @param {string} lang Language code
     */
    addConfirmationButtons(lang) {
        const mode = CONFIG.features?.inlineEditing?.confirmationMode || 'inline';

        this.log('Adding confirmation buttons with mode:', mode);

        if (mode === 'actionArea') {
            this.addButtonsToActionArea(lang);
        } else {
            this.addButtonsToChat(lang);
        }
    }

    /**
     * Add confirmation buttons to dedicated action area (like redesign version)
     * @param {string} lang Language code
     */
    addButtonsToActionArea(lang) {
        const yesText = lang === 'fr' ? 'Oui, c\'est correct' : 'Yes, it\'s correct';
        const noText = lang === 'fr' ? 'Non, modifier' : 'No, edit';

        // Find action area container
        console.log('[InlineEditing] Looking for action area...');
        console.log('[InlineEditing] message-action-area:', !!document.getElementById('message-action-area'));
        console.log('[InlineEditing] action-area:', !!document.getElementById('action-area'));
        console.log('[InlineEditing] quickActions:', !!document.getElementById('quickActions'));

        const actionArea = document.getElementById('message-action-area') ||
                          document.getElementById('action-area') ||
                          document.getElementById('quickActions') ||
                          document.getElementById('input-area');

        console.log('[InlineEditing] Found action area:', actionArea?.id);

        if (!actionArea) {
            this.log('Warning: action area not found, falling back to chat buttons');
            this.addButtonsToChat(lang);
            return;
        }

        // Set flag to prevent quick actions from overwriting our buttons
        stateManager.set('inlineEditingActive', true);

        // Use setTimeout to ensure buttons are added AFTER any pending quick actions
        setTimeout(() => {
            // Clear existing content and add buttons
            actionArea.innerHTML = `
                <div class="inline-confirmation-buttons flex gap-3">
                    <button class="btn-confirm-data btn-premium flex-1 py-3 rounded-xl font-semibold text-white shadow-glow flex items-center justify-center gap-2" data-action="confirm">
                        <span class="material-symbols-outlined">check</span>
                        ${yesText}
                    </button>
                    <button class="btn-edit-data btn-secondary flex-1 py-3 rounded-xl font-semibold flex items-center justify-center gap-2" data-action="edit">
                        <span class="material-symbols-outlined">edit</span>
                        ${noText}
                    </button>
                </div>
            `;

            // Attach event listeners to action area
            this.attachActionAreaListeners(actionArea);

            this.log('Inline confirmation buttons added to action area');
        }, 100);
    }

    /**
     * Add confirmation buttons to chat messages (original behavior)
     * @param {string} lang Language code
     */
    addButtonsToChat(lang) {
        const yesText = lang === 'fr' ? 'Oui, c\'est correct' : 'Yes, it\'s correct';
        const noText = lang === 'fr' ? 'Non, modifier' : 'No, edit';

        const buttonsHtml = `
            <div class="inline-confirmation-buttons">
                <button class="btn-confirm-data btn-premium" data-action="confirm">
                    <span class="material-symbols-outlined">check</span>
                    ${yesText}
                </button>
                <button class="btn-edit-data btn-secondary" data-action="edit">
                    <span class="material-symbols-outlined">edit</span>
                    ${noText}
                </button>
            </div>
        `;

        // Use messagesManager to add buttons in chat
        this.messagesManager.addActionButtons(buttonsHtml);

        // Attach event listeners
        this.attachButtonListeners();
    }

    /**
     * Attach event listeners to action area buttons
     * @param {HTMLElement} actionArea Action area container
     */
    attachActionAreaListeners(actionArea) {
        console.log('[InlineEditing] Attaching listeners to:', actionArea?.id);
        actionArea.addEventListener('click', (event) => {
            console.log('[InlineEditing] Click event on action area, target:', event.target.tagName);
            const button = event.target.closest('[data-action]');
            console.log('[InlineEditing] Button found:', button?.dataset?.action);
            if (!button) return;

            const action = button.dataset.action;
            console.log('[InlineEditing] Action:', action);

            if (action === 'confirm') {
                console.log('[InlineEditing] Calling handleConfirm');
                this.handleConfirm();
            } else if (action === 'edit') {
                console.log('[InlineEditing] Calling handleEdit');
                this.handleEdit();
            }
        });
    }

    /**
     * Attach click event listeners to confirmation buttons
     */
    attachButtonListeners() {
        // Use event delegation on chat messages container
        const container = document.getElementById('chat-messages');
        if (!container) {
            this.log('Warning: chat-messages container not found');
            return;
        }

        // Remove previous listeners to avoid duplicates
        container.removeEventListener('click', this.handleButtonClick);

        // Add new listener
        container.addEventListener('click', this.handleButtonClick.bind(this));
    }

    /**
     * Handle button click event
     * @param {Event} event Click event
     */
    handleButtonClick(event) {
        const button = event.target.closest('[data-action]');
        if (!button) return;

        const action = button.dataset.action;

        if (action === 'confirm') {
            this.handleConfirm();
        } else if (action === 'edit') {
            this.handleEdit();
        }
    }

    /**
     * Handle "Oui, c'est correct" click
     */
    handleConfirm() {
        this.log('Data confirmed by user');

        const lang = stateManager.get('language') || CONFIG.defaults.language;

        // Clear inline editing flag
        stateManager.set('inlineEditingActive', false);

        // Add user message
        const userMsg = lang === 'fr' ? '✓ Données confirmées' : '✓ Data confirmed';
        this.messagesManager.addUserMessage(userMsg);

        // Clear action buttons
        this.messagesManager.clearActionButtons();

        // Trigger callback
        if (this.onConfirm && typeof this.onConfirm === 'function') {
            this.onConfirm(this.currentData, this.currentDocType);
        }
    }

    /**
     * Handle "Non, modifier" click
     */
    handleEdit() {
        this.log('Edit requested by user');

        const lang = stateManager.get('language') || CONFIG.defaults.language;
        const editMode = CONFIG.features?.inlineEditing?.editMode || 'modal';

        console.log('[InlineEditing handleEdit] editMode:', editMode);
        console.log('[InlineEditing handleEdit] lang:', lang);

        // Clear inline editing flag
        stateManager.set('inlineEditingActive', false);

        // Add user message
        const userMsg = lang === 'fr' ? '✏️ Modifier les données' : '✏️ Edit data';
        this.messagesManager.addUserMessage(userMsg);

        // Clear action buttons
        this.messagesManager.clearActionButtons();

        // Check edit mode from config
        if (editMode === 'inline') {
            console.log('[InlineEditing handleEdit] Using inline form');
            // Use inline chat form (like redesign version)
            this.showInlineEditForm(lang);
        } else {
            console.log('[InlineEditing handleEdit] Using modal');
            // Use modal (original behavior)
            this.showModalEdit(lang);
        }
    }

    /**
     * Show inline edit form in chat (redesign-style)
     * @param {string} lang Language code
     */
    showInlineEditForm(lang) {
        this.log('Showing inline edit form');
        console.log('[InlineEditing showInlineEditForm] currentData:', this.currentData);
        console.log('[InlineEditing showInlineEditForm] currentDocType:', this.currentDocType);

        try {
        const formManager = new InlineChatFormManager({
            messagesManager: this.messagesManager,
            onSave: (mergedData, docType) => {
                this.log('Inline edit saved', mergedData);

                // Add confirmation message
                const savedMsg = lang === 'fr' ? '✓ Modifications enregistrées' : '✓ Changes saved';
                this.messagesManager.addUserMessage(savedMsg);

                // Trigger confirm callback with merged data
                if (this.onConfirm && typeof this.onConfirm === 'function') {
                    this.onConfirm(mergedData, docType);
                }
            },
            onCancel: () => {
                this.log('Inline edit cancelled');
                // Show confirmation buttons again
                this.showInlineConfirmation(this.currentData, this.currentDocType);
            }
        });

        formManager.renderEditForm(this.currentData, this.currentDocType, lang);
        } catch (error) {
            console.error('[InlineEditing showInlineEditForm] Error:', error);
            this.log('Error showing inline edit form:', error);
            // Fallback to modal if inline form fails
            this.showModalEdit(lang);
        }
    }

    /**
     * Show modal for editing (original behavior)
     * @param {string} lang Language code
     */
    showModalEdit(lang) {
        // Trigger edit callback - let the chatbot handle modal initialization
        if (this.onEdit && typeof this.onEdit === 'function') {
            this.onEdit(this.currentData, this.currentDocType, {
                onConfirm: (editedData) => {
                    this.log('Data edited and confirmed', editedData);
                    // Merge edited data with original
                    const mergedData = { ...this.currentData, ...editedData };

                    // Trigger confirm callback
                    if (this.onConfirm && typeof this.onConfirm === 'function') {
                        this.onConfirm(mergedData, this.currentDocType);
                    }
                },
                onCancel: () => {
                    this.log('Edit cancelled');
                    // Show confirmation buttons again
                    this.addConfirmationButtons(lang);
                }
            });
        } else {
            this.log('Warning: onEdit callback not available');
        }
    }

    /**
     * Reset state
     */
    reset() {
        this.currentData = null;
        this.currentDocType = null;
        this.messagesManager.clearActionButtons();
    }
}

/**
 * Inline Chat Form Manager - Renders edit forms directly in chat
 * Ported from chatbot-redesign.js handleDataEdit() function
 *
 * @class InlineChatFormManager
 */
export class InlineChatFormManager {
    /**
     * Constructor
     * @param {Object} options Configuration options
     */
    constructor(options = {}) {
        this.messagesManager = options.messagesManager;
        this.onSave = options.onSave || (() => {});
        this.onCancel = options.onCancel || (() => {});
        this.currentData = null;
        this.currentDocType = null;
    }

    /**
     * Log helper
     */
    log(...args) {
        if (CONFIG.defaults.debug) {
            console.log('[InlineChatForm]', ...args);
        }
    }

    /**
     * Render edit form directly in chat
     * @param {Object} extractedData Extracted OCR data
     * @param {string} docType Document type
     * @param {string} lang Language code
     */
    renderEditForm(extractedData, docType, lang) {
        this.log('Rendering inline edit form', { docType, extractedData });

        this.currentData = extractedData;
        this.currentDocType = docType;

        const fields = extractedData.fields || extractedData;
        const labels = (FIELD_LABELS[docType] || FIELD_LABELS.passport)[lang];
        const editableFields = this.getEditableFields(docType);

        // Build form HTML
        let formHtml = '<div class="inline-edit-form space-y-3">';

        editableFields.forEach(key => {
            const field = fields[key];
            let value = '';

            if (field && typeof field === 'object' && field.value !== undefined) {
                value = field.value || '';
            } else if (field && (typeof field === 'string' || typeof field === 'number')) {
                value = String(field);
            }

            const inputType = key.includes('date') ? 'date' : 'text';
            const label = labels[key] || key;

            formHtml += `
                <div class="form-field flex flex-col gap-1">
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">${label}</label>
                    <input type="${inputType}"
                           name="${key}"
                           value="${this.escapeHtml(value)}"
                           class="inline-edit-input clean-input px-3 py-2 rounded-lg text-sm border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                           data-field="${key}">
                </div>
            `;
        });

        formHtml += '</div>';

        // Add edit message header
        const editMsg = lang === 'fr'
            ? 'Modifiez les informations ci-dessous puis validez :'
            : 'Edit the information below then validate:';

        this.messagesManager.addBotMessage(`<strong>${editMsg}</strong>${formHtml}`);

        // Add save/cancel buttons to action area
        this.addFormButtons(lang);
    }

    /**
     * Get list of editable fields for document type
     * @param {string} docType Document type
     * @returns {string[]} Array of field names
     */
    getEditableFields(docType) {
        const fieldsMap = {
            passport: ['surname', 'given_names', 'date_of_birth', 'nationality', 'passport_number', 'date_of_expiry', 'sex'],
            ticket: ['airline', 'flight_number', 'departure_date', 'departure_airport', 'arrival_airport', 'passenger_name'],
            hotel: ['hotel_name', 'check_in_date', 'check_out_date', 'guest_name', 'confirmation_number'],
            vaccination: ['vaccine_type', 'vaccination_date', 'patient_name', 'batch_number']
        };
        return fieldsMap[docType] || fieldsMap.passport;
    }

    /**
     * Add save/cancel buttons to action area
     * @param {string} lang Language code
     */
    addFormButtons(lang) {
        const saveText = lang === 'fr' ? 'Enregistrer les modifications' : 'Save changes';
        const cancelText = lang === 'fr' ? 'Annuler' : 'Cancel';

        const actionArea = document.getElementById('message-action-area') ||
                          document.getElementById('action-area') ||
                          document.getElementById('quickActions') ||
                          document.getElementById('input-area');

        if (!actionArea) {
            this.log('Warning: action area not found for form buttons');
            return;
        }

        actionArea.innerHTML = `
            <div class="inline-edit-buttons flex gap-3 p-4">
                <button id="btn-save-edit" class="btn-premium flex-1 py-3 rounded-xl font-semibold text-white shadow-glow flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">save</span>
                    ${saveText}
                </button>
                <button id="btn-cancel-edit" class="btn-secondary flex-1 py-3 rounded-xl font-semibold flex items-center justify-center gap-2 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <span class="material-symbols-outlined">close</span>
                    ${cancelText}
                </button>
            </div>
        `;

        // Attach event listeners
        document.getElementById('btn-save-edit')?.addEventListener('click', () => this.handleSave());
        document.getElementById('btn-cancel-edit')?.addEventListener('click', () => this.handleCancel());
    }

    /**
     * Handle save button click
     */
    handleSave() {
        this.log('Saving edited data');

        // Collect form values
        const editedData = this.collectFormData();

        // Merge with original data
        const mergedData = this.mergeData(this.currentData, editedData);

        // Trigger callback
        if (this.onSave && typeof this.onSave === 'function') {
            this.onSave(mergedData, this.currentDocType);
        }
    }

    /**
     * Handle cancel button click
     */
    handleCancel() {
        this.log('Edit cancelled');

        // Trigger callback
        if (this.onCancel && typeof this.onCancel === 'function') {
            this.onCancel();
        }
    }

    /**
     * Collect data from form inputs
     * @returns {Object} Collected form data
     */
    collectFormData() {
        const inputs = document.querySelectorAll('[data-field]');
        const data = {};

        inputs.forEach(input => {
            const fieldName = input.dataset.field;
            data[fieldName] = input.value;
        });

        return data;
    }

    /**
     * Merge edited data with original data
     * @param {Object} original Original extracted data
     * @param {Object} edited Edited field values
     * @returns {Object} Merged data
     */
    mergeData(original, edited) {
        const merged = JSON.parse(JSON.stringify(original));
        const fields = merged.fields || merged;

        for (const [key, value] of Object.entries(edited)) {
            if (fields[key]) {
                if (typeof fields[key] === 'object' && fields[key].value !== undefined) {
                    fields[key].value = value;
                    fields[key].confidence = 1.0; // User-edited = high confidence
                } else {
                    fields[key] = value;
                }
            } else {
                fields[key] = { value: value, confidence: 1.0 };
            }
        }

        return merged;
    }

    /**
     * Escape HTML to prevent XSS
     * @param {string} str Input string
     * @returns {string} Escaped string
     */
    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

export default InlineEditingManager;
