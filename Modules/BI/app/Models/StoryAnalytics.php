<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int             $id
 * @property int             $story_id
 * @property int             $total_views
 * @property int             $unique_viewers
 * @property int             $total_slide_views
 * @property string          $avg_time_per_slide
 * @property string          $completion_rate
 * @property int             $shares_count
 * @property int             $interactions_count
 * @property \Carbon\Carbon|null $last_viewed_at
 * @property \Carbon\Carbon  $created_at
 * @property \Carbon\Carbon  $updated_at
 * @property-read DataStory  $story
 */
class StoryAnalytics extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_story_analytics';

    protected $fillable = [
        'story_id',
        'total_views',
        'unique_viewers',
        'total_slide_views',
        'avg_time_per_slide',
        'completion_rate',
        'shares_count',
        'interactions_count',
        'last_viewed_at',
    ];

    protected $casts = [
        'avg_time_per_slide' => 'decimal:2',
        'completion_rate'    => 'decimal:2',
        'last_viewed_at'     => 'datetime',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(DataStory::class);
    }

    public function incrementViews(int $count = 1): void
    {
        $this->increment('total_views', $count);
        $this->update(['last_viewed_at' => now()]);
    }

    public function incrementUniqueViewers(int $count = 1): void
    {
        $this->increment('unique_viewers', $count);
    }

    public function incrementShares(int $count = 1): void
    {
        $this->increment('shares_count', $count);
    }

    public function getCompletionRatePercentage(): float
    {
        return (float) $this->completion_rate;
    }
}
