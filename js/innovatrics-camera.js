/**
 * Innovatrics Camera Capture Module
 * Solution de capture document avec détection automatique
 * Basée sur Innovatrics DOT (Document Object Tracking) v7.7.0
 * 
 * @version 1.0.0
 * @author Ambassade de Côte d'Ivoire
 */

class InnovatricsCameraCapture {
    constructor(options = {}) {
        this.config = {
            container: options.container || null,
            onCapture: options.onCapture || null,
            onError: options.onError || null,
            onStateChange: options.onStateChange || null,
            type: options.type || 'passport',
            debug: options.debug || false,
            language: options.language || 'fr',
            assetsPath: options.assetsPath || '/hunyuanocr/visa-chatbot'
        };

        this.state = {
            mode: null, // 'desktop' or 'mobile'
            isActive: false,
            sessionId: null,
            pollingInterval: null,
            capturedImage: null
        };

        this.elements = {
            modal: null,
            captureElement: null,
            captureUI: null,
            container: null
        };

        // Instructions localisées
        this.i18n = {
            fr: {
                choiceTitle: 'Comment souhaitez-vous scanner votre document ?',
                choiceTitlePhoto: 'Comment souhaitez-vous prendre votre photo ?',
                mobileOption: 'Scanner sur mobile',
                mobileOptionPhoto: 'Prendre sur mobile',
                mobileDesc: 'Scannez le QR code avec votre téléphone',
                mobileDescPhoto: 'Utilisez la caméra de votre téléphone',
                desktopOption: 'Utiliser cet appareil',
                desktopDesc: 'Utilisez la caméra de votre ordinateur',
                qrTitle: 'Scannez ce QR code avec votre téléphone',
                qrWaiting: 'En attente de connexion mobile...',
                qrConnected: 'Mobile connecté!',
                qrCaptured: 'Image reçue du mobile!',
                back: 'Retour',
                close: 'Fermer',
                httpsWarning: 'La caméra mobile nécessite HTTPS. Utilisez ngrok pour créer un tunnel sécurisé.',
                instructions: {
                    document_not_present: 'Positionnez le document',
                    document_centering: 'Centrez le document',
                    document_too_far: 'Rapprochez le document',
                    sharpness_too_low: 'Image floue, stabilisez',
                    brightness_too_low: 'Pas assez de lumière',
                    brightness_too_high: 'Trop de lumière',
                    hotspots_present: 'Évitez les reflets',
                    candidate_selection: 'Ne bougez plus...'
                },
                faceInstructions: {
                    face_not_present: 'Positionnez votre visage',
                    face_centering: 'Centrez votre visage',
                    face_too_close: 'Éloignez-vous',
                    face_too_far: 'Rapprochez-vous',
                    sharpness_too_low: 'Image floue',
                    brightness_too_low: 'Plus de lumière',
                    brightness_too_high: 'Moins de lumière',
                    candidate_selection: 'Ne bougez plus...'
                }
            },
            en: {
                choiceTitle: 'How would you like to scan your document?',
                choiceTitlePhoto: 'How would you like to take your photo?',
                mobileOption: 'Scan on mobile',
                mobileOptionPhoto: 'Take on mobile',
                mobileDesc: 'Scan the QR code with your phone',
                mobileDescPhoto: 'Use your phone camera',
                desktopOption: 'Use this device',
                desktopDesc: 'Use your computer\'s camera',
                qrTitle: 'Scan this QR code with your phone',
                qrWaiting: 'Waiting for mobile connection...',
                qrConnected: 'Mobile connected!',
                qrCaptured: 'Image received from mobile!',
                back: 'Back',
                close: 'Close',
                httpsWarning: 'Mobile camera requires HTTPS. Use ngrok to create a secure tunnel.',
                instructions: {
                    document_not_present: 'Position the document',
                    document_centering: 'Center the document',
                    document_too_far: 'Move document closer',
                    sharpness_too_low: 'Blurry image, hold steady',
                    brightness_too_low: 'Not enough light',
                    brightness_too_high: 'Too much light',
                    hotspots_present: 'Avoid reflections',
                    candidate_selection: 'Hold still...'
                },
                faceInstructions: {
                    face_not_present: 'Position your face',
                    face_centering: 'Center your face',
                    face_too_close: 'Move back',
                    face_too_far: 'Come closer',
                    sharpness_too_low: 'Image blurry',
                    brightness_too_low: 'More light needed',
                    brightness_too_high: 'Less light needed',
                    candidate_selection: 'Hold still...'
                }
            }
        };
    }

    /**
     * Vérifie si c'est une capture de visage (photo d'identité)
     */
    isFaceCapture() {
        return this.config.type === 'photo' || this.config.type === 'face';
    }

    /**
     * Obtient la traduction
     */
    t(key) {
        const lang = this.config.language;
        const keys = key.split('.');
        let value = this.i18n[lang] || this.i18n.fr;
        for (const k of keys) {
            value = value?.[k];
        }
        return value || key;
    }

    /**
     * Log de debug
     */
    log(...args) {
        if (this.config.debug) {
            console.log('[InnovatricsCam]', ...args);
        }
    }

    /**
     * Vérifie si Innovatrics est chargé
     */
    static isLoaded() {
        return !!customElements.get('x-dot-document-auto-capture');
    }

    /**
     * Ouvre le modal de choix Desktop/Mobile
     */
    openChoiceModal() {
        this.log('Opening choice modal');
        
        // Créer le modal
        const modal = document.createElement('div');
        modal.id = 'innovatrics-camera-modal';
        modal.className = 'fixed inset-0 bg-gray-900/80 backdrop-blur-sm z-[200] flex items-center justify-center p-4';
        modal.innerHTML = this.renderChoiceScreen();
        
        document.body.appendChild(modal);
        this.elements.modal = modal;

        // Bind events
        modal.querySelector('.choice-desktop-btn')?.addEventListener('click', () => this.startDesktopCapture());
        modal.querySelector('.choice-mobile-btn')?.addEventListener('click', () => this.showMobileQR());
        modal.querySelector('.modal-close-btn')?.addEventListener('click', () => this.close());
        modal.addEventListener('click', (e) => {
            if (e.target === modal) this.close();
        });

        this.state.isActive = true;
    }

    /**
     * Rendu de l'écran de choix
     */
    renderChoiceScreen() {
        const isFace = this.isFaceCapture();
        const icon = isFace ? 'face' : 'document_scanner';
        const title = isFace ? this.t('choiceTitlePhoto') : this.t('choiceTitle');
        const mobileLabel = isFace ? this.t('mobileOptionPhoto') : this.t('mobileOption');
        const mobileDesc = isFace ? this.t('mobileDescPhoto') : this.t('mobileDesc');
        
        return `
            <div class="glass-panel w-full max-w-xl rounded-3xl p-6 sm:p-8 relative animate-enter">
                <button class="modal-close-btn absolute top-4 right-4 p-2 rounded-full hover:bg-gray-100/50 dark:hover:bg-white/10 transition-colors text-gray-500">
                    <span class="material-symbols-outlined">close</span>
                </button>

                <div class="text-center mb-8">
                    <div class="size-16 rounded-2xl bg-primary/10 flex items-center justify-center mx-auto mb-4 text-primary">
                        <span class="material-symbols-outlined text-3xl">${icon}</span>
                    </div>
                    <h3 class="text-xl font-display font-bold text-gray-900 dark:text-white mb-2">
                        ${title}
                    </h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Mobile Option -->
                    <button class="choice-mobile-btn flex flex-col items-center gap-3 p-6 rounded-2xl border-2 border-gray-200 dark:border-gray-700 hover:border-primary hover:bg-primary/5 transition-all group">
                        <div class="size-14 rounded-xl bg-primary/10 flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-all">
                            <span class="material-symbols-outlined text-2xl">smartphone</span>
                        </div>
                        <div class="text-center">
                            <span class="block font-semibold text-gray-900 dark:text-white">${mobileLabel}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">${mobileDesc}</span>
                        </div>
                    </button>

                    <!-- Desktop Option -->
                    <button class="choice-desktop-btn flex flex-col items-center gap-3 p-6 rounded-2xl border-2 border-gray-200 dark:border-gray-700 hover:border-primary hover:bg-primary/5 transition-all group">
                        <div class="size-14 rounded-xl bg-primary/10 flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-all">
                            <span class="material-symbols-outlined text-2xl">${isFace ? 'videocam' : 'laptop'}</span>
                        </div>
                        <div class="text-center">
                            <span class="block font-semibold text-gray-900 dark:text-white">${this.t('desktopOption')}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">${this.t('desktopDesc')}</span>
                        </div>
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Démarre la capture Desktop
     */
    async startDesktopCapture() {
        this.log('Starting desktop capture, type:', this.config.type);
        this.state.mode = 'desktop';

        if (!this.elements.modal) return;

        // Remplacer le contenu du modal selon le type
        const modalContent = this.elements.modal.querySelector('.glass-panel');
        if (modalContent) {
            if (this.isFaceCapture()) {
                modalContent.innerHTML = this.renderFaceCaptureScreen();
            } else {
                modalContent.innerHTML = this.renderDesktopCaptureScreen();
            }
        }

        // Attendre que le DOM soit mis à jour
        await new Promise(resolve => setTimeout(resolve, 100));

        // Configurer la capture Innovatrics selon le type
        if (this.isFaceCapture()) {
            this.initFaceCapture();
        } else {
            this.initInnovatricsCapture();
        }

        // Bind events
        this.elements.modal.querySelector('.back-btn')?.addEventListener('click', () => this.showChoiceScreen());
        this.elements.modal.querySelector('.modal-close-btn')?.addEventListener('click', () => this.close());
    }

    /**
     * Rendu de l'écran de capture Desktop
     */
    renderDesktopCaptureScreen() {
        return `
            <button class="modal-close-btn absolute top-4 right-4 p-2 rounded-full hover:bg-gray-100/50 dark:hover:bg-white/10 transition-colors text-gray-500 z-20">
                <span class="material-symbols-outlined">close</span>
            </button>

            <button class="back-btn absolute top-4 left-4 p-2 rounded-full hover:bg-gray-100/50 dark:hover:bg-white/10 transition-colors text-gray-500 z-20 flex items-center gap-1 text-sm">
                <span class="material-symbols-outlined text-lg">arrow_back</span>
                ${this.t('back')}
            </button>

            <div class="pt-12">
                <style>
                    /* === Styles identiques à debug-innovatrics.html === */
                    #innovatrics-camera-container {
                        position: relative;
                        width: 100%;
                        /* Laisser la vidéo définir la hauteur naturellement */
                        background: #000;
                        border-radius: 8px;
                        overflow: hidden;
                    }
                    /* Le composant capture doit remplir le conteneur */
                    #innovatrics-camera-container x-dot-document-auto-capture,
                    #innovatrics-document-capture {
                        width: 100%;
                        display: block;
                    }
                    /* L'UI doit être en position absolute pour se superposer */
                    #innovatrics-camera-container x-dot-document-auto-capture-ui,
                    #innovatrics-document-capture-ui {
                        position: absolute !important;
                        top: 0 !important;
                        left: 0 !important;
                        width: 100% !important;
                        height: 100% !important;
                        z-index: 10 !important;
                        pointer-events: auto !important;
                    }
                    /* S'assurer que le Shadow DOM du composant UI est visible */
                    #innovatrics-document-capture-ui::part(container),
                    x-dot-document-auto-capture-ui::part(container) {
                        position: absolute !important;
                        inset: 0 !important;
                    }
                </style>
                <div id="innovatrics-camera-container">
                    <x-dot-document-auto-capture id="innovatrics-document-capture"></x-dot-document-auto-capture>
                    <x-dot-document-auto-capture-ui id="innovatrics-document-capture-ui"></x-dot-document-auto-capture-ui>

                    <!-- Loading overlay -->
                    <div id="innovatrics-loading" class="absolute inset-0 bg-gray-900/80 flex flex-col items-center justify-center z-30">
                        <div class="size-12 border-4 border-primary/30 border-t-primary rounded-full animate-spin mb-4"></div>
                        <p class="text-white text-sm">Initialisation de la caméra...</p>
                    </div>
                </div>

                <div id="innovatrics-instruction" class="mt-4 p-3 bg-primary/10 rounded-xl text-center">
                    <p class="text-sm font-medium text-primary">${this.t('instructions.document_not_present')}</p>
                </div>

                <div id="innovatrics-captured-preview" class="hidden mt-4">
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <p class="text-xs text-gray-500 mb-2">Image capturée</p>
                            <img id="captured-image-preview" src="" alt="Captured" class="w-full rounded-lg border-2 border-success">
                        </div>
                    </div>
                    <button id="use-captured-image-btn" class="btn-premium w-full mt-4 py-3 rounded-xl font-semibold">
                        <span class="material-symbols-outlined mr-2">check</span>
                        Utiliser cette image
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Initialise la capture Innovatrics
     */
    initInnovatricsCapture() {
        const captureElement = document.getElementById('innovatrics-document-capture');
        const captureUI = document.getElementById('innovatrics-document-capture-ui');
        const container = document.getElementById('innovatrics-camera-container');
        const loadingOverlay = document.getElementById('innovatrics-loading');

        if (!captureElement) {
            this.log('Capture element not found');
            this.handleError('Composant de capture non trouvé');
            return;
        }

        this.elements.captureElement = captureElement;
        this.elements.captureUI = captureUI;
        this.elements.container = container;

        // Configurer l'UI
        if (captureUI) {
            captureUI.props = {
                placeholder: 'id-solid-rounded',
                showCameraButtons: true,
                showDetectionLayer: false,
                backdropColor: 'rgba(0, 0, 0, 0.4)',
                styleTarget: container,
                theme: {
                    colors: {
                        instructionColor: 'white',
                        instructionColorSuccess: '#10816A',
                        instructionTextColor: '#333',
                        placeholderColor: 'white',
                        placeholderColorSuccess: '#10816A'
                    }
                },
                instructions: this.t('instructions')
            };

            // Forward events
            const eventsToForward = [
                'document-auto-capture:state-changed',
                'document-auto-capture:detected-document-changed',
                'document-auto-capture:instruction-changed',
                'document-auto-capture:video-element-size'
            ];

            eventsToForward.forEach(eventName => {
                captureElement.addEventListener(eventName, (e) => {
                    document.dispatchEvent(new CustomEvent(eventName, {
                        detail: e.detail,
                        bubbles: true
                    }));

                    if (eventName === 'document-auto-capture:instruction-changed') {
                        this.updateInstruction(e.detail?.instructionCode);
                    }
                });
            });
        }

        // Configuration de la caméra
        const cameraOptions = {
            // CRITIQUE: Chemin vers les assets WASM
            // Le composant ajoute automatiquement "dot-assets/" au chemin
            assetsDirectoryPath: this.config.assetsPath,

            styleTarget: container,
            cameraFacing: 'user',
            captureMode: 'AUTO_CAPTURE',
            candidateSelectionDurationMillis: 2000,
            sessionToken: 'session-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9),

            onPhotoTaken: async (imageData, content) => {
                this.log('Photo captured!', imageData);
                await this.handleCapturedImage(imageData);
            },

            onError: (error) => {
                this.log('Camera error:', error);
                if (loadingOverlay) loadingOverlay.classList.add('hidden');
                this.handleError(error?.message || 'Erreur de caméra');
            },

            thresholds: {
                confidenceThreshold: 0.85,
                sharpnessThreshold: 0.6,
                brightnessLowThreshold: 0.25,
                brightnessHighThreshold: 0.9,
                hotspotsScoreThreshold: 0.08,
                outOfBoundsThreshold: 0.05,
                sizeSmallThreshold: 0.25
            }
        };

        try {
            captureElement.cameraOptions = cameraOptions;

            // Cacher le loading après un délai
            setTimeout(() => {
                if (loadingOverlay) loadingOverlay.classList.add('hidden');
            }, 2000);

        } catch (e) {
            this.log('Error setting camera options:', e);
            this.handleError('Erreur d\'initialisation de la caméra');
        }
    }

    /**
     * Met à jour l'instruction affichée
     */
    updateInstruction(code) {
        const instructionEl = document.getElementById('innovatrics-instruction');
        if (!instructionEl) return;

        const instructionKey = this.isFaceCapture() ? 'faceInstructions' : 'instructions';
        const defaultKey = this.isFaceCapture() ? 'face_not_present' : 'document_not_present';
        const instruction = this.t(`${instructionKey}.${code}`) || this.t(`${instructionKey}.${defaultKey}`);
        instructionEl.querySelector('p').textContent = instruction;

        // Changer la couleur selon l'instruction
        if (code === 'candidate_selection') {
            instructionEl.classList.remove('bg-primary/10');
            instructionEl.classList.add('bg-success/10');
            instructionEl.querySelector('p').classList.remove('text-primary');
            instructionEl.querySelector('p').classList.add('text-success');
        } else {
            instructionEl.classList.add('bg-primary/10');
            instructionEl.classList.remove('bg-success/10');
            instructionEl.querySelector('p').classList.add('text-primary');
            instructionEl.querySelector('p').classList.remove('text-success');
        }
    }

    // ========================================
    // FACE CAPTURE METHODS
    // ========================================

    /**
     * Rendu de l'écran de capture Face (photo d'identité)
     */
    renderFaceCaptureScreen() {
        return `
            <button class="modal-close-btn absolute top-4 right-4 p-2 rounded-full hover:bg-gray-100/50 dark:hover:bg-white/10 transition-colors text-gray-500 z-20">
                <span class="material-symbols-outlined">close</span>
            </button>

            <button class="back-btn absolute top-4 left-4 p-2 rounded-full hover:bg-gray-100/50 dark:hover:bg-white/10 transition-colors text-gray-500 z-20 flex items-center gap-1 text-sm">
                <span class="material-symbols-outlined text-lg">arrow_back</span>
                ${this.t('back')}
            </button>

            <div class="pt-12 px-4 pb-4">
                <style>
                    /* === Styles identiques aux pages de debug Innovatrics === */
                    #face-camera-container {
                        position: relative;
                        width: 100%;
                        /* Laisser la vidéo définir la hauteur naturellement comme dans debug */
                        background: #000;
                        border-radius: 12px;
                        overflow: hidden;
                    }
                    /* Le composant capture doit remplir le conteneur */
                    #face-camera-container x-dot-face-auto-capture,
                    #face-auto-capture {
                        width: 100%;
                        display: block;
                    }
                    /* L'UI doit être en position absolute pour se superposer */
                    #face-camera-container x-dot-face-auto-capture-ui,
                    #face-auto-capture-ui {
                        position: absolute !important;
                        top: 0 !important;
                        left: 0 !important;
                        width: 100% !important;
                        height: 100% !important;
                        z-index: 10 !important;
                        pointer-events: auto !important;
                    }
                    /* S'assurer que le Shadow DOM du composant UI est visible */
                    #face-auto-capture-ui::part(container),
                    x-dot-face-auto-capture-ui::part(container) {
                        position: absolute !important;
                        inset: 0 !important;
                    }
                </style>
                <div id="face-camera-container">
                    <x-dot-face-auto-capture id="face-auto-capture"></x-dot-face-auto-capture>
                    <x-dot-face-auto-capture-ui id="face-auto-capture-ui"></x-dot-face-auto-capture-ui>

                    <!-- Loading overlay -->
                    <div id="face-loading" class="absolute inset-0 bg-gray-900/80 flex flex-col items-center justify-center z-30">
                        <div class="size-12 border-4 border-primary/30 border-t-primary rounded-full animate-spin mb-4"></div>
                        <p class="text-white text-sm">Initialisation de la caméra...</p>
                    </div>
                </div>

                <div id="innovatrics-instruction" class="mt-4 p-3 bg-primary/10 rounded-xl text-center">
                    <p class="text-sm font-medium text-primary">${this.t('faceInstructions.face_not_present')}</p>
                </div>

                <!-- Info box -->
                <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-xl flex gap-3">
                    <span class="material-symbols-outlined text-blue-500 text-xl">info</span>
                    <p class="text-xs text-blue-700 dark:text-blue-300">
                        ${this.config.language === 'fr' 
                            ? 'Positionnez votre visage au centre de l\'écran. L\'image sera automatiquement recadrée au format photo d\'identité.' 
                            : 'Position your face in the center of the screen. The image will be automatically cropped to ID photo format.'}
                    </p>
                </div>

                <div id="innovatrics-captured-preview" class="hidden mt-4">
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <p class="text-xs text-gray-500 mb-2">${this.config.language === 'fr' ? 'Photo capturée' : 'Captured photo'}</p>
                            <img id="captured-image-preview" src="" alt="Captured" class="w-full rounded-lg border-2 border-success">
                        </div>
                        <div class="flex-1" id="cropped-preview-container">
                            <p class="text-xs text-gray-500 mb-2">${this.config.language === 'fr' ? 'Format ID' : 'ID Format'}</p>
                            <img id="cropped-image-preview" src="" alt="Cropped" class="w-full rounded-lg border-2 border-primary">
                        </div>
                    </div>
                    <button id="use-captured-image-btn" class="btn-premium w-full mt-4 py-3 rounded-xl font-semibold">
                        <span class="material-symbols-outlined mr-2">check</span>
                        ${this.config.language === 'fr' ? 'Utiliser cette photo' : 'Use this photo'}
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Initialise la capture de visage Innovatrics
     */
    initFaceCapture() {
        const captureElement = document.getElementById('face-auto-capture');
        const captureUI = document.getElementById('face-auto-capture-ui');
        const container = document.getElementById('face-camera-container');
        const loadingOverlay = document.getElementById('face-loading');

        if (!captureElement) {
            this.log('Face capture element not found');
            this.handleError('Composant de capture visage non trouvé');
            return;
        }

        this.elements.captureElement = captureElement;
        this.elements.captureUI = captureUI;
        this.elements.container = container;

        // Configurer l'UI Face (configuration identique à debug-face-innovatrics.html)
        if (captureUI) {
            captureUI.props = {
                // CRITIQUE: Utiliser 'circle-solid' comme dans la page de debug
                // Valeurs valides: 'circle-solid', 'circle-dash', 'circle-dot', etc.
                placeholder: 'circle-solid',
                showCameraButtons: true,
                showDetectionLayer: true,  // Activer le calque de détection (coins verts)
                backdropColor: 'rgba(0, 0, 0, 0.5)',
                styleTarget: container,
                theme: {
                    colors: {
                        instructionColor: 'white',
                        instructionColorSuccess: '#10816A',
                        instructionTextColor: '#333',
                        placeholderColor: 'white',
                        placeholderColorSuccess: '#10816A'
                    }
                },
                instructions: this.t('faceInstructions')
            };

            // Forward events
            const eventsToForward = [
                'face-auto-capture:state-changed',
                'face-auto-capture:face-detected',
                'face-auto-capture:instruction-changed'
            ];

            eventsToForward.forEach(eventName => {
                captureElement.addEventListener(eventName, (e) => {
                    document.dispatchEvent(new CustomEvent(eventName, {
                        detail: e.detail,
                        bubbles: true
                    }));

                    if (eventName === 'face-auto-capture:instruction-changed') {
                        this.updateInstruction(e.detail?.instructionCode);
                    }
                });
            });
        }

        // Configuration de la caméra pour le visage
        const cameraOptions = {
            // CRITIQUE: Chemin vers les assets WASM
            // Le composant ajoute automatiquement "dot-assets/" au chemin
            assetsDirectoryPath: this.config.assetsPath,

            styleTarget: container,
            cameraFacing: 'user', // Caméra frontale pour selfie
            captureMode: 'AUTO_CAPTURE',
            candidateSelectionDurationMillis: 1500,
            sessionToken: 'face-session-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9),

            onPhotoTaken: async (imageData, content) => {
                this.log('Face photo captured!', imageData);
                await this.handleFaceCapturedImage(imageData);
            },

            onError: (error) => {
                this.log('Face camera error:', error);
                if (loadingOverlay) loadingOverlay.classList.add('hidden');
                this.handleError(error?.message || 'Erreur de caméra');
            },

            thresholds: {
                confidenceThreshold: 0.9,
                sharpnessThreshold: 0.5,
                brightnessLowThreshold: 0.2,
                brightnessHighThreshold: 0.95,
                sizeSmallThreshold: 0.15,
                sizeLargeThreshold: 0.85
            }
        };

        try {
            captureElement.cameraOptions = cameraOptions;

            // Cacher le loading après un délai
            setTimeout(() => {
                if (loadingOverlay) loadingOverlay.classList.add('hidden');
            }, 2000);

        } catch (e) {
            this.log('Error setting face camera options:', e);
            this.handleError('Erreur d\'initialisation de la caméra');
        }
    }

    /**
     * Gère l'image de visage capturée
     */
    async handleFaceCapturedImage(imageData) {
        if (!imageData?.image) {
            this.handleError('Image non valide');
            return;
        }

        try {
            // Recadrer au format photo d'identité
            const croppedImage = await this.cropToIDPhotoFormat(
                imageData.image,
                imageData?.data?.detection
            );

            this.state.capturedImage = croppedImage;

            // Afficher la preview avec les deux versions
            const previewContainer = document.getElementById('innovatrics-captured-preview');
            const previewImage = document.getElementById('captured-image-preview');
            const croppedPreview = document.getElementById('cropped-image-preview');

            if (previewContainer && previewImage) {
                const originalUrl = URL.createObjectURL(imageData.image);
                const croppedUrl = URL.createObjectURL(croppedImage);
                
                previewImage.src = originalUrl;
                if (croppedPreview) {
                    croppedPreview.src = croppedUrl;
                }
                previewContainer.classList.remove('hidden');

                // Bind event pour utiliser l'image recadrée
                document.getElementById('use-captured-image-btn')?.addEventListener('click', () => {
                    this.useImage(croppedImage, croppedUrl);
                });
            }

        } catch (error) {
            this.log('Error processing face image:', error);
            // Utiliser l'image originale en cas d'erreur
            this.state.capturedImage = imageData.image;
            
            const imageUrl = URL.createObjectURL(imageData.image);
            const previewContainer = document.getElementById('innovatrics-captured-preview');
            const previewImage = document.getElementById('captured-image-preview');
            const croppedContainer = document.getElementById('cropped-preview-container');

            if (previewContainer && previewImage) {
                previewImage.src = imageUrl;
                if (croppedContainer) croppedContainer.classList.add('hidden');
                previewContainer.classList.remove('hidden');

                document.getElementById('use-captured-image-btn')?.addEventListener('click', () => {
                    this.useImage(imageData.image, imageUrl);
                });
            }
        }
    }

    /**
     * Recadre l'image au format photo d'identité (35x45mm, ratio 7:9)
     */
    async cropToIDPhotoFormat(imageBlob, detection) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => {
                try {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');

                    // Dimensions standard photo d'identité (ratio 7:9)
                    const targetWidth = 413; // 35mm @ 300dpi
                    const targetHeight = 531; // 45mm @ 300dpi
                    const ratio = targetWidth / targetHeight;

                    canvas.width = targetWidth;
                    canvas.height = targetHeight;

                    // Calculer le recadrage centré sur le visage
                    let cropX, cropY, cropWidth, cropHeight;

                    if (detection?.topLeft && detection?.bottomRight) {
                        // Si on a les coordonnées du visage
                        const faceX = detection.topLeft.x * img.width;
                        const faceY = detection.topLeft.y * img.height;
                        const faceWidth = (detection.bottomRight.x - detection.topLeft.x) * img.width;
                        const faceHeight = (detection.bottomRight.y - detection.topLeft.y) * img.height;

                        // Le visage doit occuper environ 70% de la hauteur
                        const targetFaceHeight = targetHeight * 0.7;
                        const scale = targetFaceHeight / faceHeight;
                        
                        cropHeight = img.height / scale * (targetHeight / (img.height / scale));
                        cropWidth = cropHeight * ratio;

                        // Centrer sur le visage avec un peu plus d'espace en haut
                        const faceCenterX = faceX + faceWidth / 2;
                        const faceCenterY = faceY + faceHeight / 2;
                        
                        cropX = faceCenterX - cropWidth / 2;
                        cropY = faceCenterY - cropHeight * 0.45; // 45% du centre vers le haut

                        // Ajuster pour rester dans les limites de l'image
                        cropX = Math.max(0, Math.min(cropX, img.width - cropWidth));
                        cropY = Math.max(0, Math.min(cropY, img.height - cropHeight));
                    } else {
                        // Recadrage par défaut: centré avec ratio correct
                        const imgRatio = img.width / img.height;
                        
                        if (imgRatio > ratio) {
                            // Image plus large: ajuster par hauteur
                            cropHeight = img.height;
                            cropWidth = cropHeight * ratio;
                            cropX = (img.width - cropWidth) / 2;
                            cropY = 0;
                        } else {
                            // Image plus haute: ajuster par largeur
                            cropWidth = img.width;
                            cropHeight = cropWidth / ratio;
                            cropX = 0;
                            cropY = (img.height - cropHeight) / 2;
                        }
                    }

                    // Dessiner avec fond blanc
                    ctx.fillStyle = '#FFFFFF';
                    ctx.fillRect(0, 0, targetWidth, targetHeight);

                    // Dessiner l'image recadrée
                    ctx.drawImage(
                        img,
                        cropX, cropY, cropWidth, cropHeight,
                        0, 0, targetWidth, targetHeight
                    );

                    // Convertir en blob
                    canvas.toBlob(
                        (blob) => resolve(blob),
                        'image/jpeg',
                        0.92
                    );
                } catch (error) {
                    reject(error);
                }
            };

            img.onerror = () => reject(new Error('Failed to load image'));
            img.src = URL.createObjectURL(imageBlob);
        });
    }

    /**
     * Gère l'image capturée
     */
    async handleCapturedImage(imageData) {
        if (!imageData?.image) {
            this.handleError('Image non valide');
            return;
        }

        try {
            // Traiter l'image (rognage et amélioration)
            const processedImage = await this.processImage(
                imageData.image,
                imageData?.data?.detection,
                imageData?.data?.imageResolution
            );

            this.state.capturedImage = processedImage;

            // Afficher la preview
            const previewContainer = document.getElementById('innovatrics-captured-preview');
            const previewImage = document.getElementById('captured-image-preview');

            if (previewContainer && previewImage) {
                const imageUrl = URL.createObjectURL(processedImage);
                previewImage.src = imageUrl;
                previewContainer.classList.remove('hidden');

                // Bind event pour utiliser l'image
                document.getElementById('use-captured-image-btn')?.addEventListener('click', () => {
                    this.useImage(processedImage, imageUrl);
                });
            }

        } catch (error) {
            this.log('Error processing image:', error);
            // Utiliser l'image originale en cas d'erreur
            this.state.capturedImage = imageData.image;
            
            const imageUrl = URL.createObjectURL(imageData.image);
            const previewContainer = document.getElementById('innovatrics-captured-preview');
            const previewImage = document.getElementById('captured-image-preview');

            if (previewContainer && previewImage) {
                previewImage.src = imageUrl;
                previewContainer.classList.remove('hidden');

                document.getElementById('use-captured-image-btn')?.addEventListener('click', () => {
                    this.useImage(imageData.image, imageUrl);
                });
            }
        }
    }

    /**
     * Traite et améliore l'image
     */
    async processImage(imageBlob, detection, imageResolution) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => {
                try {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');

                    let cropX = 0, cropY = 0;
                    let cropWidth = img.width;
                    let cropHeight = img.height;

                    // Si on a des coordonnées de détection, rogner l'image
                    if (detection?.topLeft && detection?.bottomRight) {
                        const allCoordsNormalized = 
                            detection.topLeft.x <= 1 && detection.topLeft.y <= 1 &&
                            detection.bottomRight.x <= 1 && detection.bottomRight.y <= 1;

                        let x0, y0, x2, y2;

                        if (allCoordsNormalized) {
                            x0 = detection.topLeft.x * img.width;
                            y0 = detection.topLeft.y * img.height;
                            x2 = detection.bottomRight.x * img.width;
                            y2 = detection.bottomRight.y * img.height;
                        } else {
                            x0 = detection.topLeft.x;
                            y0 = detection.topLeft.y;
                            x2 = detection.bottomRight.x;
                            y2 = detection.bottomRight.y;
                        }

                        const marginX = (x2 - x0) * 0.02;
                        const marginY = (y2 - y0) * 0.02;

                        cropX = Math.max(0, x0 - marginX);
                        cropY = Math.max(0, y0 - marginY);
                        cropWidth = Math.min(img.width - cropX, x2 - x0 + marginX * 2);
                        cropHeight = Math.min(img.height - cropY, y2 - y0 + marginY * 2);
                    }

                    canvas.width = cropWidth;
                    canvas.height = cropHeight;

                    ctx.imageSmoothingEnabled = true;
                    ctx.imageSmoothingQuality = 'high';

                    ctx.drawImage(
                        img,
                        cropX, cropY, cropWidth, cropHeight,
                        0, 0, cropWidth, cropHeight
                    );

                    // Amélioration du contraste
                    const imageData = ctx.getImageData(0, 0, cropWidth, cropHeight);
                    const data = imageData.data;
                    const contrastFactor = 1.15;

                    for (let i = 0; i < data.length; i += 4) {
                        data[i] = Math.min(255, Math.max(0, (data[i] - 128) * contrastFactor + 128));
                        data[i + 1] = Math.min(255, Math.max(0, (data[i + 1] - 128) * contrastFactor + 128));
                        data[i + 2] = Math.min(255, Math.max(0, (data[i + 2] - 128) * contrastFactor + 128));
                    }

                    ctx.putImageData(imageData, 0, 0);

                    canvas.toBlob((blob) => {
                        if (blob) {
                            resolve(blob);
                        } else {
                            reject(new Error('Erreur lors de la conversion'));
                        }
                    }, 'image/jpeg', 0.95);

                } catch (error) {
                    reject(error);
                }
            };

            img.onerror = () => reject(new Error('Erreur de chargement de l\'image'));
            img.src = URL.createObjectURL(imageBlob);
        });
    }

    /**
     * Utilise l'image capturée
     */
    useImage(imageBlob, imageUrl) {
        this.log('Using captured image');

        if (this.config.onCapture) {
            // Convertir en base64 pour le callback
            const reader = new FileReader();
            reader.onloadend = () => {
                this.config.onCapture({
                    blob: imageBlob,
                    dataUrl: reader.result,
                    url: imageUrl,
                    type: this.config.type,
                    mode: this.state.mode
                });
                this.close();
            };
            reader.readAsDataURL(imageBlob);
        } else {
            this.close();
        }
    }

    /**
     * Affiche l'écran QR Code pour mobile
     */
    showMobileQR() {
        this.log('Showing mobile QR screen');
        this.state.mode = 'mobile';

        // Générer un ID de session
        this.state.sessionId = 'session-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);

        if (!this.elements.modal) return;

        const modalContent = this.elements.modal.querySelector('.glass-panel');
        if (modalContent) {
            modalContent.innerHTML = this.renderMobileQRScreen();
        }

        // Générer le QR code
        this.generateQRCode();

        // Démarrer le polling
        this.startMobilePolling();

        // Bind events
        this.elements.modal.querySelector('.back-btn')?.addEventListener('click', () => this.showChoiceScreen());
        this.elements.modal.querySelector('.modal-close-btn')?.addEventListener('click', () => this.close());
    }

    /**
     * Rendu de l'écran QR Code
     */
    renderMobileQRScreen() {
        const isHTTPS = window.location.protocol === 'https:';
        
        return `
            <button class="modal-close-btn absolute top-4 right-4 p-2 rounded-full hover:bg-gray-100/50 dark:hover:bg-white/10 transition-colors text-gray-500 z-20">
                <span class="material-symbols-outlined">close</span>
            </button>

            <button class="back-btn absolute top-4 left-4 p-2 rounded-full hover:bg-gray-100/50 dark:hover:bg-white/10 transition-colors text-gray-500 z-20 flex items-center gap-1 text-sm">
                <span class="material-symbols-outlined text-lg">arrow_back</span>
                ${this.t('back')}
            </button>

            <div class="pt-12 text-center">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">${this.t('qrTitle')}</h3>

                ${!isHTTPS ? `
                <div class="mb-4 p-3 bg-warning/10 rounded-xl text-warning text-sm">
                    <span class="material-symbols-outlined text-lg align-middle mr-1">warning</span>
                    ${this.t('httpsWarning')}
                </div>
                ` : ''}

                <div id="qr-code-container" class="inline-block bg-white p-4 rounded-2xl mb-4">
                    <div class="size-48 flex items-center justify-center">
                        <div class="size-8 border-4 border-primary/30 border-t-primary rounded-full animate-spin"></div>
                    </div>
                </div>

                <div id="mobile-status" class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-warning/10 text-warning text-sm">
                    <span class="size-2 rounded-full bg-current animate-pulse"></span>
                    ${this.t('qrWaiting')}
                </div>

                <div id="mobile-captured-image" class="hidden mt-6">
                    <img id="mobile-preview-image" src="" alt="Captured" class="max-w-full max-h-48 mx-auto rounded-lg border-2 border-success mb-4">
                    <button id="use-mobile-image-btn" class="btn-premium px-6 py-3 rounded-xl font-semibold">
                        <span class="material-symbols-outlined mr-2">check</span>
                        Utiliser cette image
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Génère le QR Code
     */
    generateQRCode() {
        const container = document.getElementById('qr-code-container');
        if (!container) return;

        // Construire l'URL mobile selon le type de capture
        let origin = window.location.origin;
        const mobilePage = this.isFaceCapture() ? 'mobile-face-capture.html' : 'mobile-capture.html';
        const mobileUrl = `${origin}${this.config.assetsPath}/${mobilePage}?session=${this.state.sessionId}`;

        this.log('Mobile URL:', mobileUrl);

        // Utiliser la librairie QRCode si disponible
        if (typeof QRCode !== 'undefined') {
            container.innerHTML = '';
            const qrDiv = document.createElement('div');
            qrDiv.style.cssText = 'display: inline-block; background: white; padding: 10px; border-radius: 8px;';
            container.appendChild(qrDiv);

            try {
                new QRCode(qrDiv, {
                    text: mobileUrl,
                    width: 180,
                    height: 180,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
            } catch (e) {
                this.log('QR generation error:', e);
                container.innerHTML = `<p class="text-error">Erreur de génération QR</p>`;
            }
        } else {
            // Fallback: afficher le lien
            container.innerHTML = `
                <div class="p-4 text-center">
                    <p class="text-sm text-gray-500 mb-2">Ouvrez ce lien sur mobile:</p>
                    <a href="${mobileUrl}" target="_blank" class="text-primary text-xs break-all">${mobileUrl}</a>
                </div>
            `;
        }
    }

    /**
     * Démarre le polling pour la capture mobile
     */
    startMobilePolling() {
        this.state.pollingInterval = setInterval(async () => {
            try {
                const capturedData = localStorage.getItem('mobile-capture-' + this.state.sessionId);
                if (capturedData) {
                    localStorage.removeItem('mobile-capture-' + this.state.sessionId);
                    this.handleMobileCapturedImage(capturedData);
                    this.stopMobilePolling();
                }
            } catch (e) {
                this.log('Polling error:', e);
            }
        }, 2000);
    }

    /**
     * Arrête le polling mobile
     */
    stopMobilePolling() {
        if (this.state.pollingInterval) {
            clearInterval(this.state.pollingInterval);
            this.state.pollingInterval = null;
        }
    }

    /**
     * Gère l'image reçue du mobile
     */
    handleMobileCapturedImage(imageBase64) {
        this.log('Received mobile image');

        // Mettre à jour le statut
        const statusEl = document.getElementById('mobile-status');
        if (statusEl) {
            statusEl.className = 'inline-flex items-center gap-2 px-4 py-2 rounded-full bg-success/10 text-success text-sm';
            statusEl.innerHTML = `<span class="material-symbols-outlined">check_circle</span> ${this.t('qrCaptured')}`;
        }

        // Afficher l'image
        const imageContainer = document.getElementById('mobile-captured-image');
        const previewImage = document.getElementById('mobile-preview-image');

        if (imageContainer && previewImage) {
            previewImage.src = imageBase64;
            imageContainer.classList.remove('hidden');

            // Bind event
            document.getElementById('use-mobile-image-btn')?.addEventListener('click', () => {
                // Convertir base64 en blob
                fetch(imageBase64)
                    .then(res => res.blob())
                    .then(blob => {
                        this.useImage(blob, imageBase64);
                    });
            });
        }
    }

    /**
     * Retourne à l'écran de choix
     */
    showChoiceScreen() {
        this.stopMobilePolling();
        this.stopCapture();

        if (this.elements.modal) {
            const modalContent = this.elements.modal.querySelector('.glass-panel');
            if (modalContent) {
                modalContent.innerHTML = this.renderChoiceScreen().replace(/<div class="glass-panel[^>]*>|<\/div>$/g, '');
            }
        }

        // Re-bind events
        this.elements.modal?.querySelector('.choice-desktop-btn')?.addEventListener('click', () => this.startDesktopCapture());
        this.elements.modal?.querySelector('.choice-mobile-btn')?.addEventListener('click', () => this.showMobileQR());
        this.elements.modal?.querySelector('.modal-close-btn')?.addEventListener('click', () => this.close());
    }

    /**
     * Arrête la capture
     */
    stopCapture() {
        if (this.elements.captureElement) {
            try {
                if (typeof this.elements.captureElement.stop === 'function') {
                    this.elements.captureElement.stop();
                }
                this.elements.captureElement.cameraOptions = null;
            } catch (e) {
                this.log('Error stopping capture:', e);
            }
        }
    }

    /**
     * Ferme le modal
     */
    close() {
        this.log('Closing modal');
        this.stopMobilePolling();
        this.stopCapture();

        if (this.elements.modal) {
            this.elements.modal.remove();
            this.elements.modal = null;
        }

        this.state.isActive = false;
        this.state.mode = null;

        if (this.config.onStateChange) {
            this.config.onStateChange({ isActive: false });
        }
    }

    /**
     * Gère les erreurs
     */
    handleError(message) {
        this.log('Error:', message);

        if (this.config.onError) {
            this.config.onError(message);
        }

        // Afficher une notification si disponible
        if (typeof window.chatbot?.showNotification === 'function') {
            window.chatbot.showNotification(message, 'error');
        }
    }
}

// Exposer globalement
window.InnovatricsCameraCapture = InnovatricsCameraCapture;

