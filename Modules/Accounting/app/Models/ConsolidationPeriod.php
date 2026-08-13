<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsolidationPeriod extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'consolidation_periods';

    protected $fillable = [
        'consolidation_hierarchy_id',
        'period_start',
        'period_end',
        'frequency',
        'status',
        'notes',
        'consolidated_at',
        'consolidated_by',
        'consolidation_rules',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'consolidated_at' => 'datetime',
        'consolidation_rules' => 'json',
    ];

    public function hierarchy(): BelongsTo
    {
        return $this->belongsTo(ConsolidationHierarchy::class, 'consolidation_hierarchy_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ConsolidationEntry::class, 'consolidation_period_id');
    }

    public function eliminations(): HasMany
    {
        return $this->hasMany(ConsolidationElimination::class, 'consolidation_period_id');
    }
}
