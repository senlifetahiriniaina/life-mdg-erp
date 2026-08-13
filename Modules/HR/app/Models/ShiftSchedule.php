<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftSchedule extends Model
{
    use SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'hr_shift_schedules';

    protected $fillable = [
        'employee_id',
        'shift_name',
        'shift_code',
        'start_time',
        'end_time',
        'working_hours',
        'days_of_week',
        'is_night_shift',
        'is_flexible',
        'effective_from',
        'effective_to',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'days_of_week' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' &&
               now()->greaterThanOrEqualTo($this->effective_from) &&
               (is_null($this->effective_to) || now()->lessThanOrEqualTo($this->effective_to));
    }

    public function isNightShift(): bool
    {
        return $this->is_night_shift;
    }

    public function getWorksOnDay(int $dayOfWeek): bool
    {
        $days = $this->days_of_week ?? [];
        return in_array($dayOfWeek, $days);
    }

    public function getDurationHours(): int
    {
        return $this->working_hours;
    }

    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
            'effective_to' => now()->toDateString(),
        ]);
    }
}
