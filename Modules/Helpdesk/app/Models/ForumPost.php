<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Helpdesk\Database\Factories\ForumPostFactory;

/**
 * @property int $id
 * @property string $title
 * @property string $content
 * @property int $author_id
 * @property string $category
 * @property int $votes
 * @property int $views
 * @property int|null $accepted_answer_id
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $author
 * @property-read Collection<int, ForumReply> $replies
 */
class ForumPost extends Model
{
    use HasFactory;

    protected $table = 'helpdesk_forum_posts';

    protected $fillable = [
        'title',
        'content',
        'author_id',
        'category',
        'votes',
        'views',
        'accepted_answer_id',
        'status',
    ];

    protected $casts = [
        'votes' => 'integer',
        'views' => 'integer',
        'accepted_answer_id' => 'integer',
    ];

    protected static function newFactory(): ForumPostFactory
    {
        return ForumPostFactory::new();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ForumReply::class, 'post_id');
    }
}
