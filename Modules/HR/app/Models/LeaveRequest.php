<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HR\Database\Factories\LeaveRequestFactory;

class LeaveRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hr_leave_requests';

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'type',
        'start_date',
        'end_date',
        'days',
        'days_requested',
        'reason',
        'status',
        'approved_by',
        'approval_notes',
        'approved_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'days_requested' => 'decimal:2',
    ];

    protected static function newFactory()
    {
        return LeaveRequestFactory::new();
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function approver()
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
