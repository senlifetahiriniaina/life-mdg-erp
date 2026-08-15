<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class TimeOffRequest extends Model
{
    use HasFactory;
    use SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'hr_time_off_requests';

    protected $fillable = [
        'employee_id',
        'request_type',
        'start_date',
        'end_date',
        'duration_days',
        'duration_hours',
        'status',
        'approved_by',
        'reason',
        'rejection_reason',
        'approved_at',
        'rejected_at',
        'partial_day',
        'partial_day_details',
        'is_urgent',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'partial_day_details' => 'array',
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

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function getDaysRequested(): int
    {
        return (int) $this->duration_days;
    }

    public function getHoursRequested(): float
    {
        return (float) ($this->duration_hours ?? ($this->duration_days * 8));
    }

    public function approve(int $userId): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);
    }

    public function reject(string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'rejected_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        if ($this->status !== 'used') {
            $this->update(['status' => 'cancelled']);
        }
    }
}
