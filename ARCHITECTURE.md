# Architecture du Projet visa-chatbot

## Vue d'ensemble

Ce document décrit la nouvelle architecture modulaire du chatbot visa.

## Structure des dossiers

```
visa-chatbot/
├── index.php                    # Point d'entrée
├── composer.json                # Dépendances PHP
├── ARCHITECTURE.md              # Ce fichier
│
├── config/
│   ├── app.php                  # Configuration principale
│   └── tailwind.php             # Config Tailwind
│
├── views/
│   ├── layouts/
│   │   └── main.php             # Layout principal
│   ├── pages/
│   │   └── home.php             # Page d'accueil
│   └── partials/                # Composants partiels
│
├── css/
│   ├── main.css                 # Point d'entrée CSS (importe les autres)
│   ├── chatbot.css              # Styles principaux
│   ├── forms.css                # Formulaires
│   ├── multi-upload.css         # Upload multiple
│   ├── coherence-ui.css         # UI de validation
│   └── accessibility.css        # Accessibilité
│
├── js/
│   ├── app.js                   # Point d'entrée JS
│   ├── modules/                 # Architecture modulaire ES6
│   │   ├── index.js             # Export de tous les modules
│   │   ├── config.js            # Configuration centralisée
│   │   ├── i18n.js              # Internationalisation FR/EN
│   │   ├── state.js             # Gestion d'état centralisée
│   │   ├── messages.js          # Affichage des messages
│   │   ├── ui.js                # Composants UI
│   │   ├── upload.js            # Gestion des uploads
│   │   ├── analytics.js         # Analytics et A/B testing
│   │   ├── api.js               # Communications API
│   │   └── chatbot.js           # Classe principale
│   ├── legacy/                  # Anciens fichiers (backup)
│   │   ├── chatbot.legacy.js
│   │   ├── chatbot-redesign.legacy.js
│   │   └── chatbot-tailwind.legacy.js
│   └── *.js                     # Modules complémentaires
│
├── php/
│   ├── api/                     # API centralisée
│   │   ├── Router.php           # Routeur d'API
│   │   └── endpoints.php        # Registre des endpoints
│   │
│   ├── extractors/              # Extracteurs par type de document
│   │   ├── AbstractExtractor.php
│   │   ├── PassportExtractor.php
│   │   ├── FlightTicketExtractor.php
│   │   ├── VaccinationCardExtractor.php
│   │   └── ...
│   │
│   ├── services/                # Services métier
│   │   ├── OCRService.php
│   │   ├── OCRIntegrationService.php
│   │   └── DocumentCoherenceValidator.php
│   │
│   ├── utils/                   # Utilitaires
│   │   ├── StringUtils.php
│   │   ├── ArrayUtils.php
│   │   └── CacheManager.php
│   │
│   ├── prompts/                 # Prompts IA
│   │   ├── flight-ticket-prompt.php
│   │   ├── hotel-reservation-prompt.php
│   │   └── gemini/
│   │
│   ├── cron/                    # Tâches planifiées
│   ├── data/                    # Données statiques
│   │
│   └── *.php                    # Handlers principaux
│
├── tests/
│   ├── Unit/                    # Tests unitaires
│   │   ├── Extractors/
│   │   ├── Services/
│   │   └── Validators/
│   ├── Integration/             # Tests d'intégration
│   ├── functional/              # Tests fonctionnels
│   └── fixtures/                # Données de test
│
└── data/                        # Données runtime
    ├── sessions/
    ├── drafts/
    ├── analytics/
    ├── cache/
    └── logs/
```

## Modules JavaScript

### Architecture ES6 Modulaire

```javascript
// Utilisation dans app.js
import { VisaChatbot } from './modules/index.js';

const chatbot = new VisaChatbot({
    debug: true,
    language: 'fr'
});
```

### Modules disponibles

| Module | Responsabilité |
|--------|---------------|
| `config.js` | Configuration, constantes, types de documents |
| `i18n.js` | Traductions FR/EN, formatage dates |
| `state.js` | Gestion d'état centralisée (pattern observer) |
| `messages.js` | Affichage messages bot/user, typewriter |
| `ui.js` | Progress bar, notifications, quick actions |
| `upload.js` | Upload fichiers, OCR, preview |
| `analytics.js` | Tracking, A/B testing |
| `api.js` | Communications avec le backend |
| `chatbot.js` | Classe principale intégrant tout |

## Classes PHP Utilitaires

### StringUtils

```php
use VisaChatbot\Utils\StringUtils;

StringUtils::normalizeForComparison($name);
StringUtils::removeAccents($string);
StringUtils::parseDate($date);
StringUtils::namesMatch($name1, $name2);
```

### ArrayUtils

```php
use VisaChatbot\Utils\ArrayUtils;

ArrayUtils::getNestedValue($array, 'path.to.value');
ArrayUtils::setNestedValue($array, 'path', $value);
ArrayUtils::flatten($array);
ArrayUtils::groupBy($array, 'key');
```

### CacheManager

```php
use VisaChatbot\Utils\CacheManager;

$cache = new CacheManager('/path/to/cache');
$cache->get('type', $content);
$cache->set('type', $content, $result);
$cache->clear();
```

## API Endpoints

### Chat Handler (`php/chat-handler.php`)

| Action | Méthode | Description |
|--------|---------|-------------|
| `init` | GET | Initialiser session |
| `message` | POST | Envoyer message |
| `navigate` | POST | Naviguer vers étape |
| `passport_ocr` | POST | Traiter OCR passeport |
| `extract_document` | POST | Extraire document |
| `submit_application` | POST | Soumettre demande |

### Session Manager (`php/session-manager.php`)

| Action | Méthode | Description |
|--------|---------|-------------|
| `create` | POST | Créer session |
| `get` | GET | Récupérer session |
| `update` | POST | Mettre à jour |
| `destroy` | POST | Détruire session |

## Extracteurs de Documents

Architecture Triple Layer:
1. **Layer 1 (OCR)**: Google Vision - Extraction texte brut
2. **Layer 2 (Structuration)**: Gemini Flash - Structuration intelligente
3. **Layer 3 (Validation)**: Claude Sonnet - Supervision

### Types supportés

- `passport` - Passeport (OCR + MRZ)
- `ticket` - Billet d'avion
- `hotel` - Réservation hôtel
- `vaccination` - Carnet vaccinal
- `invitation` - Lettre d'invitation
- `verbal_note` - Note verbale
- `residence_card` - Carte de séjour
- `financial_proof` - Justificatif financier

## Conventions de nommage

### PHP

| Type | Convention | Exemple |
|------|-----------|---------|
| Classes | PascalCase | `DocumentExtractor` |
| Méthodes | camelCase | `extractPassport()` |
| Constantes | UPPER_SNAKE | `DOCUMENT_TYPES` |
| Fichiers (classes) | PascalCase | `StringUtils.php` |
| Fichiers (procédural) | kebab-case | `chat-handler.php` |

### JavaScript

| Type | Convention | Exemple |
|------|-----------|---------|
| Classes | PascalCase | `VisaChatbot` |
| Fonctions | camelCase | `sendMessage()` |
| Constantes | UPPER_SNAKE | `CONFIG` |
| Fichiers | kebab-case | `config.js` |

### CSS

| Type | Convention | Exemple |
|------|-----------|---------|
| Classes | kebab-case | `.chat-message` |
| Variables | kebab-case | `--accent-color` |
| BEM | block__element--modifier | `.btn__icon--large` |

## Tests

```bash
# Exécuter les tests unitaires
./vendor/bin/phpunit tests/Unit

# Exécuter les tests d'intégration
./vendor/bin/phpunit tests/Integration

# Exécuter tous les tests
./tests/run-all-tests.sh
```

## Performance

### Optimisations implémentées

1. **Cache OCR**: Résultats d'extraction mis en cache (TTL 24h)
2. **Lazy loading**: Modules JS chargés à la demande
3. **Compression**: Assets CSS/JS minifiés en production
4. **Session storage**: Données session en fichiers (scalable vers Redis)

### Recommandations

- Utiliser `main.css` comme point d'entrée unique
- Préférer les imports ES6 modules
- Activer le cache en production
- Monitorer les temps d'extraction OCR

## Migrations

### Depuis l'ancienne architecture

1. Remplacer `chatbot.js` par `js/app.js`
2. Utiliser `css/main.css` au lieu des imports multiples
3. Les anciens fichiers sont dans `js/legacy/` pour référence
4. Les tests sont maintenant dans `tests/functional/`
