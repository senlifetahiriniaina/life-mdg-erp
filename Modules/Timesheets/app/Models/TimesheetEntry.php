<?php

namespace Modules\Timesheets\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HR\Models\Employee;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\Task;
use Modules\Timesheets\Database\Factories\TimesheetEntryFactory;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $employee_id
 * @property Carbon $entry_date
 * @property float $hours_worked
 * @property string $status
 * @property int|null $project_id
 * @property int|null $task_id
 * @property string|null $description
 * @property string|null $notes
 * @property int|null $submitted_by
 * @property Carbon|null $submitted_at
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, TimeAllocation> $allocations
 */
class TimesheetEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'timesheet_entries';

    protected $fillable = [
        'tenant_id', 'employee_id', 'entry_date', 'hours_worked', 'status',
        'project_id', 'task_id', 'description', 'notes', 'submitted_by',
        'submitted_at', 'approved_by', 'approved_at', 'approval_notes', 'rejection_reason',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected static function newFactory(): TimesheetEntryFactory
    {
        return TimesheetEntryFactory::new();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(TimeAllocation::class, 'timesheet_entry_id');
    }

    public function scopeByEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeByDate($query, string $date)
    {
        return $query->whereDate('entry_date', $date);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('entry_date', [$startDate, $endDate]);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isSubmitted(): bool
    {
        return in_array($this->status, ['submitted', 'approved']);
    }

    public function canEdit(): bool
    {
        return $this->status === 'draft';
    }
}
