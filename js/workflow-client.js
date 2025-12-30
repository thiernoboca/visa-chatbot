/**
 * Workflow Client - Gestion des étapes côté client
 * Complément au chatbot principal
 * 
 * @version 1.0.0
 */

class WorkflowClient {
    /**
     * Labels des étapes en français
     */
    static STEP_LABELS_FR = {
        'welcome': 'Accueil',
        'residence': 'Résidence',
        'passport': 'Passeport',
        'photo': 'Photo d\'identité',
        'contact': 'Coordonnées',
        'trip': 'Voyage',
        'health': 'Santé',
        'customs': 'Douanes',
        'confirm': 'Confirmation'
    };
    
    /**
     * Labels des étapes en anglais
     */
    static STEP_LABELS_EN = {
        'welcome': 'Welcome',
        'residence': 'Residence',
        'passport': 'Passport',
        'photo': 'ID Photo',
        'contact': 'Contact',
        'trip': 'Travel',
        'health': 'Health',
        'customs': 'Customs',
        'confirm': 'Confirmation'
    };
    
    /**
     * Icônes des étapes
     */
    static STEP_ICONS = {
        'welcome': '👋',
        'residence': '🏠',
        'passport': '📘',
        'photo': '📷',
        'contact': '📞',
        'trip': '✈️',
        'health': '💉',
        'customs': '📦',
        'confirm': '✅'
    };
    
    /**
     * Types d'input attendus par étape
     */
    static STEP_INPUT_TYPES = {
        'welcome': 'selection',
        'residence': 'selection',
        'passport': 'file',
        'photo': 'file',
        'contact': 'text',
        'trip': 'mixed',
        'health': 'selection',
        'customs': 'selection',
        'confirm': 'selection'
    };
    
    /**
     * Retourne le label d'une étape
     */
    static getStepLabel(step, lang = 'fr') {
        const labels = lang === 'fr' ? this.STEP_LABELS_FR : this.STEP_LABELS_EN;
        return labels[step] || step;
    }
    
    /**
     * Retourne l'icône d'une étape
     */
    static getStepIcon(step) {
        return this.STEP_ICONS[step] || '📋';
    }
    
    /**
     * Retourne le type d'input attendu pour une étape
     */
    static getExpectedInputType(step) {
        return this.STEP_INPUT_TYPES[step] || 'text';
    }
    
    /**
     * Calcule le pourcentage de progression
     */
    static calculateProgress(currentStep, totalSteps = 9) {
        const steps = Object.keys(this.STEP_LABELS_FR);
        const index = steps.indexOf(currentStep);
        if (index === -1) return 0;
        return Math.round((index / (totalSteps - 1)) * 100);
    }
    
    /**
     * Retourne les informations sur le workflow selon le type de passeport
     */
    static getWorkflowInfo(passportType, lang = 'fr') {
        const workflows = {
            'DIPLOMATIQUE': {
                fr: {
                    title: 'Passeport Diplomatique',
                    badge: '🎖️ Prioritaire',
                    features: [
                        '✓ Traitement prioritaire (24-48h)',
                        '✓ Visa gratuit',
                        '✓ Note verbale requise',
                        '✗ Justificatif d\'hébergement non requis',
                        '✗ Justificatif de ressources non requis'
                    ]
                },
                en: {
                    title: 'Diplomatic Passport',
                    badge: '🎖️ Priority',
                    features: [
                        '✓ Priority processing (24-48h)',
                        '✓ Free visa',
                        '✓ Verbal note required',
                        '✗ Accommodation proof not required',
                        '✗ Financial proof not required'
                    ]
                }
            },
            'ORDINAIRE': {
                fr: {
                    title: 'Passeport Ordinaire',
                    badge: '📘 Standard',
                    features: [
                        '• Délai: 5-10 jours ouvrés',
                        '• Frais: 73,000-120,000 ETB',
                        '• Lettre d\'invitation requise',
                        '• Justificatif d\'hébergement requis',
                        '• Justificatif de ressources requis'
                    ]
                },
                en: {
                    title: 'Regular Passport',
                    badge: '📘 Standard',
                    features: [
                        '• Processing: 5-10 business days',
                        '• Fees: 73,000-120,000 ETB',
                        '• Invitation letter required',
                        '• Accommodation proof required',
                        '• Financial proof required'
                    ]
                }
            }
        };
        
        const category = this.getWorkflowCategory(passportType);
        return workflows[category]?.[lang] || workflows['ORDINAIRE'][lang];
    }
    
    /**
     * Retourne la catégorie de workflow pour un type de passeport
     */
    static getWorkflowCategory(passportType) {
        const diplomaticTypes = ['DIPLOMATIQUE', 'SERVICE', 'LAISSEZ_PASSER', 'SPECIAL', 'REFUGIE', 'APATRIDE'];
        return diplomaticTypes.includes(passportType) ? 'DIPLOMATIQUE' : 'ORDINAIRE';
    }
    
    /**
     * Formate une date pour l'affichage
     */
    static formatDate(dateString, lang = 'fr') {
        if (!dateString) return '';
        
        const date = new Date(dateString);
        const options = { day: 'numeric', month: 'long', year: 'numeric' };
        
        return date.toLocaleDateString(lang === 'fr' ? 'fr-FR' : 'en-US', options);
    }
    
    /**
     * Formate un montant
     */
    static formatAmount(amount, currency = 'ETB') {
        if (amount === 0) return lang === 'fr' ? 'GRATUIT' : 'FREE';
        return new Intl.NumberFormat('fr-FR').format(amount) + ' ' + currency;
    }
    
    /**
     * Vérifie si un email est valide
     */
    static validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    /**
     * Vérifie si un numéro de téléphone est valide (format international)
     */
    static validatePhone(phone) {
        const re = /^\+[0-9]{1,4}[0-9]{6,14}$/;
        return re.test(phone.replace(/\s/g, ''));
    }
    
    /**
     * Vérifie si une date est dans le futur
     */
    static isFutureDate(dateString) {
        const date = new Date(dateString);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return date >= today;
    }
    
    /**
     * Calcule la durée de séjour en jours
     */
    static calculateStayDuration(arrivalDate, departureDate) {
        const arrival = new Date(arrivalDate);
        const departure = new Date(departureDate);
        const diffTime = Math.abs(departure - arrival);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        return diffDays;
    }
}

// Export global
window.WorkflowClient = WorkflowClient;

