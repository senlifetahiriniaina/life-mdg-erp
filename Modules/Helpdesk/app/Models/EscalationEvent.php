<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int|null $rule_id
 * @property Carbon $triggered_at
 * @property string|null $action_taken
 * @property string $result
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EscalationEvent extends Model
{
    use HasFactory;
    protected $table = 'hd_escalation_events';

    protected $fillable = [
        'ticket_id',
        'rule_id',
        'triggered_at',
        'action_taken',
        'result',
    ];

    protected $casts = [
        'triggered_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(EscalationRule::class, 'rule_id');
    }
}
