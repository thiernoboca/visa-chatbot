<?php
/**
 * Validateur pour les Mineurs
 * Ambassade de Côte d'Ivoire en Éthiopie
 *
 * Valide les documents spécifiques aux mineurs:
 * - Autorisation parentale
 * - Acte de naissance
 * - Pièces d'identité des parents/tuteurs
 *
 * @package VisaChatbot\Validators
 * @version 1.0.0
 */

namespace VisaChatbot\Validators;

require_once __DIR__ . '/AbstractValidator.php';

class MinorValidator extends AbstractValidator {

    /**
     * Seuil d'âge pour être considéré comme mineur
     */
    private const MINOR_AGE_THRESHOLD = 18;

    /**
     * Documents requis pour les mineurs
     */
    private const REQUIRED_DOCUMENTS = [
        'parental_authorization',
        'birth_certificate',
        'parent_id'
    ];

    /**
     * Documents optionnels selon le cas
     */
    private const OPTIONAL_DOCUMENTS = [
        'guardianship_proof',  // Si tuteur légal
        'consent_from_absent_parent',  // Si un seul parent signe
        'death_certificate'  // Si un parent est décédé
    ];

    /**
     * Valide un dossier de demandeur mineur
     */
    public function validate(array $minorData, array $parentData = []): array {
        $this->clearAlerts();

        $result = [
            'valid' => true,
            'is_minor' => false,
            'age' => null,
            'checks' => [],
            'required_documents' => [],
            'missing_documents' => [],
            'parent_validations' => [],
            'recommendations' => []
        ];

        // 1. Vérifier si c'est bien un mineur
        $dateOfBirth = $minorData['date_of_birth'] ?? $minorData['dateOfBirth'] ?? null;
        if (!$dateOfBirth) {
            $result['valid'] = false;
            $this->addAlert('MISSING_DOB', 'Date de naissance manquante', 'CRITICAL');
            $result['checks']['has_dob'] = false;
            return $result;
        }

        $age = $this->calculateAge($dateOfBirth);
        $result['age'] = $age;
        $result['is_minor'] = $age < self::MINOR_AGE_THRESHOLD;
        $result['checks']['is_minor'] = $result['is_minor'];

        if (!$result['is_minor']) {
            // Pas un mineur, pas de validation spécifique nécessaire
            $result['checks']['age_verified'] = true;
            $result['recommendations'][] = 'NOT_MINOR: Applicant is an adult, no minor-specific validation required';
            return $result;
        }

        // 2. Déterminer les documents requis
        $result['required_documents'] = $this->determineRequiredDocuments($minorData, $parentData);

        // 3. Vérifier les documents fournis
        $providedDocs = $minorData['documents'] ?? [];
        $result['missing_documents'] = $this->findMissingDocuments($result['required_documents'], $providedDocs);

        if (!empty($result['missing_documents'])) {
            $result['valid'] = false;
            foreach ($result['missing_documents'] as $doc) {
                $this->addAlert(
                    'MISSING_MINOR_DOC',
                    "Document manquant: {$doc['label']}",
                    'CRITICAL'
                );
            }
        }

        // 4. Valider l'autorisation parentale
        if (isset($providedDocs['parental_authorization'])) {
            $result['checks']['parental_authorization'] = $this->validateParentalAuthorization(
                $providedDocs['parental_authorization'],
                $minorData,
                $parentData
            );

            if (!$result['checks']['parental_authorization']['valid']) {
                $result['valid'] = false;
            }
        }

        // 5. Valider l'acte de naissance
        if (isset($providedDocs['birth_certificate'])) {
            $result['checks']['birth_certificate'] = $this->validateBirthCertificate(
                $providedDocs['birth_certificate'],
                $minorData
            );

            if (!$result['checks']['birth_certificate']['valid']) {
                $result['valid'] = false;
            }
        }

        // 6. Valider les pièces d'identité des parents
        if (isset($providedDocs['parent_id'])) {
            $result['parent_validations'] = $this->validateParentIds(
                $providedDocs['parent_id'],
                $parentData
            );
        }

        // 7. Cross-validation avec le passeport du mineur
        if (isset($minorData['passport'])) {
            $result['checks']['passport_cross_validation'] = $this->crossValidateWithPassport(
                $minorData['passport'],
                $providedDocs
            );
        }

        // 8. Vérifications spéciales selon l'âge
        if ($age < 12) {
            $result['checks']['very_young'] = $this->validateVeryYoungMinor($minorData, $parentData);
        }

        // 9. Générer les recommandations
        $result['recommendations'] = $this->generateRecommendations($result, $age);

        // 10. Ajouter les alertes
        $result['alerts'] = $this->getAlerts();

        return $result;
    }

    /**
     * Calcule l'âge à partir de la date de naissance
     */
    private function calculateAge(string $dateOfBirth): int {
        $dob = new \DateTime($dateOfBirth);
        $today = new \DateTime();
        $diff = $today->diff($dob);

        return $diff->y;
    }

    /**
     * Détermine les documents requis selon le cas
     */
    private function determineRequiredDocuments(array $minorData, array $parentData): array {
        $required = [
            [
                'type' => 'parental_authorization',
                'label' => 'Autorisation parentale',
                'description' => 'Autorisation de voyage signée par les parents ou tuteurs légaux'
            ],
            [
                'type' => 'birth_certificate',
                'label' => 'Acte de naissance',
                'description' => 'Copie certifiée de l\'acte de naissance'
            ],
            [
                'type' => 'parent_id',
                'label' => 'Pièce d\'identité des parents',
                'description' => 'Copie des pièces d\'identité des parents ou tuteurs'
            ]
        ];

        // Cas spéciaux
        $travelingWith = $parentData['traveling_with_minor'] ?? 'both_parents';

        if ($travelingWith === 'one_parent') {
            $required[] = [
                'type' => 'consent_from_absent_parent',
                'label' => 'Consentement du parent absent',
                'description' => 'Autorisation écrite du parent qui ne voyage pas'
            ];
        }

        if ($travelingWith === 'guardian') {
            $required[] = [
                'type' => 'guardianship_proof',
                'label' => 'Preuve de tutelle',
                'description' => 'Document officiel de tutelle légale'
            ];
        }

        if ($travelingWith === 'alone' || $travelingWith === 'third_party') {
            $required[] = [
                'type' => 'notarized_consent',
                'label' => 'Consentement notarié',
                'description' => 'Consentement des deux parents légalisé par notaire'
            ];
        }

        // Si parent décédé
        if (isset($parentData['parent_deceased']) && $parentData['parent_deceased']) {
            $required[] = [
                'type' => 'death_certificate',
                'label' => 'Acte de décès',
                'description' => 'Acte de décès du parent'
            ];
        }

        return $required;
    }

    /**
     * Trouve les documents manquants
     */
    private function findMissingDocuments(array $required, array $provided): array {
        $missing = [];

        foreach ($required as $doc) {
            if (!isset($provided[$doc['type']]) || empty($provided[$doc['type']])) {
                $missing[] = $doc;
            }
        }

        return $missing;
    }

    /**
     * Valide l'autorisation parentale
     */
    private function validateParentalAuthorization(array $authData, array $minorData, array $parentData): array {
        $result = [
            'valid' => true,
            'issues' => []
        ];

        // Vérifier que le nom du mineur correspond
        $authMinorName = $authData['minor_name'] ?? $authData['child_name'] ?? '';
        $actualMinorName = ($minorData['first_name'] ?? '') . ' ' . ($minorData['last_name'] ?? '');
        $actualMinorName = trim($actualMinorName);

        if (!empty($authMinorName) && !empty($actualMinorName)) {
            $similarity = $this->calculateNameSimilarity($authMinorName, $actualMinorName);
            if ($similarity < 0.8) {
                $result['valid'] = false;
                $result['issues'][] = 'Le nom sur l\'autorisation ne correspond pas au mineur';
            }
        }

        // Vérifier la date de l'autorisation (ne doit pas être trop ancienne)
        $authDate = $authData['date'] ?? $authData['authorization_date'] ?? null;
        if ($authDate) {
            $authTimestamp = strtotime($authDate);
            $sixMonthsAgo = strtotime('-6 months');

            if ($authTimestamp < $sixMonthsAgo) {
                $result['valid'] = false;
                $result['issues'][] = 'L\'autorisation date de plus de 6 mois';
            }
        }

        // Vérifier les signatures
        $signatures = $authData['signatures'] ?? [];
        if (count($signatures) < 1) {
            // Au moins une signature requise
            $result['issues'][] = 'Signature(s) parentale(s) non détectée(s)';
        }

        // Vérifier que l'autorisation couvre la destination
        $destination = $authData['destination'] ?? '';
        if (!empty($destination) && stripos($destination, 'IVOIRE') === false && stripos($destination, "COTE D'IVOIRE") === false) {
            $result['issues'][] = 'La destination mentionnée ne semble pas être la Côte d\'Ivoire';
        }

        return $result;
    }

    /**
     * Valide l'acte de naissance
     */
    private function validateBirthCertificate(array $certData, array $minorData): array {
        $result = [
            'valid' => true,
            'issues' => []
        ];

        // Vérifier le nom
        $certName = $certData['child_name'] ?? $certData['name'] ?? '';
        $minorName = ($minorData['first_name'] ?? '') . ' ' . ($minorData['last_name'] ?? '');

        if (!empty($certName) && !empty($minorName)) {
            $similarity = $this->calculateNameSimilarity($certName, $minorName);
            if ($similarity < 0.8) {
                $result['valid'] = false;
                $result['issues'][] = 'Le nom sur l\'acte de naissance ne correspond pas';
            }
        }

        // Vérifier la date de naissance
        $certDob = $certData['date_of_birth'] ?? $certData['birth_date'] ?? '';
        $minorDob = $minorData['date_of_birth'] ?? $minorData['dateOfBirth'] ?? '';

        if (!empty($certDob) && !empty($minorDob)) {
            $certDobNorm = date('Y-m-d', strtotime($certDob));
            $minorDobNorm = date('Y-m-d', strtotime($minorDob));

            if ($certDobNorm !== $minorDobNorm) {
                $result['valid'] = false;
                $result['issues'][] = 'La date de naissance ne correspond pas';
            }
        }

        // Vérifier si certifié/légalisé
        $isCertified = $certData['certified'] ?? $certData['is_certified'] ?? null;
        if ($isCertified === false) {
            $result['issues'][] = 'L\'acte de naissance ne semble pas être certifié';
        }

        return $result;
    }

    /**
     * Valide les pièces d'identité des parents
     */
    private function validateParentIds(array $parentIds, array $parentData): array {
        $validations = [];

        // Peut être un seul document ou un tableau
        $ids = isset($parentIds['type']) ? [$parentIds] : $parentIds;

        foreach ($ids as $index => $id) {
            $validation = [
                'valid' => true,
                'parent_index' => $index,
                'issues' => []
            ];

            // Vérifier l'expiration
            $expiry = $id['expiry_date'] ?? $id['date_of_expiry'] ?? null;
            if ($expiry && strtotime($expiry) < time()) {
                $validation['valid'] = false;
                $validation['issues'][] = 'La pièce d\'identité est expirée';
            }

            // Vérifier que c'est un document officiel
            $docType = $id['document_type'] ?? $id['type'] ?? '';
            $validTypes = ['passport', 'national_id', 'driver_license', 'carte_identite', 'passeport'];
            $isValidType = false;
            foreach ($validTypes as $type) {
                if (stripos($docType, $type) !== false) {
                    $isValidType = true;
                    break;
                }
            }

            if (!$isValidType && !empty($docType)) {
                $validation['issues'][] = 'Type de document non reconnu';
            }

            $validations[] = $validation;
        }

        return $validations;
    }

    /**
     * Cross-validation avec le passeport du mineur
     */
    private function crossValidateWithPassport(array $passport, array $documents): array {
        $result = [
            'valid' => true,
            'issues' => []
        ];

        $passportName = ($passport['surname'] ?? '') . ' ' . ($passport['given_names'] ?? '');
        $passportDob = $passport['date_of_birth'] ?? '';

        // Vérifier cohérence avec l'acte de naissance
        if (isset($documents['birth_certificate'])) {
            $certName = $documents['birth_certificate']['child_name'] ?? '';
            if (!empty($certName) && !empty($passportName)) {
                $similarity = $this->calculateNameSimilarity($certName, $passportName);
                if ($similarity < 0.8) {
                    $result['issues'][] = 'Incohérence entre passeport et acte de naissance';
                }
            }
        }

        // Vérifier cohérence avec l'autorisation parentale
        if (isset($documents['parental_authorization'])) {
            $authName = $documents['parental_authorization']['minor_name'] ??
                        $documents['parental_authorization']['child_name'] ?? '';
            if (!empty($authName) && !empty($passportName)) {
                $similarity = $this->calculateNameSimilarity($authName, $passportName);
                if ($similarity < 0.8) {
                    $result['issues'][] = 'Incohérence entre passeport et autorisation parentale';
                }
            }
        }

        return $result;
    }

    /**
     * Validations spéciales pour les très jeunes enfants (< 12 ans)
     */
    private function validateVeryYoungMinor(array $minorData, array $parentData): array {
        $result = [
            'valid' => true,
            'notes' => []
        ];

        $travelingWith = $parentData['traveling_with_minor'] ?? 'both_parents';

        // Un enfant de moins de 12 ans doit généralement voyager avec un adulte
        if ($travelingWith === 'alone') {
            $result['valid'] = false;
            $result['notes'][] = 'Un enfant de moins de 12 ans ne peut pas voyager seul';
        }

        // Vérifier que l'accompagnant est bien identifié
        if ($travelingWith === 'third_party' && empty($parentData['third_party_details'])) {
            $result['notes'][] = 'Détails du tiers accompagnant requis';
        }

        return $result;
    }

    /**
     * Calcule la similarité entre deux noms
     */
    private function calculateNameSimilarity(string $name1, string $name2): float {
        $n1 = $this->normalizeName($name1);
        $n2 = $this->normalizeName($name2);

        if ($n1 === $n2) return 1.0;
        if (empty($n1) || empty($n2)) return 0.0;

        $maxLen = max(strlen($n1), strlen($n2));
        $distance = levenshtein($n1, $n2);

        return 1 - ($distance / $maxLen);
    }

    /**
     * Normalise un nom pour comparaison
     */
    private function normalizeName(string $name): string {
        $name = strtoupper($name);
        $name = preg_replace('/[^A-Z\s]/', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        return trim($name);
    }

    /**
     * Génère les recommandations
     */
    private function generateRecommendations(array $result, int $age): array {
        $recommendations = [];

        if (!$result['valid']) {
            $recommendations[] = 'INCOMPLETE: Documents manquants ou invalides pour le mineur';
        }

        if ($age < 6) {
            $recommendations[] = 'INFO: Enfant très jeune, vérification renforcée recommandée';
        }

        if (!empty($result['missing_documents'])) {
            $docList = array_map(fn($d) => $d['label'], $result['missing_documents']);
            $recommendations[] = 'ACTION: Fournir les documents manquants: ' . implode(', ', $docList);
        }

        if ($result['valid'] && empty($result['missing_documents'])) {
            $recommendations[] = 'OK: Dossier mineur complet';
        }

        return $recommendations;
    }

    public function getDocumentType(): string {
        return 'minor_documents';
    }
}
