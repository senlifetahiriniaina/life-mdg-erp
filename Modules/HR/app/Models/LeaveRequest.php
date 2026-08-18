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
        'leave_type',
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

    protected static function booted(): void
    {
        // leave_type_id is a required FK to hr_leave_types, but a caller may
        // only know the plain string code ('vacation', 'sick', ...) stored in
        // the separate leave_type column — resolve/create the matching
        // LeaveType here so the FK is never left null, the same "derive the
        // id from what callers actually pass" pattern already used by
        // Employee::booted(). Chantier 8.3: this hook's original sole caller,
        // AbsenceManagementService (a redundant, broken-schema parallel leave
        // subsystem — see CLAUDE.md), was deleted; kept as harmless defensive
        // coverage for any future leave_type-string-only caller.
        static::creating(function (self $request) {
            if (empty($request->leave_type_id) && ! empty($request->leave_type)) {
                $request->leave_type_id = LeaveType::firstOrCreate(
                    ['code' => $request->leave_type],
                    [
                        'name' => ucfirst(str_replace('_', ' ', $request->leave_type)),
                        'is_paid' => $request->leave_type !== 'unpaid',
                    ]
                )->id;
            }
        });
    }

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

    /**
     * Approved leave requests whose date range covers the given date, for a
     * given employee — the "is this employee on leave right now" check used
     * by Modules\Validation\Services\ApprovalRoutingResolver. Did not exist
     * before this — nothing in the codebase queried leave by date range.
     */
    public function scopeApprovedAndCoveringDate($query, int $employeeId, \Illuminate\Support\Carbon $date)
    {
        return $query->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date);
    }
}
