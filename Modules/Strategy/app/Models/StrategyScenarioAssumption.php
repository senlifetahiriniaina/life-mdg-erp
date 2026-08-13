<?php

namespace Modules\Strategy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrategyScenarioAssumption extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $fillable = [
        'scenario_id',
        'variable_name',
        'description',
        'base_value',
        'adjusted_value',
        'impact_scope',
    ];

    protected $casts = [
        'base_value'     => 'float',
        'adjusted_value' => 'float',
    ];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(StrategyScenario::class, 'scenario_id');
    }
}
