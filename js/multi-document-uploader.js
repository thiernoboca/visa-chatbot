/**
 * Multi-Document Uploader - Chatbot Visa CI
 * Composant d'upload multi-documents avec extraction IA
 * Style macOS minimaliste
 * 
 * @version 1.0.0
 */

class MultiDocumentUploader {
    
    /**
     * Types de documents supportés
     */
    static DOCUMENT_TYPES = {
        passport: {
            name: 'Passeport',
            icon: '🛂',
            required: true,
            accepts: 'image/jpeg,image/png,image/webp,application/pdf'
        },
        ticket: {
            name: 'Billet d\'avion',
            icon: '✈️',
            required: true,
            accepts: 'application/pdf,image/jpeg,image/png'
        },
        hotel: {
            name: 'Réservation hôtel',
            icon: '🏨',
            required: true,
            accepts: 'application/pdf,image/jpeg,image/png'
        },
        vaccination: {
            name: 'Carnet vaccinal',
            icon: '💉',
            required: true,
            accepts: 'image/jpeg,image/png,application/pdf'
        }
    };
    
    /**
     * États possibles d'un document
     */
    static STATUS = {
        PENDING: 'pending',
        UPLOADING: 'uploading',
        PROCESSING: 'processing',
        COMPLETE: 'complete',
        ERROR: 'error'
    };
    
    /**
     * Constructeur
     * @param {Object} options Configuration
     */
    constructor(options = {}) {
        this.config = {
            endpoint: options.endpoint || 'php/chat-handler.php',
            maxFileSize: options.maxFileSize || 10 * 1024 * 1024, // 10MB
            onProgress: options.onProgress || (() => {}),
            onDocumentComplete: options.onDocumentComplete || (() => {}),
            onAllComplete: options.onAllComplete || (() => {}),
            onError: options.onError || (() => {}),
            debug: options.debug || false
        };
        
        this.documents = {};
        this.extractedData = {};
        this.isExtracting = false;
        
        // Initialiser les états des documents
        Object.keys(MultiDocumentUploader.DOCUMENT_TYPES).forEach(type => {
            this.documents[type] = {
                file: null,
                status: MultiDocumentUploader.STATUS.PENDING,
                data: null,
                error: null
            };
        });
    }
    
    /**
     * Génère le HTML de l'interface d'upload
     * @returns {string} HTML
     */
    render() {
        const types = MultiDocumentUploader.DOCUMENT_TYPES;
        
        return `
        <div class="multi-upload-container">
            <div class="multi-upload-header">
                <h3>📁 Téléversez vos documents</h3>
                <p class="upload-subtitle">Glissez-déposez ou cliquez pour ajouter</p>
            </div>
            
            <div class="upload-grid">
                ${Object.entries(types).map(([type, info]) => this.renderUploadZone(type, info)).join('')}
            </div>
            
            <div class="upload-progress-container">
                <div class="upload-progress-bar">
                    <div class="upload-progress-fill" id="uploadProgressFill"></div>
                </div>
                <span class="upload-progress-text" id="uploadProgressText">0/4 documents</span>
            </div>
            
            <div class="upload-actions">
                <button type="button" class="btn-extract" id="btnExtractAll" disabled>
                    <span class="btn-icon">🤖</span>
                    <span class="btn-text">Analyser avec l'IA</span>
                </button>
            </div>
        </div>
        `;
    }
    
    /**
     * Génère le HTML d'une zone d'upload
     */
    renderUploadZone(type, info) {
        return `
        <div class="upload-zone-wrapper" data-type="${type}">
            <label for="upload-${type}" class="upload-zone" data-type="${type}" data-required="${info.required}">
                <div class="upload-zone-content">
                    <span class="upload-zone-icon">${info.icon}</span>
                    <span class="upload-zone-label">${info.name}</span>
                    <span class="upload-zone-status" data-status="pending"></span>
                </div>
                <div class="upload-zone-preview" hidden>
                    <img src="" alt="Aperçu" class="upload-preview-img">
                    <span class="upload-preview-name"></span>
                    <button type="button" class="btn-remove-file" title="Supprimer">×</button>
                </div>
                <div class="upload-zone-processing" hidden>
                    <div class="upload-spinner"></div>
                    <span>Extraction...</span>
                </div>
                <div class="upload-zone-result" hidden>
                    <span class="result-icon">✓</span>
                    <span class="result-confidence"></span>
                </div>
            </label>
            <input type="file" 
                   id="upload-${type}" 
                   class="upload-input sr-only" 
                   data-type="${type}"
                   accept="${info.accepts}">
        </div>
        `;
    }
    
    /**
     * Monte le composant dans un conteneur
     * @param {HTMLElement|string} container Conteneur ou sélecteur
     */
    mount(container) {
        const el = typeof container === 'string' 
            ? document.querySelector(container) 
            : container;
        
        if (!el) {
            console.error('[MultiDocumentUploader] Container not found');
            return;
        }
        
        el.innerHTML = this.render();
        this.bindEvents(el);
        this.container = el;
        
        this.log('Mounted');
    }
    
    /**
     * Lie les événements
     */
    bindEvents(container) {
        // Événements sur les inputs file
        container.querySelectorAll('.upload-input').forEach(input => {
            input.addEventListener('change', (e) => this.handleFileSelect(e));
        });
        
        // Drag & drop sur les zones
        container.querySelectorAll('.upload-zone').forEach(zone => {
            zone.addEventListener('dragover', (e) => this.handleDragOver(e));
            zone.addEventListener('dragleave', (e) => this.handleDragLeave(e));
            zone.addEventListener('drop', (e) => this.handleDrop(e));
        });
        
        // Bouton supprimer
        container.querySelectorAll('.btn-remove-file').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const type = btn.closest('.upload-zone-wrapper').dataset.type;
                this.removeFile(type);
            });
        });
        
        // Bouton extraction
        const btnExtract = container.querySelector('#btnExtractAll');
        if (btnExtract) {
            btnExtract.addEventListener('click', () => this.extractAll());
        }
    }
    
    /**
     * Gère la sélection de fichier
     */
    handleFileSelect(event) {
        const input = event.target;
        const type = input.dataset.type;
        const file = input.files[0];
        
        if (file) {
            this.addFile(type, file);
        }
    }
    
    /**
     * Gère le drag over
     */
    handleDragOver(event) {
        event.preventDefault();
        event.stopPropagation();
        event.currentTarget.classList.add('drag-over');
    }
    
    /**
     * Gère le drag leave
     */
    handleDragLeave(event) {
        event.preventDefault();
        event.stopPropagation();
        event.currentTarget.classList.remove('drag-over');
    }
    
    /**
     * Gère le drop
     */
    handleDrop(event) {
        event.preventDefault();
        event.stopPropagation();
        
        const zone = event.currentTarget;
        zone.classList.remove('drag-over');
        
        const type = zone.dataset.type;
        const file = event.dataTransfer.files[0];
        
        if (file) {
            this.addFile(type, file);
        }
    }
    
    /**
     * Ajoute un fichier
     */
    addFile(type, file) {
        // Valider la taille
        if (file.size > this.config.maxFileSize) {
            this.showError(type, `Fichier trop volumineux (max ${this.config.maxFileSize / 1024 / 1024}MB)`);
            return;
        }
        
        // Valider le type
        const acceptedTypes = MultiDocumentUploader.DOCUMENT_TYPES[type].accepts.split(',');
        if (!acceptedTypes.includes(file.type)) {
            this.showError(type, 'Format de fichier non accepté');
            return;
        }
        
        // Stocker le fichier
        this.documents[type] = {
            file: file,
            status: MultiDocumentUploader.STATUS.UPLOADING,
            data: null,
            error: null
        };
        
        // Afficher l'aperçu
        this.showPreview(type, file);
        
        // Mettre à jour la progression
        this.updateProgress();
        
        this.log(`File added: ${type}`, file.name);
    }
    
    /**
     * Supprime un fichier
     */
    removeFile(type) {
        this.documents[type] = {
            file: null,
            status: MultiDocumentUploader.STATUS.PENDING,
            data: null,
            error: null
        };
        
        // Réinitialiser l'affichage
        this.resetZone(type);
        
        // Mettre à jour la progression
        this.updateProgress();
        
        // Réinitialiser l'input
        const input = this.container.querySelector(`#upload-${type}`);
        if (input) input.value = '';
        
        this.log(`File removed: ${type}`);
    }
    
    /**
     * Affiche l'aperçu du fichier
     */
    showPreview(type, file) {
        const wrapper = this.container.querySelector(`.upload-zone-wrapper[data-type="${type}"]`);
        if (!wrapper) return;
        
        const content = wrapper.querySelector('.upload-zone-content');
        const preview = wrapper.querySelector('.upload-zone-preview');
        const previewImg = wrapper.querySelector('.upload-preview-img');
        const previewName = wrapper.querySelector('.upload-preview-name');
        
        // Masquer le contenu, afficher l'aperçu
        if (content) content.hidden = true;
        if (preview) preview.hidden = false;
        
        // Nom du fichier
        if (previewName) {
            previewName.textContent = file.name.length > 20 
                ? file.name.substring(0, 17) + '...' 
                : file.name;
        }
        
        // Aperçu image
        if (file.type.startsWith('image/') && previewImg) {
            const reader = new FileReader();
            reader.onload = (e) => {
                previewImg.src = e.target.result;
            };
            reader.readAsDataURL(file);
        } else if (previewImg) {
            // Icône pour PDF
            previewImg.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="%23E85D04" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
        }
        
        // Ajouter classe uploadé
        const zone = wrapper.querySelector('.upload-zone');
        if (zone) zone.classList.add('has-file');
    }
    
    /**
     * Réinitialise une zone d'upload
     */
    resetZone(type) {
        const wrapper = this.container.querySelector(`.upload-zone-wrapper[data-type="${type}"]`);
        if (!wrapper) return;
        
        const content = wrapper.querySelector('.upload-zone-content');
        const preview = wrapper.querySelector('.upload-zone-preview');
        const processing = wrapper.querySelector('.upload-zone-processing');
        const result = wrapper.querySelector('.upload-zone-result');
        const zone = wrapper.querySelector('.upload-zone');
        
        if (content) content.hidden = false;
        if (preview) preview.hidden = true;
        if (processing) processing.hidden = true;
        if (result) result.hidden = true;
        if (zone) zone.classList.remove('has-file', 'processing', 'complete', 'error');
    }
    
    /**
     * Met à jour la barre de progression
     */
    updateProgress() {
        const total = Object.keys(this.documents).length;
        const uploaded = Object.values(this.documents).filter(d => d.file !== null).length;
        
        const progressFill = this.container.querySelector('#uploadProgressFill');
        const progressText = this.container.querySelector('#uploadProgressText');
        const btnExtract = this.container.querySelector('#btnExtractAll');
        
        const percent = (uploaded / total) * 100;
        
        if (progressFill) {
            progressFill.style.width = `${percent}%`;
        }
        
        if (progressText) {
            progressText.textContent = `${uploaded}/${total} documents`;
        }
        
        // Activer le bouton si au moins 1 document
        if (btnExtract) {
            btnExtract.disabled = uploaded === 0;
        }
        
        this.config.onProgress(uploaded / total);
    }
    
    /**
     * Lance l'extraction de tous les documents
     */
    async extractAll() {
        if (this.isExtracting) return;
        
        const filesToExtract = Object.entries(this.documents)
            .filter(([_, doc]) => doc.file !== null);
        
        if (filesToExtract.length === 0) {
            return;
        }
        
        this.isExtracting = true;
        const btnExtract = this.container.querySelector('#btnExtractAll');
        if (btnExtract) {
            btnExtract.disabled = true;
            btnExtract.querySelector('.btn-text').textContent = 'Analyse en cours...';
        }
        
        let completed = 0;
        const results = {};
        
        for (const [type, doc] of filesToExtract) {
            this.showProcessing(type);
            
            try {
                const result = await this.extractDocument(type, doc.file);
                
                this.documents[type].status = MultiDocumentUploader.STATUS.COMPLETE;
                this.documents[type].data = result;
                results[type] = result;
                
                this.showResult(type, result);
                this.config.onDocumentComplete(type, result);
            } catch (error) {
                this.documents[type].status = MultiDocumentUploader.STATUS.ERROR;
                this.documents[type].error = error.message;
                
                this.showError(type, error.message);
                this.config.onError(type, error);
            }
            
            completed++;
            this.updateExtractionProgress(completed, filesToExtract.length);
        }
        
        this.isExtracting = false;
        this.extractedData = results;
        
        if (btnExtract) {
            btnExtract.querySelector('.btn-text').textContent = 'Analyser avec l\'IA';
            btnExtract.disabled = false;
        }
        
        // Appeler le callback avec tous les résultats
        this.config.onAllComplete(results);
        
        this.log('Extraction complete', results);
    }
    
    /**
     * Extrait les données d'un document
     */
    async extractDocument(type, file) {
        // Convertir en base64
        const base64 = await this.fileToBase64(file);
        
        const response = await fetch(this.config.endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({
                action: 'extract_document',
                type: type,
                content: base64.split(',')[1], // Enlever le préfixe data:...
                mime_type: file.type
            })
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Extraction échouée');
        }
        
        return data.data.extracted_data;
    }
    
    /**
     * Affiche l'état de traitement
     */
    showProcessing(type) {
        const wrapper = this.container.querySelector(`.upload-zone-wrapper[data-type="${type}"]`);
        if (!wrapper) return;
        
        const preview = wrapper.querySelector('.upload-zone-preview');
        const processing = wrapper.querySelector('.upload-zone-processing');
        const zone = wrapper.querySelector('.upload-zone');
        
        if (preview) preview.hidden = true;
        if (processing) processing.hidden = false;
        if (zone) zone.classList.add('processing');
    }
    
    /**
     * Affiche le résultat de l'extraction
     */
    showResult(type, result) {
        const wrapper = this.container.querySelector(`.upload-zone-wrapper[data-type="${type}"]`);
        if (!wrapper) return;
        
        const processing = wrapper.querySelector('.upload-zone-processing');
        const resultEl = wrapper.querySelector('.upload-zone-result');
        const confidence = wrapper.querySelector('.result-confidence');
        const zone = wrapper.querySelector('.upload-zone');
        
        if (processing) processing.hidden = true;
        if (resultEl) resultEl.hidden = false;
        if (zone) {
            zone.classList.remove('processing');
            zone.classList.add('complete');
        }
        
        // Afficher la confiance
        const score = result.overall_confidence || result._metadata?.ocr_confidence || 0;
        if (confidence) {
            confidence.textContent = `${Math.round(score * 100)}%`;
        }
    }
    
    /**
     * Affiche une erreur
     */
    showError(type, message) {
        const wrapper = this.container.querySelector(`.upload-zone-wrapper[data-type="${type}"]`);
        if (!wrapper) return;
        
        const processing = wrapper.querySelector('.upload-zone-processing');
        const resultEl = wrapper.querySelector('.upload-zone-result');
        const icon = wrapper.querySelector('.result-icon');
        const confidence = wrapper.querySelector('.result-confidence');
        const zone = wrapper.querySelector('.upload-zone');
        
        if (processing) processing.hidden = true;
        if (resultEl) resultEl.hidden = false;
        if (icon) icon.textContent = '✗';
        if (confidence) confidence.textContent = 'Erreur';
        if (zone) {
            zone.classList.remove('processing');
            zone.classList.add('error');
        }
        
        this.log(`Error for ${type}: ${message}`, 'error');
    }
    
    /**
     * Met à jour la progression de l'extraction
     */
    updateExtractionProgress(completed, total) {
        const progressFill = this.container.querySelector('#uploadProgressFill');
        const progressText = this.container.querySelector('#uploadProgressText');
        
        const percent = (completed / total) * 100;
        
        if (progressFill) {
            progressFill.style.width = `${percent}%`;
        }
        
        if (progressText) {
            progressText.textContent = `Extraction: ${completed}/${total}`;
        }
    }
    
    /**
     * Convertit un fichier en base64
     */
    fileToBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }
    
    /**
     * Retourne les données extraites
     */
    getExtractedData() {
        return this.extractedData;
    }
    
    /**
     * Retourne le nombre de documents uploadés
     */
    getUploadedCount() {
        return Object.values(this.documents).filter(d => d.file !== null).length;
    }
    
    /**
     * Vérifie si tous les documents requis sont présents
     */
    hasAllRequired() {
        const required = Object.entries(MultiDocumentUploader.DOCUMENT_TYPES)
            .filter(([_, info]) => info.required)
            .map(([type]) => type);
        
        return required.every(type => this.documents[type].file !== null);
    }
    
    /**
     * Réinitialise le composant
     */
    reset() {
        Object.keys(this.documents).forEach(type => {
            this.removeFile(type);
        });
        this.extractedData = {};
    }
    
    /**
     * Log conditionnel
     */
    log(message, data = null) {
        if (this.config.debug) {
            console.log(`[MultiDocumentUploader] ${message}`, data || '');
        }
    }
}

// Export global
window.MultiDocumentUploader = MultiDocumentUploader;

