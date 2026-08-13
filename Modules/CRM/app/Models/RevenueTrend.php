<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RevenueTrend extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'crm_revenue_trends';

    protected $fillable = [
        'metric_name', 'dimension', 'dimension_value', 'period_start', 'period_end',
        'current_value', 'previous_value', 'change_pct', 'trend_direction', 'data_points_count',
    ];

    protected $casts = [
        'current_value'  => 'decimal:2',
        'previous_value' => 'decimal:2',
        'change_pct'     => 'decimal:2',
        'period_start'   => 'date',
        'period_end'     => 'date',
    ];

    public function getIsPositiveAttribute(): bool
    {
        return $this->trend_direction === 'up';
    }

    public function getImprovementPercentageAttribute(): float
    {
        return abs($this->change_pct ?? 0);
    }
}
