/**
 * Passport Scanner - Intégration OCR dans le chatbot
 * Utilise le module OCR existant
 * 
 * @version 1.0.0
 */

class PassportScanner {
    /**
     * Constructeur
     * @param {Object} options - Options de configuration
     */
    constructor(options = {}) {
        this.config = {
            ocrEndpoint: options.ocrEndpoint || '../passport-ocr-module/php/api-handler.php',
            maxFileSize: options.maxFileSize || 50 * 1024 * 1024, // 50MB
            allowedTypes: options.allowedTypes || ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
            onScanComplete: options.onScanComplete || null,
            onError: options.onError || null,
            debug: options.debug || false
        };
        
        this.state = {
            file: null,
            isScanning: false
        };
    }
    
    /**
     * Valide un fichier avant le scan
     */
    validateFile(file) {
        if (!file) {
            return { valid: false, error: 'Aucun fichier sélectionné' };
        }
        
        if (!this.config.allowedTypes.includes(file.type)) {
            return { 
                valid: false, 
                error: 'Format non supporté. Utilisez JPEG, PNG, WebP ou PDF.' 
            };
        }
        
        if (file.size > this.config.maxFileSize) {
            return { 
                valid: false, 
                error: `Fichier trop volumineux (max ${this.formatFileSize(this.config.maxFileSize)})` 
            };
        }
        
        return { valid: true };
    }
    
    /**
     * Scanne un fichier passeport
     */
    async scan(file) {
        // Validation
        const validation = this.validateFile(file);
        if (!validation.valid) {
            this.onError(validation.error);
            return null;
        }
        
        this.state.file = file;
        this.state.isScanning = true;
        
        try {
            // Convertir en base64
            const base64 = await this.fileToBase64(file);
            
            // Appeler l'API OCR
            const response = await fetch(this.config.ocrEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    image: base64.split(',')[1], // Retirer le préfixe data:
                    mime_type: file.type,
                    action: 'extract_passport'
                })
            });
            
            if (!response.ok) {
                throw new Error(`Erreur serveur: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Extraction échouée');
            }
            
            this.log('Scan réussi:', data);
            
            // Normaliser les données
            const normalizedData = this.normalizeOCRData(data.extracted_data);
            
            // Callback de succès
            if (this.config.onScanComplete) {
                this.config.onScanComplete(normalizedData);
            }
            
            return normalizedData;
            
        } catch (error) {
            this.log('Erreur scan:', error);
            this.onError(error.message || 'Erreur lors du scan');
            return null;
        } finally {
            this.state.isScanning = false;
        }
    }
    
    /**
     * Normalise les données OCR pour le chatbot
     */
    normalizeOCRData(data) {
        const fields = data.fields || {};
        
        return {
            fields: {
                surname: this.extractField(fields, 'surname'),
                given_names: this.extractField(fields, 'given_names'),
                date_of_birth: this.extractField(fields, 'date_of_birth'),
                place_of_birth: this.extractField(fields, 'place_of_birth'),
                sex: this.extractField(fields, 'sex'),
                nationality: this.extractField(fields, 'nationality'),
                passport_number: this.extractField(fields, 'passport_number'),
                date_of_issue: this.extractField(fields, 'date_of_issue'),
                date_of_expiry: this.extractField(fields, 'date_of_expiry'),
                issuing_authority: this.extractField(fields, 'issuing_authority'),
                place_of_issue: this.extractField(fields, 'place_of_issue')
            },
            mrz: data.mrz || { line1: null, line2: null, detected: false },
            quality: data.quality_assessment || { readability: 0.5 },
            metadata: data._metadata || {}
        };
    }
    
    /**
     * Extrait un champ avec valeur et confiance
     */
    extractField(fields, fieldName) {
        const field = fields[fieldName];
        if (!field) {
            return { value: null, confidence: 0 };
        }
        return {
            value: field.value || null,
            confidence: field.confidence || 0
        };
    }
    
    /**
     * Détecte le type de passeport depuis les données OCR
     */
    detectPassportType(ocrData) {
        const mrz = ocrData.mrz;
        
        if (mrz && mrz.line1) {
            const firstChar = mrz.line1.charAt(0).toUpperCase();
            
            switch (firstChar) {
                case 'D': return 'DIPLOMATIQUE';
                case 'S': return 'SERVICE';
                case 'L': return 'LAISSEZ_PASSER';
                default: return 'ORDINAIRE';
            }
        }
        
        return 'ORDINAIRE';
    }
    
    /**
     * Vérifie la validité du passeport
     */
    checkPassportValidity(ocrData) {
        const expiryDate = ocrData.fields?.date_of_expiry?.value;
        
        if (!expiryDate) {
            return { valid: true, warning: 'Date d\'expiration non détectée' };
        }
        
        const expiry = new Date(expiryDate);
        const today = new Date();
        const sixMonthsFromNow = new Date();
        sixMonthsFromNow.setMonth(sixMonthsFromNow.getMonth() + 6);
        
        if (expiry < today) {
            return { valid: false, error: 'Passeport expiré' };
        }
        
        if (expiry < sixMonthsFromNow) {
            return { 
                valid: true, 
                warning: 'Passeport expire dans moins de 6 mois' 
            };
        }
        
        return { valid: true };
    }
    
    /**
     * Convertit un fichier en base64
     */
    fileToBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = () => reject(new Error('Erreur de lecture du fichier'));
            reader.readAsDataURL(file);
        });
    }
    
    /**
     * Formate la taille d'un fichier
     */
    formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' o';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' Ko';
        return (bytes / (1024 * 1024)).toFixed(1) + ' Mo';
    }
    
    /**
     * Gère les erreurs
     */
    onError(message) {
        if (this.config.onError) {
            this.config.onError(message);
        } else {
            console.error('[PassportScanner]', message);
        }
    }
    
    /**
     * Log conditionnel
     */
    log(...args) {
        if (this.config.debug) {
            console.log('[PassportScanner]', ...args);
        }
    }
    
    /**
     * Retourne l'état actuel
     */
    isScanning() {
        return this.state.isScanning;
    }
    
    /**
     * Réinitialise le scanner
     */
    reset() {
        this.state.file = null;
        this.state.isScanning = false;
    }
}

// Export global
window.PassportScanner = PassportScanner;

