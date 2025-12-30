# Visa Chatbot - Embassy of Côte d'Ivoire

AI-powered visa application digitalization system serving 7 East African countries.

## Overview

The Visa Chatbot streamlines the visa application process for the Embassy of Côte d'Ivoire, covering Ethiopia, Kenya, Djibouti, Tanzania, Uganda, South Sudan, and Somalia. The system uses a **Triple Layer Architecture** combining Google Vision API, Gemini 3 Flash, and Claude Sonnet for intelligent document processing and conversation management.

## Architecture

### Triple Layer System

1. **Layer 1: Google Vision API** - OCR document extraction
2. **Layer 2: Gemini 3 Flash** - Real-time conversation engine
3. **Layer 3: Claude Sonnet** - Quality supervision and validation

### Supported Documents

The system processes 8 document types:
- Passport (MRZ extraction)
- Flight tickets
- Hotel reservations
- Vaccination cards (Yellow Fever validation)
- Invitation letters
- Payment proofs
- Residence cards
- Verbal notes (diplomatic)

## Tech Stack

- **Backend**: Native PHP (no framework)
- **Testing**: PHPUnit with TDD methodology
- **OCR**: Google Vision API
- **AI**: Google Gemini 3 Flash, Anthropic Claude Sonnet
- **Frontend**: Vanilla JavaScript with Vite build system

## Getting Started

### Prerequisites

- PHP 8.0+
- Composer
- Node.js 18+
- Google Cloud Vision API credentials
- Google Gemini API key
- Anthropic Claude API key

### Installation

1. Clone the repository:
```bash
git clone https://github.com/thiernoboca/visa-chatbot.git
cd visa-chatbot
```

2. Install PHP dependencies:
```bash
composer install
```

3. Install JavaScript dependencies:
```bash
npm install
```

4. Configure environment:
```bash
cp .env.example .env
# Edit .env with your API keys
```

5. Run development server:
```bash
npm run dev
```

## Testing

### Run all tests:
```bash
./vendor/bin/phpunit
```

### Run specific test suite:
```bash
./vendor/bin/phpunit tests/Unit/Extractors/PassportExtractorTest.php
```

### Generate coverage report:
```bash
./vendor/bin/phpunit --coverage-html coverage/
```

## Project Structure

```
visa-chatbot/
├── php/
│   ├── services/           # Core services
│   │   ├── OCRIntegrationService.php
│   │   └── OCRService.php
│   ├── extractors/         # Document extractors (8 types)
│   ├── validators/         # Cross-document validation
│   └── prompts/gemini/     # Gemini extraction prompts
├── js/modules/             # Frontend modules
├── tests/                  # PHPUnit tests
└── docs/                   # Documentation
```

## Key Features

- **Multi-country Support**: Handles visa requirements for 7 countries
- **GDPR Compliance**: PII sanitization and secure file handling
- **Cross-document Validation**: Ensures data coherence across documents
- **Real-time Conversation**: Natural language interaction powered by Gemini
- **MRZ Extraction**: Automated passport data extraction
- **Yellow Fever Validation**: Country-specific vaccination requirements

## Development Workflow

This project follows TDD (Test-Driven Development) with the RED-GREEN-REFACTOR cycle:

1. Write failing test (RED)
2. Implement minimum code to pass (GREEN)
3. Refactor for quality (REFACTOR)

See `.claude/skills/php-tdd.md` for detailed TDD guidelines.

## Security

- Environment-based API key management
- PII data sanitization
- GDPR-compliant data handling
- Secure file upload validation

## Contributing

1. Create a feature branch
2. Write tests first (TDD)
3. Implement the feature
4. Ensure all tests pass
5. Create a pull request

## License

UNLICENSED - Private project for Embassy of Côte d'Ivoire

## Support

For issues or questions, please contact the development team.
