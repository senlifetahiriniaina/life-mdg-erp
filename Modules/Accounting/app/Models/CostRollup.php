<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pre-computed cost aggregation for a given entity + period.
 *
 * Refreshed on-demand by CostEngineService::rollupCosts().
 * Cached for fast dashboard retrieval; never used as the source of truth
 * (cost_entries always holds the atomic records).
 */
class CostRollup extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'cost_rollups';

    protected $fillable = [
        'tenant_id',
        'entity_type',
        'entity_id',
        'entity_name',
        'period',
        'capex_total',
        'opex_total',
        'finex_total',
        'riskex_total',
        'total_cost',
        'currency',
        'unit_cost',
        'margin',
        'margin_pct',
        'computed_at',
    ];

    protected $casts = [
        'capex_total'  => 'float',
        'opex_total'   => 'float',
        'finex_total'  => 'float',
        'riskex_total' => 'float',
        'total_cost'   => 'float',
        'unit_cost'    => 'float',
        'margin'       => 'float',
        'margin_pct'   => 'float',
        'computed_at'  => 'datetime',
    ];

    // ─── Scopes ───────────────────────────────────────────────────────────

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForPeriod(Builder $query, string $period): Builder
    {
        return $query->where('period', $period);
    }

    public function scopeEntityType(Builder $query, string $type): Builder
    {
        return $query->where('entity_type', $type);
    }

    public function scopeForEntity(Builder $query, string $type, int $id): Builder
    {
        return $query->where('entity_type', $type)->where('entity_id', $id);
    }

    // ─── Computed attributes ──────────────────────────────────────────────

    /**
     * Percentage breakdown of each category within the total.
     */
    public function getCategoryPercentagesAttribute(): array
    {
        $total = $this->total_cost ?: 1;

        return [
            'CAPEX'  => $this->total_cost > 0 ? round(($this->capex_total  / $total) * 100, 1) : 0,
            'OPEX'   => $this->total_cost > 0 ? round(($this->opex_total   / $total) * 100, 1) : 0,
            'FINEX'  => $this->total_cost > 0 ? round(($this->finex_total  / $total) * 100, 1) : 0,
            'RISKEX' => $this->total_cost > 0 ? round(($this->riskex_total / $total) * 100, 1) : 0,
        ];
    }
}
