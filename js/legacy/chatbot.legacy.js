/**
 * Chatbot Visa CI - Client principal v2.0
 * Expérience conversationnelle dynamique avec persona Aya
 * 
 * @version 2.0.0
 */

class VisaChatbot {
    /**
     * Persona du chatbot
     */
    static PERSONA = {
        name: 'Aya',
        avatar: '🇨🇮',
        greetings: ['Akwaba !', 'Bienvenue !', 'Salut !'],
        encouragements: ['C\'est super !', 'On avance bien !', 'Parfait !'],
        celebrations: ['🎉 Bravo !', '✨ Excellent !', '🌟 Magnifique !']
    };
    
    /**
     * Constructeur
     * @param {Object} options - Options de configuration
     */
    constructor(options = {}) {
        this.config = {
            apiEndpoint: options.apiEndpoint || 'php/chat-handler.php',
            ocrEndpoint: options.ocrEndpoint || '../passport-ocr-module/php/api-handler.php',
            debug: options.debug || false,
            initialSessionId: options.initialSessionId || null,
            enableMicroInteractions: options.enableMicroInteractions ?? true,
            enableTypewriter: options.enableTypewriter ?? true,
            typewriterSpeed: options.typewriterSpeed || 15
        };
        
        this.state = {
            sessionId: null,
            currentStep: 'welcome',
            workflowCategory: null,
            isTyping: false,
            pendingInputType: null,
            highestStepReached: 0,
            stepsInfo: {},
            userFirstName: null,
            messageQueue: [],
            isProcessingQueue: false
        };
        
        this.elements = {};
        this._stepStartTime = null;
        this._microInteractions = null;
        this.init();
    }
    
    /**
     * Initialise le chatbot
     */
    async init() {
        this.bindElements();
        this.bindEvents();

        // Initialiser les micro-interactions
        if (this.config.enableMicroInteractions && window.microInteractions) {
            this._microInteractions = window.microInteractions;
            this._microInteractions.setupInputFocus();
        }

        // Initialiser le bouton scroll-to-bottom
        this.initScrollToBottom();

        // Initialiser swipe-to-close pour les modals
        if (this.elements.passportScannerOverlay) {
            this.setupSwipeToClose(this.elements.passportScannerOverlay);
        }

        // Initialiser la session
        await this.initSession();

        // Initialiser CoherenceUI
        if (window.CoherenceUI) {
            this._coherenceUI = new CoherenceUI({
                container: this.elements.chatMessages,
                apiEndpoint: 'php/coherence-validator-api.php',
                debug: this.config.debug,
                onAction: (actionType, docType) => {
                    this.log('Coherence action:', actionType, docType);
                    if (actionType === 'upload' && docType) {
                        this.triggerDocumentUpload(docType);
                    }
                }
            });
            window.coherenceUI = this._coherenceUI;
            this.log('CoherenceUI initialisé');
        }

        this.log('Chatbot initialisé avec persona:', VisaChatbot.PERSONA.name);
    }
    
    /**
     * Lie les éléments DOM
     */
    bindElements() {
        this.elements = {
            chatMessages: document.getElementById('chatMessages'),
            chatWelcome: document.getElementById('chatWelcome'),
            quickActions: document.getElementById('quickActions'),
            chatInput: document.getElementById('chatInput'),
            btnSend: document.getElementById('btnSend'),
            btnAttachment: document.getElementById('btnAttachment'),
            stepNav: document.getElementById('stepNav'),
            stepLabel: document.getElementById('stepLabel'),
            stepCount: document.getElementById('stepCount'),
            progressFill: document.getElementById('progressFill'),
            notificationContainer: document.getElementById('notificationContainer'),
            generalFileInput: document.getElementById('generalFileInput'),
            // Scroll to bottom button
            scrollToBottomBtn: document.getElementById('scrollToBottomBtn'),
            // Scanner passeport
            passportScannerOverlay: document.getElementById('passportScannerOverlay'),
            btnCloseScanner: document.getElementById('btnCloseScanner'),
            passportUploadZone: document.getElementById('passportUploadZone'),
            passportFileInput: document.getElementById('passportFileInput'),
            scannerPreview: document.getElementById('scannerPreview'),
            passportPreviewImg: document.getElementById('passportPreviewImg'),
            btnScanPassport: document.getElementById('btnScanPassport'),
            btnRetryScan: document.getElementById('btnRetryScan'),
            scannerProcessing: document.getElementById('scannerProcessing')
        };
    }
    
    /**
     * Lie les événements
     */
    bindEvents() {
        // Envoi de message
        this.elements.btnSend?.addEventListener('click', () => this.sendMessage());
        this.elements.chatInput?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        
        // Pièces jointes
        this.elements.btnAttachment?.addEventListener('click', () => {
            if (this.state.currentStep === 'passport') {
                this.openPassportScanner();
            } else {
                this.elements.generalFileInput?.click();
            }
        });
        
        this.elements.generalFileInput?.addEventListener('change', (e) => {
            this.handleFileUpload(e.target.files[0]);
        });
        
        // Scanner passeport - Using native label/input, no click handler needed
        this.elements.btnCloseScanner?.addEventListener('click', () => this.closePassportScanner());
        
        // File input change - triggered by native label click
        this.elements.passportFileInput?.addEventListener('change', (e) => {
            this.previewPassport(e.target.files[0]);
        });
        this.elements.btnScanPassport?.addEventListener('click', () => this.scanPassport());
        this.elements.btnRetryScan?.addEventListener('click', () => this.resetScanner());
        
        // Drag & drop pour le scanner
        this.elements.passportUploadZone?.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.currentTarget.classList.add('drag-over');
        });
        this.elements.passportUploadZone?.addEventListener('dragleave', (e) => {
            e.currentTarget.classList.remove('drag-over');
        });
        this.elements.passportUploadZone?.addEventListener('drop', (e) => {
            e.preventDefault();
            e.currentTarget.classList.remove('drag-over');
            const file = e.dataTransfer.files[0];
            if (file) this.previewPassport(file);
        });
        
        // Navigation par step-dots (clic sur étapes accessibles)
        this.elements.stepNav?.addEventListener('click', (e) => {
            const stepDot = e.target.closest('.step-dot');
            if (stepDot && !stepDot.disabled) {
                const targetStep = stepDot.dataset.step;
                if (targetStep && targetStep !== this.state.currentStep) {
                    this.navigateToStep(targetStep);
                }
            }
        });
        
        // Navigation clavier pour les step-dots
        this.elements.stepNav?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                const stepDot = e.target.closest('.step-dot');
                if (stepDot && !stepDot.disabled) {
                    e.preventDefault();
                    const targetStep = stepDot.dataset.step;
                    if (targetStep) {
                        this.navigateToStep(targetStep);
                    }
                }
            }
        });
    }
    
    /**
     * Initialise la session avec le backend
     */
    async initSession() {
        try {
            // Support for multi-device sync: use provided session ID if available
            let initUrl = `${this.config.apiEndpoint}?action=init`;
            if (this.config.initialSessionId) {
                initUrl += `&session_id=${encodeURIComponent(this.config.initialSessionId)}`;
            }
            
            const response = await fetch(initUrl, { credentials: 'include' });
            const data = await response.json();
            
            if (data.success) {
                this.state.sessionId = data.data.session_id;
                this.state.currentStep = data.data.step_info?.current || 'welcome';
                
                // Initialiser Analytics et A/B Testing
                this.initAnalyticsAndABTesting();
                
                // Masquer l'écran de bienvenue
                if (this.elements.chatWelcome) {
                    this.elements.chatWelcome.style.display = 'none';
                }
                
                // Afficher le message initial
                this.addBotMessage(data.data.message.content);
                
                // Afficher les quick actions
                this.showQuickActions(data.data.quick_actions || []);
                
                // Mettre à jour la progression
                this.updateProgress(data.data.step_info);
                
                // Track le début de l'étape initiale
                this.trackStepStart(this.state.currentStep);
            } else {
                this.showNotification('Erreur', data.error || 'Erreur d\'initialisation', 'error');
            }
        } catch (error) {
            this.log('Erreur init:', error);
            this.showNotification('Erreur', 'Impossible de se connecter au serveur', 'error');
        }
    }
    
    /**
     * Initialise Analytics et A/B Testing
     */
    initAnalyticsAndABTesting() {
        // Initialiser Analytics
        if (window.AnalyticsClient && this.state.sessionId) {
            AnalyticsClient.init(this.state.sessionId, { debug: this.config.debug });
            AnalyticsClient.trackSessionStart();
        }
        
        // Initialiser A/B Testing
        if (window.ABTestingClient && this.state.sessionId) {
            ABTestingClient.init(this.state.sessionId, { debug: this.config.debug });
            
            // Précharger les variants pour les tests actifs
            ABTestingClient.preloadVariants([
                'welcome_message',
                'passport_scan_ui',
                'quick_actions_style',
                'cta_text'
            ]);
        }
    }
    
    /**
     * Track le début d'une étape
     */
    trackStepStart(step) {
        if (window.AnalyticsClient) {
            AnalyticsClient.trackStepStart(step);
        }
        this._stepStartTime = performance.now();
    }
    
    /**
     * Track la complétion d'une étape
     */
    trackStepComplete(step) {
        if (window.AnalyticsClient) {
            const duration = this._stepStartTime 
                ? (performance.now() - this._stepStartTime) / 1000 
                : 0;
            AnalyticsClient.trackStepComplete(step, duration);
        }
    }
    
    /**
     * Track la complétion de la demande
     */
    trackApplicationComplete() {
        // Track la fin de session dans Analytics
        if (window.AnalyticsClient) {
            AnalyticsClient.trackSessionEnd(true);
        }
        
        // Track les conversions A/B pour tous les tests actifs
        if (window.ABTestingClient) {
            const activeTests = ['welcome_message', 'passport_scan_ui', 'quick_actions_style', 'cta_text', 'confirmation_layout'];
            activeTests.forEach(testId => {
                ABTestingClient.trackConversion(testId);
            });
        }
    }
    
    /**
     * Getter pour sessionId (utilisé par les autres modules)
     */
    get sessionId() {
        return this.state.sessionId;
    }
    
    /**
     * Envoie un message
     */
    async sendMessage(text = null) {
        const message = text || this.elements.chatInput?.value.trim();
        
        if (!message) return;
        
        // Afficher le message utilisateur
        this.addUserMessage(message);
        
        // Vider l'input
        if (this.elements.chatInput) {
            this.elements.chatInput.value = '';
        }
        
        // Masquer les quick actions
        this.hideQuickActions();
        
        // Marquer comme modifié pour autosave
        window.autosave?.markDirty();
        
        // Afficher l'indicateur de frappe
        this.showTyping();
        
        try {
            const response = await fetch(this.config.apiEndpoint, {
                method: 'POST',
                credentials: 'include', // Important: include PHP session cookie
                headers: {
                    'Content-Type': 'application/json',
                    'X-Session-ID': this.state.sessionId
                },
                body: JSON.stringify({
                    action: 'message',
                    session_id: this.state.sessionId,
                    message: message
                })
            });
            
            const data = await response.json();
            
            // Masquer l'indicateur de frappe
            this.hideTyping();
            
            if (data.success) {
                // Mettre à jour le session ID si différent
                if (data.data.session_id && data.data.session_id !== this.state.sessionId) {
                    this.state.sessionId = data.data.session_id;
                }
                
                // Détecter le changement d'étape
                const previousStep = this.state.currentStep;
                const newStep = data.data.step_info?.current;
                
                // Mettre à jour l'état
                this.state.currentStep = newStep;
                this.state.workflowCategory = data.data.workflow_category;
                
                // Track les changements d'étape
                if (previousStep !== newStep && previousStep) {
                    this.trackStepComplete(previousStep);
                    this.trackStepStart(newStep);
                    
                    // Track la conversion si étape de confirmation terminée
                    if (previousStep === 'confirm' && data.data.metadata?.completed) {
                        this.trackApplicationComplete();
                    }
                }
                
                // Afficher la réponse du bot
                this.addBotMessage(data.data.bot_message.content);
                
                // Afficher les quick actions
                this.showQuickActions(data.data.quick_actions || []);
                
                // Mettre à jour la progression
                this.updateProgress(data.data.step_info);
                
                // Gérer les actions spéciales
                this.handleMetadata(data.data.metadata);
            } else {
                this.addBotMessage(data.error || 'Une erreur est survenue');
                
                // Track l'erreur
                if (window.AnalyticsClient) {
                    AnalyticsClient.trackError(this.state.currentStep, 'api_error', data.error || 'Unknown error');
                }
            }
        } catch (error) {
            this.hideTyping();
            this.log('Erreur envoi:', error);
            this.addBotMessage('Erreur de connexion. Veuillez réessayer.');
        }
    }
    
    /**
     * Ajoute un message du bot avec animation
     */
    async addBotMessage(content, options = {}) {
        const {
            typewriter = this.config.enableTypewriter,
            animate = true,
            delay = 0
        } = options;
        
        // Ajouter au queue si on est déjà en train de traiter
        if (this.state.isProcessingQueue && !options.skipQueue) {
            this.state.messageQueue.push({ content, options });
            return;
        }
        
        if (delay > 0) {
            await new Promise(resolve => setTimeout(resolve, delay));
        }
        
        const message = this.createMessageElement('bot', typewriter ? '' : content);
        this.elements.chatMessages?.appendChild(message);
        
        // Animation d'entrée
        if (animate && this._microInteractions) {
            this._microInteractions.animateMessage(message);
        }
        
        this.scrollToBottom();
        
        // Effet typewriter
        if (typewriter && content) {
            const contentEl = message.querySelector('.message-content');
            if (contentEl) {
                await this.typewriterEffect(contentEl, content);
            }
        }
        
        // Extraire le prénom si mentionné
        this.extractUserInfo(content);
        
        // Traiter le prochain message dans la queue
        if (this.state.messageQueue.length > 0) {
            const next = this.state.messageQueue.shift();
            await this.addBotMessage(next.content, { ...next.options, skipQueue: true });
        }
    }
    
    /**
     * Effet typewriter pour affichage progressif du texte
     */
    async typewriterEffect(element, text) {
        const speed = this.config.typewriterSpeed;
        const parsedContent = this.parseContent(text);
        
        // Pour le typewriter, on affiche caractère par caractère
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = parsedContent;
        const plainText = tempDiv.textContent || tempDiv.innerText;
        
        element.textContent = '';
        
        for (let i = 0; i < plainText.length; i++) {
            element.textContent += plainText.charAt(i);
            this.scrollToBottom();
            
            // Pause plus longue sur la ponctuation
            const char = plainText.charAt(i);
            if (['.', '!', '?'].includes(char)) {
                await new Promise(r => setTimeout(r, speed * 10));
            } else if ([',', ';', ':'].includes(char)) {
                await new Promise(r => setTimeout(r, speed * 3));
            } else {
                await new Promise(r => setTimeout(r, speed));
            }
        }
        
        // Appliquer le formatage final
        element.innerHTML = parsedContent;
    }
    
    /**
     * Extrait les informations utilisateur du contenu
     */
    extractUserInfo(content) {
        // Chercher le prénom dans les messages de confirmation passeport
        const nameMatch = content.match(/(?:Bonjour|Merci|Parfait|Super)\s+([A-Z][a-zàâäéèêëïîôùûüÿç]+)/);
        if (nameMatch && !this.state.userFirstName) {
            this.state.userFirstName = nameMatch[1];
            this.log('Prénom détecté:', this.state.userFirstName);
        }
    }
    
    /**
     * Ajoute un message de l'utilisateur
     */
    addUserMessage(content) {
        const message = this.createMessageElement('user', content);
        this.elements.chatMessages?.appendChild(message);
        this.scrollToBottom();
    }
    
    /**
     * Crée un élément de message
     */
    createMessageElement(role, content) {
        const div = document.createElement('div');
        div.className = `message ${role}`;

        // Message grouping - check if previous message is from same sender
        const messages = this.elements.chatMessages?.querySelectorAll('.message');
        if (messages && messages.length > 0) {
            const lastMessage = messages[messages.length - 1];
            if (lastMessage && lastMessage.classList.contains(role)) {
                div.classList.add('message-grouped');
            }
        }

        const avatar = document.createElement('div');
        avatar.className = 'message-avatar';
        avatar.innerHTML = role === 'bot'
            ? '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h12a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><circle cx="12" cy="10" r="3"/><path d="M7 16h10"/></svg>'
            : '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';

        const contentDiv = document.createElement('div');
        contentDiv.className = 'message-content';

        // Parser le contenu (markdown basique)
        contentDiv.innerHTML = this.parseContent(content);

        // Add timestamp
        const now = new Date();
        const timeStr = now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        const timeSpan = document.createElement('span');
        timeSpan.className = 'message-time';
        timeSpan.textContent = timeStr;
        contentDiv.appendChild(timeSpan);

        div.appendChild(avatar);
        div.appendChild(contentDiv);

        return div;
    }
    
    /**
     * Parse le contenu du message (markdown basique)
     */
    parseContent(content) {
        if (!content) return '';
        
        return content
            // Gras
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            // Listes
            .replace(/^[•✓✗] (.+)$/gm, '<span class="list-item">$&</span>')
            // Lignes
            .split('\n').map(line => `<p>${line}</p>`).join('');
    }
    
    /**
     * Affiche les quick actions avec animations
     */
    showQuickActions(actions) {
        if (!this.elements.quickActions) return;
        
        this.elements.quickActions.innerHTML = '';
        
        actions.forEach((action, index) => {
            const btn = document.createElement('button');
            btn.className = 'quick-action-btn';
            if (action.highlight) {
                btn.classList.add('primary');
            }
            btn.innerHTML = action.label;
            
            // Animation d'entrée décalée
            btn.style.opacity = '0';
            btn.style.transform = 'translateY(10px)';
            
            btn.addEventListener('click', () => {
                // Feedback haptique
                if (this._microInteractions) {
                    this._microInteractions.hapticFeedback('light');
                }
                this.sendMessage(action.value);
            });
            
            this.elements.quickActions.appendChild(btn);
            
            // Animation avec délai
            setTimeout(() => {
                btn.animate([
                    { opacity: 0, transform: 'translateY(10px)' },
                    { opacity: 1, transform: 'translateY(0)' }
                ], {
                    duration: 200,
                    easing: 'ease-out',
                    fill: 'forwards'
                });
            }, index * 50);
        });
    }
    
    /**
     * Affiche une suggestion proactive
     */
    showProactiveSuggestion(message, type = 'tip') {
        const suggestion = document.createElement('div');
        suggestion.className = `proactive-suggestion ${type}`;
        
        const icons = {
            tip: '💡',
            warning: '⚠️',
            info: 'ℹ️',
            celebration: '🎉'
        };
        
        suggestion.innerHTML = `
            <span class="suggestion-icon">${icons[type] || '💡'}</span>
            <span class="suggestion-text">${message}</span>
            <button class="suggestion-dismiss" aria-label="Fermer">×</button>
        `;
        
        // Animation d'entrée
        suggestion.style.opacity = '0';
        suggestion.style.transform = 'translateY(-10px)';
        
        this.elements.chatMessages?.appendChild(suggestion);
        
        suggestion.animate([
            { opacity: 0, transform: 'translateY(-10px)' },
            { opacity: 1, transform: 'translateY(0)' }
        ], {
            duration: 300,
            easing: 'ease-out',
            fill: 'forwards'
        });
        
        // Bouton de fermeture
        suggestion.querySelector('.suggestion-dismiss')?.addEventListener('click', () => {
            suggestion.animate([
                { opacity: 1 },
                { opacity: 0 }
            ], { duration: 200 }).onfinish = () => suggestion.remove();
        });
        
        this.scrollToBottom();
        
        // Auto-dismiss après 10 secondes
        setTimeout(() => {
            if (suggestion.parentNode) {
                suggestion.animate([
                    { opacity: 1 },
                    { opacity: 0 }
                ], { duration: 200 }).onfinish = () => suggestion.remove();
            }
        }, 10000);
    }
    
    /**
     * Célèbre un succès avec animation
     */
    celebrateSuccess(message = null) {
        if (this._microInteractions) {
            this._microInteractions.celebrateSuccess();
        }
        
        // Afficher un message de célébration
        const celebration = VisaChatbot.PERSONA.celebrations[
            Math.floor(Math.random() * VisaChatbot.PERSONA.celebrations.length)
        ];
        
        if (message) {
            this.showProactiveSuggestion(`${celebration} ${message}`, 'celebration');
        }
    }
    
    /**
     * Affiche une erreur avec animation
     */
    showError(element, message) {
        if (this._microInteractions) {
            this._microInteractions.shakeError(element);
        }
        
        this.showNotification('Oops !', message, 'error');
    }
    
    /**
     * Masque les quick actions
     */
    hideQuickActions() {
        if (this.elements.quickActions) {
            this.elements.quickActions.innerHTML = '';
        }
    }
    
    /**
     * Affiche l'indicateur de frappe avec Aya
     */
    showTyping(customText = null) {
        this.state.isTyping = true;
        
        // Utiliser les micro-interactions si disponibles
        if (this._microInteractions) {
            this._microInteractions.showTyping(customText || 'Aya réfléchit...');
            return;
        }
        
        // Fallback standard
        const typing = document.createElement('div');
        typing.className = 'message bot';
        typing.id = 'typingIndicator';
        typing.innerHTML = `
            <div class="message-avatar">
                <span class="avatar-emoji">${VisaChatbot.PERSONA.avatar}</span>
            </div>
            <div class="typing-indicator">
                <span class="typing-name">${VisaChatbot.PERSONA.name}</span>
                <div class="typing-dots">
                    <span></span><span></span><span></span>
                </div>
            </div>
        `;
        
        this.elements.chatMessages?.appendChild(typing);
        this.scrollToBottom();
    }
    
    /**
     * Masque l'indicateur de frappe
     */
    hideTyping() {
        this.state.isTyping = false;
        
        if (this._microInteractions) {
            this._microInteractions.hideTyping();
            return;
        }
        
        const typing = document.getElementById('typingIndicator');
        typing?.remove();
    }
    
    /**
     * Met à jour la progression
     */
    updateProgress(stepInfo) {
        if (!stepInfo) return;
        
        const stepLabels = {
            'welcome': 'Accueil',
            'residence': 'Résidence',
            'documents': 'Documents',
            'passport': 'Passeport',
            'photo': 'Photo',
            'contact': 'Contact',
            'trip': 'Voyage',
            'health': 'Santé',
            'customs': 'Douanes',
            'confirm': 'Confirmation'
        };
        
        // Mettre à jour le state
        this.state.highestStepReached = stepInfo.highest_reached ?? stepInfo.index;
        this.state.stepsInfo = stepInfo.steps_info || {};
        
        // Mettre à jour le label
        if (this.elements.stepLabel) {
            this.elements.stepLabel.textContent = stepLabels[stepInfo.current] || stepInfo.current;
        }
        
        // Mettre à jour le compteur
        if (this.elements.stepCount) {
            this.elements.stepCount.textContent = `${stepInfo.index + 1}/${stepInfo.total}`;
        }
        
        // Mettre à jour la barre de progression
        if (this.elements.progressFill) {
            this.elements.progressFill.style.width = `${stepInfo.progress}%`;
        }
        
        // Mettre à jour les points d'étape avec états accessibles
        const dots = this.elements.stepNav?.querySelectorAll('.step-dot');
        const highestReached = stepInfo.highest_reached ?? stepInfo.index;
        
        dots?.forEach((dot, index) => {
            const step = dot.dataset.step;
            const stepData = stepInfo.steps_info?.[step] || {};
            
            // Reset des classes
            dot.classList.remove('active', 'completed', 'accessible');
            
            // État actif
            if (index === stepInfo.index) {
                dot.classList.add('active');
                dot.setAttribute('aria-current', 'step');
            } else {
                dot.removeAttribute('aria-current');
            }
            
            // État complété
            if (index < stepInfo.index) {
                dot.classList.add('completed');
            }
            
            // État accessible (peut naviguer)
            if (index <= highestReached) {
                dot.classList.add('accessible');
                dot.disabled = false;
                dot.setAttribute('aria-disabled', 'false');
                dot.title = `${stepLabels[step]} (cliquez pour revenir)`;
            } else {
                dot.disabled = true;
                dot.setAttribute('aria-disabled', 'true');
                dot.title = `${stepLabels[step]} (non accessible)`;
            }
        });
    }
    
    /**
     * Navigue vers une étape spécifique
     */
    async navigateToStep(targetStep) {
        if (this.state.isTyping) return;
        
        this.log(`Navigation vers: ${targetStep}`);
        
        // Afficher un message de navigation
        this.addSystemMessage(`🔄 Retour à l'étape "${this.getStepLabel(targetStep)}"...`);
        this.showTyping();
        
        try {
            const response = await fetch(this.config.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Session-ID': this.state.sessionId
                },
                body: JSON.stringify({
                    action: 'navigate',
                    session_id: this.state.sessionId,
                    target_step: targetStep
                })
            });
            
            const data = await response.json();
            this.hideTyping();
            
            if (data.success) {
                this.state.currentStep = data.data.step_info?.current;
                this.state.workflowCategory = data.data.workflow_category;
                this.state.stepsInfo = data.data.step_info?.steps_info || {};
                
                this.addBotMessage(data.data.bot_message.content);
                this.showQuickActions(data.data.quick_actions || []);
                this.updateProgress(data.data.step_info);
                
                this.showNotification('Navigation', `Étape "${this.getStepLabel(targetStep)}"`, 'info');
            } else {
                this.addBotMessage(data.error || 'Impossible de naviguer vers cette étape.');
            }
        } catch (error) {
            this.hideTyping();
            this.log('Erreur navigation:', error);
            this.addBotMessage('Erreur de navigation. Veuillez réessayer.');
        }
    }
    
    /**
     * Ajoute un message système
     */
    addSystemMessage(content) {
        const div = document.createElement('div');
        div.className = 'message system';
        div.innerHTML = `<div class="message-content system-content">${content}</div>`;
        this.elements.chatMessages?.appendChild(div);
        this.scrollToBottom();
    }
    
    /**
     * Retourne le label d'une étape
     */
    getStepLabel(step) {
        const labels = {
            'welcome': 'Accueil',
            'residence': 'Résidence',
            'documents': 'Documents',
            'passport': 'Passeport',
            'photo': 'Photo',
            'contact': 'Contact',
            'trip': 'Voyage',
            'health': 'Santé',
            'customs': 'Douanes',
            'confirm': 'Confirmation'
        };
        return labels[step] || step;
    }
    
    /**
     * Gère les métadonnées de la réponse
     */
    handleMetadata(metadata) {
        if (!metadata) return;

        // Ouvrir le scanner de passeport si nécessaire
        if (metadata.input_type === 'file' && metadata.file_type === 'passport') {
            this.state.pendingInputType = 'passport';
            // Ajouter un bouton pour scanner
            this.addScanButton();
        }

        // *** NOUVEAU: Gestion upload documents (ticket, hotel, vaccination, invitation) ***
        if (metadata.input_type === 'file' && metadata.document_type) {
            const docType = metadata.document_type;
            const acceptTypes = metadata.accept || '.pdf,.jpg,.jpeg,.png';
            this.state.pendingInputType = docType;
            this.state.pendingDocumentAccept = acceptTypes;
            // Afficher la zone d'upload
            this.showDocumentUploader(docType, acceptTypes);
        }

        // Ouvrir l'interface multi-documents si nécessaire
        if (metadata.input_type === 'multi_document' || metadata.show_uploader) {
            this.addDocumentsUploadButton();
        }
        
        // Session bloquée (vaccination obligatoire non faite, etc.)
        if (metadata.blocking) {
            this.elements.chatInput?.setAttribute('disabled', 'true');
            this.elements.btnSend?.setAttribute('disabled', 'true');
        } else {
            // Réactiver si non bloquée
            this.elements.chatInput?.removeAttribute('disabled');
            this.elements.btnSend?.removeAttribute('disabled');
        }
        
        // Suggestions proactives du backend
        if (metadata.proactive_tip) {
            setTimeout(() => {
                this.showProactiveSuggestion(metadata.proactive_tip, 'tip');
            }, 1000);
        }
        
        if (metadata.proactive_warning) {
            setTimeout(() => {
                this.showProactiveSuggestion(metadata.proactive_warning, 'warning');
            }, 500);
        }
        
        // Célébration étape importante
        if (metadata.milestone) {
            this.celebrateSuccess(metadata.milestone_message);
        }
        
        // Stocker les informations utilisateur
        if (metadata.user_firstname) {
            this.state.userFirstName = metadata.user_firstname;
        }
        
        // Workflow catégorie détecté
        if (metadata.is_diplomatic || metadata.is_priority) {
            this.showProactiveSuggestion(
                metadata.is_diplomatic 
                    ? '🎖️ Passeport diplomatique détecté - Traitement prioritaire et gratuit !'
                    : '⚡ Traitement prioritaire activé',
                'celebration'
            );
        }
        
        // Session terminée avec succès
        if (metadata.completed) {
            this.celebrateSuccess('Votre demande a été soumise avec succès !');
            this.showNotification('Succès', 'Votre demande a été soumise !', 'success');
        }
        
        // Progression milestone
        if (metadata.progress_milestone) {
            const progressMessages = {
                0.25: "Un quart du chemin parcouru ! 🚀",
                0.5: "À mi-chemin ! Vous êtes au top ! 🌟",
                0.75: "Plus que quelques étapes ! 💪",
                0.9: "Dernière ligne droite ! 🏁"
            };
            const msg = progressMessages[metadata.progress_milestone];
            if (msg) {
                this.showProactiveSuggestion(msg, 'celebration');
            }
        }
    }
    
    /**
     * Ajoute un bouton pour ouvrir l'upload multi-documents
     */
    addDocumentsUploadButton() {
        // Créer le bouton d'upload
        const btn = document.createElement('button');
        btn.className = 'quick-action-btn primary';
        btn.innerHTML = '📁 Télécharger mes documents';
        btn.addEventListener('click', () => this.openMultiUploader());
        this.elements.quickActions?.appendChild(btn);
        
        // Ajouter aussi le bouton pour passer directement au passeport
        const skipBtn = document.createElement('button');
        skipBtn.className = 'quick-action-btn';
        skipBtn.innerHTML = 'Scanner passeport seul →';
        skipBtn.addEventListener('click', () => this.sendMessage('passport_only'));
        this.elements.quickActions?.appendChild(skipBtn);
    }

    // =========================================================================
    // UPLOAD DOCUMENTS CONVERSATIONNEL (NOUVEAU)
    // =========================================================================

    /**
     * Affiche la zone d'upload pour un type de document spécifique
     * @param {string} docType - Type de document (ticket, hotel, vaccination, invitation)
     * @param {string} acceptTypes - Types de fichiers acceptés
     */
    showDocumentUploader(docType, acceptTypes) {
        const icons = {
            ticket: '✈️',
            hotel: '🏨',
            vaccination: '💉',
            invitation: '📄'
        };
        const labels = {
            ticket: 'billet d\'avion',
            hotel: 'réservation hôtel',
            vaccination: 'carnet de vaccination',
            invitation: 'lettre d\'invitation'
        };

        const icon = icons[docType] || '📄';
        const label = labels[docType] || 'document';
        const uniqueId = `doc-upload-${docType}-${Date.now()}`;

        // Créer la zone d'upload dans le chat avec preview
        const uploadHtml = `
            <div class="document-upload-zone" data-doc-type="${docType}">
                <input type="file" id="${uniqueId}" accept="${acceptTypes}" hidden>
                <label for="${uniqueId}" class="upload-label">
                    <span class="upload-icon">${icon}</span>
                    <span class="upload-text">Cliquez pour télécharger votre ${label}</span>
                    <span class="upload-hint">ou glissez-déposez le fichier ici</span>
                    <span class="upload-formats">PDF, JPG, PNG</span>
                </label>
                <div class="document-preview hidden">
                    <div class="preview-container">
                        <img class="preview-image" alt="Aperçu du document" />
                        <div class="preview-pdf-icon hidden">📄 PDF</div>
                    </div>
                    <div class="preview-info">
                        <span class="preview-name"></span>
                        <span class="preview-size"></span>
                    </div>
                    <div class="preview-actions">
                        <button type="button" class="preview-change-btn">🔄 Changer</button>
                        <button type="button" class="preview-confirm-btn">✅ Analyser</button>
                    </div>
                </div>
                <div class="upload-progress hidden">
                    <div class="progress-bar"><div class="progress-fill"></div></div>
                    <span class="progress-text">Analyse en cours...</span>
                </div>
            </div>
        `;

        // Ajouter au chat
        const msgEl = document.createElement('div');
        msgEl.className = 'chat-message bot-message upload-container';
        msgEl.innerHTML = uploadHtml;
        this.elements.chatMessages?.appendChild(msgEl);
        this.scrollToBottom();

        // Attacher les événements
        const fileInput = document.getElementById(uniqueId);
        const uploadZone = msgEl.querySelector('.document-upload-zone');
        const previewSection = uploadZone?.querySelector('.document-preview');
        const labelEl = uploadZone?.querySelector('.upload-label');

        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                if (e.target.files[0]) {
                    this.showDocumentPreview(e.target.files[0], docType, uploadZone, previewSection, labelEl);
                }
            });
        }

        // Preview actions
        const changeBtn = uploadZone?.querySelector('.preview-change-btn');
        const confirmBtn = uploadZone?.querySelector('.preview-confirm-btn');

        if (changeBtn) {
            changeBtn.addEventListener('click', () => {
                previewSection?.classList.add('hidden');
                labelEl?.classList.remove('hidden');
                fileInput.value = '';
            });
        }

        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => {
                if (fileInput.files[0]) {
                    this.handleDocumentUpload(fileInput.files[0], docType, uploadZone);
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
                    // Show preview instead of direct upload
                    this.showDocumentPreview(file, docType, uploadZone, previewSection, labelEl);
                    // Update the file input
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    fileInput.files = dataTransfer.files;
                }
            });
        }
    }

    /**
     * Affiche un aperçu du document avant l'extraction
     * @param {File} file - Le fichier à prévisualiser
     * @param {string} docType - Type de document
     * @param {HTMLElement} uploadZone - Zone d'upload
     * @param {HTMLElement} previewSection - Section de prévisualisation
     * @param {HTMLElement} labelEl - Label d'upload à masquer
     */
    showDocumentPreview(file, docType, uploadZone, previewSection, labelEl) {
        if (!file || !previewSection) return;

        const previewImage = previewSection.querySelector('.preview-image');
        const pdfIcon = previewSection.querySelector('.preview-pdf-icon');
        const previewName = previewSection.querySelector('.preview-name');
        const previewSize = previewSection.querySelector('.preview-size');

        // Afficher les infos du fichier
        if (previewName) previewName.textContent = file.name;
        if (previewSize) {
            const sizeKB = (file.size / 1024).toFixed(1);
            const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            previewSize.textContent = file.size > 1024 * 1024 ? `${sizeMB} Mo` : `${sizeKB} Ko`;
        }

        // Afficher l'aperçu selon le type
        if (file.type.startsWith('image/')) {
            // Image: créer un aperçu
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
            // PDF: afficher l'icône
            if (previewImage) previewImage.classList.add('hidden');
            if (pdfIcon) {
                pdfIcon.classList.remove('hidden');
                pdfIcon.textContent = `📄 ${file.name}`;
            }
        }

        // Masquer le label, afficher le preview
        if (labelEl) labelEl.classList.add('hidden');
        previewSection.classList.remove('hidden');

        this.scrollToBottom();
    }

    /**
     * Étapes de progression pour l'extraction de document
     */
    static EXTRACTION_STAGES = [
        { progress: 10, text: 'Envoi du document...', icon: '📤' },
        { progress: 30, text: 'Lecture du document...', icon: '🔍' },
        { progress: 60, text: 'Extraction des données...', icon: '⚙️' },
        { progress: 85, text: 'Validation...', icon: '✅' },
        { progress: 100, text: 'Terminé !', icon: '🎉' }
    ];

    /**
     * Gère l'upload et l'extraction OCR d'un document
     * @param {File} file - Le fichier uploadé
     * @param {string} docType - Type de document
     * @param {HTMLElement} uploadZone - Zone d'upload pour afficher le progress
     */
    async handleDocumentUpload(file, docType, uploadZone) {
        if (!file) return;

        this.log(`Upload document: ${docType}`, file.name);

        // Afficher la progression
        const progressEl = uploadZone?.querySelector('.upload-progress');
        const progressBar = progressEl?.querySelector('.progress-fill');
        const progressText = progressEl?.querySelector('.progress-text');
        const labelEl = uploadZone?.querySelector('.upload-label');

        if (progressEl) progressEl.classList.remove('hidden');
        if (labelEl) labelEl.classList.add('hidden');

        // Fonction helper pour mettre à jour la progression
        const updateProgress = (stageIndex) => {
            if (stageIndex >= VisaChatbot.EXTRACTION_STAGES.length) return;
            const stage = VisaChatbot.EXTRACTION_STAGES[stageIndex];
            if (progressBar) {
                progressBar.style.width = `${stage.progress}%`;
                progressBar.style.transition = 'width 0.5s ease-out';
            }
            if (progressText) {
                progressText.innerHTML = `${stage.icon} ${stage.text}`;
            }
        };

        // Démarrer la progression simulée
        let currentStage = 0;
        updateProgress(currentStage);

        // Progression simulée pendant le traitement
        const progressInterval = setInterval(() => {
            if (currentStage < 3) { // Ne pas dépasser "Extraction des données..."
                currentStage++;
                updateProgress(currentStage);
            }
        }, 2000);

        try {
            // Étape 1: Envoi
            updateProgress(0);

            // Convertir en base64
            const base64 = await this.fileToBase64(file);
            const mimeType = file.type || 'application/octet-stream';

            // Étape 2: Lecture (après conversion base64)
            updateProgress(1);

            // Appeler l'API d'extraction
            const response = await fetch(this.config.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Session-ID': this.state.sessionId
                },
                body: JSON.stringify({
                    action: 'extract_document',
                    session_id: this.state.sessionId,
                    document_type: docType,
                    file_data: base64,
                    mime_type: mimeType,
                    file_name: file.name
                })
            });

            // Arrêter la progression simulée
            clearInterval(progressInterval);

            // Étape 4: Validation
            updateProgress(3);

            const data = await response.json();
            this.log('Extraction result:', data);

            if (data.success && data.extracted_data) {
                // Étape 5: Terminé !
                updateProgress(4);

                // Ajouter animation de succès
                if (uploadZone) {
                    uploadZone.classList.add('upload-complete', 'extraction-success');
                }

                // Attendre un peu pour montrer le succès
                await new Promise(resolve => setTimeout(resolve, 800));

                // Envoyer au workflow avec les données extraites
                await this.sendMessageWithMetadata('document_uploaded', {
                    document_uploaded: true,
                    document_type: docType,
                    extracted_data: data.extracted_data,
                    file_name: file.name
                });
            } else {
                // Erreur d'extraction - afficher message explicite
                clearInterval(progressInterval);
                const errorMsg = this.getExplicitErrorMessage(data.error, docType);
                this.showNotification('Erreur', errorMsg, 'error');

                // Réafficher la zone d'upload avec animation
                if (progressEl) progressEl.classList.add('hidden');
                if (labelEl) labelEl.classList.remove('hidden');
                if (uploadZone) uploadZone.classList.add('extraction-failed');
                setTimeout(() => uploadZone?.classList.remove('extraction-failed'), 500);
            }
        } catch (error) {
            clearInterval(progressInterval);
            this.log('Erreur upload document:', error);

            // Message d'erreur explicite
            const errorMsg = this.getExplicitErrorMessage('network_error', docType);
            this.showNotification('Erreur', errorMsg, 'error');

            // Réafficher la zone d'upload
            if (progressEl) progressEl.classList.add('hidden');
            if (labelEl) labelEl.classList.remove('hidden');
        }
    }

    /**
     * Obtient un message d'erreur explicite et compréhensible
     * @param {string} error - Code d'erreur
     * @param {string} docType - Type de document
     * @returns {string} Message d'erreur explicite
     */
    getExplicitErrorMessage(error, docType) {
        const docLabels = {
            'ticket': 'billet d\'avion',
            'hotel': 'réservation d\'hôtel',
            'vaccination': 'carnet de vaccination',
            'invitation': 'lettre d\'invitation',
            'passport': 'passeport'
        };
        const docLabel = docLabels[docType] || 'document';

        const errorMessages = {
            'network_error': `Impossible d'envoyer le ${docLabel}. Vérifiez votre connexion internet.`,
            'timeout': `L'analyse prend trop de temps. Essayez avec une image plus nette.`,
            'invalid_format': `Ce format de fichier n'est pas accepté. Utilisez PDF, JPG ou PNG.`,
            'file_too_large': `Le fichier est trop volumineux. Taille maximum: 10 Mo.`,
            'blurry_image': `L'image semble floue. Prenez une nouvelle photo avec un meilleur éclairage.`,
            'unreadable': `Impossible de lire le ${docLabel}. Assurez-vous que le document est bien visible.`,
            'corrupted_pdf': `Le fichier PDF semble endommagé. Essayez de le télécharger à nouveau.`,
            'missing_fields': `Certaines informations sont manquantes. Vérifiez que tout le ${docLabel} est visible.`
        };

        // Chercher une correspondance dans le message d'erreur
        if (error) {
            const lowerError = error.toLowerCase();
            if (lowerError.includes('timeout') || lowerError.includes('temps')) {
                return errorMessages['timeout'];
            }
            if (lowerError.includes('format') || lowerError.includes('type')) {
                return errorMessages['invalid_format'];
            }
            if (lowerError.includes('large') || lowerError.includes('taille')) {
                return errorMessages['file_too_large'];
            }
            if (lowerError.includes('flou') || lowerError.includes('blur')) {
                return errorMessages['blurry_image'];
            }
            if (lowerError.includes('pdf') && (lowerError.includes('corrompu') || lowerError.includes('invalid'))) {
                return errorMessages['corrupted_pdf'];
            }
        }

        // Message par défaut
        return `Impossible d'analyser le ${docLabel}. Veuillez réessayer avec un fichier de meilleure qualité.`;
    }

    /**
     * Envoie un message avec des métadonnées additionnelles
     * @param {string} message - Le message texte
     * @param {Object} metadata - Les métadonnées à envoyer
     */
    async sendMessageWithMetadata(message, metadata = {}) {
        try {
            this.showTyping();

            const response = await fetch(this.config.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Session-ID': this.state.sessionId
                },
                body: JSON.stringify({
                    action: 'message',
                    session_id: this.state.sessionId,
                    message: message,
                    metadata: metadata
                })
            });

            const data = await response.json();
            this.hideTyping();

            if (data.success) {
                // Mettre à jour l'état
                if (data.data.step_info) {
                    this.state.currentStep = data.data.step_info.current;
                    this.updateProgress(data.data.step_info);
                }

                // Afficher le message du bot (résultats OCR)
                if (data.data.bot_message?.content) {
                    await this.addBotMessage(data.data.bot_message.content);
                }

                // Afficher les quick actions
                if (data.data.quick_actions?.length > 0) {
                    this.showQuickActions(data.data.quick_actions);
                }

                // Gérer les métadonnées de réponse
                if (data.data.metadata) {
                    this.handleMetadata(data.data.metadata);
                }
            } else {
                this.addBotMessage(data.error || 'Une erreur est survenue.');
            }
        } catch (error) {
            this.hideTyping();
            this.log('Erreur sendMessageWithMetadata:', error);
            this.addBotMessage('Erreur de communication avec le serveur.');
        }
    }

    // =========================================================================
    // FIN UPLOAD DOCUMENTS CONVERSATIONNEL
    // =========================================================================

    /**
     * Ouvre l'interface d'upload multi-documents
     */
    openMultiUploader() {
        // Vérifier si le composant existe
        if (!window.MultiDocumentUploader) {
            this.showNotification('Erreur', 'Composant d\'upload non chargé', 'error');
            return;
        }
        
        // Afficher le modal
        const modal = document.getElementById('multiUploadModal');
        if (modal) {
            modal.hidden = false;
            
            // Initialiser l'uploader si pas déjà fait
            if (!this._multiUploader) {
                this._multiUploader = new MultiDocumentUploader({
                    endpoint: this.config.apiEndpoint,
                    debug: this.config.debug,
                    onProgress: (progress) => {
                        this.log('Upload progress:', Math.round(progress * 100) + '%');
                    },
                    onDocumentComplete: (type, result) => {
                        this.log('Document complete:', type, result);
                    },
                    onAllComplete: (results) => {
                        this.handleDocumentsExtracted(results);
                    },
                    onError: (type, error) => {
                        this.showNotification('Erreur', `Erreur ${type}: ${error.message}`, 'error');
                    }
                });
                
                this._multiUploader.mount('#multiUploadModalBody');
            }
        }
    }
    
    /**
     * Ferme l'interface d'upload multi-documents
     */
    closeMultiUploader() {
        const modal = document.getElementById('multiUploadModal');
        if (modal) {
            modal.hidden = true;
        }
    }
    
    /**
     * Gère les résultats de l'extraction multi-documents
     */
    async handleDocumentsExtracted(results) {
        this.closeMultiUploader();
        
        // Récupérer les validations croisées
        let validations = null;
        try {
            const validationResponse = await fetch(this.config.apiEndpoint, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Session-ID': this.state.sessionId
                },
                body: JSON.stringify({
                    action: 'validate_documents',
                    session_id: this.state.sessionId,
                    documents: results
                })
            });
            
            const validationData = await validationResponse.json();
            if (validationData.success) {
                validations = validationData.data;
            }
        } catch (error) {
            this.log('Validation error:', error);
        }
        
        // Ouvrir le modal de vérification
        if (window.VerificationModal) {
            if (!this._verificationModal) {
                this._verificationModal = new VerificationModal({
                    debug: this.config.debug,
                    onConfirm: (finalData, editedFields) => {
                        this.handleDocumentsConfirmed(finalData, validations);
                    },
                    onModify: () => {
                        this.openMultiUploader();
                    },
                    onFieldEdit: (type, field, newValue, oldValue) => {
                        this.log('Field edited:', type, field, oldValue, '->', newValue);
                    }
                });
            }
            
            this._verificationModal.open(results, validations?.validations || []);
        } else {
            // Fallback: envoyer directement
            this.handleDocumentsConfirmed(results, validations);
        }
    }
    
    /**
     * Gère la confirmation des documents extraits
     */
    async handleDocumentsConfirmed(extractedData, validations) {
        this.addUserMessage('[Documents analysés]');
        this.showTyping();
        
        try {
            const response = await fetch(this.config.apiEndpoint, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Session-ID': this.state.sessionId
                },
                body: JSON.stringify({
                    action: 'message',
                    session_id: this.state.sessionId,
                    message: 'confirm',
                    metadata: {
                        documents_extracted: true,
                        extracted_data: extractedData,
                        validations: validations
                    }
                })
            });
            
            const data = await response.json();
            this.hideTyping();
            
            if (data.success) {
                this.state.currentStep = data.data.step_info?.current;
                this.state.workflowCategory = data.data.workflow_category;
                
                this.addBotMessage(data.data.bot_message.content);
                this.showQuickActions(data.data.quick_actions || []);
                this.updateProgress(data.data.step_info);
                
                // Notification de succès
                const docCount = Object.keys(extractedData).length;
                this.showNotification('Documents analysés', `${docCount} document(s) extrait(s) avec succès`, 'success');

                // Afficher le rapport de cohérence après l'extraction
                await this.displayCoherenceReport();
            } else {
                this.addBotMessage(data.error || 'Erreur lors du traitement des documents');
            }
        } catch (error) {
            this.hideTyping();
            this.log('Erreur documents:', error);
            this.addBotMessage('Erreur de connexion. Veuillez réessayer.');
        }
    }

    /**
     * Affiche le rapport de cohérence cross-documents
     */
    async displayCoherenceReport() {
        if (!this._coherenceUI) {
            this.log('CoherenceUI non disponible');
            return;
        }

        try {
            this.log('Fetching coherence validation...');

            // Appeler l'API de validation de cohérence
            const reportElement = await this._coherenceUI.validateAndDisplay();

            if (reportElement) {
                // Créer un message bot contenant le rapport
                const msgEl = document.createElement('div');
                msgEl.className = 'message bot';
                msgEl.innerHTML = `
                    <div class="message-avatar">
                        <span class="avatar-emoji">🇨🇮</span>
                    </div>
                    <div class="message-content coherence-report-container">
                        <p style="margin-bottom: var(--space-3);">
                            <strong>📋 Analyse de votre dossier</strong><br>
                            Voici le résumé de cohérence de vos documents :
                        </p>
                    </div>
                `;

                const contentEl = msgEl.querySelector('.message-content');
                contentEl.appendChild(reportElement);

                this.elements.chatMessages.appendChild(msgEl);
                this.scrollToBottom();

                this.log('Coherence report displayed');
            }
        } catch (error) {
            this.log('Error displaying coherence report:', error);
        }
    }

    /**
     * Déclenche l'upload d'un document spécifique
     */
    triggerDocumentUpload(docType) {
        this.log('Triggering upload for:', docType);

        // Créer un input file temporaire
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*,application/pdf';
        input.onchange = (e) => {
            const file = e.target.files[0];
            if (file) {
                this.handleDocumentUpload(file, docType);
            }
        };
        input.click();
    }
    
    /**
     * Ajoute un bouton pour scanner le passeport
     */
    addScanButton() {
        const btn = document.createElement('button');
        btn.className = 'quick-action-btn';
        btn.innerHTML = '📷 Scanner mon passeport';
        btn.addEventListener('click', () => this.openPassportScanner());
        this.elements.quickActions?.appendChild(btn);
    }
    
    /**
     * Ouvre le scanner de passeport
     */
    openPassportScanner() {
        if (this.elements.passportScannerOverlay) {
            this.elements.passportScannerOverlay.hidden = false;
        }
    }
    
    /**
     * Ferme le scanner de passeport
     */
    closePassportScanner() {
        if (this.elements.passportScannerOverlay) {
            this.elements.passportScannerOverlay.hidden = true;
        }
        this.resetScanner();
    }
    
    /**
     * Prévisualise le passeport
     */
    previewPassport(file) {
        if (!file) return;
        
        this.state.passportFile = file;
        
        const reader = new FileReader();
        reader.onload = (e) => {
            if (this.elements.passportPreviewImg) {
                this.elements.passportPreviewImg.src = e.target.result;
            }
            if (this.elements.passportUploadZone) {
                this.elements.passportUploadZone.hidden = true;
            }
            if (this.elements.scannerPreview) {
                this.elements.scannerPreview.hidden = false;
            }
        };
        reader.readAsDataURL(file);
    }
    
    /**
     * Scanne le passeport via l'API OCR
     */
    async scanPassport() {
        if (!this.state.passportFile) return;
        
        // Afficher le traitement
        if (this.elements.scannerPreview) {
            this.elements.scannerPreview.hidden = true;
        }
        if (this.elements.scannerProcessing) {
            this.elements.scannerProcessing.hidden = false;
        }
        
        try {
            // Lire le fichier en base64
            const base64 = await this.fileToBase64(this.state.passportFile);
            
            // Appeler l'API OCR
            const ocrResponse = await fetch(this.config.ocrEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    image: base64.split(',')[1],
                    mime_type: this.state.passportFile.type,
                    action: 'extract_passport'
                })
            });
            
            const ocrData = await ocrResponse.json();
            
            if (ocrData.success && ocrData.extracted_data) {
                // Fermer le scanner
                this.closePassportScanner();
                
                // Envoyer les données OCR au chatbot
                await this.sendPassportData(ocrData.extracted_data);
            } else {
                throw new Error(ocrData.error || 'Extraction échouée');
            }
        } catch (error) {
            this.log('Erreur OCR:', error);
            this.showNotification('Erreur', 'Impossible de lire le passeport. Réessayez avec une meilleure image.', 'error');
            this.resetScanner();
        }
    }
    
    /**
     * Envoie les données du passeport au backend
     */
    async sendPassportData(ocrData) {
        this.addUserMessage('[Passeport scanné]');
        this.showTyping();
        
        try {
            const response = await fetch(this.config.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Session-ID': this.state.sessionId
                },
                body: JSON.stringify({
                    action: 'passport_ocr',
                    session_id: this.state.sessionId,
                    ocr_data: ocrData
                })
            });
            
            const data = await response.json();
            this.hideTyping();
            
            if (data.success) {
                this.state.currentStep = data.data.step_info?.current;
                this.state.workflowCategory = data.data.workflow_category;
                
                this.addBotMessage(data.data.bot_message.content);
                this.showQuickActions(data.data.quick_actions || []);
                this.updateProgress(data.data.step_info);
                
                // Notification si passeport diplomatique
                if (data.data.is_free) {
                    this.showNotification('Passeport diplomatique', 'Traitement prioritaire et gratuit !', 'success');
                }
            } else {
                this.addBotMessage(data.error || 'Erreur lors du traitement du passeport');
            }
        } catch (error) {
            this.hideTyping();
            this.log('Erreur passport:', error);
            this.addBotMessage('Erreur de connexion. Veuillez réessayer.');
        }
    }
    
    /**
     * Réinitialise le scanner
     */
    resetScanner() {
        this.state.passportFile = null;
        
        if (this.elements.passportUploadZone) {
            this.elements.passportUploadZone.hidden = false;
        }
        if (this.elements.scannerPreview) {
            this.elements.scannerPreview.hidden = true;
        }
        if (this.elements.scannerProcessing) {
            this.elements.scannerProcessing.hidden = true;
        }
        if (this.elements.passportFileInput) {
            this.elements.passportFileInput.value = '';
        }
    }
    
    /**
     * Gère l'upload de fichier générique
     */
    async handleFileUpload(file) {
        if (!file) return;
        
        this.addUserMessage(`[${file.name} uploadé]`);
        this.showTyping();
        
        try {
            const response = await fetch(this.config.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Session-ID': this.state.sessionId
                },
                body: JSON.stringify({
                    action: 'file_upload',
                    session_id: this.state.sessionId,
                    file_type: this.state.pendingInputType || 'document',
                    file_path: file.name
                })
            });
            
            const data = await response.json();
            this.hideTyping();
            
            if (data.success) {
                this.state.currentStep = data.data.step_info?.current;
                this.addBotMessage(data.data.bot_message.content);
                this.showQuickActions(data.data.quick_actions || []);
                this.updateProgress(data.data.step_info);
            }
        } catch (error) {
            this.hideTyping();
            this.log('Erreur upload:', error);
        }
        
        // Réinitialiser l'input
        if (this.elements.generalFileInput) {
            this.elements.generalFileInput.value = '';
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
     * Scroll vers le bas des messages
     */
    scrollToBottom() {
        if (this.elements.chatMessages) {
            this.elements.chatMessages.scrollTop = this.elements.chatMessages.scrollHeight;
        }
    }
    
    /**
     * Affiche une notification
     */
    showNotification(title, message, type = 'info') {
        const container = this.elements.notificationContainer;
        if (!container) return;
        
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <div>
                <strong>${title}</strong>
                <p>${message}</p>
            </div>
        `;
        
        container.appendChild(notification);
        
        // Animation d'entrée
        requestAnimationFrame(() => {
            notification.classList.add('show');
        });
        
        // Auto-dismiss après 4 secondes
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 4000);
    }
    
    /**
     * Log conditionnel
     */
    log(...args) {
        if (this.config.debug) {
            console.log('[VisaChatbot]', ...args);
        }
    }

    /**
     * Initialize scroll-to-bottom button
     */
    initScrollToBottom() {
        if (!this.elements.chatMessages) return;

        // Create button if it doesn't exist
        let btn = this.elements.scrollToBottomBtn;
        if (!btn) {
            btn = document.createElement('button');
            btn.id = 'scrollToBottomBtn';
            btn.className = 'scroll-to-bottom';
            btn.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            `;
            btn.setAttribute('aria-label', 'Retour en bas');
            this.elements.chatMessages.appendChild(btn);
            this.elements.scrollToBottomBtn = btn;
        }

        // Click handler
        btn.addEventListener('click', () => {
            this.scrollToBottom();
            btn.classList.remove('visible');
        });

        // Scroll listener
        this.elements.chatMessages.addEventListener('scroll', () => {
            const { scrollTop, scrollHeight, clientHeight } = this.elements.chatMessages;
            const distanceFromBottom = scrollHeight - scrollTop - clientHeight;

            if (distanceFromBottom > 200) {
                btn.classList.add('visible');
            } else {
                btn.classList.remove('visible');
            }
        });
    }

    /**
     * Celebrate with confetti animation
     */
    celebrateWithConfetti() {
        const container = document.createElement('div');
        container.className = 'confetti-container';
        document.body.appendChild(container);

        const colors = ['#FF6B00', '#009639', '#34C759', '#FF9500', '#007AFF', '#AF52DE'];
        const shapes = ['circle', 'square', 'triangle'];

        // Create confetti pieces
        for (let i = 0; i < 50; i++) {
            const confetti = document.createElement('div');
            confetti.className = `confetti ${shapes[Math.floor(Math.random() * shapes.length)]}`;
            confetti.style.left = Math.random() * 100 + '%';
            confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.animationDelay = Math.random() * 0.5 + 's';
            confetti.style.animationDuration = (2 + Math.random() * 2) + 's';
            container.appendChild(confetti);
        }

        // Remove after animation
        setTimeout(() => {
            container.remove();
        }, 4000);
    }

    /**
     * Setup swipe-to-close for modals
     */
    setupSwipeToClose(modalElement) {
        if (!modalElement) return;

        let startY = 0;
        let currentY = 0;
        let isDragging = false;

        const modalContent = modalElement.querySelector('.passport-scanner-modal, .sync-modal, [class*="modal"]');
        if (!modalContent) return;

        // Add swipe handle
        const handle = document.createElement('div');
        handle.className = 'modal-swipe-handle';
        modalContent.insertBefore(handle, modalContent.firstChild);
        modalContent.classList.add('modal-swipeable');

        modalContent.addEventListener('touchstart', (e) => {
            startY = e.touches[0].clientY;
            isDragging = true;
            modalContent.classList.add('swiping');
        }, { passive: true });

        modalContent.addEventListener('touchmove', (e) => {
            if (!isDragging) return;
            currentY = e.touches[0].clientY;
            const deltaY = currentY - startY;

            if (deltaY > 0) {
                modalContent.style.transform = `translateY(${deltaY}px)`;
            }
        }, { passive: true });

        modalContent.addEventListener('touchend', () => {
            if (!isDragging) return;
            isDragging = false;
            modalContent.classList.remove('swiping');

            const deltaY = currentY - startY;
            if (deltaY > 100) {
                // Close modal
                modalElement.hidden = true;
                modalContent.style.transform = '';
            } else {
                // Snap back
                modalContent.style.transform = '';
            }
        });
    }

    /**
     * Celebrate success with animation
     */
    celebrateSuccess(message) {
        // Show confetti
        this.celebrateWithConfetti();

        // Show celebration message if provided
        if (message) {
            this.showProactiveSuggestion(message, 'celebration');
        }

        // Add celebration class to progress
        if (this.elements.progressFill) {
            this.elements.progressFill.classList.add('step-completed-celebration');
            setTimeout(() => {
                this.elements.progressFill.classList.remove('step-completed-celebration');
            }, 1000);
        }

        // Haptic feedback
        if (this._microInteractions) {
            this._microInteractions.hapticFeedback('success');
        }
    }

    /**
     * Show skeleton loading state
     */
    showSkeleton() {
        const skeleton = document.createElement('div');
        skeleton.className = 'skeleton-message';
        skeleton.innerHTML = `
            <div class="skeleton skeleton-avatar"></div>
            <div class="skeleton-content">
                <div class="skeleton skeleton-line long"></div>
                <div class="skeleton skeleton-line medium"></div>
                <div class="skeleton skeleton-line short"></div>
            </div>
        `;
        skeleton.id = 'loadingSkeleton';
        this.elements.chatMessages?.appendChild(skeleton);
        this.scrollToBottom();
    }

    /**
     * Hide skeleton loading state
     */
    hideSkeleton() {
        const skeleton = document.getElementById('loadingSkeleton');
        if (skeleton) {
            skeleton.remove();
        }
    }
}

// Export global
window.VisaChatbot = VisaChatbot;

