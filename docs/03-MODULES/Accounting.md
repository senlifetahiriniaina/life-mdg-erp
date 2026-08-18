# Accounting

## Rôle

Le module Accounting est le cœur Compta/Finance de Life MDG ERP. Il couvre le plan comptable, les journaux et écritures, la facturation client (avec circuit d'approbation OHADA), les dépenses, la trésorerie/rapprochement bancaire, la TVA/fiscalité, les immobilisations, la budgétisation, le forecasting de trésorerie, la consolidation multi-entités et le moteur de coûts CAPEX/OPEX/FINEX/RISKEX — le tout aligné sur les normes comptables OHADA/SYSCOHADA utilisées en Afrique francophone.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `ChartOfAccount` | `acc_chart_of_accounts` | Plan comptable hiérarchique (parent/enfants) |
| `GLAccount` | (voir migrations) | Comptes du grand livre général |
| `Journal` | (voir migrations) | Journaux comptables |
| `JournalEntry` | `acc_journal_entries` | Écritures comptables (numéro, date, compte GL, type) |
| `Invoice` / `InvoiceLine` / `InvoicePayment` | (voir migrations) | Factures client, leurs lignes et paiements |
| `InvoiceApproval` | (voir migrations) | Étapes du circuit d'approbation de facture (niveaux OHADA) |
| `Expense` / `ExpenseLine` / `ExpenseReport` | (voir migrations) | Notes de frais et rapports de dépenses |
| `Budget` / `BudgetLine` / `BudgetScenario` | (voir migrations) | Budgets, lignes budgétaires, scénarios |
| `BankAccount` / `BankStatement` / `BankTransaction` | (voir migrations) | Comptes bancaires et relevés |
| `Reconciliation` / `ReconciliationSession` | (voir migrations) | Rapprochement bancaire |
| `TaxRate` / `VatRate` / `VatDeclaration` / `TaxEntry` | (voir migrations) | Taux de taxe, TVA, déclarations |
| `FixedAsset` / `DepreciationSchedule` / `DepreciationEntry` | (voir migrations) | Immobilisations et amortissements |
| `CashFlowForecast` / `TreasuryForecast` / `TreasuryScenario` | (voir migrations) | Prévisions de trésorerie |
| `ConsolidationEntry` / `ConsolidationHierarchy` / `ConsolidationReport` | (voir migrations) | Consolidation multi-entités (marquée incomplète — voir CLAUDE.md racine) |
| `CostEntry` / `CostCategory` / `CostRollup` | (voir migrations) | Moteur de coûts CAPEX/OPEX/FINEX/RISKEX |
| `ExchangeRate` / `CurrencyGainLoss` | (voir migrations) | Multi-devises |

## Endpoints principaux

Toutes les routes API (`Modules/Accounting/routes/api.php`, 525 lignes) sont regroupées par domaine, sous `role:accountant,finance-manager,manager,admin`. Principaux groupes :

| Domaine | Exemples de routes |
|---|---|
| Factures | `GET/POST /invoices`, `GET /invoices/aged-receivables`, `/outstanding`, `/overdue`, `POST /invoices/{invoice}/payment`, `/mark-paid` |
| Dépenses | `GET/POST /expenses`, `/expenses/pending`, `/expenses/by-category/{category}`, `POST /expenses/{expense}/approve` |
| Plan comptable & GL | `GET/POST /chart-of-accounts`, `/gl-accounts`, `/journals`, `/journal-entries`, `POST /journal-entries/{id}/post` \| `/reverse` |
| Budgets | `GET/POST /budgets`, `/budgets/{budget}/variance`, `/variance-trend`, `POST /budgets/{budget}/approve` \| `/reject` \| `/clone` |
| Taxes / TVA | `GET/POST /tax-rates`, `/vat-declarations`, `POST /vat-declarations/calculate` \| `/{id}/submit`, `/tax/calculate` \| `/calculate-compound` |
| Rapports financiers | `GET /reports/income-statement`, `/balance-sheet`, `/cash-flow`, `POST /financial-reports/{report}/publish` \| `/export/pdf` \| `/export/excel` |
| Ratios financiers | `GET /ratios`, `/ratios/liquidity`, `/profitability`, `/efficiency`, `/leverage`, `/market`, `/dupont` |
| Rapprochement bancaire | `POST /reconciliations`, `/{reconciliation}/auto-match`, `/manual-match`, `/approve` |
| Consolidation | `GET/POST /consolidations`, `/consolidation-reports`, `/intercompany-transactions` |
| IA | `POST /ai/forecast-cash-flow`, `/ai/summarize-balance-sheet`, `/ai/categorize-transactions` |
| AI Assisted First | `POST /ai/assist` (guidance contextuelle) |

## Contrôleurs

Le module compte 39 contrôleurs Api (`Modules/Accounting/app/Http/Controllers/Api/`) plus 7 contrôleurs directement sous `Http/Controllers/` et 13 contrôleurs Web (`Http/Controllers/Web/`) — c'est le module le plus fourni du périmètre en volume de contrôleurs, cohérent avec ses 76+ modèles.

Principaux contrôleurs Api : `InvoiceController`, `InvoiceApprovalController`, `ExpenseController`/`ExpenseReportController`, `ChartOfAccountController`, `GLAccountController`, `JournalController`/`JournalEntryApiController`, `BudgetController`/`BudgetManagementController`/`BudgetVarianceController`, `TaxRateController`/`TaxCalculationController`/`TaxComplianceController`/`VatDeclarationController`/`VatRateController`, `FixedAssetController`, `BankAccountController`/`BankReconciliationController`/`BankTransactionController`/`AccOpenBankingController`, `ReconciliationController`/`ReconciliationSessionController`, `ConsolidationController`, `CostEngineController`, `ScenarioPlanningController`, `ReportController`/`ReportingController`/`FinancialRatiosController`, `TreasuryController`/`TreasuryPlanningController`, `ExchangeRateController`, `SmartCategorizationController`, `AccountingAIController`/`AccountingAiAssistController`.

Contrôleurs directement sous `Http/Controllers/` (pas `Api/`) — les 7 issus de Chantier 8.1b, tous corrigés pour `extends App\Http\Controllers\Controller` (aucun ne l'était, ce qui rendait tout `$this->authorize()` fatal) : `AssetImpairmentController`, `ConsolidationHierarchyController`, `DepreciationPolicyController`, `DepreciationScheduleController`, `IntercompanyClearanceController`, `ScenarioPlanningController`, `RevenueContractController` (ASC606, hors périmètre fonctionnel — voir Particularités), `TaxComplianceReportController`, `InvoiceApprovalController`.

Contrôleurs Web (13) : `AccountingWebController` (dashboard, plan comptable, dépenses), `InvoiceWebController`, `BankReconciliationWebController`, `VatDeclarationWebController`, `ConsolidationWebController`/`ConsolidationHierarchyWebController`, et les 7 issus de Chantier 8.1b (`AssetImpairmentWebController`, `BudgetVarianceWebController`, `CostEngineWebController`, `DepreciationPolicyWebController`, `DepreciationScheduleWebController`, `IntercompanyClearanceWebController`, `ScenarioPlanningWebController`).

**Contrôleurs supprimés lors de l'audit Chantier 8.1** (redondants, remplacés par les méthodes déjà routées de `ReportController`/`AccOpenBankingController`) : `BalanceSheetController`, `IncomeStatementController`, `CashFlowController`, `LedgerMatchingController`, `OpenBankingController`, `TransactionAIController`, `BalanceSheetAIController`.

## Vues (Vue/Inertia)

Deux arborescences coexistent, `resources/js/Pages/Accounting/` (racine — priorité de résolution dans `app.js`) et `Modules/Accounting/resources/js/Pages/` :

- **Racine** (`resources/js/Pages/Accounting/`) : `Invoices/`, `Expenses/`, `VatDeclaration/`, `OpenBanking/`, `Currency/`, `JournalEntries/`, `BankReconciliation/` — pages « riches » servies par les contrôleurs Web réels.
- **Module** (`Modules/Accounting/resources/js/Pages/`) : `ChartOfAccounts/`, `InvoiceApproval/`, `Consolidation/`, `ConsolidationHierarchies/`, et les 7 pages du Chantier 8.1b (`AssetImpairments/`, `BudgetVariance/`, `CostEngine/`, `DepreciationPolicies/`, `DepreciationSchedules/`, `IntercompanyClearances/`, `ScenarioPlanning/`), plus `AIAnomalyDetection/`.

Les deux duplicatas d'`Invoices/Index.vue` et `Expenses/Index.vue` qui existaient côté module (masqués par les vraies copies racine) ont été supprimés lors du Chantier 8.1 — confirmé : `Modules/Accounting/resources/js/Pages/Invoices/` ne contient plus que `Form.vue`/`Show.vue`.

Les 7 pages du Chantier 8.1b (`asset-impairments`, `budget-variance`, `cost-engine`, `depreciation-policies`, `depreciation-schedules`, `intercompany-clearances`, `scenario-planning`) et `consolidation-hierarchies` ne sont accessibles que par URL directe — aucun lien de navigation ne pointe encore vers elles (pas de sous-navigation Accounting dédiée dans ce dépôt).

## Services

- **`AccountingService`** — service générique CRUD (comptes GL, factures, dépenses, journaux, budgets, rapprochements) et métriques financières de base.
- **`InvoiceService`** / **`InvoiceApprovalService`** — cycle de vie facture ; `InvoiceApprovalService` implémente le circuit d'approbation **OHADA à 3 niveaux par seuil de montant** (`THRESHOLDS`: niveau 1 ≤ 100 000 XOF — manager, niveau 2 ≤ 500 000 XOF — directeur, niveau 3 > 500 000 XOF jusqu'à 10 000 000 XOF — DG).
- **`FinancialReportService`** — bilan (`balanceSheet`), compte de résultat (`incomeStatement`), tableau de flux de trésorerie (`cashFlow`).
- **`VatDeclarationService`** — calcul de période TVA, génération de rapport de déclaration, export XML, marquage « soumis ».
- **`CostEngineService`** — moteur CAPEX/OPEX/FINEX/RISKEX ; sa constante `OHADA_ACCOUNT_MAP` mappe chaque catégorie de coût vers les classes du plan comptable OHADA (Classe 2 = CAPEX, Classe 6 = OPEX, Classe 67 = FINEX, Classe 69 = RISKEX), et son prompt IA interne est explicitement rédigé « expert ERP cost controller specialised in OHADA/SYSCOHADA accounting for African SMEs ».
- **`CostBenchmarkService`** — benchmarks de coûts par pays africain.
- **`BudgetService`** / **`BudgetVarianceService`** — budgets et analyse d'écarts.
- **`CashFlowForecastService`** / **`TreasuryService`** / **`ScenarioPlanningService`** — prévisions et scénarios de trésorerie.
- **`BankReconciliationService`** / **`AccOpenBankingService`** / **`OpenBankingService`** — rapprochement bancaire et connexions open banking.
- **`ConsolidationService`** / **`MultiEntityConsolidationService`** — consolidation multi-sociétés (incomplète dans ce périmètre, voir plus bas).
- **`ASC606RevenueRecognitionService`** — reconnaissance de revenus (incomplète dans ce périmètre).
- **`AdvancedTaxComplianceService`** / **`TaxService`** — calcul et conformité fiscale.
- **`FixedAssetService`** — immobilisations et amortissements.
- **`MultiCurrencyService`** — gestion multi-devises et écarts de change.
- **`AI\AccountingAIService`**, **`AI\BalanceSheetSummaryService`**, **`AI\TransactionCategorizationService`** — capacités IA (catégorisation de transactions, résumé de bilan, prévision de trésorerie via Claude).

## Permissions RBAC

Accounting a une entrée dédiée dans `RolesAndPermissionsSeeder::MODULES` : `'accounting' => ['invoice', 'journal', 'chart-of-account', 'bank-account', 'expense', 'tax_compliance', 'revenue_recognition', 'consolidation', 'depreciation', 'intercompany', 'asset_impairment', 'depreciation_policy', 'budget', 'budget_scenario']` — 14 ressources × 5 actions standard (`view-any,view,create,update,delete`), sensiblement élargie depuis la version d'origine du module (qui ne couvrait que `invoice`/`journal`/`chart-of-account`) au fil des Chantiers 8.1/8.1b pour couvrir les 7 contrôleurs nouvellement câblés (immobilisations, dépréciation, intercompany, scénarios budgétaires). Un bloc `ACCOUNTING_EXTRA_PERMISSIONS` dédié ajoute les verbes non-CRUD que les policies vérifient réellement (`accounting.tax_compliance.file`, `accounting.revenue_recognition.recognize`, `accounting.depreciation.record`, `accounting.intercompany.clear`, `accounting.expense.approve`, `accounting.asset_impairment.approve`/`.record`, `accounting.budget_scenario.approve`, plus les `.restore`/`.force_delete` correspondants) — ce bloc ferme le trou où `AssetImpairmentPolicy`/`DepreciationPolicyPolicy` existaient déjà mais leurs permissions n'avaient jamais été seedées (Chantier 8.1b).

Le rôle `accountant` reçoit l'intégralité des permissions `accounting.*`. Le rôle `finance-manager` reçoit `accounting.*` + `bi.*` + `strategy.*`. Le rôle `manager` reçoit toutes les permissions `accounting.*` sauf `.delete`. Le rôle `purchasing-manager` reçoit un accès restreint (`accounting.invoice.view-any/view/create/update`, sans `delete`). Au niveau route, `Modules/Accounting/routes/api.php` impose en plus `role:accountant,finance-manager,manager,admin` sur chaque groupe (vérifié dans le code — pas seulement documenté).

## Dépendances avec d'autres modules

Le code du module Accounting lui-même n'importe (`use Modules\...`) que `Modules\AI`, `Modules\Shared` et `Modules\Timesheets`. En sens inverse, trois modules importent explicitement des classes Accounting : **BI**, **Core**, et **Payroll**. Le module Sales ne référence pas Accounting directement dans son code PHP, mais le rattache fonctionnellement via `sales_order.currency` et via des workflows d'automatisation (Workflow → `InventoryAccountingActionHandler`). Le trait `HelpdeskLinkable` (module Helpdesk) est appliqué au modèle `Invoice`, permettant à toute facture de générer/lister ses propres tickets Helpdesk.

## Particularités du périmètre life-mdg-erp

La plupart des lacunes documentées à l'origine ont depuis été résolues pour de bon (Chantiers 8.1/8.1b, cette session) : la Consolidation multi-société vit désormais derrière un `ConsolidationGroup` unifié réel (le `ConsolidationHierarchy` parallèle a été conservé — c'est un concept réellement distinct, un arbre de détention avec périodes/éliminations — et câblé plutôt que supprimé), et les modèles d'écart budgétaire (`BudgetActual`, `BudgetAlert`, `BudgetForecast`, `GLEntry`) se sont reconnectés correctement une fois le socle `App\Models\Company` en place.

**La seule exception volontairement laissée de côté est ASC606 Revenue Recognition** (`RevenueRecognitionEvent`/`RevenueContract`, `RevenueContractController`) : c'est la norme US-GAAP de reconnaissance de revenu en cinq étapes (allocation des obligations de performance, contrainte de contrepartie variable, échéanciers de passif contractuel) — Life MDG tient sa comptabilité sous OHADA/SYSCOHADA, qui n'a pas d'équivalent, donc rien à porter. Les 5 échecs de `Modules/Accounting/tests/Feature/RevenueContractTest.php` documentent ce choix, pas une régression.

36 des 42 tables `acc_*` créées par la migration fourre-tout de scaffold (`database/migrations/2026_05_29_000003_create_all_missing_module_tables.php`) n'ont aucun modèle Eloquent (schéma stub `id/tenant_id/data/timestamps`, jamais interrogées par le code) — dette de scaffold inerte laissée telle quelle, personne n'y référence rien.
