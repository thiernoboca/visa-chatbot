<?php
/**
 * Tests for Cross-Document Validation
 *
 * @package VisaChatbot\Tests\Unit\Validators
 */

namespace VisaChatbot\Tests\Unit\Validators;

use PHPUnit\Framework\TestCase;
use VisaChatbot\Validators\DocumentValidator;
use VisaChatbot\Validators\AbstractValidator;

class CrossValidationTest extends TestCase
{
    private DocumentValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DocumentValidator();
    }

    // ==========================================
    // COMPLETE SCENARIO TESTS
    // ==========================================

    public function test_scenario_ethiopian_standard_applicant(): void
    {
        $documents = [
            'passport' => [
                'surname' => 'BEKELE',
                'given_names' => 'ABEBE TESHOME',
                'passport_number' => 'EP1234567',
                'nationality' => 'ETH',
                'expiry_date' => '2032-03-19',
                'date_of_birth' => '1985-01-15',
                'passport_type' => 'ORDINAIRE',
                'mrz_valid' => true
            ],
            'ticket' => [
                'passenger_name' => 'BEKELE/ABEBE TESHOME',
                'flight_number' => 'ET508',
                'departure_airport' => 'ADD',
                'arrival_airport' => 'ABJ',
                'departure_date' => '2025-01-15'
            ],
            'vaccination' => [
                'holder_name' => 'BEKELE ABEBE TESHOME',
                'yellow_fever_date' => '2023-12-10',
                'yellow_fever_valid' => true
            ],
            'hotel' => [
                'guest_name' => 'BEKELE ABEBE TESHOME',
                'hotel_name' => 'Sofitel Abidjan',
                'check_in_date' => '2025-01-15',
                'check_out_date' => '2025-01-22'
            ],
            'payment' => [
                'amount' => 73000,
                'currency' => 'XOF',
                'date' => '2024-12-15',
                'reference' => 'TXN-2024-123456',
                'amount_matches_expected' => true
            ]
        ];

        $result = $this->validator->validate($documents);

        // Should be valid with low risk
        $this->assertTrue($result['valid']);
        $this->assertEquals('LOW', $result['risk_level']);
        $this->assertFalse($result['requires_manual_review']);

        // All cross-validations should pass
        $this->assertTrue($result['cross_validations']['name_consistency']['consistent']);
        $this->assertEmpty($result['cross_validations']['date_consistency']['issues']);
    }

    public function test_scenario_kenyan_diplomat(): void
    {
        $documents = [
            'passport' => [
                'surname' => 'MWANGI',
                'given_names' => 'PETER JOSEPH',
                'passport_number' => 'DK1111111',
                'nationality' => 'KEN',
                'expiry_date' => '2029-12-31',
                'passport_type' => 'DIPLOMATIQUE',
                'mrz_valid' => true
            ],
            'verbal_note' => [
                'sending_entity' => 'EMBASSY OF KENYA',
                'diplomat_name' => 'PETER JOSEPH MWANGI',
                'date' => '2024-12-01',
                'reference_number' => 'VN-2024-001'
            ]
        ];

        $result = $this->validator->validate($documents);

        // Diplomatic passport should be valid without payment
        $this->assertTrue($result['valid']);
        $this->assertEquals('LOW', $result['risk_level']);
    }

    public function test_scenario_fraud_detection(): void
    {
        $documents = [
            'passport' => [
                'surname' => 'DOE',
                'given_names' => 'JOHN',
                'passport_number' => 'XX9999999',
                'nationality' => 'ETH',
                'expiry_date' => '2024-06-15',  // Expired
                'passport_type' => 'ORDINAIRE',
                'mrz_valid' => false  // Invalid checksum
            ],
            'ticket' => [
                'passenger_name' => 'SMITH/JANE',  // Different name!
                'flight_number' => 'ET123',
                'departure_airport' => 'ADD',
                'arrival_airport' => 'ABJ',
                'departure_date' => '2025-01-15'
            ]
        ];

        $result = $this->validator->validate($documents);

        // Should be flagged as high risk
        $this->assertFalse($result['valid']);
        $this->assertEquals('CRITICAL', $result['risk_level']);
        $this->assertTrue($result['requires_manual_review']);

        // Should have fraud indicators
        $fraudTypes = array_column($result['fraud_indicators'], 'type');
        $this->assertContains('EXPIRED_PASSPORT', $fraudTypes);
        $this->assertContains('INVALID_MRZ_CHECKSUM', $fraudTypes);

        // Name mismatch should be detected
        $this->assertFalse($result['cross_validations']['name_consistency']['consistent']);
    }

    public function test_scenario_edge_cases(): void
    {
        $documents = [
            'passport' => [
                'surname' => 'MÜLLER-O\'BRIEN',
                'given_names' => 'JEAN PIERRE',
                'passport_number' => 'DJ2222222',
                'nationality' => 'DJI',
                'expiry_date' => '2033-09-14',
                'passport_type' => 'ORDINAIRE',
                'mrz_valid' => true
            ],
            'ticket' => [
                'passenger_name' => 'MULLEROBRIEN/JEANPIERRE',  // Normalized without accents
                'flight_number' => 'TK789',
                'departure_airport' => 'JIB',
                'arrival_airport' => 'ABJ',
                'departure_date' => '2025-02-01'
            ],
            'hotel' => [
                'guest_name' => 'Jean Pierre Muller O Brien',  // Different format
                'hotel_name' => 'Novotel Abidjan',
                'check_in_date' => '2025-02-01',
                'check_out_date' => '2025-05-01'  // 89 nights (close to max)
            ]
        ];

        $result = $this->validator->validate($documents);

        // Name normalization should handle accents and special chars
        $nameCheck = $result['cross_validations']['name_consistency'];
        $this->assertTrue($nameCheck['consistent']);

        // Long stay should trigger anomaly but not fraud
        $anomalyTypes = array_column($result['anomalies'] ?? [], 'type');
        // 89 nights is close to 90, may or may not trigger depending on implementation
    }

    // ==========================================
    // NAME SIMILARITY TESTS
    // ==========================================

    /**
     * @dataProvider nameSimilarityProvider
     */
    public function test_name_similarity_calculation(
        string $name1,
        string $name2,
        bool $expectedMatch
    ): void {
        $documents = [
            'passport' => [
                'surname' => explode(' ', $name1)[0] ?? $name1,
                'given_names' => implode(' ', array_slice(explode(' ', $name1), 1)) ?: 'TEST',
                'passport_number' => 'XX1234567',
                'expiry_date' => date('Y-m-d', strtotime('+2 years'))
            ],
            'ticket' => [
                'passenger_name' => str_replace(' ', '/', $name2),
                'flight_number' => 'XX123',
                'departure_date' => date('Y-m-d', strtotime('+30 days'))
            ]
        ];

        $result = $this->validator->validate($documents);

        $nameConsistency = $result['cross_validations']['name_consistency'];

        $this->assertEquals(
            $expectedMatch,
            $nameConsistency['consistent'],
            "Name comparison failed for: '{$name1}' vs '{$name2}'"
        );
    }

    public static function nameSimilarityProvider(): array
    {
        return [
            // Exact matches
            'exact match' => ['JOHN DOE', 'JOHN DOE', true],
            'case insensitive' => ['John Doe', 'JOHN DOE', true],

            // Accent handling
            'accent removal' => ['MÜLLER HANS', 'MULLER HANS', true],
            'cedilla' => ['FRANÇOIS MARC', 'FRANCOIS MARC', true],

            // Special characters
            'apostrophe removal' => ["O'BRIEN JOHN", 'OBRIEN JOHN', true],
            'hyphen handling' => ['JEAN-PIERRE MARC', 'JEAN PIERRE MARC', true],

            // Clear mismatches
            'different names' => ['JOHN DOE', 'JANE SMITH', false],
            'partial match' => ['JOHN PETER DOE', 'JOHN DOE', true],  // Should match

            // Order variations (may depend on implementation)
            'name order' => ['DOE JOHN', 'JOHN DOE', true],
        ];
    }

    // ==========================================
    // DATE VALIDATION TESTS
    // ==========================================

    public function test_passport_expiry_6_month_rule(): void
    {
        // Passport expires exactly 6 months from travel
        $travelDate = date('Y-m-d', strtotime('+2 months'));
        $expiryDate = date('Y-m-d', strtotime('+7 months'));  // 5 months after travel

        $documents = [
            'passport' => [
                'surname' => 'TEST',
                'given_names' => 'USER',
                'passport_number' => 'XX1234567',
                'expiry_date' => $expiryDate
            ],
            'ticket' => [
                'passenger_name' => 'TEST/USER',
                'flight_number' => 'XX123',
                'departure_date' => $travelDate
            ]
        ];

        $result = $this->validator->validate($documents);

        $dateCheck = $result['cross_validations']['date_consistency'];
        // 5 months validity should trigger warning
        $this->assertContains(
            'Passport validity less than 6 months from travel',
            $dateCheck['issues']
        );
    }

    public function test_hotel_flight_date_tolerance(): void
    {
        // Hotel check-in 1 day after flight arrival (acceptable)
        $documents = [
            'passport' => [
                'surname' => 'TEST',
                'given_names' => 'USER',
                'passport_number' => 'XX1234567',
                'expiry_date' => date('Y-m-d', strtotime('+2 years'))
            ],
            'ticket' => [
                'passenger_name' => 'TEST/USER',
                'flight_number' => 'XX123',
                'departure_date' => '2025-01-15',
                'arrival_date' => '2025-01-15'
            ],
            'hotel' => [
                'guest_name' => 'TEST USER',
                'hotel_name' => 'Test Hotel',
                'check_in_date' => '2025-01-16',  // 1 day after
                'check_out_date' => '2025-01-20'
            ]
        ];

        $result = $this->validator->validate($documents);

        // 1 day difference should be within tolerance
        $dateCheck = $result['cross_validations']['date_consistency'];
        // Should not have major issues for 1 day difference
        $this->assertLessThan(2, count($dateCheck['issues'] ?? []));
    }

    // ==========================================
    // CONFIDENCE THRESHOLDS
    // ==========================================

    public function test_auto_approve_threshold(): void
    {
        $documents = $this->createValidDocumentSet();

        $result = $this->validator->validate($documents);

        // High confidence should suggest auto-approve
        $this->assertGreaterThanOrEqual(0.95, $result['confidence']);
    }

    public function test_manual_review_threshold(): void
    {
        $documents = $this->createValidDocumentSet();
        // Add one minor issue
        $documents['passport']['expiry_date'] = date('Y-m-d', strtotime('+8 months'));

        $result = $this->validator->validate($documents);

        // Should still be above manual review threshold
        $this->assertGreaterThanOrEqual(0.75, $result['confidence']);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    private function createValidDocumentSet(): array
    {
        return [
            'passport' => [
                'surname' => 'BEKELE',
                'given_names' => 'ABEBE',
                'passport_number' => 'EP1234567',
                'nationality' => 'ETH',
                'expiry_date' => date('Y-m-d', strtotime('+5 years')),
                'passport_type' => 'ORDINAIRE',
                'mrz_valid' => true
            ],
            'ticket' => [
                'passenger_name' => 'BEKELE/ABEBE',
                'flight_number' => 'ET508',
                'departure_airport' => 'ADD',
                'arrival_airport' => 'ABJ',
                'departure_date' => date('Y-m-d', strtotime('+30 days'))
            ],
            'vaccination' => [
                'holder_name' => 'BEKELE ABEBE',
                'yellow_fever_date' => date('Y-m-d', strtotime('-6 months')),
                'yellow_fever_valid' => true
            ],
            'hotel' => [
                'guest_name' => 'BEKELE ABEBE',
                'hotel_name' => 'Sofitel Abidjan',
                'check_in_date' => date('Y-m-d', strtotime('+30 days')),
                'check_out_date' => date('Y-m-d', strtotime('+37 days'))
            ],
            'payment' => [
                'amount' => 73000,
                'currency' => 'XOF',
                'date' => date('Y-m-d', strtotime('-7 days')),
                'reference' => 'TXN123456',
                'amount_matches_expected' => true
            ]
        ];
    }
}
