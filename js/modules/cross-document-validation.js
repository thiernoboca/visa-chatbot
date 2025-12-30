/**
 * Cross-Document Validation Module
 * Validates consistency between documents (names, dates, etc.)
 *
 * @version 1.0.0
 * @module CrossDocumentValidation
 */

/**
 * Validation result types
 */
export const ValidationResultType = {
    PASS: 'pass',
    WARNING: 'warning',
    FAIL: 'fail'
};

/**
 * Validation error codes
 */
export const ValidationErrorCode = {
    NAME_MISMATCH: 'name_mismatch',
    DATE_INCONSISTENCY: 'date_inconsistency',
    PASSPORT_EXPIRED: 'passport_expired',
    PASSPORT_EXPIRY_TOO_SOON: 'passport_expiry_too_soon',
    VISA_DURATION_EXCEEDED: 'visa_duration_exceeded',
    DOCUMENT_MISSING: 'document_missing',
    VACCINATION_INVALID: 'vaccination_invalid',
    TRAVEL_DATES_INVALID: 'travel_dates_invalid',
    HOTEL_DATES_MISMATCH: 'hotel_dates_mismatch',
    MINOR_WITHOUT_AUTH: 'minor_without_auth',
    FACE_MISMATCH: 'face_mismatch'
};

/**
 * Levenshtein distance calculation for name comparison
 * @param {string} str1
 * @param {string} str2
 * @returns {number}
 */
function levenshteinDistance(str1, str2) {
    const s1 = str1.toLowerCase().trim();
    const s2 = str2.toLowerCase().trim();

    if (s1 === s2) return 0;
    if (s1.length === 0) return s2.length;
    if (s2.length === 0) return s1.length;

    const matrix = [];
    for (let i = 0; i <= s2.length; i++) {
        matrix[i] = [i];
    }
    for (let j = 0; j <= s1.length; j++) {
        matrix[0][j] = j;
    }

    for (let i = 1; i <= s2.length; i++) {
        for (let j = 1; j <= s1.length; j++) {
            if (s2.charAt(i - 1) === s1.charAt(j - 1)) {
                matrix[i][j] = matrix[i - 1][j - 1];
            } else {
                matrix[i][j] = Math.min(
                    matrix[i - 1][j - 1] + 1,
                    matrix[i][j - 1] + 1,
                    matrix[i - 1][j] + 1
                );
            }
        }
    }

    return matrix[s2.length][s1.length];
}

/**
 * Normalize name for comparison
 * @param {string} name
 * @returns {string}
 */
function normalizeName(name) {
    if (!name) return '';
    return name
        .toUpperCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '') // Remove accents
        .replace(/[^A-Z\s]/g, '')        // Keep only letters and spaces
        .replace(/\s+/g, ' ')            // Normalize spaces
        .trim();
}

/**
 * Compare two names with tolerance
 * @param {string} name1
 * @param {string} name2
 * @param {number} maxDistance - Maximum Levenshtein distance allowed
 * @returns {Object}
 */
function compareNames(name1, name2, maxDistance = 2) {
    const n1 = normalizeName(name1);
    const n2 = normalizeName(name2);

    if (n1 === n2) {
        return { match: true, distance: 0, confidence: 1 };
    }

    const distance = levenshteinDistance(n1, n2);
    const maxLen = Math.max(n1.length, n2.length);
    const confidence = maxLen > 0 ? 1 - (distance / maxLen) : 0;

    return {
        match: distance <= maxDistance,
        distance,
        confidence,
        normalized: { name1: n1, name2: n2 }
    };
}

/**
 * Parse date string to Date object
 * @param {string|Date} dateInput
 * @returns {Date|null}
 */
function parseDate(dateInput) {
    if (!dateInput) return null;
    if (dateInput instanceof Date) return dateInput;

    // Try different date formats
    const formats = [
        /^(\d{4})-(\d{2})-(\d{2})$/,          // YYYY-MM-DD
        /^(\d{2})\/(\d{2})\/(\d{4})$/,        // DD/MM/YYYY
        /^(\d{2})-(\d{2})-(\d{4})$/,          // DD-MM-YYYY
    ];

    for (const format of formats) {
        const match = dateInput.match(format);
        if (match) {
            if (format === formats[0]) {
                return new Date(match[1], match[2] - 1, match[3]);
            } else {
                return new Date(match[3], match[2] - 1, match[1]);
            }
        }
    }

    // Fallback to Date.parse
    const parsed = new Date(dateInput);
    return isNaN(parsed.getTime()) ? null : parsed;
}

/**
 * Add days to a date
 * @param {Date} date
 * @param {number} days
 * @returns {Date}
 */
function addDays(date, days) {
    const result = new Date(date);
    result.setDate(result.getDate() + days);
    return result;
}

/**
 * Calculate days between two dates
 * @param {Date} date1
 * @param {Date} date2
 * @returns {number}
 */
function daysBetween(date1, date2) {
    const oneDay = 24 * 60 * 60 * 1000;
    return Math.round((date2 - date1) / oneDay);
}

/**
 * CrossDocumentValidator class
 */
export class CrossDocumentValidator {
    constructor() {
        this.results = [];
        this.passportData = null;
        this.documents = {};
    }

    /**
     * Set passport as reference document
     * @param {Object} data
     */
    setPassportData(data) {
        this.passportData = data;
    }

    /**
     * Add document data for validation
     * @param {string} docType
     * @param {Object} data
     */
    addDocument(docType, data) {
        this.documents[docType] = data;
    }

    /**
     * Clear all data
     */
    reset() {
        this.results = [];
        this.passportData = null;
        this.documents = {};
    }

    /**
     * Run all validations
     * @returns {Object} Validation report
     */
    validateAll() {
        this.results = [];

        // Run each validation
        this.validatePassportExpiry();
        this.validateNameConsistency();
        this.validateTravelDates();
        this.validateHotelDates();
        this.validateVaccination();
        this.validateVisaDuration();
        this.validateMinorDocuments();

        // Calculate overall score
        const passCount = this.results.filter(r => r.type === ValidationResultType.PASS).length;
        const warnCount = this.results.filter(r => r.type === ValidationResultType.WARNING).length;
        const failCount = this.results.filter(r => r.type === ValidationResultType.FAIL).length;

        const totalChecks = this.results.length || 1;
        const coherenceScore = ((passCount * 1) + (warnCount * 0.5)) / totalChecks;

        return {
            results: this.results,
            summary: {
                passed: passCount,
                warnings: warnCount,
                failed: failCount,
                total: this.results.length
            },
            coherenceScore,
            isValid: failCount === 0,
            hasWarnings: warnCount > 0
        };
    }

    /**
     * Validate passport expiry (must be valid 6 months after return)
     */
    validatePassportExpiry() {
        if (!this.passportData?.expiry_date) return;

        const expiryDate = parseDate(this.passportData.expiry_date);
        const today = new Date();
        const returnDate = this.documents.ticket?.departure_date
            ? parseDate(this.documents.ticket.departure_date)
            : addDays(today, 30); // Default 30 days trip

        if (!expiryDate) return;

        // Check if passport is already expired
        if (expiryDate < today) {
            this.results.push({
                type: ValidationResultType.FAIL,
                code: ValidationErrorCode.PASSPORT_EXPIRED,
                message: {
                    fr: 'Votre passeport est expiré',
                    en: 'Your passport has expired'
                },
                details: { expiryDate: this.passportData.expiry_date }
            });
            return;
        }

        // Check 6 months validity after return
        const requiredValidity = addDays(returnDate, 180);
        if (expiryDate < requiredValidity) {
            this.results.push({
                type: ValidationResultType.FAIL,
                code: ValidationErrorCode.PASSPORT_EXPIRY_TOO_SOON,
                message: {
                    fr: 'Votre passeport doit être valide au moins 6 mois après la date de retour',
                    en: 'Your passport must be valid at least 6 months after return date'
                },
                details: {
                    expiryDate: this.passportData.expiry_date,
                    requiredUntil: requiredValidity.toISOString().split('T')[0]
                }
            });
            return;
        }

        this.results.push({
            type: ValidationResultType.PASS,
            code: 'passport_expiry_valid',
            message: {
                fr: 'Validité du passeport conforme',
                en: 'Passport validity confirmed'
            }
        });
    }

    /**
     * Validate name consistency across documents
     */
    validateNameConsistency() {
        if (!this.passportData) return;

        const passportName = `${this.passportData.surname || ''} ${this.passportData.given_names || ''}`.trim();
        if (!passportName) return;

        const documentsToCheck = [
            { type: 'ticket', nameField: 'passenger_name', label: { fr: 'billet', en: 'ticket' } },
            { type: 'hotel', nameField: 'guest_name', label: { fr: 'réservation hôtel', en: 'hotel reservation' } },
            { type: 'vaccination', nameField: 'patient_name', label: { fr: 'certificat vaccination', en: 'vaccination certificate' } },
            { type: 'invitation', nameField: 'invitee_name', label: { fr: 'invitation', en: 'invitation' } }
        ];

        for (const doc of documentsToCheck) {
            const docData = this.documents[doc.type];
            if (!docData || !docData[doc.nameField]) continue;

            const comparison = compareNames(passportName, docData[doc.nameField]);

            if (!comparison.match) {
                this.results.push({
                    type: comparison.confidence > 0.7 ? ValidationResultType.WARNING : ValidationResultType.FAIL,
                    code: ValidationErrorCode.NAME_MISMATCH,
                    message: {
                        fr: `Le nom sur le ${doc.label.fr} diffère du passeport`,
                        en: `Name on ${doc.label.en} differs from passport`
                    },
                    details: {
                        passportName,
                        documentName: docData[doc.nameField],
                        distance: comparison.distance,
                        confidence: comparison.confidence
                    }
                });
            } else {
                this.results.push({
                    type: ValidationResultType.PASS,
                    code: `name_match_${doc.type}`,
                    message: {
                        fr: `Nom cohérent avec le ${doc.label.fr}`,
                        en: `Name consistent with ${doc.label.en}`
                    }
                });
            }
        }
    }

    /**
     * Validate travel dates
     */
    validateTravelDates() {
        const ticket = this.documents.ticket;
        if (!ticket?.arrival_date || !ticket?.departure_date) return;

        const arrival = parseDate(ticket.arrival_date);
        const departure = parseDate(ticket.departure_date);
        const today = new Date();

        if (!arrival || !departure) return;

        // Check arrival is in the future
        if (arrival < today) {
            this.results.push({
                type: ValidationResultType.FAIL,
                code: ValidationErrorCode.TRAVEL_DATES_INVALID,
                message: {
                    fr: 'La date d\'arrivée est dans le passé',
                    en: 'Arrival date is in the past'
                },
                details: { arrivalDate: ticket.arrival_date }
            });
            return;
        }

        // Check departure is after arrival
        if (departure < arrival) {
            this.results.push({
                type: ValidationResultType.FAIL,
                code: ValidationErrorCode.TRAVEL_DATES_INVALID,
                message: {
                    fr: 'La date de retour est avant la date d\'arrivée',
                    en: 'Return date is before arrival date'
                },
                details: { arrivalDate: ticket.arrival_date, departureDate: ticket.departure_date }
            });
            return;
        }

        this.results.push({
            type: ValidationResultType.PASS,
            code: 'travel_dates_valid',
            message: {
                fr: 'Dates de voyage valides',
                en: 'Travel dates valid'
            }
        });
    }

    /**
     * Validate hotel dates match travel dates
     */
    validateHotelDates() {
        const ticket = this.documents.ticket;
        const hotel = this.documents.hotel;

        if (!ticket?.arrival_date || !hotel?.check_in_date) return;

        const arrival = parseDate(ticket.arrival_date);
        const checkIn = parseDate(hotel.check_in_date);

        if (!arrival || !checkIn) return;

        const daysDiff = Math.abs(daysBetween(arrival, checkIn));

        if (daysDiff > 1) {
            this.results.push({
                type: ValidationResultType.WARNING,
                code: ValidationErrorCode.HOTEL_DATES_MISMATCH,
                message: {
                    fr: 'La date d\'arrivée à l\'hôtel ne correspond pas au billet d\'avion',
                    en: 'Hotel check-in date does not match flight arrival'
                },
                details: {
                    flightArrival: ticket.arrival_date,
                    hotelCheckIn: hotel.check_in_date,
                    daysDifference: daysDiff
                }
            });
        } else {
            this.results.push({
                type: ValidationResultType.PASS,
                code: 'hotel_dates_match',
                message: {
                    fr: 'Dates hôtel cohérentes avec le vol',
                    en: 'Hotel dates consistent with flight'
                }
            });
        }
    }

    /**
     * Validate vaccination certificate
     */
    validateVaccination() {
        const vaccination = this.documents.vaccination;
        const ticket = this.documents.ticket;

        if (!vaccination?.vaccination_date) return;

        const vaccDate = parseDate(vaccination.vaccination_date);
        const travelDate = ticket?.arrival_date ? parseDate(ticket.arrival_date) : new Date();

        if (!vaccDate || !travelDate) return;

        // Vaccination must be at least 10 days before travel
        const minVaccDate = addDays(travelDate, -10);

        if (vaccDate > minVaccDate) {
            this.results.push({
                type: ValidationResultType.FAIL,
                code: ValidationErrorCode.VACCINATION_INVALID,
                message: {
                    fr: 'La vaccination doit être effectuée au moins 10 jours avant le voyage',
                    en: 'Vaccination must be done at least 10 days before travel'
                },
                details: {
                    vaccinationDate: vaccination.vaccination_date,
                    travelDate: ticket?.arrival_date,
                    daysBeforeTravel: daysBetween(vaccDate, travelDate)
                }
            });
        } else {
            this.results.push({
                type: ValidationResultType.PASS,
                code: 'vaccination_valid',
                message: {
                    fr: 'Vaccination valide',
                    en: 'Vaccination valid'
                }
            });
        }
    }

    /**
     * Validate visa duration (max 90 days for tourist)
     */
    validateVisaDuration() {
        const ticket = this.documents.ticket;
        if (!ticket?.arrival_date || !ticket?.departure_date) return;

        const arrival = parseDate(ticket.arrival_date);
        const departure = parseDate(ticket.departure_date);

        if (!arrival || !departure) return;

        const stayDuration = daysBetween(arrival, departure);
        const maxDays = 90; // Tourist visa max

        if (stayDuration > maxDays) {
            this.results.push({
                type: ValidationResultType.FAIL,
                code: ValidationErrorCode.VISA_DURATION_EXCEEDED,
                message: {
                    fr: `La durée de séjour (${stayDuration} jours) dépasse le maximum autorisé (${maxDays} jours)`,
                    en: `Stay duration (${stayDuration} days) exceeds maximum allowed (${maxDays} days)`
                },
                details: { stayDuration, maxDays }
            });
        } else {
            this.results.push({
                type: ValidationResultType.PASS,
                code: 'visa_duration_valid',
                message: {
                    fr: `Durée de séjour conforme (${stayDuration} jours)`,
                    en: `Stay duration valid (${stayDuration} days)`
                }
            });
        }
    }

    /**
     * Validate minor documents if applicant is under 18
     */
    validateMinorDocuments() {
        if (!this.passportData?.date_of_birth) return;

        const dob = parseDate(this.passportData.date_of_birth);
        if (!dob) return;

        const today = new Date();
        const age = Math.floor((today - dob) / (365.25 * 24 * 60 * 60 * 1000));

        if (age < 18) {
            const hasParentalAuth = !!this.documents.parental_auth;
            const hasBirthCert = !!this.documents.birth_certificate;
            const hasParentId = !!this.documents.parent_id;

            if (!hasParentalAuth || !hasBirthCert || !hasParentId) {
                const missing = [];
                if (!hasParentalAuth) missing.push({ fr: 'autorisation parentale', en: 'parental authorization' });
                if (!hasBirthCert) missing.push({ fr: 'acte de naissance', en: 'birth certificate' });
                if (!hasParentId) missing.push({ fr: 'pièce d\'identité parent', en: 'parent ID' });

                this.results.push({
                    type: ValidationResultType.FAIL,
                    code: ValidationErrorCode.MINOR_WITHOUT_AUTH,
                    message: {
                        fr: `Mineur détecté (${age} ans) - documents manquants`,
                        en: `Minor detected (${age} years) - missing documents`
                    },
                    details: { age, missingDocuments: missing }
                });
            } else {
                this.results.push({
                    type: ValidationResultType.PASS,
                    code: 'minor_documents_complete',
                    message: {
                        fr: 'Documents pour mineur complets',
                        en: 'Minor documents complete'
                    }
                });
            }
        }
    }

    /**
     * Get human-readable validation report
     * @param {string} lang - 'fr' or 'en'
     * @returns {string}
     */
    getReadableReport(lang = 'fr') {
        const report = this.validateAll();
        let text = lang === 'fr'
            ? `## Rapport de Validation\n\n`
            : `## Validation Report\n\n`;

        text += lang === 'fr'
            ? `Score de cohérence: ${Math.round(report.coherenceScore * 100)}%\n\n`
            : `Coherence score: ${Math.round(report.coherenceScore * 100)}%\n\n`;

        if (report.summary.failed > 0) {
            text += lang === 'fr' ? `### ❌ Erreurs\n` : `### ❌ Errors\n`;
            for (const r of report.results.filter(x => x.type === ValidationResultType.FAIL)) {
                text += `- ${r.message[lang]}\n`;
            }
            text += '\n';
        }

        if (report.summary.warnings > 0) {
            text += lang === 'fr' ? `### ⚠️ Avertissements\n` : `### ⚠️ Warnings\n`;
            for (const r of report.results.filter(x => x.type === ValidationResultType.WARNING)) {
                text += `- ${r.message[lang]}\n`;
            }
            text += '\n';
        }

        if (report.summary.passed > 0) {
            text += lang === 'fr' ? `### ✅ Validés\n` : `### ✅ Passed\n`;
            for (const r of report.results.filter(x => x.type === ValidationResultType.PASS)) {
                text += `- ${r.message[lang]}\n`;
            }
        }

        return text;
    }
}

// Export singleton instance
export const crossDocumentValidator = new CrossDocumentValidator();

export default {
    CrossDocumentValidator,
    crossDocumentValidator,
    ValidationResultType,
    ValidationErrorCode,
    compareNames,
    levenshteinDistance
};
