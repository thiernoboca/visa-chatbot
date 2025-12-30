/**
 * Chatbot Redesign - Main Controller
 * Interface utilisateur pour le chatbot visa avec OCR
 * 
 * @version 1.0.0
 */

(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        apiEndpoint: 'php/chat-handler.php',
        ocrEndpoint: '../passport-ocr-module/php/api-handler.php',
        debug: true
    };

    // État de l'application
    const state = {
        currentStep: 'welcome',
        language: 'fr',
        sessionId: null,
        selectedFile: null,
        isProcessing: false
    };

    // Éléments DOM
    const elements = {};

    /**
     * Log de debug
     */
    function log(...args) {
        if (CONFIG.debug) {
            console.log('[ChatbotRedesign]', ...args);
        }
    }

    /**
     * Génère un ID de session unique
     */
    function generateSessionId() {
        return 'session-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    }

    /**
     * Initialisation au chargement de la page
     */
    function init() {
        log('Initializing chatbot redesign...');
        
        // Générer/récupérer session ID
        state.sessionId = sessionStorage.getItem('visa_session_id') || generateSessionId();
        sessionStorage.setItem('visa_session_id', state.sessionId);

        // Récupérer les éléments DOM
        cacheElements();

        // Attacher les événements
        bindEvents();

        // Afficher le message de bienvenue
        showWelcomeMessage();

        log('Initialization complete', { sessionId: state.sessionId });
    }

    /**
     * Cache les références aux éléments DOM
     */
    function cacheElements() {
        elements.chatMessages = document.getElementById('chat-messages');
        elements.actionArea = document.getElementById('action-area');
        elements.uploadModal = document.getElementById('upload-modal');
        elements.dropZone = document.getElementById('drop-zone');
        elements.fileInput = document.getElementById('file-input');
        elements.uploadPreview = document.getElementById('upload-preview');
        elements.previewImage = document.getElementById('preview-image');
        elements.clearPreview = document.getElementById('clear-preview');
        elements.confirmUploadBtn = document.getElementById('confirm-upload-btn');
        elements.closeUploadModal = document.getElementById('close-upload-modal');
        elements.uploadProcessing = document.getElementById('upload-processing');
        elements.webcamBtn = document.getElementById('webcam-btn');
        elements.progressBar = document.getElementById('progress-bar');
        elements.progressPercent = document.getElementById('progress-percent');
        elements.stepTimeline = document.getElementById('step-timeline');
        elements.passportConfirmModal = document.getElementById('passport-confirm-modal');
        elements.themeToggle = document.getElementById('theme-toggle');
        elements.langToggle = document.getElementById('lang-toggle');
    }

    /**
     * Attache les événements aux éléments
     */
    function bindEvents() {
        // Upload modal
        if (elements.fileInput) {
            elements.fileInput.addEventListener('change', handleFileSelect);
        }

        if (elements.dropZone) {
            elements.dropZone.addEventListener('dragover', handleDragOver);
            elements.dropZone.addEventListener('dragleave', handleDragLeave);
            elements.dropZone.addEventListener('drop', handleDrop);
        }

        if (elements.confirmUploadBtn) {
            elements.confirmUploadBtn.addEventListener('click', handleConfirmUpload);
        }

        if (elements.closeUploadModal) {
            elements.closeUploadModal.addEventListener('click', closeUploadModal);
        }

        if (elements.clearPreview) {
            elements.clearPreview.addEventListener('click', clearFilePreview);
        }

        if (elements.webcamBtn) {
            elements.webcamBtn.addEventListener('click', openWebcamCapture);
        }

        // Theme toggle
        if (elements.themeToggle) {
            elements.themeToggle.addEventListener('click', toggleTheme);
        }

        // Language toggle
        if (elements.langToggle) {
            elements.langToggle.addEventListener('click', toggleLanguage);
        }

        // Fermer le modal en cliquant à l'extérieur
        if (elements.uploadModal) {
            elements.uploadModal.addEventListener('click', (e) => {
                if (e.target === elements.uploadModal) {
                    closeUploadModal();
                }
            });
        }
    }

    /**
     * Affiche le message de bienvenue
     */
    function showWelcomeMessage() {
        const lang = state.language;
        const messages = {
            fr: {
                greeting: 'Akwaba ! 👋',
                intro: 'Je suis votre assistant pour la demande de visa e-Visa pour la Côte d\'Ivoire.',
                instruction: 'Pour commencer, veuillez scanner ou télécharger votre passeport.'
            },
            en: {
                greeting: 'Akwaba! 👋',
                intro: 'I am your assistant for the Côte d\'Ivoire e-Visa application.',
                instruction: 'To begin, please scan or upload your passport.'
            }
        };

        const msg = messages[lang] || messages.fr;

        addBotMessage(`<strong>${msg.greeting}</strong><br><br>${msg.intro}<br><br>${msg.instruction}`);

        // Afficher le bouton d'upload dans la zone d'action
        showUploadAction();
    }

    /**
     * Affiche le bouton d'upload du passeport
     */
    function showUploadAction() {
        const lang = state.language;
        const btnText = lang === 'fr' ? 'Scanner mon passeport' : 'Scan my passport';

        if (elements.actionArea) {
            elements.actionArea.innerHTML = `
                <button id="btn-upload-passport" class="btn-premium w-full py-4 rounded-xl font-semibold text-white shadow-glow flex items-center justify-center gap-3">
                    <span class="material-symbols-outlined">document_scanner</span>
                    ${btnText}
                </button>
            `;

            document.getElementById('btn-upload-passport')?.addEventListener('click', openUploadModal);
        }
    }

    /**
     * Ajoute un message du bot au chat
     */
    function addBotMessage(content) {
        if (!elements.chatMessages) return;

        const msgEl = document.createElement('div');
        msgEl.className = 'flex gap-3 animate-enter';
        msgEl.innerHTML = `
            <div class="shrink-0 size-9 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-lg">smart_toy</span>
            </div>
            <div class="msg-bubble-bot p-4 max-w-[85%]">
                <p class="text-sm text-gray-700 dark:text-gray-200 leading-relaxed">${content}</p>
            </div>
        `;

        elements.chatMessages.appendChild(msgEl);
        scrollToBottom();
    }

    /**
     * Ajoute un message de l'utilisateur au chat
     */
    function addUserMessage(content) {
        if (!elements.chatMessages) return;

        const msgEl = document.createElement('div');
        msgEl.className = 'flex justify-end animate-enter';
        msgEl.innerHTML = `
            <div class="msg-bubble-user p-4 max-w-[85%]">
                <p class="text-sm leading-relaxed">${content}</p>
            </div>
        `;

        elements.chatMessages.appendChild(msgEl);
        scrollToBottom();
    }

    /**
     * Scroll vers le bas du chat
     */
    function scrollToBottom() {
        if (elements.chatMessages) {
            elements.chatMessages.scrollTop = elements.chatMessages.scrollHeight;
        }
    }

    /**
     * Ouvre le modal d'upload
     */
    function openUploadModal() {
        log('Opening upload modal');
        if (elements.uploadModal) {
            elements.uploadModal.classList.remove('hidden');
            elements.uploadModal.classList.add('flex');
        }
    }

    /**
     * Ferme le modal d'upload
     */
    function closeUploadModal() {
        log('Closing upload modal');
        if (elements.uploadModal) {
            elements.uploadModal.classList.add('hidden');
            elements.uploadModal.classList.remove('flex');
        }
        clearFilePreview();
    }

    /**
     * Gère la sélection de fichier
     */
    function handleFileSelect(e) {
        const file = e.target.files?.[0];
        if (file) {
            log('File selected:', file.name, file.type, file.size);
            state.selectedFile = file;
            showFilePreview(file);
        }
    }

    /**
     * Gère le dragover
     */
    function handleDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        elements.dropZone?.classList.add('drag-over', 'border-primary', 'bg-primary/5');
    }

    /**
     * Gère le dragleave
     */
    function handleDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        elements.dropZone?.classList.remove('drag-over', 'border-primary', 'bg-primary/5');
    }

    /**
     * Gère le drop
     */
    function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        elements.dropZone?.classList.remove('drag-over', 'border-primary', 'bg-primary/5');

        const file = e.dataTransfer?.files?.[0];
        if (file) {
            log('File dropped:', file.name, file.type, file.size);
            state.selectedFile = file;

            // Mettre à jour l'input file
            if (elements.fileInput) {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                elements.fileInput.files = dataTransfer.files;
            }

            showFilePreview(file);
        }
    }

    /**
     * Affiche l'aperçu du fichier
     */
    function showFilePreview(file) {
        if (!elements.uploadPreview || !elements.previewImage) return;

        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                elements.previewImage.src = e.target.result;
                elements.uploadPreview.classList.remove('hidden');
                elements.dropZone?.classList.add('hidden');
                
                // Activer le bouton confirmer
                if (elements.confirmUploadBtn) {
                    elements.confirmUploadBtn.disabled = false;
                }
            };
            reader.readAsDataURL(file);
        } else if (file.type === 'application/pdf') {
            // Pour les PDFs, afficher une icône
            elements.previewImage.src = 'data:image/svg+xml,' + encodeURIComponent(`
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
                    <rect fill="#DC2626" width="100" height="100" rx="8"/>
                    <text x="50" y="60" font-family="Arial" font-size="24" fill="white" text-anchor="middle">PDF</text>
                </svg>
            `);
            elements.uploadPreview.classList.remove('hidden');
            elements.dropZone?.classList.add('hidden');
            
            if (elements.confirmUploadBtn) {
                elements.confirmUploadBtn.disabled = false;
            }
        }
    }

    /**
     * Efface l'aperçu du fichier
     */
    function clearFilePreview() {
        state.selectedFile = null;
        
        if (elements.fileInput) {
            elements.fileInput.value = '';
        }
        
        if (elements.previewImage) {
            elements.previewImage.src = '';
        }
        
        if (elements.uploadPreview) {
            elements.uploadPreview.classList.add('hidden');
        }
        
        if (elements.dropZone) {
            elements.dropZone.classList.remove('hidden');
        }
        
        if (elements.confirmUploadBtn) {
            elements.confirmUploadBtn.disabled = true;
        }
    }

    /**
     * Gère la confirmation d'upload
     */
    async function handleConfirmUpload() {
        if (!state.selectedFile || state.isProcessing) {
            log('No file selected or already processing');
            return;
        }

        log('Starting passport OCR...', state.selectedFile.name);
        state.isProcessing = true;

        // Afficher le processing
        if (elements.uploadPreview) {
            elements.uploadPreview.classList.add('hidden');
        }
        if (elements.uploadProcessing) {
            elements.uploadProcessing.classList.remove('hidden');
        }
        if (elements.confirmUploadBtn) {
            elements.confirmUploadBtn.disabled = true;
        }

        try {
            // Convertir le fichier en base64
            const base64 = await fileToBase64(state.selectedFile);
            const base64Data = base64.split(',')[1]; // Enlever le préfixe data:...

            log('Sending to OCR API...');

            // Appeler l'API OCR
            const response = await fetch(CONFIG.ocrEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    image: base64Data,
                    mime_type: state.selectedFile.type,
                    action: 'extract_passport'
                })
            });

            const data = await response.json();
            log('OCR Response:', data);

            if (data.success !== false && data.extracted_data) {
                // Succès - fermer le modal et afficher les résultats
                closeUploadModal();
                handleOCRSuccess(data.extracted_data);
            } else {
                throw new Error(data.error || 'Extraction failed');
            }

        } catch (error) {
            log('OCR Error:', error);
            handleOCRError(error);
        } finally {
            state.isProcessing = false;
            if (elements.uploadProcessing) {
                elements.uploadProcessing.classList.add('hidden');
            }
        }
    }

    /**
     * Convertit un fichier en base64
     */
    function fileToBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }

    /**
     * Gère le succès de l'OCR
     */
    function handleOCRSuccess(extractedData) {
        log('OCR Success:', extractedData);
        
        // #region agent log
        // Debug: Log structure des données pour diagnostic
        console.log('[DEBUG] extractedData:', JSON.stringify(extractedData, null, 2));
        console.log('[DEBUG] fields:', extractedData.fields);
        console.log('[DEBUG] passport_number field:', extractedData.fields?.passport_number);
        // #endregion

        const fields = extractedData.fields || {};
        const lang = state.language;

        // Message de confirmation
        addUserMessage(lang === 'fr' ? '📄 Passeport scanné' : '📄 Passport scanned');

        // Construire le message avec les données extraites
        let dataHtml = '';
        
        const fieldLabels = {
            fr: {
                surname: 'Nom',
                given_names: 'Prénoms',
                date_of_birth: 'Date de naissance',
                nationality: 'Nationalité',
                passport_number: 'N° Passeport',
                date_of_expiry: 'Date d\'expiration',
                sex: 'Sexe',
                passport_type: 'Type de passeport'
            },
            en: {
                surname: 'Surname',
                given_names: 'Given names',
                date_of_birth: 'Date of birth',
                nationality: 'Nationality',
                passport_number: 'Passport No.',
                date_of_expiry: 'Expiry date',
                sex: 'Sex',
                passport_type: 'Passport type'
            }
        };

        const labels = fieldLabels[lang] || fieldLabels.fr;
        const displayFields = ['surname', 'given_names', 'date_of_birth', 'nationality', 'passport_number', 'date_of_expiry', 'sex', 'passport_type'];

        displayFields.forEach(key => {
            const field = fields[key];
            // Extraire la valeur correctement (peut être un objet {value, confidence} ou une string)
            let value = '';
            if (field && typeof field === 'object' && field.value !== undefined && field.value !== null) {
                value = String(field.value);
            } else if (field && typeof field === 'string') {
                value = field;
            } else if (field && typeof field === 'number') {
                value = String(field);
            }
            
            // Afficher le champ s'il a une valeur non vide
            // Pour les champs critiques comme passport_number, toujours afficher même si vide
            const criticalFields = ['passport_number', 'surname', 'given_names'];
            const shouldDisplay = value.trim() !== '' || criticalFields.includes(key);
            
            if (shouldDisplay) {
                const displayValue = value.trim() !== '' ? value : '-';
                dataHtml += `<div class="flex justify-between py-1 border-b border-gray-100 dark:border-gray-700">
                    <span class="text-gray-500">${labels[key] || key}</span>
                    <span class="font-medium">${displayValue}</span>
                </div>`;
            }
        });

        const confirmText = lang === 'fr' ? 'Ces informations sont-elles correctes ?' : 'Is this information correct?';
        const successMsg = lang === 'fr' ? '✅ Passeport lu avec succès !' : '✅ Passport read successfully!';

        addBotMessage(`
            <strong>${successMsg}</strong>
            <div class="mt-4 space-y-1 text-sm">
                ${dataHtml}
            </div>
            <p class="mt-4 font-medium">${confirmText}</p>
        `);

        // Afficher les boutons de confirmation
        showConfirmationButtons(extractedData);

        // Mettre à jour la progression
        updateProgress(20);
    }

    /**
     * Affiche les boutons de confirmation
     */
    function showConfirmationButtons(extractedData) {
        const lang = state.language;
        const yesText = lang === 'fr' ? 'Oui, c\'est correct' : 'Yes, it\'s correct';
        const noText = lang === 'fr' ? 'Non, modifier' : 'No, edit';

        if (elements.actionArea) {
            elements.actionArea.innerHTML = `
                <div class="flex gap-3">
                    <button id="btn-confirm-data" class="btn-premium flex-1 py-3 rounded-xl font-semibold text-white shadow-glow flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined">check</span>
                        ${yesText}
                    </button>
                    <button id="btn-edit-data" class="btn-secondary flex-1 py-3 rounded-xl font-semibold flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined">edit</span>
                        ${noText}
                    </button>
                </div>
            `;

            document.getElementById('btn-confirm-data')?.addEventListener('click', () => {
                handleDataConfirmed(extractedData);
            });

            document.getElementById('btn-edit-data')?.addEventListener('click', () => {
                handleDataEdit(extractedData);
            });
        }
    }

    /**
     * Gère la confirmation des données
     */
    function handleDataConfirmed(extractedData) {
        const lang = state.language;
        
        addUserMessage(lang === 'fr' ? '✓ Données confirmées' : '✓ Data confirmed');
        
        const nextMsg = lang === 'fr' 
            ? 'Parfait ! Passons à l\'étape suivante. Veuillez télécharger votre billet d\'avion.'
            : 'Perfect! Let\'s move to the next step. Please upload your flight ticket.';
        
        addBotMessage(nextMsg);
        
        // Continuer le workflow...
        state.currentStep = 'ticket';
        updateProgress(30);
    }

    /**
     * Gère l'édition des données
     */
    function handleDataEdit(extractedData) {
        log('Edit data requested', extractedData);
        
        const lang = state.language;
        const fields = extractedData.fields || {};
        
        addUserMessage(lang === 'fr' ? '✏️ Modifier les données' : '✏️ Edit data');
        
        // Labels pour le formulaire
        const fieldLabels = {
            fr: {
                surname: 'Nom',
                given_names: 'Prénoms',
                date_of_birth: 'Date de naissance',
                nationality: 'Nationalité',
                passport_number: 'N° Passeport',
                date_of_expiry: 'Date d\'expiration',
                sex: 'Sexe'
            },
            en: {
                surname: 'Surname',
                given_names: 'Given names',
                date_of_birth: 'Date of birth',
                nationality: 'Nationality',
                passport_number: 'Passport No.',
                date_of_expiry: 'Expiry date',
                sex: 'Sex'
            }
        };
        
        const labels = fieldLabels[lang] || fieldLabels.fr;
        const editableFields = ['surname', 'given_names', 'date_of_birth', 'nationality', 'passport_number', 'date_of_expiry', 'sex'];
        
        // Construire le formulaire d'édition
        let formHtml = '<div class="space-y-3">';
        
        editableFields.forEach(key => {
            const field = fields[key];
            let value = '';
            if (field && typeof field === 'object' && field.value !== undefined) {
                value = field.value || '';
            } else if (field && typeof field === 'string') {
                value = field;
            }
            
            const inputType = key.includes('date') ? 'date' : 'text';
            
            formHtml += `
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium text-gray-500">${labels[key] || key}</label>
                    <input type="${inputType}" 
                           name="${key}" 
                           value="${value}" 
                           class="clean-input px-3 py-2 rounded-lg text-sm"
                           data-field="${key}">
                </div>
            `;
        });
        
        formHtml += '</div>';
        
        const editMsg = lang === 'fr' 
            ? 'Modifiez les informations ci-dessous puis validez :'
            : 'Edit the information below then validate:';
        
        addBotMessage(`<strong>${editMsg}</strong>${formHtml}`);
        
        // Bouton de validation
        const saveText = lang === 'fr' ? 'Enregistrer les modifications' : 'Save changes';
        const cancelText = lang === 'fr' ? 'Annuler' : 'Cancel';
        
        if (elements.actionArea) {
            elements.actionArea.innerHTML = `
                <div class="flex gap-3">
                    <button id="btn-save-edit" class="btn-premium flex-1 py-3 rounded-xl font-semibold text-white shadow-glow flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined">save</span>
                        ${saveText}
                    </button>
                    <button id="btn-cancel-edit" class="btn-secondary flex-1 py-3 rounded-xl font-semibold flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined">close</span>
                        ${cancelText}
                    </button>
                </div>
            `;
            
            document.getElementById('btn-save-edit')?.addEventListener('click', () => {
                // Récupérer les valeurs modifiées
                const inputs = document.querySelectorAll('[data-field]');
                const updatedData = { ...extractedData, fields: { ...fields } };
                
                inputs.forEach(input => {
                    const fieldName = input.dataset.field;
                    if (updatedData.fields[fieldName]) {
                        if (typeof updatedData.fields[fieldName] === 'object') {
                            updatedData.fields[fieldName].value = input.value;
                        } else {
                            updatedData.fields[fieldName] = input.value;
                        }
                    } else {
                        updatedData.fields[fieldName] = { value: input.value, confidence: 1.0 };
                    }
                });
                
                handleDataConfirmed(updatedData);
            });
            
            document.getElementById('btn-cancel-edit')?.addEventListener('click', () => {
                // Réafficher les données originales
                handleOCRSuccess(extractedData);
            });
        }
    }

    /**
     * Gère les erreurs OCR
     */
    function handleOCRError(error) {
        log('OCR Error:', error);
        
        const lang = state.language;
        const errorMsg = lang === 'fr'
            ? 'Impossible de lire le passeport. Veuillez réessayer avec une meilleure image.'
            : 'Unable to read passport. Please try again with a better image.';

        showNotification('Erreur', errorMsg, 'error');
        
        // Réinitialiser l'état
        if (elements.uploadPreview) {
            elements.uploadPreview.classList.remove('hidden');
        }
        if (elements.confirmUploadBtn) {
            elements.confirmUploadBtn.disabled = false;
        }
    }

    /**
     * Ouvre la capture webcam
     */
    function openWebcamCapture() {
        log('Opening webcam capture');
        
        if (typeof window.InnovatricsCameraCapture !== 'undefined') {
            const camera = new window.InnovatricsCameraCapture({
                type: 'passport',
                language: state.language,
                debug: CONFIG.debug,
                onCapture: (captureData) => {
                    log('Webcam capture:', captureData);
                    state.selectedFile = new File([captureData.blob], 'passport_capture.jpg', { type: 'image/jpeg' });
                    showFilePreview(state.selectedFile);
                },
                onError: (error) => {
                    log('Webcam error:', error);
                    showNotification('Erreur', error, 'error');
                }
            });
            camera.openChoiceModal();
        } else {
            showNotification('Erreur', 'Module caméra non disponible', 'error');
        }
    }

    /**
     * Met à jour la progression
     */
    function updateProgress(percent) {
        if (elements.progressBar) {
            elements.progressBar.style.width = `${percent}%`;
        }
        if (elements.progressPercent) {
            elements.progressPercent.textContent = `${percent}%`;
        }
    }

    /**
     * Affiche une notification
     */
    function showNotification(title, message, type = 'info') {
        const container = document.getElementById('notification-container');
        if (!container) return;

        const colors = {
            success: 'bg-success text-white',
            error: 'bg-error text-white',
            warning: 'bg-warning text-white',
            info: 'bg-primary text-white'
        };

        const icons = {
            success: 'check_circle',
            error: 'error',
            warning: 'warning',
            info: 'info'
        };

        const notif = document.createElement('div');
        notif.className = `pointer-events-auto animate-enter p-4 rounded-xl shadow-lg ${colors[type] || colors.info} max-w-sm`;
        notif.innerHTML = `
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined">${icons[type] || icons.info}</span>
                <div>
                    <p class="font-semibold">${title}</p>
                    <p class="text-sm opacity-90">${message}</p>
                </div>
            </div>
        `;

        container.appendChild(notif);

        // Auto-remove after 5s
        setTimeout(() => {
            notif.remove();
        }, 5000);
    }

    /**
     * Toggle theme
     */
    function toggleTheme() {
        const html = document.documentElement;
        const isDark = html.classList.contains('dark');
        
        if (isDark) {
            html.classList.remove('dark');
            html.classList.add('light');
        } else {
            html.classList.remove('light');
            html.classList.add('dark');
        }

        const icon = document.getElementById('theme-icon');
        if (icon) {
            icon.textContent = isDark ? 'dark_mode' : 'light_mode';
        }
    }

    /**
     * Toggle language
     */
    function toggleLanguage() {
        state.language = state.language === 'fr' ? 'en' : 'fr';
        
        const label = document.getElementById('lang-label');
        if (label) {
            label.textContent = state.language.toUpperCase();
        }

        log('Language changed to:', state.language);
    }

    // Exposer l'API publique
    window.chatbotRedesign = {
        init,
        openUploadModal,
        closeUploadModal,
        showNotification,
        state
    };

    // Initialiser au chargement du DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
