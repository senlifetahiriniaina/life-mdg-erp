<?php

namespace Modules\Strategy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StrategyScenario extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'type',
        'base_plan_id',
        'status',
        'probability',
        'created_by',
    ];

    protected $casts = [
        'probability' => 'float',
    ];

    public function assumptions(): HasMany
    {
        return $this->hasMany(StrategyScenarioAssumption::class, 'scenario_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(StrategyPlan::class, 'base_plan_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
