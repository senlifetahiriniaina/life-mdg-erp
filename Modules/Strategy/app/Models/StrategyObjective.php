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
