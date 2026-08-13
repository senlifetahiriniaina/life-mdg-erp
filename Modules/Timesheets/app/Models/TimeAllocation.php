<?php

namespace Modules\Timesheets\Models;

// use App\Models\CostCenter; // Model not implemented yet
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\Task;
use Modules\Timesheets\Database\Factories\TimeAllocationFactory;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $timesheet_entry_id
 * @property int|null $project_id
 * @property int|null $cost_center_id
 * @property int|null $task_id
 * @property float $hours_allocated
 * @property string $allocation_type
 * @property float|null $hourly_rate
 * @property float|null $cost_amount
 * @property string|null $description
 * @property string $billable
 */
class TimeAllocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'time_allocations';

    protected $fillable = [
        'tenant_id', 'timesheet_entry_id', 'entry_id', 'project_id', 'cost_center_id',
        'task_id', 'hours_allocated', 'hours', 'allocation_type', 'hourly_rate',
        'cost_amount', 'description', 'billable', 'is_billable',
    ];

    protected static function newFactory(): TimeAllocationFactory
    {
        return TimeAllocationFactory::new();
    }

    public function setAttribute($key, $value)
    {
        if ($key === 'entry_id') {
            parent::setAttribute('entry_id', $value);
            $key = 'timesheet_entry_id';
        } elseif ($key === 'hours') {
            $key = 'hours_allocated';
        } elseif ($key === 'is_billable') {
            $value = $value === true || $value === 1 || $value === '1' ? 'yes' : 'no';
            $key = 'billable';
        }

        return parent::setAttribute($key, $value);
    }

    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if ($key === 'hours') {
            return parent::getAttribute('hours_allocated');
        }

        return $value;
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(TimesheetEntry::class, 'timesheet_entry_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'cost_center_id'); // Stub: CostCenter not implemented
    }

    public function scopeBillable($query)
    {
        return $query->where('billable', 'yes');
    }

    public function scopeByProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function isBillable(): bool
    {
        return $this->billable === 'yes';
    }

    public function calculateCost(): float
    {
        if (! $this->hourly_rate) {
            return 0;
        }

        return $this->hours_allocated * $this->hourly_rate;
    }
}
