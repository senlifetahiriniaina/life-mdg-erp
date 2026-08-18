<?php

declare(strict_types=1);

namespace Modules\Payroll\Data;

/**
 * African statutory payroll scheme definitions (Africa First).
 *
 * Each country exposes:
 *  - `schemes`     : social contribution schemes (employee + employer rates,
 *                    monthly ceilings/plafonds, declaration periodicity, OHADA accounts,
 *                    `category` — 'pension' | 'social_security' | 'health' | 'combined',
 *                    the last used where a single scheme covers everything and can't be
 *                    split further without inventing a percentage this dataset doesn't have)
 *  - `income_tax`  : progressive monthly withholding brackets + abatements
 *
 * All amounts are MONTHLY and expressed in the country currency (XOF/XAF/MGA).
 *
 * OHADA account mapping:
 *  - Cl.66  (661/664) charges de personnel — employer expense
 *  - Cl.43  (431/4313/447…) organismes sociaux & État — liabilities
 *
 * Rates are the standard statutory rates; tenants can override via
 * salary_components where local collective agreements differ.
 */
class StatutorySchemes
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            // ── Sénégal ──────────────────────────────────────────────────────
            'SN' => [
                'name'     => 'Sénégal',
                'currency' => 'XOF',
                'schemes'  => [
                    'ipres' => [
                        'label'          => 'IPRES — Régime de retraite',
                        'agency'         => 'IPRES',
                        'category'       => 'pension',
                        'employee_rate'  => 0.056,
                        'employer_rate'  => 0.084,
                        'ceiling'        => 432_000,
                        'period'         => 'monthly',
                        'ohada_accounts' => ['expense' => '664', 'liability' => '4313'],
                    ],
                    'css' => [
                        'label'          => 'CSS — Sécurité sociale (PF + AT/MP)',
                        'agency'         => 'Caisse de Sécurité Sociale',
                        'category'       => 'social_security',
                        'employee_rate'  => 0.0,
                        'employer_rate'  => 0.10, // 7% prestations familiales + 3% accidents du travail (taux moyen)
                        'ceiling'        => 63_000,
                        'period'         => 'quarterly',
                        'ohada_accounts' => ['expense' => '664', 'liability' => '4311'],
                    ],
                ],
                'income_tax' => [
                    'label'          => 'IR — Impôt sur le Revenu (+ TRIMF)',
                    'agency'         => 'DGID',
                    'period'         => 'monthly',
                    'abatement_rate' => 0.30,      // frais professionnels
                    'abatement_cap'  => 75_000,    // 900 000 XOF/an / 12
                    'fixed_tax'      => 300,       // TRIMF (montant mensuel forfaitaire)
                    'brackets'       => [          // barème annuel / 12
                        [0,         52_500,    0.00],
                        [52_500,    125_000,   0.20],
                        [125_000,   333_333,   0.30],
                        [333_333,   694_167,   0.35],
                        [694_167,   1_125_000, 0.37],
                        [1_125_000, null,      0.40],
                    ],
                    'ohada_accounts' => ['liability' => '447'],
                ],
            ],

            // ── Côte d'Ivoire ───────────────────────────────────────────────
            'CI' => [
                'name'     => "Côte d'Ivoire",
                'currency' => 'XOF',
                'schemes'  => [
                    'cnps_retraite' => [
                        'label'          => 'CNPS — Retraite',
                        'agency'         => 'CNPS',
                        'category'       => 'pension',
                        'employee_rate'  => 0.063,
                        'employer_rate'  => 0.077,
                        'ceiling'        => 3_375_000, // 45 × SMIG (75 000 XOF)
                        'period'         => 'monthly',
                        'ohada_accounts' => ['expense' => '664', 'liability' => '4313'],
                    ],
                    'cnps_pf_at' => [
                        'label'          => 'CNPS — Prestations familiales + AT/MP',
                        'agency'         => 'CNPS',
                        'category'       => 'social_security',
                        'employee_rate'  => 0.0,
                        'employer_rate'  => 0.0875, // 5,75% PF + 3% AT (taux moyen)
                        'ceiling'        => 70_000,
                        'period'         => 'monthly',
                        'ohada_accounts' => ['expense' => '664', 'liability' => '4311'],
                    ],
                ],
                'income_tax' => [
                    'label'          => 'ITS/CN — Impôt sur Traitements et Salaires',
                    'agency'         => 'DGI',
                    'period'         => 'monthly',
                    'abatement_rate' => 0.20, // base imposable = 80 % du brut
                    'abatement_cap'  => null,
                    'fixed_tax'      => 0,
                    'brackets'       => [ // barème mensuel (réforme 2024)
                        [0,         75_000,    0.00],
                        [75_000,    240_000,   0.16],
                        [240_000,   800_000,   0.21],
                        [800_000,   2_400_000, 0.24],
                        [2_400_000, 8_000_000, 0.28],
                        [8_000_000, null,      0.32],
                    ],
                    'ohada_accounts' => ['liability' => '447'],
                ],
            ],

            // ── Cameroun ────────────────────────────────────────────────────
            'CM' => [
                'name'     => 'Cameroun',
                'currency' => 'XAF',
                'schemes'  => [
                    'cnps_pension' => [
                        'label'          => 'CNPS — Pension Vieillesse-Invalidité-Décès',
                        'agency'         => 'CNPS',
                        'category'       => 'pension',
                        'employee_rate'  => 0.042,
                        'employer_rate'  => 0.042,
                        'ceiling'        => 750_000,
                        'period'         => 'monthly',
                        'ohada_accounts' => ['expense' => '664', 'liability' => '4313'],
                    ],
                    'cnps_pf_rp' => [
                        'label'          => 'CNPS — Prestations familiales + Risques professionnels',
                        'agency'         => 'CNPS',
                        'category'       => 'social_security',
                        'employee_rate'  => 0.0,
                        'employer_rate'  => 0.095, // 7% PF + 2,5% RP (groupe B)
                        'ceiling'        => 750_000,
                        'period'         => 'monthly',
                        'ohada_accounts' => ['expense' => '664', 'liability' => '4311'],
                    ],
                ],
                'income_tax' => [
                    'label'          => 'IRPP — Impôt sur le Revenu des Personnes Physiques',
                    'agency'         => 'DGI',
                    'period'         => 'monthly',
                    'abatement_rate' => 0.30,
                    'abatement_cap'  => null,
                    'surcharge_rate' => 0.10, // CAC — Centimes Additionnels Communaux
                    'fixed_tax'      => 0,
                    'brackets'       => [ // barème annuel / 12
                        [0,       166_667, 0.10],
                        [166_667, 250_000, 0.15],
                        [250_000, 416_667, 0.25],
                        [416_667, null,    0.35],
                    ],
                    'ohada_accounts' => ['liability' => '447'],
                ],
            ],

            // ── Madagascar ──────────────────────────────────────────────────
            'MG' => [
                'name'     => 'Madagascar',
                'currency' => 'MGA',
                'schemes'  => [
                    'cnaps' => [
                        'label'          => 'CNaPS — Caisse Nationale de Prévoyance Sociale',
                        'agency'         => 'CNaPS',
                        'category'       => 'pension',
                        'employee_rate'  => 0.01,
                        'employer_rate'  => 0.13,
                        'ceiling'        => 2_000_000, // 8 × salaire minimum
                        'period'         => 'quarterly',
                        'ohada_accounts' => ['expense' => '664', 'liability' => '4313'],
                    ],
                    'ostie' => [
                        'label'          => 'OSTIE — Organisation Sanitaire Inter-Entreprises',
                        'agency'         => 'OSTIE',
                        'category'       => 'health',
                        'employee_rate'  => 0.01,
                        'employer_rate'  => 0.05,
                        'ceiling'        => 2_000_000,
                        'period'         => 'monthly',
                        'ohada_accounts' => ['expense' => '664', 'liability' => '4312'],
                    ],
                ],
                'income_tax' => [
                    'label'          => 'IRSA — Impôt sur les Revenus Salariaux et Assimilés',
                    'agency'         => 'DGI Madagascar',
                    'period'         => 'monthly',
                    'abatement_rate' => 0.0,
                    'abatement_cap'  => null,
                    'minimum_tax'    => 3_000, // IRSA minimum de perception
                    'fixed_tax'      => 0,
                    'brackets'       => [
                        [0,       350_000, 0.00],
                        [350_000, 400_000, 0.05],
                        [400_000, 500_000, 0.10],
                        [500_000, 600_000, 0.15],
                        [600_000, null,    0.20],
                    ],
                    'ohada_accounts' => ['liability' => '447'],
                ],
            ],

            // ── Bénin ───────────────────────────────────────────────────────
            'BJ' => [
                'name'     => 'Bénin',
                'currency' => 'XOF',
                'schemes'  => [
                    'cnss' => [
                        'label'          => 'CNSS — Caisse Nationale de Sécurité Sociale',
                        'agency'         => 'CNSS Bénin',
                        'category'       => 'combined',
                        'employee_rate'  => 0.036,
                        'employer_rate'  => 0.154, // 6,4% vieillesse + 9% PF + AT
                        'ceiling'        => null,
                        'period'         => 'monthly',
                        'ohada_accounts' => ['expense' => '664', 'liability' => '4311'],
                    ],
                ],
                'income_tax' => [
                    'label'          => 'ITS — Impôt sur Traitements et Salaires',
                    'agency'         => 'DGI Bénin',
                    'period'         => 'monthly',
                    'abatement_rate' => 0.0,
                    'abatement_cap'  => null,
                    'fixed_tax'      => 0,
                    'brackets'       => [
                        [0,       60_000,  0.00],
                        [60_000,  150_000, 0.10],
                        [150_000, 250_000, 0.15],
                        [250_000, 500_000, 0.19],
                        [500_000, null,    0.30],
                    ],
                    'ohada_accounts' => ['liability' => '447'],
                ],
            ],

            // ── Togo ────────────────────────────────────────────────────────
            'TG' => [
                'name'     => 'Togo',
                'currency' => 'XOF',
                'schemes'  => [
                    'cnss' => [
                        'label'          => 'CNSS — Caisse Nationale de Sécurité Sociale',
                        'agency'         => 'CNSS Togo',
                        'category'       => 'combined',
                        'employee_rate'  => 0.04,
                        'employer_rate'  => 0.175, // 12,5% pensions + 3% PF + 2% AT
                        'ceiling'        => null,
                        'period'         => 'monthly',
                        'ohada_accounts' => ['expense' => '664', 'liability' => '4311'],
                    ],
                ],
                'income_tax' => [
                    'label'          => 'IRPP — catégorie traitements et salaires',
                    'agency'         => 'OTR',
                    'period'         => 'monthly',
                    'abatement_rate' => 0.0,
                    'abatement_cap'  => null,
                    'fixed_tax'      => 0,
                    'brackets'       => [
                        [0,       75_000,  0.00],
                        [75_000,  166_667, 0.10],
                        [166_667, 375_000, 0.15],
                        [375_000, 833_333, 0.25],
                        [833_333, null,    0.35],
                    ],
                    'ohada_accounts' => ['liability' => '447'],
                ],
            ],

            // ── Burkina Faso ────────────────────────────────────────────────
            'BF' => [
                'name'     => 'Burkina Faso',
                'currency' => 'XOF',
                'schemes'  => [
                    'cnss' => [
                        'label'          => 'CNSS — Caisse Nationale de Sécurité Sociale',
                        'agency'         => 'CNSS Burkina',
                        'category'       => 'combined',
                        'employee_rate'  => 0.055,
                        'employer_rate'  => 0.16,
                        'ceiling'        => 600_000,
                        'period'         => 'monthly',
                        'ohada_accounts' => ['expense' => '664', 'liability' => '4311'],
                    ],
                ],
                'income_tax' => [
                    'label'          => 'IUTS — Impôt Unique sur Traitements et Salaires',
                    'agency'         => 'DGI Burkina',
                    'period'         => 'monthly',
                    'abatement_rate' => 0.0,
                    'abatement_cap'  => null,
                    'fixed_tax'      => 0,
                    'brackets'       => [
                        [0,       30_000,  0.00],
                        [30_000,  50_000,  0.121],
                        [50_000,  80_000,  0.139],
                        [80_000,  120_000, 0.157],
                        [120_000, 170_000, 0.184],
                        [170_000, 250_000, 0.217],
                        [250_000, null,    0.25],
                    ],
                    'ohada_accounts' => ['liability' => '447'],
                ],
            ],

            // ── Mali ────────────────────────────────────────────────────────
            'ML' => [
                'name'     => 'Mali',
                'currency' => 'XOF',
                'schemes'  => [
                    'inps' => [
                        'label'          => 'INPS — Institut National de Prévoyance Sociale',
                        'agency'         => 'INPS',
                        'category'       => 'combined',
                        'employee_rate'  => 0.036,
                        'employer_rate'  => 0.182, // vieillesse + PF + AT + AMO part employeur
                        'ceiling'        => null,
                        'period'         => 'monthly',
                        'ohada_accounts' => ['expense' => '664', 'liability' => '4311'],
                    ],
                ],
                'income_tax' => [
                    'label'          => 'ITS — Impôt sur Traitements et Salaires',
                    'agency'         => 'DGI Mali',
                    'period'         => 'monthly',
                    'abatement_rate' => 0.0,
                    'abatement_cap'  => null,
                    'fixed_tax'      => 0,
                    'brackets'       => [
                        [0,       330_000,   0.00],
                        [330_000, 578_400,   0.05],
                        [578_400, 1_176_400, 0.12],
                        [1_176_400, 1_789_733, 0.18],
                        [1_789_733, 2_384_195, 0.26],
                        [2_384_195, 3_494_130, 0.31],
                        [3_494_130, null,      0.37],
                    ],
                    'ohada_accounts' => ['liability' => '447'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function country(string $code): ?array
    {
        return self::all()[strtoupper($code)] ?? null;
    }

    /** @return string[] */
    public static function supportedCountries(): array
    {
        return array_keys(self::all());
    }
}
