<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Atomic cost record.
 *
 * Each entry belongs to exactly one cost category (CAPEX/OPEX/FINEX/RISKEX)
 * and is allocated to a polymorphic target (bom_component, product, project,
 * client, or production_order).
 */
class CostEntry extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    use \Modules\Core\Models\Concerns\BelongsToTenant;

    protected $table = 'cost_entries';

    protected $fillable = [
        'tenant_id',
        'category_code',
        'amount',
        'currency',
        'amount_xof',
        'allocatable_type',
        'allocatable_id',
        'source_module',
        'source_type',
        'source_id',
        'description',
        'period',
        'fiscal_year',
        'cost_driver',
        'units',
        'unit_cost',
        'is_estimated',
        'is_allocated',
        'allocated_at',
        'created_by',
    ];

    protected $casts = [
        'amount'       => 'float',
        'amount_xof'   => 'float',
        'units'        => 'float',
        'unit_cost'    => 'float',
        'is_estimated' => 'boolean',
        'is_allocated' => 'boolean',
        'allocated_at' => 'datetime',
    ];

    // ─── Polymorphic relationship ──────────────────────────────────────────

    public function allocatable()
    {
        return $this->morphTo();
    }

    // ─── Scopes ───────────────────────────────────────────────────────────

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForPeriod(Builder $query, string $period): Builder
    {
        return $query->where('period', $period);
    }

    public function scopeCategory(Builder $query, string $code): Builder
    {
        return $query->where('category_code', $code);
    }

    public function scopeForEntity(Builder $query, string $type, int $id): Builder
    {
        return $query->where('allocatable_type', $type)->where('allocatable_id', $id);
    }

    // ─── Mutators ─────────────────────────────────────────────────────────

    /**
     * Auto-compute unit_cost when both amount and units are set.
     */
    protected static function booted(): void
    {
        static::saving(function (CostEntry $entry) {
            if ($entry->units && $entry->units > 0 && $entry->amount) {
                $entry->unit_cost = round($entry->amount / $entry->units, 6);
            }
            if (! $entry->fiscal_year && $entry->period) {
                $entry->fiscal_year = substr($entry->period, 0, 4);
            }
        });
    }
}
