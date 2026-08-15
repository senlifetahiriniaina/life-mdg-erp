<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class AttendanceAnalytics extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'hr_attendance_analytics';

    public $timestamps = true;

    protected $fillable = [
        'employee_id',
        'analytics_date',
        'month',
        'year',
        'days_present',
        'days_absent',
        'days_late',
        'days_early_departure',
        'total_late_minutes',
        'total_early_minutes',
        'total_working_minutes',
        'total_expected_minutes',
        'attendance_percentage',
        'punctuality_percentage',
        'trend',
    ];

    protected $casts = [
        'analytics_date' => 'date',
        'attendance_percentage' => 'decimal:2',
        'punctuality_percentage' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function getWorkedHours(): int
    {
        return (int) ($this->total_working_minutes / 60);
    }

    public function getExpectedHours(): int
    {
        return (int) ($this->total_expected_minutes / 60);
    }

    public function getAverageLateness(): float
    {
        if ($this->days_late === 0) {
            return 0.0;
        }
        return $this->total_late_minutes / $this->days_late;
    }

    public function getAverageEarlyDeparture(): float
    {
        if ($this->days_early_departure === 0) {
            return 0.0;
        }
        return $this->total_early_minutes / $this->days_early_departure;
    }

    public function isTrendImproving(): bool
    {
        return $this->trend === 'improving';
    }

    public function isTrendDeclining(): bool
    {
        return $this->trend === 'declining';
    }

    public function calculateTrend(?float $previousPercentage = null): string
    {
        if ($previousPercentage === null) {
            return 'stable';
        }

        if ($this->attendance_percentage > $previousPercentage + 2) {
            return 'improving';
        }
        if ($this->attendance_percentage < $previousPercentage - 2) {
            return 'declining';
        }
        return 'stable';
    }
}
