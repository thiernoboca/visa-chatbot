/**
 * Accompanist Manager
 * Handles traveling companions and minors management
 *
 * @module accompanist
 * @version 1.0.0
 *
 * Features:
 * - Add/remove accompanying persons
 * - Minor detection and special requirements
 * - Parental authorization tracking
 * - Family relationship validation
 */

import { DocumentTypes } from './requirements-matrix.js';

// =============================================================================
// CONSTANTS
// =============================================================================

/**
 * Relationship types
 */
export const RelationshipType = {
    SPOUSE: 'spouse',
    CHILD: 'child',
    PARENT: 'parent',
    SIBLING: 'sibling',
    GRANDPARENT: 'grandparent',
    GRANDCHILD: 'grandchild',
    UNCLE_AUNT: 'uncle_aunt',
    NEPHEW_NIECE: 'nephew_niece',
    COUSIN: 'cousin',
    FRIEND: 'friend',
    COLLEAGUE: 'colleague',
    OTHER: 'other'
};

/**
 * Relationship labels (bilingual)
 */
const RELATIONSHIP_LABELS = {
    [RelationshipType.SPOUSE]: { fr: 'Conjoint(e)', en: 'Spouse' },
    [RelationshipType.CHILD]: { fr: 'Enfant', en: 'Child' },
    [RelationshipType.PARENT]: { fr: 'Parent', en: 'Parent' },
    [RelationshipType.SIBLING]: { fr: 'Frère/Soeur', en: 'Sibling' },
    [RelationshipType.GRANDPARENT]: { fr: 'Grand-parent', en: 'Grandparent' },
    [RelationshipType.GRANDCHILD]: { fr: 'Petit-enfant', en: 'Grandchild' },
    [RelationshipType.UNCLE_AUNT]: { fr: 'Oncle/Tante', en: 'Uncle/Aunt' },
    [RelationshipType.NEPHEW_NIECE]: { fr: 'Neveu/Nièce', en: 'Nephew/Niece' },
    [RelationshipType.COUSIN]: { fr: 'Cousin(e)', en: 'Cousin' },
    [RelationshipType.FRIEND]: { fr: 'Ami(e)', en: 'Friend' },
    [RelationshipType.COLLEAGUE]: { fr: 'Collègue', en: 'Colleague' },
    [RelationshipType.OTHER]: { fr: 'Autre', en: 'Other' }
};

/**
 * Minor age threshold
 */
export const MINOR_AGE_THRESHOLD = 18;

/**
 * Documents required for minors
 */
export const MINOR_REQUIRED_DOCUMENTS = {
    parentalAuthorization: {
        type: 'PARENTAL_AUTHORIZATION',
        fr: 'Autorisation parentale',
        en: 'Parental Authorization',
        description: {
            fr: 'Autorisation de voyage signée par les deux parents ou le tuteur légal',
            en: 'Travel authorization signed by both parents or legal guardian'
        },
        required: true
    },
    birthCertificate: {
        type: 'BIRTH_CERTIFICATE',
        fr: 'Acte de naissance',
        en: 'Birth Certificate',
        description: {
            fr: 'Copie certifiée de l\'acte de naissance',
            en: 'Certified copy of birth certificate'
        },
        required: true
    },
    parentId: {
        type: 'PARENT_ID',
        fr: 'Pièce d\'identité du parent',
        en: 'Parent ID',
        description: {
            fr: 'Copie de la pièce d\'identité des parents ou tuteurs',
            en: 'Copy of parent or guardian ID'
        },
        required: true
    },
    guardianshipProof: {
        type: 'GUARDIANSHIP_PROOF',
        fr: 'Preuve de tutelle',
        en: 'Guardianship Proof',
        description: {
            fr: 'Document officiel de tutelle (si applicable)',
            en: 'Official guardianship document (if applicable)'
        },
        required: false,
        condition: 'is_guardian'
    }
};

// =============================================================================
// ACCOMPANIST CLASS
// =============================================================================

export class Accompanist {
    constructor(data = {}) {
        this.id = data.id || `acc_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
        this.firstName = data.firstName || '';
        this.lastName = data.lastName || '';
        this.dateOfBirth = data.dateOfBirth || null;
        this.nationality = data.nationality || '';
        this.passportNumber = data.passportNumber || '';
        this.relationship = data.relationship || RelationshipType.OTHER;
        this.documents = data.documents || {};
        this.createdAt = data.createdAt || new Date().toISOString();
    }

    /**
     * Get full name
     */
    get fullName() {
        return `${this.firstName} ${this.lastName}`.trim();
    }

    /**
     * Calculate age
     */
    get age() {
        if (!this.dateOfBirth) return null;

        const dob = new Date(this.dateOfBirth);
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const monthDiff = today.getMonth() - dob.getMonth();

        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
            age--;
        }

        return age;
    }

    /**
     * Check if minor
     */
    get isMinor() {
        const age = this.age;
        return age !== null && age < MINOR_AGE_THRESHOLD;
    }

    /**
     * Get required documents for this accompanist
     */
    getRequiredDocuments() {
        if (!this.isMinor) {
            // Adults only need passport
            return [{
                type: 'PASSPORT',
                required: true
            }];
        }

        // Minors need additional documents
        return Object.values(MINOR_REQUIRED_DOCUMENTS).filter(doc => {
            if (!doc.required && doc.condition) {
                // Handle conditional documents
                return false; // Will be evaluated by the caller
            }
            return doc.required;
        });
    }

    /**
     * Check if all required documents are provided
     */
    hasAllRequiredDocuments() {
        const required = this.getRequiredDocuments();
        return required.every(doc => this.documents[doc.type]?.provided);
    }

    /**
     * Add a document
     */
    addDocument(type, data) {
        this.documents[type] = {
            provided: true,
            data,
            addedAt: new Date().toISOString()
        };
    }

    /**
     * Remove a document
     */
    removeDocument(type) {
        delete this.documents[type];
    }

    /**
     * Export to plain object
     */
    toJSON() {
        return {
            id: this.id,
            firstName: this.firstName,
            lastName: this.lastName,
            dateOfBirth: this.dateOfBirth,
            nationality: this.nationality,
            passportNumber: this.passportNumber,
            relationship: this.relationship,
            documents: this.documents,
            createdAt: this.createdAt,
            // Computed
            fullName: this.fullName,
            age: this.age,
            isMinor: this.isMinor
        };
    }
}

// =============================================================================
// ACCOMPANIST MANAGER CLASS
// =============================================================================

export class AccompanistManager {
    constructor(options = {}) {
        this.language = options.language || 'fr';
        this.accompanists = [];
        this.maxAccompanists = options.maxAccompanists || 10;
        this.onChange = options.onChange || (() => {});
    }

    /**
     * Add a new accompanist
     */
    add(data) {
        if (this.accompanists.length >= this.maxAccompanists) {
            throw new Error(this.t({
                fr: `Maximum ${this.maxAccompanists} accompagnants autorisés`,
                en: `Maximum ${this.maxAccompanists} accompanists allowed`
            }));
        }

        const accompanist = new Accompanist(data);
        this.accompanists.push(accompanist);
        this.onChange('add', accompanist);

        return accompanist;
    }

    /**
     * Update an accompanist
     */
    update(id, data) {
        const index = this.accompanists.findIndex(a => a.id === id);
        if (index === -1) {
            throw new Error('Accompanist not found');
        }

        const accompanist = this.accompanists[index];
        Object.assign(accompanist, data);
        this.onChange('update', accompanist);

        return accompanist;
    }

    /**
     * Remove an accompanist
     */
    remove(id) {
        const index = this.accompanists.findIndex(a => a.id === id);
        if (index === -1) {
            throw new Error('Accompanist not found');
        }

        const [removed] = this.accompanists.splice(index, 1);
        this.onChange('remove', removed);

        return removed;
    }

    /**
     * Get accompanist by ID
     */
    get(id) {
        return this.accompanists.find(a => a.id === id);
    }

    /**
     * Get all accompanists
     */
    getAll() {
        return [...this.accompanists];
    }

    /**
     * Get minors only
     */
    getMinors() {
        return this.accompanists.filter(a => a.isMinor);
    }

    /**
     * Get adults only
     */
    getAdults() {
        return this.accompanists.filter(a => !a.isMinor);
    }

    /**
     * Check if there are any minors
     */
    hasMinors() {
        return this.accompanists.some(a => a.isMinor);
    }

    /**
     * Get count of accompanists
     */
    get count() {
        return this.accompanists.length;
    }

    /**
     * Get count of minors
     */
    get minorCount() {
        return this.getMinors().length;
    }

    /**
     * Get all missing documents for minors
     */
    getMissingMinorDocuments() {
        const missing = [];

        for (const minor of this.getMinors()) {
            const requiredDocs = minor.getRequiredDocuments();
            const missingDocs = requiredDocs.filter(doc => !minor.documents[doc.type]?.provided);

            if (missingDocs.length > 0) {
                missing.push({
                    accompanist: minor,
                    missingDocuments: missingDocs.map(doc => ({
                        ...doc,
                        label: this.t(MINOR_REQUIRED_DOCUMENTS[this.getDocKey(doc.type)] || { fr: doc.type, en: doc.type })
                    }))
                });
            }
        }

        return missing;
    }

    /**
     * Get document key from type
     */
    getDocKey(type) {
        for (const [key, doc] of Object.entries(MINOR_REQUIRED_DOCUMENTS)) {
            if (doc.type === type) return key;
        }
        return null;
    }

    /**
     * Validate all accompanists
     */
    validate() {
        const issues = [];

        for (const acc of this.accompanists) {
            // Check required fields
            if (!acc.firstName || !acc.lastName) {
                issues.push({
                    accompanistId: acc.id,
                    type: 'MISSING_NAME',
                    message: this.t({
                        fr: `Nom incomplet pour ${acc.fullName || 'un accompagnant'}`,
                        en: `Incomplete name for ${acc.fullName || 'an accompanist'}`
                    })
                });
            }

            if (!acc.dateOfBirth) {
                issues.push({
                    accompanistId: acc.id,
                    type: 'MISSING_DOB',
                    message: this.t({
                        fr: `Date de naissance manquante pour ${acc.fullName}`,
                        en: `Date of birth missing for ${acc.fullName}`
                    })
                });
            }

            if (!acc.passportNumber) {
                issues.push({
                    accompanistId: acc.id,
                    type: 'MISSING_PASSPORT',
                    message: this.t({
                        fr: `Numéro de passeport manquant pour ${acc.fullName}`,
                        en: `Passport number missing for ${acc.fullName}`
                    })
                });
            }

            // Check minor-specific requirements
            if (acc.isMinor && !acc.hasAllRequiredDocuments()) {
                const missing = acc.getRequiredDocuments().filter(d => !acc.documents[d.type]?.provided);
                for (const doc of missing) {
                    issues.push({
                        accompanistId: acc.id,
                        type: 'MISSING_MINOR_DOC',
                        documentType: doc.type,
                        message: this.t({
                            fr: `Document manquant pour le mineur ${acc.fullName}: ${MINOR_REQUIRED_DOCUMENTS[this.getDocKey(doc.type)]?.fr || doc.type}`,
                            en: `Missing document for minor ${acc.fullName}: ${MINOR_REQUIRED_DOCUMENTS[this.getDocKey(doc.type)]?.en || doc.type}`
                        })
                    });
                }
            }
        }

        return {
            valid: issues.length === 0,
            issues
        };
    }

    /**
     * Get relationship label
     */
    getRelationshipLabel(relationship) {
        const labels = RELATIONSHIP_LABELS[relationship];
        return labels ? this.t(labels) : relationship;
    }

    /**
     * Get all relationship options
     */
    getRelationshipOptions() {
        return Object.entries(RELATIONSHIP_LABELS).map(([value, labels]) => ({
            value,
            label: this.t(labels)
        }));
    }

    /**
     * Get minor document requirements (for display)
     */
    getMinorDocumentRequirements() {
        return Object.entries(MINOR_REQUIRED_DOCUMENTS).map(([key, doc]) => ({
            key,
            type: doc.type,
            label: this.t({ fr: doc.fr, en: doc.en }),
            description: this.t(doc.description),
            required: doc.required
        }));
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
     * Clear all accompanists
     */
    clear() {
        this.accompanists = [];
        this.onChange('clear', null);
    }

    /**
     * Export state
     */
    exportState() {
        return {
            accompanists: this.accompanists.map(a => a.toJSON())
        };
    }

    /**
     * Import state
     */
    importState(state) {
        if (state.accompanists) {
            this.accompanists = state.accompanists.map(data => new Accompanist(data));
        }
    }

    /**
     * Get summary for display
     */
    getSummary() {
        return {
            total: this.count,
            adults: this.getAdults().length,
            minors: this.minorCount,
            allDocumentsComplete: this.getMinors().every(m => m.hasAllRequiredDocuments()),
            accompanists: this.accompanists.map(a => ({
                id: a.id,
                name: a.fullName,
                age: a.age,
                isMinor: a.isMinor,
                relationship: this.getRelationshipLabel(a.relationship),
                documentsComplete: a.isMinor ? a.hasAllRequiredDocuments() : true
            }))
        };
    }
}

// =============================================================================
// SINGLETON EXPORT
// =============================================================================

export const accompanistManager = new AccompanistManager();

export default accompanistManager;
