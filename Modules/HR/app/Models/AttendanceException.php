<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceException extends Model
{
    use SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'hr_attendance_exceptions';

    protected $fillable = [
        'employee_id',
        'attendance_date',
        'exception_type',
        'minutes_late',
        'minutes_early',
        'reason',
        'status',
        'approved_by',
        'manager_notes',
        'employee_response',
        'acknowledged_at',
        'approved_at',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'acknowledged_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function acknowledge(): void
    {
        $this->update(['acknowledged_at' => now()]);
    }

    public function approve(): void
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function reject(): void
    {
        $this->update(['status' => 'rejected']);
    }
}
