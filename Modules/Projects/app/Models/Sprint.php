<?php

declare(strict_types=1);

namespace Modules\Projects\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Projects\Database\Factories\SprintFactory;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string|null $goal
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property string $status
 * @property int|null $capacity_points
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Sprint extends Model
{
    use HasFactory;

    protected $table = 'prj_sprints';

    protected static function newFactory(): SprintFactory
    {
        return SprintFactory::new();
    }

    protected $fillable = [
        'project_id',
        'name',
        'goal',
        'start_date',
        'end_date',
        'status',
        'capacity_points',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'capacity_points' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'sprint_id');
    }
}
