<?php
/**
 * Tests for DocumentValidator
 *
 * @package VisaChatbot\Tests\Unit\Validators
 */

namespace VisaChatbot\Tests\Unit\Validators;

use PHPUnit\Framework\TestCase;
use VisaChatbot\Validators\DocumentValidator;

class DocumentValidatorTest extends TestCase
{
    private DocumentValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DocumentValidator();
    }

    // ==========================================
    // NAME CONSISTENCY TESTS
    // ==========================================

    public function test_detects_name_mismatch(): void
    {
        $documents = [
            'passport' => [
                'surname' => 'DOE',
                'given_names' => 'JOHN',
                'passport_number' => 'AB1234567',
                'expiry_date' => date('Y-m-d', strtotime('+2 years'))
            ],
            'ticket' => [
                'passenger_name' => 'SMITH/JOHN',  // Different surname!
                'flight_number' => 'ET123',
                'departure_date' => date('Y-m-d', strtotime('+30 days'))
            ]
        ];

        $result = $this->validator->validate($documents);

        $this->assertFalse($result['cross_validations']['name_consistency']['consistent'] ?? true);
    }

    public function test_accepts_matching_names(): void
    {
        $documents = [
            'passport' => [
                'surname' => 'BEKELE',
                'given_names' => 'ABEBE',
                'passport_number' => 'EP1234567',
                'expiry_date' => date('Y-m-d', strtotime('+2 years'))
            ],
            'ticket' => [
                'passenger_name' => 'BEKELE/ABEBE',
                'flight_number' => 'ET508',
                'departure_date' => date('Y-m-d', strtotime('+30 days'))
            ]
        ];

        $result = $this->validator->validate($documents);

        $this->assertTrue($result['cross_validations']['name_consistency']['consistent'] ?? false);
    }

    public function test_accepts_name_variations(): void
    {
        $documents = [
            'passport' => [
                'surname' => "O'BRIEN",
                'given_names' => 'JOHN PATRICK',
                'passport_number' => 'AB1234567',
                'expiry_date' => date('Y-m-d', strtotime('+2 years'))
            ],
            'ticket' => [
                'passenger_name' => 'OBRIEN/JOHNPATRICK',  // Without apostrophe and space
                'flight_number' => 'ET123',
                'departure_date' => date('Y-m-d', strtotime('+30 days'))
            ]
        ];

        $result = $this->validator->validate($documents);

        // Should recognize as same person with reasonable confidence
        $nameCheck = $result['cross_validations']['name_consistency'] ?? [];
        $this->assertTrue($nameCheck['consistent'] ?? false);
    }

    // ==========================================
    // DATE CONSISTENCY TESTS
    // ==========================================

    public function test_validates_passport_expiry_vs_travel(): void
    {
        $documents = [
            'passport' => [
                'surname' => 'TEST',
                'given_names' => 'USER',
                'passport_number' => 'AB1234567',
                'expiry_date' => date('Y-m-d', strtotime('+3 months'))  // Less than 6 months
            ],
            'ticket' => [
                'passenger_name' => 'TEST/USER',
                'flight_number' => 'ET123',
                'departure_date' => date('Y-m-d', strtotime('+1 month'))
            ]
        ];

        $result = $this->validator->validate($documents);

        $dateCheck = $result['cross_validations']['date_consistency'] ?? [];
        $this->assertContains(
            'Passport validity less than 6 months from travel',
            $dateCheck['issues'] ?? []
        );
    }

    public function test_validates_hotel_vs_flight_dates(): void
    {
        $documents = [
            'passport' => [
                'surname' => 'TEST',
                'given_names' => 'USER',
                'passport_number' => 'AB1234567',
                'expiry_date' => date('Y-m-d', strtotime('+2 years'))
            ],
            'ticket' => [
                'passenger_name' => 'TEST/USER',
                'flight_number' => 'ET123',
                'departure_date' => '2025-01-15',
                'arrival_date' => '2025-01-15'
            ],
            'hotel' => [
                'guest_name' => 'TEST USER',
                'check_in_date' => '2025-01-20',  // 5 days after flight arrival!
                'check_out_date' => '2025-01-25',
                'hotel_name' => 'Test Hotel'
            ]
        ];

        $result = $this->validator->validate($documents);

        $dateCheck = $result['cross_validations']['date_consistency'] ?? [];
        // Should flag the mismatch
        $this->assertNotEmpty($dateCheck['issues'] ?? []);
    }

    // ==========================================
    // FRAUD DETECTION TESTS
    // ==========================================

    public function test_detects_expired_passport_fraud(): void
    {
        $documents = [
            'passport' => [
                'surname' => 'FRAUD',
                'given_names' => 'TEST',
                'passport_number' => 'XX9999999',
                'expiry_date' => date('Y-m-d', strtotime('-1 year'))  // Expired!
            ]
        ];

        $result = $this->validator->validate($documents);

        $fraudIndicators = array_column($result['fraud_indicators'] ?? [], 'type');
        $this->assertContains('EXPIRED_PASSPORT', $fraudIndicators);
    }

    public function test_detects_invalid_mrz_checksum(): void
    {
        $documents = [
            'passport' => [
                'surname' => 'TEST',
                'given_names' => 'USER',
                'passport_number' => 'AB1234567',
                'expiry_date' => date('Y-m-d', strtotime('+2 years')),
                'mrz_valid' => false  // Invalid MRZ checksum
            ]
        ];

        $result = $this->validator->validate($documents);

        $fraudIndicators = array_column($result['fraud_indicators'] ?? [], 'type');
        $this->assertContains('INVALID_MRZ_CHECKSUM', $fraudIndicators);
    }

    public function test_detects_missing_yellow_fever(): void
    {
        $documents = [
            'passport' => [
                'surname' => 'TEST',
                'given_names' => 'USER',
                'passport_number' => 'AB1234567',
                'expiry_date' => date('Y-m-d', strtotime('+2 years'))
            ],
            'vaccination' => [
                'holder_name' => 'TEST USER',
                'yellow_fever_date' => null,  // Missing!
                'yellow_fever_valid' => false
            ]
        ];

        $result = $this->validator->validate($documents);

        $fraudIndicators = array_column($result['fraud_indicators'] ?? [], 'type');
        $this->assertContains('INVALID_YELLOW_FEVER', $fraudIndicators);
    }

    public function test_detects_wrong_payment_amount(): void
    {
        $documents = [
            'passport' => [
                'surname' => 'TEST',
                'given_names' => 'USER',
                'passport_number' => 'AB1234567',
                'expiry_date' => date('Y-m-d', strtotime('+2 years')),
                'passport_type' => 'ORDINAIRE'
            ],
            'payment' => [
                'amount' => 50000,  // Should be 73000 for ORDINAIRE
                'currency' => 'XOF',
                'date' => date('Y-m-d'),
                'reference' => 'TEST123',
                'amount_matches_expected' => false
            ]
        ];

        $result = $this->validator->validate($documents);

        $fraudIndicators = array_column($result['fraud_indicators'] ?? [], 'type');
        $this->assertContains('INCORRECT_PAYMENT_AMOUNT', $fraudIndicators);
    }

    // ==========================================
    // RISK LEVEL CALCULATION
    // ==========================================

    public function test_calculates_low_risk(): void
    {
        $documents = [
            'passport' => [
                'surname' => 'GOOD',
                'given_names' => 'USER',
                'passport_number' => 'AB1234567',
                'expiry_date' => date('Y-m-d', strtotime('+5 years')),
                'nationality' => 'ETH',
                'mrz_valid' => true
            ],
            'vaccination' => [
                'holder_name' => 'GOOD USER',
                'yellow_fever_date' => date('Y-m-d', strtotime('-1 year')),
                'yellow_fever_valid' => true
            ]
        ];

        $result = $this->validator->validate($documents);

        $this->assertEquals('LOW', $result['risk_level']);
        $this->assertFalse($result['requires_manual_review']);
    }

    public function test_calculates_critical_risk(): void
    {
        $documents = [
            'passport' => [
                'surname' => 'FRAUD',
                'given_names' => 'USER',
                'passport_number' => 'XX9999999',
                'expiry_date' => date('Y-m-d', strtotime('-1 year')),  // Expired
                'mrz_valid' => false  // Invalid MRZ
            ]
        ];

        $result = $this->validator->validate($documents);

        $this->assertEquals('CRITICAL', $result['risk_level']);
        $this->assertTrue($result['requires_manual_review']);
    }

    // ==========================================
    // ANOMALY DETECTION
    // ==========================================

    public function test_detects_long_stay_anomaly(): void
    {
        $documents = [
            'passport' => [
                'surname' => 'LONG',
                'given_names' => 'STAY',
                'passport_number' => 'AB1234567',
                'expiry_date' => date('Y-m-d', strtotime('+2 years'))
            ],
            'hotel' => [
                'guest_name' => 'LONG STAY',
                'check_in_date' => '2025-01-15',
                'check_out_date' => '2025-05-15',  // 120 nights!
                'hotel_name' => 'Extended Stay Hotel'
            ]
        ];

        $result = $this->validator->validate($documents);

        $anomalies = array_column($result['anomalies'] ?? [], 'type');
        $this->assertContains('LONG_STAY', $anomalies);
    }

    public function test_detects_urgent_travel_anomaly(): void
    {
        $documents = [
            'passport' => [
                'surname' => 'URGENT',
                'given_names' => 'TRAVELER',
                'passport_number' => 'AB1234567',
                'expiry_date' => date('Y-m-d', strtotime('+2 years'))
            ],
            'ticket' => [
                'passenger_name' => 'URGENT/TRAVELER',
                'flight_number' => 'ET123',
                'departure_date' => date('Y-m-d', strtotime('+3 days'))  // Very soon!
            ]
        ];

        $result = $this->validator->validate($documents);

        $anomalies = array_column($result['anomalies'] ?? [], 'type');
        $this->assertContains('URGENT_TRAVEL', $anomalies);
    }

    // ==========================================
    // REQUIRED FIELDS
    // ==========================================

    public function test_validates_required_passport_fields(): void
    {
        $documents = [
            'passport' => [
                'surname' => 'TEST',
                // Missing: given_names, passport_number, expiry_date
            ]
        ];

        $result = $this->validator->validate($documents);

        $passportValidation = $result['documents_validated']['passport'] ?? [];
        $this->assertFalse($passportValidation['has_required_fields'] ?? true);
    }

    // ==========================================
    // RECOMMENDATIONS
    // ==========================================

    public function test_provides_recommendations(): void
    {
        $documents = [
            'passport' => [
                'surname' => 'TEST',
                'given_names' => 'USER',
                'passport_number' => 'AB1234567',
                'expiry_date' => date('Y-m-d', strtotime('+4 months'))  // Less than 6 months
            ]
        ];

        $result = $this->validator->validate($documents);

        $this->assertNotEmpty($result['recommendations']);
    }
}
