<?php

declare(strict_types=1);

namespace Modules\Reporting\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OhadaReportService
 *
 * Generates SYSCOHADA-compliant financial statements for 17 West/Central African countries
 * operating under OHADA (Organisation pour l'Harmonisation en Afrique du Droit des Affaires).
 *
 * Supported currencies: XOF (UEMOA), XAF (CEMAC), and other African currencies.
 * All labels returned bilingual (French primary, English secondary) per SYSCOHADA standard.
 *
 * OHADA account class mapping:
 *  Cl.1 → Ressources durables (Capitaux propres + Dettes financières)
 *  Cl.2 → Actifs immobilisés (Immobilisations)
 *  Cl.3 → Actifs circulants – Stocks
 *  Cl.4 → Actifs circulants – Créances / Passifs circulants – Dettes
 *  Cl.5 → Trésorerie Actif / Passif
 *  Cl.6 → Charges
 *  Cl.7 → Produits
 */
class OhadaReportService
{
    // TVA rates by country ISO code
    private const TVA_RATES = [
        'SN' => 18.0,   // Sénégal
        'CI' => 18.0,   // Côte d'Ivoire
        'ML' => 18.0,   // Mali
        'BF' => 18.0,   // Burkina Faso
        'BJ' => 18.0,   // Bénin
        'TG' => 18.0,   // Togo
        'NE' => 19.0,   // Niger
        'GN' => 18.0,   // Guinée
        'CM' => 19.25,  // Cameroun
        'GA' => 18.0,   // Gabon
        'CG' => 18.0,   // Congo
        'CD' => 16.0,   // RD Congo
        'CF' => 19.0,   // Centrafrique
        'TD' => 18.0,   // Tchad
        'GQ' => 15.0,   // Guinée équatoriale
        'KM' => 10.0,   // Comores
        'MG' => 20.0,   // Madagascar (SYSCEBNL applicable)
    ];

    // IS (Impôt sur les Sociétés) rates by country
    private const IS_RATES = [
        'SN' => 30.0,
        'CI' => 25.0,
        'CM' => 33.0,
        'BF' => 27.5,
        'ML' => 30.0,
        'GA' => 30.0,
        'CG' => 30.0,
        'SN_MIN' => 0.5,  // Minimum IS (% of CA for SN)
    ];

    // ─── Balance Sheet (Bilan SYSCOHADA) ──────────────────────────────────────

    /**
     * Génère le Bilan SYSCOHADA (classes 1-5).
     * Retourne ACTIF et PASSIF avec comparaison N / N-1.
     *
     * @return array{
     *   report_type: string,
     *   period: string,
     *   currency: string,
     *   generated_at: string,
     *   actif: array,
     *   passif: array,
     *   totaux: array,
     *   equilibre: bool
     * }
     */
    public function generateBalanceSheet(int $tenantId, string $period, string $currency = 'XOF'): array
    {
        [$periodStart, $periodEnd]     = $this->parsePeriod($period);
        [$prevStart,   $prevEnd]       = $this->previousYear($periodStart, $periodEnd);

        $currentBalances  = $this->getAccountBalances($tenantId, $periodEnd);
        $previousBalances = $this->getAccountBalances($tenantId, $prevEnd);

        // ── ACTIF ──────────────────────────────────────────────────────────────
        $actif = [
            'immobilisations' => [
                'label_fr' => 'Actif immobilisé (Classe 2)',
                'label_en' => 'Non-current assets',
                'accounts' => $this->extractAccounts($currentBalances, '2', 'debit'),
                'n_1'      => $this->sumAccounts($previousBalances, '2', 'debit'),
                'total'    => $this->sumAccounts($currentBalances, '2', 'debit'),
                'lines'    => [
                    ['code' => '21', 'label_fr' => 'Immobilisations incorporelles',    'label_en' => 'Intangible assets'],
                    ['code' => '22', 'label_fr' => 'Terrains',                          'label_en' => 'Land'],
                    ['code' => '23', 'label_fr' => 'Bâtiments et agencements',          'label_en' => 'Buildings'],
                    ['code' => '24', 'label_fr' => 'Matériel et mobilier',              'label_en' => 'Equipment & furniture'],
                    ['code' => '25', 'label_fr' => 'Matériel de transport',             'label_en' => 'Transport equipment'],
                    ['code' => '26', 'label_fr' => 'Titres de participation',            'label_en' => 'Equity investments'],
                    ['code' => '28', 'label_fr' => 'Amortissements des immobilisations','label_en' => 'Depreciation (-)'],
                ],
            ],
            'stocks' => [
                'label_fr' => 'Stocks (Classe 3)',
                'label_en' => 'Inventories',
                'accounts' => $this->extractAccounts($currentBalances, '3', 'debit'),
                'n_1'      => $this->sumAccounts($previousBalances, '3', 'debit'),
                'total'    => $this->sumAccounts($currentBalances, '3', 'debit'),
                'lines'    => [
                    ['code' => '31', 'label_fr' => 'Marchandises',           'label_en' => 'Goods for resale'],
                    ['code' => '32', 'label_fr' => 'Matières premières',     'label_en' => 'Raw materials'],
                    ['code' => '33', 'label_fr' => 'En-cours de production', 'label_en' => 'Work in progress'],
                    ['code' => '35', 'label_fr' => 'Produits finis',         'label_en' => 'Finished goods'],
                    ['code' => '39', 'label_fr' => 'Provisions pour stocks', 'label_en' => 'Stock provisions (-)'],
                ],
            ],
            'creances' => [
                'label_fr' => 'Créances (Classe 4 – Actif circulant)',
                'label_en' => 'Receivables',
                'accounts' => $this->extractAccounts($currentBalances, '4', 'debit'),
                'n_1'      => $this->sumAccounts($previousBalances, '4', 'debit'),
                'total'    => $this->sumAccounts($currentBalances, '4', 'debit'),
                'lines'    => [
                    ['code' => '41', 'label_fr' => 'Clients',               'label_en' => 'Trade receivables'],
                    ['code' => '42', 'label_fr' => 'Personnel',             'label_en' => 'Employee receivables'],
                    ['code' => '44', 'label_fr' => 'État et collectivités', 'label_en' => 'Government receivables'],
                    ['code' => '45', 'label_fr' => 'Organismes sociaux',    'label_en' => 'Social org. receivables'],
                    ['code' => '46', 'label_fr' => 'Divers débiteurs',      'label_en' => 'Other receivables'],
                    ['code' => '49', 'label_fr' => 'Provisions créances',   'label_en' => 'Receivable provisions (-)'],
                ],
            ],
            'tresorerie_actif' => [
                'label_fr' => 'Trésorerie – Actif (Classe 5)',
                'label_en' => 'Cash & cash equivalents',
                'accounts' => $this->extractAccounts($currentBalances, '5', 'debit'),
                'n_1'      => $this->sumAccounts($previousBalances, '5', 'debit'),
                'total'    => $this->sumAccounts($currentBalances, '5', 'debit'),
                'lines'    => [
                    ['code' => '51', 'label_fr' => 'Banques',                'label_en' => 'Bank accounts'],
                    ['code' => '52', 'label_fr' => 'Chèques postaux',        'label_en' => 'Postal accounts'],
                    ['code' => '53', 'label_fr' => 'Caisses',                'label_en' => 'Cash on hand'],
                    ['code' => '54', 'label_fr' => 'Mobile money',           'label_en' => 'Mobile money'],
                    ['code' => '57', 'label_fr' => 'Régies d\'avances',      'label_en' => 'Petty cash'],
                    ['code' => '59', 'label_fr' => 'Provisions trésorerie',  'label_en' => 'Cash provisions (-)'],
                ],
            ],
        ];

        // ── PASSIF ─────────────────────────────────────────────────────────────
        $passif = [
            'capitaux_propres' => [
                'label_fr' => 'Capitaux propres (Classe 1)',
                'label_en' => 'Shareholders equity',
                'accounts' => $this->extractAccounts($currentBalances, '1', 'credit'),
                'n_1'      => $this->sumAccounts($previousBalances, '1', 'credit'),
                'total'    => $this->sumAccounts($currentBalances, '1', 'credit'),
                'lines'    => [
                    ['code' => '10', 'label_fr' => 'Capital social',           'label_en' => 'Share capital'],
                    ['code' => '11', 'label_fr' => 'Réserves',                 'label_en' => 'Retained earnings'],
                    ['code' => '12', 'label_fr' => 'Report à nouveau',         'label_en' => 'Carried forward result'],
                    ['code' => '13', 'label_fr' => 'Résultat de l\'exercice',  'label_en' => 'Net profit/loss'],
                    ['code' => '14', 'label_fr' => 'Subventions d\'inv.',      'label_en' => 'Investment grants'],
                    ['code' => '15', 'label_fr' => 'Provisions réglementées', 'label_en' => 'Regulated provisions'],
                ],
            ],
            'dettes_financieres' => [
                'label_fr' => 'Dettes financières (Classe 1 – long terme)',
                'label_en' => 'Financial debts (long-term)',
                'accounts' => $this->extractAccounts($currentBalances, '16', 'credit'),
                'n_1'      => $this->sumAccounts($previousBalances, '16', 'credit'),
                'total'    => $this->sumAccounts($currentBalances, '16', 'credit'),
                'lines'    => [
                    ['code' => '16', 'label_fr' => 'Emprunts et dettes assimilées', 'label_en' => 'Loans & borrowings'],
                    ['code' => '17', 'label_fr' => 'Dettes de crédit-bail',         'label_en' => 'Lease liabilities'],
                    ['code' => '18', 'label_fr' => 'Dettes des associés',           'label_en' => 'Partner loans'],
                    ['code' => '19', 'label_fr' => 'Provisions fin. pour risques',  'label_en' => 'Financial provisions'],
                ],
            ],
            'dettes_circulantes' => [
                'label_fr' => 'Passif circulant (Classe 4 – Dettes)',
                'label_en' => 'Current liabilities',
                'accounts' => $this->extractAccounts($currentBalances, '4', 'credit'),
                'n_1'      => $this->sumAccounts($previousBalances, '4', 'credit'),
                'total'    => $this->sumAccounts($currentBalances, '4', 'credit'),
                'lines'    => [
                    ['code' => '40', 'label_fr' => 'Fournisseurs',                  'label_en' => 'Trade payables'],
                    ['code' => '42', 'label_fr' => 'Personnel – dettes',            'label_en' => 'Employee liabilities'],
                    ['code' => '43', 'label_fr' => 'Organismes sociaux – dettes',   'label_en' => 'Social charges payable'],
                    ['code' => '44', 'label_fr' => 'État – TVA et impôts',          'label_en' => 'VAT & taxes payable'],
                    ['code' => '47', 'label_fr' => 'Divers créditeurs',             'label_en' => 'Other payables'],
                ],
            ],
            'tresorerie_passif' => [
                'label_fr' => 'Trésorerie – Passif (Classe 5)',
                'label_en' => 'Bank overdrafts & short-term borrowings',
                'accounts' => $this->extractAccounts($currentBalances, '5', 'credit'),
                'n_1'      => $this->sumAccounts($previousBalances, '5', 'credit'),
                'total'    => $this->sumAccounts($currentBalances, '5', 'credit'),
                'lines'    => [
                    ['code' => '56', 'label_fr' => 'Découverts bancaires',    'label_en' => 'Bank overdrafts'],
                    ['code' => '58', 'label_fr' => 'Virements internes',      'label_en' => 'Internal transfers'],
                ],
            ],
        ];

        $totalActif  = collect($actif)->sum('total');
        $totalPassif = collect($passif)->sum('total');

        return [
            'report_type'  => 'bilan_syscohada',
            'label_fr'     => 'Bilan SYSCOHADA',
            'label_en'     => 'SYSCOHADA Balance Sheet',
            'period'       => $period,
            'period_start' => $periodStart,
            'period_end'   => $periodEnd,
            'currency'     => $currency,
            'tenant_id'    => $tenantId,
            'generated_at' => now()->toIso8601String(),
            'actif'        => $actif,
            'passif'       => $passif,
            'totaux'       => [
                'total_actif'       => $totalActif,
                'total_passif'      => $totalPassif,
                'total_actif_n1'    => collect($actif)->sum('n_1'),
                'total_passif_n1'   => collect($passif)->sum('n_1'),
            ],
            'equilibre'    => abs($totalActif - $totalPassif) < 1,  // OHADA: actif = passif
        ];
    }

    // ─── Income Statement (Compte de Résultat SYSCOHADA) ──────────────────────

    /**
     * Génère le Compte de Résultat SYSCOHADA (classes 6 et 7).
     *
     * @return array
     */
    public function generateIncomeStatement(int $tenantId, string $period, string $currency = 'XOF'): array
    {
        [$periodStart, $periodEnd] = $this->parsePeriod($period);
        [$prevStart,   $prevEnd]   = $this->previousYear($periodStart, $periodEnd);

        $currentMovements  = $this->getPeriodMovements($tenantId, $periodStart, $periodEnd);
        $previousMovements = $this->getPeriodMovements($tenantId, $prevStart, $prevEnd);

        // ── PRODUITS (Classe 7) ────────────────────────────────────────────────
        $produits = [
            [
                'code' => '70', 'label_fr' => 'Ventes de marchandises',
                'label_en'   => 'Goods sales',
                'current'    => $this->sumMovements($currentMovements, '70', 'credit'),
                'previous'   => $this->sumMovements($previousMovements, '70', 'credit'),
            ],
            [
                'code' => '71', 'label_fr' => 'Ventes de produits fabriqués',
                'label_en'   => 'Manufactured goods sales',
                'current'    => $this->sumMovements($currentMovements, '71', 'credit'),
                'previous'   => $this->sumMovements($previousMovements, '71', 'credit'),
            ],
            [
                'code' => '72', 'label_fr' => 'Travaux, services vendus',
                'label_en'   => 'Services rendered',
                'current'    => $this->sumMovements($currentMovements, '72', 'credit'),
                'previous'   => $this->sumMovements($previousMovements, '72', 'credit'),
            ],
            [
                'code' => '73', 'label_fr' => 'Variation de stocks de produits finis',
                'label_en'   => 'Change in finished goods stock',
                'current'    => $this->sumMovements($currentMovements, '73', 'credit'),
                'previous'   => $this->sumMovements($previousMovements, '73', 'credit'),
            ],
            [
                'code' => '74', 'label_fr' => 'Production immobilisée',
                'label_en'   => 'Own-account production',
                'current'    => $this->sumMovements($currentMovements, '74', 'credit'),
                'previous'   => $this->sumMovements($previousMovements, '74', 'credit'),
            ],
            [
                'code' => '75', 'label_fr' => 'Autres produits',
                'label_en'   => 'Other income',
                'current'    => $this->sumMovements($currentMovements, '75', 'credit'),
                'previous'   => $this->sumMovements($previousMovements, '75', 'credit'),
            ],
            [
                'code' => '77', 'label_fr' => 'Produits financiers',
                'label_en'   => 'Financial income',
                'current'    => $this->sumMovements($currentMovements, '77', 'credit'),
                'previous'   => $this->sumMovements($previousMovements, '77', 'credit'),
            ],
            [
                'code' => '78', 'label_fr' => 'Reprises de provisions',
                'label_en'   => 'Provision reversals',
                'current'    => $this->sumMovements($currentMovements, '78', 'credit'),
                'previous'   => $this->sumMovements($previousMovements, '78', 'credit'),
            ],
            [
                'code' => '79', 'label_fr' => 'Transferts de charges',
                'label_en'   => 'Expense transfers',
                'current'    => $this->sumMovements($currentMovements, '79', 'credit'),
                'previous'   => $this->sumMovements($previousMovements, '79', 'credit'),
            ],
        ];

        // ── CHARGES (Classe 6) ─────────────────────────────────────────────────
        $charges = [
            [
                'code' => '60', 'label_fr' => 'Achats de marchandises',
                'label_en'   => 'Goods purchased',
                'current'    => $this->sumMovements($currentMovements, '60', 'debit'),
                'previous'   => $this->sumMovements($previousMovements, '60', 'debit'),
            ],
            [
                'code' => '61', 'label_fr' => 'Transports',
                'label_en'   => 'Transport costs',
                'current'    => $this->sumMovements($currentMovements, '61', 'debit'),
                'previous'   => $this->sumMovements($previousMovements, '61', 'debit'),
            ],
            [
                'code' => '62', 'label_fr' => 'Services extérieurs A',
                'label_en'   => 'External services A',
                'current'    => $this->sumMovements($currentMovements, '62', 'debit'),
                'previous'   => $this->sumMovements($previousMovements, '62', 'debit'),
            ],
            [
                'code' => '63', 'label_fr' => 'Services extérieurs B',
                'label_en'   => 'External services B',
                'current'    => $this->sumMovements($currentMovements, '63', 'debit'),
                'previous'   => $this->sumMovements($previousMovements, '63', 'debit'),
            ],
            [
                'code' => '64', 'label_fr' => 'Impôts et taxes',
                'label_en'   => 'Taxes & duties',
                'current'    => $this->sumMovements($currentMovements, '64', 'debit'),
                'previous'   => $this->sumMovements($previousMovements, '64', 'debit'),
            ],
            [
                'code' => '65', 'label_fr' => 'Autres charges',
                'label_en'   => 'Other charges',
                'current'    => $this->sumMovements($currentMovements, '65', 'debit'),
                'previous'   => $this->sumMovements($previousMovements, '65', 'debit'),
            ],
            [
                'code' => '66', 'label_fr' => 'Charges de personnel',
                'label_en'   => 'Payroll costs',
                'current'    => $this->sumMovements($currentMovements, '66', 'debit'),
                'previous'   => $this->sumMovements($previousMovements, '66', 'debit'),
            ],
            [
                'code' => '67', 'label_fr' => 'Charges financières',
                'label_en'   => 'Financial charges',
                'current'    => $this->sumMovements($currentMovements, '67', 'debit'),
                'previous'   => $this->sumMovements($previousMovements, '67', 'debit'),
            ],
            [
                'code' => '68', 'label_fr' => 'Dotations aux amortissements',
                'label_en'   => 'Depreciation & amortisation',
                'current'    => $this->sumMovements($currentMovements, '68', 'debit'),
                'previous'   => $this->sumMovements($previousMovements, '68', 'debit'),
            ],
            [
                'code' => '69', 'label_fr' => 'Impôts sur les bénéfices (IS)',
                'label_en'   => 'Corporate income tax',
                'current'    => $this->sumMovements($currentMovements, '69', 'debit'),
                'previous'   => $this->sumMovements($previousMovements, '69', 'debit'),
            ],
        ];

        $totalProduits       = collect($produits)->sum('current');
        $totalCharges        = collect($charges)->sum('current');
        $totalProduitsPrev   = collect($produits)->sum('previous');
        $totalChargesPrev    = collect($charges)->sum('previous');
        $chiffreAffaires     = $this->sumMovements($currentMovements, '70', 'credit')
                             + $this->sumMovements($currentMovements, '71', 'credit')
                             + $this->sumMovements($currentMovements, '72', 'credit');

        return [
            'report_type'     => 'compte_de_resultat_syscohada',
            'label_fr'        => 'Compte de Résultat SYSCOHADA',
            'label_en'        => 'SYSCOHADA Income Statement',
            'period'          => $period,
            'period_start'    => $periodStart,
            'period_end'      => $periodEnd,
            'currency'        => $currency,
            'tenant_id'       => $tenantId,
            'generated_at'    => now()->toIso8601String(),
            'produits'        => $produits,
            'charges'         => $charges,
            'totaux' => [
                'total_produits'           => $totalProduits,
                'total_charges'            => $totalCharges,
                'chiffre_affaires'         => $chiffreAffaires,
                'resultat_net'             => $totalProduits - $totalCharges,
                'total_produits_n1'        => $totalProduitsPrev,
                'total_charges_n1'         => $totalChargesPrev,
                'resultat_net_n1'          => $totalProduitsPrev - $totalChargesPrev,
                'marge_brute'              => $chiffreAffaires
                    - $this->sumMovements($currentMovements, '60', 'debit'),
                'resultat_exploitation'    => $totalProduits
                    - collect($charges)->whereNotIn('code', ['67', '69'])->sum('current'),
                'resultat_financier'       => $this->sumMovements($currentMovements, '77', 'credit')
                    - $this->sumMovements($currentMovements, '67', 'debit'),
            ],
        ];
    }

    // ─── Trial Balance (Balance Générale) ─────────────────────────────────────

    /**
     * Génère la Balance Générale SYSCOHADA — tous les comptes du plan comptable.
     *
     * @return array
     */
    public function generateTrialBalance(int $tenantId, string $period): array
    {
        [$periodStart, $periodEnd] = $this->parsePeriod($period);

        $rows = $this->getTrialBalanceRows($tenantId, $periodEnd);

        $totalDebitMvt   = collect($rows)->sum('debit_mouvement');
        $totalCreditMvt  = collect($rows)->sum('credit_mouvement');
        $totalSoldeDebit = collect($rows)->sum('solde_debiteur');
        $totalSoldeCred  = collect($rows)->sum('solde_crediteur');

        return [
            'report_type'     => 'balance_generale',
            'label_fr'        => 'Balance Générale des Comptes',
            'label_en'        => 'Trial Balance',
            'period'          => $period,
            'period_end'      => $periodEnd,
            'tenant_id'       => $tenantId,
            'generated_at'    => now()->toIso8601String(),
            'lignes'          => $rows,
            'totaux'          => [
                'debit_mouvements'  => $totalDebitMvt,
                'credit_mouvements' => $totalCreditMvt,
                'soldes_debiteurs'  => $totalSoldeDebit,
                'soldes_crediteurs' => $totalSoldeCred,
                'equilibre_mvt'     => abs($totalDebitMvt - $totalCreditMvt) < 1,
                'equilibre_soldes'  => abs($totalSoldeDebit - $totalSoldeCred) < 1,
            ],
        ];
    }

    // ─── General Ledger (Grand Livre) ─────────────────────────────────────────

    /**
     * Génère le Grand Livre pour un compte spécifique.
     *
     * @return array
     */
    public function generateGeneralLedger(int $tenantId, string $accountCode, string $period): array
    {
        [$periodStart, $periodEnd] = $this->parsePeriod($period);

        $entries = $this->getJournalEntries($tenantId, $accountCode, $periodStart, $periodEnd);

        $totalDebit  = collect($entries)->sum('debit');
        $totalCredit = collect($entries)->sum('credit');

        return [
            'report_type'   => 'grand_livre',
            'label_fr'      => 'Grand Livre du compte ' . $accountCode,
            'label_en'      => 'General Ledger – account ' . $accountCode,
            'account_code'  => $accountCode,
            'period'        => $period,
            'period_start'  => $periodStart,
            'period_end'    => $periodEnd,
            'tenant_id'     => $tenantId,
            'generated_at'  => now()->toIso8601String(),
            'ecritures'     => $entries,
            'totaux'        => [
                'total_debit'  => $totalDebit,
                'total_credit' => $totalCredit,
                'solde'        => $totalDebit - $totalCredit,
            ],
        ];
    }

    // ─── Day Book (Journal) ────────────────────────────────────────────────────

    /**
     * Génère un Journal comptable (ventes / achats / caisse / banque / OD).
     *
     * @param string $journalType  ventes|achats|caisse|banque|opérations_diverses
     * @return array
     */
    public function generateJournal(int $tenantId, string $journalType, string $period): array
    {
        [$periodStart, $periodEnd] = $this->parsePeriod($period);

        $journalLabels = [
            'ventes'               => ['fr' => 'Journal des Ventes',                 'en' => 'Sales Journal'],
            'achats'               => ['fr' => 'Journal des Achats',                 'en' => 'Purchases Journal'],
            'caisse'               => ['fr' => 'Journal de Caisse',                  'en' => 'Cash Journal'],
            'banque'               => ['fr' => 'Journal de Banque',                  'en' => 'Bank Journal'],
            'opérations_diverses'  => ['fr' => 'Journal des Opérations Diverses',   'en' => 'Miscellaneous Journal'],
        ];

        $entries = $this->getJournalByType($tenantId, $journalType, $periodStart, $periodEnd);

        $totalDebit  = collect($entries)->sum('debit');
        $totalCredit = collect($entries)->sum('credit');

        return [
            'report_type'   => 'journal_' . $journalType,
            'label_fr'      => $journalLabels[$journalType]['fr'] ?? 'Journal ' . $journalType,
            'label_en'      => $journalLabels[$journalType]['en'] ?? $journalType . ' journal',
            'journal_type'  => $journalType,
            'period'        => $period,
            'period_start'  => $periodStart,
            'period_end'    => $periodEnd,
            'tenant_id'     => $tenantId,
            'generated_at'  => now()->toIso8601String(),
            'ecritures'     => $entries,
            'totaux'        => [
                'total_debit'  => $totalDebit,
                'total_credit' => $totalCredit,
                'equilibre'    => abs($totalDebit - $totalCredit) < 1,
            ],
        ];
    }

    // ─── Aged Receivables (Balance âgée clients) ──────────────────────────────

    /**
     * Balance âgée clients — standard UEMOA avec 4 tranches d'ancienneté.
     *
     * @return array
     */
    public function generateAgedReceivables(int $tenantId, string $asOfDate): array
    {
        $asOf = \Carbon\Carbon::parse($asOfDate);

        $clients = $this->getOutstandingReceivables($tenantId, $asOfDate);

        $buckets = [
            '0_30'  => ['label_fr' => '0 – 30 jours',   'label_en' => '0 – 30 days',   'total' => 0, 'lines' => []],
            '31_60' => ['label_fr' => '31 – 60 jours',  'label_en' => '31 – 60 days',  'total' => 0, 'lines' => []],
            '61_90' => ['label_fr' => '61 – 90 jours',  'label_en' => '61 – 90 days',  'total' => 0, 'lines' => []],
            '90p'   => ['label_fr' => '> 90 jours',     'label_en' => '> 90 days',     'total' => 0, 'lines' => []],
        ];

        $grandTotal = 0;

        foreach ($clients as $client) {
            $dueDate = \Carbon\Carbon::parse($client['due_date'] ?? $client['invoice_date']);
            $days    = (int) $asOf->diffInDays($dueDate, false) * -1;
            $days    = max(0, $days);
            $amount  = (float) ($client['balance'] ?? 0);

            $key = match (true) {
                $days <= 30  => '0_30',
                $days <= 60  => '31_60',
                $days <= 90  => '61_90',
                default      => '90p',
            };

            $buckets[$key]['lines'][] = array_merge($client, ['days_overdue' => $days]);
            $buckets[$key]['total']  += $amount;
            $grandTotal              += $amount;
        }

        return [
            'report_type'   => 'balance_agee_clients',
            'label_fr'      => 'Balance Âgée Clients',
            'label_en'      => 'Aged Receivables',
            'as_of_date'    => $asOfDate,
            'tenant_id'     => $tenantId,
            'generated_at'  => now()->toIso8601String(),
            'tranches'      => $buckets,
            'total_general' => $grandTotal,
            'nombre_clients' => count($clients),
        ];
    }

    // ─── Aged Payables (Balance âgée fournisseurs) ────────────────────────────

    /**
     * Balance âgée fournisseurs.
     *
     * @return array
     */
    public function generateAgedPayables(int $tenantId, string $asOfDate): array
    {
        $asOf        = \Carbon\Carbon::parse($asOfDate);
        $fournisseurs = $this->getOutstandingPayables($tenantId, $asOfDate);

        $buckets = [
            '0_30'  => ['label_fr' => '0 – 30 jours',  'label_en' => '0 – 30 days',  'total' => 0, 'lines' => []],
            '31_60' => ['label_fr' => '31 – 60 jours', 'label_en' => '31 – 60 days', 'total' => 0, 'lines' => []],
            '61_90' => ['label_fr' => '61 – 90 jours', 'label_en' => '61 – 90 days', 'total' => 0, 'lines' => []],
            '90p'   => ['label_fr' => '> 90 jours',    'label_en' => '> 90 days',    'total' => 0, 'lines' => []],
        ];

        $grandTotal = 0;

        foreach ($fournisseurs as $fournisseur) {
            $dueDate = \Carbon\Carbon::parse($fournisseur['due_date'] ?? $fournisseur['invoice_date']);
            $days    = (int) $asOf->diffInDays($dueDate, false) * -1;
            $days    = max(0, $days);
            $amount  = (float) ($fournisseur['balance'] ?? 0);

            $key = match (true) {
                $days <= 30  => '0_30',
                $days <= 60  => '31_60',
                $days <= 90  => '61_90',
                default      => '90p',
            };

            $buckets[$key]['lines'][] = array_merge($fournisseur, ['days_overdue' => $days]);
            $buckets[$key]['total']  += $amount;
            $grandTotal              += $amount;
        }

        return [
            'report_type'        => 'balance_agee_fournisseurs',
            'label_fr'           => 'Balance Âgée Fournisseurs',
            'label_en'           => 'Aged Payables',
            'as_of_date'         => $asOfDate,
            'tenant_id'          => $tenantId,
            'generated_at'       => now()->toIso8601String(),
            'tranches'           => $buckets,
            'total_general'      => $grandTotal,
            'nombre_fournisseurs' => count($fournisseurs),
        ];
    }

    // ─── TVA Report ────────────────────────────────────────────────────────────

    /**
     * Rapport TVA — déclaration mensuelle ou trimestrielle.
     * Taux par pays : SN 18%, CI 18%, CM 19.25%, MG 20%, etc.
     *
     * @return array
     */
    public function generateTvaReport(int $tenantId, string $period): array
    {
        [$periodStart, $periodEnd] = $this->parsePeriod($period);

        $countryCode = $this->getTenantCountry($tenantId);
        $tvaRate     = self::TVA_RATES[$countryCode] ?? 18.0;

        $movements = $this->getPeriodMovements($tenantId, $periodStart, $periodEnd);

        // TVA collectée (compte 4431 / 4435)
        $tvaCollectee   = $this->sumAccountByCode($tenantId, '4431', $periodStart, $periodEnd)
                        + $this->sumAccountByCode($tenantId, '4435', $periodStart, $periodEnd);

        // TVA déductible sur immobilisations (compte 4452)
        $tvaDedImmob    = $this->sumAccountByCode($tenantId, '4452', $periodStart, $periodEnd);

        // TVA déductible sur charges (compte 4455)
        $tvaDedCharges  = $this->sumAccountByCode($tenantId, '4455', $periodStart, $periodEnd);

        $tvaDeductible  = $tvaDedImmob + $tvaDedCharges;
        $tvaADecaisser  = $tvaCollectee - $tvaDeductible;
        $creditTva      = $tvaADecaisser < 0 ? abs($tvaADecaisser) : 0;

        return [
            'report_type'       => 'rapport_tva',
            'label_fr'          => 'Déclaration de TVA',
            'label_en'          => 'VAT Return',
            'period'            => $period,
            'period_start'      => $periodStart,
            'period_end'        => $periodEnd,
            'country_code'      => $countryCode,
            'tva_rate_pct'      => $tvaRate,
            'tenant_id'         => $tenantId,
            'generated_at'      => now()->toIso8601String(),
            'tva_collectee' => [
                'label_fr'  => 'TVA collectée (ventes)',
                'label_en'  => 'Output VAT',
                'compte_fr' => 'Compte 4431 / 4435',
                'montant'   => $tvaCollectee,
            ],
            'tva_deductible' => [
                'label_fr'          => 'TVA déductible (achats)',
                'label_en'          => 'Input VAT',
                'sur_immobilisations' => $tvaDedImmob,
                'sur_charges'         => $tvaDedCharges,
                'total'              => $tvaDeductible,
            ],
            'solde' => [
                'tva_a_decaisser'   => max(0, $tvaADecaisser),
                'credit_de_tva'     => $creditTva,
                'label_fr'          => $tvaADecaisser >= 0 ? 'TVA à décaisser' : 'Crédit de TVA',
                'label_en'          => $tvaADecaisser >= 0 ? 'VAT payable' : 'VAT credit',
            ],
        ];
    }

    // ─── IS Report (Impôt sur les Sociétés) ────────────────────────────────────

    /**
     * Calcule l'Impôt sur les Sociétés selon les règles OHADA/UEMOA.
     *
     * @return array
     */
    public function generateIsReport(int $tenantId, string $fiscalYear): array
    {
        $periodStart = $fiscalYear . '-01-01';
        $periodEnd   = $fiscalYear . '-12-31';

        $income = $this->generateIncomeStatement($tenantId, $fiscalYear, 'XOF');

        $countryCode    = $this->getTenantCountry($tenantId);
        $isRate         = self::IS_RATES[$countryCode] ?? 30.0;
        $minRate        = self::IS_RATES[$countryCode . '_MIN'] ?? null;

        $resultatAvantIs       = $income['totaux']['resultat_net'] ?? 0;
        $baseImposable         = max(0, $resultatAvantIs);
        $isTheorique           = $baseImposable * ($isRate / 100);
        $chiffreAffaires       = $income['totaux']['chiffre_affaires'] ?? 0;
        $isMinimum             = $minRate ? $chiffreAffaires * ($minRate / 100) : 0;
        $isDu                  = max($isTheorique, $isMinimum);

        return [
            'report_type'           => 'rapport_is',
            'label_fr'              => 'Impôt sur les Sociétés (IS)',
            'label_en'              => 'Corporate Income Tax',
            'fiscal_year'           => $fiscalYear,
            'period_start'          => $periodStart,
            'period_end'            => $periodEnd,
            'country_code'          => $countryCode,
            'taux_is_pct'           => $isRate,
            'taux_is_minimum_pct'   => $minRate,
            'tenant_id'             => $tenantId,
            'generated_at'          => now()->toIso8601String(),
            'resultat_avant_is'     => $resultatAvantIs,
            'base_imposable'        => $baseImposable,
            'is_theorique'          => $isTheorique,
            'is_minimum'            => $isMinimum,
            'is_du'                 => $isDu,
            'chiffre_affaires'      => $chiffreAffaires,
        ];
    }

    // ─── NGO/SYSCEBNL Report ───────────────────────────────────────────────────

    /**
     * Rapport SYSCEBNL pour les entités sans but lucratif (ONG, associations).
     * Utilisé notamment à Madagascar, Sénégal, Côte d'Ivoire.
     *
     * @return array
     */
    public function generateNgoReport(int $tenantId, string $period): array
    {
        [$periodStart, $periodEnd] = $this->parsePeriod($period);

        // For NGOs: "recettes" instead of "produits", "dépenses" instead of "charges"
        $movements = $this->getPeriodMovements($tenantId, $periodStart, $periodEnd);

        $recettes = [
            ['code' => '71', 'label_fr' => 'Cotisations des membres',       'montant' => $this->sumMovements($movements, '71', 'credit')],
            ['code' => '74', 'label_fr' => 'Subventions reçues',            'montant' => $this->sumMovements($movements, '74', 'credit')],
            ['code' => '75', 'label_fr' => 'Dons et legs',                  'montant' => $this->sumMovements($movements, '75', 'credit')],
            ['code' => '77', 'label_fr' => 'Produits financiers',           'montant' => $this->sumMovements($movements, '77', 'credit')],
            ['code' => '70', 'label_fr' => 'Prestations de services',       'montant' => $this->sumMovements($movements, '70', 'credit')],
        ];

        $depenses = [
            ['code' => '60', 'label_fr' => 'Achats consommés',              'montant' => $this->sumMovements($movements, '60', 'debit')],
            ['code' => '66', 'label_fr' => 'Charges de personnel',          'montant' => $this->sumMovements($movements, '66', 'debit')],
            ['code' => '62', 'label_fr' => 'Services extérieurs',           'montant' => $this->sumMovements($movements, '62', 'debit')],
            ['code' => '68', 'label_fr' => 'Amortissements',                'montant' => $this->sumMovements($movements, '68', 'debit')],
            ['code' => '65', 'label_fr' => 'Autres charges',                'montant' => $this->sumMovements($movements, '65', 'debit')],
        ];

        $totalRecettes = collect($recettes)->sum('montant');
        $totalDepenses = collect($depenses)->sum('montant');

        return [
            'report_type'    => 'syscebnl_rapport',
            'label_fr'       => 'Compte de Résultat SYSCEBNL (ONG/Association)',
            'label_en'       => 'SYSCEBNL Income Statement (NGO/Non-profit)',
            'period'         => $period,
            'period_start'   => $periodStart,
            'period_end'     => $periodEnd,
            'tenant_id'      => $tenantId,
            'generated_at'   => now()->toIso8601String(),
            'recettes'       => $recettes,
            'depenses'       => $depenses,
            'totaux'         => [
                'total_recettes'   => $totalRecettes,
                'total_depenses'   => $totalDepenses,
                'excedent_deficit' => $totalRecettes - $totalDepenses,
            ],
        ];
    }

    // ─── Private helpers ───────────────────────────────────────────────────────

    /**
     * Parses a period string into [start, end] dates.
     * Supports: 2026, 2026-Q1, 2026-Q2, 2026-03, 2026-01-01
     */
    private function parsePeriod(string $period): array
    {
        if (preg_match('/^(\d{4})-Q([1-4])$/', $period, $m)) {
            $q     = (int) $m[2];
            $year  = $m[1];
            $start = \Carbon\Carbon::create($year, ($q - 1) * 3 + 1, 1)->startOfMonth();
            $end   = $start->copy()->addMonths(2)->endOfMonth();
            return [$start->toDateString(), $end->toDateString()];
        }

        if (preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) {
            $date = \Carbon\Carbon::create($m[1], $m[2], 1);
            return [$date->startOfMonth()->toDateString(), $date->endOfMonth()->toDateString()];
        }

        if (preg_match('/^(\d{4})$/', $period, $m)) {
            return [$m[1] . '-01-01', $m[1] . '-12-31'];
        }

        // Already a full date
        return [$period, $period];
    }

    private function previousYear(string $start, string $end): array
    {
        $s = \Carbon\Carbon::parse($start)->subYear();
        $e = \Carbon\Carbon::parse($end)->subYear();
        return [$s->toDateString(), $e->toDateString()];
    }

    /**
     * Returns cumulative account balances as of a given date for a tenant.
     * Falls back to empty array if accounting tables don't exist (graceful degradation).
     *
     * @return array<string, array{debit: float, credit: float}>
     */
    private function getAccountBalances(int $tenantId, string $asOfDate): array
    {
        try {
            $rows = DB::table('accounting_journal_lines as jl')
                ->join('accounting_journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
                ->where('je.tenant_id', $tenantId)
                ->whereDate('je.entry_date', '<=', $asOfDate)
                ->where('je.status', 'posted')
                ->select(
                    'jl.account_code',
                    DB::raw('SUM(jl.debit_amount) as total_debit'),
                    DB::raw('SUM(jl.credit_amount) as total_credit'),
                )
                ->groupBy('jl.account_code')
                ->get();

            $result = [];
            foreach ($rows as $row) {
                $result[$row->account_code] = [
                    'debit'  => (float) $row->total_debit,
                    'credit' => (float) $row->total_credit,
                ];
            }
            return $result;
        } catch (\Exception $e) {
            Log::warning('OhadaReportService: accounting tables unavailable', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Returns period movements (only entries within the period, not cumulative).
     */
    private function getPeriodMovements(int $tenantId, string $start, string $end): array
    {
        try {
            $rows = DB::table('accounting_journal_lines as jl')
                ->join('accounting_journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
                ->where('je.tenant_id', $tenantId)
                ->whereDate('je.entry_date', '>=', $start)
                ->whereDate('je.entry_date', '<=', $end)
                ->where('je.status', 'posted')
                ->select(
                    'jl.account_code',
                    DB::raw('SUM(jl.debit_amount) as total_debit'),
                    DB::raw('SUM(jl.credit_amount) as total_credit'),
                )
                ->groupBy('jl.account_code')
                ->get();

            $result = [];
            foreach ($rows as $row) {
                $result[$row->account_code] = [
                    'debit'  => (float) $row->total_debit,
                    'credit' => (float) $row->total_credit,
                ];
            }
            return $result;
        } catch (\Exception $e) {
            Log::warning('OhadaReportService: accounting tables unavailable for movements', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Extracts matching accounts from balances for a given prefix and side.
     *
     * @param array<string, array{debit: float, credit: float}> $balances
     * @return array
     */
    private function extractAccounts(array $balances, string $prefix, string $side): array
    {
        $lines = [];
        foreach ($balances as $code => $amounts) {
            if (str_starts_with($code, $prefix)) {
                $net = $side === 'debit'
                    ? max(0, $amounts['debit'] - $amounts['credit'])
                    : max(0, $amounts['credit'] - $amounts['debit']);

                if ($net > 0) {
                    $lines[] = ['account_code' => $code, 'montant' => $net];
                }
            }
        }
        return $lines;
    }

    /**
     * Sums balances for accounts starting with the given prefix on the specified side.
     *
     * @param array<string, array{debit: float, credit: float}> $balances
     */
    private function sumAccounts(array $balances, string $prefix, string $side): float
    {
        $total = 0.0;
        foreach ($balances as $code => $amounts) {
            if (str_starts_with($code, $prefix)) {
                $net = $side === 'debit'
                    ? max(0, $amounts['debit'] - $amounts['credit'])
                    : max(0, $amounts['credit'] - $amounts['debit']);
                $total += $net;
            }
        }
        return $total;
    }

    /**
     * Sums movements for accounts starting with the given prefix.
     *
     * @param array<string, array{debit: float, credit: float}> $movements
     */
    private function sumMovements(array $movements, string $prefix, string $side): float
    {
        $total = 0.0;
        foreach ($movements as $code => $amounts) {
            if (str_starts_with($code, $prefix)) {
                $total += $side === 'debit' ? $amounts['debit'] : $amounts['credit'];
            }
        }
        return $total;
    }

    private function sumAccountByCode(int $tenantId, string $accountCode, string $start, string $end): float
    {
        try {
            $result = DB::table('accounting_journal_lines as jl')
                ->join('accounting_journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
                ->where('je.tenant_id', $tenantId)
                ->where('jl.account_code', $accountCode)
                ->whereDate('je.entry_date', '>=', $start)
                ->whereDate('je.entry_date', '<=', $end)
                ->where('je.status', 'posted')
                ->value(DB::raw('SUM(jl.credit_amount - jl.debit_amount)'));

            return (float) ($result ?? 0);
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    private function getTrialBalanceRows(int $tenantId, string $asOfDate): array
    {
        try {
            $rows = DB::table('accounting_journal_lines as jl')
                ->join('accounting_journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
                ->leftJoin('accounting_accounts as aa', 'aa.code', '=', 'jl.account_code')
                ->where('je.tenant_id', $tenantId)
                ->whereDate('je.entry_date', '<=', $asOfDate)
                ->where('je.status', 'posted')
                ->select(
                    'jl.account_code',
                    DB::raw('COALESCE(aa.name, jl.account_code) as account_name'),
                    DB::raw('SUM(jl.debit_amount) as debit_mouvement'),
                    DB::raw('SUM(jl.credit_amount) as credit_mouvement'),
                    DB::raw('GREATEST(SUM(jl.debit_amount) - SUM(jl.credit_amount), 0) as solde_debiteur'),
                    DB::raw('GREATEST(SUM(jl.credit_amount) - SUM(jl.debit_amount), 0) as solde_crediteur'),
                )
                ->groupBy('jl.account_code', 'aa.name')
                ->orderBy('jl.account_code')
                ->get();

            return $rows->toArray();
        } catch (\Exception $e) {
            Log::warning('OhadaReportService: cannot generate trial balance', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function getJournalEntries(int $tenantId, string $accountCode, string $start, string $end): array
    {
        try {
            return DB::table('accounting_journal_lines as jl')
                ->join('accounting_journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
                ->where('je.tenant_id', $tenantId)
                ->where('jl.account_code', $accountCode)
                ->whereDate('je.entry_date', '>=', $start)
                ->whereDate('je.entry_date', '<=', $end)
                ->where('je.status', 'posted')
                ->select(
                    'je.entry_date as date',
                    'je.reference',
                    'je.description as libelle',
                    'jl.debit_amount as debit',
                    'jl.credit_amount as credit',
                )
                ->orderBy('je.entry_date')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getJournalByType(int $tenantId, string $type, string $start, string $end): array
    {
        $journalCodes = [
            'ventes'              => ['VT', 'VNT'],
            'achats'              => ['AC', 'ACH'],
            'caisse'              => ['CA', 'CAI'],
            'banque'              => ['BQ', 'BNQ'],
            'opérations_diverses' => ['OD', 'DIV'],
        ];

        $codes = $journalCodes[$type] ?? [$type];

        try {
            return DB::table('accounting_journal_entries as je')
                ->join('accounting_journal_lines as jl', 'jl.journal_entry_id', '=', 'je.id')
                ->where('je.tenant_id', $tenantId)
                ->whereIn('je.journal_code', $codes)
                ->whereDate('je.entry_date', '>=', $start)
                ->whereDate('je.entry_date', '<=', $end)
                ->where('je.status', 'posted')
                ->select(
                    'je.entry_date as date',
                    'je.reference',
                    'je.description as libelle',
                    'jl.account_code',
                    'jl.debit_amount as debit',
                    'jl.credit_amount as credit',
                )
                ->orderBy('je.entry_date')
                ->orderBy('je.id')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getOutstandingReceivables(int $tenantId, string $asOfDate): array
    {
        try {
            return DB::table('invoices as i')
                ->leftJoin('customers as c', 'c.id', '=', 'i.customer_id')
                ->where('i.tenant_id', $tenantId)
                ->whereIn('i.status', ['sent', 'partial', 'overdue'])
                ->whereDate('i.invoice_date', '<=', $asOfDate)
                ->select(
                    'i.id as invoice_id',
                    'i.reference',
                    'i.invoice_date',
                    'i.due_date',
                    DB::raw('COALESCE(c.name, i.customer_name) as client_name'),
                    DB::raw('(i.total_amount - COALESCE(i.paid_amount, 0)) as balance'),
                    'i.currency',
                )
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getOutstandingPayables(int $tenantId, string $asOfDate): array
    {
        try {
            return DB::table('supplier_invoices as si')
                ->leftJoin('suppliers as s', 's.id', '=', 'si.supplier_id')
                ->where('si.tenant_id', $tenantId)
                ->whereIn('si.status', ['received', 'partial', 'overdue'])
                ->whereDate('si.invoice_date', '<=', $asOfDate)
                ->select(
                    'si.id as invoice_id',
                    'si.reference',
                    'si.invoice_date',
                    'si.due_date',
                    DB::raw('COALESCE(s.name, si.supplier_name) as fournisseur_name'),
                    DB::raw('(si.total_amount - COALESCE(si.paid_amount, 0)) as balance'),
                    'si.currency',
                )
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getTenantCountry(int $tenantId): string
    {
        try {
            $country = DB::table('companies')
                ->where('tenant_id', $tenantId)
                ->value('country_code');

            return $country ?? 'SN';
        } catch (\Exception $e) {
            return 'SN';
        }
    }
}
