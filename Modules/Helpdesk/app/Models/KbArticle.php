<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Helpdesk\Database\Factories\KbArticleFactory;

/**
 * @property int $id
 * @property int|null $category_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $author_id
 * @property string $title
 * @property string $slug
 * @property string $content
 * @property string|null $excerpt
 * @property array<string,mixed>|null $tags
 * @property string $status
 * @property int $view_count
 * @property int $helpful_count
 * @property int $not_helpful_count
 * @property int|null $reading_time_minutes
 * @property Carbon|null $published_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read KbCategory|null $category
 */
class KbArticle extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'hd_kb_articles';

    protected $fillable = [
        'category_id',
        'created_by',
        'updated_by',
        'author_id',
        'title',
        'slug',
        'content',
        'excerpt',
        'tags',
        'status',
        'view_count',
        'helpful_count',
        'not_helpful_count',
        'published_at',
        'reading_time_minutes',
    ];

    protected $casts = [
        'tags' => 'array',
        'published_at' => 'datetime',
        'view_count' => 'integer',
        'helpful_count' => 'integer',
        'not_helpful_count' => 'integer',
        'reading_time_minutes' => 'integer',
    ];

    protected static function newFactory(): KbArticleFactory
    {
        return KbArticleFactory::new();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(KbCategory::class, 'category_id');
    }

    public function views(): HasMany
    {
        return $this->hasMany(ArticleView::class, 'article_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function publish(): void
    {
        $wasPublished = $this->isPublished();
        $this->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
        if (! $wasPublished && $this->category !== null) {
            $this->category->incrementArticleCount();
        }
    }

    public function archive(): void
    {
        $wasPublished = $this->isPublished();
        $this->update(['status' => 'archived']);
        if ($wasPublished && $this->category !== null) {
            $this->category->decrementArticleCount();
        }
    }

    public function incrementView(): void
    {
        $this->increment('view_count');
    }

    public function markHelpful(): void
    {
        $this->increment('helpful_count');
    }

    public function markNotHelpful(): void
    {
        $this->increment('not_helpful_count');
    }

    public function helpfulnessRate(): float
    {
        $total = $this->helpful_count + $this->not_helpful_count;

        return $total > 0 ? (float) ($this->helpful_count / $total * 100) : 0.0;
    }

    public function computeReadingTime(): int
    {
        $words = str_word_count($this->content);
        $minutes = (int) ($words / 200);

        return max(1, $minutes);
    }
}
