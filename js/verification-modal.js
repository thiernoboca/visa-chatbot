/**
 * Verification Modal - Chatbot Visa CI
 * Modal de vérification des données extraites avec scores de confiance
 * Style macOS minimaliste
 * 
 * @version 1.0.0
 */

class VerificationModal {
    
    /**
     * Niveaux de confiance
     */
    static CONFIDENCE_LEVELS = {
        HIGH: { min: 0.9, class: 'confidence-high', label: 'Élevée' },
        MEDIUM: { min: 0.7, class: 'confidence-medium', label: 'Moyenne' },
        LOW: { min: 0, class: 'confidence-low', label: 'Faible' }
    };
    
    /**
     * Labels des champs par type de document
     */
    static FIELD_LABELS = {
        passport: {
            surname: 'Nom',
            given_names: 'Prénoms',
            date_of_birth: 'Date de naissance',
            place_of_birth: 'Lieu de naissance',
            sex: 'Sexe',
            nationality: 'Nationalité',
            passport_number: 'N° Passeport',
            date_of_issue: 'Date d\'émission',
            date_of_expiry: 'Date d\'expiration',
            issuing_authority: 'Autorité',
            place_of_issue: 'Lieu d\'émission'
        },
        ticket: {
            'airline.name': 'Compagnie',
            'flight.number': 'N° Vol',
            'departure.date': 'Date départ',
            'departure.time': 'Heure départ',
            'departure.airport.code': 'Aéroport départ',
            'arrival.date': 'Date arrivée',
            'arrival.time': 'Heure arrivée',
            'arrival.airport.code': 'Aéroport arrivée',
            'passenger.surname': 'Nom passager',
            'passenger.given_names': 'Prénoms passager',
            'booking.pnr': 'Code réservation'
        },
        hotel: {
            'hotel.name': 'Nom hôtel',
            'hotel.address.city': 'Ville',
            'reservation.confirmation_number': 'N° Réservation',
            'reservation.check_in.date': 'Date check-in',
            'reservation.check_out.date': 'Date check-out',
            'reservation.nights': 'Nuits',
            'guest.surname': 'Nom client',
            'guest.given_names': 'Prénoms client'
        },
        vaccination: {
            'holder.surname': 'Nom',
            'holder.given_names': 'Prénoms',
            'yellow_fever.vaccinated': 'Vacciné fièvre jaune',
            'yellow_fever.date_of_vaccination': 'Date vaccination',
            'yellow_fever.certificate_number': 'N° Certificat',
            'yellow_fever.valid_until': 'Valide jusqu\'à'
        }
    };
    
    /**
     * Constructeur
     * @param {Object} options Configuration
     */
    constructor(options = {}) {
        this.config = {
            onConfirm: options.onConfirm || (() => {}),
            onModify: options.onModify || (() => {}),
            onFieldEdit: options.onFieldEdit || (() => {}),
            debug: options.debug || false
        };
        
        this.extractedData = {};
        this.validations = [];
        this.editedFields = {};
        this.isOpen = false;
    }
    
    /**
     * Affiche le modal avec les données extraites
     * @param {Object} extractedData Données extraites par document
     * @param {Array} validations Résultats des validations croisées
     */
    open(extractedData, validations = []) {
        this.extractedData = extractedData;
        this.validations = validations;
        this.editedFields = {};
        
        // Créer le modal s'il n'existe pas
        let modal = document.getElementById('verificationModal');
        if (!modal) {
            modal = this.createModal();
            document.body.appendChild(modal);
        }
        
        // Mettre à jour le contenu
        modal.querySelector('.verification-content').innerHTML = this.renderContent();
        
        // Afficher le modal
        modal.hidden = false;
        this.isOpen = true;
        
        // Lier les événements
        this.bindEvents(modal);
        
        // Focus trap
        modal.querySelector('.btn-confirm')?.focus();
        
        this.log('Modal opened');
    }
    
    /**
     * Ferme le modal
     */
    close() {
        const modal = document.getElementById('verificationModal');
        if (modal) {
            modal.hidden = true;
        }
        this.isOpen = false;
        this.log('Modal closed');
    }
    
    /**
     * Crée la structure du modal
     */
    createModal() {
        const modal = document.createElement('div');
        modal.id = 'verificationModal';
        modal.className = 'verification-modal-overlay';
        modal.hidden = true;
        
        modal.innerHTML = `
            <div class="verification-modal" role="dialog" aria-modal="true" aria-labelledby="verificationTitle">
                <div class="verification-header">
                    <h2 id="verificationTitle">✅ Vérifiez les données extraites</h2>
                    <button type="button" class="btn-close-verification" aria-label="Fermer">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
                <div class="verification-content">
                    <!-- Contenu dynamique -->
                </div>
                <div class="verification-footer">
                    <button type="button" class="btn-modify">
                        ← Modifier documents
                    </button>
                    <button type="button" class="btn-confirm">
                        ✓ Valider →
                    </button>
                </div>
            </div>
        `;
        
        return modal;
    }
    
    /**
     * Génère le contenu du modal
     */
    renderContent() {
        return `
            ${this.renderValidationAlerts()}
            ${this.renderDocumentSections()}
        `;
    }
    
    /**
     * Génère les alertes de validation
     */
    renderValidationAlerts() {
        if (!this.validations || this.validations.length === 0) {
            return '';
        }
        
        const alertsHtml = this.validations.map(v => {
            const icon = v.type === 'success' ? '✓' : v.type === 'warning' ? '⚠' : '✗';
            return `
                <div class="validation-alert validation-${v.type}">
                    <span class="alert-icon">${icon}</span>
                    <span class="alert-message">${v.message}</span>
                </div>
            `;
        }).join('');
        
        return `
            <div class="validation-alerts">
                <h3 class="alerts-title">Vérifications automatiques</h3>
                ${alertsHtml}
            </div>
        `;
    }
    
    /**
     * Génère les sections par document
     */
    renderDocumentSections() {
        const sections = [];
        
        const documentConfigs = {
            passport: { title: '🛂 PASSEPORT', icon: '🛂' },
            ticket: { title: '✈️ BILLET D\'AVION', icon: '✈️' },
            hotel: { title: '🏨 RÉSERVATION HÔTEL', icon: '🏨' },
            vaccination: { title: '💉 CARNET VACCINAL', icon: '💉' }
        };
        
        for (const [type, config] of Object.entries(documentConfigs)) {
            if (this.extractedData[type]) {
                sections.push(this.renderDocumentSection(type, config.title, this.extractedData[type]));
            }
        }
        
        return sections.join('');
    }
    
    /**
     * Génère une section de document
     */
    renderDocumentSection(type, title, data) {
        const confidence = this.calculateAverageConfidence(type, data);
        const confidenceLevel = this.getConfidenceLevel(confidence);
        
        return `
            <div class="document-section" data-type="${type}">
                <div class="section-header">
                    <span class="section-title">${title}</span>
                    <span class="confidence-badge ${confidenceLevel.class}" title="Confiance ${confidenceLevel.label}">
                        ${Math.round(confidence * 100)}%
                    </span>
                </div>
                <div class="fields-grid">
                    ${this.renderFields(type, data)}
                </div>
            </div>
        `;
    }
    
    /**
     * Génère les champs d'un document
     */
    renderFields(type, data) {
        const labels = VerificationModal.FIELD_LABELS[type] || {};
        const fieldsHtml = [];
        
        // Pour les passeports, les champs sont dans data.fields
        if (type === 'passport' && data.fields) {
            for (const [key, field] of Object.entries(data.fields)) {
                if (field && field.value !== null && field.value !== undefined) {
                    const label = labels[key] || this.formatFieldName(key);
                    const confidence = field.confidence || 0;
                    const confidenceLevel = this.getConfidenceLevel(confidence);
                    
                    fieldsHtml.push(`
                        <div class="field-row" data-field="${key}">
                            <label class="field-label">${label}</label>
                            <div class="field-value-wrapper">
                                <input type="text" 
                                       class="field-input ${confidenceLevel.class}" 
                                       value="${this.escapeHtml(field.value)}"
                                       data-type="${type}"
                                       data-field="${key}"
                                       data-original="${this.escapeHtml(field.value)}">
                                <span class="field-confidence" title="Confiance: ${Math.round(confidence * 100)}%">
                                    ${Math.round(confidence * 100)}%
                                </span>
                            </div>
                        </div>
                    `);
                }
            }
        } else {
            // Pour les autres documents, parcourir récursivement
            this.extractFieldsRecursive(data, '', labels, fieldsHtml, type);
        }
        
        return fieldsHtml.join('');
    }
    
    /**
     * Extrait les champs récursivement
     */
    extractFieldsRecursive(obj, prefix, labels, fieldsHtml, type) {
        if (!obj || typeof obj !== 'object') return;
        
        for (const [key, value] of Object.entries(obj)) {
            if (key.startsWith('_') || key === 'confidence' || key === 'overall_confidence') continue;
            
            const fullKey = prefix ? `${prefix}.${key}` : key;
            
            if (value && typeof value === 'object' && !Array.isArray(value)) {
                // Si c'est un objet avec une valeur et une confiance
                if ('value' in value && 'confidence' in value) {
                    const label = labels[fullKey] || this.formatFieldName(key);
                    const confidence = value.confidence || 0;
                    const confidenceLevel = this.getConfidenceLevel(confidence);
                    
                    if (value.value !== null && value.value !== undefined) {
                        fieldsHtml.push(`
                            <div class="field-row" data-field="${fullKey}">
                                <label class="field-label">${label}</label>
                                <div class="field-value-wrapper">
                                    <input type="text" 
                                           class="field-input ${confidenceLevel.class}" 
                                           value="${this.escapeHtml(String(value.value))}"
                                           data-type="${type}"
                                           data-field="${fullKey}"
                                           data-original="${this.escapeHtml(String(value.value))}">
                                    <span class="field-confidence">${Math.round(confidence * 100)}%</span>
                                </div>
                            </div>
                        `);
                    }
                } else {
                    // Récursion
                    this.extractFieldsRecursive(value, fullKey, labels, fieldsHtml, type);
                }
            } else if (value !== null && value !== undefined && !Array.isArray(value)) {
                // Valeur simple
                const label = labels[fullKey] || this.formatFieldName(key);
                
                fieldsHtml.push(`
                    <div class="field-row" data-field="${fullKey}">
                        <label class="field-label">${label}</label>
                        <div class="field-value-wrapper">
                            <input type="text" 
                                   class="field-input" 
                                   value="${this.escapeHtml(String(value))}"
                                   data-type="${type}"
                                   data-field="${fullKey}"
                                   data-original="${this.escapeHtml(String(value))}">
                        </div>
                    </div>
                `);
            }
        }
    }
    
    /**
     * Calcule la confiance moyenne d'un document
     */
    calculateAverageConfidence(type, data) {
        let total = 0;
        let count = 0;
        
        // Confiance globale si disponible
        if (data.overall_confidence) {
            return data.overall_confidence;
        }
        
        // Sinon calculer depuis les champs
        if (type === 'passport' && data.fields) {
            for (const field of Object.values(data.fields)) {
                if (field && typeof field.confidence === 'number') {
                    total += field.confidence;
                    count++;
                }
            }
        }
        
        // Confiance OCR en fallback
        if (count === 0 && data._metadata?.ocr_confidence) {
            return data._metadata.ocr_confidence;
        }
        
        return count > 0 ? total / count : 0.5;
    }
    
    /**
     * Détermine le niveau de confiance
     */
    getConfidenceLevel(confidence) {
        if (confidence >= VerificationModal.CONFIDENCE_LEVELS.HIGH.min) {
            return VerificationModal.CONFIDENCE_LEVELS.HIGH;
        }
        if (confidence >= VerificationModal.CONFIDENCE_LEVELS.MEDIUM.min) {
            return VerificationModal.CONFIDENCE_LEVELS.MEDIUM;
        }
        return VerificationModal.CONFIDENCE_LEVELS.LOW;
    }
    
    /**
     * Formate un nom de champ
     */
    formatFieldName(name) {
        return name
            .replace(/_/g, ' ')
            .replace(/([A-Z])/g, ' $1')
            .replace(/^\w/, c => c.toUpperCase())
            .trim();
    }
    
    /**
     * Échappe le HTML
     */
    escapeHtml(str) {
        if (typeof str !== 'string') return str;
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    
    /**
     * Lie les événements
     */
    bindEvents(modal) {
        // Fermeture
        modal.querySelector('.btn-close-verification')?.addEventListener('click', () => this.close());
        
        // Clic sur l'overlay
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.close();
            }
        });
        
        // Bouton modifier
        modal.querySelector('.btn-modify')?.addEventListener('click', () => {
            this.close();
            this.config.onModify();
        });
        
        // Bouton confirmer
        modal.querySelector('.btn-confirm')?.addEventListener('click', () => {
            this.handleConfirm();
        });
        
        // Édition des champs
        modal.querySelectorAll('.field-input').forEach(input => {
            input.addEventListener('change', (e) => this.handleFieldChange(e));
            input.addEventListener('blur', (e) => this.handleFieldChange(e));
        });
        
        // Touche Escape
        const handleEscape = (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
                document.removeEventListener('keydown', handleEscape);
            }
        };
        document.addEventListener('keydown', handleEscape);
    }
    
    /**
     * Gère le changement d'un champ
     */
    handleFieldChange(event) {
        const input = event.target;
        const type = input.dataset.type;
        const field = input.dataset.field;
        const originalValue = input.dataset.original;
        const newValue = input.value;
        
        if (newValue !== originalValue) {
            // Marquer comme modifié
            input.classList.add('field-modified');
            
            // Stocker la modification
            if (!this.editedFields[type]) {
                this.editedFields[type] = {};
            }
            this.editedFields[type][field] = newValue;
            
            this.config.onFieldEdit(type, field, newValue, originalValue);
            this.log(`Field edited: ${type}.${field}`, { old: originalValue, new: newValue });
        } else {
            input.classList.remove('field-modified');
            
            // Supprimer de editedFields si revenu à l'original
            if (this.editedFields[type]) {
                delete this.editedFields[type][field];
            }
        }
    }
    
    /**
     * Gère la confirmation
     */
    handleConfirm() {
        // Fusionner les modifications dans les données extraites
        const finalData = JSON.parse(JSON.stringify(this.extractedData));
        
        for (const [type, fields] of Object.entries(this.editedFields)) {
            if (finalData[type]) {
                for (const [field, value] of Object.entries(fields)) {
                    this.setNestedValue(finalData[type], field, value);
                }
            }
        }
        
        this.close();
        this.config.onConfirm(finalData, this.editedFields);
        
        this.log('Data confirmed', { original: this.extractedData, edited: this.editedFields, final: finalData });
    }
    
    /**
     * Définit une valeur imbriquée
     */
    setNestedValue(obj, path, value) {
        const parts = path.split('.');
        let current = obj;
        
        for (let i = 0; i < parts.length - 1; i++) {
            if (!current[parts[i]]) {
                current[parts[i]] = {};
            }
            current = current[parts[i]];
        }
        
        const lastKey = parts[parts.length - 1];
        if (current[lastKey] && typeof current[lastKey] === 'object' && 'value' in current[lastKey]) {
            current[lastKey].value = value;
        } else {
            current[lastKey] = value;
        }
    }
    
    /**
     * Retourne les modifications effectuées
     */
    getEditedFields() {
        return this.editedFields;
    }
    
    /**
     * Log conditionnel
     */
    log(message, data = null) {
        if (this.config.debug) {
            console.log(`[VerificationModal] ${message}`, data || '');
        }
    }
}

// Export global
window.VerificationModal = VerificationModal;

