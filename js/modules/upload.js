/**
 * Upload Module
 * Handles document uploads and OCR extraction
 *
 * @version 4.0.0
 * @module Upload
 */

import { CONFIG, DOCUMENT_TYPES, EXTRACTION_STAGES } from './config.js';
import { i18n } from './i18n.js';
import { stateManager } from './state.js';
import { messagesManager } from './messages.js';
import { uiManager } from './ui.js';

/**
 * Upload Manager class
 */
export class UploadManager {
    constructor() {
        this.apiEndpoint = CONFIG.endpoints.api;
        this.ocrEndpoint = CONFIG.endpoints.ocr;
        this.uploadEndpoint = CONFIG.endpoints.upload;
        this.currentFile = null;
        this.currentDocType = null;
    }

    /**
     * Initialize upload manager
     * @param {Object} options
     */
    init(options = {}) {
        if (options.apiEndpoint) this.apiEndpoint = options.apiEndpoint;
        if (options.ocrEndpoint) this.ocrEndpoint = options.ocrEndpoint;
        return this;
    }

    /**
     * Show document uploader in chat with camera option
     * @param {string} docType
     * @param {string} acceptTypes
     */
    showDocumentUploader(docType, acceptTypes = '.pdf,.jpg,.jpeg,.png') {
        const lang = stateManager.get('language') || 'fr';
        const docConfig = DOCUMENT_TYPES[docType] || {};
        const icon = docConfig.icon || '📄';
        const label = lang === 'fr' ? docConfig.nameFr : docConfig.nameEn;
        const uniqueId = `doc-upload-${docType}-${Date.now()}`;
        const supportsCamera = docConfig.supportsCamera || false;
        const cameraType = docConfig.cameraType || 'document';
        const isFaceCapture = cameraType === 'face';

        // Textes pour le bouton caméra selon le type
        const cameraIcon = isFaceCapture ? 'photo_camera' : 'document_scanner';
        const cameraLabel = isFaceCapture
            ? i18n.t('upload.takePhoto')
            : i18n.t('upload.scanWithCamera');

        // Bouton caméra si supporté
        const cameraButtonHtml = supportsCamera ? `
            <div class="upload-divider">
                <span class="divider-text">${i18n.t('upload.orUseCamera')}</span>
            </div>
            <button type="button" class="camera-capture-btn" data-doc-type="${docType}" data-camera-type="${cameraType}">
                <span class="material-symbols-outlined">${cameraIcon}</span>
                <span class="camera-btn-text">${cameraLabel}</span>
            </button>
        ` : '';

        const uploadHtml = `
            <div class="document-upload-zone" data-doc-type="${docType}" data-supports-camera="${supportsCamera}">
                <input type="file" id="${uniqueId}" accept="${acceptTypes}" hidden>
                <label for="${uniqueId}" class="upload-label">
                    <span class="upload-icon">${icon}</span>
                    <span class="upload-text">${i18n.t('upload.clickToUpload')} ${label}</span>
                    <span class="upload-hint">${i18n.t('upload.dragDrop')}</span>
                    <span class="upload-formats">PDF, JPG, PNG</span>
                </label>
                ${cameraButtonHtml}
                <div class="document-preview hidden">
                    <div class="preview-container">
                        <img class="preview-image" alt="Preview" />
                        <div class="preview-pdf-icon hidden">📄 PDF</div>
                    </div>
                    <div class="preview-info">
                        <span class="preview-name"></span>
                        <span class="preview-size"></span>
                    </div>
                    <div class="preview-actions">
                        <button type="button" class="preview-change-btn">🔄 ${i18n.t('common.edit')}</button>
                        <button type="button" class="preview-confirm-btn">✅ ${i18n.t('common.confirm')}</button>
                    </div>
                </div>
                <div class="upload-progress hidden">
                    <div class="progress-bar"><div class="progress-fill"></div></div>
                    <span class="progress-text">${i18n.t('upload.analyzing')}</span>
                </div>
            </div>
        `;

        const msgEl = document.createElement('div');
        msgEl.className = 'chat-message bot-message upload-container';
        msgEl.innerHTML = uploadHtml;

        const container = document.getElementById('chatMessages');
        container?.appendChild(msgEl);
        messagesManager.scrollToBottom();

        this.bindUploadEvents(msgEl, docType, uniqueId);

        // Bind camera events if supported
        if (supportsCamera) {
            this.bindCameraEvents(msgEl, docType, cameraType);
        }
    }

    /**
     * Bind events to upload zone
     * @param {HTMLElement} msgEl
     * @param {string} docType
     * @param {string} inputId
     */
    bindUploadEvents(msgEl, docType, inputId) {
        const fileInput = document.getElementById(inputId);
        const uploadZone = msgEl.querySelector('.document-upload-zone');
        const previewSection = uploadZone?.querySelector('.document-preview');
        const labelEl = uploadZone?.querySelector('.upload-label');
        const changeBtn = uploadZone?.querySelector('.preview-change-btn');
        const confirmBtn = uploadZone?.querySelector('.preview-confirm-btn');

        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                if (e.target.files[0]) {
                    this.showPreview(e.target.files[0], docType, uploadZone, previewSection, labelEl);
                }
            });
        }

        if (changeBtn) {
            changeBtn.addEventListener('click', () => {
                previewSection?.classList.add('hidden');
                labelEl?.classList.remove('hidden');
                if (fileInput) fileInput.value = '';
            });
        }

        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => {
                console.log('[Upload] Confirm clicked', {
                    hasFileInput: !!fileInput,
                    hasFiles: fileInput?.files?.length > 0,
                    docType
                });
                if (fileInput?.files[0]) {
                    this.handleDocumentUpload(fileInput.files[0], docType, uploadZone);
                } else {
                    console.error('[Upload] No file found in fileInput');
                }
            });
        }

        // Drag & drop
        if (uploadZone) {
            uploadZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadZone.classList.add('drag-over');
            });
            uploadZone.addEventListener('dragleave', () => {
                uploadZone.classList.remove('drag-over');
            });
            uploadZone.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadZone.classList.remove('drag-over');
                const file = e.dataTransfer?.files[0];
                if (file) {
                    this.showPreview(file, docType, uploadZone, previewSection, labelEl);
                    if (fileInput) {
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(file);
                        fileInput.files = dataTransfer.files;
                    }
                }
            });
        }
    }

    /**
     * Bind camera capture events
     * @param {HTMLElement} msgEl
     * @param {string} docType
     * @param {string} cameraType
     */
    bindCameraEvents(msgEl, docType, cameraType) {
        const cameraBtn = msgEl.querySelector('.camera-capture-btn');
        if (!cameraBtn) return;

        cameraBtn.addEventListener('click', () => {
            this.openCameraCapture(docType, cameraType, msgEl);
        });
    }

    /**
     * Open Innovatrics camera capture modal
     * @param {string} docType
     * @param {string} cameraType
     * @param {HTMLElement} uploadMsgEl
     */
    openCameraCapture(docType, cameraType, uploadMsgEl) {
        // Vérifier si Innovatrics est disponible
        if (typeof window.InnovatricsCameraCapture === 'undefined') {
            console.error('InnovatricsCameraCapture not loaded');
            uiManager.showNotification(
                i18n.t('common.error'),
                'Camera module not available',
                'error'
            );
            return;
        }

        const lang = stateManager.get('language') || 'fr';
        const docConfig = DOCUMENT_TYPES[docType] || {};

        // Créer l'instance Innovatrics
        const cameraCapture = new window.InnovatricsCameraCapture({
            type: cameraType === 'face' ? 'photo' : docType,
            language: lang,
            debug: CONFIG.defaults.debug,
            assetsPath: window.APP_CONFIG?.baseUrl || '/hunyuanocr/visa-chatbot',

            onCapture: (captureData) => {
                // Convertir le blob en File pour le traitement
                const fileName = `${docType}_capture_${Date.now()}.jpg`;
                const file = new File([captureData.blob], fileName, { type: 'image/jpeg' });

                // Montrer la preview dans la zone d'upload
                const uploadZone = uploadMsgEl.querySelector('.document-upload-zone');
                const previewSection = uploadZone?.querySelector('.document-preview');
                const labelEl = uploadZone?.querySelector('.upload-label');
                const cameraBtn = uploadZone?.querySelector('.camera-capture-btn');
                const divider = uploadZone?.querySelector('.upload-divider');

                if (uploadZone && previewSection) {
                    // Cacher le bouton caméra et le divider
                    if (cameraBtn) cameraBtn.classList.add('hidden');
                    if (divider) divider.classList.add('hidden');

                    // Afficher la preview
                    this.showPreview(file, docType, uploadZone, previewSection, labelEl);

                    // Stocker le fichier pour la confirmation
                    this.currentFile = file;
                    this.currentDocType = docType;

                    // Rebinder le bouton confirm pour ce fichier capturé
                    const confirmBtn = uploadZone.querySelector('.preview-confirm-btn');
                    const changeBtn = uploadZone.querySelector('.preview-change-btn');

                    if (confirmBtn) {
                        const newConfirmBtn = confirmBtn.cloneNode(true);
                        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
                        newConfirmBtn.addEventListener('click', () => {
                            this.handleDocumentUpload(file, docType, uploadZone);
                        });
                    }

                    if (changeBtn) {
                        const newChangeBtn = changeBtn.cloneNode(true);
                        changeBtn.parentNode.replaceChild(newChangeBtn, changeBtn);
                        newChangeBtn.addEventListener('click', () => {
                            previewSection?.classList.add('hidden');
                            labelEl?.classList.remove('hidden');
                            if (cameraBtn) cameraBtn.classList.remove('hidden');
                            if (divider) divider.classList.remove('hidden');
                        });
                    }
                }

                console.log(`[Upload] Camera captured: ${docType}`, captureData);
            },

            onError: (error) => {
                console.error('[Upload] Camera error:', error);
                uiManager.showNotification(
                    i18n.t('common.error'),
                    error || i18n.t('upload.error'),
                    'error'
                );
            },

            onStateChange: (state) => {
                console.log('[Upload] Camera state:', state);
            }
        });

        // Ouvrir le modal de choix (Desktop/Mobile)
        cameraCapture.openChoiceModal();
    }

    /**
     * Show file preview
     * @param {File} file
     * @param {string} docType
     * @param {HTMLElement} uploadZone
     * @param {HTMLElement} previewSection
     * @param {HTMLElement} labelEl
     */
    showPreview(file, docType, uploadZone, previewSection, labelEl) {
        if (!file || !previewSection) return;

        const previewImage = previewSection.querySelector('.preview-image');
        const pdfIcon = previewSection.querySelector('.preview-pdf-icon');
        const previewName = previewSection.querySelector('.preview-name');
        const previewSize = previewSection.querySelector('.preview-size');

        if (previewName) previewName.textContent = file.name;
        if (previewSize) {
            const sizeKB = (file.size / 1024).toFixed(1);
            const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            previewSize.textContent = file.size > 1024 * 1024 ? `${sizeMB} Mo` : `${sizeKB} Ko`;
        }

        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                if (previewImage) {
                    previewImage.src = e.target.result;
                    previewImage.classList.remove('hidden');
                }
                if (pdfIcon) pdfIcon.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        } else if (file.type === 'application/pdf') {
            if (previewImage) previewImage.classList.add('hidden');
            if (pdfIcon) {
                pdfIcon.classList.remove('hidden');
                pdfIcon.textContent = `📄 ${file.name}`;
            }
        }

        if (labelEl) labelEl.classList.add('hidden');
        previewSection.classList.remove('hidden');
        messagesManager.scrollToBottom();
    }

    /**
     * Handle document upload and extraction
     * @param {File} file
     * @param {string} docType
     * @param {HTMLElement} uploadZone
     */
    async handleDocumentUpload(file, docType, uploadZone) {
        if (!file) return;

        const lang = stateManager.get('language') || 'fr';
        const progressEl = uploadZone?.querySelector('.upload-progress');
        const progressBar = progressEl?.querySelector('.progress-fill');
        const progressText = progressEl?.querySelector('.progress-text');
        const labelEl = uploadZone?.querySelector('.upload-label');
        const previewSection = uploadZone?.querySelector('.document-preview');

        if (progressEl) progressEl.classList.remove('hidden');
        if (labelEl) labelEl.classList.add('hidden');
        if (previewSection) previewSection.classList.add('hidden');

        const updateProgress = (stageIndex) => {
            if (stageIndex >= EXTRACTION_STAGES.length) return;
            const stage = EXTRACTION_STAGES[stageIndex];
            if (progressBar) {
                progressBar.style.width = `${stage.progress}%`;
                progressBar.style.transition = 'width 0.5s ease-out';
            }
            if (progressText) {
                const text = lang === 'fr' ? stage.textFr : stage.textEn;
                progressText.innerHTML = `${stage.icon} ${text}`;
            }
        };

        let currentStage = 0;
        updateProgress(currentStage);

        const progressInterval = setInterval(() => {
            if (currentStage < 3) {
                currentStage++;
                updateProgress(currentStage);
            }
        }, 2000);

        try {
            updateProgress(0);
            const base64 = await this.fileToBase64(file);
            const mimeType = file.type || 'application/octet-stream';

            updateProgress(1);

            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Session-ID': stateManager.getSessionId()
                },
                body: JSON.stringify({
                    action: 'extract_document',
                    session_id: stateManager.getSessionId(),
                    document_type: docType,
                    file_data: base64,
                    mime_type: mimeType,
                    file_name: file.name
                })
            });

            clearInterval(progressInterval);
            updateProgress(3);

            const data = await response.json();

            // Handle both response formats: direct or wrapped in 'data' property
            const responseData = data.data || data;
            const extractedData = responseData.extracted_data;

            if (data.success && extractedData) {
                updateProgress(4);

                if (uploadZone) {
                    uploadZone.classList.add('upload-complete', 'extraction-success');
                }

                await new Promise(resolve => setTimeout(resolve, 800));

                stateManager.setDocumentUploaded(docType, extractedData);

                this.onDocumentExtracted?.(docType, extractedData, file.name);
            } else {
                clearInterval(progressInterval);
                const errorMsg = this.getErrorMessage(data.error || responseData.error, docType, lang);
                uiManager.showNotification(i18n.t('common.error'), errorMsg, 'error');

                if (progressEl) progressEl.classList.add('hidden');
                if (labelEl) labelEl.classList.remove('hidden');
                if (uploadZone) uploadZone.classList.add('extraction-failed');
                setTimeout(() => uploadZone?.classList.remove('extraction-failed'), 500);
            }
        } catch (error) {
            clearInterval(progressInterval);
            console.error('Upload error:', error);

            const errorMsg = this.getErrorMessage('network_error', docType, lang);
            uiManager.showNotification(i18n.t('common.error'), errorMsg, 'error');

            if (progressEl) progressEl.classList.add('hidden');
            if (labelEl) labelEl.classList.remove('hidden');
        }
    }

    /**
     * Handle passport scan
     * @param {File} file
     */
    async scanPassport(file) {
        if (!file) return null;

        try {
            const base64 = await this.fileToBase64(file);

            const response = await fetch(this.ocrEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    image: base64.split(',')[1],
                    mime_type: file.type,
                    action: 'extract_passport'
                })
            });

            const data = await response.json();

            if (data.success && data.extracted_data) {
                return data.extracted_data;
            } else {
                throw new Error(data.error || 'Extraction failed');
            }
        } catch (error) {
            console.error('Passport OCR error:', error);
            throw error;
        }
    }

    /**
     * Convert file to base64
     * @param {File} file
     * @returns {Promise<string>}
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
     * Get localized error message
     * @param {string} error
     * @param {string} docType
     * @param {string} lang
     * @returns {string}
     */
    getErrorMessage(error, docType, lang = 'fr') {
        const docConfig = DOCUMENT_TYPES[docType] || {};
        const docLabel = lang === 'fr' ? docConfig.nameFr : docConfig.nameEn;

        const errorKey = this.mapErrorToKey(error);
        const template = i18n.t(`errors.${errorKey}`) || i18n.t('errors.generic');

        return template.replace('{document}', docLabel || 'document');
    }

    /**
     * Map error to i18n key
     * @param {string} error
     * @returns {string}
     */
    mapErrorToKey(error) {
        if (!error) return 'generic';

        const lowerError = error.toLowerCase();
        if (lowerError.includes('timeout') || lowerError.includes('temps')) return 'timeout';
        if (lowerError.includes('format') || lowerError.includes('type')) return 'invalidFormat';
        if (lowerError.includes('large') || lowerError.includes('taille')) return 'tooLarge';
        if (lowerError.includes('flou') || lowerError.includes('blur')) return 'blurryImage';
        if (lowerError.includes('network') || lowerError.includes('connexion')) return 'network';

        return 'generic';
    }

    /**
     * Set document extracted callback
     * @param {Function} callback
     */
    setOnDocumentExtracted(callback) {
        this.onDocumentExtracted = callback;
    }

    /**
     * Validate file before upload
     * @param {File} file
     * @returns {Object} { valid: boolean, error?: string }
     */
    validateFile(file) {
        if (!file) {
            return { valid: false, error: 'No file provided' };
        }

        if (file.size > CONFIG.upload.maxSize) {
            return { valid: false, error: i18n.t('upload.tooLarge') };
        }

        if (!CONFIG.upload.acceptedTypes.includes(file.type)) {
            return { valid: false, error: i18n.t('upload.unsupported') };
        }

        return { valid: true };
    }
}

// Export singleton
export const uploadManager = new UploadManager();
export default uploadManager;
