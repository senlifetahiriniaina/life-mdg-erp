<?php

namespace Modules\Strategy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrategyRitualSession extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $fillable = [
        'ritual_id',
        'scheduled_at',
        'started_at',
        'completed_at',
        'facilitator_id',
        'agenda',
        'decisions',
        'action_items',
        'ai_summary',
    ];

    protected $casts = [
        'agenda'       => 'array',
        'decisions'    => 'array',
        'action_items' => 'array',
        'scheduled_at' => 'datetime',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function ritual(): BelongsTo
    {
        return $this->belongsTo(StrategyRitual::class, 'ritual_id');
    }

    public function facilitator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'facilitator_id');
    }
}
