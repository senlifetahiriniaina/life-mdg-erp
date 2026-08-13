<?php

namespace Modules\Timesheets\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HR\Models\Employee;
use Modules\Projects\Models\Project;

class TimeEntry extends Model
{
    use HasFactory;

    protected $table = 'timesheets_entries';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'project_id',
        'task_description',
        'work_date',
        'hours',
        'billable',
        'rate',
        'status',
        'notes',
        'approved_by',
        'approved_at',
        'rejected_reason',
    ];

    protected $casts = [
        'work_date' => 'date',
        'hours' => 'decimal:2',
        'rate' => 'decimal:2',
        'billable' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getAmountAttribute(): float
    {
        return $this->hours * $this->rate;
    }
}
