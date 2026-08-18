<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 8.8 (Finance/Commercial stub-table audit — Accounting, CRM, Sales).
 *
 * Drops confirmed-dead stub tables scaffolded by the catch-all
 * `2026_05_29_000003_create_all_missing_module_tables.php` migration for these 3
 * modules, verified fresh against real code (not just against
 * `docs/06-BASE-DE-DONNEES/INVENTAIRE-02-FINANCE-COMMERCIAL.md`, which itself turned
 * out to be stale/approximate in a few places — see the corrections below).
 *
 * ── Accounting: 33 tables, zero Eloquent model anywhere in the repo ──────────────
 *
 * `docs/06-BASE-DE-DONNEES/...` and `CLAUDE.md`'s "36 of 42 acc_ tables have no
 * Eloquent model" note both listed 37/36 candidate table names. Re-verified against
 * every one of Accounting's 73 real model files (all of which declare an explicit
 * `protected $table`, so there is no naming-convention ambiguity to check separately):
 *
 *   - `acc_fiscal_years` is NOT dead — `FixedAsset`'s sibling `FiscalYear` model
 *     (Modules/Accounting/app/Models/FiscalYear.php) declares
 *     `protected $table = 'acc_fiscal_years'` and is a real, live model. The doc's
 *     list was stale on this one point; excluded here, not dropped.
 *   - `acc_revenue_contracts`, `acc_revenue_recognition_events`,
 *     `acc_revenue_recognition_policies` (the ASC606 stub trio) were ALREADY dropped
 *     by `2026_09_02_000002_drop_asc606_and_mobile_auth_tables.php` from a parallel
 *     session — excluded here to avoid a redundant duplicate drop (Schema::hasTable()
 *     guards would make it harmless either way, but this keeps the two migrations'
 *     table lists non-overlapping and easier to audit).
 *   - The remaining 33 names split into two groups, both equally dead but for a
 *     subtlety worth recording: 24 were NEVER touched by any migration beyond the
 *     original catch-all (still the bare `id/tenant_id/data/timestamps` scaffold
 *     schema), while 9 — `acc_consolidation_entities`, `acc_consolidations`,
 *     `acc_financial_reports`, `acc_tax_calculation_audits`, `acc_tax_categories`,
 *     `acc_tax_compliance`, `acc_tax_deductions`, `acc_tax_rule_audit_logs`,
 *     `acc_tax_rules` — were, contrary to what "pure stub, never retouched" implies,
 *     later patched with real, purpose-built columns by several `patch_wave*`
 *     migrations (tax_rule_id/name/type/amount/percentage/conditions/jurisdiction,
 *     consolidation_method/entity_type/ownership_percentage/exchange_rate, etc.).
 *     None of that matters for the drop decision, though: a repo-wide grep (outside
 *     migrations) for both the bare table-name string and every plausible model class
 *     confirms zero Eloquent model, zero controller/service reference, zero raw
 *     `DB::table()` reference, and zero factory for any of the 9 — the schema
 *     investment was never wired to any application code at all, so it is exactly as
 *     dead as the 24 untouched ones, just with more unused columns.
 *   - `acc_consolidations` is the FK target of `acc_consolidation_subsidiaries`
 *     (`consolidation_id`, from the dedicated, non-catch-all
 *     `2026_05_30_000001_create_acc_consolidation_subsidiaries_table.php`). That child
 *     table is itself unmodeled and unreferenced anywhere in the app — but it is NOT
 *     part of this chantier's catch-all-stub scope (own dedicated migration, and the
 *     inventory doc lists it under Accounting's "real" tables), so it is left in place
 *     rather than dropped here; only its now-dangling FK constraint to
 *     `acc_consolidations` is dropped, matching the constraint-only-drop precedent in
 *     `2026_09_02_000001_drop_excluded_module_and_collision_stub_tables.php`.
 *
 * ── CRM: 1 table, real model but confirmed dead-parallel-subsystem ───────────────
 *
 * All 12 "pure stub" CRM tables the inventory doc lists in fact have a real Eloquent
 * model (`AiAgentRun`, `CallLog`, `EmailSequenceEnrollment`, `EmailSequenceStep`,
 * `EngagementSignal`, `OpportunityHistory`, `OpportunityScore`, `PipelineSnapshot`,
 * `ProductBundle`, `SequenceEnrollment`, `WebFormSubmission`, `WinLossRecord`) — the
 * doc's "stub" label refers only to the underlying table schema, not to model
 * existence. 11 of the 12 have real, live controller/service consumers (confirmed via
 * `<Model>::` grep across `Http/`, `Services/`, `routes/`) and are kept as Category
 * "real model, real consumer" — see the session report for the full list.
 *
 * `EmailSequenceStep` (`crm_email_sequence_steps`) is the one exception: it has ZERO
 * consumers anywhere (only its own class file + its own factory reference it) and is a
 * confirmed dead duplicate of the real, live `SequenceStep` model
 * (`crm_sequence_steps`) — `EmailSequence::steps()` itself points at `SequenceStep::
 * class`, and every real caller (`EmailSequenceController`, `EmailSequenceService`,
 * `SequenceService`, `ContactWebController`) goes through that relation, never through
 * `EmailSequenceStep`. Same dead-parallel-subsystem pattern already established this
 * session (CRM's own deleted `TerritoryManagementController` subtree, Logistics'
 * `wh_*`/`lgx_*`, root `WorkflowEngine`) — a real, working equivalent already covers
 * the identical concept in the same module. The model file and its factory are deleted
 * alongside the table.
 *
 * ── Sales: nothing to drop ────────────────────────────────────────────────────────
 *
 * Sales has exactly 3 tables (`sales_orders`, `sales_order_lines`,
 * `sales_quotations`), all created by dedicated migrations
 * (`2026_06_08_000001/2/3_*`), none by the catch-all scaffold — confirmed via grep,
 * zero `'sales_*'` entries exist in `2026_05_29_000003_create_all_missing_module_
 * tables.php` at all. The inventory doc's "3 tables, toutes réelles, aucun stub" was
 * accurate.
 *
 * This is a one-way cleanup: down() intentionally does not attempt to recreate the
 * dropped schemas, matching the down() convention already established by
 * `2026_05_29_000003_create_all_missing_module_tables.php` and this session's other
 * destructive stub-table-cleanup migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Accounting: FK constraint on the confirmed-dead, out-of-scope
        //    acc_consolidation_subsidiaries child table must go before its parent
        //    acc_consolidations is dropped. The child table itself is left in place
        //    (own dedicated migration, not catch-all scaffold — out of this chantier's
        //    scope even though it too has zero model/consumer).
        if (Schema::hasTable('acc_consolidation_subsidiaries')
            && Schema::hasColumn('acc_consolidation_subsidiaries', 'consolidation_id')) {
            Schema::table('acc_consolidation_subsidiaries', function ($table) {
                $table->dropForeign(['consolidation_id']);
            });
        }

        // ── Accounting: 24 tables never retouched beyond the catch-all scaffold ──
        $accountingUntouched = [
            'acc_consolidated_financial_statements', 'acc_consolidation_adjustments',
            'acc_consolidation_rules', 'acc_consolidation_worksheets',
            'acc_ebitda_reconciliations', 'acc_entity_relationships',
            'acc_expense_approvals', 'acc_expense_categories', 'acc_expense_receipts',
            'acc_financial_metric_trends', 'acc_generated_reports',
            'acc_impairment_tests', 'acc_lease_payments', 'acc_minority_interests',
            'acc_ml_matching_metrics', 'acc_operating_leases', 'acc_outstanding_items',
            'acc_reconciliation_exceptions', 'acc_reconciliation_matches',
            'acc_reporting_currencies', 'acc_segment_reports',
            'acc_tax_automation_rules', 'acc_trend_forecasts', 'acc_xbrl_exports',
        ];

        // ── Accounting: 9 tables later patched with real columns by patch_wave*
        //    migrations, but still zero model/controller/service/raw-query consumer ──
        $accountingPatchedButOrphaned = [
            'acc_consolidation_entities', 'acc_consolidations', 'acc_financial_reports',
            'acc_tax_calculation_audits', 'acc_tax_categories', 'acc_tax_compliance',
            'acc_tax_deductions', 'acc_tax_rule_audit_logs', 'acc_tax_rules',
        ];

        foreach ([...$accountingUntouched, ...$accountingPatchedButOrphaned] as $t) {
            Schema::dropIfExists($t);
        }

        // ── CRM: dead duplicate of the real, live SequenceStep model ──
        Schema::dropIfExists('crm_email_sequence_steps');

        // ── Sales: nothing to drop (all 3 tables are real, no catch-all stubs exist) ──
    }

    public function down(): void
    {
        // Intentionally left empty — these are dead scaffold tables (24 untouched +
        // 9 patched-but-never-consumed Accounting stubs, 1 dead-duplicate CRM model
        // table) with zero live readers; there is no schema worth restoring. Matches
        // the down() convention already established by
        // 2026_05_29_000003_create_all_missing_module_tables.php and this session's
        // other destructive-cleanup migrations.
    }
};
