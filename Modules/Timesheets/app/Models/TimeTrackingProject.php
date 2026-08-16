<?php

namespace Modules\Timesheets\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HR\Models\Department;
use Modules\Timesheets\Database\Factories\TimeTrackingProjectFactory;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property float|null $budget_hours
 * @property float $hours_tracked
 * @property int|null $department_id
 * @property string $status
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TimeTrackingProject extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'time_tracking_projects';

    protected $fillable = [
        'tenant_id', 'name', 'code', 'description', 'budget_hours',
        'hours_tracked', 'department_id', 'status', 'start_date', 'end_date',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'assigned_employees' => 'array',
        'budget_hours' => 'float',
        'hours_tracked' => 'float',
    ];

    protected $appends = ['remaining_hours', 'is_over_budget'];

    protected static function newFactory(): TimeTrackingProjectFactory
    {
        return TimeTrackingProjectFactory::new();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getRemainingHoursAttribute(): ?float
    {
        if (! $this->budget_hours) {
            return null;
        }

        return max(0, $this->budget_hours - $this->hours_tracked);
    }

    public function getIsOverBudgetAttribute(): bool
    {
        return $this->budget_hours && $this->hours_tracked > $this->budget_hours;
    }

    public function isOverBudget(): bool
    {
        return $this->budget_hours && $this->hours_tracked > $this->budget_hours;
    }
}
