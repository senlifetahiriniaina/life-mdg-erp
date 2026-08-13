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

    public function auditLogs(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\Modules\AuditLog\Models\AuditLog::class, 'auditable');
    }
}
