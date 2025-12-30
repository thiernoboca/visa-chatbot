/**
 * Autosave Module - Chatbot Visa CI
 * Gère la sauvegarde automatique et la récupération des brouillons
 * 
 * @version 1.0.0
 */

class AutosaveManager {
    /**
     * Constructeur
     * @param {VisaChatbot} chatbot - Instance du chatbot
     * @param {Object} options - Options de configuration
     */
    constructor(chatbot, options = {}) {
        this.chatbot = chatbot;
        this.config = {
            endpoint: options.endpoint || 'php/draft-manager.php',
            autoSaveInterval: options.autoSaveInterval || 30000, // 30 secondes
            localStorageKey: 'visa_chatbot_draft',
            debug: options.debug || false
        };
        
        this.state = {
            preApplicationId: null,
            lastSavedAt: null,
            isDirty: false,
            isSaving: false,
            autoSaveTimer: null
        };
        
        this.elements = {};
        this.init();
    }
    
    /**
     * Initialise le module
     */
    async init() {
        this.bindElements();
        await this.checkForExistingDraft();
        this.startAutoSave();
        this.log('Autosave initialisé');
    }
    
    /**
     * Lie les éléments DOM
     */
    bindElements() {
        this.elements = {
            draftBadge: document.getElementById('draftBadge'),
            draftStatus: document.getElementById('draftStatus'),
            draftPreId: document.getElementById('draftPreId'),
            btnSaveDraft: document.getElementById('btnSaveDraft'),
            btnRestoreDraft: document.getElementById('btnRestoreDraft')
        };
        
        // Événements
        this.elements.btnSaveDraft?.addEventListener('click', () => this.saveNow());
    }
    
    /**
     * Vérifie s'il y a un brouillon existant
     */
    async checkForExistingDraft() {
        // D'abord vérifier le localStorage
        const localDraft = this.getLocalDraft();
        
        if (localDraft) {
            this.state.preApplicationId = localDraft.pre_application_id;
            
            // Vérifier si le brouillon existe côté serveur
            try {
                const response = await fetch(`${this.config.endpoint}?action=info&session_id=${this.chatbot.state.sessionId}`);
                const data = await response.json();
                
                if (data.success && data.data) {
                    this.state.preApplicationId = data.data.pre_application_id;
                    this.updateDraftUI(data.data);
                }
            } catch (error) {
                this.log('Erreur vérification brouillon:', error);
            }
        }
    }
    
    /**
     * Démarre la sauvegarde automatique
     */
    startAutoSave() {
        if (this.state.autoSaveTimer) {
            clearInterval(this.state.autoSaveTimer);
        }
        
        this.state.autoSaveTimer = setInterval(() => {
            if (this.state.isDirty && !this.state.isSaving) {
                this.save();
            }
        }, this.config.autoSaveInterval);
        
        // Sauvegarder aussi avant de quitter la page
        window.addEventListener('beforeunload', (e) => {
            if (this.state.isDirty) {
                this.saveToLocalStorage();
            }
        });
    }
    
    /**
     * Marque le brouillon comme modifié
     */
    markDirty() {
        this.state.isDirty = true;
        this.updateDraftStatus('non sauvegardé', 'pending');
    }
    
    /**
     * Sauvegarde le brouillon
     */
    async save() {
        if (this.state.isSaving) return;
        
        this.state.isSaving = true;
        this.updateDraftStatus('sauvegarde...', 'saving');
        
        try {
            // Sauvegarder dans localStorage d'abord (offline backup)
            this.saveToLocalStorage();
            
            // Puis côté serveur
            const response = await fetch(this.config.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Session-ID': this.chatbot.state.sessionId
                },
                body: JSON.stringify({
                    action: 'save',
                    session_id: this.chatbot.state.sessionId,
                    pre_application_id: this.state.preApplicationId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.state.preApplicationId = data.data.pre_application_id;
                this.state.lastSavedAt = data.data.saved_at;
                this.state.isDirty = false;
                
                this.updateDraftUI(data.data);
                this.updateDraftStatus('sauvegardé', 'saved');
                
                // Mettre à jour le localStorage avec le bon ID
                this.saveToLocalStorage();
                
                this.log('Brouillon sauvegardé:', data.data.pre_application_id);
            } else {
                this.updateDraftStatus('erreur', 'error');
            }
        } catch (error) {
            this.log('Erreur sauvegarde:', error);
            this.updateDraftStatus('hors ligne', 'offline');
        } finally {
            this.state.isSaving = false;
        }
    }
    
    /**
     * Force une sauvegarde immédiate
     */
    async saveNow() {
        this.state.isDirty = true;
        await this.save();
    }
    
    /**
     * Sauvegarde dans localStorage
     */
    saveToLocalStorage() {
        const draft = {
            pre_application_id: this.state.preApplicationId,
            session_id: this.chatbot.state.sessionId,
            current_step: this.chatbot.state.currentStep,
            workflow_category: this.chatbot.state.workflowCategory,
            saved_at: Date.now()
        };
        
        localStorage.setItem(this.config.localStorageKey, JSON.stringify(draft));
    }
    
    /**
     * Récupère le brouillon du localStorage
     */
    getLocalDraft() {
        const stored = localStorage.getItem(this.config.localStorageKey);
        if (!stored) return null;
        
        try {
            const draft = JSON.parse(stored);
            
            // Vérifier l'expiration (7 jours)
            const expiryMs = 7 * 24 * 60 * 60 * 1000;
            if (Date.now() - draft.saved_at > expiryMs) {
                localStorage.removeItem(this.config.localStorageKey);
                return null;
            }
            
            return draft;
        } catch (e) {
            return null;
        }
    }
    
    /**
     * Restaure un brouillon
     */
    async restore(preApplicationId) {
        try {
            const response = await fetch(this.config.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Session-ID': this.chatbot.state.sessionId
                },
                body: JSON.stringify({
                    action: 'restore',
                    session_id: this.chatbot.state.sessionId,
                    pre_application_id: preApplicationId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Recharger l'état du chatbot
                this.chatbot.showNotification('Brouillon', 'Votre demande a été restaurée', 'success');
                // Recharger la page pour appliquer les changements
                window.location.reload();
                return true;
            }
            
            return false;
        } catch (error) {
            this.log('Erreur restauration:', error);
            return false;
        }
    }
    
    /**
     * Met à jour l'interface du brouillon
     */
    updateDraftUI(draftInfo) {
        if (this.elements.draftPreId) {
            this.elements.draftPreId.textContent = draftInfo.pre_application_id || '';
        }
        
        if (this.elements.draftBadge) {
            this.elements.draftBadge.hidden = false;
        }
    }
    
    /**
     * Met à jour le statut de sauvegarde
     */
    updateDraftStatus(text, status) {
        if (this.elements.draftStatus) {
            this.elements.draftStatus.textContent = text;
            this.elements.draftStatus.dataset.status = status;
        }
    }
    
    /**
     * Retourne l'ID de pré-demande
     */
    getPreApplicationId() {
        return this.state.preApplicationId;
    }
    
    /**
     * Log de debug
     */
    log(...args) {
        if (this.config.debug) {
            console.log('[Autosave]', ...args);
        }
    }
    
    /**
     * Arrête l'autosave
     */
    destroy() {
        if (this.state.autoSaveTimer) {
            clearInterval(this.state.autoSaveTimer);
        }
    }
}

// Exposer globalement
window.AutosaveManager = AutosaveManager;

