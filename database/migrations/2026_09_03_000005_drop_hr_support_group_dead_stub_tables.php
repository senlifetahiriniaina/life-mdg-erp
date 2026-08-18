<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 9 (HR / Payroll / Timesheets / Projects / Helpdesk group audit):
 * drops the confirmed-dead-weight "pure stub" tables belonging to this
 * group — tables scaffolded by the catch-all
 * `2026_05_29_000003_create_all_missing_module_tables.php` migration at the
 * generic `id/tenant_id/[status]/data/timestamps` schema and never patched
 * with real columns by any later migration, with zero Eloquent model
 * anywhere in the repo declaring `$table` against them.
 *
 * `docs/06-BASE-DE-DONNEES/INVENTAIRE-05-RH-SUPPORT.md` was used as a
 * starting point only — it was NOT trusted as ground truth. Every table it
 * lists as "pure stub" was re-verified against the real migration history
 * (every `patch_*`/`fix_*`/`add_*` migration that touches the table, not
 * just ones matching an obvious naming convention) and against a repo-wide
 * grep for Eloquent models and raw `DB::table()` consumers. The doc turned
 * out to be stale in both directions:
 *
 * - **Helpdesk (doc claimed 10 pure stubs): all 16 of the module's original
 *   catch-all-scaffolded candidates (`hd_chat_messages`, `hd_chat_sessions`,
 *   `hd_escalation_events`, `hd_escalation_rules`,
 *   `hd_helpdesk_sla_policies`, `hd_kb_article_views`,
 *   `hd_kb_portal_articles`, `hd_kb_portal_categories`, `hd_sla_breaches`,
 *   `hd_sla_policies`, `hd_teams`, `hd_ticket_comments`,
 *   `helpdesk_bot_deflections`, `helpdesk_csat_campaigns`,
 *   `helpdesk_csat_surveys`, `helpdesk_forum_posts`) turned out to have been
 *   patched with real columns by one or more of
 *   `2026_05_29_000005_patch_stub_table_schemas.php`,
 *   `2026_05_29_000008_patch_helpdesk_crm_stub_columns.php`,
 *   `2026_05_29_000012_patch_bi_planning_hr_remaining_columns.php`,
 *   `2026_05_29_000028_patch_wave16_pos_bi_projects_helpdesk.php`,
 *   `2026_05_31_000001_patch_core_test_compatibility.php`,
 *   `2026_05_31_700001_patch_documents_helpdesk_missing_columns.php`,
 *   `2026_06_02_000002_patch_bi_helpdesk_missing_columns.php`, and
 *   `2026_08_21_000003_add_helpful_counts_to_hd_kb_portal_articles.php`.
 *   The `cs_*` (27 tables) customer-service-AI family is not catch-all
 *   scaffolding at all — it has its own real, non-generic schema from
 *   `2026_08_16_000001_create_customer_service_ai_tables.php` /
 *   `2026_08_23_000001_create_remaining_cs_ai_tables.php`. **Net: zero pure
 *   stub tables remain in Helpdesk. Nothing dropped for this module.**
 *
 * - **Projects (doc claimed 5 pure stubs, 3 of them — `prj_epics`,
 *   `prj_sprints`, `project_task_dependencies` — from this module's own
 *   catch-all block): all 3 were patched by one or more of
 *   `2026_05_29_000005_patch_stub_table_schemas.php`,
 *   `2026_05_29_000015_patch_wave4_columns.php`,
 *   `2026_05_29_000016_patch_wave5_columns.php`, and
 *   `2026_05_29_000025_patch_wave13_remaining_schema_gaps.php`. (The other
 *   2 of the doc's 5 — `prj_project_billing`/`prj_resource_capacity` — were
 *   already correctly listed as patched by the doc itself.) **Net: zero
 *   pure stub tables remain in Projects. Nothing dropped for this module.**
 *
 * - **HR (doc claimed 17 pure stubs)**: `hr_positions` was miscategorized
 *   as pure-stub by the doc — `2026_08_18_000002_fix_positions_calendar_
 *   events_forum_replies_schema.php` patched it with the real columns
 *   `Modules\HR\Models\Position` (in this module's real "basique" scope
 *   per CLAUDE.md) actually fills; kept, not touched here. `hr_courses`,
 *   `hr_recruitment_jobs`, `hr_performance_cycles`, and `hr_appraisal_
 *   cycles` were also miscategorized as pure-stub — all patched by
 *   `2026_05_29_000009_patch_logistics_ecommerce_hr_stub_columns.php` /
 *   `2026_05_29_000012_patch_bi_planning_hr_remaining_columns.php` /
 *   `2026_05_29_000020_patch_wave8_mfg_inventory_hr_logistics.php` — left
 *   alone per this chantier's "don't touch patched tables" rule regardless
 *   of whether anything actually consumes the patched columns yet.
 *   `hr_job_applications` only ever appears, beyond the catch-all creation,
 *   in `2026_05_04_000002_add_performance_indexes.php` — but that migration
 *   guards every index behind an `array_filter` that skips any table whose
 *   indexed columns don't already exist, and `job_posting_id` never exists
 *   on the bare stub, so that migration is a permanent no-op for this table
 *   — it remains a genuine pure stub. The remaining 10 confirmed pure
 *   stubs (`hr_appraisal_competencies`, `hr_appraisal_goals`,
 *   `hr_course_enrollments`, `hr_interview_schedules`, `hr_interviews`,
 *   `hr_job_applications`, `hr_learning_paths`, `hr_recruitment_applicants`,
 *   `hr_review_goals`, `hr_training_enrollments`) have zero Eloquent model
 *   anywhere in the repo (`Modules/HR/app/Models/` was enumerated in full;
 *   no model declares `$table` against any of them) and zero reference of
 *   any kind — not even a raw `DB::table()` call — anywhere outside
 *   migrations. They correspond exactly to the ATS/recruitment,
 *   360°-performance-review, and training-catalogue functionality CLAUDE.md
 *   already documents as intentionally excluded from HR's "basique" scope.
 *   Dropped below with full confidence (case 3: no model at all).
 *
 *   `hr_performance_appraisals` (also in the doc's pure-stub list, also
 *   zero-model) is the one HR table from this list deliberately **NOT**
 *   dropped, despite otherwise qualifying: `app/Services/DashboardService.
 *   php`'s `team_performance_avg` widget references it via
 *   `DB::table('hr_performance_appraisals')->avg('overall_score')`, wrapped
 *   in that service's own `safe()` try/catch-to-0 fallback (the same
 *   fallback-first pattern documented throughout this app, e.g. Strategy's
 *   `training_roi`/`time_to_fill`). Since `overall_score` was never a real
 *   column on this stub, the query has in practice always thrown and
 *   fallen back to 0, and dropping the table would produce the identical
 *   observable behavior (a missing table throws just as reliably as a
 *   missing column, caught by the same `catch (\Throwable)`) — but that
 *   root-level file sits outside this chantier's HR/Payroll/Timesheets/
 *   Projects/Helpdesk module boundary, and 360°-performance-reviews being
 *   out of scope is a documented, accepted design choice rather than an
 *   already-established "delete this" precedent the way the Timesheets
 *   case below is. Left as a Category-C-flavoured "kept out of caution"
 *   table rather than dropped — see the chantier report for the full
 *   reasoning.
 *
 * - **Timesheets (doc claimed 0 pure stubs — "5 tables, all patched")**:
 *   re-verification found this was wrong for 2 of the module's 5 catch-all
 *   candidates. `timesheets_sheets` is referenced nowhere in live code —
 *   only in comments in `Modules/Timesheets/app/Models/TimesheetPeriod.php`,
 *   `.../Http/Controllers/Api/TimesheetAdvancedController.php`, and
 *   `.../tests/Feature/TimesheetsTest.php` that all explicitly document it
 *   as the stub table the now-deleted `Modules\Timesheets\Models\Timesheet`
 *   used to (badly) back before Chantier 8.4 rewired everything onto the
 *   real `TimesheetPeriod`/`TimesheetEntry` models — an already-established
 *   dead-parallel-subsystem precedent (case 4), not a fresh judgment call.
 *   `timesheets_entries` (distinct from the real, patched, actively-used
 *   `timesheet_entries` — singular — backing `Modules\Timesheets\Models\
 *   TimesheetEntry`) is referenced exactly once outside migrations:
 *   `Modules/Calendar/app/Services/ModuleEventAggregatorService.php`'s
 *   `importTimesheetEntries()`, itself wrapped in `aggregateForUser()`'s
 *   blanket `try { ... } catch (\Throwable) { }` (silently skip) — and
 *   it queries `employee_id`/`work_date`/`status`/`hours`/
 *   `task_description`/`project_id`, none of which exist on this table's
 *   bare `id/tenant_id/data/timestamps` schema (this module's catch-all
 *   block never added a `status` column at all, unlike HR's/Quality's).
 *   This is a dangling reference to the same already-deleted `Modules\
 *   Timesheets\Http\Controllers\Api\TimeEntryController`/`Models\TimeEntry`
 *   subtree CLAUDE.md documents Chantier 8.4 removing as "a fully broken,
 *   fully unrouted parallel duplicate of the real TimesheetEntryController/
 *   TimesheetEntry" — same case-4 precedent as `timesheets_sheets`. Both
 *   dropped below. (Calendar's dangling `importTimesheetEntries()` call is
 *   left as-is — it already silently no-ops today via the same try/catch,
 *   so dropping the table changes nothing observable, and editing
 *   `Modules/Calendar` is outside this HR/Payroll/Timesheets/Projects/
 *   Helpdesk chantier's module boundary.)
 *
 * - **Payroll**: has no catch-all stub block at all — its 3 tables
 *   (`payroll_runs`, `payslips`, `salary_components`) all come from
 *   dedicated, real migrations (`2026_06_08_00000{1,2,3}_*`). Nothing to
 *   audit or drop for this module.
 *
 * This is a one-way cleanup: down() intentionally does not attempt to
 * recreate the generic stub schema, matching the established convention of
 * `2026_09_02_000001_drop_excluded_module_and_collision_stub_tables.php`
 * and the sibling `2026_09_03_000001`/`2026_09_03_000002` group-audit
 * migrations from this same chantier.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            // HR — ATS/recruitment, 360°-performance-review, and training-
            // catalogue tables: zero Eloquent model anywhere, zero reference
            // of any kind outside migrations. Matches CLAUDE.md's documented
            // "basique" HR scope exclusion.
            'hr_appraisal_competencies',
            'hr_appraisal_goals',
            'hr_course_enrollments',
            'hr_interview_schedules',
            'hr_interviews',
            'hr_job_applications',
            'hr_learning_paths',
            'hr_recruitment_applicants',
            'hr_review_goals',
            'hr_training_enrollments',

            // Timesheets — dangling remnants of the dead `Timesheet`/
            // `TimeEntry` parallel subsystem Chantier 8.4 already deleted
            // (see docblock above).
            'timesheets_sheets',
            'timesheets_entries',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }

    public function down(): void
    {
        // Intentionally left empty — these are dead generic stub tables with
        // zero live readers; there is no schema worth restoring. Matches the
        // down() convention already established by
        // 2026_05_29_000003_create_all_missing_module_tables.php and this
        // session's other destructive stub-table-cleanup migrations.
    }
};
