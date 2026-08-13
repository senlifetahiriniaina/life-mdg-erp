<?php

declare(strict_types=1);

namespace Modules\Projects\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Projects\Database\Factories\ResourceAllocationFactory;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $task_id
 * @property int $user_id
 * @property string $allocation_type
 * @property int $allocation_percent
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $hours_per_day
 * @property string|null $actual_hours_logged
 * @property string $status
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ResourceAllocation extends Model
{
    use HasFactory;

    protected static function newFactory(): ResourceAllocationFactory
    {
        return ResourceAllocationFactory::new();
    }

    protected $table = 'prj_resource_allocations';

    protected $fillable = [
        'project_id',
        'task_id',
        'user_id',
        'allocation_type',
        'allocation_percent',
        'start_date',
        'end_date',
        'hours_per_day',
        'actual_hours_logged',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'hours_per_day' => 'decimal:2',
        'actual_hours_logged' => 'decimal:2',
        'allocation_percent' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Total planned hours factoring allocation percentage.
     */
    public function totalPlannedHours(): float
    {
        $days = $this->start_date->diffInDays($this->end_date);

        return (float) $this->hours_per_day * $days * ($this->allocation_percent / 100);
    }

    /**
     * Actual utilisation as a percentage of planned hours.
     */
    public function utilizationPercent(): float
    {
        $planned = $this->totalPlannedHours();
        if ($planned <= 0) {
            return 0.0;
        }

        return ((float) $this->actual_hours_logged / $planned) * 100;
    }

    /**
     * Whether this allocation overlaps with the given date range.
     */
    public function isOverlapping(Carbon $from, Carbon $to): bool
    {
        return $this->start_date->lte($to) && $this->end_date->gte($from);
    }

    /**
     * Whether this allocation is currently active.
     */
    public function isActive(): bool
    {
        $today = Carbon::today();

        return in_array($this->status, ['planned', 'confirmed'], true)
            && $this->start_date->lte($today)
            && $this->end_date->gte($today);
    }
}
