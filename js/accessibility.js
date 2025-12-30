/**
 * Accessibility Module - Chatbot Visa CI
 * Améliore l'accessibilité selon WCAG 2.1 AA
 * 
 * @version 1.0.0
 */

class AccessibilityManager {
    /**
     * Constructeur
     */
    constructor(options = {}) {
        this.config = {
            announceMessages: true,
            keyboardNavigation: true,
            focusManagement: true,
            reducedMotion: options.reducedMotion ?? this.prefersReducedMotion(),
            highContrast: options.highContrast ?? false,
            fontSize: options.fontSize ?? 'normal', // 'normal', 'large', 'larger'
            debug: options.debug || false
        };
        
        this.elements = {
            liveRegion: null,
            skipLink: null
        };
        
        this.init();
    }
    
    /**
     * Initialise le module
     */
    init() {
        this.createLiveRegion();
        this.createSkipLink();
        this.bindKeyboardEvents();
        this.applySettings();
        this.enhanceExistingElements();
        
        this.log('Accessibility manager initialisé');
    }
    
    /**
     * Crée la région live pour les annonces screen reader
     */
    createLiveRegion() {
        // Région pour les messages importants
        const assertive = document.createElement('div');
        assertive.id = 'a11y-assertive';
        assertive.setAttribute('role', 'alert');
        assertive.setAttribute('aria-live', 'assertive');
        assertive.setAttribute('aria-atomic', 'true');
        assertive.className = 'sr-only';
        
        // Région pour les mises à jour non urgentes
        const polite = document.createElement('div');
        polite.id = 'a11y-polite';
        polite.setAttribute('role', 'status');
        polite.setAttribute('aria-live', 'polite');
        polite.setAttribute('aria-atomic', 'true');
        polite.className = 'sr-only';
        
        document.body.appendChild(assertive);
        document.body.appendChild(polite);
        
        this.elements.liveRegion = { assertive, polite };
    }
    
    /**
     * Crée le lien "Skip to content"
     */
    createSkipLink() {
        const skipLink = document.createElement('a');
        skipLink.href = '#chatMessages';
        skipLink.className = 'skip-link';
        skipLink.textContent = 'Aller au contenu principal';
        
        skipLink.addEventListener('click', (e) => {
            e.preventDefault();
            const target = document.getElementById('chatMessages');
            if (target) {
                target.tabIndex = -1;
                target.focus();
            }
        });
        
        document.body.insertBefore(skipLink, document.body.firstChild);
        this.elements.skipLink = skipLink;
    }
    
    /**
     * Annonce un message aux lecteurs d'écran
     */
    announce(message, priority = 'polite') {
        if (!this.config.announceMessages) return;
        
        const region = this.elements.liveRegion[priority] || this.elements.liveRegion.polite;
        if (!region) return;
        
        // Vider puis remplir pour déclencher l'annonce
        region.textContent = '';
        setTimeout(() => {
            region.textContent = message;
        }, 50);
    }
    
    /**
     * Lie les événements clavier
     */
    bindKeyboardEvents() {
        if (!this.config.keyboardNavigation) return;
        
        document.addEventListener('keydown', (e) => {
            // Escape ferme les modals/overlays
            if (e.key === 'Escape') {
                this.handleEscape();
            }
            
            // Tab trap dans les modals
            if (e.key === 'Tab') {
                this.handleTabTrap(e);
            }
        });
        
        // Navigation clavier dans les quick actions
        document.getElementById('quickActions')?.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                e.preventDefault();
                this.focusNextQuickAction(1);
            } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                e.preventDefault();
                this.focusNextQuickAction(-1);
            }
        });
    }
    
    /**
     * Gère la touche Escape
     */
    handleEscape() {
        // Fermer le scanner passeport
        const scanner = document.getElementById('passportScannerOverlay');
        if (scanner && !scanner.hidden) {
            scanner.hidden = true;
            this.announce('Scanner fermé');
            return;
        }
        
        // Fermer la checklist documents
        const checklist = document.getElementById('documentChecklist');
        if (checklist?.classList.contains('open')) {
            checklist.classList.remove('open');
            this.announce('Liste de documents fermée');
        }
    }
    
    /**
     * Gère le focus trap dans les modals
     */
    handleTabTrap(e) {
        const modal = document.querySelector('.modal-open, [role="dialog"]:not([hidden])');
        if (!modal) return;
        
        const focusableElements = modal.querySelectorAll(
            'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"]), a[href]'
        );
        
        if (focusableElements.length === 0) return;
        
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];
        
        if (e.shiftKey && document.activeElement === firstElement) {
            e.preventDefault();
            lastElement.focus();
        } else if (!e.shiftKey && document.activeElement === lastElement) {
            e.preventDefault();
            firstElement.focus();
        }
    }
    
    /**
     * Navigation dans les quick actions
     */
    focusNextQuickAction(direction) {
        const container = document.getElementById('quickActions');
        const buttons = container?.querySelectorAll('.quick-action');
        if (!buttons || buttons.length === 0) return;
        
        const current = Array.from(buttons).indexOf(document.activeElement);
        let next = current + direction;
        
        if (next < 0) next = buttons.length - 1;
        if (next >= buttons.length) next = 0;
        
        buttons[next].focus();
    }
    
    /**
     * Améliore les éléments existants
     */
    enhanceExistingElements() {
        // Ajouter des rôles ARIA manquants
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.setAttribute('role', 'log');
            chatMessages.setAttribute('aria-label', 'Messages de conversation');
            chatMessages.setAttribute('aria-live', 'polite');
        }
        
        const chatInput = document.getElementById('chatInput');
        if (chatInput) {
            chatInput.setAttribute('aria-label', 'Votre message');
        }
        
        const btnSend = document.getElementById('btnSend');
        if (btnSend) {
            btnSend.setAttribute('aria-label', 'Envoyer le message');
        }
        
        // Rendre les quick actions navigables au clavier
        const quickActions = document.getElementById('quickActions');
        if (quickActions) {
            quickActions.setAttribute('role', 'group');
            quickActions.setAttribute('aria-label', 'Actions rapides');
        }
    }
    
    /**
     * Applique les paramètres d'accessibilité
     */
    applySettings() {
        const body = document.body;
        
        // Mode mouvement réduit
        body.classList.toggle('reduced-motion', this.config.reducedMotion);
        
        // Mode contraste élevé
        body.classList.toggle('high-contrast', this.config.highContrast);
        
        // Taille de police
        body.classList.remove('font-large', 'font-larger');
        if (this.config.fontSize === 'large') {
            body.classList.add('font-large');
        } else if (this.config.fontSize === 'larger') {
            body.classList.add('font-larger');
        }
    }
    
    /**
     * Vérifie la préférence système pour le mouvement réduit
     */
    prefersReducedMotion() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }
    
    /**
     * Active/désactive le mode contraste élevé
     */
    toggleHighContrast() {
        this.config.highContrast = !this.config.highContrast;
        this.applySettings();
        this.announce(this.config.highContrast ? 'Mode contraste élevé activé' : 'Mode contraste normal');
    }
    
    /**
     * Change la taille de police
     */
    setFontSize(size) {
        this.config.fontSize = size;
        this.applySettings();
        this.announce(`Taille de texte: ${size}`);
    }
    
    /**
     * Déplace le focus vers un élément
     */
    focusElement(element) {
        if (!element) return;
        
        // Rendre focusable si nécessaire
        if (element.tabIndex === undefined || element.tabIndex < 0) {
            element.tabIndex = -1;
        }
        
        element.focus();
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    /**
     * Annonce un nouveau message dans le chat
     */
    announceNewMessage(content, sender = 'assistant') {
        const prefix = sender === 'assistant' ? 'Assistant: ' : 'Vous: ';
        this.announce(prefix + content, 'polite');
    }
    
    /**
     * Annonce un changement d'étape
     */
    announceStepChange(stepName, stepNumber, totalSteps) {
        this.announce(`Étape ${stepNumber} sur ${totalSteps}: ${stepName}`, 'assertive');
    }
    
    /**
     * Log de debug
     */
    log(...args) {
        if (this.config.debug) {
            console.log('[A11y]', ...args);
        }
    }
}

// Exposer globalement
window.AccessibilityManager = AccessibilityManager;

