<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Modules\Helpdesk\Database\Factories\ForumThreadFactory;

/**
 * @property int $id
 * @property int $forum_id
 * @property int $author_id
 * @property string $title
 * @property string $slug
 * @property string $content
 * @property string $status  open|closed|pinned
 * @property int $views
 * @property bool $is_answered
 */
class ForumThread extends Model
{
    use HasFactory;

    protected $table = 'helpdesk_forum_threads';

    protected static function newFactory(): ForumThreadFactory
    {
        return ForumThreadFactory::new();
    }

    protected $guarded = [];

    protected $casts = [
        'views' => 'integer',
        'is_answered' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ForumThread $thread) {
            if (empty($thread->slug)) {
                $thread->slug = Str::slug($thread->title).'-'.Str::random(6);
            }
        });
    }

    public function forum(): BelongsTo
    {
        return $this->belongsTo(Forum::class, 'forum_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ForumReply::class, 'thread_id');
    }

    public function votes(): MorphMany
    {
        return $this->morphMany(ForumVote::class, 'votable');
    }
}
