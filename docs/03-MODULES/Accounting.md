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

Toutes les routes API (`Modules/Accounting/routes/api.php`, 413 lignes) sont regroupées par domaine. Principaux groupes :

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

Accounting a une entrée dédiée dans `RolesAndPermissionsSeeder::MODULES` : `'accounting' => ['invoice', 'journal', 'chart-of-account']`, générant les permissions `accounting.invoice.{view-any,view,create,update,delete}`, `accounting.journal.*`, `accounting.chart-of-account.*` (5 actions × 3 ressources = 15 permissions). Le rôle `accountant` reçoit l'intégralité des permissions `accounting.*`. Le rôle `finance-manager` reçoit `accounting.*` + `bi.*` + `strategy.*`. Le rôle `manager` reçoit toutes les permissions `accounting.*` sauf `.delete` (règle explicite dans le seeder : pas de suppression de facture par un manager). Le rôle `purchasing-manager` reçoit un accès restreint (`accounting.invoice.view-any/view/create/update`, sans `delete`).

## Dépendances avec d'autres modules

Le code du module Accounting lui-même n'importe (`use Modules\...`) que `Modules\AI`, `Modules\Shared` et `Modules\Timesheets`. En sens inverse, trois modules importent explicitement des classes Accounting : **BI**, **Core**, et **Payroll**. Le module Sales ne référence pas Accounting directement dans son code PHP, mais le rattache fonctionnellement via `sales_order.currency` et via des workflows d'automatisation (Workflow → `InventoryAccountingActionHandler`). Le trait `HelpdeskLinkable` (module Helpdesk) est appliqué au modèle `Invoice`, permettant à toute facture de générer/lister ses propres tickets Helpdesk.

## Particularités du périmètre life-mdg-erp

Certains pans du module étaient déjà incomplets dans le WideHalo-ERP source (pas une régression de l'extraction), documentés dans le `CLAUDE.md` racine : la Consolidation multi-société (`ConsolidationGroup`), la reconnaissance de revenus ASC606 (`RevenueRecognitionEvent`), et une partie des modèles avancés de dépréciation/écart budgétaire (`GLJournal`, `BudgetActual`, `BudgetAlert`, `BudgetForecast`, `GLEntry`) référencent des classes qui ne sont jamais entièrement câblées dans ce dépôt. Ces éléments se traduisent par des échecs de tests individuels plutôt que des erreurs fatales — `vendor/bin/pest` va jusqu'au bout — et sont considérés comme un backlog hors périmètre du lancement Life MDG plutôt qu'un défaut à corriger dans l'immédiat.
