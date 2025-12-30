/**
 * Health Declaration Module
 * Handles health questionnaire and vaccination verification
 *
 * @module health-declaration
 * @version 1.0.0
 *
 * Features:
 * - Symptom questionnaire (last 30 days)
 * - Recent travel history
 * - Vaccination records (Yellow Fever mandatory for CI)
 * - Health alerts and restrictions
 */

// =============================================================================
// CONSTANTS
// =============================================================================

/**
 * Symptom types to check
 */
export const SymptomType = {
    FEVER: 'fever',
    COUGH: 'cough',
    BREATHING_DIFFICULTY: 'breathing_difficulty',
    FATIGUE: 'fatigue',
    BODY_ACHES: 'body_aches',
    HEADACHE: 'headache',
    LOSS_OF_TASTE_SMELL: 'loss_of_taste_smell',
    SORE_THROAT: 'sore_throat',
    DIARRHEA: 'diarrhea',
    SKIN_RASH: 'skin_rash',
    OTHER: 'other'
};

/**
 * Symptom configurations (bilingual)
 */
const SYMPTOMS = {
    [SymptomType.FEVER]: {
        label: { fr: 'Fièvre (> 38°C)', en: 'Fever (> 38°C / 100.4°F)' },
        severity: 'high'
    },
    [SymptomType.COUGH]: {
        label: { fr: 'Toux persistante', en: 'Persistent cough' },
        severity: 'medium'
    },
    [SymptomType.BREATHING_DIFFICULTY]: {
        label: { fr: 'Difficultés respiratoires', en: 'Breathing difficulties' },
        severity: 'high'
    },
    [SymptomType.FATIGUE]: {
        label: { fr: 'Fatigue intense', en: 'Severe fatigue' },
        severity: 'low'
    },
    [SymptomType.BODY_ACHES]: {
        label: { fr: 'Douleurs musculaires', en: 'Body aches' },
        severity: 'low'
    },
    [SymptomType.HEADACHE]: {
        label: { fr: 'Maux de tête intenses', en: 'Severe headache' },
        severity: 'medium'
    },
    [SymptomType.LOSS_OF_TASTE_SMELL]: {
        label: { fr: 'Perte de goût/odorat', en: 'Loss of taste/smell' },
        severity: 'medium'
    },
    [SymptomType.SORE_THROAT]: {
        label: { fr: 'Mal de gorge', en: 'Sore throat' },
        severity: 'low'
    },
    [SymptomType.DIARRHEA]: {
        label: { fr: 'Diarrhée', en: 'Diarrhea' },
        severity: 'medium'
    },
    [SymptomType.SKIN_RASH]: {
        label: { fr: 'Éruption cutanée', en: 'Skin rash' },
        severity: 'medium'
    },
    [SymptomType.OTHER]: {
        label: { fr: 'Autres symptômes', en: 'Other symptoms' },
        severity: 'low'
    }
};

/**
 * Vaccination types
 */
export const VaccinationType = {
    YELLOW_FEVER: 'yellow_fever',
    COVID_19: 'covid_19',
    POLIO: 'polio',
    HEPATITIS_A: 'hepatitis_a',
    HEPATITIS_B: 'hepatitis_b',
    TYPHOID: 'typhoid',
    MENINGITIS: 'meningitis',
    CHOLERA: 'cholera'
};

/**
 * Vaccination configurations
 */
const VACCINATIONS = {
    [VaccinationType.YELLOW_FEVER]: {
        label: { fr: 'Fièvre jaune', en: 'Yellow Fever' },
        required: true,
        validityDays: null, // Lifetime
        effectiveDays: 10, // Effective 10 days after vaccination
        certificateRequired: true,
        destinationRequired: true // Required for entry to CI
    },
    [VaccinationType.COVID_19]: {
        label: { fr: 'COVID-19', en: 'COVID-19' },
        required: false, // Check current regulations
        validityDays: 365,
        effectiveDays: 14,
        certificateRequired: false
    },
    [VaccinationType.POLIO]: {
        label: { fr: 'Polio', en: 'Polio' },
        required: false,
        recommendedFor: ['children']
    },
    [VaccinationType.HEPATITIS_A]: {
        label: { fr: 'Hépatite A', en: 'Hepatitis A' },
        required: false,
        recommended: true
    },
    [VaccinationType.HEPATITIS_B]: {
        label: { fr: 'Hépatite B', en: 'Hepatitis B' },
        required: false,
        recommended: true
    },
    [VaccinationType.TYPHOID]: {
        label: { fr: 'Typhoïde', en: 'Typhoid' },
        required: false,
        recommended: true
    },
    [VaccinationType.MENINGITIS]: {
        label: { fr: 'Méningite', en: 'Meningitis' },
        required: false,
        recommended: true
    },
    [VaccinationType.CHOLERA]: {
        label: { fr: 'Choléra', en: 'Cholera' },
        required: false,
        recommended: false
    }
};

/**
 * High-risk countries for health monitoring
 */
const HIGH_RISK_COUNTRIES = [
    'BRAZIL', 'BRESIL',
    'DEMOCRATIC REPUBLIC OF CONGO', 'RDC', 'DRC',
    'NIGERIA',
    'ANGOLA',
    'SUDAN', 'SOUDAN',
    'SOUTH SUDAN', 'SOUDAN DU SUD'
];

/**
 * Health declaration questions
 */
const HEALTH_QUESTIONS = {
    symptoms: {
        question: {
            fr: 'Avez-vous eu l\'un des symptômes suivants au cours des 30 derniers jours ?',
            en: 'Have you experienced any of the following symptoms in the last 30 days?'
        },
        type: 'multi_select'
    },
    recentTravel: {
        question: {
            fr: 'Avez-vous voyagé dans un autre pays au cours des 30 derniers jours ?',
            en: 'Have you traveled to another country in the last 30 days?'
        },
        type: 'yes_no',
        followUp: {
            question: {
                fr: 'Quels pays avez-vous visités ?',
                en: 'Which countries did you visit?'
            },
            type: 'country_list'
        }
    },
    yellowFever: {
        question: {
            fr: 'Êtes-vous vacciné contre la fièvre jaune ?',
            en: 'Are you vaccinated against Yellow Fever?'
        },
        type: 'yes_no',
        required: true,
        uploadRequired: true
    },
    medication: {
        question: {
            fr: 'Transportez-vous des médicaments spéciaux nécessitant une déclaration ?',
            en: 'Are you carrying special medications that require declaration?'
        },
        type: 'yes_no',
        examples: {
            fr: '(Insuline, médicaments contrôlés, seringues...)',
            en: '(Insulin, controlled substances, syringes...)'
        }
    },
    chronicCondition: {
        question: {
            fr: 'Avez-vous une condition médicale chronique ?',
            en: 'Do you have any chronic medical conditions?'
        },
        type: 'yes_no',
        optional: true
    }
};

// =============================================================================
// HEALTH DECLARATION CLASS
// =============================================================================

export class HealthDeclaration {
    constructor(options = {}) {
        this.language = options.language || 'fr';
        this.responses = {};
        this.symptoms = [];
        this.recentCountries = [];
        this.vaccinations = {};
        this.medications = [];
        this.travelDate = options.travelDate || null;
        this.onChange = options.onChange || (() => {});
    }

    /**
     * Get all health questions
     */
    getQuestions() {
        return Object.entries(HEALTH_QUESTIONS).map(([key, config]) => ({
            id: key,
            question: this.t(config.question),
            type: config.type,
            required: config.required || false,
            examples: config.examples ? this.t(config.examples) : null,
            followUp: config.followUp ? {
                question: this.t(config.followUp.question),
                type: config.followUp.type
            } : null
        }));
    }

    /**
     * Get symptom list for selection
     */
    getSymptomOptions() {
        return Object.entries(SYMPTOMS).map(([id, config]) => ({
            id,
            label: this.t(config.label),
            severity: config.severity
        }));
    }

    /**
     * Record symptoms
     */
    setSymptoms(symptoms) {
        this.symptoms = symptoms;
        this.responses.symptoms = symptoms;
        this.onChange('symptoms', symptoms);
    }

    /**
     * Record recent travel countries
     */
    setRecentCountries(countries) {
        this.recentCountries = countries;
        this.responses.recentTravel = countries.length > 0;
        this.responses.recentCountries = countries;
        this.onChange('recentCountries', countries);
    }

    /**
     * Check if traveled to high-risk country
     */
    hasHighRiskTravel() {
        return this.recentCountries.some(country =>
            HIGH_RISK_COUNTRIES.some(risk =>
                country.toUpperCase().includes(risk)
            )
        );
    }

    /**
     * Record vaccination
     */
    addVaccination(type, data) {
        this.vaccinations[type] = {
            type,
            ...data,
            addedAt: new Date().toISOString()
        };
        this.onChange('vaccination', { type, data });
    }

    /**
     * Check if Yellow Fever vaccination is valid
     */
    isYellowFeverValid() {
        const yf = this.vaccinations[VaccinationType.YELLOW_FEVER];
        if (!yf || !yf.vaccinationDate) return false;

        const vacDate = new Date(yf.vaccinationDate);
        const effectiveDate = new Date(vacDate);
        effectiveDate.setDate(effectiveDate.getDate() + 10); // Effective 10 days after

        const today = new Date();

        // Check if effective (10 days after vaccination)
        if (today < effectiveDate) {
            return {
                valid: false,
                reason: this.t({
                    fr: 'Le vaccin sera effectif dans ' + Math.ceil((effectiveDate - today) / (1000 * 60 * 60 * 24)) + ' jours',
                    en: 'Vaccine will be effective in ' + Math.ceil((effectiveDate - today) / (1000 * 60 * 60 * 24)) + ' days'
                })
            };
        }

        // Yellow fever is lifetime valid
        return { valid: true };
    }

    /**
     * Record medications
     */
    setMedications(medications) {
        this.medications = medications;
        this.responses.medication = medications.length > 0;
        this.responses.medications = medications;
        this.onChange('medications', medications);
    }

    /**
     * Set response for a question
     */
    setResponse(questionId, value) {
        this.responses[questionId] = value;
        this.onChange('response', { questionId, value });
    }

    /**
     * Calculate health risk score (0-100)
     */
    calculateRiskScore() {
        let score = 0;
        let maxScore = 0;

        // Symptoms (max 40 points)
        const symptomPoints = {
            high: 10,
            medium: 5,
            low: 2
        };

        for (const symptomId of this.symptoms) {
            const symptom = SYMPTOMS[symptomId];
            if (symptom) {
                score += symptomPoints[symptom.severity] || 2;
            }
        }
        maxScore += 40;

        // High-risk travel (max 20 points)
        if (this.hasHighRiskTravel()) {
            score += 20;
        }
        maxScore += 20;

        // Yellow fever not valid (max 30 points)
        const yfValid = this.isYellowFeverValid();
        if (!yfValid || !yfValid.valid) {
            score += 30;
        }
        maxScore += 30;

        // Medications (max 10 points)
        if (this.medications.length > 0) {
            score += 10;
        }
        maxScore += 10;

        return Math.min(100, Math.round((score / maxScore) * 100));
    }

    /**
     * Get health risk level
     */
    getRiskLevel() {
        const score = this.calculateRiskScore();

        if (score >= 50) return { level: 'high', label: this.t({ fr: 'Élevé', en: 'High' }) };
        if (score >= 25) return { level: 'medium', label: this.t({ fr: 'Moyen', en: 'Medium' }) };
        return { level: 'low', label: this.t({ fr: 'Faible', en: 'Low' }) };
    }

    /**
     * Validate the health declaration
     */
    validate() {
        const issues = [];
        const warnings = [];

        // Check Yellow Fever vaccination (required for CI)
        const yfStatus = this.isYellowFeverValid();
        if (!yfStatus) {
            issues.push({
                type: 'MISSING_YELLOW_FEVER',
                message: this.t({
                    fr: 'Le certificat de vaccination contre la fièvre jaune est obligatoire pour l\'entrée en Côte d\'Ivoire.',
                    en: 'Yellow Fever vaccination certificate is mandatory for entry to Côte d\'Ivoire.'
                })
            });
        } else if (!yfStatus.valid) {
            issues.push({
                type: 'INVALID_YELLOW_FEVER',
                message: yfStatus.reason
            });
        }

        // Check for high-severity symptoms
        const highSeveritySymptoms = this.symptoms.filter(s =>
            SYMPTOMS[s]?.severity === 'high'
        );

        if (highSeveritySymptoms.length > 0) {
            warnings.push({
                type: 'HIGH_SEVERITY_SYMPTOMS',
                message: this.t({
                    fr: 'Vous avez signalé des symptômes sévères. Il est recommandé de consulter un médecin avant de voyager.',
                    en: 'You reported severe symptoms. It is recommended to consult a doctor before traveling.'
                }),
                symptoms: highSeveritySymptoms.map(s => this.t(SYMPTOMS[s].label))
            });
        }

        // Check high-risk travel
        if (this.hasHighRiskTravel()) {
            warnings.push({
                type: 'HIGH_RISK_TRAVEL',
                message: this.t({
                    fr: 'Vous avez récemment visité un pays à risque sanitaire. Des mesures supplémentaires peuvent être requises.',
                    en: 'You recently visited a high-risk country. Additional measures may be required.'
                }),
                countries: this.recentCountries.filter(c =>
                    HIGH_RISK_COUNTRIES.some(r => c.toUpperCase().includes(r))
                )
            });
        }

        // Check if travel date allows vaccine effectiveness
        if (this.travelDate && this.vaccinations[VaccinationType.YELLOW_FEVER]) {
            const travelDateObj = new Date(this.travelDate);
            const vacDate = new Date(this.vaccinations[VaccinationType.YELLOW_FEVER].vaccinationDate);
            const effectiveDate = new Date(vacDate);
            effectiveDate.setDate(effectiveDate.getDate() + 10);

            if (travelDateObj < effectiveDate) {
                warnings.push({
                    type: 'VACCINE_NOT_EFFECTIVE_BY_TRAVEL',
                    message: this.t({
                        fr: 'Le vaccin contre la fièvre jaune ne sera pas effectif à la date de votre voyage.',
                        en: 'Yellow Fever vaccine will not be effective by your travel date.'
                    })
                });
            }
        }

        return {
            valid: issues.length === 0,
            issues,
            warnings,
            riskLevel: this.getRiskLevel(),
            riskScore: this.calculateRiskScore()
        };
    }

    /**
     * Get required vaccination info
     */
    getRequiredVaccinations() {
        return Object.entries(VACCINATIONS)
            .filter(([_, config]) => config.required)
            .map(([id, config]) => ({
                id,
                label: this.t(config.label),
                certificateRequired: config.certificateRequired,
                effectiveDays: config.effectiveDays,
                status: this.vaccinations[id] ? 'provided' : 'missing'
            }));
    }

    /**
     * Get recommended vaccinations
     */
    getRecommendedVaccinations() {
        return Object.entries(VACCINATIONS)
            .filter(([_, config]) => config.recommended && !config.required)
            .map(([id, config]) => ({
                id,
                label: this.t(config.label),
                status: this.vaccinations[id] ? 'provided' : 'not_provided'
            }));
    }

    /**
     * Export declaration data
     */
    exportDeclaration() {
        return {
            symptoms: this.symptoms.map(s => ({
                id: s,
                label: this.t(SYMPTOMS[s]?.label || { fr: s, en: s })
            })),
            hasSymptoms: this.symptoms.length > 0,
            recentTravel: this.recentCountries.length > 0,
            recentCountries: this.recentCountries,
            hasHighRiskTravel: this.hasHighRiskTravel(),
            vaccinations: Object.entries(this.vaccinations).map(([type, data]) => ({
                type,
                label: this.t(VACCINATIONS[type]?.label || { fr: type, en: type }),
                ...data
            })),
            yellowFeverValid: this.isYellowFeverValid(),
            medications: this.medications,
            hasMedications: this.medications.length > 0,
            riskScore: this.calculateRiskScore(),
            riskLevel: this.getRiskLevel(),
            declarationDate: new Date().toISOString(),
            responses: this.responses
        };
    }

    /**
     * Get summary for display
     */
    getSummary() {
        const validation = this.validate();

        return {
            symptomsReported: this.symptoms.length,
            countriesVisited: this.recentCountries.length,
            hasHighRiskTravel: this.hasHighRiskTravel(),
            yellowFeverStatus: this.vaccinations[VaccinationType.YELLOW_FEVER]
                ? (this.isYellowFeverValid().valid ? 'valid' : 'invalid')
                : 'missing',
            medicationsCount: this.medications.length,
            riskLevel: validation.riskLevel,
            issuesCount: validation.issues.length,
            warningsCount: validation.warnings.length,
            isComplete: this.isComplete()
        };
    }

    /**
     * Check if declaration is complete
     */
    isComplete() {
        // Must have answered symptoms question
        if (this.responses.symptoms === undefined) return false;

        // Must have answered recent travel question
        if (this.responses.recentTravel === undefined) return false;

        // Must have Yellow Fever vaccination or explicit decline
        if (!this.vaccinations[VaccinationType.YELLOW_FEVER] &&
            this.responses.yellowFever !== false) return false;

        return true;
    }

    /**
     * Translation helper
     */
    t(obj) {
        if (typeof obj === 'string') return obj;
        return obj[this.language] || obj.fr || obj.en || '';
    }

    /**
     * Set language
     */
    setLanguage(lang) {
        this.language = lang === 'en' ? 'en' : 'fr';
    }

    /**
     * Reset declaration
     */
    reset() {
        this.responses = {};
        this.symptoms = [];
        this.recentCountries = [];
        this.vaccinations = {};
        this.medications = [];
    }

    /**
     * Import state
     */
    importState(state) {
        if (state.responses) this.responses = state.responses;
        if (state.symptoms) this.symptoms = state.symptoms;
        if (state.recentCountries) this.recentCountries = state.recentCountries;
        if (state.vaccinations) this.vaccinations = state.vaccinations;
        if (state.medications) this.medications = state.medications;
    }

    /**
     * Export state
     */
    exportState() {
        return {
            responses: this.responses,
            symptoms: this.symptoms,
            recentCountries: this.recentCountries,
            vaccinations: this.vaccinations,
            medications: this.medications
        };
    }
}

// =============================================================================
// SINGLETON EXPORT
// =============================================================================

export const healthDeclaration = new HealthDeclaration();

export default healthDeclaration;
