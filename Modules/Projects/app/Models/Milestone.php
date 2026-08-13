<?php

namespace Modules\Projects\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property Carbon|null $due_date
 * @property bool $is_reached
 * @property Carbon|null $reached_at
 */
class Milestone extends Model
{
    use HasFactory;

    protected $table = 'prj_milestones';

    protected $fillable = [
        'project_id', 'name', 'due_date', 'is_reached', 'reached_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'is_reached' => 'boolean',
        'reached_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'milestone_id');
    }
}
