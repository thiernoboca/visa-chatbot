/**
 * Document Checklist Module - Chatbot Visa CI
 * Panneau de suivi des documents requis
 * 
 * @version 1.0.0
 */

class DocumentChecklist {
    /**
     * Constructeur
     * @param {VisaChatbot} chatbot - Instance du chatbot
     * @param {Object} options - Options de configuration
     */
    constructor(chatbot, options = {}) {
        this.chatbot = chatbot;
        this.config = {
            container: options.container || document.getElementById('documentChecklist'),
            onDocumentClick: options.onDocumentClick || null,
            debug: options.debug || false
        };
        
        // Documents par catégorie de workflow
        this.documentsByWorkflow = {
            STANDARD: [
                { id: 'passport', label: 'Passeport (page biographique)', required: true, icon: '📘' },
                { id: 'photo', label: 'Photo d\'identité', required: true, icon: '📷' },
                { id: 'ticket', label: 'Billet d\'avion / Réservation', required: true, icon: '✈️' },
                { id: 'accommodation', label: 'Justificatif d\'hébergement', required: true, icon: '🏨' },
                { id: 'financial', label: 'Justificatif de ressources', required: true, icon: '💳' },
                { id: 'vaccination', label: 'Carnet de vaccination', required: true, icon: '💉' }
            ],
            DIPLOMATIC: [
                { id: 'passport', label: 'Passeport diplomatique', required: true, icon: '📘' },
                { id: 'verbal_note', label: 'Note verbale', required: true, icon: '📜' },
                { id: 'photo', label: 'Photo d\'identité', required: true, icon: '📷' },
                { id: 'mission_letter', label: 'Lettre de mission', required: false, icon: '✉️' }
            ],
            SERVICE: [
                { id: 'passport', label: 'Passeport de service', required: true, icon: '📘' },
                { id: 'photo', label: 'Photo d\'identité', required: true, icon: '📷' },
                { id: 'mission_letter', label: 'Ordre de mission', required: true, icon: '✉️' },
                { id: 'vaccination', label: 'Carnet de vaccination', required: true, icon: '💉' }
            ],
            UN: [
                { id: 'passport', label: 'Laissez-passer ONU', required: true, icon: '📘' },
                { id: 'photo', label: 'Photo d\'identité', required: true, icon: '📷' },
                { id: 'un_letter', label: 'Lettre de l\'Organisation', required: true, icon: '🌐' }
            ],
            BUSINESS: [
                { id: 'passport', label: 'Passeport (page biographique)', required: true, icon: '📘' },
                { id: 'photo', label: 'Photo d\'identité', required: true, icon: '📷' },
                { id: 'invitation', label: 'Lettre d\'invitation', required: true, icon: '✉️' },
                { id: 'company_letter', label: 'Lettre de l\'employeur', required: true, icon: '🏢' },
                { id: 'ticket', label: 'Billet d\'avion', required: true, icon: '✈️' },
                { id: 'vaccination', label: 'Carnet de vaccination', required: true, icon: '💉' }
            ]
        };
        
        // État des documents
        this.state = {
            documents: [],
            uploadedDocuments: new Map(),
            isOpen: false
        };
        
        this.init();
    }
    
    /**
     * Initialise le module
     */
    init() {
        this.createPanel();
        this.bindEvents();
        this.log('Document checklist initialisé');
    }
    
    /**
     * Crée le panneau HTML
     */
    createPanel() {
        if (!this.config.container) {
            // Créer le conteneur s'il n'existe pas
            const panel = document.createElement('div');
            panel.id = 'documentChecklist';
            panel.className = 'document-checklist-panel';
            document.querySelector('.chat-app')?.appendChild(panel);
            this.config.container = panel;
        }
        
        this.render();
    }
    
    /**
     * Met à jour la liste des documents selon le workflow
     */
    setWorkflow(workflowCategory) {
        const category = workflowCategory?.toUpperCase() || 'STANDARD';
        this.state.documents = this.documentsByWorkflow[category] || this.documentsByWorkflow.STANDARD;
        this.render();
    }
    
    /**
     * Marque un document comme uploadé
     */
    markDocumentUploaded(documentId, data = {}) {
        this.state.uploadedDocuments.set(documentId, {
            uploadedAt: Date.now(),
            validated: data.validated || false,
            error: data.error || null,
            filename: data.filename || null,
            ...data
        });
        this.render();
        this.checkCompletion();
    }
    
    /**
     * Marque un document comme en attente de validation
     */
    markDocumentPending(documentId) {
        this.state.uploadedDocuments.set(documentId, {
            uploadedAt: Date.now(),
            validated: false,
            pending: true
        });
        this.render();
    }
    
    /**
     * Marque un document comme en erreur
     */
    markDocumentError(documentId, errorMessage) {
        this.state.uploadedDocuments.set(documentId, {
            uploadedAt: Date.now(),
            validated: false,
            error: errorMessage
        });
        this.render();
    }
    
    /**
     * Retourne le statut d'un document
     */
    getDocumentStatus(documentId) {
        const doc = this.state.uploadedDocuments.get(documentId);
        if (!doc) return 'missing';
        if (doc.error) return 'error';
        if (doc.pending) return 'pending';
        if (doc.validated) return 'validated';
        return 'uploaded';
    }
    
    /**
     * Vérifie si tous les documents requis sont uploadés
     */
    checkCompletion() {
        const requiredDocs = this.state.documents.filter(d => d.required);
        const uploadedRequired = requiredDocs.filter(d => {
            const status = this.getDocumentStatus(d.id);
            return status === 'uploaded' || status === 'validated';
        });
        
        const isComplete = uploadedRequired.length === requiredDocs.length;
        
        if (isComplete) {
            this.showCompletionMessage();
        }
        
        return isComplete;
    }
    
    /**
     * Affiche le message de complétion
     */
    showCompletionMessage() {
        this.chatbot?.showNotification('Documents', 'Tous les documents requis ont été fournis ✓', 'success');
    }
    
    /**
     * Calcule la progression
     */
    getProgress() {
        const requiredDocs = this.state.documents.filter(d => d.required);
        if (requiredDocs.length === 0) return 100;
        
        const uploadedRequired = requiredDocs.filter(d => {
            const status = this.getDocumentStatus(d.id);
            return status !== 'missing';
        });
        
        return Math.round((uploadedRequired.length / requiredDocs.length) * 100);
    }
    
    /**
     * Render le panneau
     */
    render() {
        if (!this.config.container) return;
        
        const progress = this.getProgress();
        
        const html = `
            <div class="checklist-header">
                <button type="button" class="checklist-toggle" aria-label="Toggle checklist">
                    <span class="checklist-icon">📋</span>
                    <span class="checklist-title">Documents requis</span>
                    <span class="checklist-badge">${progress}%</span>
                </button>
            </div>
            <div class="checklist-content ${this.state.isOpen ? 'open' : ''}">
                <div class="checklist-progress">
                    <div class="checklist-progress-bar">
                        <div class="checklist-progress-fill" style="width: ${progress}%"></div>
                    </div>
                    <span class="checklist-progress-text">${this.getProgressText()}</span>
                </div>
                <ul class="checklist-items">
                    ${this.state.documents.map(doc => this.renderDocumentItem(doc)).join('')}
                </ul>
                ${this.state.documents.length === 0 ? `
                    <p class="checklist-empty">La liste des documents s'affichera une fois le type de passeport sélectionné.</p>
                ` : ''}
            </div>
        `;
        
        this.config.container.innerHTML = html;
        this.config.container.classList.toggle('open', this.state.isOpen);
    }
    
    /**
     * Render un élément de document
     */
    renderDocumentItem(doc) {
        const status = this.getDocumentStatus(doc.id);
        const uploadData = this.state.uploadedDocuments.get(doc.id);
        
        const statusIcons = {
            missing: '○',
            pending: '⏳',
            uploaded: '✓',
            validated: '✓',
            error: '✗'
        };
        
        const statusClasses = {
            missing: '',
            pending: 'pending',
            uploaded: 'uploaded',
            validated: 'validated',
            error: 'error'
        };
        
        return `
            <li class="checklist-item ${statusClasses[status]}" data-doc-id="${doc.id}">
                <span class="item-status">${statusIcons[status]}</span>
                <span class="item-icon">${doc.icon}</span>
                <span class="item-label">
                    ${doc.label}
                    ${!doc.required ? '<span class="item-optional">(optionnel)</span>' : ''}
                </span>
                ${status === 'missing' ? `
                    <button type="button" class="item-action btn-upload" data-doc-id="${doc.id}">
                        Ajouter
                    </button>
                ` : ''}
                ${status === 'error' ? `
                    <span class="item-error" title="${uploadData?.error || 'Erreur'}">⚠️</span>
                ` : ''}
            </li>
        `;
    }
    
    /**
     * Retourne le texte de progression
     */
    getProgressText() {
        const required = this.state.documents.filter(d => d.required).length;
        const uploaded = this.state.documents.filter(d => {
            const status = this.getDocumentStatus(d.id);
            return d.required && status !== 'missing';
        }).length;
        
        if (uploaded === required) {
            return 'Tous les documents requis sont fournis';
        }
        
        return `${uploaded}/${required} documents fournis`;
    }
    
    /**
     * Lie les événements
     */
    bindEvents() {
        this.config.container?.addEventListener('click', (e) => {
            // Toggle panel
            if (e.target.closest('.checklist-toggle')) {
                this.toggle();
            }
            
            // Upload button
            const uploadBtn = e.target.closest('.btn-upload');
            if (uploadBtn) {
                const docId = uploadBtn.dataset.docId;
                if (this.config.onDocumentClick) {
                    this.config.onDocumentClick(docId);
                } else {
                    this.triggerUpload(docId);
                }
            }
        });
    }
    
    /**
     * Déclenche l'upload d'un document
     */
    triggerUpload(documentId) {
        // Créer un input file temporaire
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*,application/pdf';
        
        input.onchange = (e) => {
            const file = e.target.files?.[0];
            if (file) {
                this.markDocumentPending(documentId);
                // Émettre un événement pour que le chatbot gère l'upload
                const event = new CustomEvent('documentUpload', {
                    detail: { documentId, file }
                });
                document.dispatchEvent(event);
            }
        };
        
        input.click();
    }
    
    /**
     * Toggle l'ouverture du panneau
     */
    toggle() {
        this.state.isOpen = !this.state.isOpen;
        this.render();
    }
    
    /**
     * Ouvre le panneau
     */
    open() {
        this.state.isOpen = true;
        this.render();
    }
    
    /**
     * Ferme le panneau
     */
    close() {
        this.state.isOpen = false;
        this.render();
    }
    
    /**
     * Retourne la liste des documents manquants
     */
    getMissingDocuments() {
        return this.state.documents.filter(d => {
            return d.required && this.getDocumentStatus(d.id) === 'missing';
        });
    }
    
    /**
     * Log de debug
     */
    log(...args) {
        if (this.config.debug) {
            console.log('[Checklist]', ...args);
        }
    }
}

// Exposer globalement
window.DocumentChecklist = DocumentChecklist;

