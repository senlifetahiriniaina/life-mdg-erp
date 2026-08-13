<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Rules for distributing shared costs across targets.
 *
 * Supports six allocation methods:
 *   direct          — cost is already directly attributable
 *   per_unit        — distribute proportionally to units produced/sold
 *   by_weight       — distribute by physical weight of components
 *   by_revenue      — distribute by revenue share of each product/client
 *   by_labor_hours  — distribute by timesheet hours logged
 *   custom          — formula-based (JSON expression stored in `formula`)
 */
class CostAllocationKey extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'cost_allocation_keys';

    protected $fillable = [
        'tenant_id',
        'name',
        'allocation_method',
        'source_pool',
        'target_type',
        'formula',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
