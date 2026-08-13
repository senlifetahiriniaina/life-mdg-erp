<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int                  $id
 * @property int                  $story_id
 * @property int                  $slide_number
 * @property string               $title
 * @property string               $narrative_text
 * @property array<string, mixed>|null $visualization_config
 * @property array<string, mixed>|null $interaction_rules
 * @property string               $transition_type
 * @property int                  $transition_duration
 * @property array<string, mixed>|null $layout
 * @property \Carbon\Carbon       $created_at
 * @property \Carbon\Carbon       $updated_at
 * @property \Carbon\Carbon|null  $deleted_at
 * @property-read DataStory       $story
 */
class StorySlide extends Model
{
    use HasFactory, SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_story_slides';

    protected $fillable = [
        'story_id',
        'slide_number',
        'title',
        'narrative_text',
        'visualization_config',
        'interaction_rules',
        'transition_type',
        'transition_duration',
        'layout',
    ];

    protected $casts = [
        'visualization_config' => 'array',
        'interaction_rules'    => 'array',
        'layout'               => 'array',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(DataStory::class);
    }

    public function hasVisualization(): bool
    {
        return $this->visualization_config !== null && ! empty($this->visualization_config);
    }

    public function getTransitionDurationMs(): int
    {
        return $this->transition_duration;
    }
}
