/**
 * Coherence UI Components - Chatbot Visa CI
 * Composants visuels pour la validation de cohérence cross-documents
 *
 * @version 1.0.0
 */

class CoherenceUI {

    /**
     * Configuration des types de documents
     */
    static DOC_CONFIG = {
        passport: { icon: '🛂', name: 'Passeport', nameFr: 'Passeport' },
        ticket: { icon: '✈️', name: 'Billet d\'avion', nameFr: 'Billet d\'avion' },
        hotel: { icon: '🏨', name: 'Hôtel', nameFr: 'Réservation hôtel' },
        vaccination: { icon: '💉', name: 'Vaccination', nameFr: 'Carnet vaccinal' },
        invitation: { icon: '📄', name: 'Invitation', nameFr: 'Lettre d\'invitation' }
    };

    /**
     * Configuration des sévérités
     */
    static SEVERITY_CONFIG = {
        error: { icon: '❌', class: 'severity-error', label: 'Erreur' },
        warning: { icon: '⚠️', class: 'severity-warning', label: 'Attention' },
        info: { icon: 'ℹ️', class: 'severity-info', label: 'Info' }
    };

    constructor(options = {}) {
        this.config = {
            container: options.container || document.getElementById('chatMessages'),
            apiEndpoint: options.apiEndpoint || 'php/coherence-validator-api.php',
            onAction: options.onAction || (() => {}),
            debug: options.debug || false
        };
    }

    /**
     * Récupère et affiche la validation de cohérence
     */
    async validateAndDisplay() {
        try {
            const response = await fetch(this.config.apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({})
            });

            const result = await response.json();

            if (result.success) {
                return this.renderFullReport(result.data);
            } else {
                console.error('Coherence validation failed:', result.error);
                return null;
            }
        } catch (error) {
            console.error('Coherence API error:', error);
            return null;
        }
    }

    /**
     * Génère le rapport complet de cohérence
     */
    renderFullReport(data) {
        const container = document.createElement('div');
        container.className = 'coherence-report';

        // 1. Résumé du dossier
        container.appendChild(this.renderDossierSummary(data));

        // 2. Timeline du voyage
        if (data.stay_info) {
            container.appendChild(this.renderTimeline(data.stay_info));
        }

        // 3. Checklist des documents
        container.appendChild(this.renderDocumentChecklist(data.documents_validated || []));

        // 4. Alertes de cohérence
        if (data.issues && data.issues.length > 0) {
            container.appendChild(this.renderAlerts(data.issues));
        }

        // 5. Actions requises
        if (data.required_actions && data.required_actions.length > 0) {
            container.appendChild(this.renderRequiredActions(data.required_actions));
        }

        return container;
    }

    /**
     * Résumé visuel du dossier
     */
    renderDossierSummary(data) {
        const summary = data.summary || {};
        const stayInfo = data.stay_info || {};

        const statusClass = data.is_coherent ? 'status-success' :
                           (data.is_blocked ? 'status-error' : 'status-warning');
        const statusIcon = data.is_coherent ? '✅' : (data.is_blocked ? '❌' : '⚠️');
        const statusText = data.is_coherent ? 'Dossier complet' :
                          (data.is_blocked ? 'Action requise' : 'Vérifications recommandées');

        const el = document.createElement('div');
        el.className = 'dossier-summary';
        el.innerHTML = `
            <div class="summary-header ${statusClass}">
                <span class="summary-icon">${statusIcon}</span>
                <div class="summary-title">
                    <h3>Résumé de votre dossier</h3>
                    <span class="summary-status">${statusText}</span>
                </div>
            </div>
            <div class="summary-body">
                <div class="summary-grid">
                    <div class="summary-item">
                        <span class="item-label">Demandeur</span>
                        <span class="item-value">${summary.applicant_name || 'Non renseigné'}</span>
                    </div>
                    <div class="summary-item">
                        <span class="item-label">Destination</span>
                        <span class="item-value">🇨🇮 ${summary.destination || 'Côte d\'Ivoire'}</span>
                    </div>
                    <div class="summary-item">
                        <span class="item-label">Motif</span>
                        <span class="item-value">${this.truncate(summary.purpose || 'Non spécifié', 40)}</span>
                    </div>
                    <div class="summary-item">
                        <span class="item-label">Durée du séjour</span>
                        <span class="item-value">${stayInfo.stay_days || '?'} jours</span>
                    </div>
                </div>
                <div class="summary-dates">
                    <div class="date-box arrival">
                        <span class="date-label">Arrivée</span>
                        <span class="date-value">${this.formatDate(stayInfo.arrival_date)}</span>
                    </div>
                    <div class="date-arrow">→</div>
                    <div class="date-box departure">
                        <span class="date-label">Départ</span>
                        <span class="date-value">${this.formatDate(stayInfo.departure_date) || 'Non défini'}</span>
                    </div>
                </div>
            </div>
        `;
        return el;
    }

    /**
     * Timeline du voyage
     */
    renderTimeline(stayInfo) {
        const el = document.createElement('div');
        el.className = 'voyage-timeline';

        const events = this.buildTimelineEvents(stayInfo);

        el.innerHTML = `
            <div class="timeline-header">
                <h4>📅 Chronologie de votre voyage</h4>
            </div>
            <div class="timeline-track">
                ${events.map((event, index) => `
                    <div class="timeline-event ${event.type}" style="left: ${event.position}%">
                        <div class="event-marker">
                            <span class="event-icon">${event.icon}</span>
                        </div>
                        <div class="event-details ${index % 2 === 0 ? 'above' : 'below'}">
                            <span class="event-date">${event.date}</span>
                            <span class="event-label">${event.label}</span>
                        </div>
                    </div>
                `).join('')}
                <div class="timeline-line"></div>
            </div>
        `;
        return el;
    }

    /**
     * Construit les événements de la timeline
     */
    buildTimelineEvents(stayInfo) {
        const events = [];
        const dates = [];

        // Collecter toutes les dates
        if (stayInfo.invitation_from) dates.push(new Date(stayInfo.invitation_from));
        if (stayInfo.arrival_date) dates.push(new Date(stayInfo.arrival_date));
        if (stayInfo.accommodation_from) dates.push(new Date(stayInfo.accommodation_from));
        if (stayInfo.accommodation_to) dates.push(new Date(stayInfo.accommodation_to));
        if (stayInfo.departure_date) dates.push(new Date(stayInfo.departure_date));
        if (stayInfo.invitation_to) dates.push(new Date(stayInfo.invitation_to));

        if (dates.length < 2) return events;

        const minDate = new Date(Math.min(...dates));
        const maxDate = new Date(Math.max(...dates));
        const range = maxDate - minDate;

        const calcPosition = (date) => {
            if (!date) return 0;
            const d = new Date(date);
            return Math.round(((d - minDate) / range) * 100);
        };

        // Ajouter les événements
        if (stayInfo.invitation_from) {
            events.push({
                date: this.formatDateShort(stayInfo.invitation_from),
                label: 'Début invitation',
                icon: '📄',
                type: 'invitation-start',
                position: calcPosition(stayInfo.invitation_from)
            });
        }

        if (stayInfo.arrival_date) {
            events.push({
                date: this.formatDateShort(stayInfo.arrival_date),
                label: 'Vol aller',
                icon: '✈️',
                type: 'flight-arrival',
                position: calcPosition(stayInfo.arrival_date)
            });
        }

        if (stayInfo.accommodation_from && stayInfo.accommodation_to) {
            events.push({
                date: this.formatDateShort(stayInfo.accommodation_from),
                label: 'Check-in hôtel',
                icon: '🏨',
                type: 'hotel-checkin',
                position: calcPosition(stayInfo.accommodation_from)
            });

            // Seulement si check-out est différent de check-in
            if (stayInfo.accommodation_to !== stayInfo.accommodation_from) {
                events.push({
                    date: this.formatDateShort(stayInfo.accommodation_to),
                    label: 'Check-out',
                    icon: '🏨',
                    type: 'hotel-checkout',
                    position: calcPosition(stayInfo.accommodation_to)
                });
            }
        }

        if (stayInfo.departure_date) {
            events.push({
                date: this.formatDateShort(stayInfo.departure_date),
                label: 'Vol retour',
                icon: '✈️',
                type: 'flight-departure',
                position: calcPosition(stayInfo.departure_date)
            });
        }

        if (stayInfo.invitation_to && stayInfo.invitation_to !== stayInfo.departure_date) {
            events.push({
                date: this.formatDateShort(stayInfo.invitation_to),
                label: 'Fin invitation',
                icon: '📄',
                type: 'invitation-end',
                position: calcPosition(stayInfo.invitation_to)
            });
        }

        // Trier par position
        events.sort((a, b) => a.position - b.position);

        // Ajuster les positions pour éviter les chevauchements
        for (let i = 1; i < events.length; i++) {
            if (events[i].position - events[i-1].position < 10) {
                events[i].position = events[i-1].position + 10;
            }
        }

        return events;
    }

    /**
     * Checklist des documents
     */
    renderDocumentChecklist(documentsValidated) {
        const allDocs = ['passport', 'ticket', 'hotel', 'vaccination', 'invitation'];

        const el = document.createElement('div');
        el.className = 'document-checklist-ui';
        el.innerHTML = `
            <div class="checklist-header">
                <h4>📁 Documents fournis</h4>
                <span class="checklist-count">${documentsValidated.length}/${allDocs.length}</span>
            </div>
            <div class="checklist-items">
                ${allDocs.map(doc => {
                    const config = CoherenceUI.DOC_CONFIG[doc];
                    const isPresent = documentsValidated.includes(doc);
                    return `
                        <div class="checklist-item ${isPresent ? 'present' : 'missing'}">
                            <span class="item-check">${isPresent ? '☑️' : '☐'}</span>
                            <span class="item-icon">${config.icon}</span>
                            <span class="item-name">${config.nameFr}</span>
                            <span class="item-status">${isPresent ? 'Vérifié' : 'Manquant'}</span>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
        return el;
    }

    /**
     * Alertes de cohérence interactives
     */
    renderAlerts(issues) {
        const el = document.createElement('div');
        el.className = 'coherence-alerts';

        const groupedIssues = {
            error: issues.filter(i => i.severity === 'error'),
            warning: issues.filter(i => i.severity === 'warning'),
            info: issues.filter(i => i.severity === 'info')
        };

        el.innerHTML = `
            <div class="alerts-header">
                <h4>🔍 Vérifications</h4>
                <div class="alerts-summary">
                    ${groupedIssues.error.length > 0 ? `<span class="badge error">${groupedIssues.error.length} erreur(s)</span>` : ''}
                    ${groupedIssues.warning.length > 0 ? `<span class="badge warning">${groupedIssues.warning.length} attention</span>` : ''}
                    ${groupedIssues.info.length > 0 ? `<span class="badge info">${groupedIssues.info.length} info(s)</span>` : ''}
                </div>
            </div>
            <div class="alerts-list">
                ${issues.map(issue => this.renderAlertItem(issue)).join('')}
            </div>
        `;
        return el;
    }

    /**
     * Élément d'alerte individuel
     */
    renderAlertItem(issue) {
        const config = CoherenceUI.SEVERITY_CONFIG[issue.severity] || CoherenceUI.SEVERITY_CONFIG.info;
        const hasActions = issue.actions && issue.actions.length > 0;

        return `
            <div class="alert-item ${config.class}" data-issue-type="${issue.type}">
                <div class="alert-icon">${config.icon}</div>
                <div class="alert-content">
                    <div class="alert-message">${issue.message_fr || issue.message}</div>
                    ${issue.detail ? `<div class="alert-detail">${issue.detail}</div>` : ''}
                    ${hasActions ? `
                        <div class="alert-actions">
                            ${issue.actions.map(action => this.renderActionButton(action)).join('')}
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    /**
     * Bouton d'action
     */
    renderActionButton(action) {
        const icons = {
            upload: '📤',
            confirm: '✅',
            update: '🔄'
        };
        const icon = icons[action.type] || '▶️';

        return `
            <button type="button" class="action-btn action-${action.type}"
                    data-action-type="${action.type}"
                    data-doc-type="${action.doc_type || ''}"
                    onclick="window.coherenceUI?.handleAction(this)">
                <span class="action-icon">${icon}</span>
                <span class="action-label">${action.label_fr || action.label}</span>
            </button>
        `;
    }

    /**
     * Actions requises
     */
    renderRequiredActions(actions) {
        if (actions.length === 0) return document.createElement('div');

        const el = document.createElement('div');
        el.className = 'required-actions';
        el.innerHTML = `
            <div class="actions-header">
                <h4>📋 Actions recommandées</h4>
            </div>
            <div class="actions-list">
                ${actions.map(action => `
                    <div class="action-item">
                        ${this.renderActionButton(action)}
                        ${action.detail ? `<span class="action-detail">${action.detail}</span>` : ''}
                    </div>
                `).join('')}
            </div>
        `;
        return el;
    }

    /**
     * Preview des données extraites (pour un document)
     */
    renderDataPreview(docType, data) {
        const config = CoherenceUI.DOC_CONFIG[docType] || { icon: '📄', name: docType };

        const el = document.createElement('div');
        el.className = 'data-preview';
        el.innerHTML = `
            <div class="preview-header">
                <span class="preview-icon">${config.icon}</span>
                <h4>Données extraites - ${config.nameFr}</h4>
                <span class="preview-confidence">${Math.round((data.confidence || 0.9) * 100)}% confiance</span>
            </div>
            <div class="preview-fields">
                ${this.renderPreviewFields(docType, data)}
            </div>
            <div class="preview-actions">
                <button type="button" class="btn-preview-edit" onclick="window.coherenceUI?.editPreview('${docType}')">
                    ✏️ Corriger
                </button>
                <button type="button" class="btn-preview-confirm" onclick="window.coherenceUI?.confirmPreview('${docType}')">
                    ✅ Confirmer
                </button>
            </div>
        `;
        return el;
    }

    /**
     * Champs de preview selon le type de document
     */
    renderPreviewFields(docType, data) {
        const fieldConfigs = {
            passport: [
                { key: 'fields.surname.value', label: 'Nom' },
                { key: 'fields.given_names.value', label: 'Prénoms' },
                { key: 'fields.passport_number.value', label: 'N° Passeport' },
                { key: 'fields.date_of_birth.value', label: 'Date de naissance' },
                { key: 'fields.date_of_expiry.value', label: 'Date d\'expiration' },
                { key: 'fields.nationality.value', label: 'Nationalité' }
            ],
            ticket: [
                { key: 'passenger_name', label: 'Passager' },
                { key: 'airline', label: 'Compagnie' },
                { key: 'flight_number', label: 'N° Vol aller' },
                { key: 'departure_date', label: 'Date départ' },
                { key: 'return_flight_number', label: 'N° Vol retour' },
                { key: 'return_date', label: 'Date retour' }
            ],
            hotel: [
                { key: 'guest_name', label: 'Client' },
                { key: 'hotel_name', label: 'Hôtel' },
                { key: 'hotel_city', label: 'Ville' },
                { key: 'check_in_date', label: 'Check-in' },
                { key: 'check_out_date', label: 'Check-out' },
                { key: 'confirmation_number', label: 'N° Confirmation' }
            ],
            vaccination: [
                { key: 'holder_name', label: 'Titulaire' },
                { key: 'vaccine_type', label: 'Vaccin' },
                { key: 'vaccination_date', label: 'Date vaccination' },
                { key: 'certificate_number', label: 'N° Certificat' }
            ],
            invitation: [
                { key: 'invitee.name', label: 'Invité' },
                { key: 'inviter.name', label: 'Invitant' },
                { key: 'inviter.organization', label: 'Organisation' },
                { key: 'purpose', label: 'Motif' },
                { key: 'dates.from', label: 'Du' },
                { key: 'dates.to', label: 'Au' }
            ]
        };

        const fields = fieldConfigs[docType] || [];

        return fields.map(field => {
            const value = this.getNestedValue(data, field.key);
            return `
                <div class="preview-field">
                    <span class="field-label">${field.label}</span>
                    <span class="field-value" data-field="${field.key}">${value || 'Non extrait'}</span>
                </div>
            `;
        }).join('');
    }

    /**
     * Gestion des actions
     */
    handleAction(button) {
        const actionType = button.dataset.actionType;
        const docType = button.dataset.docType;

        this.log('Action triggered:', actionType, docType);

        switch (actionType) {
            case 'upload':
                this.triggerUpload(docType);
                break;
            case 'confirm':
                this.confirmAction(button);
                break;
            case 'update':
                this.triggerUpdate(docType);
                break;
        }

        if (this.config.onAction) {
            this.config.onAction(actionType, docType);
        }
    }

    /**
     * Déclenche l'upload d'un document
     */
    triggerUpload(docType) {
        // Créer un input file temporaire
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*,application/pdf';
        input.onchange = (e) => {
            const file = e.target.files[0];
            if (file && window.visaChatbot) {
                window.visaChatbot.handleFileUpload(file, docType);
            }
        };
        input.click();
    }

    /**
     * Confirme une action
     */
    confirmAction(button) {
        const alertItem = button.closest('.alert-item');
        if (alertItem) {
            alertItem.classList.add('confirmed');
            alertItem.querySelector('.alert-actions')?.remove();

            const confirmBadge = document.createElement('div');
            confirmBadge.className = 'confirm-badge';
            confirmBadge.innerHTML = '✅ Confirmé';
            alertItem.querySelector('.alert-content').appendChild(confirmBadge);
        }
    }

    /**
     * Déclenche une mise à jour
     */
    triggerUpdate(docType) {
        // Ouvrir la modal d'édition
        this.log('Update triggered for:', docType);
    }

    /**
     * Éditer le preview
     */
    editPreview(docType) {
        this.log('Edit preview:', docType);
        // Rendre les champs éditables
    }

    /**
     * Confirmer le preview
     */
    confirmPreview(docType) {
        this.log('Confirm preview:', docType);
        // Valider les données
    }

    // === UTILITAIRES ===

    /**
     * Récupère une valeur imbriquée
     */
    getNestedValue(obj, path) {
        return path.split('.').reduce((o, k) => (o || {})[k], obj);
    }

    /**
     * Formate une date
     */
    formatDate(dateStr) {
        if (!dateStr) return null;
        try {
            const date = new Date(dateStr);
            return date.toLocaleDateString('fr-FR', {
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            });
        } catch {
            return dateStr;
        }
    }

    /**
     * Formate une date courte
     */
    formatDateShort(dateStr) {
        if (!dateStr) return '';
        try {
            const date = new Date(dateStr);
            return date.toLocaleDateString('fr-FR', {
                day: 'numeric',
                month: 'short'
            });
        } catch {
            return dateStr;
        }
    }

    /**
     * Tronque un texte
     */
    truncate(str, maxLength) {
        if (!str) return '';
        return str.length > maxLength ? str.substring(0, maxLength) + '...' : str;
    }

    /**
     * Log conditionnel
     */
    log(...args) {
        if (this.config.debug) {
            console.log('[CoherenceUI]', ...args);
        }
    }
}

// Export global
window.CoherenceUI = CoherenceUI;
