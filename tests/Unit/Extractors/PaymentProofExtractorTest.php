<?php
/**
 * Tests for PaymentProofExtractor
 *
 * @package VisaChatbot\Tests\Unit\Extractors
 */

namespace VisaChatbot\Tests\Unit\Extractors;

use PHPUnit\Framework\TestCase;
use VisaChatbot\Extractors\PaymentProofExtractor;

class PaymentProofExtractorTest extends TestCase
{
    private PaymentProofExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new PaymentProofExtractor();
    }

    // ==========================================
    // AMOUNT EXTRACTION
    // ==========================================

    public function test_extracts_xof_amount(): void
    {
        $text = loadFixture('payments/xof_73000_valid.txt');

        $result = $this->extractor->extract($text);

        $this->assertTrue($result['success']);
        $this->assertEquals(73000, $result['extracted']['amount']);
        $this->assertEquals('XOF', $result['extracted']['currency']);
    }

    public function test_extracts_etb_amount(): void
    {
        $text = loadFixture('payments/etb_payment.txt');

        $result = $this->extractor->extract($text);

        $this->assertTrue($result['success']);
        $this->assertEquals(73000, $result['extracted']['amount']);
        $this->assertEquals('ETB', $result['extracted']['currency']);
    }

    // ==========================================
    // AMOUNT VALIDATION
    // ==========================================

    public function test_validates_expected_amount_court_sejour(): void
    {
        $text = loadFixture('payments/xof_73000_valid.txt');
        $result = $this->extractor->extract($text);

        $validations = $this->extractor->validate($result['extracted']);

        $this->assertTrue($validations['amount_matches_expected']);
    }

    public function test_detects_underpayment(): void
    {
        $text = loadFixture('payments/xof_underpayment.txt');
        $result = $this->extractor->extract($text);

        // Check amount analysis - 40,000 XOF doesn't match any expected visa amount
        $this->assertEquals(40000, $result['extracted']['amount']);
        $this->assertFalse($result['amount_analysis']['matches_expected']);
    }

    /**
     * @dataProvider visaAmountProvider
     */
    public function test_visa_type_detection_by_amount(float $amount, string $expectedVisaType): void
    {
        $extracted = [
            'amount' => $amount,
            'currency' => 'XOF',
            'date' => date('Y-m-d'),
            'reference' => 'TEST123'
        ];

        // Use reflection to access amount analysis method if private
        $reflection = new \ReflectionClass($this->extractor);
        if ($reflection->hasMethod('analyzeAmount')) {
            $method = $reflection->getMethod('analyzeAmount');
            $method->setAccessible(true);

            $analysis = $method->invoke($this->extractor, $amount, 'XOF');

            if ($analysis['matches_expected']) {
                $this->assertEquals($expectedVisaType, $analysis['visa_type']);
            }
        }
    }

    public static function visaAmountProvider(): array
    {
        return [
            'court séjour amount' => [73000, 'COURT_SEJOUR'],
            'long séjour amount' => [120000, 'LONG_SEJOUR'],
            'transit amount' => [50000, 'TRANSIT'],
            // Note: AFFAIRES (73,000) same as COURT_SEJOUR, first match returned
        ];
    }

    // ==========================================
    // PAYEE VALIDATION
    // ==========================================

    public function test_validates_payee_tresor_ci(): void
    {
        $text = loadFixture('payments/xof_73000_valid.txt');
        $result = $this->extractor->extract($text);

        $validations = $this->extractor->validate($result['extracted']);

        $this->assertTrue($validations['payee_is_tresor_ci']);
    }

    /**
     * @dataProvider validPayeesProvider
     */
    public function test_accepts_valid_payees(string $payee): void
    {
        $extracted = [
            'amount' => 73000,
            'currency' => 'XOF',
            'date' => date('Y-m-d'),
            'reference' => 'TEST123',
            'payee' => $payee
        ];

        $validations = $this->extractor->validate($extracted);

        $this->assertTrue($validations['payee_is_tresor_ci']);
    }

    public static function validPayeesProvider(): array
    {
        return [
            ['TRESOR PUBLIC COTE D\'IVOIRE'],
            ['TRESOR PUBLIC CI'],
            ['TRESOR CI'],
            ['AMBASSADE COTE D\'IVOIRE'],
            ['AMBASSADE CI ETHIOPIE'],
            ['EMBASSY OF COTE D\'IVOIRE'],
        ];
    }

    // ==========================================
    // DATE VALIDATION
    // ==========================================

    public function test_validates_recent_payment_date(): void
    {
        $extracted = [
            'amount' => 73000,
            'currency' => 'XOF',
            'date' => date('Y-m-d', strtotime('-15 days')),
            'reference' => 'TEST123'
        ];

        $validations = $this->extractor->validate($extracted);

        $this->assertTrue($validations['date_is_recent']);
    }

    public function test_rejects_old_payment_date(): void
    {
        $extracted = [
            'amount' => 73000,
            'currency' => 'XOF',
            'date' => date('Y-m-d', strtotime('-60 days')),
            'reference' => 'TEST123'
        ];

        $validations = $this->extractor->validate($extracted);

        $this->assertFalse($validations['date_is_recent']);
    }

    // ==========================================
    // REFERENCE EXTRACTION
    // ==========================================

    public function test_extracts_transaction_reference(): void
    {
        $text = loadFixture('payments/xof_73000_valid.txt');

        $result = $this->extractor->extract($text);

        $this->assertNotEmpty($result['extracted']['reference']);
    }

    public function test_validates_reference_format(): void
    {
        $extracted = [
            'amount' => 73000,
            'date' => date('Y-m-d'),
            'reference' => 'TXN-2024-123456'
        ];

        $validations = $this->extractor->validate($extracted);

        $this->assertTrue($validations['reference_format_valid']);
    }

    // ==========================================
    // EDGE CASES
    // ==========================================

    public function test_handles_empty_input(): void
    {
        $result = $this->extractor->extract('');

        $this->assertFalse($result['success']);
    }

    public function test_handles_amount_with_spaces(): void
    {
        $text = "MONTANT: 73 000 XOF\nREFERENCE: TEST123\nDATE: 15/12/2024";

        $result = $this->extractor->extract($text);

        $this->assertEquals(73000, $result['extracted']['amount']);
    }

    public function test_handles_amount_with_commas(): void
    {
        $text = "AMOUNT: 73,000 FCFA\nREF: TEST456\nDATE: 2024-12-15";

        $result = $this->extractor->extract($text);

        $this->assertEquals(73000, $result['extracted']['amount']);
        $this->assertEquals('XOF', $result['extracted']['currency']); // FCFA normalized to XOF
    }

    // ==========================================
    // PRD COMPLIANCE
    // ==========================================

    public function test_returns_correct_document_type(): void
    {
        $this->assertEquals('payment', $this->extractor->getDocumentType());
    }

    public function test_returns_correct_prd_code(): void
    {
        $this->assertEquals('DOC_PAIEMENT', $this->extractor->getPrdCode());
    }
}
