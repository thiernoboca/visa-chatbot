/**
 * PDF Generator Module
 * Generates visa application receipt PDF
 *
 * @module pdf-generator
 * @version 1.0.0
 *
 * Features:
 * - Generates visa application receipt
 * - Includes QR code for verification
 * - Includes signature
 * - Multi-language support (FR/EN)
 */

// =============================================================================
// CONSTANTS
// =============================================================================

/**
 * PDF configuration
 */
const PDF_CONFIG = {
    pageWidth: 595.28, // A4 width in points
    pageHeight: 841.89, // A4 height in points
    margin: 40,
    headerHeight: 100,
    footerHeight: 60,
    primaryColor: '#FF6600', // CI Embassy orange
    secondaryColor: '#009E49', // CI green
    textColor: '#333333',
    lightGray: '#f5f5f5',
    borderColor: '#e0e0e0'
};

/**
 * Section types
 */
const SectionType = {
    APPLICANT: 'applicant',
    TRAVEL: 'travel',
    DOCUMENTS: 'documents',
    PAYMENT: 'payment',
    SIGNATURE: 'signature'
};

// =============================================================================
// PDF GENERATOR CLASS
// =============================================================================

export class PDFGenerator {
    constructor(options = {}) {
        this.language = options.language || 'fr';
        this.applicationData = null;
        this.referenceNumber = null;
        this.qrCodeData = null;
    }

    /**
     * Generate PDF receipt
     * @param {Object} data - Application data
     * @param {Object} options - Generation options
     * @returns {Object} - PDF data and download function
     */
    async generate(data, options = {}) {
        this.applicationData = data;
        this.referenceNumber = data.referenceNumber || this.generateReferenceNumber();

        // Generate QR code data
        this.qrCodeData = await this.generateQRCode(this.referenceNumber);

        // Build PDF content
        const pdfContent = this.buildPDFContent(data, options);

        return {
            success: true,
            referenceNumber: this.referenceNumber,
            content: pdfContent,
            download: (filename) => this.downloadPDF(pdfContent, filename),
            print: () => this.printPDF(pdfContent),
            getDataURL: () => this.getDataURL(pdfContent)
        };
    }

    /**
     * Generate reference number
     */
    generateReferenceNumber() {
        const date = new Date();
        const dateStr = date.toISOString().slice(0, 10).replace(/-/g, '');
        const random = Math.random().toString(36).substring(2, 8).toUpperCase();
        return `CIV-${dateStr}-${random}`;
    }

    /**
     * Generate QR code (placeholder - actual implementation would use a library)
     */
    async generateQRCode(reference) {
        // In production, use a QR code library like qrcode.js
        // For now, return placeholder data
        return {
            data: reference,
            url: `https://visa.ambaci-addis.org/verify/${reference}`,
            // dataURL would be the actual QR code image
            dataURL: null
        };
    }

    /**
     * Build PDF content structure
     */
    buildPDFContent(data, options) {
        const sections = [];

        // Header
        sections.push(this.buildHeader(data));

        // Applicant Information
        sections.push(this.buildApplicantSection(data.applicant || data.passport));

        // Travel Information
        sections.push(this.buildTravelSection(data.travel || data.ticket));

        // Documents Summary
        sections.push(this.buildDocumentsSection(data.documents));

        // Payment Information (if applicable)
        if (data.payment && !data.payment.exempted) {
            sections.push(this.buildPaymentSection(data.payment));
        }

        // Signature
        if (data.signature) {
            sections.push(this.buildSignatureSection(data.signature));
        }

        // QR Code and Footer
        sections.push(this.buildFooter());

        return {
            format: 'A4',
            orientation: 'portrait',
            sections,
            metadata: {
                title: this.t({ fr: 'Récépissé de Demande de Visa', en: 'Visa Application Receipt' }),
                author: 'Ambassade de Côte d\'Ivoire en Éthiopie',
                subject: `Visa Application - ${this.referenceNumber}`,
                keywords: 'visa, cote d\'ivoire, application, receipt',
                creator: 'Visa Chatbot System',
                creationDate: new Date().toISOString()
            }
        };
    }

    /**
     * Build header section
     */
    buildHeader(data) {
        return {
            type: 'header',
            content: {
                logo: '/images/ci-coat-of-arms.png', // Path to coat of arms
                title: {
                    main: this.t({
                        fr: 'RÉPUBLIQUE DE CÔTE D\'IVOIRE',
                        en: 'REPUBLIC OF CÔTE D\'IVOIRE'
                    }),
                    sub: this.t({
                        fr: 'Union - Discipline - Travail',
                        en: 'Union - Discipline - Work'
                    })
                },
                embassy: this.t({
                    fr: 'AMBASSADE DE CÔTE D\'IVOIRE EN ÉTHIOPIE',
                    en: 'EMBASSY OF CÔTE D\'IVOIRE IN ETHIOPIA'
                }),
                documentTitle: this.t({
                    fr: 'RÉCÉPISSÉ DE DEMANDE DE VISA',
                    en: 'VISA APPLICATION RECEIPT'
                }),
                referenceNumber: this.referenceNumber,
                dateGenerated: new Date().toLocaleDateString(this.language === 'fr' ? 'fr-FR' : 'en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                })
            }
        };
    }

    /**
     * Build applicant information section
     */
    buildApplicantSection(applicant) {
        const fields = [
            {
                label: this.t({ fr: 'Nom complet', en: 'Full Name' }),
                value: `${applicant.surname || ''} ${applicant.given_names || applicant.givenNames || ''}`.trim()
            },
            {
                label: this.t({ fr: 'Nationalité', en: 'Nationality' }),
                value: applicant.nationality || '-'
            },
            {
                label: this.t({ fr: 'Date de naissance', en: 'Date of Birth' }),
                value: this.formatDate(applicant.date_of_birth || applicant.dateOfBirth)
            },
            {
                label: this.t({ fr: 'Numéro de passeport', en: 'Passport Number' }),
                value: applicant.passport_number || applicant.passportNumber || '-'
            },
            {
                label: this.t({ fr: 'Expiration du passeport', en: 'Passport Expiry' }),
                value: this.formatDate(applicant.expiry_date || applicant.expiryDate)
            },
            {
                label: this.t({ fr: 'Type de passeport', en: 'Passport Type' }),
                value: applicant.passport_type || applicant.passportType || 'ORDINAIRE'
            }
        ];

        return {
            type: 'section',
            id: SectionType.APPLICANT,
            title: this.t({ fr: 'INFORMATIONS DU DEMANDEUR', en: 'APPLICANT INFORMATION' }),
            fields
        };
    }

    /**
     * Build travel information section
     */
    buildTravelSection(travel) {
        if (!travel) return null;

        const fields = [
            {
                label: this.t({ fr: 'Date d\'arrivée', en: 'Arrival Date' }),
                value: this.formatDate(travel.departure_date || travel.arrivalDate)
            },
            {
                label: this.t({ fr: 'Date de départ', en: 'Departure Date' }),
                value: this.formatDate(travel.return_date || travel.departureDate)
            },
            {
                label: this.t({ fr: 'Motif du voyage', en: 'Purpose of Travel' }),
                value: travel.purpose || travel.motif || '-'
            },
            {
                label: this.t({ fr: 'Type de visa demandé', en: 'Visa Type Requested' }),
                value: travel.visa_type || travel.visaType || 'Court séjour'
            },
            {
                label: this.t({ fr: 'Hébergement', en: 'Accommodation' }),
                value: travel.accommodation || travel.hotel_name || '-'
            }
        ];

        return {
            type: 'section',
            id: SectionType.TRAVEL,
            title: this.t({ fr: 'INFORMATIONS DE VOYAGE', en: 'TRAVEL INFORMATION' }),
            fields
        };
    }

    /**
     * Build documents summary section
     */
    buildDocumentsSection(documents) {
        if (!documents) return null;

        const docList = [];

        const docLabels = {
            passport: { fr: 'Passeport', en: 'Passport' },
            photo: { fr: 'Photo d\'identité', en: 'ID Photo' },
            ticket: { fr: 'Billet d\'avion', en: 'Flight Ticket' },
            hotel: { fr: 'Réservation d\'hôtel', en: 'Hotel Reservation' },
            vaccination: { fr: 'Certificat de vaccination', en: 'Vaccination Certificate' },
            invitation: { fr: 'Lettre d\'invitation', en: 'Invitation Letter' },
            verbal_note: { fr: 'Note verbale', en: 'Verbal Note' },
            residence_card: { fr: 'Carte de résidence', en: 'Residence Card' },
            payment: { fr: 'Preuve de paiement', en: 'Payment Proof' }
        };

        for (const [docType, docData] of Object.entries(documents)) {
            if (docData && (docData.provided || docData.success)) {
                docList.push({
                    type: docType,
                    label: this.t(docLabels[docType] || { fr: docType, en: docType }),
                    status: 'provided',
                    icon: '✓'
                });
            }
        }

        return {
            type: 'documentList',
            id: SectionType.DOCUMENTS,
            title: this.t({ fr: 'DOCUMENTS FOURNIS', en: 'DOCUMENTS PROVIDED' }),
            documents: docList
        };
    }

    /**
     * Build payment information section
     */
    buildPaymentSection(payment) {
        const fields = [
            {
                label: this.t({ fr: 'Montant', en: 'Amount' }),
                value: `${payment.amount?.toLocaleString() || '-'} ${payment.currency || 'XOF'}`
            },
            {
                label: this.t({ fr: 'Référence de paiement', en: 'Payment Reference' }),
                value: payment.reference || '-'
            },
            {
                label: this.t({ fr: 'Date de paiement', en: 'Payment Date' }),
                value: this.formatDate(payment.date)
            },
            {
                label: this.t({ fr: 'Statut', en: 'Status' }),
                value: payment.verified
                    ? this.t({ fr: 'Vérifié', en: 'Verified' })
                    : this.t({ fr: 'En attente de vérification', en: 'Pending verification' })
            }
        ];

        return {
            type: 'section',
            id: SectionType.PAYMENT,
            title: this.t({ fr: 'INFORMATIONS DE PAIEMENT', en: 'PAYMENT INFORMATION' }),
            fields
        };
    }

    /**
     * Build signature section
     */
    buildSignatureSection(signature) {
        return {
            type: 'signature',
            id: SectionType.SIGNATURE,
            title: this.t({ fr: 'SIGNATURE ÉLECTRONIQUE', en: 'ELECTRONIC SIGNATURE' }),
            content: {
                image: signature.image || signature.data,
                signer: signature.signer || signature.name,
                timestamp: signature.timestamp,
                formattedDate: this.formatDateTime(signature.timestamp)
            },
            disclaimer: this.t({
                fr: 'En signant ce document, je certifie que les informations fournies sont exactes et complètes.',
                en: 'By signing this document, I certify that the information provided is accurate and complete.'
            })
        };
    }

    /**
     * Build footer section
     */
    buildFooter() {
        return {
            type: 'footer',
            content: {
                qrCode: this.qrCodeData,
                qrLabel: this.t({
                    fr: 'Scanner pour vérifier',
                    en: 'Scan to verify'
                }),
                referenceNumber: this.referenceNumber,
                verificationUrl: this.qrCodeData?.url,
                embassy: {
                    name: 'Ambassade de Côte d\'Ivoire',
                    address: 'Africa Avenue, Addis Ababa, Ethiopia',
                    phone: '+251 11 551 2155',
                    email: 'visa@ambaci-addis.org',
                    website: 'www.ambaci-addis.org'
                },
                disclaimer: this.t({
                    fr: 'Ce récépissé ne constitue pas un visa. Veuillez vous présenter à l\'ambassade avec les originaux de vos documents.',
                    en: 'This receipt is not a visa. Please present yourself at the embassy with original documents.'
                }),
                generatedAt: new Date().toISOString()
            }
        };
    }

    /**
     * Download PDF
     */
    downloadPDF(content, filename) {
        // In production, use a PDF library like jsPDF or pdfmake
        // For now, generate a simple HTML preview that can be printed to PDF

        const html = this.generateHTML(content);
        const blob = new Blob([html], { type: 'text/html' });
        const url = URL.createObjectURL(blob);

        const link = document.createElement('a');
        link.href = url;
        link.download = filename || `${this.referenceNumber}.html`;
        link.click();

        URL.revokeObjectURL(url);
    }

    /**
     * Print PDF
     */
    printPDF(content) {
        const html = this.generateHTML(content);
        const printWindow = window.open('', '_blank');

        if (printWindow) {
            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
        }
    }

    /**
     * Get as data URL (for email embedding, etc.)
     */
    getDataURL(content) {
        const html = this.generateHTML(content);
        return `data:text/html;charset=utf-8,${encodeURIComponent(html)}`;
    }

    /**
     * Generate HTML representation
     */
    generateHTML(content) {
        const styles = `
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 20px; color: ${PDF_CONFIG.textColor}; }
            .container { max-width: 800px; margin: 0 auto; background: white; padding: 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { text-align: center; border-bottom: 3px solid ${PDF_CONFIG.primaryColor}; padding-bottom: 20px; margin-bottom: 30px; }
            .header h1 { color: ${PDF_CONFIG.primaryColor}; margin: 0; font-size: 14px; }
            .header h2 { color: ${PDF_CONFIG.secondaryColor}; margin: 5px 0; font-size: 12px; }
            .header h3 { margin: 15px 0 5px; font-size: 18px; }
            .reference { background: ${PDF_CONFIG.lightGray}; padding: 10px; border-radius: 4px; margin-top: 15px; }
            .section { margin-bottom: 25px; }
            .section-title { background: ${PDF_CONFIG.primaryColor}; color: white; padding: 8px 15px; font-size: 14px; font-weight: bold; margin-bottom: 15px; }
            .field-row { display: flex; padding: 8px 0; border-bottom: 1px solid ${PDF_CONFIG.borderColor}; }
            .field-label { width: 40%; font-weight: 500; color: #666; }
            .field-value { width: 60%; }
            .doc-list { list-style: none; padding: 0; }
            .doc-item { padding: 8px 15px; background: ${PDF_CONFIG.lightGray}; margin-bottom: 5px; border-radius: 4px; }
            .doc-item .icon { color: ${PDF_CONFIG.secondaryColor}; margin-right: 10px; }
            .signature-box { border: 1px solid ${PDF_CONFIG.borderColor}; padding: 20px; text-align: center; }
            .signature-image { max-width: 300px; max-height: 100px; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 2px solid ${PDF_CONFIG.borderColor}; text-align: center; font-size: 12px; color: #666; }
            .qr-section { margin: 20px 0; }
            .disclaimer { background: #fff3cd; padding: 15px; border-radius: 4px; font-size: 12px; margin-top: 20px; }
            @media print { body { padding: 0; } .container { box-shadow: none; } }
        `;

        let html = `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>${content.metadata.title}</title><style>${styles}</style></head><body><div class="container">`;

        for (const section of content.sections) {
            if (!section) continue;

            switch (section.type) {
                case 'header':
                    html += this.renderHeader(section.content);
                    break;
                case 'section':
                    html += this.renderSection(section);
                    break;
                case 'documentList':
                    html += this.renderDocumentList(section);
                    break;
                case 'signature':
                    html += this.renderSignature(section);
                    break;
                case 'footer':
                    html += this.renderFooterHTML(section.content);
                    break;
            }
        }

        html += '</div></body></html>';
        return html;
    }

    /**
     * Render header HTML
     */
    renderHeader(content) {
        return `
            <div class="header">
                <h1>${content.title.main}</h1>
                <h2>${content.title.sub}</h2>
                <h3>${content.embassy}</h3>
                <div style="margin-top: 20px; font-size: 20px; font-weight: bold; color: ${PDF_CONFIG.primaryColor};">
                    ${content.documentTitle}
                </div>
                <div class="reference">
                    <strong>${this.t({ fr: 'N° de référence', en: 'Reference Number' })}:</strong>
                    ${content.referenceNumber}<br>
                    <strong>${this.t({ fr: 'Date', en: 'Date' })}:</strong>
                    ${content.dateGenerated}
                </div>
            </div>
        `;
    }

    /**
     * Render section HTML
     */
    renderSection(section) {
        let fieldsHtml = '';
        for (const field of section.fields) {
            fieldsHtml += `
                <div class="field-row">
                    <div class="field-label">${field.label}</div>
                    <div class="field-value">${field.value}</div>
                </div>
            `;
        }

        return `
            <div class="section">
                <div class="section-title">${section.title}</div>
                ${fieldsHtml}
            </div>
        `;
    }

    /**
     * Render document list HTML
     */
    renderDocumentList(section) {
        let listHtml = '';
        for (const doc of section.documents) {
            listHtml += `
                <li class="doc-item">
                    <span class="icon">${doc.icon}</span>
                    ${doc.label}
                </li>
            `;
        }

        return `
            <div class="section">
                <div class="section-title">${section.title}</div>
                <ul class="doc-list">${listHtml}</ul>
            </div>
        `;
    }

    /**
     * Render signature HTML
     */
    renderSignature(section) {
        const content = section.content;
        return `
            <div class="section">
                <div class="section-title">${section.title}</div>
                <div class="signature-box">
                    ${content.image ? `<img src="${content.image}" class="signature-image" alt="Signature">` : ''}
                    <div style="margin-top: 10px;">
                        <strong>${content.signer}</strong><br>
                        ${content.formattedDate}
                    </div>
                </div>
                <p style="font-size: 12px; color: #666; font-style: italic;">${section.disclaimer}</p>
            </div>
        `;
    }

    /**
     * Render footer HTML
     */
    renderFooterHTML(content) {
        return `
            <div class="footer">
                <div class="qr-section">
                    ${content.qrCode?.dataURL ? `<img src="${content.qrCode.dataURL}" style="width: 100px; height: 100px;" alt="QR Code">` : ''}
                    <div style="font-size: 10px; margin-top: 5px;">${content.qrLabel}</div>
                    <div style="font-size: 11px;"><strong>${content.referenceNumber}</strong></div>
                </div>
                <div style="margin-top: 15px;">
                    <strong>${content.embassy.name}</strong><br>
                    ${content.embassy.address}<br>
                    ${content.embassy.phone} | ${content.embassy.email}
                </div>
                <div class="disclaimer">${content.disclaimer}</div>
            </div>
        `;
    }

    /**
     * Format date
     */
    formatDate(date) {
        if (!date) return '-';
        try {
            return new Date(date).toLocaleDateString(this.language === 'fr' ? 'fr-FR' : 'en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        } catch {
            return date;
        }
    }

    /**
     * Format date and time
     */
    formatDateTime(datetime) {
        if (!datetime) return '-';
        try {
            return new Date(datetime).toLocaleString(this.language === 'fr' ? 'fr-FR' : 'en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch {
            return datetime;
        }
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
}

// =============================================================================
// SINGLETON EXPORT
// =============================================================================

export const pdfGenerator = new PDFGenerator();

export default pdfGenerator;
