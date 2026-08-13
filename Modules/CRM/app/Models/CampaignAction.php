<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignAction extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'crm_campaign_actions';

    protected $fillable = [
        'campaign_id', 'enrollment_id', 'action_type', 'status',
        'payload', 'scheduled_at', 'executed_at', 'error_message',
    ];

    protected $casts = [
        'payload'      => 'array',
        'scheduled_at' => 'datetime',
        'executed_at'  => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CampaignEnrollment::class, 'enrollment_id');
    }
}
