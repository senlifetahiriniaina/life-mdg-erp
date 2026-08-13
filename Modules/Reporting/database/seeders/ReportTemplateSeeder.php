<?php

declare(strict_types=1);

namespace Modules\Reporting\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Reporting\Models\ReportDefinition;

/**
 * ReportTemplateSeeder
 *
 * Seeds 10 pre-built OHADA/SYSCOHADA-compliant report templates.
 * These templates are global (tenant_id = null) and available to all tenants.
 *
 * Templates:
 *  1.  Bilan SYSCOHADA mensuel         (financial / ohada_balance_sheet)
 *  2.  Compte de résultat trimestriel  (financial / ohada_income_statement)
 *  3.  Balance générale                (financial / ohada_trial_balance)
 *  4.  Balance âgée clients            (financial / aged_receivables)
 *  5.  Balance âgée fournisseurs       (financial / aged_payables)
 *  6.  Rapport TVA mensuel             (financial / custom_sql)
 *  7.  Rapport IS annuel               (financial / custom_sql)
 *  8.  Synthèse des ventes mensuelle   (sales    / sales_performance)
 *  9.  État des stocks                 (inventory / stock_valuation)
 * 10.  Masse salariale mensuelle       (hr       / payroll_summary)
 */
class ReportTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = $this->getTemplates();

        foreach ($templates as $template) {
            ReportDefinition::updateOrCreate(
                ['slug' => $template['slug']],
                $template,
            );
        }

        $this->command->info('✔  ReportTemplateSeeder: ' . count($templates) . ' templates seeded.');
    }

    private function getTemplates(): array
    {
        return [
            // ──────────────────────────────────────────────────────────────────
            // 1. Bilan SYSCOHADA mensuel
            // ──────────────────────────────────────────────────────────────────
            [
                'slug'              => 'bilan-syscohada-mensuel',
                'tenant_id'         => null,
                'name'              => 'Bilan SYSCOHADA mensuel',
                'module'            => 'Accounting',
                'description'       => 'État du patrimoine de l\'entreprise conforme SYSCOHADA – actif (Cl.2-5) et passif (Cl.1,4,5). Comparaison N/N-1.',
                'category'          => 'financial',
                'report_type'       => 'ohada_balance_sheet',
                'output_format'     => 'pdf',
                'query_template'    => '-- Handled by OhadaReportService::generateBalanceSheet()',
                'parameters_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'period'   => ['type' => 'string', 'description' => 'Période ex: 2026-03 ou 2026-Q1', 'default' => date('Y-m')],
                        'currency' => ['type' => 'string', 'enum' => ['XOF', 'XAF', 'GHS', 'NGN', 'KES'], 'default' => 'XOF'],
                    ],
                    'required'   => ['period'],
                ],
                'config'            => [
                    'service'  => 'OhadaReportService',
                    'method'   => 'generateBalanceSheet',
                    'standard' => 'SYSCOHADA',
                    'classes'  => ['1', '2', '3', '4', '5'],
                ],
                'schedule'          => null,
                'is_system'         => true,
                'is_active'         => true,
                'created_by'        => null,
            ],

            // ──────────────────────────────────────────────────────────────────
            // 2. Compte de résultat trimestriel
            // ──────────────────────────────────────────────────────────────────
            [
                'slug'              => 'compte-de-resultat-trimestriel',
                'tenant_id'         => null,
                'name'              => 'Compte de résultat trimestriel',
                'module'            => 'Accounting',
                'description'       => 'Produits (Cl.7) et charges (Cl.6) sur un trimestre. Résultat d\'exploitation, financier et net. Conforme SYSCOHADA.',
                'category'          => 'financial',
                'report_type'       => 'ohada_income_statement',
                'output_format'     => 'pdf',
                'query_template'    => '-- Handled by OhadaReportService::generateIncomeStatement()',
                'parameters_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'period'   => ['type' => 'string', 'description' => 'Ex: 2026-Q1', 'default' => date('Y') . '-Q' . (int) ceil(date('n') / 3)],
                        'currency' => ['type' => 'string', 'default' => 'XOF'],
                    ],
                    'required'   => ['period'],
                ],
                'config'            => [
                    'service'  => 'OhadaReportService',
                    'method'   => 'generateIncomeStatement',
                    'standard' => 'SYSCOHADA',
                    'classes'  => ['6', '7'],
                ],
                'schedule'          => [
                    'frequency'  => 'quarterly',
                    'day'        => 5,
                    'time'       => '07:00',
                    'format'     => 'pdf',
                    'recipients' => [],
                ],
                'is_system'         => true,
                'is_active'         => true,
                'created_by'        => null,
            ],

            // ──────────────────────────────────────────────────────────────────
            // 3. Balance Générale (Trial Balance)
            // ──────────────────────────────────────────────────────────────────
            [
                'slug'              => 'balance-generale',
                'tenant_id'         => null,
                'name'              => 'Balance générale des comptes',
                'module'            => 'Accounting',
                'description'       => 'Liste tous les comptes du plan comptable SYSCOHADA avec mouvements débit/crédit et soldes. Vérification de l\'équilibre comptable.',
                'category'          => 'financial',
                'report_type'       => 'ohada_trial_balance',
                'output_format'     => 'xlsx',
                'query_template'    => '-- Handled by OhadaReportService::generateTrialBalance()',
                'parameters_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'period' => ['type' => 'string', 'description' => 'Ex: 2026-03', 'default' => date('Y-m')],
                    ],
                    'required'   => ['period'],
                ],
                'config'            => [
                    'service'  => 'OhadaReportService',
                    'method'   => 'generateTrialBalance',
                    'standard' => 'SYSCOHADA',
                ],
                'schedule'          => null,
                'is_system'         => true,
                'is_active'         => true,
                'created_by'        => null,
            ],

            // ──────────────────────────────────────────────────────────────────
            // 4. Balance Âgée Clients (Aged Receivables)
            // ──────────────────────────────────────────────────────────────────
            [
                'slug'              => 'balance-agee-clients',
                'tenant_id'         => null,
                'name'              => 'Balance âgée clients',
                'module'            => 'Accounting',
                'description'       => 'Créances clients regroupées par ancienneté : 0-30j / 31-60j / 61-90j / >90j. Standard UEMOA pour le suivi du recouvrement.',
                'category'          => 'financial',
                'report_type'       => 'aged_receivables',
                'output_format'     => 'xlsx',
                'query_template'    => '-- Handled by OhadaReportService::generateAgedReceivables()',
                'parameters_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'as_of_date' => ['type' => 'string', 'format' => 'date', 'description' => 'Date de référence', 'default' => date('Y-m-d')],
                    ],
                    'required'   => ['as_of_date'],
                ],
                'config'            => [
                    'service'  => 'OhadaReportService',
                    'method'   => 'generateAgedReceivables',
                    'buckets'  => ['0_30', '31_60', '61_90', '90p'],
                ],
                'schedule'          => [
                    'frequency'  => 'weekly',
                    'day'        => 1,
                    'time'       => '08:00',
                    'format'     => 'xlsx',
                    'recipients' => [],
                ],
                'is_system'         => true,
                'is_active'         => true,
                'created_by'        => null,
            ],

            // ──────────────────────────────────────────────────────────────────
            // 5. Balance Âgée Fournisseurs (Aged Payables)
            // ──────────────────────────────────────────────────────────────────
            [
                'slug'              => 'balance-agee-fournisseurs',
                'tenant_id'         => null,
                'name'              => 'Balance âgée fournisseurs',
                'module'            => 'Accounting',
                'description'       => 'Dettes fournisseurs regroupées par ancienneté. Aide à prioriser les règlements et éviter les pénalités.',
                'category'          => 'financial',
                'report_type'       => 'aged_payables',
                'output_format'     => 'xlsx',
                'query_template'    => '-- Handled by OhadaReportService::generateAgedPayables()',
                'parameters_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'as_of_date' => ['type' => 'string', 'format' => 'date', 'default' => date('Y-m-d')],
                    ],
                    'required'   => ['as_of_date'],
                ],
                'config'            => [
                    'service' => 'OhadaReportService',
                    'method'  => 'generateAgedPayables',
                    'buckets' => ['0_30', '31_60', '61_90', '90p'],
                ],
                'schedule'          => null,
                'is_system'         => true,
                'is_active'         => true,
                'created_by'        => null,
            ],

            // ──────────────────────────────────────────────────────────────────
            // 6. Rapport TVA mensuel
            // ──────────────────────────────────────────────────────────────────
            [
                'slug'              => 'rapport-tva-mensuel',
                'tenant_id'         => null,
                'name'              => 'Déclaration TVA mensuelle',
                'module'            => 'Accounting',
                'description'       => 'TVA collectée, TVA déductible (immobilisations + charges), TVA à décaisser ou crédit de TVA. Taux par pays (SN 18%, CI 18%, CM 19.25%).',
                'category'          => 'financial',
                'report_type'       => 'custom_sql',
                'output_format'     => 'pdf',
                'query_template'    => '-- Handled by OhadaReportService::generateTvaReport()',
                'parameters_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'period' => ['type' => 'string', 'description' => 'Ex: 2026-03 ou 2026-Q1', 'default' => date('Y-m')],
                    ],
                    'required'   => ['period'],
                ],
                'config'            => [
                    'service'  => 'OhadaReportService',
                    'method'   => 'generateTvaReport',
                    'accounts' => ['4431', '4435', '4452', '4455'],
                ],
                'schedule'          => [
                    'frequency'  => 'monthly',
                    'day'        => 15,
                    'time'       => '07:00',
                    'format'     => 'pdf',
                    'recipients' => [],
                ],
                'is_system'         => true,
                'is_active'         => true,
                'created_by'        => null,
            ],

            // ──────────────────────────────────────────────────────────────────
            // 7. Rapport IS annuel
            // ──────────────────────────────────────────────────────────────────
            [
                'slug'              => 'rapport-is-annuel',
                'tenant_id'         => null,
                'name'              => 'Impôt sur les Sociétés (IS) annuel',
                'module'            => 'Accounting',
                'description'       => 'Calcul de l\'IS selon les règles OHADA/UEMOA : résultat avant IS, base imposable, IS théorique, IS minimum forfaitaire, IS dû.',
                'category'          => 'financial',
                'report_type'       => 'custom_sql',
                'output_format'     => 'pdf',
                'query_template'    => '-- Handled by OhadaReportService::generateIsReport()',
                'parameters_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'fiscal_year' => ['type' => 'string', 'description' => 'Année fiscale ex: 2025', 'default' => (string) (date('Y') - 1)],
                    ],
                    'required'   => ['fiscal_year'],
                ],
                'config'            => [
                    'service' => 'OhadaReportService',
                    'method'  => 'generateIsReport',
                    'notes'   => 'IS minimum = 0.5% CA pour le Sénégal',
                ],
                'schedule'          => [
                    'frequency'  => 'monthly',
                    'day'        => 31,
                    'time'       => '08:00',
                    'format'     => 'pdf',
                    'recipients' => [],
                ],
                'is_system'         => true,
                'is_active'         => true,
                'created_by'        => null,
            ],

            // ──────────────────────────────────────────────────────────────────
            // 8. Synthèse des ventes mensuelle
            // ──────────────────────────────────────────────────────────────────
            [
                'slug'              => 'synthese-ventes-mensuelle',
                'tenant_id'         => null,
                'name'              => 'Synthèse des ventes mensuelle',
                'module'            => 'Sales',
                'description'       => 'CA par produit, par commercial et par région pour le mois en cours. Comparaison M-1 et objectifs.',
                'category'          => 'sales',
                'report_type'       => 'sales_performance',
                'output_format'     => 'xlsx',
                'query_template'    => <<<'SQL'
SELECT
    p.name                           AS produit,
    p.category                       AS categorie,
    SUM(sol.quantity)                AS quantite_vendue,
    SUM(sol.total_price)             AS chiffre_affaires,
    AVG(sol.unit_price)              AS prix_moyen,
    COUNT(DISTINCT so.customer_id)   AS nb_clients
FROM sales_order_lines sol
JOIN sales_orders so  ON so.id  = sol.order_id
JOIN products p       ON p.id   = sol.product_id
WHERE so.tenant_id  = {{tenant_id}}
  AND so.status     IN ('confirmed', 'delivered')
  AND so.order_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
  AND so.order_date <  DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
GROUP BY p.id, p.name, p.category
ORDER BY chiffre_affaires DESC
LIMIT 500
SQL,
                'parameters_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'month' => ['type' => 'string', 'description' => 'Ex: 2026-05', 'default' => date('Y-m')],
                    ],
                ],
                'config'            => [
                    'chart_type' => 'bar',
                    'x_key'      => 'produit',
                    'y_key'      => 'chiffre_affaires',
                    'currency'   => 'XOF',
                ],
                'schedule'          => [
                    'frequency'  => 'monthly',
                    'day'        => 1,
                    'time'       => '07:30',
                    'format'     => 'xlsx',
                    'recipients' => [],
                ],
                'is_system'         => true,
                'is_active'         => true,
                'created_by'        => null,
            ],

            // ──────────────────────────────────────────────────────────────────
            // 9. État des stocks (Stock Valuation)
            // ──────────────────────────────────────────────────────────────────
            [
                'slug'              => 'etat-des-stocks',
                'tenant_id'         => null,
                'name'              => 'État des stocks',
                'module'            => 'Inventory',
                'description'       => 'Valorisation complète du stock au coût moyen pondéré (CMP). Alerte sur les stocks sous le seuil de réapprovisionnement et ruptures.',
                'category'          => 'inventory',
                'report_type'       => 'stock_valuation',
                'output_format'     => 'xlsx',
                'query_template'    => <<<'SQL'
SELECT
    p.sku,
    p.name                                      AS produit,
    p.category,
    p.stock_qty                                 AS quantite_stock,
    p.reorder_point                             AS seuil_reappro,
    p.cost_price                                AS cout_unitaire_cmp,
    ROUND(p.stock_qty * p.cost_price, 2)        AS valeur_stock,
    CASE
        WHEN p.stock_qty <= 0            THEN 'RUPTURE'
        WHEN p.stock_qty <= p.reorder_point THEN 'ALERTE'
        ELSE 'OK'
    END                                         AS statut_stock
FROM products p
WHERE p.tenant_id = {{tenant_id}}
  AND p.is_active = 1
ORDER BY valeur_stock DESC
LIMIT 5000
SQL,
                'parameters_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'as_of_date' => ['type' => 'string', 'format' => 'date', 'default' => date('Y-m-d')],
                        'category'   => ['type' => 'string', 'description' => 'Filtrer par catégorie'],
                    ],
                ],
                'config'            => [
                    'highlight_rupture' => true,
                    'highlight_alert'   => true,
                    'columns_order'     => ['sku', 'produit', 'categorie', 'quantite_stock', 'cout_unitaire_cmp', 'valeur_stock', 'statut_stock'],
                ],
                'schedule'          => [
                    'frequency'  => 'weekly',
                    'day'        => 1,
                    'time'       => '06:00',
                    'format'     => 'xlsx',
                    'recipients' => [],
                ],
                'is_system'         => true,
                'is_active'         => true,
                'created_by'        => null,
            ],

            // ──────────────────────────────────────────────────────────────────
            // 10. Masse salariale mensuelle (Payroll Summary)
            // ──────────────────────────────────────────────────────────────────
            [
                'slug'              => 'masse-salariale-mensuelle',
                'tenant_id'         => null,
                'name'              => 'Masse salariale mensuelle',
                'module'            => 'HR',
                'description'       => 'Récapitulatif de la paie mensuelle par département : effectif, salaires bruts, cotisations sociales, net à payer. Conforme OHADA Cl.66.',
                'category'          => 'hr',
                'report_type'       => 'payroll_summary',
                'output_format'     => 'pdf',
                'query_template'    => <<<'SQL'
SELECT
    e.department,
    COUNT(DISTINCT e.id)             AS effectif,
    SUM(ps.gross_salary)             AS masse_brute,
    SUM(ps.employer_contributions)   AS cotisations_patronales,
    SUM(ps.employee_contributions)   AS cotisations_salariales,
    SUM(ps.net_salary)               AS masse_nette,
    AVG(ps.gross_salary)             AS salaire_moyen_brut
FROM payslips ps
JOIN employees e ON e.id = ps.employee_id
WHERE ps.tenant_id = {{tenant_id}}
  AND ps.status    = 'validated'
  AND ps.period    = {{period}}
GROUP BY e.department
ORDER BY masse_brute DESC
SQL,
                'parameters_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'period' => ['type' => 'string', 'description' => 'Période paie ex: 2026-05', 'default' => date('Y-m')],
                    ],
                    'required'   => ['period'],
                ],
                'config'            => [
                    'ohada_account' => '66',
                    'chart_type'    => 'bar',
                    'x_key'         => 'department',
                    'y_key'         => 'masse_nette',
                    'currency'      => 'XOF',
                ],
                'schedule'          => [
                    'frequency'  => 'monthly',
                    'day'        => 5,
                    'time'       => '08:00',
                    'format'     => 'pdf',
                    'recipients' => [],
                ],
                'is_system'         => true,
                'is_active'         => true,
                'created_by'        => null,
            ],
        ];
    }
}
