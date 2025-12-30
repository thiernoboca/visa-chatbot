/**
 * CelebrationManager - Micro-Animations and Celebrations
 *
 * Provides engaging visual feedback:
 * - Confetti effects for document validation
 * - Badge animations for milestones
 * - Toast notifications for success messages
 * - Cross-validation success effects
 * - Optional sound effects
 *
 * @version 6.0.0
 * @author Visa Chatbot Team
 */

/**
 * Document success messages by type
 */
const DOCUMENT_MESSAGES = {
    passport: {
        fr: 'Passeport verifie avec succes',
        en: 'Passport verified successfully'
    },
    ticket: {
        fr: 'Billet d\'avion valide',
        en: 'Flight ticket validated'
    },
    hotel: {
        fr: 'Reservation d\'hotel confirmee',
        en: 'Hotel reservation confirmed'
    },
    vaccination: {
        fr: 'Certificat de vaccination accepte',
        en: 'Vaccination certificate accepted'
    },
    invitation: {
        fr: 'Lettre d\'invitation verifiee',
        en: 'Invitation letter verified'
    },
    photo: {
        fr: 'Photo d\'identite validee',
        en: 'ID photo validated'
    },
    payment: {
        fr: 'Preuve de paiement confirmee',
        en: 'Payment proof confirmed'
    },
    residence_card: {
        fr: 'Carte de resident verifiee',
        en: 'Residence card verified'
    },
    verbal_note: {
        fr: 'Note verbale validee',
        en: 'Verbal note validated'
    }
};

/**
 * Milestone configurations
 */
const MILESTONES = {
    '50%': {
        icon: '🌟',
        message: { fr: 'Mi-parcours atteint !', en: 'Halfway there!' }
    },
    '75%': {
        icon: '🔥',
        message: { fr: 'Presque termine !', en: 'Almost done!' }
    },
    '100%': {
        icon: '🏆',
        message: { fr: 'Dossier complet !', en: 'Application complete!' }
    }
};

export class CelebrationManager {

    /**
     * Constructor
     * @param {Object} options Configuration options
     */
    constructor(options = {}) {
        this.language = options.language || 'fr';
        this.soundEnabled = options.soundEnabled !== false;
        this.confettiColors = ['#0D5C46', '#FFD700', '#FF6B35', '#FFFFFF', '#4CAF50'];
        this.confettiLoaded = false;

        this.init();
    }

    /**
     * Initialize celebration manager
     */
    init() {
        this.loadConfettiLibrary();
        this.bindEvents();
        this.createAudioContext();
    }

    /**
     * Load confetti library dynamically
     */
    loadConfettiLibrary() {
        if (window.confetti) {
            this.confettiLoaded = true;
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js';
        script.async = true;
        script.onload = () => {
            this.confettiLoaded = true;
        };
        document.head.appendChild(script);
    }

    /**
     * Create audio context for sounds
     */
    createAudioContext() {
        try {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        } catch (e) {
            console.warn('Audio context not available');
        }
    }

    /**
     * Bind event listeners
     */
    bindEvents() {
        document.addEventListener('trigger-celebration', (e) => {
            this.handleCelebration(e.detail);
        });
    }

    /**
     * Handle celebration event
     * @param {Object} detail Celebration details
     */
    handleCelebration(detail) {
        switch (detail.type) {
            case 'document':
                this.documentValidated(detail.docType);
                break;
            case 'milestone':
                this.milestoneReached(detail.milestone);
                break;
            case 'cross-validation':
                this.crossValidationSuccess();
                break;
            case 'prefill':
                this.prefillSuccess(detail.fieldsCount);
                break;
            default:
                console.warn('Unknown celebration type:', detail.type);
        }
    }

    /**
     * Document validated celebration
     * @param {string} docType Document type
     */
    documentValidated(docType) {
        // Confetti burst
        this.triggerConfetti({
            particleCount: 50,
            spread: 60,
            origin: { y: 0.6 },
            gravity: 1.2
        });

        // Toast notification
        const message = DOCUMENT_MESSAGES[docType]?.[this.language] ||
                        DOCUMENT_MESSAGES[docType]?.en ||
                        (this.language === 'fr' ? 'Document valide' : 'Document validated');

        this.showToast({
            icon: '✅',
            message: message,
            duration: 3000
        });

        // Sound effect
        this.playSuccessSound();
    }

    /**
     * Milestone reached celebration
     * @param {string} milestone Milestone identifier (50%, 75%, 100%)
     */
    milestoneReached(milestone) {
        const config = MILESTONES[milestone];
        if (!config) return;

        // Big confetti burst
        const particleCount = milestone === '100%' ? 200 : 100;
        this.triggerConfetti({
            particleCount: particleCount,
            spread: 100,
            origin: { y: 0.5 },
            gravity: 0.8,
            scalar: 1.2
        });

        // Multiple confetti bursts for 100%
        if (milestone === '100%') {
            setTimeout(() => {
                this.triggerConfetti({
                    particleCount: 100,
                    angle: 60,
                    spread: 55,
                    origin: { x: 0 }
                });
            }, 250);

            setTimeout(() => {
                this.triggerConfetti({
                    particleCount: 100,
                    angle: 120,
                    spread: 55,
                    origin: { x: 1 }
                });
            }, 400);
        }

        // Badge overlay
        this.showBadgeAnimation({
            icon: config.icon,
            message: config.message[this.language] || config.message.en
        });

        // Milestone sound
        this.playMilestoneSound();
    }

    /**
     * Cross-validation success celebration
     */
    crossValidationSuccess() {
        this.showToast({
            icon: '🔗',
            message: this.language === 'fr'
                ? 'Vos documents sont coherents entre eux ✨'
                : 'Your documents are consistent with each other ✨',
            duration: 4000
        });

        // Subtle confetti
        this.triggerConfetti({
            particleCount: 30,
            spread: 40,
            origin: { y: 0.7 },
            colors: ['#0D5C46', '#4CAF50']
        });
    }

    /**
     * Prefill success celebration
     * @param {number} fieldsCount Number of prefilled fields
     */
    prefillSuccess(fieldsCount) {
        this.showToast({
            icon: '✨',
            message: this.language === 'fr'
                ? `${fieldsCount} champs pre-remplis automatiquement`
                : `${fieldsCount} fields auto-filled`,
            duration: 3000
        });
    }

    /**
     * Trigger confetti animation
     * @param {Object} options Confetti options
     */
    triggerConfetti(options) {
        if (!this.confettiLoaded || typeof window.confetti !== 'function') {
            console.warn('Confetti library not loaded');
            return;
        }

        window.confetti({
            ...options,
            colors: options.colors || this.confettiColors,
            disableForReducedMotion: true
        });
    }

    /**
     * Show toast notification
     * @param {Object} config Toast configuration
     */
    showToast({ icon, message, duration = 3000 }) {
        // Remove existing toast
        const existingToast = document.querySelector('.celebration-toast');
        if (existingToast) {
            existingToast.remove();
        }

        // Create toast
        const toast = document.createElement('div');
        toast.className = 'celebration-toast animate-slide-in';
        toast.innerHTML = `
            <span class="toast-icon">${icon}</span>
            <span class="toast-message">${message}</span>
        `;

        document.body.appendChild(toast);

        // Auto-remove
        setTimeout(() => {
            toast.classList.remove('animate-slide-in');
            toast.classList.add('animate-slide-out');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    /**
     * Show badge animation for milestones
     * @param {Object} config Badge configuration
     */
    showBadgeAnimation({ icon, message }) {
        // Remove existing overlay
        const existing = document.querySelector('.badge-overlay');
        if (existing) {
            existing.remove();
        }

        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'badge-overlay animate-fade-in';
        overlay.innerHTML = `
            <div class="badge-container animate-bounce-in">
                <span class="badge-icon">${icon}</span>
                <span class="badge-message">${message}</span>
            </div>
        `;

        document.body.appendChild(overlay);

        // Click to dismiss
        overlay.addEventListener('click', () => {
            overlay.classList.add('animate-fade-out');
            setTimeout(() => overlay.remove(), 500);
        });

        // Auto-remove after delay
        setTimeout(() => {
            if (document.body.contains(overlay)) {
                overlay.classList.add('animate-fade-out');
                setTimeout(() => overlay.remove(), 500);
            }
        }, 3000);
    }

    /**
     * Play success sound
     */
    playSuccessSound() {
        if (!this.soundEnabled || !this.audioContext) return;

        // Resume audio context if suspended
        if (this.audioContext.state === 'suspended') {
            this.audioContext.resume();
        }

        try {
            const oscillator = this.audioContext.createOscillator();
            const gainNode = this.audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(this.audioContext.destination);

            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(880, this.audioContext.currentTime); // A5
            oscillator.frequency.setValueAtTime(1047, this.audioContext.currentTime + 0.1); // C6

            gainNode.gain.setValueAtTime(0.1, this.audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, this.audioContext.currentTime + 0.2);

            oscillator.start(this.audioContext.currentTime);
            oscillator.stop(this.audioContext.currentTime + 0.2);
        } catch (e) {
            // Ignore audio errors
        }
    }

    /**
     * Play milestone sound
     */
    playMilestoneSound() {
        if (!this.soundEnabled || !this.audioContext) return;

        if (this.audioContext.state === 'suspended') {
            this.audioContext.resume();
        }

        try {
            const notes = [523, 659, 784, 1047]; // C5, E5, G5, C6
            let time = this.audioContext.currentTime;

            notes.forEach((freq, i) => {
                const oscillator = this.audioContext.createOscillator();
                const gainNode = this.audioContext.createGain();

                oscillator.connect(gainNode);
                gainNode.connect(this.audioContext.destination);

                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(freq, time + i * 0.1);

                gainNode.gain.setValueAtTime(0.15, time + i * 0.1);
                gainNode.gain.exponentialRampToValueAtTime(0.01, time + i * 0.1 + 0.3);

                oscillator.start(time + i * 0.1);
                oscillator.stop(time + i * 0.1 + 0.3);
            });
        } catch (e) {
            // Ignore audio errors
        }
    }

    /**
     * Enable/disable sounds
     * @param {boolean} enabled Sound enabled state
     */
    setSoundEnabled(enabled) {
        this.soundEnabled = enabled;
        localStorage.setItem('soundEnabled', enabled.toString());
    }

    /**
     * Set language
     * @param {string} lang Language code (fr/en)
     */
    setLanguage(lang) {
        this.language = lang;
    }

    /**
     * Check if sounds are enabled
     * @returns {boolean}
     */
    isSoundEnabled() {
        const stored = localStorage.getItem('soundEnabled');
        return stored !== 'false';
    }

    /**
     * Show prefill highlight on fields
     * @param {Array<string>} fieldIds Field IDs to highlight
     */
    highlightPrefillFields(fieldIds) {
        fieldIds.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.closest('.form-field')?.classList.add('prefill-field');

                // Remove highlight after animation
                setTimeout(() => {
                    field.closest('.form-field')?.classList.remove('prefill-field');
                }, 5000);
            }
        });
    }

    /**
     * Show inline success indicator on element
     * @param {HTMLElement} element Target element
     */
    showInlineSuccess(element) {
        const indicator = document.createElement('span');
        indicator.className = 'inline-success-indicator animate-pop-in';
        indicator.innerHTML = '<span class="material-symbols-outlined">check_circle</span>';

        element.style.position = 'relative';
        element.appendChild(indicator);

        setTimeout(() => indicator.remove(), 2000);
    }

    /**
     * Dispose of resources
     */
    destroy() {
        if (this.audioContext) {
            this.audioContext.close();
        }
    }
}

// Default export
export default CelebrationManager;
