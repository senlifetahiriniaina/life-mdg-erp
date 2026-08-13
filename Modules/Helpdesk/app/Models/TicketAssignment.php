<?php

namespace Modules\Helpdesk\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $ticket_id
 * @property int|null $assigned_to_id
 * @property int|null $assigned_by_id
 */
class TicketAssignment extends Model
{
    protected $table = 'helpdesk_ticket_assignments';

    protected $fillable = [
        'ticket_id',
        'assigned_to_id',
        'assigned_by_id',
        'assigned_at',
        'note',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }
}
