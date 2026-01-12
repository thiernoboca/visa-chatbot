/**
 * Premium UX Module
 * Handles animations, micro-interactions, and enhanced user experiences
 *
 * @version 1.0.0
 * @module PremiumUX
 */

class PremiumUX {
    constructor(options = {}) {
        this.options = {
            enableSounds: false,
            enableHaptics: true,
            celebrationColors: ['#FF6B00', '#009639', '#FFFFFF', '#D4AF37', '#3B82F6'],
            ...options
        };

        this.state = {
            currentOCRStep: 0
        };

        this.init();
    }

    init() {
        // Initialize ripple effects on buttons
        this.initRippleEffects();

        // Initialize micro-interactions
        this.initMicroInteractions();

        // Expose globally
        window.premiumUX = this;
    }

    // ==========================================
    // RIPPLE EFFECTS
    // ==========================================

    initRippleEffects() {
        document.addEventListener('click', (e) => {
            const target = e.target.closest('button, .ripple-container');
            if (target && !target.classList.contains('no-ripple')) {
                this.createRipple(e, target);
            }
        });
    }

    createRipple(event, element) {
        const rect = element.getBoundingClientRect();
        const ripple = document.createElement('span');
        const size = Math.max(rect.width, rect.height);
        const x = event.clientX - rect.left - size / 2;
        const y = event.clientY - rect.top - size / 2;

        ripple.style.cssText = `
            width: ${size}px;
            height: ${size}px;
            left: ${x}px;
            top: ${y}px;
        `;
        ripple.className = 'ripple';

        element.style.position = 'relative';
        element.style.overflow = 'hidden';
        element.appendChild(ripple);

        setTimeout(() => ripple.remove(), 600);
    }

    // ==========================================
    // MICRO-INTERACTIONS
    // ==========================================

    initMicroInteractions() {
        // Add hover effects to quick action buttons
        document.addEventListener('mouseenter', (e) => {
            if (e.target && e.target.classList && e.target.classList.contains('quick-action-btn')) {
                this.scaleUp(e.target, 1.02);
            }
        }, true);

        document.addEventListener('mouseleave', (e) => {
            if (e.target && e.target.classList && e.target.classList.contains('quick-action-btn')) {
                this.scaleUp(e.target, 1);
            }
        }, true);
    }

    scaleUp(element, scale) {
        element.style.transform = `scale(${scale})`;
    }

    // Message animations
    animateMessageSend(element) {
        element.classList.add('message-sending');
        setTimeout(() => element.classList.remove('message-sending'), 400);
    }

    animateMessageAppear(element) {
        element.classList.add('message-appear');
    }

    // Shake error
    shakeError(element) {
        element.classList.add('shake-error');
        if (this.options.enableHaptics && navigator.vibrate) {
            navigator.vibrate([50, 50, 50]);
        }
        setTimeout(() => element.classList.remove('shake-error'), 500);
    }

    // Success checkmark
    showSuccessCheckmark(container) {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'success-checkmark');
        svg.setAttribute('viewBox', '0 0 52 52');
        svg.innerHTML = `
            <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
            <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
        `;
        container.appendChild(svg);
        return svg;
    }

    // ==========================================
    // OCR PROCESSING MODAL
    // ==========================================

    showOCRProcessing() {
        const modal = document.createElement('div');
        modal.className = 'ocr-processing-modal';
        modal.id = 'ocrProcessingModal';
        modal.innerHTML = `
            <div class="ocr-processing-card">
                <!-- Scanner Animation -->
                <div class="ocr-scanner">
                    <div class="ocr-scanner-document">
                        <div class="ocr-scanner-photo"></div>
                        <div class="ocr-scanner-line"></div>
                        <div class="ocr-scanner-line"></div>
                        <div class="ocr-scanner-line"></div>
                    </div>
                    <div class="ocr-scanner-beam"></div>
                </div>

                <h3 style="text-align: center; margin-bottom: 0.5rem; font-size: 1.125rem; color: #1E293B;">
                    Analyse en cours...
                </h3>
                <p style="text-align: center; color: #64748B; font-size: 0.875rem; margin-bottom: 1.5rem;">
                    Notre IA lit votre passeport
                </p>

                <!-- Steps -->
                <div class="ocr-steps" id="ocrSteps">
                    <div class="ocr-step" data-step="0">
                        <div class="ocr-step-icon">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <div class="ocr-step-content">
                            <div class="ocr-step-title">Détection du document</div>
                            <div class="ocr-step-subtitle">Identification du type</div>
                        </div>
                    </div>
                    <div class="ocr-step" data-step="1">
                        <div class="ocr-step-icon">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
                            </svg>
                        </div>
                        <div class="ocr-step-content">
                            <div class="ocr-step-title">Lecture du MRZ</div>
                            <div class="ocr-step-subtitle">Zone de lecture automatique</div>
                        </div>
                    </div>
                    <div class="ocr-step" data-step="2">
                        <div class="ocr-step-icon">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div class="ocr-step-content">
                            <div class="ocr-step-title">Extraction des données</div>
                            <div class="ocr-step-subtitle">Nom, date, numéro...</div>
                        </div>
                    </div>
                    <div class="ocr-step" data-step="3">
                        <div class="ocr-step-icon">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ocr-step-content">
                            <div class="ocr-step-title">Vérification</div>
                            <div class="ocr-step-subtitle">Validation des informations</div>
                        </div>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="ocr-progress-bar">
                    <div class="ocr-progress-fill" id="ocrProgressFill" style="width: 0%"></div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        setTimeout(() => modal.classList.add('active'), 10);

        this.state.currentOCRStep = 0;
        this.updateOCRStep(0);

        return modal;
    }

    updateOCRStep(step) {
        const steps = document.querySelectorAll('#ocrSteps .ocr-step');
        const progressFill = document.getElementById('ocrProgressFill');

        steps.forEach((s, i) => {
            s.classList.remove('completed', 'active');
            if (i < step) {
                s.classList.add('completed');
            } else if (i === step) {
                s.classList.add('active');
            }
        });

        const progress = ((step + 1) / steps.length) * 100;
        if (progressFill) {
            progressFill.style.width = `${progress}%`;
        }

        this.state.currentOCRStep = step;
    }

    async simulateOCRProcess(onComplete) {
        const modal = this.showOCRProcessing();
        const steps = [
            { delay: 800, step: 0 },
            { delay: 1200, step: 1 },
            { delay: 1500, step: 2 },
            { delay: 1000, step: 3 }
        ];

        for (const { delay, step } of steps) {
            await this.wait(delay);
            this.updateOCRStep(step);
        }

        await this.wait(500);
        this.hideOCRProcessing();

        if (onComplete) onComplete();
    }

    hideOCRProcessing() {
        const modal = document.getElementById('ocrProcessingModal');
        if (modal) {
            modal.classList.remove('active');
            setTimeout(() => modal.remove(), 400);
        }
    }

    // ==========================================
    // PASSPORT COMPARISON MODAL
    // ==========================================

    showPassportComparison(passportImage, ocrData) {
        const modal = document.createElement('div');
        modal.className = 'passport-comparison-modal';
        modal.id = 'passportComparisonModal';

        const confidenceLevel = (confidence) => {
            if (confidence >= 0.9) return 'high';
            if (confidence >= 0.7) return 'medium';
            return 'low';
        };

        const confidenceIcon = (level) => {
            const icons = {
                high: '<svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>',
                medium: '<svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>',
                low: '<svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>'
            };
            return icons[level] || icons.medium;
        };

        modal.innerHTML = `
            <div class="passport-comparison-card">
                <!-- Header -->
                <div class="passport-modal-header">
                    <div class="passport-modal-title">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                        </svg>
                        <span>Vérification des données</span>
                    </div>
                    <button class="passport-modal-close" id="closePassportModal">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Comparison Grid -->
                <div class="passport-comparison-grid">
                    <!-- Document Side -->
                    <div class="passport-document-side">
                        <h3>Document source</h3>
                        <div class="passport-image-container" id="passportImageZoom">
                            <img src="${passportImage || 'data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 400 280\"><rect fill=\"%23e2e8f0\" width=\"400\" height=\"280\"/><text x=\"200\" y=\"140\" text-anchor=\"middle\" fill=\"%2394a3b8\" font-size=\"16\">Aperçu du document</text></svg>'}" alt="Passeport scanné">
                            <div class="passport-image-overlay">
                                <div class="passport-zoom-hint">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                                    </svg>
                                    <span>Cliquez pour agrandir</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Data Side -->
                    <div class="passport-data-side">
                        <h3>Données extraites</h3>

                        <div class="passport-field-group">
                            <div class="passport-field">
                                <label>Nom</label>
                                <div style="position: relative;">
                                    <input type="text" class="passport-field-input" id="pcSurname" value="${ocrData?.surname || ''}" data-original="${ocrData?.surname || ''}">
                                    <span class="confidence-badge ${confidenceLevel(ocrData?.confidence?.surname || 0.8)}">${confidenceIcon(confidenceLevel(ocrData?.confidence?.surname || 0.8))}</span>
                                </div>
                            </div>
                            <div class="passport-field">
                                <label>Prénoms</label>
                                <div style="position: relative;">
                                    <input type="text" class="passport-field-input" id="pcGivenNames" value="${ocrData?.given_names || ''}" data-original="${ocrData?.given_names || ''}">
                                    <span class="confidence-badge ${confidenceLevel(ocrData?.confidence?.given_names || 0.8)}">${confidenceIcon(confidenceLevel(ocrData?.confidence?.given_names || 0.8))}</span>
                                </div>
                            </div>
                        </div>

                        <div class="passport-field-group">
                            <div class="passport-field">
                                <label>N° Passeport</label>
                                <div style="position: relative;">
                                    <input type="text" class="passport-field-input" id="pcPassportNumber" value="${ocrData?.passport_number || ''}" data-original="${ocrData?.passport_number || ''}">
                                    <span class="confidence-badge ${confidenceLevel(ocrData?.confidence?.passport_number || 0.95)}">${confidenceIcon(confidenceLevel(ocrData?.confidence?.passport_number || 0.95))}</span>
                                </div>
                            </div>
                            <div class="passport-field">
                                <label>Nationalité</label>
                                <div style="position: relative;">
                                    <input type="text" class="passport-field-input" id="pcNationality" value="${ocrData?.nationality || ''}" data-original="${ocrData?.nationality || ''}">
                                    <span class="confidence-badge ${confidenceLevel(ocrData?.confidence?.nationality || 0.9)}">${confidenceIcon(confidenceLevel(ocrData?.confidence?.nationality || 0.9))}</span>
                                </div>
                            </div>
                        </div>

                        <div class="passport-field-group">
                            <div class="passport-field">
                                <label>Date de naissance</label>
                                <div style="position: relative;">
                                    <input type="date" class="passport-field-input" id="pcDOB" value="${ocrData?.date_of_birth || ''}" data-original="${ocrData?.date_of_birth || ''}">
                                </div>
                            </div>
                            <div class="passport-field">
                                <label>Date d'expiration</label>
                                <div style="position: relative;">
                                    <input type="date" class="passport-field-input" id="pcExpiry" value="${ocrData?.expiry_date || ''}" data-original="${ocrData?.expiry_date || ''}">
                                </div>
                            </div>
                        </div>

                        <div class="passport-field-group">
                            <div class="passport-field">
                                <label>Sexe</label>
                                <div style="position: relative;">
                                    <select class="passport-field-input" id="pcSex" data-original="${ocrData?.sex || ''}">
                                        <option value="">Sélectionner</option>
                                        <option value="M" ${ocrData?.sex === 'M' ? 'selected' : ''}>Masculin</option>
                                        <option value="F" ${ocrData?.sex === 'F' ? 'selected' : ''}>Féminin</option>
                                    </select>
                                </div>
                            </div>
                            <div class="passport-field">
                                <label>Lieu de naissance</label>
                                <div style="position: relative;">
                                    <input type="text" class="passport-field-input" id="pcPOB" value="${ocrData?.place_of_birth || ''}" data-original="${ocrData?.place_of_birth || ''}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="passport-modal-footer">
                    <button class="btn-secondary" id="pcEditBtn">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Corriger
                    </button>
                    <button class="btn-primary" id="pcConfirmBtn">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Confirmer les données
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        setTimeout(() => modal.classList.add('active'), 10);

        // Add event listeners
        this.initPassportModalEvents(modal);

        return modal;
    }

    initPassportModalEvents(modal) {
        // Close button
        modal.querySelector('#closePassportModal').addEventListener('click', () => {
            this.hidePassportComparison();
        });

        // Click outside to close
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.hidePassportComparison();
            }
        });

        // Track field changes
        modal.querySelectorAll('.passport-field-input').forEach(input => {
            input.addEventListener('input', () => {
                const original = input.dataset.original;
                if (input.value !== original) {
                    input.classList.add('edited');
                } else {
                    input.classList.remove('edited');
                }
            });
        });

        // Confirm button
        modal.querySelector('#pcConfirmBtn').addEventListener('click', () => {
            const data = this.getPassportModalData();
            this.hidePassportComparison();
            if (this.onPassportConfirm) {
                this.onPassportConfirm(data);
            }
        });

        // Edit button - focus first field
        modal.querySelector('#pcEditBtn').addEventListener('click', () => {
            modal.querySelector('#pcSurname').focus();
            modal.querySelector('#pcSurname').select();
        });
    }

    getPassportModalData() {
        const modal = document.getElementById('passportComparisonModal');
        if (!modal) return null;

        return {
            surname: modal.querySelector('#pcSurname').value,
            given_names: modal.querySelector('#pcGivenNames').value,
            passport_number: modal.querySelector('#pcPassportNumber').value,
            nationality: modal.querySelector('#pcNationality').value,
            date_of_birth: modal.querySelector('#pcDOB').value,
            expiry_date: modal.querySelector('#pcExpiry').value,
            sex: modal.querySelector('#pcSex').value,
            place_of_birth: modal.querySelector('#pcPOB').value,
            wasEdited: modal.querySelectorAll('.passport-field-input.edited').length > 0
        };
    }

    hidePassportComparison() {
        const modal = document.getElementById('passportComparisonModal');
        if (modal) {
            modal.classList.remove('active');
            setTimeout(() => modal.remove(), 400);
        }
    }

    // ==========================================
    // CELEBRATION
    // ==========================================

    celebrate(message = 'Félicitations !', subtitle = 'Votre demande a été soumise avec succès.') {
        // Create confetti
        this.createConfetti();

        // Show success card
        const card = document.createElement('div');
        card.className = 'success-celebration-card';
        card.innerHTML = `
            <div class="celebration-icon">
                <svg width="40" height="40" fill="white" viewBox="0 0 24 24">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                </svg>
            </div>
            <h2>${message}</h2>
            <p>${subtitle}</p>
            <button class="btn-primary" onclick="this.parentElement.remove()" style="margin-top: 1rem;">
                Continuer
            </button>
        `;

        document.body.appendChild(card);

        // Haptic feedback
        if (this.options.enableHaptics && navigator.vibrate) {
            navigator.vibrate([100, 50, 100, 50, 200]);
        }
    }

    createConfetti() {
        const overlay = document.createElement('div');
        overlay.className = 'celebration-overlay';
        document.body.appendChild(overlay);

        const shapes = ['circle', 'square', 'triangle'];
        const colors = this.options.celebrationColors;

        for (let i = 0; i < 100; i++) {
            const confetti = document.createElement('div');
            const shape = shapes[Math.floor(Math.random() * shapes.length)];
            confetti.className = `confetti ${shape}`;
            confetti.style.left = `${Math.random() * 100}%`;
            confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.animationDelay = `${Math.random() * 0.5}s`;
            confetti.style.animationDuration = `${2 + Math.random() * 2}s`;

            if (shape === 'triangle') {
                confetti.style.borderBottomColor = colors[Math.floor(Math.random() * colors.length)];
            }

            overlay.appendChild(confetti);
        }

        setTimeout(() => overlay.remove(), 4000);
    }

    // ==========================================
    // CAMERA CAPTURE UI
    // ==========================================

    showCameraCapture(options = {}) {
        const overlay = document.createElement('div');
        overlay.className = 'camera-capture-overlay';
        overlay.id = 'cameraCaptureOverlay';
        overlay.innerHTML = `
            <div class="camera-video-container">
                <video id="cameraVideo" autoplay playsinline></video>

                <!-- AR Guide Frame -->
                <div class="camera-guide-frame" id="cameraGuideFrame">
                    <div class="camera-guide-corner top-left"></div>
                    <div class="camera-guide-corner top-right"></div>
                    <div class="camera-guide-corner bottom-left"></div>
                    <div class="camera-guide-corner bottom-right"></div>
                </div>

                <!-- Feedback Message -->
                <div class="camera-feedback" id="cameraFeedback">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Positionnez votre passeport dans le cadre</span>
                </div>
            </div>

            <!-- Controls -->
            <div class="camera-controls">
                <button class="camera-btn-close" onclick="window.premiumUX.hideCameraCapture()" style="width: 48px; height: 48px; border-radius: 50%; background: rgba(255,255,255,0.2); border: none; color: white; cursor: pointer;">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
                <button class="camera-capture-btn" id="cameraCaptureBtn"></button>
                <button style="width: 48px; height: 48px; border-radius: 50%; background: rgba(255,255,255,0.2); border: none; color: white; cursor: pointer;" id="cameraSwitchBtn">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </button>
            </div>
        `;

        document.body.appendChild(overlay);
        this.initCameraCapture(options);

        return overlay;
    }

    async initCameraCapture(options) {
        try {
            const video = document.getElementById('cameraVideo');
            const stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: options.facingMode || 'environment',
                    width: { ideal: 1920 },
                    height: { ideal: 1080 }
                }
            });
            video.srcObject = stream;
            this.cameraStream = stream;

            // Capture button
            document.getElementById('cameraCaptureBtn').addEventListener('click', () => {
                this.capturePhoto(options.onCapture);
            });

        } catch (error) {
            console.error('Camera error:', error);
            this.updateCameraFeedback('Erreur d\'accès à la caméra', 'warning');
        }
    }

    updateCameraFeedback(message, type = '') {
        const feedback = document.getElementById('cameraFeedback');
        if (feedback) {
            feedback.querySelector('span').textContent = message;
            feedback.className = `camera-feedback ${type}`;
        }
    }

    setGuideFrameAligned(aligned) {
        const frame = document.getElementById('cameraGuideFrame');
        if (frame) {
            frame.classList.toggle('aligned', aligned);
        }
    }

    capturePhoto(callback) {
        const video = document.getElementById('cameraVideo');
        const btn = document.getElementById('cameraCaptureBtn');
        btn.classList.add('capturing');

        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);

        const imageData = canvas.toDataURL('image/jpeg', 0.9);

        setTimeout(() => {
            btn.classList.remove('capturing');
            this.hideCameraCapture();
            if (callback) callback(imageData);
        }, 300);
    }

    hideCameraCapture() {
        if (this.cameraStream) {
            this.cameraStream.getTracks().forEach(track => track.stop());
        }
        const overlay = document.getElementById('cameraCaptureOverlay');
        if (overlay) {
            overlay.remove();
        }
    }

    // ==========================================
    // UTILITIES
    // ==========================================

    wait(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    // Public API for external modules
    setOnPassportConfirm(callback) {
        this.onPassportConfirm = callback;
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Check if we should auto-init
    if (!window.premiumUXInitialized) {
        window.premiumUX = new PremiumUX();
        window.premiumUXInitialized = true;
    }
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PremiumUX;
}
