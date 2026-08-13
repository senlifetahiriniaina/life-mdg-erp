<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $chat_session_id
 * @property string $sender_type
 * @property int|null $sender_id
 * @property string $message
 * @property string $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ChatMessage extends Model
{
    use HasFactory;
    protected $table = 'hd_chat_messages';

    protected $fillable = [
        'chat_session_id',
        'sender_type',
        'sender_id',
        'message',
        'type',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
