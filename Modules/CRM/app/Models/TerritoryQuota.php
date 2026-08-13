<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerritoryQuota extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'crm_territory_quotas';

    protected $fillable = [
        'territory_id', 'period', 'quota_revenue', 'quota_deals',
        'actual_revenue', 'actual_deals', 'attainment_pct', 'forecast_revenue',
        'days_remaining', 'metadata',
    ];

    protected $casts = [
        'quota_revenue'     => 'decimal:2',
        'quota_deals'       => 'decimal:2',
        'actual_revenue'    => 'decimal:2',
        'forecast_revenue'  => 'decimal:2',
        'attainment_pct'    => 'decimal:2',
        'metadata'          => 'array',
    ];

    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class, 'territory_id');
    }

    public function calculateAttainment(): float
    {
        if ($this->quota_revenue == 0) {
            return 0;
        }

        return ($this->actual_revenue / $this->quota_revenue) * 100;
    }

    public function getRemainingRevenueAttribute(): float
    {
        return max(0, $this->quota_revenue - $this->actual_revenue);
    }
}
