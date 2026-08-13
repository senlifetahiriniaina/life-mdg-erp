<?php

declare(strict_types=1);

namespace Modules\Projects\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $task_id
 * @property int $user_id
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property int|null $duration_minutes
 * @property string|null $description
 * @property bool $billable
 * @property float|null $hourly_rate
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Task|null $task
 * @property-read Project $project
 */
class ProjectTimeLog extends Model
{
    use HasFactory;
    protected $table = 'prj_time_logs';

    protected $fillable = [
        'project_id',
        'task_id',
        'user_id',
        'started_at',
        'ended_at',
        'duration_minutes',
        'description',
        'billable',
        'hourly_rate',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_minutes' => 'integer',
        'billable' => 'boolean',
        'hourly_rate' => 'float',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
