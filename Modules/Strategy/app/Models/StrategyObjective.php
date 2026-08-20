<?php

namespace Modules\Strategy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StrategyObjective extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $fillable = [
        'plan_id',
        'pillar_id',
        'parent_id',
        'level',
        'owner_type',
        'owner_id',
        'title',
        'description',
        'framework_type',
        'bsc_perspective',
        'weight',
        'start_date',
        'end_date',
        'status',
        'progress',
    ];

    protected $casts = [
        'weight'     => 'float',
        'progress'   => 'float',
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(StrategyPlan::class, 'plan_id');
    }

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(StrategyPillar::class, 'pillar_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(StrategyObjective::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(StrategyObjective::class, 'parent_id');
    }

    public function keyResults(): HasMany
    {
        return $this->hasMany(StrategyKeyResult::class, 'objective_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(StrategyObjectiveLink::class, 'strategy_objective_id');
    }

    /**
     * Chantier 19 (Lot 5): StrategyObjective has no tenant_id column of its
     * own — tenancy is inherited from its parent plan. Confirmed empirically
     * (Chantier19InvestigationTest) that OkrController::index() listed every
     * company's objectives with no filter at all, and update()/destroy()
     * let any user of any company mutate/delete another company's objective
     * by id, since StrategyObjectivePolicy::canManage() only checks role,
     * never ownership. This scope is the fix's foundation.
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->whereHas('plan', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        });
    }

    /**
     * Update objective progress as the average of its key results' progress.
     */
    public function updateProgress(): void
    {
        $keyResults = $this->keyResults()->get();

        if ($keyResults->isEmpty()) {
            return;
        }

        $avgProgress = $keyResults->avg('progress');
        $this->update(['progress' => round($avgProgress, 2)]);
    }
}
