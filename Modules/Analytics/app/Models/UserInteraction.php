<?php

namespace Modules\Analytics\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserInteraction extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'user_interactions';

    protected $fillable = [
        'company_id',
        'user_type',
        'user_id',
        'interacted_item_type',
        'interacted_item_id',
        'interaction_type',
        'engagement_score',
        'context',
        'interacted_at',
    ];

    protected $casts = [
        'engagement_score' => 'decimal:4',
        'context' => 'array',
        'interacted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    public function interactedItem(): MorphTo
    {
        return $this->morphTo();
    }
}
