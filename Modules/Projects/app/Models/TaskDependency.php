<?php

declare(strict_types=1);

namespace Modules\Projects\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Projects\Database\Factories\TaskDependencyFactory;
use Modules\Projects\Services\DependencyCycleDetectionService;

/**
 * @property int $id
 * @property int $task_id
 * @property int $depends_on_task_id
 * @property string $type
 * @property int $lag_days
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TaskDependency extends Model
{
    use HasFactory;

    protected static function newFactory(): TaskDependencyFactory
    {
        return TaskDependencyFactory::new();
    }

    protected $table = 'project_task_dependencies';

    protected $fillable = [
        'task_id',
        'depends_on_task_id',
        'type',
        'lag_days',
    ];

    protected $casts = [
        'lag_days' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (TaskDependency $dependency) {
            $task = Task::find($dependency->task_id);
            $dependsOnTask = Task::find($dependency->depends_on_task_id);

            if ($task && $dependsOnTask) {
                $result = app(DependencyCycleDetectionService::class)->checkCycleOnAdd($task, $dependsOnTask);
                if ($result['cycle']) {
                    throw new \RuntimeException($result['message']);
                }
            }
        });
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function dependsOnTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'depends_on_task_id');
    }
}
