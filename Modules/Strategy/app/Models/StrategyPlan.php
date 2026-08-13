<?php

namespace Modules\Strategy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrategyPlan extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $fillable = [
        'tenant_id',
        'name',
        'vision',
        'mission',
        'period_start',
        'period_end',
        'framework',
        'status',
        'health_score',
        'created_by',
    ];

    protected $casts = [
        'health_score' => 'integer',
        'period_start' => 'integer',
        'period_end'   => 'integer',
    ];

    public function pillars(): HasMany
    {
        return $this->hasMany(StrategyPillar::class, 'plan_id');
    }

    public function objectives(): HasMany
    {
        return $this->hasMany(StrategyObjective::class, 'plan_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
