<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RevenueAnomaly extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'crm_revenue_anomalies';

    protected $fillable = [
        'company_id', 'anomaly_type', 'metric_name', 'dimension', 'dimension_value',
        'detected_value', 'expected_value', 'deviation_pct', 'severity',
        'status', 'explanation', 'detected_at',
    ];

    protected $casts = [
        'detected_value'  => 'decimal:2',
        'expected_value'  => 'decimal:2',
        'detected_at'     => 'datetime',
    ];

    public function getIsSignificantAttribute(): bool
    {
        return abs($this->deviation_pct) > 25;
    }

    public function getIsSevereAttribute(): bool
    {
        return $this->severity === 'high';
    }
}
