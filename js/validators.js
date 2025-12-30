/**
 * Validators Module - Chatbot Visa CI
 * Validation temps réel des entrées utilisateur
 * Aligné avec T_JS_Validation du dictionnaire
 * 
 * @version 2.0.0
 */

class Validators {
    
    /**
     * Patterns de validation (alignés avec T_JS_Validation)
     */
    static patterns = {
        // T_JS_Validation: passportnum - ^[A-Z0-9]{6,12}$
        passport: /^[A-Z0-9]{6,12}$/i,
        
        // T_JS_Validation: ContactEmail - Format email standard
        email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
        
        // T_JS_Validation: phoneNumber - Format international ^\+[0-9]{1,4}\s?[0-9]{6,14}$
        phoneInternational: /^\+[0-9]{1,4}\s?[0-9]{6,14}$/,
        
        // Patterns supplémentaires
        phone: /^\+?[0-9]{8,15}$/,
        date: /^\d{4}-\d{2}-\d{2}$/,
        mrzLine: /^[A-Z0-9<]{44}$/
    };
    
    /**
     * Messages d'erreur bilingues (T_JS_Validation)
     */
    static messages = {
        fr: {
            passportnum: 'Numéro de passeport invalide (6-12 caractères alphanumériques)',
            passportexpiry: 'Le passeport doit être valide au moins 6 mois après la date de voyage',
            email: 'Adresse email invalide',
            phone: 'Numéro de téléphone invalide (format: +XXX XXXXXXXXX)',
            datesCoherence: 'La date d\'arrivée doit être antérieure à la date de départ',
            stayDuration: 'Durée de séjour: 1 à 90 jours maximum',
            photo: 'Format accepté: JPG ou PNG, taille max 2 Mo',
            signature: 'La signature est obligatoire',
            vaccination: 'Veuillez télécharger votre certificat de vaccination (format PDF)',
            engagement: 'Vous devez accepter les conditions',
            required: 'Ce champ est obligatoire'
        },
        en: {
            passportnum: 'Invalid passport number (6-12 alphanumeric characters)',
            passportexpiry: 'Passport must be valid at least 6 months after travel date',
            email: 'Invalid email address',
            phone: 'Invalid phone number (format: +XXX XXXXXXXXX)',
            datesCoherence: 'Arrival date must be before departure date',
            stayDuration: 'Stay duration: 1 to 90 days maximum',
            photo: 'Accepted format: JPG or PNG, max size 2 MB',
            signature: 'Signature is required',
            vaccination: 'Please upload your vaccination certificate (PDF format)',
            engagement: 'You must accept the terms',
            required: 'This field is required'
        }
    };
    
    /**
     * Langue courante
     */
    static lang = 'fr';
    
    /**
     * Définit la langue
     */
    static setLanguage(lang) {
        this.lang = ['fr', 'en'].includes(lang) ? lang : 'fr';
    }
    
    /**
     * Retourne un message d'erreur
     */
    static getMessage(key) {
        return this.messages[this.lang]?.[key] || this.messages.fr[key] || key;
    }
    
    /**
     * Préfixes téléphoniques par pays
     */
    static phonePrefixes = {
        'ETH': { code: '+251', name: 'Éthiopie', format: '+251 XX XXX XXXX' },
        'DJI': { code: '+253', name: 'Djibouti', format: '+253 XX XX XX XX' },
        'KEN': { code: '+254', name: 'Kenya', format: '+254 XXX XXX XXX' },
        'SSD': { code: '+211', name: 'Soudan du Sud', format: '+211 XX XXX XXXX' },
        'SOM': { code: '+252', name: 'Somalie', format: '+252 XX XXX XXXX' },
        'UGA': { code: '+256', name: 'Ouganda', format: '+256 XX XXX XXXX' },
        'TZA': { code: '+255', name: 'Tanzanie', format: '+255 XX XXX XXXX' },
        'CIV': { code: '+225', name: 'Côte d\'Ivoire', format: '+225 XX XX XX XXXX' }
    };

    // ========================================
    // VALIDATIONS T_JS_Validation
    // ========================================
    
    /**
     * T_JS_Validation: passportnum
     * Règle: ^[A-Z0-9]{6,12}$
     */
    static validatePassportNumber(value) {
        const result = {
            isValid: false,
            value: value?.trim().toUpperCase() || '',
            error: null
        };
        
        if (!result.value) {
            result.error = this.getMessage('required');
            return result;
        }
        
        if (!this.patterns.passport.test(result.value)) {
            result.error = this.getMessage('passportnum');
            return result;
        }
        
        result.isValid = true;
        return result;
    }
    
    /**
     * T_JS_Validation: passportexpiry
     * Règle: Date > aujourd'hui + 6 mois
     */
    static validatePassportExpiry(expiryDate, travelDate = null) {
        const result = {
            isValid: false,
            value: expiryDate,
            error: null,
            warning: null
        };
        
        if (!expiryDate) {
            result.error = this.getMessage('required');
            return result;
        }
        
        const expiry = new Date(expiryDate);
        const now = new Date();
        now.setHours(0, 0, 0, 0);
        
        // Passeport expiré?
        if (expiry <= now) {
            result.error = this.lang === 'fr' 
                ? '⚠️ Votre passeport est expiré' 
                : '⚠️ Your passport has expired';
            return result;
        }
        
        // Calculer la date minimum (6 mois après voyage ou aujourd'hui)
        const referenceDate = travelDate ? new Date(travelDate) : now;
        const minExpiry = new Date(referenceDate);
        minExpiry.setMonth(minExpiry.getMonth() + 6);
        
        if (expiry < minExpiry) {
            result.error = this.getMessage('passportexpiry');
            return result;
        }
        
        result.isValid = true;
        return result;
    }
    
    /**
     * T_JS_Validation: ContactEmail
     * Règle: Format email valide
     */
    static validateEmail(email) {
        const result = {
            isValid: false,
            value: email?.trim().toLowerCase() || '',
            error: null,
            suggestions: []
        };
        
        if (!result.value) {
            result.error = this.getMessage('required');
            return result;
        }
        
        if (!this.patterns.email.test(result.value)) {
            result.error = this.getMessage('email');
            
            if (!result.value.includes('@')) {
                result.suggestions = ['@gmail.com', '@yahoo.com', '@outlook.com'];
            }
            return result;
        }
        
        // Vérifier les domaines mal orthographiés
        const domain = result.value.split('@')[1];
        const misspellings = {
            'gmial.com': 'gmail.com',
            'gmai.com': 'gmail.com',
            'gmail.co': 'gmail.com',
            'yahooo.com': 'yahoo.com',
            'outlok.com': 'outlook.com'
        };
        
        if (misspellings[domain]) {
            result.error = `Vouliez-vous dire @${misspellings[domain]} ?`;
            result.suggestions = [result.value.replace(domain, misspellings[domain])];
            return result;
        }
        
        result.isValid = true;
        return result;
    }
    
    /**
     * T_JS_Validation: phoneNumber
     * Règle: Format international ^\+[0-9]{1,4}\s?[0-9]{6,14}$
     */
    static validatePhone(phone, countryCode = null) {
        const result = {
            isValid: false,
            value: phone?.replace(/[\s\-\(\)]/g, '') || '',
            formatted: '',
            error: null
        };
        
        if (!result.value) {
            result.error = this.getMessage('required');
            return result;
        }
        
        // Ajouter le préfixe si manquant
        if (!result.value.startsWith('+')) {
            if (countryCode && this.phonePrefixes[countryCode]) {
                result.value = this.phonePrefixes[countryCode].code + result.value.replace(/^0/, '');
            } else if (result.value.startsWith('0')) {
                result.value = '+' + result.value.substring(1);
            } else {
                result.value = '+' + result.value;
            }
        }
        
        // Vérifier le format international
        if (!this.patterns.phoneInternational.test(result.value)) {
            result.error = this.getMessage('phone');
            return result;
        }
        
        result.formatted = this.formatPhone(result.value);
        result.isValid = true;
        return result;
    }
    
    /**
     * T_JS_Validation: ArriveeLe < DepartLe
     * Règle: Cohérence dates arrivée/départ
     */
    static validateTravelDates(arrivalDate, departureDate) {
        const result = {
            isValid: false,
            arrivalDate: arrivalDate,
            departureDate: departureDate,
            stayDuration: null,
            error: null
        };
        
        if (!arrivalDate || !departureDate) {
            result.error = this.getMessage('required');
            return result;
        }
        
        const arrival = new Date(arrivalDate);
        const departure = new Date(departureDate);
        const now = new Date();
        now.setHours(0, 0, 0, 0);
        
        // Arrivée doit être dans le futur
        if (arrival < now) {
            result.error = this.lang === 'fr' 
                ? 'La date d\'arrivée doit être dans le futur' 
                : 'Arrival date must be in the future';
            return result;
        }
        
        // Arrivée avant départ
        if (arrival >= departure) {
            result.error = this.getMessage('datesCoherence');
            return result;
        }
        
        // Calculer la durée
        result.stayDuration = Math.ceil((departure - arrival) / (1000 * 60 * 60 * 24));
        
        result.isValid = true;
        return result;
    }
    
    /**
     * T_JS_Validation: NbJoursVisaCourt
     * Règle: 1-90 jours
     */
    static validateStayDuration(days, maxDays = 90) {
        const result = {
            isValid: false,
            value: parseInt(days, 10),
            error: null
        };
        
        if (isNaN(result.value) || result.value < 1) {
            result.error = this.getMessage('stayDuration');
            return result;
        }
        
        if (result.value > maxDays) {
            result.error = this.lang === 'fr'
                ? `La durée maximale est de ${maxDays} jours`
                : `Maximum stay is ${maxDays} days`;
            return result;
        }
        
        result.isValid = true;
        return result;
    }
    
    /**
     * T_JS_Validation: Photo
     * Règle: JPG/PNG, max 2MB
     */
    static validatePhoto(file) {
        const result = {
            isValid: false,
            file: file,
            error: null
        };
        
        if (!file) {
            result.error = this.getMessage('required');
            return result;
        }
        
        const validTypes = ['image/jpeg', 'image/png'];
        const maxSize = 2 * 1024 * 1024; // 2MB
        
        if (!validTypes.includes(file.type)) {
            result.error = this.getMessage('photo');
            return result;
        }
        
        if (file.size > maxSize) {
            result.error = this.lang === 'fr'
                ? `Fichier trop volumineux (max 2 Mo, actuel: ${(file.size / 1024 / 1024).toFixed(1)} Mo)`
                : `File too large (max 2 MB, current: ${(file.size / 1024 / 1024).toFixed(1)} MB)`;
            return result;
        }
        
        result.isValid = true;
        return result;
    }
    
    /**
     * T_JS_Validation: Signature
     * Règle: Non vide
     */
    static validateSignature(signatureData) {
        const result = {
            isValid: false,
            value: signatureData,
            error: null
        };
        
        if (!signatureData || signatureData.trim() === '') {
            result.error = this.getMessage('signature');
            return result;
        }
        
        // Vérifier si c'est une image base64 valide
        if (signatureData.startsWith('data:image/')) {
            result.isValid = true;
            return result;
        }
        
        // Si c'est du texte simple
        if (signatureData.length >= 2) {
            result.isValid = true;
            return result;
        }
        
        result.error = this.getMessage('signature');
        return result;
    }
    
    /**
     * T_JS_Validation: CertificatVaccination
     * Règle: PDF requis
     */
    static validateVaccinationCertificate(file) {
        const result = {
            isValid: false,
            file: file,
            error: null
        };
        
        if (!file) {
            result.error = this.getMessage('required');
            return result;
        }
        
        // Accepter PDF, JPG, PNG
        const validTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!validTypes.includes(file.type)) {
            result.error = this.getMessage('vaccination');
            return result;
        }
        
        if (file.size > maxSize) {
            result.error = this.lang === 'fr'
                ? `Fichier trop volumineux (max 5 Mo)`
                : `File too large (max 5 MB)`;
            return result;
        }
        
        result.isValid = true;
        return result;
    }
    
    /**
     * T_JS_Validation: Engagement
     * Règle: Doit être coché
     */
    static validateEngagement(checked) {
        const result = {
            isValid: false,
            value: checked,
            error: null
        };
        
        if (!checked) {
            result.error = this.getMessage('engagement');
            return result;
        }
        
        result.isValid = true;
        return result;
    }
    
    // ========================================
    // MÉTHODES UTILITAIRES
    // ========================================
    
    /**
     * Valide une date de naissance
     */
    static validateBirthDate(birthDate) {
        const result = {
            isValid: false,
            value: birthDate,
            age: null,
            error: null,
            isMinor: false
        };
        
        if (!birthDate) {
            result.error = this.getMessage('required');
            return result;
        }
        
        const birth = new Date(birthDate);
        const now = new Date();
        
        if (birth >= now) {
            result.error = this.lang === 'fr' 
                ? 'La date de naissance doit être dans le passé'
                : 'Birth date must be in the past';
            return result;
        }
        
        // Calculer l'âge
        let age = now.getFullYear() - birth.getFullYear();
        const m = now.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && now.getDate() < birth.getDate())) {
            age--;
        }
        
        result.age = age;
        
        if (age < 0 || age > 120) {
            result.error = this.lang === 'fr' 
                ? 'Date de naissance invalide'
                : 'Invalid birth date';
            return result;
        }
        
        result.isMinor = age < 18;
        result.isValid = true;
        return result;
    }
    
    /**
     * Formate un numéro de téléphone
     */
    static formatPhone(phone) {
        for (const [, info] of Object.entries(this.phonePrefixes)) {
            if (phone.startsWith(info.code)) {
                const rest = phone.substring(info.code.length);
                return info.code + ' ' + rest.match(/.{1,3}/g)?.join(' ');
            }
        }
        return phone;
    }
    
    /**
     * Formate une date pour affichage
     */
    static formatDate(date, locale = 'fr-FR') {
        if (typeof date === 'string') {
            date = new Date(date);
        }
        return date.toLocaleDateString(locale, {
            day: '2-digit',
            month: 'long',
            year: 'numeric'
        });
    }
    
    /**
     * Valide tous les champs d'un formulaire
     */
    static validateForm(formData, rules) {
        const errors = {};
        let isValid = true;
        
        for (const [field, rule] of Object.entries(rules)) {
            const value = formData[field];
            let result;
            
            switch (rule.type) {
                case 'email':
                    result = this.validateEmail(value);
                    break;
                case 'phone':
                    result = this.validatePhone(value, formData.countryCode);
                    break;
                case 'passport':
                    result = this.validatePassportNumber(value);
                    break;
                case 'passportExpiry':
                    result = this.validatePassportExpiry(value, formData.departureDate);
                    break;
                case 'photo':
                    result = this.validatePhoto(value);
                    break;
                case 'signature':
                    result = this.validateSignature(value);
                    break;
                case 'vaccination':
                    result = this.validateVaccinationCertificate(value);
                    break;
                case 'engagement':
                    result = this.validateEngagement(value);
                    break;
                default:
                    result = { isValid: !!value, error: !value ? this.getMessage('required') : null };
            }
            
            if (!result.isValid) {
                errors[field] = result.error;
                isValid = false;
            }
        }
        
        return { isValid, errors };
    }
    
    /**
     * Ajoute la validation en temps réel à un input
     */
    static attachRealTimeValidation(inputElement, validationType, options = {}) {
        const validate = () => {
            let result;
            const value = inputElement.type === 'file' 
                ? inputElement.files?.[0] 
                : inputElement.type === 'checkbox'
                    ? inputElement.checked
                    : inputElement.value;
            
            switch (validationType) {
                case 'email':
                    result = this.validateEmail(value);
                    break;
                case 'phone':
                    result = this.validatePhone(value, options.countryCode);
                    break;
                case 'passport':
                    result = this.validatePassportNumber(value);
                    break;
                case 'passportExpiry':
                    result = this.validatePassportExpiry(value, options.travelDate);
                    break;
                case 'photo':
                    result = this.validatePhoto(value);
                    break;
                case 'vaccination':
                    result = this.validateVaccinationCertificate(value);
                    break;
                case 'engagement':
                    result = this.validateEngagement(value);
                    break;
                default:
                    result = { isValid: !!value };
            }
            
            // Mettre à jour l'UI
            this.updateInputUI(inputElement, result);
            
            return result;
        };
        
        inputElement.addEventListener('blur', validate);
        inputElement.addEventListener('change', validate);
        
        return validate;
    }
    
    /**
     * Met à jour l'UI d'un input selon le résultat de validation
     */
    static updateInputUI(inputElement, result) {
        const parent = inputElement.closest('.form-field') || inputElement.parentNode;
        const existingError = parent?.querySelector('.validation-error');
        
        // Supprimer l'ancienne erreur
        existingError?.remove();
        
        // Supprimer les classes
        inputElement.classList.remove('input-valid', 'input-invalid');
        
        if (result.isValid) {
            inputElement.classList.add('input-valid');
        } else if (result.error) {
            inputElement.classList.add('input-invalid');
            
            // Ajouter le message d'erreur
            const errorDiv = document.createElement('div');
            errorDiv.className = 'validation-error';
            errorDiv.textContent = result.error;
            parent?.appendChild(errorDiv);
        }
    }
}

// Exposer globalement
window.Validators = Validators;

