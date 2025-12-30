<?php
/**
 * VaccinationRequirements - Vaccination requirements by nationality
 *
 * Determines vaccination requirements based on traveler's nationality.
 * Yellow fever is mandatory for travelers from endemic zones (WHO guidelines).
 *
 * @version 1.0.0
 * @author Visa Chatbot Team
 */

class VaccinationRequirements {

    /**
     * Countries in Yellow Fever endemic zone (WHO classification)
     * ISO 3166-1 alpha-2 codes
     */
    private const YELLOW_FEVER_ENDEMIC_COUNTRIES = [
        // East Africa
        'ET' => 'Ethiopia',
        'KE' => 'Kenya',
        'UG' => 'Uganda',
        'TZ' => 'Tanzania',
        'SS' => 'South Sudan',
        'SO' => 'Somalia',
        'RW' => 'Rwanda',
        'BI' => 'Burundi',
        'ER' => 'Eritrea',

        // Central Africa
        'CD' => 'Democratic Republic of Congo',
        'CG' => 'Republic of Congo',
        'CF' => 'Central African Republic',
        'CM' => 'Cameroon',
        'GA' => 'Gabon',
        'GQ' => 'Equatorial Guinea',
        'TD' => 'Chad',
        'AO' => 'Angola',

        // West Africa
        'NG' => 'Nigeria',
        'GH' => 'Ghana',
        'CI' => 'Ivory Coast',
        'SN' => 'Senegal',
        'ML' => 'Mali',
        'BF' => 'Burkina Faso',
        'NE' => 'Niger',
        'BJ' => 'Benin',
        'TG' => 'Togo',
        'GN' => 'Guinea',
        'SL' => 'Sierra Leone',
        'LR' => 'Liberia',
        'GM' => 'Gambia',
        'GW' => 'Guinea-Bissau',
        'MR' => 'Mauritania',

        // South America (endemic zone)
        'BR' => 'Brazil',
        'CO' => 'Colombia',
        'PE' => 'Peru',
        'VE' => 'Venezuela',
        'EC' => 'Ecuador',
        'BO' => 'Bolivia',
        'GY' => 'Guyana',
        'SR' => 'Suriname',
        'GF' => 'French Guiana',
        'PY' => 'Paraguay',
        'TT' => 'Trinidad and Tobago',
        'PA' => 'Panama'
    ];

    /**
     * Vaccination types and their configurations
     */
    private const VACCINATION_TYPES = [
        'yellow_fever' => [
            'name' => [
                'en' => 'Yellow Fever',
                'fr' => 'Fièvre Jaune'
            ],
            'icon' => '💉',
            'validity_years' => null, // Lifetime validity since 2016 WHO update
            'document_name' => [
                'en' => 'International Certificate of Vaccination (Yellow Card)',
                'fr' => 'Certificat International de Vaccination (Carnet Jaune)'
            ]
        ],
        'covid19' => [
            'name' => [
                'en' => 'COVID-19',
                'fr' => 'COVID-19'
            ],
            'icon' => '🦠',
            'validity_months' => 9,
            'document_name' => [
                'en' => 'COVID-19 Vaccination Certificate',
                'fr' => 'Certificat de Vaccination COVID-19'
            ]
        ],
        'polio' => [
            'name' => [
                'en' => 'Polio',
                'fr' => 'Poliomyélite'
            ],
            'icon' => '💊',
            'validity_years' => 10,
            'document_name' => [
                'en' => 'Polio Vaccination Certificate',
                'fr' => 'Certificat de Vaccination contre la Polio'
            ]
        ]
    ];

    /**
     * Get vaccination requirements for a nationality
     *
     * @param string $nationalityCode ISO 3166-1 alpha-2 country code
     * @return array Requirements with required/recommended flags
     */
    public function getRequirements(string $nationalityCode): array {
        $nationalityCode = strtoupper($nationalityCode);
        $requirements = [];

        // Yellow Fever - REQUIRED for endemic zone nationals
        if ($this->requiresYellowFever($nationalityCode)) {
            $requirements['yellow_fever'] = [
                'type' => 'yellow_fever',
                'required' => true,
                'recommended' => true,
                'reason' => 'endemic_zone',
                'reason_text' => [
                    'en' => "Required for travelers from Yellow Fever endemic zones",
                    'fr' => "Obligatoire pour les voyageurs provenant de zones endémiques de fièvre jaune"
                ],
                'warning_level' => 'high',
                'info' => self::VACCINATION_TYPES['yellow_fever']
            ];
        } else {
            // Recommended for all travelers to tropical regions
            $requirements['yellow_fever'] = [
                'type' => 'yellow_fever',
                'required' => false,
                'recommended' => true,
                'reason' => 'travel_recommendation',
                'reason_text' => [
                    'en' => "Recommended for travel to Côte d'Ivoire (tropical region)",
                    'fr' => "Recommandé pour voyager en Côte d'Ivoire (région tropicale)"
                ],
                'warning_level' => 'medium',
                'info' => self::VACCINATION_TYPES['yellow_fever']
            ];
        }

        // COVID-19 - Currently recommended (not required as of 2024)
        $requirements['covid19'] = [
            'type' => 'covid19',
            'required' => false,
            'recommended' => true,
            'reason' => 'health_recommendation',
            'reason_text' => [
                'en' => "Recommended for international travel",
                'fr' => "Recommandé pour les voyages internationaux"
            ],
            'warning_level' => 'low',
            'info' => self::VACCINATION_TYPES['covid19']
        ];

        return $requirements;
    }

    /**
     * Check if nationality requires Yellow Fever vaccination
     *
     * @param string $nationalityCode ISO 3166-1 alpha-2 country code
     * @return bool
     */
    public function requiresYellowFever(string $nationalityCode): bool {
        return isset(self::YELLOW_FEVER_ENDEMIC_COUNTRIES[strtoupper($nationalityCode)]);
    }

    /**
     * Check if any mandatory vaccination is required
     *
     * @param string $nationalityCode ISO 3166-1 alpha-2 country code
     * @return bool
     */
    public function hasRequiredVaccinations(string $nationalityCode): bool {
        return $this->requiresYellowFever($nationalityCode);
    }

    /**
     * Get only the required (mandatory) vaccinations
     *
     * @param string $nationalityCode ISO 3166-1 alpha-2 country code
     * @return array
     */
    public function getRequiredVaccinations(string $nationalityCode): array {
        $requirements = $this->getRequirements($nationalityCode);
        return array_filter($requirements, fn($v) => $v['required'] === true);
    }

    /**
     * Get country name from endemic list
     *
     * @param string $countryCode ISO 3166-1 alpha-2 country code
     * @return string|null Country name or null
     */
    public function getEndemicCountryName(string $countryCode): ?string {
        return self::YELLOW_FEVER_ENDEMIC_COUNTRIES[strtoupper($countryCode)] ?? null;
    }

    /**
     * Format vaccination warning message for display
     *
     * @param string $nationalityCode ISO 3166-1 alpha-2 country code
     * @param string $nationalityName Country name for display
     * @param string $lang Language code (en/fr)
     * @return array|null Warning message or null if no warning needed
     */
    public function getProactiveWarning(string $nationalityCode, string $nationalityName, string $lang = 'fr'): ?array {
        if (!$this->hasRequiredVaccinations($nationalityCode)) {
            return null;
        }

        $requirements = $this->getRequiredVaccinations($nationalityCode);

        $vaccinationList = [];
        foreach ($requirements as $req) {
            $vaccinationList[] = $req['info']['icon'] . ' ' . $req['info']['name'][$lang];
        }

        return [
            'type' => 'vaccination_warning',
            'level' => 'important',
            'nationality' => $nationalityName,
            'nationality_code' => $nationalityCode,
            'vaccinations_required' => array_keys($requirements),
            'vaccinations_list' => $vaccinationList,
            'message' => [
                'fr' => $this->buildWarningMessage($nationalityName, $vaccinationList, 'fr'),
                'en' => $this->buildWarningMessage($nationalityName, $vaccinationList, 'en')
            ]
        ];
    }

    /**
     * Build warning message text
     *
     * @param string $nationality Country name
     * @param array $vaccinations List of required vaccinations
     * @param string $lang Language code
     * @return string
     */
    private function buildWarningMessage(string $nationality, array $vaccinations, string $lang): string {
        $vacList = implode(', ', $vaccinations);

        if ($lang === 'fr') {
            return "⚠️ **Information importante**\n\n" .
                   "En tant que ressortissant(e) de **{$nationality}**, " .
                   "les vaccinations suivantes seront **obligatoires** pour votre demande de visa:\n\n" .
                   "{$vacList}\n\n" .
                   "💉 Assurez-vous d'avoir votre certificat de vaccination prêt pour l'étape de vérification.\n\n" .
                   "Continuons avec votre demande ! 🇨🇮";
        }

        return "⚠️ **Important Information**\n\n" .
               "As a **{$nationality}** national, " .
               "the following vaccinations will be **required** for your visa application:\n\n" .
               "{$vacList}\n\n" .
               "💉 Please make sure you have your vaccination certificate ready for the verification step.\n\n" .
               "Let's continue with your application! 🇨🇮";
    }

    /**
     * Get all endemic countries list
     *
     * @return array
     */
    public function getEndemicCountries(): array {
        return self::YELLOW_FEVER_ENDEMIC_COUNTRIES;
    }

    /**
     * Validate a vaccination certificate date
     *
     * @param string $vaccinationType Type of vaccination
     * @param string $vaccineDate Date of vaccination (Y-m-d)
     * @param string|null $travelDate Date of travel (Y-m-d), null for today
     * @return array Validation result
     */
    public function validateVaccinationDate(
        string $vaccinationType,
        string $vaccineDate,
        ?string $travelDate = null
    ): array {
        $vaccineDateTime = new DateTime($vaccineDate);
        $travelDateTime = $travelDate ? new DateTime($travelDate) : new DateTime();

        $config = self::VACCINATION_TYPES[$vaccinationType] ?? null;

        if (!$config) {
            return [
                'valid' => false,
                'error' => 'Unknown vaccination type'
            ];
        }

        // Check if vaccination is in the future
        if ($vaccineDateTime > new DateTime()) {
            return [
                'valid' => false,
                'error' => [
                    'en' => 'Vaccination date cannot be in the future',
                    'fr' => 'La date de vaccination ne peut pas être dans le futur'
                ]
            ];
        }

        // Check validity period
        if (isset($config['validity_years']) && $config['validity_years'] !== null) {
            $expiryDate = clone $vaccineDateTime;
            $expiryDate->modify("+{$config['validity_years']} years");

            if ($travelDateTime > $expiryDate) {
                return [
                    'valid' => false,
                    'expired' => true,
                    'expiry_date' => $expiryDate->format('Y-m-d'),
                    'error' => [
                        'en' => "Vaccination expired on {$expiryDate->format('M d, Y')}",
                        'fr' => "Vaccination expirée le {$expiryDate->format('d/m/Y')}"
                    ]
                ];
            }
        } elseif (isset($config['validity_months'])) {
            $expiryDate = clone $vaccineDateTime;
            $expiryDate->modify("+{$config['validity_months']} months");

            if ($travelDateTime > $expiryDate) {
                return [
                    'valid' => false,
                    'expired' => true,
                    'expiry_date' => $expiryDate->format('Y-m-d'),
                    'error' => [
                        'en' => "Vaccination expired on {$expiryDate->format('M d, Y')}",
                        'fr' => "Vaccination expirée le {$expiryDate->format('d/m/Y')}"
                    ]
                ];
            }
        }

        // Yellow fever must be administered at least 10 days before travel
        if ($vaccinationType === 'yellow_fever') {
            $minValidDate = clone $vaccineDateTime;
            $minValidDate->modify('+10 days');

            if ($travelDateTime < $minValidDate) {
                return [
                    'valid' => false,
                    'too_recent' => true,
                    'valid_from' => $minValidDate->format('Y-m-d'),
                    'error' => [
                        'en' => "Yellow fever vaccination becomes valid only 10 days after administration (from {$minValidDate->format('M d, Y')})",
                        'fr' => "La vaccination contre la fièvre jaune n'est valide que 10 jours après l'administration (à partir du {$minValidDate->format('d/m/Y')})"
                    ]
                ];
            }
        }

        return [
            'valid' => true,
            'vaccination_date' => $vaccineDate,
            'travel_date' => $travelDateTime->format('Y-m-d')
        ];
    }
}
