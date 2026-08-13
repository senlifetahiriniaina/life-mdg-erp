<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Helpdesk\Database\Factories\ArticleViewFactory;

/**
 * @property int $id
 * @property int $article_id
 * @property int|null $user_id
 * @property string|null $ip_address
 * @property Carbon $viewed_at
 * @property bool|null $helpful
 */
class ArticleView extends Model
{
    use HasFactory;

    protected $table = 'hd_kb_article_views';

    protected $fillable = [
        'article_id',
        'user_id',
        'ip_address',
        'viewed_at',
        'helpful',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
        'helpful' => 'boolean',
    ];

    protected static function newFactory(): ArticleViewFactory
    {
        return ArticleViewFactory::new();
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(KbArticle::class, 'article_id');
    }

    public function isHelpful(): bool
    {
        return $this->helpful === true;
    }

    public function hasRated(): bool
    {
        return $this->helpful !== null;
    }
}
