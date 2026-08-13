<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignStage extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'crm_campaign_stages';

    protected $fillable = [
        'campaign_id', 'name', 'sequence', 'delay_days',
        'condition_type', 'conditions', 'actions',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions'    => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }
}
