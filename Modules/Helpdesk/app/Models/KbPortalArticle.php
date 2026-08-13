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
    ];

    protected $casts = [
        'view_count' => 'integer',
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

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
