<?php
/**
 * Prompt Claude pour extraction de lettres d'invitation
 * 
 * @package VisaChatbot
 */

class InvitationLetterPrompt {
    
    /**
     * Construit le prompt pour extraction de lettre d'invitation
     * 
     * @param string $text Texte OCR de la lettre
     * @return string Prompt formaté
     */
    public static function build(string $text): string {
        return <<<PROMPT
Tu es un assistant spécialisé dans l'extraction de données de lettres d'invitation pour les demandes de visa.

Analyse le texte suivant extrait d'une lettre d'invitation et retourne un JSON structuré.

TEXTE DE LA LETTRE:
{$text}

RETOURNE UNIQUEMENT un JSON avec cette structure exacte:
{
  "inviter": {
    "type": "INDIVIDUAL|COMPANY|ORGANIZATION|GOVERNMENT",
    "name": "Nom complet de l'invitant (personne ou organisation)",
    "title": "Titre ou fonction",
    "organization": "Nom de l'entreprise/organisation si applicable",
    "address": {
      "street": "Adresse",
      "city": "Ville",
      "country": "Pays",
      "postal_code": "Code postal"
    },
    "phone": "Téléphone",
    "email": "Email",
    "id_number": "Numéro d'identification (CNI, passeport) si mentionné",
    "confidence": 0.88
  },
  "invitee": {
    "surname": "NOM en majuscules",
    "given_names": "Prénoms",
    "passport_number": "Numéro de passeport si mentionné",
    "nationality": "Nationalité",
    "relationship": "Relation avec l'invitant (ami, collègue, partenaire, famille)",
    "confidence": 0.90
  },
  "visit": {
    "purpose": "BUSINESS|TOURISM|FAMILY|CONFERENCE|MEDICAL|STUDY|OTHER",
    "purpose_details": "Description détaillée du motif",
    "start_date": "DD/MM/YYYY",
    "end_date": "DD/MM/YYYY",
    "duration_days": 14,
    "confidence": 0.92
  },
  "accommodation": {
    "provided_by_inviter": true,
    "address": "Adresse d'hébergement si mentionnée",
    "type": "HOTEL|PRIVATE_RESIDENCE|COMPANY_HOUSING",
    "confidence": 0.85
  },
  "financial_support": {
    "provided": true,
    "type": "FULL|PARTIAL|NONE",
    "details": "Détails sur la prise en charge",
    "confidence": 0.80
  },
  "letter": {
    "date": "DD/MM/YYYY",
    "reference": "Numéro de référence si présent",
    "signed": true,
    "stamped": true,
    "notarized": false,
    "language": "FR|EN|OTHER"
  },
  "overall_confidence": 0.87
}

RÈGLES IMPORTANTES:
1. Les dates au format DD/MM/YYYY
2. Les noms en MAJUSCULES
3. Identifier le type d'invitant (particulier, entreprise, organisation, gouvernement)
4. Calculer la durée du séjour si dates de début et fin sont présentes
5. Détecter si la lettre est signée, tamponnée, notariée
6. Si un champ n'est pas trouvé, mettre null et confidence à 0
7. Le motif de visite doit être catégorisé (BUSINESS, TOURISM, FAMILY, etc.)
8. Vérifier si l'hébergement et/ou le soutien financier sont mentionnés
PROMPT;
    }
    
    /**
     * Champs extraits par ce prompt
     */
    public static function getFields(): array {
        return [
            'inviter' => ['type', 'name', 'title', 'organization', 'address', 'phone', 'email'],
            'invitee' => ['surname', 'given_names', 'passport_number', 'nationality', 'relationship'],
            'visit' => ['purpose', 'purpose_details', 'start_date', 'end_date', 'duration_days'],
            'accommodation' => ['provided_by_inviter', 'address', 'type'],
            'financial_support' => ['provided', 'type', 'details'],
            'letter' => ['date', 'reference', 'signed', 'stamped', 'notarized', 'language']
        ];
    }
    
    /**
     * Mapping vers les champs du formulaire visa
     */
    public static function getFormMapping(): array {
        return [
            'NomInvitant' => 'inviter.name',
            'AdresseInvitant' => 'inviter.address',
            'TelephoneInvitant' => 'inviter.phone',
            'EmailInvitant' => 'inviter.email',
            'MotifVoyage' => 'visit.purpose',
            'DescriptionMotif' => 'visit.purpose_details',
            'RelationInvitant' => 'invitee.relationship'
        ];
    }
    
    /**
     * Valide les informations de l'invitation
     * 
     * @param array $data Données extraites
     * @return array Résultat de validation
     */
    public static function validate(array $data): array {
        $issues = [];
        $warnings = [];
        
        // Vérifier que l'invitant est identifié
        if (empty($data['inviter']['name'])) {
            $issues[] = 'Nom de l\'invitant non identifié';
        }
        
        // Vérifier la présence de dates
        if (empty($data['visit']['start_date'])) {
            $warnings[] = 'Date de début de visite non précisée';
        }
        
        // Vérifier si la lettre est signée
        if (empty($data['letter']['signed'])) {
            $warnings[] = 'Signature non détectée';
        }
        
        // Vérifier la cohérence des dates
        if (!empty($data['visit']['start_date']) && !empty($data['visit']['end_date'])) {
            $start = \DateTime::createFromFormat('d/m/Y', $data['visit']['start_date']);
            $end = \DateTime::createFromFormat('d/m/Y', $data['visit']['end_date']);
            
            if ($start && $end && $end < $start) {
                $issues[] = 'Date de fin antérieure à la date de début';
            }
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'warnings' => $warnings
        ];
    }
}

