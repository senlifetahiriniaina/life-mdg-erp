<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 9 (Socle group audit): drops the confirmed-dead-weight "pure stub"
 * tables belonging to the Socle CORE / système modules (Core, AI, Security,
 * AuditLog, API, Integration, Validation, Shared, Settings, Setup, Workflow,
 * Calendar) — tables scaffolded by the catch-all
 * `2026_05_29_000003_create_all_missing_module_tables.php` migration at the
 * generic `id/tenant_id/status/data/timestamps` schema and never patched
 * with real columns by any later migration, with zero Eloquent model
 * anywhere in the repo declaring `$table` against them.
 *
 * Investigation notes (see the accompanying chantier report for full detail):
 *
 * - Of these 12 modules, only Core and Workflow have any stub-table block at
 *   all in the catch-all migration (`$core`/`$wf` arrays) — AI, Security,
 *   AuditLog, API, Integration, Validation, Shared, Settings, Setup, and
 *   Calendar have zero entries there; every one of their tables comes from a
 *   dedicated, real migration. `docs/06-BASE-DE-DONNEES/INVENTAIRE-01-SOCLE.md`
 *   confirms this for those 10 modules, so nothing to drop for them here.
 *
 * - `Modules/Validation`'s 8-table stub block (`$val`) was found, on
 *   verification, to have been patched with real columns for every single
 *   table — no pure stubs remain there either.
 *
 * - Core's 14-table stub block (`$core`) was documented as having 6 pure
 *   stubs, but re-verifying against every migration (not just files matching
 *   a `patch_stub_*` naming convention) found that `core_approval_decisions`,
 *   `core_import_rows`, and `core_workflow_states` were in fact already
 *   patched with real columns by `2026_05_29_000019_patch_wave7_core_email_
 *   wa_products.php` / `2026_05_29_000022_patch_wave10_comprehensive.php` /
 *   `2026_05_29_000048_patch_wave36_core_schema_gaps.php` — the documentation
 *   inventory was stale on this point. Only 3 of Core's 14 stub tables are
 *   genuinely still pure-stub AND have zero Eloquent model anywhere
 *   referencing them (confirmed via a repo-wide grep outside migrations):
 *   `core_encrypted_fields`, `core_key_rotations`, `core_validation_audits`.
 *   These are dropped below.
 *
 * - Workflow's `$wf` stub block was documented as having 6 pure stubs, but
 *   the same re-verification found `wfd_actions`/`wfd_execution_logs` were
 *   patched by `2026_05_29_000032_patch_wave20_workflow_definitions.php` /
 *   `..._000033_patch_wave21_wfd_execution_logs_result.php`, `webhook_events`
 *   was patched by `2026_05_29_000026_patch_wave15_whatsapp_email_logistics_
 *   marketing.php`, and `workflow_chain_definitions` was patched by
 *   `2026_05_29_000038_patch_wave26_workflow_chain_soft_deletes.php`. That
 *   leaves only `workflow_chain_executions` and `workflow_execution_steps`
 *   genuinely unpatched — but BOTH have real, actively-used Eloquent models
 *   (`WorkflowChainExecution`/`WorkflowExecutionStep`) with real controller/
 *   service consumers (`WorkflowChainController`, `WorkflowExecutionController`,
 *   `WorkflowEngineService::execute()` writes a `WorkflowChainExecution` row
 *   on every real trigger-based workflow run; `WorkflowDefinitionController::
 *   executions()` eager-loads the `steps` relation backed by
 *   `WorkflowExecutionStep`) — so NEITHER is dropped here. This is flagged
 *   as an active landmine for a future migration (the live code already
 *   writes/reads columns — `workflow_definition_id`, `trigger_key`,
 *   `context_snapshot`, `status`, `started_at`, `completed_at`, `result_log`,
 *   `error_message`, `execution_id`, `step_index`, `action_key`,
 *   `input_context`, `output`, `duration_ms`, `executed_at` — that do not
 *   exist on the live `id/tenant_id/status/data/timestamps` schema), not a
 *   dead-weight drop candidate — see the chantier report for detail.
 *
 * `Modules\Core\Models\{EncryptedField,KeyRotationLog}` (Security's
 * differently-named, non-`core_`-prefixed real models backing
 * `encrypted_fields`/`key_rotation_logs`) and `Modules\Core\Models\
 * ValidationRule`/`Modules\Validation`'s own real validation-audit
 * concepts were double-checked to confirm none of them alias onto these
 * `core_`-prefixed table names — the collision risk this session has
 * repeatedly found elsewhere (Security's `company_id` string/int mismatch,
 * `api_keys` triple-collision documented in the same inventory doc) does not
 * apply here: these three tables are simply unclaimed by any model at all.
 *
 * This is a one-way cleanup: down() intentionally does not attempt to
 * recreate the generic stub schema, matching the established convention of
 * `2026_09_02_000001_drop_excluded_module_and_collision_stub_tables.php` and
 * `2026_09_02_000002_drop_asc606_and_mobile_auth_tables.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'core_encrypted_fields',
            'core_key_rotations',
            'core_validation_audits',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }

    public function down(): void
    {
        // Intentionally left empty — these are dead generic stub tables with
        // zero live readers (no Eloquent model anywhere in the repo declares
        // $table against them); there is no schema worth restoring. Matches
        // the down() convention already established by
        // 2026_05_29_000003_create_all_missing_module_tables.php and this
        // session's other destructive stub-table-cleanup migrations.
    }
};
