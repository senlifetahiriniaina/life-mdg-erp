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
| `ConsolidationEntry` / `ConsolidationHierarchy` / `ConsolidationReport` | (voir migrations) | Consolidation multi-entités — deux sous-systèmes distincts, voir « Particularités » |
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
| Taxes / TVA | `GET/POST /tax-rates`, `/vat-declarations`, `POST /vat-declarations/calculate` \| `/{id}/submit`, `/tax/calculate` \| `/calculate-compound`, `GET/POST /tax-compliance-reports`, `POST /tax-compliance-reports/calculate` (Chantier 32.14, `AdvancedTaxComplianceService`) |
| Rapports financiers | `GET /reports/income-statement`, `/balance-sheet`, `/cash-flow`, `POST /financial-reports/{report}/publish` \| `/export/pdf` \| `/export/excel` |
| Ratios financiers | `GET /ratios`, `/ratios/liquidity`, `/profitability`, `/efficiency`, `/leverage`, `/market`, `/dupont` |
| Rapprochement bancaire | `POST /reconciliations`, `/{reconciliation}/auto-match`, `/manual-match`, `/approve` |
| Consolidation | `GET/POST /consolidations`, `/consolidation-reports`, `/intercompany-transactions` |
| IA | `POST /ai/forecast-cash-flow`, `/ai/summarize-balance-sheet`, `/ai/categorize-transactions` |
| AI Assisted First | `POST /ai/assist` (guidance contextuelle) |

## Contrôleurs

Le module compte 46 contrôleurs Api (`Modules/Accounting/app/Http/Controllers/Api/`) plus 6 contrôleurs directement sous `Http/Controllers/` et 14 contrôleurs Web (`Http/Controllers/Web/`) — c'est le module le plus fourni du périmètre en volume de contrôleurs, cohérent avec ses 77 modèles (chiffres recomptés pour de vrai au Chantier 32.14, l'ancienne doc était en dérive : 39/7/13, et listait un `RevenueContractController` qui n'existe plus sur disque depuis Chantier 9).

Principaux contrôleurs Api : `InvoiceController`, `InvoiceApprovalController`, `ExpenseController`/`ExpenseReportController`, `ChartOfAccountController`, `GLAccountController`, `JournalController`/`JournalEntryApiController`, `BudgetController`/`BudgetManagementController`/`BudgetVarianceController`, `TaxRateController`/`TaxCalculationController`/`TaxComplianceController`/`VatDeclarationController`/`VatRateController`, `FixedAssetController`, `BankAccountController`/`BankReconciliationController`/`BankTransactionController`/`AccOpenBankingController`, `ReconciliationController`/`ReconciliationSessionController`, `ConsolidationController`, `CostEngineController`, `ReportController`/`ReportingController`/`FinancialRatiosController`, `TreasuryController`/`TreasuryPlanningController`/`TreasuryImportController`, `ExchangeRateController`, `SmartCategorizationController`, `AccountingAIController`/`AccountingAiAssistController`, `FinancialSimulationController` (Chantier 18), `OperationTemplateController` (Chantier 15), `FinanceReviewController` (Chantier 26D).

Contrôleurs directement sous `Http/Controllers/` (pas `Api/`) — 6, tous corrigés pour `extends App\Http\Controllers\Controller` depuis Chantier 8.1b (aucun ne l'était à l'origine, ce qui rendait tout `$this->authorize()` fatal) : `AssetImpairmentController`, `ConsolidationHierarchyController`, `DepreciationPolicyController`, `DepreciationScheduleController`, `IntercompanyClearanceController`, `TaxComplianceReportController` (ce dernier a gagné un endpoint `calculate()` au Chantier 32.14, voir « Particularités »). `InvoiceApprovalController` vit en réalité sous `Http/Controllers/Api/`, pas ici — corrigé dans cette doc, qui le listait au mauvais endroit. `ScenarioPlanningController`/`RevenueContractController` n'existent plus à ce chemin — le premier vit désormais uniquement sous `Api/ScenarioPlanningController`, le second (ASC606) a été supprimé pour de bon dès Chantier 9, voir « Particularités ».

Contrôleurs Web (14) : `AccountingWebController` (dashboard, plan comptable, dépenses), `InvoiceWebController`, `BankReconciliationWebController`, `VatDeclarationWebController`, `TreasuryImportWebController` (Chantier 15), `ConsolidationWebController`/`ConsolidationHierarchyWebController`, et les 7 issus de Chantier 8.1b (`AssetImpairmentWebController`, `BudgetVarianceWebController`, `CostEngineWebController`, `DepreciationPolicyWebController`, `DepreciationScheduleWebController`, `IntercompanyClearanceWebController`, `ScenarioPlanningWebController`).

**Contrôleurs supprimés lors de l'audit Chantier 8.1** (redondants, remplacés par les méthodes déjà routées de `ReportController`/`AccOpenBankingController`) : `BalanceSheetController`, `IncomeStatementController`, `CashFlowController`, `LedgerMatchingController`, `OpenBankingController`, `TransactionAIController`, `BalanceSheetAIController`.

## Vues (Vue/Inertia)

Deux arborescences coexistent, `resources/js/Pages/Accounting/` (racine — priorité de résolution dans `app.js`) et `Modules/Accounting/resources/js/Pages/` :

- **Racine** (`resources/js/Pages/Accounting/`) : `Invoices/`, `Expenses/`, `VatDeclaration/`, `OpenBanking/`, `Currency/`, `JournalEntries/`, `BankReconciliation/`, `TreasuryImport/` (Chantier 15), `FinancialSimulation/` (Chantier 18), `BalanceSheet.vue`/`IncomeStatement.vue` (Chantier 18, états OHADA réels) — pages « riches » servies par les contrôleurs Web réels.
- **Module** (`Modules/Accounting/resources/js/Pages/`) : `ChartOfAccounts/`, `InvoiceApproval/`, `Consolidation/`, `ConsolidationHierarchies/`, `FinanceReview/` (Chantier 26D), et les 7 pages du Chantier 8.1b (`AssetImpairments/`, `BudgetVariance/`, `CostEngine/`, `DepreciationPolicies/`, `DepreciationSchedules/`, `IntercompanyClearances/`, `ScenarioPlanning/`), plus `AIAnomalyDetection/`.

Les deux duplicatas d'`Invoices/Index.vue` et `Expenses/Index.vue` qui existaient côté module (masqués par les vraies copies racine) ont été supprimés lors du Chantier 8.1 — confirmé : `Modules/Accounting/resources/js/Pages/Invoices/` ne contient plus que `Form.vue`/`Show.vue`.

Les 7 pages du Chantier 8.1b (`asset-impairments`, `budget-variance`, `cost-engine`, `depreciation-policies`, `depreciation-schedules`, `intercompany-clearances`, `scenario-planning`) et `consolidation-hierarchies` ne sont accessibles que par URL directe — aucun lien de navigation ne pointe encore vers elles (pas de sous-navigation Accounting dédiée dans ce dépôt).

## Services

25 services (`Modules/Accounting/app/Services/`, recompté au Chantier 32.14 après suppression de 2 orphelins confirmés cassés — voir « Particularités »).

- **`AccountingService`** — service générique CRUD (comptes GL, factures, dépenses, journaux, budgets, rapprochements) et métriques financières de base.
- **`InvoiceApprovalService`** — cycle de vie de l'approbation de facture, circuit **OHADA à 3 niveaux par seuil de montant** (`THRESHOLDS`: niveau 1 ≤ 100 000 XOF — manager, niveau 2 ≤ 500 000 XOF — directeur, niveau 3 > 500 000 XOF jusqu'à 10 000 000 XOF — DG). Le reste du cycle de vie facture (création, lignes, statuts, guard rails de transition draft→sent→paid→cancelled) vit directement dans `InvoiceController` depuis Chantier 32.14 — l'ancien `InvoiceService` (zéro appelant réel, dupliqué et jamais branché) a été supprimé, ses guard rails absorbés dans le contrôleur.
- **`FinancialReportService`** — bilan (`balanceSheet`), compte de résultat (`incomeStatement`), tableau de flux de trésorerie (`cashFlow`).
- **`VatDeclarationService`** — calcul de période TVA, génération de rapport de déclaration, export XML, marquage « soumis ».
- **`CostEngineService`** — moteur CAPEX/OPEX/FINEX/RISKEX ; sa constante `OHADA_ACCOUNT_MAP` mappe chaque catégorie de coût vers les classes du plan comptable OHADA (Classe 2 = CAPEX, Classe 6 = OPEX, Classe 67 = FINEX, Classe 69 = RISKEX), et son prompt IA interne est explicitement rédigé « expert ERP cost controller specialised in OHADA/SYSCOHADA accounting for African SMEs ».
- **`CostBenchmarkService`** — benchmarks de coûts par pays africain.
- **`BudgetService`** / **`BudgetVarianceService`** / **`BudgetGenerationService`** (Chantier 26C, génération de budget depuis l'historique) — budgets et analyse d'écarts.
- **`CashFlowForecastService`** / **`TreasuryService`** / **`ScenarioPlanningService`** / **`TreasuryImportService`** (Chantier 15) — prévisions et import de trésorerie.
- **`BankReconciliationService`** / **`AccOpenBankingService`** / **`OpenBankingService`** — rapprochement bancaire et connexions open banking.
- **`ConsolidationService`** — le sous-système de consolidation **réel et vivant**, derrière `ConsolidationController`/`ConsolidationGroup` (`eliminateIntercompany()`/`eliminateIntercompanyTransactions()`, calcul de l'intérêt minoritaire depuis les vraies données de propriété, `generateReport()`). `MultiEntityConsolidationService` (l'autre sous-système, `ConsolidationHierarchy`/`ConsolidationPeriod`/`ConsolidationEntry`/`ConsolidationElimination`) est resté **confirmé cassé** au Chantier 32.14 — voir « Particularités ».
- **`AdvancedTaxComplianceService`** / **`TaxService`** — calcul et conformité fiscale. `AdvancedTaxComplianceService` (VAT/impôt progressif/prix de transfert/impôt différé) était orpheline (zéro appelant, y compris un bug de résolution DI qui l'aurait fait planter même méthode-injectée) jusqu'au Chantier 32.14 — branchée dans `TaxComplianceReportController::calculate()`.
- **`FixedAssetService`** — immobilisations et amortissements.
- **`MultiCurrencyService`** — gestion multi-devises et écarts de change.
- **`FinancialSimulationService`** (Chantier 18) — simulation financière façon upmetrics, réalisation de lignes simulées en vraies commandes/écritures.
- **`AI\AccountingAIService`**, **`AI\BalanceSheetSummaryService`**, **`AI\TransactionCategorizationService`** — capacités IA (catégorisation de transactions, résumé de bilan, prévision de trésorerie via Claude).

## Permissions RBAC

Accounting a une entrée dédiée dans `RolesAndPermissionsSeeder::MODULES` : `'accounting' => ['invoice', 'journal', 'chart-of-account', 'bank-account', 'expense', 'tax_compliance', 'consolidation', 'depreciation', 'intercompany', 'asset_impairment', 'depreciation_policy', 'budget', 'budget_scenario', 'financial-simulation', 'financereview']` — 15 ressources × 5 actions standard (`view-any,view,create,update,delete`), sensiblement élargie depuis la version d'origine du module (qui ne couvrait que `invoice`/`journal`/`chart-of-account`) au fil des Chantiers 8.1/8.1b/18/26D pour couvrir les contrôleurs nouvellement câblés. Un bloc `ACCOUNTING_EXTRA_PERMISSIONS` dédié ajoute les verbes non-CRUD que les policies vérifient réellement (`accounting.tax_compliance.file`, `accounting.depreciation.record`, `accounting.intercompany.clear`, `accounting.expense.approve`, `accounting.asset_impairment.approve`/`.record`, `accounting.budget_scenario.approve`, `accounting.financial-simulation.realize`, `accounting.consolidation.generate-report`/`.record-transaction`/`.eliminate-intercompany`, et depuis Chantier 32.14 `accounting.budget.approve`/`.reject`, plus les `.restore`/`.force_delete` correspondants) — ce bloc ferme le trou où `AssetImpairmentPolicy`/`DepreciationPolicyPolicy` existaient déjà mais leurs permissions n'avaient jamais été seedées (Chantier 8.1b).

**Chantier 32.14 — `BudgetManagementController` avait zéro appel `authorize()` sur 23 de ses 24 méthodes** (seule `generateFromHistory()` était gardée, depuis Chantier 26C — un trou déjà documenté à l'époque, non corrigé faute d'être dans le périmètre de ce volet-là). Confirmé empiriquement qu'un `manager` d'une autre société pouvait approuver/rejeter le budget de n'importe quelle société via `POST budgets/{budget}/approve`/`/reject`, zéro vérification. Corrigé : `BudgetPolicy` gagne deux nouvelles habilités `approve()`/`reject()` (permissions non-standard, seedées ci-dessus), et les 23 méthodes de `BudgetManagementController` appellent désormais toutes `authorize()` contre `BudgetPolicy`/`BudgetScenarioPolicy` (déjà correctement auto-découvertes par Laravel via la convention `\Models\`→`\Policies\`, aucun `Gate::policy()` explicite nécessaire — contrairement à la plupart des policies `Modules\*` de cette app, qui elles ne s'auto-découvrent jamais).

Le rôle `accountant` reçoit l'intégralité des permissions `accounting.*`. Le rôle `finance-manager` reçoit `accounting.*` + `bi.*` + `strategy.*`. Le rôle `manager` reçoit toutes les permissions `accounting.*` sauf `.delete`. Le rôle `purchasing-manager` reçoit un accès restreint (`accounting.invoice.view-any/view/create/update`, sans `delete`). Au niveau route, `Modules/Accounting/routes/api.php` impose en plus `role:accountant,finance-manager,manager,admin` sur chaque groupe (vérifié dans le code — pas seulement documenté).

## Dépendances avec d'autres modules

Le code du module Accounting lui-même n'importe (`use Modules\...`) que `Modules\AI`, `Modules\Shared` et `Modules\Timesheets`. En sens inverse, trois modules importent explicitement des classes Accounting : **BI**, **Core**, et **Payroll**. Le module Sales ne référence pas Accounting directement dans son code PHP, mais le rattache fonctionnellement via `sales_order.currency` et via des workflows d'automatisation (Workflow → `InventoryAccountingActionHandler`). Le trait `HelpdeskLinkable` (module Helpdesk) est appliqué au modèle `Invoice`, permettant à toute facture de générer/lister ses propres tickets Helpdesk.

## Particularités du périmètre life-mdg-erp

La plupart des lacunes documentées à l'origine ont depuis été résolues pour de bon (Chantiers 8.1/8.1b, cette session) : la Consolidation multi-société vit désormais derrière un `ConsolidationGroup` unifié réel (le `ConsolidationHierarchy` parallèle a été conservé — c'est un concept réellement distinct, un arbre de détention avec périodes/éliminations — et partiellement câblé plutôt que supprimé, voir ci-dessous), et les modèles d'écart budgétaire (`BudgetActual`, `BudgetAlert`, `BudgetForecast`, `GLEntry`) se sont reconnectés correctement une fois le socle `App\Models\Company` en place.

**ASC606 Revenue Recognition a été supprimé pour de bon dès Chantier 9** (`RevenueRecognitionEvent`/`RevenueContract`/`ASC606RevenueRecognitionService`/`RevenueContractController` n'existent plus nulle part sur le disque, confirmé par une recherche de fichiers vide au Chantier 32.14) — c'était la norme US-GAAP de reconnaissance de revenu en cinq étapes (allocation des obligations de performance, contrainte de contrepartie variable, échéanciers de passif contractuel), Life MDG tenant sa comptabilité sous OHADA/SYSCOHADA, qui n'a pas d'équivalent. **Cette doc décrivait jusqu'ici cette exclusion comme si le code était encore présent-mais-non-branché — corrigé au Chantier 32.14 : le code n'existe simplement plus.**

**`GLAccount`/`acc_gl_accounts` est un grand livre parallèle mort**, confirmé et documenté à plusieurs reprises cette session (Chantier 18, 26C) : jamais semé, `AccountingService::getBalanceSheet()`/`getIncomeStatement()` (le chemin `GET reports/*`, distinct du chemin réel `financial-reports/ohada/*` fixé au Chantier 18) retournent toujours des collections vides. Le Chantier 32.14 a trouvé une deuxième conséquence de ce même grand livre mort : `MultiEntityConsolidationService` — la seule logique métier réelle derrière `ConsolidationHierarchy`/`ConsolidationPeriod`/`ConsolidationEntry`/`ConsolidationElimination` (création de période, saisie de balance, éliminations, calcul de l'intérêt minoritaire, `consolidatePeriod()`, `generateConsolidatedStatements()`) — lit systématiquement `ConsolidationEntry->glAccount` (`GLAccount`) pour connaître le type de compte (actif/passif/produit/charge), donc `generateConsolidatedStatements()` planterait de façon fatale (« Attempt to read property on null ») dès la première tentative réelle avec de vraies données. Le service a **zéro appelant nulle part** dans l'app (seule `ConsolidationHierarchyController` existe, et elle ne fait que du CRUD sur la fiche hiérarchie elle-même, jamais sur ses périodes/entrées/éliminations) — confirmé cassé et orphelin, documenté ici comme un chantier futur dédié (repointer tout le sous-système sur le vrai grand livre `acc_journal_entry_lines`/`ChartOfAccount`, à l'image de ce qui a déjà été fait pour `OhadaReportService` au Chantier 18) plutôt que réparé dans cette passe — trop large pour une correction chirurgicale, et la décision de toucher au modèle de données `ConsolidationHierarchy` (déjà explicitement conservé par un chantier précédent) dépasse le mandat d'un audit de résolution.

**Chantier 32.14 (audit approfondi 14 couches) — les 10 jobs `extends BaseAsyncJob` de `Modules\Accounting\Jobs\` ont été supprimés, pas réparés.** Tous fatal à la construction (`parent::__construct($id)` contre une classe de base sans constructeur — même bug déjà documenté pour 4 jobs Core au Chantier 32.1), zéro site de dispatch réel nulle part dans l'app, et — plus grave — leur logique métier était soit du pur théâtre (nombres codés en dur sans rapport avec les données réelles), soit un doublon confirmé **inférieur** de la logique synchrone déjà réelle et déjà testée de `ConsolidationService` (`EliminateIntercompanyJob`/`CalculateMinorityInterestJob` dupliquaient `eliminateIntercompanyTransactions()`/le calcul d'intérêt minoritaire de `generateReport()`, en pire — sans la trace d'audit `ConsolidationGroupEntry`, avec une formule bidon plutôt que la vraie donnée de propriété). Voir `Modules/Accounting/app/Jobs/README.md` pour le détail complet. Deux autres orphelins confirmés cassés ont été supprimés au même chantier : `InvoiceService` (absorbé dans `InvoiceController`, voir « Services ») et `LaborCostAllocationService` (filtrait sur une colonne `is_billable` qui n'a jamais existé sur `time_allocations` — seule `billable` existe — silencieusement toujours 0 plutôt qu'une erreur SQL ; sa partie « écriture d'écriture comptable » ne persistait d'ailleurs jamais rien de réel). `AdvancedTaxComplianceService`, elle, a été **activée** plutôt que supprimée — voir « Services ».

36 des 42 tables `acc_*` créées par la migration fourre-tout de scaffold (`database/migrations/2026_05_29_000003_create_all_missing_module_tables.php`) n'ont aucun modèle Eloquent (schéma stub `id/tenant_id/data/timestamps`, jamais interrogées par le code) — dette de scaffold inerte laissée telle quelle, personne n'y référence rien.
