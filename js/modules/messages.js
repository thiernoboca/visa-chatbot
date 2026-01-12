/**
 * Messages Module
 * Handles message display (bot, user, system)
 *
 * @version 4.0.0
 * @module Messages
 */

import { PERSONA } from './config.js';
import { i18n } from './i18n.js';
import { stateManager } from './state.js';

/**
 * Messages Manager class
 */
export class MessagesManager {
    constructor(containerSelector = '#chatMessages') {
        this.container = null;
        this.containerSelector = containerSelector;
        this.typewriterSpeed = 15;
        this.enableTypewriter = true;
        this.typingIndicator = null;
        this.showTypingDelay = 300; // ms to show typing before message (reduced from 800ms for better UX)
    }

    /**
     * Set typing indicator instance
     * @param {Object} typingIndicator - Instance with show/hide methods
     */
    setTypingIndicator(typingIndicator) {
        this.typingIndicator = typingIndicator;
    }

    /**
     * Initialize with DOM container
     * @param {HTMLElement|string} container
     */
    init(container) {
        if (typeof container === 'string') {
            this.container = document.querySelector(container);
        } else {
            this.container = container;
        }

        if (!this.container) {
            this.container = document.querySelector(this.containerSelector);
        }

        return this;
    }

    /**
     * Set typewriter configuration
     * @param {boolean} enabled
     * @param {number} speed
     */
    setTypewriter(enabled, speed = 15) {
        this.enableTypewriter = enabled;
        this.typewriterSpeed = speed;
    }

    /**
     * Add bot message
     * @param {string} content
     * @param {Object} options
     * @returns {Promise<HTMLElement>}
     */
    async addBotMessage(content, options = {}) {
        const {
            typewriter = this.enableTypewriter,
            animate = true,
            delay = 0,
            showTyping = true
        } = options;

        if (delay > 0) {
            await this.wait(delay);
        }

        // Show typing indicator
        if (showTyping && this.typingIndicator) {
            this.typingIndicator.show('Aya');
            await this.wait(this.showTypingDelay);
            this.typingIndicator.hide();
            await this.wait(100); // Brief pause after hiding
        }

        const message = this.createMessageElement('bot', typewriter ? '' : content);
        this.container?.appendChild(message);

        if (animate) {
            this.animateMessage(message);
        }

        this.scrollToBottom();

        // Typewriter effect
        if (typewriter && content) {
            const contentEl = message.querySelector('.message-content');
            if (contentEl) {
                await this.typewriterEffect(contentEl, content);
            }
        }

        // Track message
        stateManager.addMessage({ role: 'bot', content, timestamp: Date.now() });

        return message;
    }

    /**
     * Add user message
     * @param {string} content
     * @returns {HTMLElement}
     */
    addUserMessage(content) {
        const message = this.createMessageElement('user', content);
        this.container?.appendChild(message);
        this.scrollToBottom();

        stateManager.addMessage({ role: 'user', content, timestamp: Date.now() });

        return message;
    }

    /**
     * Add system message
     * @param {string} content
     * @returns {HTMLElement}
     */
    addSystemMessage(content) {
        const div = document.createElement('div');
        div.className = 'message system';
        div.innerHTML = `<div class="message-content system-content">${content}</div>`;
        this.container?.appendChild(div);
        this.scrollToBottom();
        return div;
    }

    /**
     * Create message element
     * @param {string} role - 'bot' or 'user'
     * @param {string} content
     * @returns {HTMLElement}
     */
    createMessageElement(role, content) {
        const div = document.createElement('div');
        div.className = `message ${role}`;

        // Message grouping
        const messages = this.container?.querySelectorAll('.message');
        if (messages && messages.length > 0) {
            const lastMessage = messages[messages.length - 1];
            if (lastMessage?.classList.contains(role)) {
                div.classList.add('message-grouped');
            }
        }

        const avatar = document.createElement('div');
        avatar.className = 'message-avatar';

        if (role === 'bot') {
            // Modern avatar with online indicator (prototype style)
            avatar.innerHTML = `
                <div class="avatar-wrapper">
                    <div class="avatar-icon">
                        <span class="material-symbols-outlined">smart_toy</span>
                    </div>
                    <div class="avatar-online-indicator"></div>
                </div>
            `;
        } else {
            // User avatar
            avatar.innerHTML = '<span class="material-symbols-outlined">person</span>';
        }

        const contentDiv = document.createElement('div');
        contentDiv.className = 'message-content';
        contentDiv.innerHTML = this.parseContent(content);

        // Timestamp
        const timeSpan = document.createElement('span');
        timeSpan.className = 'message-time';
        timeSpan.textContent = i18n.formatTime();
        contentDiv.appendChild(timeSpan);

        div.appendChild(avatar);
        div.appendChild(contentDiv);

        return div;
    }

    /**
     * Parse message content (enhanced markdown)
     * Supports: bold, italic, lists, emojis, line breaks
     * @param {string} content
     * @returns {string}
     */
    parseContent(content) {
        if (!content) return '';

        // Split into lines for processing
        const lines = content.split('\n');
        const result = [];
        let inList = false;

        for (const line of lines) {
            const trimmed = line.trim();

            // Empty line - close list if open, add spacing
            if (!trimmed) {
                if (inList) {
                    result.push('</ul>');
                    inList = false;
                }
                result.push('<br/>');
                continue;
            }

            // Check if line is a list item (• ✓ ✗ - * or numbered)
            const listMatch = trimmed.match(/^([•✓✗\-\*]|\d+\.)\s+(.+)$/);
            if (listMatch) {
                if (!inList) {
                    result.push('<ul class="message-list">');
                    inList = true;
                }
                const itemContent = this.parseInline(listMatch[2]);
                const icon = listMatch[1];
                let iconClass = '';
                if (icon === '✓') iconClass = 'check';
                else if (icon === '✗') iconClass = 'cross';
                result.push(`<li class="${iconClass}">${itemContent}</li>`);
                continue;
            }

            // Regular paragraph - close list if open
            if (inList) {
                result.push('</ul>');
                inList = false;
            }

            // Parse inline formatting and wrap in paragraph
            const parsedLine = this.parseInline(trimmed);
            result.push(`<p>${parsedLine}</p>`);
        }

        // Close any open list
        if (inList) {
            result.push('</ul>');
        }

        return result.join('');
    }

    /**
     * Parse inline markdown formatting
     * @param {string} text
     * @returns {string}
     */
    parseInline(text) {
        return text
            // Bold: **text** or __text__
            .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
            .replace(/__([^_]+)__/g, '<strong>$1</strong>')
            // Italic: *text* or _text_ (not at start of line)
            .replace(/(?<!\*)\*([^*]+)\*(?!\*)/g, '<em>$1</em>')
            // Code: `text`
            .replace(/`([^`]+)`/g, '<code>$1</code>');
    }

    /**
     * Typewriter effect
     * @param {HTMLElement} element
     * @param {string} text
     */
    async typewriterEffect(element, text) {
        const parsedContent = this.parseContent(text);
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = parsedContent;
        const plainText = tempDiv.textContent || tempDiv.innerText;

        element.textContent = '';

        for (let i = 0; i < plainText.length; i++) {
            element.textContent += plainText.charAt(i);
            this.scrollToBottom();

            const char = plainText.charAt(i);
            if (['.', '!', '?'].includes(char)) {
                await this.wait(this.typewriterSpeed * 10);
            } else if ([',', ';', ':'].includes(char)) {
                await this.wait(this.typewriterSpeed * 3);
            } else {
                await this.wait(this.typewriterSpeed);
            }
        }

        // Apply final formatting
        element.innerHTML = parsedContent;
    }

    /**
     * Show typing indicator
     * @param {string} customText
     */
    showTyping(customText = null) {
        if (document.getElementById('typingIndicator')) return;

        stateManager.set('isTyping', true);
        const lang = stateManager.get('language') || 'fr';
        const text = customText || (lang === 'fr' ? 'Aya réfléchit...' : 'Aya is thinking...');

        const typing = document.createElement('div');
        typing.className = 'message bot';
        typing.id = 'typingIndicator';
        typing.innerHTML = `
            <div class="message-avatar">
                <div class="avatar-wrapper">
                    <div class="avatar-icon">
                        <span class="material-symbols-outlined">smart_toy</span>
                    </div>
                    <div class="avatar-online-indicator"></div>
                </div>
            </div>
            <div class="typing-bubble">
                <div class="typing-content">
                    <span class="typing-name">${PERSONA.name}</span>
                    <div class="typing-dots">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            </div>
        `;

        this.container?.appendChild(typing);
        this.scrollToBottom();
    }

    /**
     * Hide typing indicator
     */
    hideTyping() {
        stateManager.set('isTyping', false);
        document.getElementById('typingIndicator')?.remove();
    }

    /**
     * Animate message entrance
     * @param {HTMLElement} element
     */
    animateMessage(element) {
        element.style.opacity = '0';
        element.style.transform = 'translateY(10px)';

        requestAnimationFrame(() => {
            element.animate([
                { opacity: 0, transform: 'translateY(10px)' },
                { opacity: 1, transform: 'translateY(0)' }
            ], {
                duration: 300,
                easing: 'ease-out',
                fill: 'forwards'
            });
        });
    }

    /**
     * Scroll container to bottom
     */
    scrollToBottom() {
        if (this.container) {
            this.container.scrollTop = this.container.scrollHeight;
        }
    }

    /**
     * Clear all messages
     */
    clear() {
        if (this.container) {
            this.container.innerHTML = '';
        }
    }

    /**
     * Add action buttons to chat (for inline editing)
     * @param {string} html - HTML string for buttons
     * @returns {HTMLElement}
     */
    addActionButtons(html) {
        // Remove existing action buttons first
        this.clearActionButtons();

        const actionArea = document.createElement('div');
        actionArea.className = 'message-action-area';
        actionArea.id = 'message-action-area';
        actionArea.innerHTML = html;

        this.container?.appendChild(actionArea);
        this.scrollToBottom();

        return actionArea;
    }

    /**
     * Clear action buttons from chat
     */
    clearActionButtons() {
        const existing = document.getElementById('message-action-area');
        if (existing) {
            existing.remove();
        }
    }

    /**
     * Wait utility
     * @param {number} ms
     * @returns {Promise}
     */
    wait(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// Export singleton
export const messagesManager = new MessagesManager();
export default messagesManager;
