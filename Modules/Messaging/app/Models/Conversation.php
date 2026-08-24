<?php

declare(strict_types=1);

namespace Modules\Messaging\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $table = 'msg_conversations';

    protected $fillable = ['company_id', 'type', 'name', 'created_by'];

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'msg_conversation_participants')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    /**
     * Chantier 32.28: was `hasMany(...)->latest()`, eager-loaded via
     * `->with(['lastMessage'])` and reduced to a single row with `->first()`
     * in ConversationController::index() — this loaded EVERY message of
     * EVERY one of the caller's conversations into memory just to discard
     * all but the newest per thread, confirmed empirically (no LIMIT clause
     * at all in the generated SQL). `latestOfMany()` produces a real
     * single-row-per-parent query instead.
     */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
