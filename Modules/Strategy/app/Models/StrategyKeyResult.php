<?php

namespace Modules\Strategy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrategyKeyResult extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $fillable = [
        'objective_id',
        'title',
        'description',
        'type',
        'baseline_value',
        'target_value',
        'current_value',
        'unit',
        'data_source_module',
        'data_source_key',
        'progress',
        'confidence',
    ];

    protected $casts = [
        'baseline_value' => 'float',
        'target_value'   => 'float',
        'current_value'  => 'float',
        'progress'       => 'float',
    ];

    public function objective(): BelongsTo
    {
        return $this->belongsTo(StrategyObjective::class, 'objective_id');
    }
}
