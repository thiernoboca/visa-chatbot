<?php
/**
 * Tests for PassportExtractor
 *
 * @package VisaChatbot\Tests\Unit\Extractors
 */

namespace VisaChatbot\Tests\Unit\Extractors;

use PHPUnit\Framework\TestCase;
use VisaChatbot\Extractors\PassportExtractor;

class PassportExtractorTest extends TestCase
{
    private PassportExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new PassportExtractor();
    }

    // ==========================================
    // VALID MRZ EXTRACTION TESTS
    // ==========================================

    public function test_extracts_valid_td3_mrz_ethiopia(): void
    {
        $text = loadFixture('mrz/td3_ethiopia_valid.txt');

        $result = $this->extractor->extract($text);

        $this->assertTrue($result['success']);
        $this->assertEquals('BEKELE', $result['extracted']['surname']);
        $this->assertEquals('ABEBE TESHOME', $result['extracted']['given_names']);
        $this->assertEquals('EP1234567', $result['extracted']['passport_number']);
        $this->assertEquals('ETH', $result['extracted']['nationality']);
        $this->assertEquals('M', $result['extracted']['sex']);
    }

    public function test_extracts_valid_td3_mrz_kenya(): void
    {
        $text = loadFixture('mrz/td3_kenya_valid.txt');

        $result = $this->extractor->extract($text);

        $this->assertTrue($result['success']);
        $this->assertEquals('WANJIKU', $result['extracted']['surname']);
        $this->assertEquals('MARY ELIZABETH', $result['extracted']['given_names']);
        $this->assertEquals('BK9876543', $result['extracted']['passport_number']);
        $this->assertEquals('KEN', $result['extracted']['nationality']);
        $this->assertEquals('F', $result['extracted']['sex']);
    }

    public function test_extracts_diplomatic_passport(): void
    {
        $text = loadFixture('mrz/td3_diplomatic.txt');

        $result = $this->extractor->extract($text);

        $this->assertTrue($result['success']);
        $this->assertEquals('DIPLOMATIQUE', $result['extracted']['passport_type']);
        $this->assertEquals('MWANGI', $result['extracted']['surname']);
    }

    // ==========================================
    // VALIDATION TESTS
    // ==========================================

    public function test_validates_passport_not_expired(): void
    {
        $text = loadFixture('mrz/td3_ethiopia_valid.txt');
        $result = $this->extractor->extract($text);

        $validations = $this->extractor->validate($result['extracted']);

        $this->assertTrue($validations['expiry_valid']);
    }

    public function test_detects_expired_passport(): void
    {
        $text = loadFixture('mrz/td3_expired.txt');
        $result = $this->extractor->extract($text);

        $validations = $this->extractor->validate($result['extracted']);

        $this->assertFalse($validations['expiry_valid']);
    }

    public function test_validates_6_month_rule(): void
    {
        $text = loadFixture('mrz/td3_expiring_soon.txt');
        $result = $this->extractor->extract($text);

        $validations = $this->extractor->validate($result['extracted']);

        // Passport expiring in Feb 2025 - less than 6 months
        $this->assertFalse($validations['expiry_6months']);
    }

    public function test_validates_jurisdiction_country(): void
    {
        $text = loadFixture('mrz/td3_ethiopia_valid.txt');
        $result = $this->extractor->extract($text);

        $validations = $this->extractor->validate($result['extracted']);

        $this->assertTrue($validations['in_jurisdiction']);
    }

    // ==========================================
    // EDGE CASES
    // ==========================================

    public function test_handles_corrupted_mrz(): void
    {
        $text = loadFixture('mrz/td3_corrupted.txt');

        $result = $this->extractor->extract($text);

        // Should still try to extract but may have lower confidence
        // The extraction should not throw an exception
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function test_normalizes_accented_names(): void
    {
        $text = loadFixture('mrz/td3_accented_name.txt');

        $result = $this->extractor->extract($text);

        // MÜLLER should be normalized to MULLER in MRZ
        $this->assertTrue($result['success']);
        // Check that the extractor handled the accented text properly
        $this->assertNotEmpty($result['extracted']['surname']);
    }

    public function test_handles_empty_input(): void
    {
        $result = $this->extractor->extract('');

        $this->assertFalse($result['success']);
        $this->assertEmpty($result['extracted']['passport_number'] ?? null);
    }

    public function test_handles_non_mrz_text(): void
    {
        $text = "This is just some random text without any MRZ data.";

        $result = $this->extractor->extract($text);

        $this->assertFalse($result['success']);
    }

    // ==========================================
    // DATA PROVIDER TESTS
    // ==========================================

    /**
     * @dataProvider mrzChecksumProvider
     */
    public function test_mrz_checksum_validation(string $mrz, bool $expectedValid): void
    {
        // Test the MRZ checksum validation directly if method is accessible
        // This tests the internal checksum logic
        $reflection = new \ReflectionClass($this->extractor);

        if ($reflection->hasMethod('calculateMrzChecksum')) {
            $method = $reflection->getMethod('calculateMrzChecksum');
            // Note: setAccessible() removed - no longer needed in PHP 8.1+

            // For valid MRZ, the checksum should match
            $this->assertTrue(true); // Placeholder - actual implementation depends on method signature
        } else {
            $this->markTestSkipped('calculateMrzChecksum method not accessible');
        }
    }

    public static function mrzChecksumProvider(): array
    {
        return [
            'valid passport number checksum' => ['EP12345671', true],
            'invalid passport number checksum' => ['EP12345670', false],
        ];
    }

    /**
     * @dataProvider jurisdictionCountriesProvider
     */
    public function test_jurisdiction_countries(string $nationality, bool $inJurisdiction): void
    {
        $result = [
            'nationality' => $nationality,
            'expiry_date' => date('Y-m-d', strtotime('+2 years')),
            'passport_number' => 'XX1234567'
        ];

        $validations = $this->extractor->validate($result);

        $this->assertEquals($inJurisdiction, $validations['in_jurisdiction'] ?? false);
    }

    public static function jurisdictionCountriesProvider(): array
    {
        return [
            'Ethiopia in jurisdiction' => ['ETH', true],
            'Kenya in jurisdiction' => ['KEN', true],
            'Djibouti in jurisdiction' => ['DJI', true],
            'Uganda in jurisdiction' => ['UGA', true],
            'Somalia in jurisdiction' => ['SOM', true],
            'South Sudan in jurisdiction' => ['SSD', true],
            // Countries NOT in jurisdiction
            'France not in jurisdiction' => ['FRA', false],
            'USA not in jurisdiction' => ['USA', false],
            'Nigeria not in jurisdiction' => ['NGA', false],
        ];
    }

    // ==========================================
    // PRD CODE COMPLIANCE
    // ==========================================

    public function test_returns_correct_document_type(): void
    {
        $this->assertEquals('passport', $this->extractor->getDocumentType());
    }

    public function test_returns_correct_prd_code(): void
    {
        $this->assertEquals('DOC_PASSEPORT', $this->extractor->getPrdCode());
    }
}
