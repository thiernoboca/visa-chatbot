/**
 * A/B Testing Client - Chatbot Visa CI
 * Client JavaScript pour les tests A/B
 * 
 * @version 1.0.0
 */

class ABTestingClient {
    
    /**
     * URL de l'API A/B Testing
     */
    static API_URL = 'php/ab-testing-service.php';
    
    /**
     * Session ID courante
     */
    static sessionId = null;
    
    /**
     * Variants assignés (cache local)
     */
    static variants = {};
    
    /**
     * Configuration des variants
     */
    static variantConfigs = {};
    
    /**
     * Mode debug
     */
    static debug = false;
    
    /**
     * Initialise le client avec un session ID
     * 
     * @param {string} sessionId - ID de session
     * @param {Object} options - Options
     */
    static init(sessionId, options = {}) {
        this.sessionId = sessionId;
        this.debug = options.debug || false;
        
        // Charger les variants depuis localStorage
        this.loadVariantsFromStorage();
        
        if (this.debug) {
            console.log('[A/B Testing] Initialized with session:', sessionId);
        }
    }
    
    /**
     * Charge les variants depuis localStorage
     */
    static loadVariantsFromStorage() {
        try {
            const stored = localStorage.getItem('ab_variants_' + this.sessionId);
            if (stored) {
                const data = JSON.parse(stored);
                this.variants = data.variants || {};
                this.variantConfigs = data.configs || {};
            }
        } catch (e) {
            // localStorage peut être désactivé
        }
    }
    
    /**
     * Sauvegarde les variants dans localStorage
     */
    static saveVariantsToStorage() {
        try {
            localStorage.setItem('ab_variants_' + this.sessionId, JSON.stringify({
                variants: this.variants,
                configs: this.variantConfigs
            }));
        } catch (e) {
            // localStorage peut être désactivé
        }
    }
    
    /**
     * Récupère le variant assigné pour un test
     * 
     * @param {string} testId - ID du test
     * @returns {Promise<Object>} Variant et sa configuration
     */
    static async getVariant(testId) {
        // Vérifier le cache local d'abord
        if (this.variants[testId]) {
            if (this.debug) {
                console.log('[A/B Testing] Using cached variant for', testId, ':', this.variants[testId]);
            }
            return {
                variant: this.variants[testId],
                config: this.variantConfigs[testId] || {}
            };
        }
        
        // Récupérer depuis l'API
        try {
            const url = `${this.API_URL}?action=variant&session_id=${encodeURIComponent(this.sessionId)}&test_id=${encodeURIComponent(testId)}`;
            const response = await fetch(url, { credentials: 'include' });
            const result = await response.json();
            
            if (result.success) {
                this.variants[testId] = result.variant;
                this.variantConfigs[testId] = result.config || {};
                this.saveVariantsToStorage();
                
                if (this.debug) {
                    console.log('[A/B Testing] Assigned variant for', testId, ':', result.variant);
                }
                
                return {
                    variant: result.variant,
                    config: result.config || {}
                };
            }
        } catch (error) {
            if (this.debug) {
                console.error('[A/B Testing] Failed to get variant:', error);
            }
        }
        
        // Fallback au control
        return { variant: 'control', config: {} };
    }
    
    /**
     * Récupère le variant de manière synchrone (depuis le cache)
     * 
     * @param {string} testId - ID du test
     * @returns {string} Nom du variant ou 'control'
     */
    static getVariantSync(testId) {
        return this.variants[testId] || 'control';
    }
    
    /**
     * Récupère la config d'un variant de manière synchrone
     * 
     * @param {string} testId - ID du test
     * @returns {Object} Configuration du variant
     */
    static getVariantConfigSync(testId) {
        return this.variantConfigs[testId] || {};
    }
    
    /**
     * Track une conversion pour un test
     * 
     * @param {string} testId - ID du test
     * @returns {Promise<boolean>} Succès
     */
    static async trackConversion(testId) {
        if (!this.sessionId || !this.variants[testId]) {
            return false;
        }
        
        try {
            const response = await fetch(this.API_URL + '?action=convert', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    session_id: this.sessionId,
                    test_id: testId
                }),
                credentials: 'include'
            });
            
            const result = await response.json();
            
            if (this.debug) {
                console.log('[A/B Testing] Conversion tracked for', testId, ':', result.converted);
            }
            
            return result.converted || false;
        } catch (error) {
            if (this.debug) {
                console.error('[A/B Testing] Failed to track conversion:', error);
            }
            return false;
        }
    }
    
    /**
     * Applique un variant à un élément DOM
     * 
     * @param {string} testId - ID du test
     * @param {Object} handlers - Fonctions par variant { control: fn, variant_a: fn, ... }
     */
    static async apply(testId, handlers) {
        const { variant, config } = await this.getVariant(testId);
        
        const handler = handlers[variant] || handlers.control || (() => {});
        handler(config);
        
        if (this.debug) {
            console.log('[A/B Testing] Applied variant', variant, 'for test', testId);
        }
    }
    
    /**
     * Applique un variant de manière synchrone (utilise le cache)
     * 
     * @param {string} testId - ID du test
     * @param {Object} handlers - Fonctions par variant
     */
    static applySync(testId, handlers) {
        const variant = this.getVariantSync(testId);
        const config = this.getVariantConfigSync(testId);
        
        const handler = handlers[variant] || handlers.control || (() => {});
        handler(config);
    }
    
    /**
     * Précharge les variants pour une liste de tests
     * 
     * @param {string[]} testIds - Liste des IDs de test
     */
    static async preloadVariants(testIds) {
        const promises = testIds.map(testId => this.getVariant(testId));
        await Promise.all(promises);
        
        if (this.debug) {
            console.log('[A/B Testing] Preloaded variants for:', testIds);
        }
    }
    
    /**
     * Récupère les tests actifs
     * 
     * @returns {Promise<Object>} Tests actifs
     */
    static async getActiveTests() {
        try {
            const response = await fetch(this.API_URL + '?action=tests', { credentials: 'include' });
            const result = await response.json();
            return result.success ? result.data : {};
        } catch (error) {
            if (this.debug) {
                console.error('[A/B Testing] Failed to get active tests:', error);
            }
            return {};
        }
    }
    
    /**
     * Récupère les résultats d'un test (admin)
     * 
     * @param {string} testId - ID du test (optionnel, tous si vide)
     * @returns {Promise<Object>} Résultats
     */
    static async getResults(testId = null) {
        try {
            let url = this.API_URL + '?action=results';
            if (testId) {
                url += '&test_id=' + encodeURIComponent(testId);
            }
            
            const response = await fetch(url, { credentials: 'include' });
            const result = await response.json();
            return result.success ? result.data : null;
        } catch (error) {
            if (this.debug) {
                console.error('[A/B Testing] Failed to get results:', error);
            }
            return null;
        }
    }
    
    /**
     * Helper: Teste si le variant actuel est un variant spécifique
     * 
     * @param {string} testId - ID du test
     * @param {string} variantName - Nom du variant à tester
     * @returns {boolean} True si c'est le variant assigné
     */
    static isVariant(testId, variantName) {
        return this.getVariantSync(testId) === variantName;
    }
    
    /**
     * Helper: Retourne une valeur selon le variant
     * 
     * @param {string} testId - ID du test
     * @param {Object} values - Valeurs par variant
     * @param {*} defaultValue - Valeur par défaut
     * @returns {*} Valeur pour le variant actuel
     */
    static getValue(testId, values, defaultValue = null) {
        const variant = this.getVariantSync(testId);
        return values[variant] ?? values.control ?? defaultValue;
    }
}

// Exposer globalement
window.ABTestingClient = ABTestingClient;

