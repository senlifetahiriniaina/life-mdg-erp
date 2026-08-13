<?php

namespace Modules\Strategy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StrategyKpi extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'category',
        'source_module',
        'source_key',
        'source_aggregation',
        'source_filter',
        'unit',
        'frequency',
        'target_value',
        'warning_threshold',
        'critical_threshold',
        'higher_is_better',
        'is_public',
    ];

    protected $casts = [
        'source_filter'   => 'array',
        'higher_is_better' => 'boolean',
        'is_public'       => 'boolean',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(StrategyKpiValue::class, 'kpi_id');
    }

    public function latest(): HasOne
    {
        return $this->hasOne(StrategyKpiValue::class, 'kpi_id')->latestOfMany('recorded_at');
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }
}
