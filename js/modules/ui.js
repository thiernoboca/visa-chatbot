/**
 * UI Module
 * Handles UI elements (progress, notifications, quick actions)
 *
 * @version 4.0.0
 * @module UI
 */

import { STEPS, PERSONA } from './config.js';
import { i18n } from './i18n.js';
import { stateManager } from './state.js';

/**
 * UI Manager class
 */
export class UIManager {
    constructor() {
        this.elements = {};
        this.microInteractions = null;
    }

    /**
     * Initialize UI with DOM elements
     * @param {Object} elementIds - Map of element IDs
     */
    init(elementIds = {}) {
        const defaultIds = {
            stepNav: 'stepNav',
            stepLabel: 'stepLabel',
            stepCount: 'stepCount',
            progressFill: 'progressFill',
            quickActions: 'quickActions',
            notificationContainer: 'notificationContainer',
            scrollToBottomBtn: 'scrollToBottomBtn',
            chatMessages: 'chatMessages'
        };

        const ids = { ...defaultIds, ...elementIds };

        for (const [key, id] of Object.entries(ids)) {
            this.elements[key] = document.getElementById(id);
        }

        // Initialize micro-interactions if available
        if (window.microInteractions) {
            this.microInteractions = window.microInteractions;
            this.microInteractions.setupInputFocus?.();
        }

        this.initScrollToBottom();

        return this;
    }

    /**
     * Update progress display
     * @param {Object} stepInfo
     */
    updateProgress(stepInfo) {
        if (!stepInfo) return;

        const lang = stateManager.get('language') || 'fr';
        const labelKey = lang === 'fr' ? 'labelFr' : 'labelEn';

        // Find current step config
        const currentStepConfig = STEPS.find(s => s.id === stepInfo.current);

        // Update label
        if (this.elements.stepLabel && currentStepConfig) {
            this.elements.stepLabel.textContent = currentStepConfig[labelKey];
        }

        // Update counter
        if (this.elements.stepCount) {
            this.elements.stepCount.textContent = `${stepInfo.index + 1}/${stepInfo.total}`;
        }

        // Update progress bar
        if (this.elements.progressFill) {
            this.elements.progressFill.style.width = `${stepInfo.progress}%`;
        }

        // Update step dots
        const dots = this.elements.stepNav?.querySelectorAll('.step-dot');
        const highestReached = stepInfo.highest_reached ?? stepInfo.index;

        dots?.forEach((dot, index) => {
            const step = dot.dataset.step;
            const stepConfig = STEPS.find(s => s.id === step);

            dot.classList.remove('active', 'completed', 'accessible');

            if (index === stepInfo.index) {
                dot.classList.add('active');
                dot.setAttribute('aria-current', 'step');
            } else {
                dot.removeAttribute('aria-current');
            }

            if (index < stepInfo.index) {
                dot.classList.add('completed');
            }

            if (index <= highestReached) {
                dot.classList.add('accessible');
                dot.disabled = false;
                dot.setAttribute('aria-disabled', 'false');
                dot.title = `${stepConfig?.[labelKey]} (${lang === 'fr' ? 'cliquez pour revenir' : 'click to return'})`;
            } else {
                dot.disabled = true;
                dot.setAttribute('aria-disabled', 'true');
                dot.title = `${stepConfig?.[labelKey]} (${lang === 'fr' ? 'non accessible' : 'not accessible'})`;
            }
        });

        // Update state
        stateManager.update({
            currentStep: stepInfo.current,
            stepIndex: stepInfo.index,
            highestStepReached: highestReached,
            stepsInfo: stepInfo.steps_info || {}
        });
    }

    /**
     * Show quick action buttons
     * @param {Array} actions
     */
    showQuickActions(actions) {
        if (!this.elements.quickActions || !actions?.length) return;

        this.elements.quickActions.innerHTML = '';

        actions.forEach((action, index) => {
            const btn = document.createElement('button');
            btn.className = 'quick-action-btn';
            if (action.highlight) {
                btn.classList.add('primary');
            }
            btn.innerHTML = action.label;

            // Animate entrance
            btn.style.opacity = '0';
            btn.style.transform = 'translateY(10px)';

            btn.addEventListener('click', () => {
                if (this.microInteractions) {
                    this.microInteractions.hapticFeedback?.('light');
                }
                this.onQuickActionClick?.(action.value);
            });

            this.elements.quickActions.appendChild(btn);

            // Staggered animation
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
     * Hide quick actions
     */
    hideQuickActions() {
        if (this.elements.quickActions) {
            this.elements.quickActions.innerHTML = '';
        }
    }

    /**
     * Set quick action click handler
     * @param {Function} handler
     */
    setQuickActionHandler(handler) {
        this.onQuickActionClick = handler;
    }

    /**
     * Show notification
     * @param {string} title
     * @param {string} message
     * @param {string} type - 'info', 'success', 'warning', 'error'
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

        requestAnimationFrame(() => {
            notification.classList.add('show');
        });

        // Auto dismiss
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 4000);
    }

    /**
     * Show proactive suggestion
     * @param {string} message
     * @param {string} type - 'tip', 'warning', 'info', 'celebration'
     */
    showProactiveSuggestion(message, type = 'tip') {
        const icons = {
            tip: '💡',
            warning: '⚠️',
            info: 'ℹ️',
            celebration: '🎉'
        };

        const suggestion = document.createElement('div');
        suggestion.className = `proactive-suggestion ${type}`;
        suggestion.innerHTML = `
            <span class="suggestion-icon">${icons[type] || '💡'}</span>
            <span class="suggestion-text">${message}</span>
            <button class="suggestion-dismiss" aria-label="Fermer">×</button>
        `;

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

        // Dismiss button
        suggestion.querySelector('.suggestion-dismiss')?.addEventListener('click', () => {
            suggestion.animate([
                { opacity: 1 },
                { opacity: 0 }
            ], { duration: 200 }).onfinish = () => suggestion.remove();
        });

        // Auto dismiss
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
     * Celebrate success with confetti
     * @param {string} message
     */
    celebrateSuccess(message = null) {
        this.celebrateWithConfetti();

        if (message) {
            this.showProactiveSuggestion(message, 'celebration');
        }

        if (this.elements.progressFill) {
            this.elements.progressFill.classList.add('step-completed-celebration');
            setTimeout(() => {
                this.elements.progressFill.classList.remove('step-completed-celebration');
            }, 1000);
        }

        if (this.microInteractions) {
            this.microInteractions.hapticFeedback?.('success');
        }
    }

    /**
     * Create confetti animation
     */
    celebrateWithConfetti() {
        const container = document.createElement('div');
        container.className = 'confetti-container';
        document.body.appendChild(container);

        const colors = ['#FF6B00', '#009639', '#34C759', '#FF9500', '#007AFF', '#AF52DE'];
        const shapes = ['circle', 'square', 'triangle'];

        for (let i = 0; i < 50; i++) {
            const confetti = document.createElement('div');
            confetti.className = `confetti ${shapes[Math.floor(Math.random() * shapes.length)]}`;
            confetti.style.left = Math.random() * 100 + '%';
            confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.animationDelay = Math.random() * 0.5 + 's';
            confetti.style.animationDuration = (2 + Math.random() * 2) + 's';
            container.appendChild(confetti);
        }

        setTimeout(() => container.remove(), 4000);
    }

    /**
     * Shake element for error feedback
     * @param {HTMLElement} element
     */
    shakeError(element) {
        if (this.microInteractions) {
            this.microInteractions.shakeError?.(element);
        } else {
            element?.animate([
                { transform: 'translateX(0)' },
                { transform: 'translateX(-10px)' },
                { transform: 'translateX(10px)' },
                { transform: 'translateX(-10px)' },
                { transform: 'translateX(0)' }
            ], { duration: 400 });
        }
    }

    /**
     * Initialize scroll to bottom button
     */
    initScrollToBottom() {
        const chatMessages = this.elements.chatMessages;
        let btn = this.elements.scrollToBottomBtn;

        if (!chatMessages) return;

        if (!btn) {
            btn = document.createElement('button');
            btn.id = 'scrollToBottomBtn';
            btn.className = 'scroll-to-bottom';
            btn.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            `;
            btn.setAttribute('aria-label', 'Retour en bas');
            chatMessages.appendChild(btn);
            this.elements.scrollToBottomBtn = btn;
        }

        btn.addEventListener('click', () => {
            chatMessages.scrollTop = chatMessages.scrollHeight;
            btn.classList.remove('visible');
        });

        chatMessages.addEventListener('scroll', () => {
            const { scrollTop, scrollHeight, clientHeight } = chatMessages;
            const distanceFromBottom = scrollHeight - scrollTop - clientHeight;

            if (distanceFromBottom > 200) {
                btn.classList.add('visible');
            } else {
                btn.classList.remove('visible');
            }
        });
    }

    /**
     * Toggle dark mode
     * @param {boolean} enabled
     */
    setDarkMode(enabled) {
        // Remove both classes first, then add the correct one
        document.documentElement.classList.remove('light', 'dark');
        document.documentElement.classList.add(enabled ? 'dark' : 'light');
        stateManager.set('darkMode', enabled);
        localStorage.setItem('theme', enabled ? 'dark' : 'light');
    }

    /**
     * Load saved theme
     */
    loadSavedTheme() {
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            this.setDarkMode(true);
        }
    }
}

// Export singleton
export const uiManager = new UIManager();
export default uiManager;
