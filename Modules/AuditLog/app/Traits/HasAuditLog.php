<?php

declare(strict_types=1);

namespace Modules\AuditLog\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Audit logging trait — automatically records create/update/delete events.
 */
trait HasAuditLog
{
    public static function bootHasAuditLog(): void
    {
        static::created(fn (Model $model) => static::writeAuditEntry('created', $model));
        static::updated(fn (Model $model) => static::writeAuditEntry('updated', $model));
        static::deleted(fn (Model $model) => static::writeAuditEntry('deleted', $model));
    }

    protected static function writeAuditEntry(string $event, Model $model): void
    {
        try {
            $userId = Auth::id();
            $changes = $event === 'updated' ? $model->getDirty() : [];

            Log::channel('audit')->info("[$event] " . class_basename($model), [
                'model'   => get_class($model),
                'id'      => $model->getKey(),
                'user_id' => $userId,
                'changes' => $changes,
            ]);
        } catch (\Throwable) {
            // Never let audit logging break normal operations
        }
    }

    // Chantier 32.4 (14-layer audit): this trait's own auditLogs() MorphMany
    // relation was removed here — it targeted Modules\AuditLog\Models\AuditLog
    // (table `audit_logs`), a confirmed-dead duplicate of the app's real audit
    // trail (Modules\Core\Models\AuditLog, table `core_audit_logs`) deleted in
    // the same chantier, and had zero real callers anywhere in the app even
    // before that (confirmed via grep). It was also independently broken on
    // its own terms — it targeted 'auditable_type'/'auditable_id' morph
    // columns that never existed on that table's real schema (which used
    // entity_type/entity_id instead, no morph pair at all).
    //
    // Repointing it onto Core's real, populated 'subject' morph pair was
    // investigated and rejected: Core's DB-based writers (RecordsActivity/
    // AuditableActions) are not used by every model that consumes this trait
    // (e.g. Modules\Calendar\Models\Calendar uses HasAuditLog only) — a
    // repointed relation would silently return empty for those models' real
    // audit trail (which above's bootHasAuditLog() correctly writes to
    // storage/logs/audit.log, not the database), a misleading half-fix
    // rather than a genuine one. Neither RecordsActivity nor AuditableActions
    // exposes its own relation onto Modules\Core\Models\AuditLog either
    // (confirmed by reading both traits — both only register model-event
    // hooks, no relation method) — a model that wants its own queryable
    // trail today has to query Modules\Core\Models\AuditLog::where(
    // 'subject_type', static::class)->where('subject_id', $this->id)
    // directly; adding a shared convenience relation for that is a genuine
    // Core-layer improvement, but out of this AuditLog-module chantier's
    // boundary (Modules/Core's own audit already went through its own
    // 14-layer pass in Chantier 32.1) — flagged in CLAUDE.md rather than
    // built here.
}
