<?php

namespace App\Traits;

use Modules\Core\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

trait AuditableActions
{
    public static function bootAuditableActions(): void
    {
        static::updated(function ($model) {
            if (auth()->check()) {
                $changedAttributes = $model->getChanges();

                foreach ($model->getAuditableFields() as $field) {
                    if (isset($changedAttributes[$field])) {
                        AuditLog::create([
                            'user_id'      => auth()->id(),
                            'company_id'   => auth()->user()?->company_id ?? 0,
                            'user_name'    => auth()->user()?->name,
                            'user_role'    => auth()->user()?->roles?->first()?->name,
                            'action'       => $model->getAuditAction($field),
                            'module'       => $model->getAuditModule(),
                            'event_type'   => 'update',
                            'description'  => $model->getAuditDescription($field),
                            'subject_type' => get_class($model),
                            'subject_id'   => $model->id,
                            'old_values'   => [$field => $model->getOriginal($field)],
                            'new_values'   => [$field => $changedAttributes[$field]],
                            'ip_address'   => request()?->ip(),
                            'user_agent'   => request()?->userAgent(),
                            'created_at'   => now(),
                        ]);
                    }
                }
            }
        });

        static::deleted(function ($model) {
            if (auth()->check()) {
                AuditLog::create([
                    'user_id'      => auth()->id(),
                    'company_id'   => auth()->user()?->company_id ?? 0,
                    'user_name'    => auth()->user()?->name,
                    'user_role'    => auth()->user()?->roles?->first()?->name,
                    'action'       => 'deleted',
                    'module'       => $model->getAuditModule(),
                    'event_type'   => 'delete',
                    'description'  => "Deleted {$model->getAuditModelName()}",
                    'subject_type' => get_class($model),
                    'subject_id'   => $model->id,
                    'old_values'   => $model->getAttributes(),
                    'new_values'   => [],
                    'ip_address'   => request()?->ip(),
                    'user_agent'   => request()?->userAgent(),
                    'created_at'   => now(),
                ]);
            }
        });
    }

    protected function getAuditableFields(): array
    {
        return property_exists($this, 'auditableFields')
            ? $this->auditableFields
            : [];
    }

    protected function getAuditAction(string $field): string
    {
        return match ($field) {
            'status' => 'status_changed',
            'approved_by', 'approved_at' => 'approved',
            'rejected_by', 'rejected_at' => 'rejected',
            default => 'field_changed',
        };
    }

    protected function getAuditDescription(string $field): string
    {
        return match ($field) {
            'status' => "{$field} changed from {$this->getOriginal($field)} to {$this->getAttribute($field)}",
            'approved_by' => 'Document approved',
            'rejected_by' => 'Document rejected',
            default => "Field {$field} changed",
        };
    }

    protected function getAuditModule(): string
    {
        return property_exists($this, 'auditModule')
            ? $this->auditModule
            : class_basename($this);
    }

    protected function getAuditModelName(): string
    {
        return class_basename($this);
    }
}
