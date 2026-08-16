<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int $user_id
 * @property string $content
 * @property bool $is_internal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TicketComment extends Model
{
    use HasFactory;
    protected $table = 'hd_ticket_comments';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'body',
        'content', // alias for `body` — mutator below maps it to the real column
        'is_internal',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    protected $appends = ['content'];

    // Real column is `body` (see hd_ticket_comments migration patch); `content`
    // is kept as the stable API/frontend-facing name (TicketCommentController,
    // Tickets/Show/Show.vue both already speak `content`).
    public function getContentAttribute(): ?string
    {
        return $this->attributes['body'] ?? null;
    }

    public function setContentAttribute($value): void
    {
        $this->attributes['body'] = $value;
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
