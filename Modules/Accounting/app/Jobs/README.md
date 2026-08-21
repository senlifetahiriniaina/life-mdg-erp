# Accounting Module Async Jobs

This directory is intentionally empty as of Chantier 32.14 (14-layer deep audit of `Modules\Accounting`).

## What used to be here

10 job classes (`ConsolidateFinancialsJob`, `EliminateIntercompanyJob`, `CalculateMinorityInterestJob`,
`ProcessConsolidationAdjustmentsJob`, `CalculateTaxProvisionsJob`, `UpdateDeferredTaxJob`,
`ComputeTransferPricingJob`, `GenerateConsolidatedReportsJob`, `GenerateTaxReportsJob`,
`ValidateComplianceJob`) all `extends Modules\Shared\Jobs\BaseAsyncJob` and were deleted after
confirming, empirically, that they were **fatal on construction** (`parent::__construct($id)` against
a base class that declares no constructor — `Error: Cannot call constructor`, the same bug class
already documented for 4 `Modules\Core` jobs at Chantier 32.1), had **zero real dispatch site**
anywhere in the app (confirmed via a repo-wide grep, not just their own tests), and — critically —
their business logic, even once the constructor bug was fixed, was either pure scaffold theater
(hardcoded numbers unrelated to any real company's data — e.g. `UpdateDeferredTaxJob`'s
`$differences = 500000`) or a confirmed **inferior duplicate** of already-real, already-routed,
already-tested synchronous logic in `Modules\Accounting\Services\ConsolidationService`:
`EliminateIntercompanyJob` duplicated `ConsolidationService::eliminateIntercompanyTransactions()`
(which additionally writes a real `ConsolidationGroupEntry` audit trail the job never did),
`CalculateMinorityInterestJob` duplicated the minority-interest calculation already embedded in
`ConsolidationService::generateReport()` (which derives it from real per-company data via
`Company::minorityInterest()`, not the job's hardcoded `(100 - ownership_percentage) * 100`), and
`ConsolidateFinancialsJob`/`ProcessConsolidationAdjustmentsJob` would have written **fake zero or
hardcoded totals** into real `ConsolidationGroup`/`ConsolidationReport` records had they ever been
wired up — a landmine, not dormant capability. Fixing the constructor/`handle()` bugs and leaving
them in place would have produced jobs that no longer crash but silently write wrong data — worse
than deleting them.

Two of the README's own 12 listed jobs (`RecognizeRevenueJob`, `ProcessContractModificationJob`)
had already been deleted years earlier as part of the ASC606 Revenue Recognition exclusion
(see `CLAUDE.md`'s "Known gaps" section, Chantier 9) — this file was stale even before this pass.

## Where the real functionality lives instead

- Consolidation (elimination, minority interest, consolidated reports): `ConsolidationController`
  (`Http/Controllers/Api/ConsolidationController.php`) → `ConsolidationService`, routed at
  `accounting/consolidation-groups/{group}/...`, real RBAC via `CompanyPolicy`.
- Tax calculation math (VAT, progressive income tax, transfer pricing, deferred tax) — a real,
  well-written, previously **orphaned** service, `Modules\Accounting\Services\AdvancedTaxComplianceService`,
  found during this same audit — now wired into `TaxComplianceReportController::calculate()`
  (`POST accounting/tax-compliance-reports/calculate`), which returns a live calculation without
  persisting anything (the caller still records the final `total_tax_liability`/`total_tax_paid`
  manually via the existing `store()`/`update()` endpoints — auto-populating those fields from the
  calculation is a natural follow-up, left for a future chantier since it changes existing behavior).
- ASC606 revenue recognition: deliberately out of scope (OHADA/SYSCOHADA has no equivalent concept).
