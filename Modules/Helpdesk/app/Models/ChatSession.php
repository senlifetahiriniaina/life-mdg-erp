<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $visitor_id
 * @property string|null $visitor_name
 * @property string|null $visitor_email
 * @property string $status
 * @property string $channel
 * @property int|null $assigned_agent_id
 * @property int|null $ticket_id
 * @property array<string,mixed>|null $metadata
 * @property Carbon|null $started_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ChatSession extends Model
{
    use HasFactory;
    protected $table = 'hd_chat_sessions';

    protected $fillable = [
        'visitor_id',
        'visitor_name',
        'visitor_email',
        'status',
        'channel',
        'assigned_agent_id',
        'ticket_id',
        'metadata',
        'started_at',
        'closed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'chat_session_id');
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
