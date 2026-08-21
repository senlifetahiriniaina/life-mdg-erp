<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32.4 — deep 14-layer audit of Modules/AuditLog.
 *
 * Drops `audit_logs` (Modules\AuditLog\Models\AuditLog, `tenant_id`-scoped) —
 * confirmed a real-but-fully-redundant duplicate of the app's actual live
 * audit trail, `Modules\Core\Models\AuditLog` (table `core_audit_logs`,
 * `company_id`-scoped, real 200+ writers via RecordsActivity/
 * AuditableActions, browsed via this very module's own
 * AuditLogApiController/AuditLogWebController — which already query Core's
 * model, not this one).
 *
 * Confirmed empirically (tinker + grep), not assumed from the pre-existing
 * Chantier 8.5-light note that only checked for readers:
 *   - Zero real readers anywhere: this module's own controllers, the ones
 *     you'd expect to browse this table, query Modules\Core\Models\AuditLog
 *     instead. No other reader exists anywhere in the app.
 *   - One real, live writer: App\Listeners\AuthEventSubscriber (registered
 *     in app/Providers/AppServiceProvider.php, deleted alongside this
 *     migration), firing on every Login/Logout/Failed/Lockout event
 *     app-wide — but hardcoding `tenant_id: 0` on every row (never the
 *     real acting user's company), duplicating exactly what
 *     Modules\Core\Listeners\AuditAuthListener already does correctly
 *     (real company_id) for the same 3 events. A wasted DB write on every
 *     single authentication event in the app, feeding a table nothing
 *     reads, with a real scoping bug baked in.
 *   - 8 more writer call sites across 5 Modules/Helpdesk services
 *     (SentimentAnalysisService, AiResponseService, PredictiveEscalationService,
 *     SatisfactionPredictionService, AgentPerformanceAnalyticsService) — all
 *     confirmed unreachable dead code (zero controller/route/job anywhere
 *     ever calls the methods containing these AuditLog::create() calls;
 *     Modules/Helpdesk's own test suite deliberately avoids exercising them,
 *     per a code comment already left by an earlier chantier). Left
 *     untouched (Modules/Helpdesk is outside this chantier's module
 *     boundary) — flagged in CLAUDE.md for a future Helpdesk-scoped pass.
 *     These call sites also used field names (`model_type`/`model_id`/
 *     `changes`) that were never in AuditLog::$fillable in the first place,
 *     so even if ever reached they'd have silently mass-assignment-dropped
 *     those keys and then fatally violated `tenant_id`'s NOT NULL
 *     constraint (no default) — a second, independent bug on top of being
 *     unreachable.
 *   - Modules\AuditLog\Services\AuditService::log() (deleted alongside this
 *     migration) had exactly 2 real callers: the AuthEventSubscriber above,
 *     and Modules\Workflow\Services\Actions\Phase52ActionHandler::
 *     recordAuditEvent() — which already calls AuditService::log() with 6
 *     positional arguments against a method that only ever accepted a
 *     single array parameter, a pre-existing signature mismatch (confirmed
 *     by reading the two files side by side) already silently caught by
 *     that handler's own try/catch and reported as `status: skipped` —
 *     already non-functional before this migration, and stays exactly as
 *     non-functional after it (the catch block now catches a
 *     class-not-found error instead of a TypeError, same silent-skip
 *     outcome, confirmed no Workflow test depends on the old behavior).
 *     Left untouched (Modules/Workflow is outside this chantier's module
 *     boundary) — flagged in CLAUDE.md for a future Workflow-scoped pass.
 *   - Modules\AuditLog\Traits\HasAuditLog's own create/update/delete
 *     auto-logging hook (used by ~200 models app-wide) writes to
 *     Log::channel('audit') (a file, storage/logs/audit.log) — confirmed
 *     empirically to work correctly and completely independently of this
 *     table. Its dead auditLogs() MorphMany relation (zero callers
 *     anywhere, and independently broken — it targeted 'auditable_type'/
 *     'auditable_id' morph columns that were never on this table's real
 *     schema, which used entity_type/entity_id instead) was removed from
 *     the trait rather than left dangling on a deleted class.
 *
 * Repointing HasAuditLog's dead relation onto Core's AuditLog (which does
 * have a real, populated 'subject' morph pair) was investigated and
 * rejected: RecordsActivity/AuditableActions (Core's real DB writers) are
 * not used by every HasAuditLog-consuming model (e.g.
 * Modules\Calendar\Models\Calendar uses HasAuditLog only) — repointing
 * would silently return empty results for those models' real (file-only)
 * audit trail, a misleading half-fix rather than a genuine one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('audit_logs');
    }

    public function down(): void
    {
        // Deliberately no-op: the dropped table backed a confirmed-dead,
        // fully-redundant duplicate of Modules\Core\Models\AuditLog — its
        // model/service/writer have been deleted alongside this migration,
        // so there is nothing left to recreate the schema for.
    }
};
