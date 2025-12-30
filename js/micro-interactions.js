/**
 * Micro-Interactions et Feedback Temps Réel
 * Améliore l'expérience conversationnelle avec des animations subtiles
 * 
 * @package VisaChatbot
 * @version 2.0.0
 */

class MicroInteractions {
    
    constructor() {
        this.typingSpeed = 30; // ms par caractère
        this.messageDelay = 500; // délai entre messages multiples
        this.emojiParticles = ['✨', '🎉', '🌟', '💫', '🇨🇮'];
        this.init();
    }
    
    init() {
        this.setupTypingIndicator();
        this.setupMessageAnimations();
        this.setupFeedbackSystem();
        this.setupHapticFeedback();
    }
    
    /**
     * Indicateur de frappe "Aya écrit..."
     */
    setupTypingIndicator() {
        // Créer l'élément typing indicator
        this.typingIndicator = document.createElement('div');
        this.typingIndicator.className = 'typing-indicator';
        this.typingIndicator.innerHTML = `
            <div class="typing-bubble">
                <span class="typing-avatar">🇨🇮</span>
                <span class="typing-dots">
                    <span class="dot"></span>
                    <span class="dot"></span>
                    <span class="dot"></span>
                </span>
                <span class="typing-text">Aya réfléchit...</span>
            </div>
        `;
        this.typingIndicator.style.display = 'none';
    }
    
    /**
     * Affiche l'indicateur de frappe
     */
    showTyping(customText = null) {
        const messagesContainer = document.getElementById('chatMessages');
        if (!messagesContainer) return;
        
        if (customText) {
            this.typingIndicator.querySelector('.typing-text').textContent = customText;
        }
        
        this.typingIndicator.style.display = 'flex';
        messagesContainer.appendChild(this.typingIndicator);
        this.scrollToBottom();
        
        // Ajouter animation subtile
        this.typingIndicator.animate([
            { opacity: 0, transform: 'translateY(10px)' },
            { opacity: 1, transform: 'translateY(0)' }
        ], { duration: 200, easing: 'ease-out' });
    }
    
    /**
     * Cache l'indicateur de frappe
     */
    hideTyping() {
        if (this.typingIndicator.parentNode) {
            this.typingIndicator.animate([
                { opacity: 1 },
                { opacity: 0 }
            ], { duration: 150 }).onfinish = () => {
                this.typingIndicator.remove();
            };
        }
        this.typingIndicator.style.display = 'none';
    }
    
    /**
     * Configure les animations de messages
     */
    setupMessageAnimations() {
        // Observer pour les nouveaux messages
        const observer = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                mutation.addedNodes.forEach(node => {
                    if (node.classList && node.classList.contains('chat-message')) {
                        this.animateMessage(node);
                    }
                });
            });
        });
        
        const messagesContainer = document.getElementById('chatMessages');
        if (messagesContainer) {
            observer.observe(messagesContainer, { childList: true });
        }
    }
    
    /**
     * Anime l'apparition d'un message
     */
    animateMessage(messageEl) {
        const isBot = messageEl.classList.contains('bot');
        
        messageEl.animate([
            { 
                opacity: 0, 
                transform: isBot ? 'translateX(-20px)' : 'translateX(20px)',
                filter: 'blur(5px)'
            },
            { 
                opacity: 1, 
                transform: 'translateX(0)',
                filter: 'blur(0)'
            }
        ], {
            duration: 300,
            easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
            fill: 'forwards'
        });
    }
    
    /**
     * Effet de frappe progressive (typewriter)
     */
    async typeMessage(text, container, speed = this.typingSpeed) {
        return new Promise(resolve => {
            let i = 0;
            const content = container.querySelector('.message-content') || container;
            content.textContent = '';
            
            const type = () => {
                if (i < text.length) {
                    content.textContent += text.charAt(i);
                    i++;
                    this.scrollToBottom();
                    setTimeout(type, speed);
                } else {
                    resolve();
                }
            };
            
            type();
        });
    }
    
    /**
     * Système de feedback visuel
     */
    setupFeedbackSystem() {
        // Feedback sur les quick actions
        document.addEventListener('click', e => {
            const quickAction = e.target.closest('.quick-action');
            if (quickAction) {
                this.rippleEffect(quickAction, e);
                this.hapticFeedback('light');
            }
        });
    }
    
    /**
     * Effet ripple sur clic
     */
    rippleEffect(element, event) {
        const ripple = document.createElement('span');
        ripple.className = 'ripple';
        
        const rect = element.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const y = event.clientY - rect.top;
        
        ripple.style.cssText = `
            position: absolute;
            left: ${x}px;
            top: ${y}px;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            transform: translate(-50%, -50%);
            pointer-events: none;
        `;
        
        element.style.position = 'relative';
        element.style.overflow = 'hidden';
        element.appendChild(ripple);
        
        ripple.animate([
            { width: '0', height: '0', opacity: 0.5 },
            { width: '200px', height: '200px', opacity: 0 }
        ], { duration: 500, easing: 'ease-out' }).onfinish = () => {
            ripple.remove();
        };
    }
    
    /**
     * Configure le feedback haptique
     */
    setupHapticFeedback() {
        this.hasHaptic = 'vibrate' in navigator;
    }
    
    /**
     * Déclenche le feedback haptique
     */
    hapticFeedback(type = 'light') {
        if (!this.hasHaptic) return;
        
        const patterns = {
            light: [10],
            medium: [20],
            heavy: [30],
            success: [10, 50, 10],
            error: [20, 100, 20]
        };
        
        navigator.vibrate(patterns[type] || patterns.light);
    }
    
    /**
     * Animation de succès avec particules
     */
    celebrateSuccess(element = null) {
        this.hapticFeedback('success');
        
        // Explosion de confettis
        const container = element || document.body;
        const rect = container.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;
        
        for (let i = 0; i < 12; i++) {
            const particle = document.createElement('span');
            particle.textContent = this.emojiParticles[i % this.emojiParticles.length];
            particle.style.cssText = `
                position: fixed;
                left: ${centerX}px;
                top: ${centerY}px;
                font-size: 1.5rem;
                pointer-events: none;
                z-index: 9999;
            `;
            document.body.appendChild(particle);
            
            const angle = (i / 12) * Math.PI * 2;
            const velocity = 100 + Math.random() * 100;
            const endX = Math.cos(angle) * velocity;
            const endY = Math.sin(angle) * velocity - 50;
            
            particle.animate([
                { 
                    transform: 'translate(0, 0) scale(0) rotate(0deg)',
                    opacity: 1
                },
                { 
                    transform: `translate(${endX}px, ${endY}px) scale(1.5) rotate(${Math.random() * 360}deg)`,
                    opacity: 0
                }
            ], {
                duration: 800 + Math.random() * 400,
                easing: 'cubic-bezier(0, 0.5, 0.5, 1)'
            }).onfinish = () => particle.remove();
        }
    }
    
    /**
     * Animation d'erreur avec shake
     */
    shakeError(element) {
        this.hapticFeedback('error');
        
        element.animate([
            { transform: 'translateX(0)' },
            { transform: 'translateX(-10px)' },
            { transform: 'translateX(10px)' },
            { transform: 'translateX(-10px)' },
            { transform: 'translateX(10px)' },
            { transform: 'translateX(0)' }
        ], { duration: 400, easing: 'ease-in-out' });
        
        // Flash rouge subtil
        element.animate([
            { boxShadow: '0 0 0 0 rgba(255, 82, 82, 0)' },
            { boxShadow: '0 0 20px 5px rgba(255, 82, 82, 0.3)' },
            { boxShadow: '0 0 0 0 rgba(255, 82, 82, 0)' }
        ], { duration: 600 });
    }
    
    /**
     * Animation de chargement avec progression
     */
    createProgressLoader(text = 'Analyse en cours...') {
        const loader = document.createElement('div');
        loader.className = 'progress-loader';
        loader.innerHTML = `
            <div class="progress-content">
                <div class="progress-spinner">
                    <svg viewBox="0 0 50 50">
                        <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="4"/>
                    </svg>
                </div>
                <div class="progress-text">${text}</div>
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <div class="progress-percentage">0%</div>
            </div>
        `;
        
        return {
            element: loader,
            setProgress: (percent, newText = null) => {
                const fill = loader.querySelector('.progress-fill');
                const percentage = loader.querySelector('.progress-percentage');
                const textEl = loader.querySelector('.progress-text');
                
                fill.style.width = `${percent}%`;
                percentage.textContent = `${Math.round(percent)}%`;
                if (newText) textEl.textContent = newText;
            },
            complete: (successText = 'Terminé !') => {
                loader.querySelector('.progress-spinner').innerHTML = '✅';
                loader.querySelector('.progress-text').textContent = successText;
                loader.querySelector('.progress-fill').style.width = '100%';
                loader.querySelector('.progress-percentage').textContent = '100%';
                
                setTimeout(() => {
                    loader.animate([
                        { opacity: 1 },
                        { opacity: 0 }
                    ], { duration: 300 }).onfinish = () => loader.remove();
                }, 1000);
            }
        };
    }
    
    /**
     * Tooltip contextuel
     */
    showTooltip(element, text, position = 'top') {
        const tooltip = document.createElement('div');
        tooltip.className = `tooltip tooltip-${position}`;
        tooltip.textContent = text;
        
        document.body.appendChild(tooltip);
        
        const rect = element.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        
        let top, left;
        switch (position) {
            case 'top':
                top = rect.top - tooltipRect.height - 10;
                left = rect.left + (rect.width - tooltipRect.width) / 2;
                break;
            case 'bottom':
                top = rect.bottom + 10;
                left = rect.left + (rect.width - tooltipRect.width) / 2;
                break;
        }
        
        tooltip.style.cssText = `
            position: fixed;
            top: ${top}px;
            left: ${left}px;
            z-index: 10000;
        `;
        
        tooltip.animate([
            { opacity: 0, transform: 'translateY(5px)' },
            { opacity: 1, transform: 'translateY(0)' }
        ], { duration: 200 });
        
        setTimeout(() => {
            tooltip.animate([
                { opacity: 1 },
                { opacity: 0 }
            ], { duration: 200 }).onfinish = () => tooltip.remove();
        }, 3000);
        
        return tooltip;
    }
    
    /**
     * Animation de focus élégante pour les inputs
     */
    setupInputFocus() {
        document.querySelectorAll('input, textarea').forEach(input => {
            input.addEventListener('focus', () => {
                input.animate([
                    { boxShadow: '0 0 0 0 rgba(255, 162, 0, 0)' },
                    { boxShadow: '0 0 0 4px rgba(255, 162, 0, 0.2)' }
                ], { duration: 300, fill: 'forwards' });
            });
            
            input.addEventListener('blur', () => {
                input.animate([
                    { boxShadow: '0 0 0 4px rgba(255, 162, 0, 0.2)' },
                    { boxShadow: '0 0 0 0 rgba(255, 162, 0, 0)' }
                ], { duration: 300, fill: 'forwards' });
            });
        });
    }
    
    /**
     * Scroll fluide vers le bas
     */
    scrollToBottom() {
        const messagesContainer = document.getElementById('chatMessages');
        if (messagesContainer) {
            messagesContainer.scrollTo({
                top: messagesContainer.scrollHeight,
                behavior: 'smooth'
            });
        }
    }
    
    /**
     * Animation de comptage (pour les frais)
     */
    animateCounter(element, endValue, duration = 1000, suffix = ' FCFA') {
        const startValue = 0;
        const startTime = performance.now();
        
        const update = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Easing: ease-out-quart
            const eased = 1 - Math.pow(1 - progress, 4);
            const current = Math.round(startValue + (endValue - startValue) * eased);
            
            element.textContent = current.toLocaleString('fr-FR') + suffix;
            
            if (progress < 1) {
                requestAnimationFrame(update);
            }
        };
        
        requestAnimationFrame(update);
    }
    
    /**
     * Badge notification animé
     */
    showBadge(element, count, type = 'info') {
        let badge = element.querySelector('.badge');
        
        if (!badge) {
            badge = document.createElement('span');
            badge.className = `badge badge-${type}`;
            element.style.position = 'relative';
            element.appendChild(badge);
        }
        
        badge.textContent = count;
        badge.animate([
            { transform: 'scale(0)' },
            { transform: 'scale(1.2)' },
            { transform: 'scale(1)' }
        ], { duration: 300, easing: 'ease-out' });
    }
}

// Instance globale
window.microInteractions = new MicroInteractions();

// Export pour modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MicroInteractions;
}

