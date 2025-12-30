/**
 * Camera Capture Module - Chatbot Visa CI
 * Capture de documents via la caméra du mobile
 * 
 * @version 1.0.0
 */

class CameraCapture {
    /**
     * Constructeur
     * @param {Object} options - Options de configuration
     */
    constructor(options = {}) {
        this.config = {
            container: options.container || null,
            onCapture: options.onCapture || null,
            onError: options.onError || null,
            type: options.type || 'passport', // 'passport', 'photo', 'document'
            debug: options.debug || false
        };
        
        this.state = {
            stream: null,
            isActive: false,
            facingMode: 'environment', // 'user' for selfie, 'environment' for rear camera
            lastCapture: null
        };
        
        this.elements = {
            video: null,
            canvas: null,
            overlay: null
        };
        
        // Guide overlays par type
        this.overlayGuides = {
            passport: {
                aspectRatio: 1.5, // Largeur / Hauteur
                label: 'Placez la page biographique du passeport',
                icon: '📘'
            },
            photo: {
                aspectRatio: 0.75, // Portrait
                label: 'Centrez votre visage dans le cadre',
                icon: '📷'
            },
            document: {
                aspectRatio: 1.414, // A4
                label: 'Placez le document dans le cadre',
                icon: '📄'
            }
        };
    }
    
    /**
     * Vérifie si la caméra est supportée
     */
    static isSupported() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    }
    
    /**
     * Démarre la capture caméra
     */
    async start(container = null) {
        if (!CameraCapture.isSupported()) {
            this.handleError('La caméra n\'est pas supportée sur ce navigateur');
            return false;
        }
        
        if (container) {
            this.config.container = container;
        }
        
        try {
            // Créer l'interface
            this.createUI();
            
            // Obtenir le flux vidéo
            this.state.stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: this.state.facingMode,
                    width: { ideal: 1920 },
                    height: { ideal: 1080 }
                },
                audio: false
            });
            
            // Connecter au video element
            if (this.elements.video) {
                this.elements.video.srcObject = this.state.stream;
                await this.elements.video.play();
            }
            
            this.state.isActive = true;
            this.log('Caméra démarrée');
            return true;
            
        } catch (error) {
            this.handleError('Impossible d\'accéder à la caméra: ' + error.message);
            return false;
        }
    }
    
    /**
     * Arrête la capture
     */
    stop() {
        if (this.state.stream) {
            this.state.stream.getTracks().forEach(track => track.stop());
            this.state.stream = null;
        }
        
        this.state.isActive = false;
        
        // Nettoyer l'interface
        if (this.config.container) {
            this.config.container.innerHTML = '';
        }
        
        this.log('Caméra arrêtée');
    }
    
    /**
     * Crée l'interface utilisateur
     */
    createUI() {
        if (!this.config.container) return;
        
        const guide = this.overlayGuides[this.config.type] || this.overlayGuides.document;
        
        this.config.container.innerHTML = `
            <div class="camera-capture">
                <div class="camera-viewport">
                    <video class="camera-video" autoplay playsinline muted></video>
                    <div class="camera-overlay">
                        <div class="camera-guide" data-type="${this.config.type}">
                            <div class="guide-frame"></div>
                            <span class="guide-label">${guide.icon} ${guide.label}</span>
                        </div>
                    </div>
                </div>
                <div class="camera-controls">
                    <button type="button" class="camera-btn camera-btn-switch" aria-label="Changer de caméra">
                        🔄
                    </button>
                    <button type="button" class="camera-btn camera-btn-capture" aria-label="Capturer">
                        📸
                    </button>
                    <button type="button" class="camera-btn camera-btn-close" aria-label="Fermer">
                        ✕
                    </button>
                </div>
                <canvas class="camera-canvas" hidden></canvas>
            </div>
        `;
        
        // Référencer les éléments
        this.elements.video = this.config.container.querySelector('.camera-video');
        this.elements.canvas = this.config.container.querySelector('.camera-canvas');
        this.elements.overlay = this.config.container.querySelector('.camera-overlay');
        
        // Événements
        this.bindEvents();
    }
    
    /**
     * Lie les événements
     */
    bindEvents() {
        const container = this.config.container;
        if (!container) return;
        
        // Capture
        container.querySelector('.camera-btn-capture')?.addEventListener('click', () => {
            this.capture();
        });
        
        // Switch camera
        container.querySelector('.camera-btn-switch')?.addEventListener('click', () => {
            this.switchCamera();
        });
        
        // Close
        container.querySelector('.camera-btn-close')?.addEventListener('click', () => {
            this.stop();
        });
    }
    
    /**
     * Capture une image
     */
    capture() {
        if (!this.elements.video || !this.elements.canvas) {
            this.handleError('Impossible de capturer l\'image');
            return null;
        }
        
        const video = this.elements.video;
        const canvas = this.elements.canvas;
        const ctx = canvas.getContext('2d');
        
        // Configurer le canvas avec les dimensions de la vidéo
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        
        // Dessiner le frame actuel
        ctx.drawImage(video, 0, 0);
        
        // Convertir en blob/dataURL
        const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
        
        // Extraire la zone guidée si applicable
        const croppedDataUrl = this.cropToGuide(canvas, ctx);
        
        this.state.lastCapture = croppedDataUrl || dataUrl;
        
        // Callback
        if (this.config.onCapture) {
            this.config.onCapture({
                dataUrl: this.state.lastCapture,
                fullDataUrl: dataUrl,
                width: canvas.width,
                height: canvas.height,
                type: this.config.type
            });
        }
        
        this.log('Image capturée');
        return this.state.lastCapture;
    }
    
    /**
     * Découpe l'image selon le guide
     */
    cropToGuide(canvas, ctx) {
        const guide = this.overlayGuides[this.config.type];
        if (!guide) return null;
        
        // Calculer la zone de crop basée sur le guide overlay
        // Le guide occupe ~80% de la largeur du viewport
        const guideWidth = canvas.width * 0.8;
        const guideHeight = guideWidth / guide.aspectRatio;
        
        const cropX = (canvas.width - guideWidth) / 2;
        const cropY = (canvas.height - guideHeight) / 2;
        
        // Créer un nouveau canvas pour le crop
        const cropCanvas = document.createElement('canvas');
        cropCanvas.width = guideWidth;
        cropCanvas.height = guideHeight;
        
        const cropCtx = cropCanvas.getContext('2d');
        cropCtx.drawImage(
            canvas,
            cropX, cropY, guideWidth, guideHeight,
            0, 0, guideWidth, guideHeight
        );
        
        return cropCanvas.toDataURL('image/jpeg', 0.9);
    }
    
    /**
     * Change de caméra (avant/arrière)
     */
    async switchCamera() {
        this.state.facingMode = this.state.facingMode === 'user' ? 'environment' : 'user';
        
        // Arrêter le flux actuel
        if (this.state.stream) {
            this.state.stream.getTracks().forEach(track => track.stop());
        }
        
        // Redémarrer avec la nouvelle caméra
        try {
            this.state.stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: this.state.facingMode,
                    width: { ideal: 1920 },
                    height: { ideal: 1080 }
                },
                audio: false
            });
            
            if (this.elements.video) {
                this.elements.video.srcObject = this.state.stream;
                await this.elements.video.play();
            }
            
            this.log('Caméra changée:', this.state.facingMode);
            
        } catch (error) {
            this.handleError('Impossible de changer de caméra: ' + error.message);
        }
    }
    
    /**
     * Vérifie la qualité de l'image
     */
    checkImageQuality(dataUrl) {
        return new Promise((resolve) => {
            const img = new Image();
            img.onload = () => {
                const quality = {
                    isGood: true,
                    issues: [],
                    resolution: { width: img.width, height: img.height }
                };
                
                // Vérifier la résolution minimum
                if (img.width < 640 || img.height < 480) {
                    quality.isGood = false;
                    quality.issues.push('Résolution trop basse');
                }
                
                // Vérifier les proportions pour un passeport
                if (this.config.type === 'passport') {
                    const ratio = img.width / img.height;
                    if (ratio < 1.2 || ratio > 1.8) {
                        quality.issues.push('Proportions incorrectes pour un passeport');
                    }
                }
                
                resolve(quality);
            };
            img.onerror = () => {
                resolve({ isGood: false, issues: ['Image invalide'] });
            };
            img.src = dataUrl;
        });
    }
    
    /**
     * Gère les erreurs
     */
    handleError(message) {
        this.log('Erreur:', message);
        
        if (this.config.onError) {
            this.config.onError(message);
        }
    }
    
    /**
     * Log de debug
     */
    log(...args) {
        if (this.config.debug) {
            console.log('[Camera]', ...args);
        }
    }
}

// Exposer globalement
window.CameraCapture = CameraCapture;

