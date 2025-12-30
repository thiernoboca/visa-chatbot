<?php
/**
 * Prompt Claude pour extraction de réservations d'hôtel
 * 
 * @package VisaChatbot
 */

class HotelReservationPrompt {
    
    /**
     * Construit le prompt pour extraction de réservation hôtel
     * 
     * @param string $text Texte OCR de la réservation
     * @return string Prompt formaté
     */
    public static function build(string $text): string {
        return <<<PROMPT
Tu es un assistant spécialisé dans l'extraction de données de réservations d'hôtel pour les demandes de visa.

Analyse le texte suivant extrait d'une confirmation de réservation hôtel et retourne un JSON structuré.

TEXTE DE LA RÉSERVATION:
{$text}

RETOURNE UNIQUEMENT un JSON avec cette structure exacte:
{
  "hotel": {
    "name": "Nom complet de l'établissement",
    "stars": 4,
    "address": {
      "street": "Adresse",
      "city": "Ville",
      "country": "Pays",
      "postal_code": "Code postal si disponible"
    },
    "phone": "Numéro de téléphone",
    "email": "Email de l'hôtel",
    "confidence": 0.92
  },
  "reservation": {
    "confirmation_number": "Numéro de réservation",
    "check_in": {
      "date": "DD/MM/YYYY",
      "time": "HH:MM"
    },
    "check_out": {
      "date": "DD/MM/YYYY",
      "time": "HH:MM"
    },
    "nights": 5,
    "room_type": "Type de chambre",
    "guests": 1,
    "confidence": 0.95
  },
  "guest": {
    "title": "MR|MRS|MS",
    "surname": "NOM en majuscules",
    "given_names": "Prénoms",
    "email": "Email du client",
    "phone": "Téléphone du client",
    "confidence": 0.90
  },
  "payment": {
    "status": "CONFIRMED|PENDING|PAID",
    "total_amount": "Montant total",
    "currency": "EUR|USD|XOF|ETB",
    "confidence": 0.85
  },
  "booking_source": "Booking.com|Expedia|Direct|Agoda|Hotels.com|Other",
  "overall_confidence": 0.91
}

RÈGLES IMPORTANTES:
1. Les dates au format DD/MM/YYYY
2. Les heures au format HH:MM
3. Calculer le nombre de nuits entre check_in et check_out
4. Les noms en MAJUSCULES
5. Si un champ n'est pas trouvé, mettre null et confidence à 0
6. Les villes en Côte d'Ivoire incluent: Abidjan, Grand-Bassam, Yamoussoukro, Bouaké, San Pedro
7. Le nombre d'étoiles de 1 à 5, null si non précisé
8. Détecter la source de réservation (Booking, Expedia, etc.) si visible
PROMPT;
    }
    
    /**
     * Champs extraits par ce prompt
     */
    public static function getFields(): array {
        return [
            'hotel' => ['name', 'stars', 'address', 'phone', 'email'],
            'reservation' => ['confirmation_number', 'check_in', 'check_out', 'nights', 'room_type', 'guests'],
            'guest' => ['title', 'surname', 'given_names', 'email', 'phone'],
            'payment' => ['status', 'total_amount', 'currency']
        ];
    }
    
    /**
     * Mapping vers les champs du formulaire visa
     */
    public static function getFormMapping(): array {
        return [
            'AdresseSejour' => 'hotel.name',
            'VilleSejour' => 'hotel.address.city',
            'TypeLieuSejour' => 'Hôtel',
            'TelephoneHebergement' => 'hotel.phone',
            'DateArriveeHebergement' => 'reservation.check_in.date',
            'DateDepartHebergement' => 'reservation.check_out.date',
            'NombreNuits' => 'reservation.nights'
        ];
    }
}

