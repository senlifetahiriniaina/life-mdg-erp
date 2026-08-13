<?php

namespace Modules\Projects\Models;

use App\Models\User;
use App\Traits\AuditableActions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Projects\Database\Factories\TaskFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $milestone_id
 * @property int|null $epic_id
 * @property int|null $sprint_id
 * @property int|null $parent_id
 * @property int|null $assignee_id
 * @property int|null $created_by
 * @property string $title
 * @property string|null $description
 * @property string $status
 * @property string $priority
 * @property string $type
 * @property int|null $estimated_hours
 * @property int|null $logged_hours
 * @property int|null $story_points
 * @property Carbon|null $start_date
 * @property Carbon|null $due_date
 * @property Carbon|null $completed_at
 * @property array<string,mixed>|null $tags
 * @property array<int,mixed>|null $dependencies
 * @property int|null $sequence
 */
class Task extends Model
{
    use AuditableActions, HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function newFactory(): TaskFactory
    {
        return TaskFactory::new();
    }

    protected $table = 'prj_tasks';

    protected string $auditModule = 'Projects';

    protected array $auditableFields = ['status', 'priority', 'assignee_id', 'completed_at'];

    protected static string $logName = 'projects';

    protected static array $logAttributes = ['title', 'status', 'priority', 'assigned_to', 'due_date', 'estimated_hours'];

    protected static bool $logOnlyDirty = true;

    protected static bool $submitEmptyLogs = false;

    protected $fillable = [
        'project_id', 'milestone_id', 'epic_id', 'sprint_id', 'parent_id', 'assignee_id', 'created_by',
        'title', 'description', 'status', 'priority', 'type',
        'estimated_hours', 'logged_hours', 'story_points', 'start_date', 'due_date', 'completed_at',
        'tags', 'dependencies', 'sequence',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'due_date' => 'date:Y-m-d',
        'completed_at' => 'datetime',
        'estimated_hours' => 'integer',
        'logged_hours' => 'integer',
        'story_points' => 'integer',
        'tags' => 'array',
        'dependencies' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    public function epic(): BelongsTo
    {
        return $this->belongsTo(Epic::class);
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class, 'task_id');
    }

    public function dependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'task_id');
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'depends_on_task_id');
    }
}
