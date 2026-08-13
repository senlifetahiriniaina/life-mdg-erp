<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                  $id
 * @property int                  $story_id
 * @property int|null             $viewer_id
 * @property int                  $slide_count_viewed
 * @property string               $time_spent_seconds
 * @property string|null          $source
 * @property array<string, mixed>|null $interaction_log
 * @property \Carbon\Carbon       $viewed_at
 * @property \Carbon\Carbon       $created_at
 * @property \Carbon\Carbon       $updated_at
 * @property-read DataStory       $story
 * @property-read \App\Models\User|null $viewer
 */
class StoryView extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_story_views';

    protected $fillable = [
        'story_id',
        'viewer_id',
        'slide_count_viewed',
        'time_spent_seconds',
        'source',
        'interaction_log',
        'viewed_at',
    ];

    protected $casts = [
        'interaction_log'   => 'array',
        'time_spent_seconds' => 'decimal:2',
        'viewed_at'         => 'datetime',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(DataStory::class);
    }

    public function viewer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'viewer_id');
    }

    public function getTimeSpentSeconds(): float
    {
        return (float) $this->time_spent_seconds;
    }

    public function logInteraction(string $action, int $slideId): void
    {
        $log = $this->interaction_log ?? [];
        $log[] = [
            'action'    => $action,
            'slide_id'  => $slideId,
            'timestamp' => now()->toIso8601String(),
        ];
        $this->update(['interaction_log' => $log]);
    }
}
