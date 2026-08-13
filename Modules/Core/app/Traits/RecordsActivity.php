<?php

declare(strict_types=1);

namespace Modules\Core\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Modules\Core\Models\AuditLog;
use Modules\Core\Support\AuditOldValuesRegistry;

/**
 * Automatically records create / update / delete events to core_audit_logs.
 *
 * Usage: add `use RecordsActivity;` to any Eloquent model.
 * Optionally set:
 *   protected static string $auditModule = 'CRM';
 *   protected static array  $auditableFields = ['name', 'status']; // empty = all fillable
 */
trait RecordsActivity
{
    public static function bootRecordsActivity(): void
    {
        static::created(function (self $model) {
            self::writeAuditLog('created', 'model_created', $model, [], $model->toAuditArray());
        });

        static::updating(function (self $model) {
            AuditOldValuesRegistry::set($model, $model->getOriginal());
        });

        static::updated(function (self $model) {
            $old = AuditOldValuesRegistry::get($model);
            AuditOldValuesRegistry::forget($model);
            $new = $model->toAuditArray();

            $filteredOld = array_intersect_key($old, $new);
            $hasChanges = false;

            foreach ($new as $k => $v) {
                if (($filteredOld[$k] ?? null) !== $v) {
                    $hasChanges = true;
                    break;
                }
            }

            if (! $hasChanges) {
                return;
            }

            self::writeAuditLog('updated', 'model_updated', $model, $filteredOld, $new);
        });

        static::deleted(function (self $model) {
            self::writeAuditLog('deleted', 'model_deleted', $model, $model->toAuditArray(), []);
        });
    }

    protected static function writeAuditLog(
        string $action,
        string $eventType,
        self $model,
        array $oldValues,
        array $newValues,
    ): void {
        try {
            $userId = Auth::id();
            $user = Auth::user();
            $module = self::resolveAuditModule();
            $label = self::buildLabel($model);

            AuditLog::create([
                'user_id' => $userId,
                'user_name' => $user?->name ?? null,
                // @phpstan-ignore-next-line function.alreadyNarrowedType
                'user_role' => ($user !== null && method_exists($user, 'getRoleNames'))
                                    ? $user->getRoleNames()->first() // @phpstan-ignore-line
                                    : null,
                'action' => $action,
                'module' => $module,
                'event_type' => $eventType,
                'description' => self::buildDescription($action, $label),
                'subject_type' => static::class,
                'subject_id' => $model->getKey(),
                'old_values' => empty($oldValues) ? null : $oldValues,
                'new_values' => empty($newValues) ? null : $newValues,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        } catch (\Throwable) {
            // Never let audit logging crash the main flow
        }
    }

    public function toAuditArray(): array
    {
        /** @phpstan-ignore staticProperty.notFound */
        $fields = property_exists(static::class, 'auditableFields') ? (static::$auditableFields ?? []) : [];

        if (empty($fields)) {
            $data = $this->toArray();
            foreach (['password', 'remember_token', 'api_token'] as $sensitive) {
                unset($data[$sensitive]);
            }

            return $data;
        }

        return array_intersect_key($this->toArray(), array_flip($fields));
    }

    protected static function resolveAuditModule(): ?string
    {
        if (property_exists(static::class, 'auditModule')) {
            /** @phpstan-ignore staticProperty.notFound */
            return static::$auditModule;
        }

        if (preg_match('/Modules\\\\([^\\\\]+)\\\\/', static::class, $m)) {
            return $m[1];
        }

        return null;
    }

    protected static function buildLabel(self $model): string
    {
        $class = class_basename($model);

        foreach (['name', 'title', 'number', 'first_name', 'email'] as $field) {
            if (! empty($model->{$field})) {
                return "{$class} « {$model->{$field}} »";
            }
        }

        return "{$class} #{$model->getKey()}";
    }

    protected static function buildDescription(string $action, string $label): string
    {
        return match ($action) {
            'created' => "Création de {$label}",
            'updated' => "Modification de {$label}",
            'deleted' => "Suppression de {$label}",
            default => ucfirst($action)." de {$label}",
        };
    }
}
