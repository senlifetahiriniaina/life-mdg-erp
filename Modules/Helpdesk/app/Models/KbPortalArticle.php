<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $category_id
 * @property int|null $author_id
 * @property string $title
 * @property string|null $slug
 * @property string $content
 * @property string $status
 * @property int $view_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class KbPortalArticle extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'hd_kb_portal_articles';

    protected $fillable = [
        'category_id',
        'author_id',
        'title',
        'slug',
        'content',
        'status',
        'view_count',
        'helpful_count',
        'not_helpful_count',
    ];

    protected $casts = [
        'view_count' => 'integer',
        'helpful_count' => 'integer',
        'not_helpful_count' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->title).'-'.Str::random(4);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(KbPortalCategory::class, 'category_id');
    }

    /**
     * Portal/Index.vue's openArticle() calls
     * GET .../kb/portal/articles/${article.slug || article.id} — since
     * every real row always has a slug (see the creating hook above), the
     * frontend always sends the slug, not the numeric id. Without this
     * override, Laravel's default implicit route-model binding looks the
     * value up by the primary key ('id') only, so every real click on a KB
     * portal article 404'd. giveFeedback() sends the numeric id on the
     * follow-up /helpful call, so both id and slug must resolve.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if ($field !== null) {
            return $this->where($field, $value)->first();
        }

        return $this->where('id', $value)->orWhere('slug', $value)->first();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
