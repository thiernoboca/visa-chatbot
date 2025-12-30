<?php
/**
 * Prompt Claude pour extraction de billets d'avion
 * 
 * @package VisaChatbot
 */

class FlightTicketPrompt {
    
    /**
     * Construit le prompt pour extraction de billet d'avion
     * 
     * @param string $text Texte OCR du billet
     * @return string Prompt formaté
     */
    public static function build(string $text): string {
        return <<<PROMPT
Tu es un assistant spécialisé dans l'extraction de données de billets d'avion pour les demandes de visa.

Analyse le texte suivant extrait d'un billet d'avion et retourne un JSON structuré avec les informations de vol.

TEXTE DU BILLET:
{$text}

RETOURNE UNIQUEMENT un JSON avec cette structure exacte (sans commentaires, sans texte avant ou après):
{
  "airline": {
    "name": "Nom complet de la compagnie aérienne",
    "iata_code": "Code IATA 2 lettres (ex: ET, AF, KQ)",
    "confidence": 0.95
  },
  "flight": {
    "number": "Numéro de vol complet avec code compagnie (ex: ET509)",
    "class": "ECONOMY|BUSINESS|FIRST|PREMIUM_ECONOMY",
    "confidence": 0.95
  },
  "departure": {
    "date": "DD/MM/YYYY",
    "time": "HH:MM",
    "airport": {
      "code": "Code IATA 3 lettres",
      "name": "Nom complet de l'aéroport",
      "city": "Ville"
    },
    "confidence": 0.95
  },
  "arrival": {
    "date": "DD/MM/YYYY",
    "time": "HH:MM",
    "airport": {
      "code": "Code IATA 3 lettres",
      "name": "Nom complet de l'aéroport",
      "city": "Ville"
    },
    "confidence": 0.95
  },
  "passenger": {
    "title": "MR|MRS|MS|MISS",
    "surname": "NOM en majuscules",
    "given_names": "Prénoms",
    "confidence": 0.95
  },
  "booking": {
    "pnr": "Code de réservation 6 caractères alphanumériques",
    "ticket_number": "Numéro de billet électronique (13 chiffres)",
    "confidence": 0.90
  },
  "return_flight": {
    "exists": true,
    "date": "DD/MM/YYYY",
    "flight_number": "ET510",
    "confidence": 0.90
  },
  "overall_confidence": 0.93
}

RÈGLES IMPORTANTES:
1. Les dates doivent être au format DD/MM/YYYY
2. Les heures au format HH:MM (24h)
3. Les noms de passager en MAJUSCULES
4. Les codes aéroports en 3 lettres majuscules
5. Si un champ n'est pas trouvé, mettre null et confidence à 0
6. Codes aéroports Côte d'Ivoire: ABJ (Abidjan), BYK (Bouaké)
7. Codes aéroports Éthiopie: ADD (Addis Ababa)
8. Si le vol de retour n'est pas mentionné, mettre return_flight.exists à false
9. Le score de confiance reflète la certitude de l'extraction (0 à 1)
PROMPT;
    }
    
    /**
     * Champs extraits par ce prompt
     */
    public static function getFields(): array {
        return [
            'airline' => ['name', 'iata_code'],
            'flight' => ['number', 'class'],
            'departure' => ['date', 'time', 'airport'],
            'arrival' => ['date', 'time', 'airport'],
            'passenger' => ['title', 'surname', 'given_names'],
            'booking' => ['pnr', 'ticket_number'],
            'return_flight' => ['exists', 'date', 'flight_number']
        ];
    }
    
    /**
     * Mapping vers les champs du formulaire visa
     */
    public static function getFormMapping(): array {
        return [
            'ArriveeLe' => 'departure.date', // Date d'arrivée en CI
            'DepartLe' => 'return_flight.date', // Date de départ de CI
            'LieuArrivee' => 'arrival.airport.code',
            'LieuDepart' => 'departure.airport.code',
            'MoyenTransport' => 'Avion',
            'CompagnieAerienne' => 'airline.name',
            'NumeroVol' => 'flight.number'
        ];
    }
}

