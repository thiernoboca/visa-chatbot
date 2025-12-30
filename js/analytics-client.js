/**
 * Analytics Client - Chatbot Visa CI
 * Client JavaScript pour le tracking des événements
 * 
 * @version 1.0.0
 */

class AnalyticsClient {
    
    /**
     * URL de l'API Analytics
     */
    static API_URL = 'php/analytics-service.php';
    
    /**
     * Session ID courante
     */
    static sessionId = null;
    
    /**
     * Timestamps de début des étapes (pour calculer la durée)
     */
    static stepStartTimes = {};
    
    /**
     * Queue d'événements pour batch (si offline)
     */
    static eventQueue = [];
    
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
        
        // Charger la queue depuis localStorage si elle existe
        this.loadQueue();
        
        // Écouter les événements de visibilité pour détecter les abandons
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                this.flushQueue();
            }
        });
        
        // Flush la queue avant de quitter
        window.addEventListener('beforeunload', () => {
            this.flushQueue();
        });
        
        // Flush la queue en background toutes les 30 secondes
        setInterval(() => this.flushQueue(), 30000);
        
        if (this.debug) {
            console.log('[Analytics] Initialized with session:', sessionId);
        }
    }
    
    /**
     * Track le début d'une étape
     * 
     * @param {string} step - Nom de l'étape
     */
    static trackStepStart(step) {
        if (!this.sessionId) return;
        
        this.stepStartTimes[step] = performance.now();
        
        this.queueEvent('step.start', {
            step: step,
            timestamp: Date.now()
        });
        
        if (this.debug) {
            console.log('[Analytics] Step started:', step);
        }
    }
    
    /**
     * Track la complétion d'une étape
     * 
     * @param {string} step - Nom de l'étape
     */
    static trackStepComplete(step) {
        if (!this.sessionId) return;
        
        let durationSeconds = 0;
        
        if (this.stepStartTimes[step]) {
            durationSeconds = (performance.now() - this.stepStartTimes[step]) / 1000;
            delete this.stepStartTimes[step];
        }
        
        this.queueEvent('step.complete', {
            step: step,
            duration_seconds: Math.round(durationSeconds * 100) / 100,
            timestamp: Date.now()
        });
        
        if (this.debug) {
            console.log('[Analytics] Step completed:', step, 'Duration:', durationSeconds.toFixed(2) + 's');
        }
    }
    
    /**
     * Track un abandon d'étape
     * 
     * @param {string} step - Nom de l'étape
     * @param {string} reason - Raison de l'abandon
     */
    static trackStepAbandon(step, reason = 'user_left') {
        if (!this.sessionId) return;
        
        this.queueEvent('step.abandon', {
            step: step,
            reason: reason,
            timestamp: Date.now()
        });
        
        // Flush immédiatement les abandons
        this.flushQueue();
        
        if (this.debug) {
            console.log('[Analytics] Step abandoned:', step, 'Reason:', reason);
        }
    }
    
    /**
     * Track une erreur de validation
     * 
     * @param {string} step - Étape actuelle
     * @param {string} errorType - Type d'erreur
     * @param {string} message - Message d'erreur
     */
    static trackError(step, errorType, message = '') {
        if (!this.sessionId) return;
        
        this.queueEvent('validation.error', {
            step: step,
            field: errorType.split(':')[0] || 'unknown',
            error_type: errorType,
            message_length: message.length,
            timestamp: Date.now()
        });
        
        if (this.debug) {
            console.log('[Analytics] Validation error:', errorType, 'at step:', step);
        }
    }
    
    /**
     * Track une interaction utilisateur
     * 
     * @param {string} element - Élément avec lequel l'utilisateur a interagi
     * @param {string} action - Type d'action (click, hover, focus, etc.)
     */
    static trackInteraction(element, action) {
        if (!this.sessionId) return;
        
        this.queueEvent('user.interaction', {
            element: element,
            action: action,
            timestamp: Date.now()
        });
        
        if (this.debug) {
            console.log('[Analytics] Interaction:', action, 'on', element);
        }
    }
    
    /**
     * Track un scan de passeport
     * 
     * @param {boolean} success - Succès ou échec
     * @param {string} passportType - Type de passeport détecté
     * @param {number} processingTimeMs - Temps de traitement en ms
     */
    static trackPassportScan(success, passportType = null, processingTimeMs = 0) {
        if (!this.sessionId) return;
        
        const event = success ? 'passport.scan.success' : 'passport.scan.failure';
        
        this.queueEvent(event, {
            passport_type: passportType,
            processing_time_ms: processingTimeMs,
            timestamp: Date.now()
        });
        
        // Flush immédiatement les scans
        this.flushQueue();
        
        if (this.debug) {
            console.log('[Analytics] Passport scan:', success ? 'SUCCESS' : 'FAILURE', passportType);
        }
    }
    
    /**
     * Track le début d'une session
     */
    static trackSessionStart() {
        if (!this.sessionId) return;
        
        this.queueEvent('session.start', {
            referrer: document.referrer || 'direct',
            screen_width: window.innerWidth,
            screen_height: window.innerHeight,
            user_agent: navigator.userAgent,
            language: navigator.language,
            timestamp: Date.now()
        });
        
        this.flushQueue();
        
        if (this.debug) {
            console.log('[Analytics] Session started');
        }
    }
    
    /**
     * Track la fin d'une session
     * 
     * @param {boolean} completed - Si la demande a été complétée
     */
    static trackSessionEnd(completed = false) {
        if (!this.sessionId) return;
        
        this.queueEvent('session.end', {
            completed: completed,
            timestamp: Date.now()
        });
        
        // Flush immédiatement
        this.flushQueue();
        
        if (this.debug) {
            console.log('[Analytics] Session ended, completed:', completed);
        }
    }
    
    /**
     * Ajoute un événement à la queue
     * 
     * @param {string} event - Type d'événement
     * @param {Object} data - Données
     */
    static queueEvent(event, data) {
        this.eventQueue.push({
            session_id: this.sessionId,
            event: event,
            data: data
        });
        
        this.saveQueue();
        
        // Flush si la queue est trop grande
        if (this.eventQueue.length >= 10) {
            this.flushQueue();
        }
    }
    
    /**
     * Envoie la queue d'événements au serveur
     */
    static async flushQueue() {
        if (this.eventQueue.length === 0) return;
        
        const events = [...this.eventQueue];
        this.eventQueue = [];
        this.saveQueue();
        
        try {
            // Envoyer chaque événement (pourrait être optimisé avec un batch endpoint)
            for (const event of events) {
                await fetch(this.API_URL + '?action=track', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(event),
                    credentials: 'include',
                    // Utiliser keepalive pour que la requête survive à la fermeture de page
                    keepalive: true
                });
            }
            
            if (this.debug) {
                console.log('[Analytics] Flushed', events.length, 'events');
            }
        } catch (error) {
            // Remettre les événements dans la queue en cas d'erreur
            this.eventQueue = [...events, ...this.eventQueue];
            this.saveQueue();
            
            if (this.debug) {
                console.error('[Analytics] Flush failed:', error);
            }
        }
    }
    
    /**
     * Sauvegarde la queue dans localStorage
     */
    static saveQueue() {
        try {
            localStorage.setItem('analytics_queue', JSON.stringify(this.eventQueue));
        } catch (e) {
            // localStorage peut être désactivé
        }
    }
    
    /**
     * Charge la queue depuis localStorage
     */
    static loadQueue() {
        try {
            const saved = localStorage.getItem('analytics_queue');
            if (saved) {
                this.eventQueue = JSON.parse(saved) || [];
            }
        } catch (e) {
            this.eventQueue = [];
        }
    }
    
    /**
     * Récupère le résumé du dashboard
     * 
     * @returns {Promise<Object>} Données du dashboard
     */
    static async getDashboardSummary() {
        try {
            const response = await fetch(this.API_URL + '?action=summary', {
                credentials: 'include'
            });
            const data = await response.json();
            return data.success ? data.data : null;
        } catch (error) {
            console.error('[Analytics] Failed to get dashboard:', error);
            return null;
        }
    }
    
    /**
     * Récupère le rapport de funnel
     * 
     * @param {string} startDate - Date de début (YYYY-MM-DD)
     * @param {string} endDate - Date de fin (YYYY-MM-DD)
     * @returns {Promise<Object>} Données du funnel
     */
    static async getFunnelReport(startDate = null, endDate = null) {
        try {
            let url = this.API_URL + '?action=funnel';
            if (startDate) url += '&start=' + startDate;
            if (endDate) url += '&end=' + endDate;
            
            const response = await fetch(url, { credentials: 'include' });
            const data = await response.json();
            return data.success ? data.data : null;
        } catch (error) {
            console.error('[Analytics] Failed to get funnel:', error);
            return null;
        }
    }
}

// Exposer globalement
window.AnalyticsClient = AnalyticsClient;

