<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CampaignEnrollment extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'crm_campaign_enrollments';

    protected $fillable = [
        'campaign_id', 'enrollable_type', 'enrollable_id',
        'current_stage', 'status', 'enrolled_at', 'completed_at',
        'email_opens', 'email_clicks', 'sms_reads', 'interactions', 'metadata',
    ];

    protected $casts = [
        'metadata'    => 'array',
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function enrollable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actions(): HasMany
    {
        return $this->hasMany(CampaignAction::class, 'enrollment_id');
    }
}
