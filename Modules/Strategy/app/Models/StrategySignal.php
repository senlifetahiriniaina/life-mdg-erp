<?php

namespace Modules\Strategy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StrategySignal extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $fillable = [
        'tenant_id',
        'type',
        'source_module',
        'source_metric',
        'title',
        'description',
        'recommendation',
        'impacted_objective_ids',
        'is_read',
        'is_dismissed',
        'detected_at',
        'expires_at',
    ];

    protected $casts = [
        'impacted_objective_ids' => 'array',
        'is_read'                => 'boolean',
        'is_dismissed'           => 'boolean',
        'detected_at'            => 'datetime',
        'expires_at'             => 'datetime',
    ];

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeActive($query)
    {
        return $query->where('is_dismissed', false);
    }
}
