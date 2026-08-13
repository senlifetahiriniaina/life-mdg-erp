<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int                       $id
 * @property int                       $company_id
 * @property int                       $created_by
 * @property string                    $title
 * @property string|null               $description
 * @property string|null               $summary
 * @property string                    $status
 * @property int                       $slide_count
 * @property bool                      $is_public
 * @property \Carbon\Carbon            $created_at
 * @property \Carbon\Carbon            $updated_at
 * @property \Carbon\Carbon|null       $deleted_at
 * @property-read \App\Models\User     $creator
 * @property-read \App\Models\Company  $company
 */
class DataStory extends Model
{
    use HasFactory, SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_data_stories';

    protected $fillable = [
        'company_id',
        'created_by',
        'title',
        'description',
        'summary',
        'status',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function slides(): HasMany
    {
        return $this->hasMany(StorySlide::class, 'story_id')->orderBy('slide_number');
    }

    public function narrativeFlows(): HasMany
    {
        return $this->hasMany(NarrativeFlow::class, 'story_id');
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(StoryAnalytics::class, 'story_id');
    }

    public function views(): HasMany
    {
        return $this->hasMany(StoryView::class, 'story_id');
    }

    public function publish(): void
    {
        $this->update(['status' => 'published']);
    }

    public function archive(): void
    {
        $this->update(['status' => 'archived']);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function getSlideCount(): int
    {
        return $this->slide_count;
    }

    public function updateSlideCount(): void
    {
        $count = $this->slides()->count();
        $this->update(['slide_count' => $count]);
    }
}
