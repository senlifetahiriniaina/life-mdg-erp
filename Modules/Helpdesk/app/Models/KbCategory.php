<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Helpdesk\Database\Factories\KbCategoryFactory;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property int|null $created_by
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $icon
 * @property string|null $color
 * @property int $sort_order
 * @property bool $is_active
 * @property int $article_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class KbCategory extends Model
{
    use HasFactory;

    protected $table = 'hd_kb_categories';

    protected $fillable = [
        'parent_id',
        'created_by',
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'sort_order',
        'is_active',
        'article_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'article_count' => 'integer',
    ];

    protected static function newFactory(): KbCategoryFactory
    {
        return KbCategoryFactory::new();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(KbCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(KbCategory::class, 'parent_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(KbArticle::class, 'category_id');
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function incrementArticleCount(): void
    {
        $this->increment('article_count');
    }

    public function decrementArticleCount(): void
    {
        if ($this->article_count > 0) {
            $this->decrement('article_count');
        }
    }

    public function publishedArticleCount(): int
    {
        return $this->articles()->where('status', 'published')->count();
    }
}
