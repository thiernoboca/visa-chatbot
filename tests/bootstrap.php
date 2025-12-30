<?php
/**
 * PHPUnit Bootstrap File
 * Visa Chatbot OCR System
 *
 * @package VisaChatbot\Tests
 */

// Autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load all extractors
require_once __DIR__ . '/../php/extractors/AbstractExtractor.php';
require_once __DIR__ . '/../php/extractors/PassportExtractor.php';
require_once __DIR__ . '/../php/extractors/FlightTicketExtractor.php';
require_once __DIR__ . '/../php/extractors/VaccinationCardExtractor.php';
require_once __DIR__ . '/../php/extractors/HotelReservationExtractor.php';
require_once __DIR__ . '/../php/extractors/VerbalNoteExtractor.php';
require_once __DIR__ . '/../php/extractors/InvitationLetterExtractor.php';
require_once __DIR__ . '/../php/extractors/PaymentProofExtractor.php';
require_once __DIR__ . '/../php/extractors/ResidenceCardExtractor.php';

// Load validators
require_once __DIR__ . '/../php/validators/AbstractValidator.php';
require_once __DIR__ . '/../php/validators/DocumentValidator.php';

// Load services
if (file_exists(__DIR__ . '/../php/services/OCRService.php')) {
    require_once __DIR__ . '/../php/services/OCRService.php';
}
if (file_exists(__DIR__ . '/../php/services/OCRIntegrationService.php')) {
    require_once __DIR__ . '/../php/services/OCRIntegrationService.php';
}

// Test helper functions
function getFixturePath(string $filename): string {
    return __DIR__ . '/fixtures/' . $filename;
}

function loadFixture(string $filename): string {
    $path = getFixturePath($filename);
    if (!file_exists($path)) {
        throw new RuntimeException("Fixture not found: {$path}");
    }
    return file_get_contents($path);
}

// Set timezone for consistent date tests
date_default_timezone_set('Africa/Addis_Ababa');
