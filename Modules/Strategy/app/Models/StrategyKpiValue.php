<?php

namespace Modules\Strategy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrategyKpiValue extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $fillable = [
        'kpi_id',
        'value',
        'recorded_at',
        'period',
    ];

    protected $casts = [
        'value'       => 'float',
        'recorded_at' => 'datetime',
    ];

    public function kpi(): BelongsTo
    {
        return $this->belongsTo(StrategyKpi::class, 'kpi_id');
    }
}
