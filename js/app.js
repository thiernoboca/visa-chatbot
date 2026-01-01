/**
 * Application Entry Point
 * Initializes the chatbot application
 *
 * For ES6 Module environments, import from './modules/index.js'
 * For legacy environments, use the bundled version or this script
 *
 * @version 6.0.0
 */

// Check if running in module mode
const isModuleMode = typeof import.meta !== 'undefined';

/**
 * Initialize the chatbot application
 */
async function initApp() {
    // Wait for DOM
    if (document.readyState !== 'complete') {
        await new Promise(resolve => window.addEventListener('load', resolve));
    }

    // Configuration
    const config = {
        debug: window.CHATBOT_DEBUG || false,
        language: document.documentElement.lang || 'fr',
        apiEndpoint: 'php/chat-handler.php',
        ocrEndpoint: 'php/document-upload-handler-v2.php'
    };

    // Check for existing session ID in URL
    const urlParams = new URLSearchParams(window.location.search);
    const sessionId = urlParams.get('session_id');
    if (sessionId) {
        config.initialSessionId = sessionId;
    }

    // Initialize chatbot
    if (isModuleMode) {
        // ES6 Module mode - cache buster for module updates
        const cacheBuster = '?v=6.0.0';
        const { VisaChatbot } = await import('./modules/index.js' + cacheBuster);
        window.chatbot = new VisaChatbot(config);
    } else if (window.VisaChatbot) {
        // Bundled mode
        window.chatbot = new window.VisaChatbot(config);
    } else {
        console.error('VisaChatbot not available. Make sure to include the chatbot modules.');
    }
}

// Auto-initialize
initApp().catch(console.error);

// Expose initialization function
window.initChatbot = initApp;
