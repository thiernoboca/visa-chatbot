/**
 * API Module
 * Handles all API communications
 *
 * @version 4.0.0
 * @module API
 */

import { CONFIG } from './config.js';
import { stateManager } from './state.js';

/**
 * API Manager class
 */
export class APIManager {
    constructor() {
        this.endpoints = { ...CONFIG.endpoints };
        this.defaultHeaders = {
            'Content-Type': 'application/json'
        };
    }

    /**
     * Initialize API manager
     * @param {Object} options
     */
    init(options = {}) {
        if (options.endpoints) {
            this.endpoints = { ...this.endpoints, ...options.endpoints };
        }
        return this;
    }

    /**
     * Get headers with session ID
     * @returns {Object}
     */
    getHeaders() {
        const headers = { ...this.defaultHeaders };
        const sessionId = stateManager.getSessionId();
        if (sessionId) {
            headers['X-Session-ID'] = sessionId;
        }
        return headers;
    }

    /**
     * Initialize session
     * @param {string} existingSessionId - Optional existing session ID
     * @param {string} language - Optional language code ('fr' or 'en')
     * @returns {Promise<Object>}
     */
    async initSession(existingSessionId = null, language = null) {
        let url = `${this.endpoints.api}?action=init`;
        if (existingSessionId) {
            url += `&session_id=${encodeURIComponent(existingSessionId)}`;
        }
        if (language) {
            url += `&lang=${encodeURIComponent(language)}`;
        }

        const response = await fetch(url, {
            credentials: 'include'
        });

        return response.json();
    }

    /**
     * Send message to chatbot
     * @param {string} message
     * @param {Object} metadata
     * @returns {Promise<Object>}
     */
    async sendMessage(message, metadata = {}) {
        const response = await fetch(this.endpoints.api, {
            method: 'POST',
            credentials: 'include',
            headers: this.getHeaders(),
            body: JSON.stringify({
                action: 'message',
                session_id: stateManager.getSessionId(),
                message: message,
                metadata: metadata
            })
        });

        return response.json();
    }

    /**
     * Navigate to specific step
     * @param {string} targetStep
     * @returns {Promise<Object>}
     */
    async navigateToStep(targetStep) {
        const response = await fetch(this.endpoints.api, {
            method: 'POST',
            headers: this.getHeaders(),
            body: JSON.stringify({
                action: 'navigate',
                session_id: stateManager.getSessionId(),
                target_step: targetStep
            })
        });

        return response.json();
    }

    /**
     * Send passport OCR data
     * @param {Object} ocrData
     * @returns {Promise<Object>}
     */
    async sendPassportData(ocrData) {
        const response = await fetch(this.endpoints.api, {
            method: 'POST',
            headers: this.getHeaders(),
            body: JSON.stringify({
                action: 'passport_ocr',
                session_id: stateManager.getSessionId(),
                ocr_data: ocrData
            })
        });

        return response.json();
    }

    /**
     * Extract document
     * @param {string} docType
     * @param {string} fileData - Base64 encoded
     * @param {string} mimeType
     * @param {string} fileName
     * @returns {Promise<Object>}
     */
    async extractDocument(docType, fileData, mimeType, fileName) {
        const response = await fetch(this.endpoints.api, {
            method: 'POST',
            headers: this.getHeaders(),
            body: JSON.stringify({
                action: 'extract_document',
                session_id: stateManager.getSessionId(),
                document_type: docType,
                file_data: fileData,
                mime_type: mimeType,
                file_name: fileName
            })
        });

        return response.json();
    }

    /**
     * Validate documents (cross-validation)
     * @param {Object} documents
     * @returns {Promise<Object>}
     */
    async validateDocuments(documents) {
        const response = await fetch(this.endpoints.api, {
            method: 'POST',
            credentials: 'include',
            headers: this.getHeaders(),
            body: JSON.stringify({
                action: 'validate_documents',
                session_id: stateManager.getSessionId(),
                documents: documents
            })
        });

        return response.json();
    }

    /**
     * Submit application
     * @param {Object} data
     * @returns {Promise<Object>}
     */
    async submitApplication(data) {
        const response = await fetch(this.endpoints.api, {
            method: 'POST',
            headers: this.getHeaders(),
            body: JSON.stringify({
                action: 'submit_application',
                session_id: stateManager.getSessionId(),
                data: data
            })
        });

        return response.json();
    }

    /**
     * Get coherence validation
     * @returns {Promise<Object>}
     */
    async getCoherenceValidation() {
        const response = await fetch(this.endpoints.coherence, {
            method: 'POST',
            headers: this.getHeaders(),
            body: JSON.stringify({
                action: 'validate',
                session_id: stateManager.getSessionId()
            })
        });

        return response.json();
    }

    /**
     * Upload file
     * @param {File} file
     * @param {string} type
     * @returns {Promise<Object>}
     */
    async uploadFile(file, type) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('type', type);
        formData.append('session_id', stateManager.getSessionId());

        const response = await fetch(this.endpoints.upload, {
            method: 'POST',
            body: formData
        });

        return response.json();
    }

    /**
     * Fetch passport OCR
     * @param {string} imageBase64
     * @param {string} mimeType
     * @returns {Promise<Object>}
     */
    async fetchPassportOCR(imageBase64, mimeType) {
        const response = await fetch(this.endpoints.ocr, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                image: imageBase64,
                mime_type: mimeType,
                action: 'extract_passport'
            })
        });

        return response.json();
    }
}

// Export singleton
export const apiManager = new APIManager();
export default apiManager;
