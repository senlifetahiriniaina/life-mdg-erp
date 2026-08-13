<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignAnalytic extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'crm_campaign_analytics';

    protected $fillable = [
        'campaign_id', 'date', 'impressions', 'opens', 'clicks',
        'conversions', 'open_rate', 'click_rate', 'conversion_rate', 'revenue_generated',
    ];

    protected $casts = [
        'date'              => 'date',
        'open_rate'         => 'decimal:2',
        'click_rate'        => 'decimal:2',
        'conversion_rate'   => 'decimal:2',
        'revenue_generated' => 'decimal:2',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }
}
