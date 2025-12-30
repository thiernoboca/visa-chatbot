<?php
/**
 * Prompt Claude pour extraction de carnets de vaccination
 * Focus sur le vaccin contre la fièvre jaune (obligatoire pour la Côte d'Ivoire)
 * 
 * @package VisaChatbot
 */

class VaccinationCardPrompt {
    
    /**
     * Construit le prompt pour extraction de carnet vaccinal
     * 
     * @param string $text Texte OCR du carnet
     * @return string Prompt formaté
     */
    public static function build(string $text): string {
        return <<<PROMPT
Tu es un assistant spécialisé dans l'extraction de données de carnets de vaccination internationaux (carnet jaune OMS).
L'objectif est de vérifier la vaccination contre la FIÈVRE JAUNE, obligatoire pour entrer en Côte d'Ivoire.

Analyse le texte suivant extrait d'un carnet de vaccination et retourne un JSON structuré.

TEXTE DU CARNET:
{$text}

RETOURNE UNIQUEMENT un JSON avec cette structure exacte:
{
  "holder": {
    "surname": "NOM en majuscules",
    "given_names": "Prénoms",
    "date_of_birth": "DD/MM/YYYY",
    "sex": "M|F",
    "nationality": "Nationalité",
    "confidence": 0.90
  },
  "yellow_fever": {
    "vaccinated": true,
    "vaccine_name": "Nom du vaccin (ex: STAMARIL, YF-VAX)",
    "batch_number": "Numéro de lot",
    "date_of_vaccination": "DD/MM/YYYY",
    "valid_from": "DD/MM/YYYY",
    "valid_until": "A_VIE|DD/MM/YYYY",
    "certificate_number": "Numéro de certificat (format: XXX-XX-YYYY-NNNNN)",
    "vaccination_center": {
      "name": "Nom du centre de vaccination",
      "city": "Ville",
      "country": "Pays"
    },
    "administering_doctor": "Nom du médecin ou signature",
    "official_stamp": true,
    "confidence": 0.92
  },
  "other_vaccines": [
    {
      "name": "Nom du vaccin",
      "disease": "Maladie (ex: COVID-19, Hépatite B)",
      "date": "DD/MM/YYYY"
    }
  ],
  "document": {
    "type": "INTERNATIONAL_VACCINATION_CERTIFICATE",
    "issuing_country": "Pays d'émission",
    "issue_date": "DD/MM/YYYY"
  },
  "overall_confidence": 0.91
}

RÈGLES IMPORTANTES:
1. La vaccination fièvre jaune est OBLIGATOIRE pour la Côte d'Ivoire
2. Le vaccin fièvre jaune est valide À VIE depuis 2016 (avant: 10 ans)
3. Les dates au format DD/MM/YYYY
4. Noms de vaccins fièvre jaune courants: STAMARIL, YF-VAX, 17D
5. Le certificat doit avoir un tampon officiel (official_stamp)
6. Si la vaccination fièvre jaune n'est pas trouvée, mettre vaccinated: false
7. Le numéro de certificat suit généralement le format: CODE_PAYS-REGION-ANNEE-NUMERO
8. Si un champ n'est pas trouvé, mettre null et confidence à 0
9. Les "other_vaccines" sont optionnels (COVID, Hépatite, etc.)
PROMPT;
    }
    
    /**
     * Champs extraits par ce prompt
     */
    public static function getFields(): array {
        return [
            'holder' => ['surname', 'given_names', 'date_of_birth', 'sex', 'nationality'],
            'yellow_fever' => ['vaccinated', 'vaccine_name', 'date_of_vaccination', 'valid_until', 'certificate_number', 'vaccination_center'],
            'other_vaccines' => [],
            'document' => ['type', 'issuing_country', 'issue_date']
        ];
    }
    
    /**
     * Vérifie si la vaccination fièvre jaune est valide
     * 
     * @param array $data Données extraites
     * @return array Résultat de validation
     */
    public static function validateYellowFever(array $data): array {
        $result = [
            'valid' => false,
            'message' => '',
            'details' => []
        ];
        
        // Vérifier si vacciné
        if (empty($data['yellow_fever']['vaccinated'])) {
            $result['message'] = 'Vaccination fièvre jaune non détectée';
            return $result;
        }
        
        // Vérifier la date de vaccination
        $vaccinationDate = $data['yellow_fever']['date_of_vaccination'] ?? null;
        if (!$vaccinationDate) {
            $result['message'] = 'Date de vaccination non trouvée';
            return $result;
        }
        
        // Vérifier validité (depuis 2016, valide à vie)
        $validUntil = $data['yellow_fever']['valid_until'] ?? 'A_VIE';
        
        if ($validUntil !== 'A_VIE') {
            $expiryDate = \DateTime::createFromFormat('d/m/Y', $validUntil);
            $today = new \DateTime();
            
            if ($expiryDate && $expiryDate < $today) {
                $result['message'] = 'Certificat de vaccination expiré';
                $result['details']['expiry_date'] = $validUntil;
                return $result;
            }
        }
        
        // Tout est OK
        $result['valid'] = true;
        $result['message'] = 'Vaccination fièvre jaune valide';
        $result['details'] = [
            'vaccination_date' => $vaccinationDate,
            'valid_until' => $validUntil,
            'certificate_number' => $data['yellow_fever']['certificate_number'] ?? null
        ];
        
        return $result;
    }
    
    /**
     * Mapping vers les champs du formulaire visa
     */
    public static function getFormMapping(): array {
        return [
            'VaccinFievreJaune' => 'yellow_fever.vaccinated',
            'DateVaccination' => 'yellow_fever.date_of_vaccination',
            'NumeroCertificat' => 'yellow_fever.certificate_number',
            'CentreVaccination' => 'yellow_fever.vaccination_center.name'
        ];
    }
}

