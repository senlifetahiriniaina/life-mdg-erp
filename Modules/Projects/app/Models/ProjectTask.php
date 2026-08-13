<?php

declare(strict_types=1);

namespace Modules\Projects\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ProjectTask — alias model for prj_tasks with Phase 49 accessors and scopes.
 *
 * The table is shared with Task; ProjectTask adds EVM/Gantt accessors
 * without breaking the existing Task model.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $milestone_id
 * @property int|null $parent_id
 * @property int|null $assignee_id
 * @property string $title
 * @property string $status
 * @property string $priority
 * @property string $type
 * @property int|null $estimated_hours
 * @property int|null $logged_hours
 * @property \Carbon\Carbon|null $start_date
 * @property \Carbon\Carbon|null $due_date
 * @property \Carbon\Carbon|null $completed_at
 * @property array|null $dependencies
 * @property bool|null $is_critical_path
 *
 * Computed:
 * @property-read string $status_color
 * @property-read int    $duration_days
 */
class ProjectTask extends Model
{
    use HasFactory, SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'prj_tasks';

    protected $fillable = [
        'project_id', 'milestone_id', 'parent_id', 'assignee_id', 'created_by',
        'epic_id', 'sprint_id', 'title', 'description', 'status', 'priority',
        'type', 'estimated_hours', 'logged_hours', 'story_points',
        'start_date', 'due_date', 'completed_at', 'tags', 'dependencies',
        'sequence', 'is_critical_path',
    ];

    protected $casts = [
        'start_date'      => 'date',
        'due_date'        => 'date',
        'completed_at'    => 'datetime',
        'dependencies'    => 'array',
        'tags'            => 'array',
        'is_critical_path'=> 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Accessors (Phase 49)
    // -------------------------------------------------------------------------

    /**
     * Tailwind/CSS color class based on task status.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'done'        => 'green',
            'in_progress' => 'blue',
            'review'      => 'yellow',
            'blocked'     => 'red',
            'todo'        => 'gray',
            default       => 'gray',
        };
    }

    /**
     * Duration in calendar days from start_date to due_date.
     */
    public function getDurationDaysAttribute(): int
    {
        if (! $this->start_date || ! $this->due_date) {
            return 0;
        }
        return max(0, (int) $this->start_date->diffInDays($this->due_date));
    }

    // -------------------------------------------------------------------------
    // Scopes (Phase 49)
    // -------------------------------------------------------------------------

    public function scopeCriticalPath(Builder $query): Builder
    {
        return $query->where('is_critical_path', true);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class, 'milestone_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
