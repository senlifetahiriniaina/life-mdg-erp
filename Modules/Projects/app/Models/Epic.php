<?php

declare(strict_types=1);

namespace Modules\Projects\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Projects\Database\Factories\EpicFactory;

/**
 * @property int $id
 * @property int $project_id
 * @property string $title
 * @property string|null $description
 * @property string $color
 * @property string $status
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Epic extends Model
{
    use HasFactory;

    protected $table = 'prj_epics';

    protected static function newFactory(): EpicFactory
    {
        return EpicFactory::new();
    }

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'color',
        'status',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'epic_id');
    }
}
