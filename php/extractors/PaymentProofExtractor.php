<?php
/**
 * Extracteur de Preuve de Paiement
 * Ambassade de Côte d'Ivoire en Éthiopie
 *
 * Conforme au PRD: DOC_PAIEMENT
 * Nouveau - Comble le gap identifié dans l'audit
 *
 * @package VisaChatbot\Extractors
 * @version 1.0.0
 */

namespace VisaChatbot\Extractors;

require_once __DIR__ . '/AbstractExtractor.php';

class PaymentProofExtractor extends AbstractExtractor {

    /**
     * Montants attendus selon le PRD (en XOF)
     */
    public const EXPECTED_AMOUNTS = [
        'COURT_SEJOUR' => [
            'amount' => 73000,
            'currency' => 'XOF',
            'description' => 'Visa court séjour (1-3 mois)'
        ],
        'LONG_SEJOUR' => [
            'amount' => 120000,
            'currency' => 'XOF',
            'description' => 'Visa long séjour (3+ mois)'
        ],
        'TRANSIT' => [
            'amount' => 50000,
            'currency' => 'XOF',
            'description' => 'Visa transit'
        ],
        'AFFAIRES' => [
            'amount' => 73000,
            'currency' => 'XOF',
            'description' => 'Visa affaires'
        ]
    ];

    /**
     * Montants en ETB (Birr éthiopien) avec taux approximatif
     */
    public const ETB_AMOUNTS = [
        'COURT_SEJOUR' => 73000, // ~73,000 ETB approximativement
        'LONG_SEJOUR' => 120000,
        'TRANSIT' => 50000,
        'AFFAIRES' => 73000
    ];

    /**
     * Bénéficiaires valides
     */
    public const VALID_PAYEES = [
        'TRESOR PUBLIC COTE D\'IVOIRE',
        'TRESOR PUBLIC CI',
        'TRESOR CI',
        'AMBASSADE COTE D\'IVOIRE',
        'AMBASSADE CI ETHIOPIE',
        'EMBASSY OF COTE D\'IVOIRE'
    ];

    /**
     * Méthodes de paiement acceptées
     */
    public const PAYMENT_METHODS = [
        'VIREMENT' => ['VIREMENT', 'TRANSFER', 'WIRE TRANSFER', 'BANK TRANSFER'],
        'ESPECES' => ['ESPECES', 'CASH', 'NUMERAIRE', 'CAISSE'],
        'MOBILE_MONEY' => ['MOBILE MONEY', 'MTN MONEY', 'ORANGE MONEY', 'MOOV MONEY', 'WAVE', 'TELEBIRR', 'M-PESA'],
        'CARTE' => ['CARTE', 'CARD', 'VISA', 'MASTERCARD', 'DEBIT', 'CREDIT'],
        'CHEQUE' => ['CHEQUE', 'CHECK', 'CHEQUIER']
    ];

    protected array $requiredFields = [
        'amount',
        'date',
        'reference'
    ];

    protected array $optionalFields = [
        'currency',
        'payer',
        'payee',
        'payment_method',
        'bank_name',
        'transaction_id'
    ];

    /**
     * Extrait les données de la preuve de paiement
     */
    public function extract(string $rawText, array $ocrMetadata = []): array {
        $result = [
            'success' => false,
            'extracted' => [],
            'amount_analysis' => []
        ];

        $text = $this->cleanOcrText($rawText);

        // 1. Extraire le montant
        $amountData = $this->extractAmount($text);
        $result['extracted']['amount'] = $amountData['amount'] ?? null;
        $result['extracted']['currency'] = $amountData['currency'] ?? 'XOF';

        // 2. Extraire la date
        $result['extracted']['date'] = $this->extractPaymentDate($text);

        // 3. Extraire la référence de transaction
        $result['extracted']['reference'] = $this->extractReference($text);

        // 4. Extraire le nom du payeur
        $result['extracted']['payer'] = $this->extractPayer($text);

        // 5. Extraire le bénéficiaire
        $result['extracted']['payee'] = $this->extractPayee($text);

        // 6. Détecter la méthode de paiement
        $result['extracted']['payment_method'] = $this->detectPaymentMethod($text);

        // 7. Extraire le nom de la banque
        $result['extracted']['bank_name'] = $this->extractBankName($text);

        // 8. Extraire l'ID de transaction
        $result['extracted']['transaction_id'] = $this->extractTransactionId($text);

        // 9. Analyser le montant par rapport aux attentes
        $result['amount_analysis'] = $this->analyzeAmount(
            $result['extracted']['amount'],
            $result['extracted']['currency']
        );

        // 10. Succès si champs requis présents
        $result['success'] = $this->hasRequiredFields($result['extracted']);

        return $result;
    }

    /**
     * Extrait le montant et la devise
     */
    private function extractAmount(string $text): array {
        $result = ['amount' => null, 'currency' => null];

        // Patterns pour différentes formats de montant
        $patterns = [
            // Format bilingue: "Montant / Amount: 73,000 XOF"
            '/(?:MONTANT|AMOUNT)\s*(?:\/\s*(?:AMOUNT|MONTANT))?\s*:\s*([0-9,.\s]+)\s*(XOF|FCFA|CFA|ETB|EUR|USD)?/i',
            // Format simple: "AMOUNT: 73,000 XOF" ou "MONTANT: 73,000 XOF"
            '/(?:AMOUNT|MONTANT|TOTAL|SUM|SOMME)[:\s]+([0-9,.\s]+)\s*(XOF|FCFA|CFA|ETB|EUR|USD)?/i',
            // Format: XOF 73,000
            '/(XOF|FCFA|CFA|ETB|EUR|USD)\s*([0-9,.\s]+)/i',
            // Format avec mots-clés de paiement
            '/(?:PAID|PAYE|RECU)[:\s]*([0-9,.\s]+)\s*(XOF|FCFA|CFA|ETB|EUR|USD)?/i',
            // Montant seul avec devise (recherche de nombres significatifs)
            '/\b(\d{2,3}[,.\s]?\d{3})\s*(XOF|FCFA|CFA|ETB|EUR|USD)\b/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                // Déterminer quel groupe contient le montant
                if (preg_match('/^[A-Z]+$/', $match[1])) {
                    // Devise en premier
                    $result['currency'] = $this->normalizeCurrency($match[1]);
                    $result['amount'] = $this->parseAmountValue($match[2]);
                } else {
                    // Montant en premier
                    $result['amount'] = $this->parseAmountValue($match[1]);
                    if (isset($match[2]) && !empty($match[2])) {
                        $result['currency'] = $this->normalizeCurrency($match[2]);
                    }
                }
                if ($result['amount'] !== null) {
                    break;
                }
            }
        }

        // Devise par défaut si non trouvée
        if ($result['amount'] && !$result['currency']) {
            // Si le montant est proche de nos montants attendus en XOF, c'est probablement XOF
            if ($result['amount'] >= 50000 && $result['amount'] <= 150000) {
                $result['currency'] = 'XOF';
            } else {
                $result['currency'] = 'ETB'; // Devise locale en Éthiopie
            }
        }

        return $result;
    }

    /**
     * Parse la valeur du montant (nettoie les séparateurs)
     */
    private function parseAmountValue(string $amountStr): ?float {
        // Nettoyer les espaces et virgules
        $cleaned = preg_replace('/[\s,]/', '', $amountStr);
        // Gérer le point comme séparateur décimal
        $cleaned = str_replace(['.'], '', $cleaned);

        if (is_numeric($cleaned)) {
            return (float)$cleaned;
        }

        return null;
    }

    /**
     * Normalise la devise
     */
    private function normalizeCurrency(string $currency): string {
        $mapping = [
            'FCFA' => 'XOF',
            'CFA' => 'XOF',
            'F CFA' => 'XOF'
        ];

        $normalized = strtoupper(trim($currency));
        return $mapping[$normalized] ?? $normalized;
    }

    /**
     * Extrait la date de paiement
     */
    private function extractPaymentDate(string $text): ?string {
        $patterns = [
            '/(?:DATE|DATED|LE|DU)[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
            '/(?:PAYMENT|PAIEMENT)\s*(?:DATE)?[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
            '/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{4})/' // Fallback: première date trouvée
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return $this->parseDate($match[1]);
            }
        }

        return null;
    }

    /**
     * Extrait la référence de transaction
     */
    private function extractReference(string $text): ?string {
        $patterns = [
            '/(?:REFERENCE|REF|N°|NO)[:\s#]*([A-Z0-9\-\/]+)/i',
            '/(?:RECEIPT|RECU|QUITTANCE)\s*(?:N°|NO)?[:\s#]*([A-Z0-9\-]+)/i',
            '/(?:TRANSACTION|TXN|TRX)\s*(?:ID|N°)?[:\s#]*([A-Z0-9\-]+)/i',
            // Format typique: 20231215-123456
            '/\b(\d{8}[\-]\d{4,8})\b/',
            // Format alphanumérique
            '/\b([A-Z]{2,4}\d{6,12})\b/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return strtoupper($match[1]);
            }
        }

        return null;
    }

    /**
     * Extrait le nom du payeur
     */
    private function extractPayer(string $text): ?string {
        $patterns = [
            // Format bilingue: "Payeur / Payer: BEKELE ABEBE TESHOME"
            '/(?:PAYEUR|PAYER)\s*(?:\/\s*(?:PAYER|PAYEUR))?\s*:\s*([A-Z][A-Z\s\-\']+?)(?=\s+(?:Montant|Amount|Date|Objet|Purpose|Reference|$))/i',
            // Format simple: "PAYER: NAME"
            '/(?:PAYER|PAYEUR|FROM|CLIENT|CUSTOMER)\s*:\s*([A-Z][A-Z\s\-\']+?)(?=\s+(?:Montant|Amount|Date|$))/i',
            // Format avec titre
            '/(?:MR|MRS|MS|MME)[\.:\s]+([A-Z][A-Z\s\-\']+?)(?=\s+(?:Montant|Amount|Date|$))/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $name = trim($match[1]);
                // S'assurer que le nom ne contient pas de mots-clés parasites
                $name = preg_replace('/\s+(Montant|Amount|Date|Objet|Purpose).*$/i', '', $name);
                if (strlen($name) > 3) {
                    return $this->normalizeName($name);
                }
            }
        }

        return null;
    }

    /**
     * Extrait le bénéficiaire
     */
    private function extractPayee(string $text): ?string {
        // D'abord chercher directement les bénéficiaires valides (plus fiable)
        $textUpper = strtoupper($text);
        foreach (self::VALID_PAYEES as $payee) {
            if (strpos($textUpper, $payee) !== false) {
                return $payee;
            }
        }

        // Patterns pour extraction avec étiquette
        $patterns = [
            // Format bilingue
            '/(?:BENEFICIAIRE|PAYEE)\s*(?:\/\s*(?:PAYEE|BENEFICIAIRE))?\s*:\s*((?:TRESOR|AMBASSADE|EMBASSY)[A-Z\s\'\-]+?)(?=\s+(?:Methode|Method|Mode|Banque|Bank|Transaction|$))/i',
            // Format simple
            '/(?:BENEFICIAIRE|PAYEE|TO)[:\s]+((?:TRESOR|AMBASSADE|EMBASSY)[A-Z\s\'\-]+?)(?=\s+(?:Methode|Method|$))/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $payee = strtoupper(trim($match[1]));
                // Nettoyer les mots-clés parasites à la fin
                $payee = preg_replace('/\s+(METHODE|METHOD|MODE|BANQUE|BANK).*$/i', '', $payee);
                if (strlen($payee) > 5) {
                    return $payee;
                }
            }
        }

        return null;
    }

    /**
     * Détecte la méthode de paiement
     */
    private function detectPaymentMethod(string $text): ?string {
        $textUpper = strtoupper($text);

        foreach (self::PAYMENT_METHODS as $method => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($textUpper, $keyword) !== false) {
                    return $method;
                }
            }
        }

        return null;
    }

    /**
     * Extrait le nom de la banque
     */
    private function extractBankName(string $text): ?string {
        $banks = [
            // Banques éthiopiennes
            'COMMERCIAL BANK OF ETHIOPIA', 'CBE', 'DASHEN BANK', 'AWASH BANK',
            'ABYSSINIA BANK', 'UNITED BANK', 'NIB BANK', 'WEGAGEN BANK',
            // Banques internationales
            'ECOBANK', 'STANDARD CHARTERED', 'CITIBANK', 'BANK OF AFRICA',
            // Banques ivoiriennes
            'BGFI', 'SGBCI', 'BICICI', 'SIB', 'CORIS BANK', 'BIAO'
        ];

        $textUpper = strtoupper($text);
        foreach ($banks as $bank) {
            if (strpos($textUpper, $bank) !== false) {
                return $bank;
            }
        }

        // Pattern générique
        if (preg_match('/(?:BANK|BANQUE)[:\s]*([A-Z][A-Za-z\s]+)/i', $text, $match)) {
            return strtoupper(trim($match[1]));
        }

        return null;
    }

    /**
     * Extrait l'ID de transaction
     */
    private function extractTransactionId(string $text): ?string {
        $patterns = [
            '/(?:TRANSACTION\s*ID|TXN\s*ID|TRX)[:\s#]*([A-Z0-9\-]+)/i',
            '/(?:FT|TT)\d{10,}/i', // Format SWIFT
            '/\b([A-Z]{2}\d{12,16})\b/' // Format standard
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return strtoupper($match[0]);
            }
        }

        return null;
    }

    /**
     * Analyse le montant par rapport aux attentes
     */
    private function analyzeAmount(?float $amount, ?string $currency): array {
        $analysis = [
            'matches_expected' => false,
            'visa_type' => null,
            'expected_amount' => null,
            'difference' => null,
            'tolerance_percent' => 5
        ];

        if (!$amount) {
            return $analysis;
        }

        // Choisir la table de montants selon la devise
        $expectedAmounts = ($currency === 'XOF') ? self::EXPECTED_AMOUNTS : self::ETB_AMOUNTS;

        // Tolérance de 5%
        $tolerance = 0.05;

        foreach (self::EXPECTED_AMOUNTS as $visaType => $info) {
            $expected = $info['amount'];
            $diff = abs($amount - $expected);
            $diffPercent = ($diff / $expected) * 100;

            if ($diffPercent <= ($tolerance * 100)) {
                $analysis['matches_expected'] = true;
                $analysis['visa_type'] = $visaType;
                $analysis['expected_amount'] = $expected;
                $analysis['difference'] = $diff;
                $analysis['description'] = $info['description'];
                break;
            }
        }

        return $analysis;
    }

    /**
     * Vérifie si les champs requis sont présents
     */
    private function hasRequiredFields(array $data): bool {
        foreach ($this->requiredFields as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Valide les données extraites
     */
    public function validate(array $extracted): array {
        $validations = [];

        // Validation montant correspond aux attentes
        if (isset($extracted['amount'])) {
            $analysis = $this->analyzeAmount($extracted['amount'], $extracted['currency'] ?? 'XOF');
            $validations['amount_matches_expected'] = $analysis['matches_expected'];
        }

        // Validation date récente (moins de 30 jours)
        if (isset($extracted['date'])) {
            $paymentDate = strtotime($extracted['date']);
            $thirtyDaysAgo = strtotime('-30 days');
            $validations['date_is_recent'] = $paymentDate !== false && $paymentDate > $thirtyDaysAgo;
        }

        // Validation bénéficiaire = Trésor CI
        if (isset($extracted['payee'])) {
            $payeeUpper = strtoupper($extracted['payee']);
            $validations['payee_is_tresor_ci'] =
                strpos($payeeUpper, 'TRESOR') !== false ||
                strpos($payeeUpper, 'AMBASSADE') !== false ||
                strpos($payeeUpper, 'EMBASSY') !== false;
        }

        // Validation format référence
        if (isset($extracted['reference'])) {
            $validations['reference_format_valid'] = strlen($extracted['reference']) >= 6;
        }

        return $validations;
    }

    public function getDocumentType(): string {
        return 'payment';
    }

    public function getPrdCode(): string {
        return 'DOC_PAIEMENT';
    }
}
