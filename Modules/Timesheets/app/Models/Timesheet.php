<?php

namespace Modules\Timesheets\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\HR\Models\Employee;

class Timesheet extends Model
{
    use HasFactory;

    protected $table = 'timesheets_sheets';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'period_start',
        'period_end',
        'status',
        'total_hours',
        'billable_hours',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejected_reason',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_hours' => 'decimal:2',
        'billable_hours' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TimeEntry::class, 'employee_id', 'employee_id')
            ->whereBetween('work_date', [$this->period_start, $this->period_end]);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
