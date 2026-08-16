<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Helpdesk\Database\Factories\ForumReplyFactory;

/**
 * @property int $id
 * @property int $thread_id
 * @property int $author_id
 * @property string $content
 * @property bool $is_accepted_answer
 * @property int $upvotes
 */
class ForumReply extends Model
{
    use HasFactory;

    protected $table = 'helpdesk_forum_replies';

    protected $guarded = [];

    protected $casts = [
        'upvotes'            => 'integer',
        'is_accepted_answer' => 'boolean',
        'is_accepted'        => 'boolean',
    ];

    protected static function newFactory(): ForumReplyFactory
    {
        return ForumReplyFactory::new();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'thread_id');
    }

    public function votes(): MorphMany
    {
        return $this->morphMany(ForumVote::class, 'votable');
    }
}
