<?php
/**
 * Générateur PDF de récépissé - Chatbot Visa CI
 * Génère le récépissé officiel de demande de visa
 * 
 * @package VisaChatbot
 * @version 1.0.0
 */

// Utiliser une librairie PDF simple basée sur HTML (dompdf alternative)
// Pour production, installer via composer: composer require tecnickcom/tcpdf

class PDFGenerator {
    
    /**
     * Configuration
     */
    private array $config;
    
    /**
     * Dossier de sortie
     */
    private string $outputDir;
    
    /**
     * Constructeur
     */
    public function __construct(array $options = []) {
        $this->config = [
            'author' => 'Ambassade de Côte d\'Ivoire en Éthiopie',
            'title' => 'Récépissé de demande de visa e-Visa',
            'subject' => 'Demande de visa électronique',
            'logo_path' => $options['logo_path'] ?? __DIR__ . '/../assets/logo-ci.png',
            'debug' => $options['debug'] ?? false
        ];
        
        $this->outputDir = $options['output_dir'] ?? __DIR__ . '/../data/receipts';
        
        // Créer le dossier si nécessaire
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }
    
    /**
     * Génère un récépissé PDF pour une demande de visa
     * 
     * @param array $applicationData Données de la demande
     * @param string|null $qrCodePath Chemin vers le QR code (optionnel)
     * @return array Résultat avec chemin du fichier
     */
    public function generateReceipt(array $applicationData, ?string $qrCodePath = null): array {
        $referenceNumber = $applicationData['reference_number'] ?? $this->generateReferenceNumber();
        $filename = "receipt_{$referenceNumber}.pdf";
        $filepath = $this->outputDir . '/' . $filename;
        
        // Générer le HTML du récépissé
        $html = $this->buildReceiptHTML($applicationData, $referenceNumber, $qrCodePath);
        
        // Sauvegarder en HTML pour l'instant (remplacer par TCPDF/DOMPDF en production)
        $htmlPath = str_replace('.pdf', '.html', $filepath);
        file_put_contents($htmlPath, $html);
        
        // En production, convertir en PDF avec TCPDF ou DOMPDF
        // Pour l'instant, on génère un PDF basique avec HTML2PDF ou on laisse en HTML
        
        return [
            'success' => true,
            'reference_number' => $referenceNumber,
            'filepath' => $htmlPath, // ou $filepath pour le vrai PDF
            'filename' => str_replace('.pdf', '.html', $filename),
            'generated_at' => date('c')
        ];
    }
    
    /**
     * Construit le HTML du récépissé
     */
    private function buildReceiptHTML(array $data, string $reference, ?string $qrCodePath): string {
        $applicant = $data['applicant'] ?? [];
        $passport = $data['passport'] ?? [];
        $trip = $data['trip'] ?? [];
        $contact = $data['contact'] ?? [];
        
        $name = trim(($applicant['given_names'] ?? $passport['given_names'] ?? '') . ' ' . ($applicant['surname'] ?? $passport['surname'] ?? ''));
        $passportNumber = $passport['passport_number'] ?? '-';
        $nationality = $passport['nationality'] ?? '-';
        $passportType = $data['passport_type'] ?? 'ORDINAIRE';
        $workflowType = $data['workflow_type'] ?? 'STANDARD';
        $arrivalDate = $trip['arrival_date'] ?? '-';
        $departureDate = $trip['departure_date'] ?? '-';
        $purpose = $trip['purpose'] ?? 'TOURISME';
        $email = $contact['email'] ?? '-';
        
        $submissionDate = date('d/m/Y H:i');
        $expiryDate = date('d/m/Y', strtotime('+30 days'));
        
        $isPriority = $workflowType === 'PRIORITY';
        $processingTime = $isPriority ? '24-48 heures' : '5-10 jours ouvrés';
        $fees = $isPriority ? 'GRATUIT' : 'À régler';
        
        $qrHtml = $qrCodePath ? "<img src=\"{$qrCodePath}\" alt=\"QR Code\" style=\"width: 120px; height: 120px;\"/>" : '';
        
        $workflowBadge = $isPriority 
            ? '<span style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white; padding: 4px 12px; border-radius: 20px; font-weight: bold; font-size: 12px;">⚡ PRIORITY</span>'
            : '<span style="background: #3b82f6; color: white; padding: 4px 12px; border-radius: 20px; font-weight: bold; font-size: 12px;">📋 STANDARD</span>';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Récépissé de demande de visa - {$reference}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            color: #333;
        }
        .receipt {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #ff8c00 0%, #ff6a00 50%, #00a650 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="3" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="2" fill="rgba(255,255,255,0.1)"/></svg>');
        }
        .header-content {
            position: relative;
            z-index: 1;
        }
        .embassy-title {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }
        .main-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .reference {
            font-size: 18px;
            background: rgba(255,255,255,0.2);
            display: inline-block;
            padding: 8px 20px;
            border-radius: 25px;
            margin-top: 10px;
            font-family: monospace;
            letter-spacing: 2px;
        }
        .content {
            padding: 30px;
        }
        .status-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        .status-item {
            text-align: center;
        }
        .status-label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 5px;
        }
        .status-value {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .info-item {
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
        }
        .info-label {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .info-value {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
        }
        .qr-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border-radius: 12px;
            margin-top: 25px;
        }
        .qr-info h4 {
            color: #0369a1;
            margin-bottom: 5px;
        }
        .qr-info p {
            font-size: 13px;
            color: #64748b;
        }
        .footer {
            background: #1e293b;
            color: white;
            padding: 20px 30px;
            text-align: center;
        }
        .footer p {
            font-size: 12px;
            opacity: 0.8;
            margin-bottom: 5px;
        }
        .footer .important {
            color: #fbbf24;
            font-weight: 600;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80px;
            color: rgba(0,0,0,0.03);
            font-weight: bold;
            pointer-events: none;
            white-space: nowrap;
        }
        @media print {
            body { padding: 0; background: white; }
            .receipt { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <div class="header-content">
                <div class="embassy-title">🇨🇮 AMBASSADE DE CÔTE D'IVOIRE EN ÉTHIOPIE</div>
                <div class="main-title">RÉCÉPISSÉ DE DEMANDE DE VISA</div>
                <div class="reference">{$reference}</div>
            </div>
        </div>
        
        <div class="content">
            <div class="watermark">RÉCÉPISSÉ</div>
            
            <div class="status-bar">
                <div class="status-item">
                    <div class="status-label">Statut</div>
                    <div class="status-value" style="color: #f59e0b;">🕐 EN ATTENTE</div>
                </div>
                <div class="status-item">
                    <div class="status-label">Workflow</div>
                    <div class="status-value">{$workflowBadge}</div>
                </div>
                <div class="status-item">
                    <div class="status-label">Délai estimé</div>
                    <div class="status-value">{$processingTime}</div>
                </div>
                <div class="status-item">
                    <div class="status-label">Frais</div>
                    <div class="status-value" style="color: #10b981;">{$fees}</div>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title">Informations du demandeur</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Nom complet</div>
                        <div class="info-value">{$name}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">N° Passeport</div>
                        <div class="info-value">{$passportNumber}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Nationalité</div>
                        <div class="info-value">{$nationality}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Type de passeport</div>
                        <div class="info-value">{$passportType}</div>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title">Détails du voyage</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Date d'arrivée</div>
                        <div class="info-value">{$arrivalDate}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Date de départ</div>
                        <div class="info-value">{$departureDate}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Motif</div>
                        <div class="info-value">{$purpose}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email de contact</div>
                        <div class="info-value">{$email}</div>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title">Informations de suivi</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Date de soumission</div>
                        <div class="info-value">{$submissionDate}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Validité du récépissé</div>
                        <div class="info-value">Jusqu'au {$expiryDate}</div>
                    </div>
                </div>
            </div>
            
            <div class="qr-section">
                <div class="qr-info">
                    <h4>🔐 Vérification sécurisée</h4>
                    <p>Scannez le QR code pour vérifier l'authenticité de ce document<br>ou visitez: visa.ambaci-ethiopie.org/verify/{$reference}</p>
                </div>
                <div class="qr-code">
                    {$qrHtml}
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p class="important">⚠️ Ce document est un récépissé de dépôt et ne constitue pas un visa.</p>
            <p>Conservez ce document et présentez-le lors de toute communication avec l'Ambassade.</p>
            <p style="margin-top: 10px;">Ambassade de Côte d'Ivoire • Addis-Abeba, Éthiopie • +251 11 xxx xxxx</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * Génère un numéro de référence unique
     */
    private function generateReferenceNumber(): string {
        $prefix = 'CIV';
        $year = date('Y');
        $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        return "{$prefix}-{$year}-{$random}";
    }
    
    /**
     * Génère un récépissé et l'envoie par email
     */
    public function generateAndSendReceipt(array $applicationData, string $email, ?string $qrCodePath = null): array {
        // Générer le PDF
        $result = $this->generateReceipt($applicationData, $qrCodePath);
        
        if (!$result['success']) {
            return $result;
        }
        
        // TODO: Intégrer un service d'envoi d'email
        // Pour l'instant, on retourne juste le résultat de génération
        
        $result['email_sent'] = false;
        $result['email_message'] = 'Email sending not implemented yet';
        
        return $result;
    }
}

// Test si exécuté directement
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($argv[0] ?? '')) {
    $generator = new PDFGenerator();
    
    $testData = [
        'reference_number' => 'CIV-2025-TEST01',
        'applicant' => [
            'given_names' => 'Jean',
            'surname' => 'DUPONT'
        ],
        'passport' => [
            'passport_number' => 'AB1234567',
            'nationality' => 'Éthiopie'
        ],
        'passport_type' => 'ORDINAIRE',
        'workflow_type' => 'STANDARD',
        'trip' => [
            'arrival_date' => '15/01/2025',
            'departure_date' => '30/01/2025',
            'purpose' => 'TOURISME'
        ],
        'contact' => [
            'email' => 'jean.dupont@example.com'
        ]
    ];
    
    $result = $generator->generateReceipt($testData);
    print_r($result);
}

