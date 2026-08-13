<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Helpdesk\Database\Factories\ForumFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $category
 * @property bool $is_public
 * @property int $sort_order
 */
class Forum extends Model
{
    use HasFactory;

    protected $table = 'helpdesk_forums';

    protected static function newFactory(): ForumFactory
    {
        return ForumFactory::new();
    }

    protected $guarded = [];

    protected $casts = [
        'is_public' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Forum $forum) {
            if (empty($forum->slug)) {
                $forum->slug = Str::slug($forum->name);
            }
        });
    }

    public function threads(): HasMany
    {
        return $this->hasMany(ForumThread::class, 'forum_id');
    }
}
