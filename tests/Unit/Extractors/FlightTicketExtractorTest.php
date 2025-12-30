<?php
/**
 * Tests for FlightTicketExtractor
 *
 * @package VisaChatbot\Tests\Unit\Extractors
 */

namespace VisaChatbot\Tests\Unit\Extractors;

use PHPUnit\Framework\TestCase;
use VisaChatbot\Extractors\FlightTicketExtractor;

class FlightTicketExtractorTest extends TestCase
{
    private FlightTicketExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new FlightTicketExtractor();
    }

    // ==========================================
    // BASIC EXTRACTION
    // ==========================================

    public function test_extracts_flight_details(): void
    {
        $text = loadFixture('tickets/ethiopian_airlines_valid.txt');

        $result = $this->extractor->extract($text);

        $this->assertTrue($result['success']);
        $this->assertEquals('ET508', $result['extracted']['flight_number']);
        $this->assertEquals('ADD', $result['extracted']['departure_airport']);
        $this->assertEquals('ABJ', $result['extracted']['arrival_airport']);
    }

    public function test_extracts_passenger_name(): void
    {
        $text = loadFixture('tickets/ethiopian_airlines_valid.txt');

        $result = $this->extractor->extract($text);

        $this->assertStringContainsString('BEKELE', $result['extracted']['passenger_name']);
    }

    public function test_extracts_booking_reference(): void
    {
        $text = loadFixture('tickets/ethiopian_airlines_valid.txt');

        $result = $this->extractor->extract($text);

        $this->assertEquals('ABCDEF', $result['extracted']['booking_reference']);
    }

    // ==========================================
    // VALIDATION
    // ==========================================

    public function test_validates_destination_abidjan(): void
    {
        $text = loadFixture('tickets/ethiopian_airlines_valid.txt');
        $result = $this->extractor->extract($text);

        $validations = $this->extractor->validate($result['extracted']);

        $this->assertTrue($validations['destination_is_abidjan']);
    }

    public function test_validates_departure_in_jurisdiction(): void
    {
        $text = loadFixture('tickets/ethiopian_airlines_valid.txt');
        $result = $this->extractor->extract($text);

        $validations = $this->extractor->validate($result['extracted']);

        // ADD (Addis Ababa) is in Ethiopia, which is in jurisdiction
        $this->assertTrue($validations['departure_in_jurisdiction']);
    }

    public function test_validates_future_departure_date(): void
    {
        $text = loadFixture('tickets/ethiopian_airlines_valid.txt');
        $result = $this->extractor->extract($text);

        $validations = $this->extractor->validate($result['extracted']);

        $this->assertTrue($validations['date_is_future']);
    }

    // ==========================================
    // ROUND TRIP DETECTION
    // ==========================================

    public function test_detects_round_trip(): void
    {
        $text = loadFixture('tickets/round_trip.txt');

        $result = $this->extractor->extract($text);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_round_trip'] ?? false);
    }

    public function test_extracts_return_flight_details(): void
    {
        $text = loadFixture('tickets/round_trip.txt');

        $result = $this->extractor->extract($text);

        // Should have return flight info
        $this->assertNotEmpty($result['extracted']['return_date'] ?? $result['flights'][1]['departure_date'] ?? null);
    }

    // ==========================================
    // EDGE CASES
    // ==========================================

    public function test_handles_empty_input(): void
    {
        $result = $this->extractor->extract('');

        $this->assertFalse($result['success']);
    }

    /**
     * @dataProvider airlineCodeProvider
     */
    public function test_recognizes_airline_codes(string $code, string $airline): void
    {
        $text = "Flight: {$code}123\nFrom: ADD To: ABJ\nDate: 15 JAN 2025\nPassenger: TEST/USER";

        $result = $this->extractor->extract($text);

        $this->assertEquals("{$code}123", $result['extracted']['flight_number']);
    }

    public static function airlineCodeProvider(): array
    {
        return [
            'Ethiopian Airlines' => ['ET', 'Ethiopian Airlines'],
            'Kenya Airways' => ['KQ', 'Kenya Airways'],
            'Turkish Airlines' => ['TK', 'Turkish Airlines'],
            'Air France' => ['AF', 'Air France'],
            'Emirates' => ['EK', 'Emirates'],
        ];
    }

    /**
     * @dataProvider jurisdictionAirportsProvider
     */
    public function test_jurisdiction_airport_detection(string $airport, bool $inJurisdiction): void
    {
        $extracted = [
            'departure_airport' => $airport,
            'arrival_airport' => 'ABJ',
            'departure_date' => date('Y-m-d', strtotime('+30 days')),
            'passenger_name' => 'TEST USER',
            'flight_number' => 'XX123'
        ];

        $validations = $this->extractor->validate($extracted);

        $this->assertEquals($inJurisdiction, $validations['departure_in_jurisdiction']);
    }

    public static function jurisdictionAirportsProvider(): array
    {
        return [
            'Addis Ababa in jurisdiction' => ['ADD', true],
            'Nairobi in jurisdiction' => ['NBO', true],
            'Entebbe in jurisdiction' => ['EBB', true],
            'Djibouti in jurisdiction' => ['JIB', true],
            'Paris not in jurisdiction' => ['CDG', false],
            'Dubai not in jurisdiction' => ['DXB', false],
        ];
    }

    // ==========================================
    // PRD COMPLIANCE
    // ==========================================

    public function test_returns_correct_document_type(): void
    {
        $this->assertEquals('ticket', $this->extractor->getDocumentType());
    }

    public function test_returns_correct_prd_code(): void
    {
        $this->assertEquals('DOC_BILLET', $this->extractor->getPrdCode());
    }
}
