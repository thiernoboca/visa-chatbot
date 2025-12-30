<?php
/**
 * Notification Service - Chatbot Visa CI
 * Gère l'envoi de notifications (email, SMS, etc.)
 * 
 * @package VisaChatbot
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session-manager.php';

class NotificationService {
    
    /**
     * Types de notifications
     */
    public const TYPE_EMAIL = 'email';
    public const TYPE_SMS = 'sms';
    public const TYPE_WHATSAPP = 'whatsapp';
    public const TYPE_PUSH = 'push';
    
    /**
     * Templates de messages
     */
    private static array $templates = [
        'submission_confirmed' => [
            'fr' => [
                'subject' => 'Demande de Visa CI #{pre_application_id} - Confirmation',
                'body' => "Bonjour {name},\n\nVotre demande de visa pour la Côte d'Ivoire a été enregistrée avec succès.\n\nNuméro de pré-demande: {pre_application_id}\nDate de soumission: {date}\n\nVous pouvez suivre l'état de votre demande ici:\n{tracking_url}\n\nProchaine étape: Veuillez prendre rendez-vous pour déposer vos documents originaux.\n\nCordialement,\nAmbassade de Côte d'Ivoire\nAddis-Abeba, Éthiopie"
            ],
            'en' => [
                'subject' => 'Visa Application CI #{pre_application_id} - Confirmation',
                'body' => "Hello {name},\n\nYour visa application for Côte d'Ivoire has been successfully registered.\n\nPre-application number: {pre_application_id}\nSubmission date: {date}\n\nYou can track the status of your application here:\n{tracking_url}\n\nNext step: Please schedule an appointment to submit your original documents.\n\nBest regards,\nEmbassy of Côte d'Ivoire\nAddis Ababa, Ethiopia"
            ]
        ],
        'documents_required' => [
            'fr' => [
                'subject' => 'Visa CI #{pre_application_id} - Documents manquants',
                'body' => "Bonjour {name},\n\nNous avons examiné votre demande de visa et les documents suivants sont manquants:\n\n{missing_documents}\n\nMerci de les téléverser via le lien ci-dessous:\n{upload_url}\n\nVotre demande sera traitée dès réception de tous les documents.\n\nCordialement,\nAmbassade de Côte d'Ivoire"
            ]
        ],
        'appointment_reminder' => [
            'fr' => [
                'subject' => 'Rappel RDV - Visa CI #{pre_application_id}',
                'body' => "Bonjour {name},\n\nRappel de votre rendez-vous:\n\nDate: {appointment_date}\nHeure: {appointment_time}\nLieu: Ambassade de Côte d'Ivoire, Addis-Abeba\n\nDocuments à apporter:\n- Passeport original\n- Formulaire imprimé\n- Photo d'identité\n- {required_documents}\n\nÀ très bientôt!"
            ]
        ],
        'visa_ready' => [
            'fr' => [
                'subject' => '🎉 Votre Visa CI est prêt! #{pre_application_id}',
                'body' => "Bonjour {name},\n\nExcellente nouvelle! Votre visa pour la Côte d'Ivoire est prêt.\n\nVous pouvez venir le récupérer à l'Ambassade du lundi au vendredi de 9h à 12h.\n\nN'oubliez pas d'apporter:\n- Une pièce d'identité\n- Le reçu de paiement\n\nBon voyage en Côte d'Ivoire! 🇨🇮\n\nCordialement,\nAmbassade de Côte d'Ivoire"
            ]
        ]
    ];
    
    /**
     * Session manager
     */
    private ?SessionManager $session;
    
    /**
     * Constructeur
     */
    public function __construct(?SessionManager $session = null) {
        $this->session = $session;
    }
    
    /**
     * Envoie une notification email
     */
    public function sendEmail(string $to, string $template, array $data, string $lang = 'fr'): array {
        $templateData = self::$templates[$template][$lang] ?? self::$templates[$template]['fr'] ?? null;
        
        if (!$templateData) {
            return ['success' => false, 'error' => 'Template non trouvé'];
        }
        
        $subject = $this->replaceVariables($templateData['subject'], $data);
        $body = $this->replaceVariables($templateData['body'], $data);
        
        // En production, utiliser un service email (SendGrid, Mailgun, etc.)
        // Pour le prototype, on simule l'envoi
        $result = $this->simulateEmailSend($to, $subject, $body);
        
        // Logger la notification
        $this->logNotification(self::TYPE_EMAIL, $to, $template, $result);
        
        return $result;
    }
    
    /**
     * Envoie une notification SMS
     */
    public function sendSMS(string $to, string $template, array $data, string $lang = 'fr'): array {
        $templateData = self::$templates[$template][$lang] ?? null;
        
        if (!$templateData) {
            return ['success' => false, 'error' => 'Template non trouvé'];
        }
        
        // SMS: version courte du message
        $message = "Visa CI #{$data['pre_application_id']}: " . substr($templateData['body'], 0, 140);
        
        // En production: Twilio, Africa's Talking, etc.
        $result = $this->simulateSMSSend($to, $message);
        
        $this->logNotification(self::TYPE_SMS, $to, $template, $result);
        
        return $result;
    }
    
    /**
     * Envoie une notification WhatsApp
     */
    public function sendWhatsApp(string $to, string $template, array $data, string $lang = 'fr'): array {
        $templateData = self::$templates[$template][$lang] ?? null;
        
        if (!$templateData) {
            return ['success' => false, 'error' => 'Template non trouvé'];
        }
        
        $message = $this->replaceVariables($templateData['body'], $data);
        
        // En production: WhatsApp Business API
        $result = $this->simulateWhatsAppSend($to, $message);
        
        $this->logNotification(self::TYPE_WHATSAPP, $to, $template, $result);
        
        return $result;
    }
    
    /**
     * Envoie une notification de confirmation de soumission
     */
    public function sendSubmissionConfirmation(array $applicationData): array {
        $results = [];
        
        $data = [
            'name' => $applicationData['full_name'] ?? 'Demandeur',
            'pre_application_id' => $applicationData['pre_application_id'] ?? 'N/A',
            'date' => date('d/m/Y H:i'),
            'tracking_url' => $this->getTrackingUrl($applicationData['pre_application_id'] ?? '')
        ];
        
        $lang = $applicationData['language'] ?? 'fr';
        
        // Email
        if (!empty($applicationData['email'])) {
            $results['email'] = $this->sendEmail(
                $applicationData['email'],
                'submission_confirmed',
                $data,
                $lang
            );
        }
        
        // SMS optionnel
        if (!empty($applicationData['phone']) && ($applicationData['notify_sms'] ?? false)) {
            $results['sms'] = $this->sendSMS(
                $applicationData['phone'],
                'submission_confirmed',
                $data,
                $lang
            );
        }
        
        return $results;
    }
    
    /**
     * Remplace les variables dans un template
     */
    private function replaceVariables(string $template, array $data): string {
        foreach ($data as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $template = str_replace('{' . $key . '}', (string)$value, $template);
            }
        }
        return $template;
    }
    
    /**
     * Retourne l'URL de suivi
     */
    private function getTrackingUrl(string $preApplicationId): string {
        $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $baseUrl . '/hunyuanocr/visa-chatbot/tracking.html?id=' . urlencode($preApplicationId);
    }
    
    /**
     * Simule l'envoi d'email (pour le prototype)
     */
    private function simulateEmailSend(string $to, string $subject, string $body): array {
        // En production: intégrer avec SendGrid, Mailgun, etc.
        return [
            'success' => true,
            'message' => 'Email simulé envoyé',
            'to' => $to,
            'subject' => $subject,
            'preview' => substr($body, 0, 100) . '...'
        ];
    }
    
    /**
     * Simule l'envoi de SMS
     */
    private function simulateSMSSend(string $to, string $message): array {
        return [
            'success' => true,
            'message' => 'SMS simulé envoyé',
            'to' => $to,
            'preview' => substr($message, 0, 50) . '...'
        ];
    }
    
    /**
     * Simule l'envoi WhatsApp
     */
    private function simulateWhatsAppSend(string $to, string $message): array {
        return [
            'success' => true,
            'message' => 'WhatsApp simulé envoyé',
            'to' => $to,
            'preview' => substr($message, 0, 50) . '...'
        ];
    }
    
    /**
     * Log une notification
     */
    private function logNotification(string $type, string $to, string $template, array $result): void {
        $logFile = __DIR__ . '/../logs/notifications.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logEntry = json_encode([
            'timestamp' => date('c'),
            'type' => $type,
            'to' => $to,
            'template' => $template,
            'success' => $result['success'] ?? false
        ]) . "\n";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

/**
 * API endpoint
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'notification-service.php') {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?? [];
    
    $action = $data['action'] ?? '';
    $notificationService = new NotificationService();
    
    $response = ['success' => false, 'error' => 'Action non reconnue'];
    
    switch ($action) {
        case 'send_confirmation':
            $response = $notificationService->sendSubmissionConfirmation($data['application'] ?? []);
            break;
            
        case 'send_email':
            $response = $notificationService->sendEmail(
                $data['to'] ?? '',
                $data['template'] ?? '',
                $data['data'] ?? [],
                $data['lang'] ?? 'fr'
            );
            break;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

