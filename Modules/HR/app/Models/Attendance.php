<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HR\Database\Factories\AttendanceFactory;

/**
 * Chantier 32.17 (HR deep 14-layer audit): $fillable used to declare
 * 'check_in'/'check_out' — columns that have never existed on the real
 * hr_attendance table (a catch-all-scaffolded table carrying 4 different,
 * never-reconciled naming attempts: clock_in/clock_out (datetime),
 * attendance_date/check_in_time/check_out_time (date/time)). Confirmed
 * empirically via `php artisan tinker` that Attendance::create() with those
 * keys was a guaranteed SQLSTATE "no such column" fatal error on every real
 * call — invisible until now because AttendanceController::store()/update()
 * (the only real callers) had zero route registered anywhere until this
 * same chantier added one, and AttendanceFactory used the identical wrong
 * keys, so no test ever exercised the real insert path either. Repointed
 * onto the real check_in_time/check_out_time TIME columns (semantically the
 * closest match — a time-of-day paired with the separate real `date`
 * column, not a full datetime like clock_in/clock_out, which belongs to the
 * unrelated, separate AttendanceRecord/hr_attendance_records model this
 * class never touches) with check_in/check_out kept as accessor aliases so
 * the JSON contract HR/Attendance/Manage.vue already reads
 * (data.check_in/data.check_out) doesn't need to change.
 */
class Attendance extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hr_attendance';

    protected $fillable = [
        'employee_id',
        'date',
        'check_in_time',
        'check_out_time',
        'status',
        'notes',
    ];

    // Chantier 32.17: HR/Attendance/Manage.vue's real table columns/avatar
    // render also read 'employee_name'/'department'/'duration' as flat,
    // top-level keys — raw Eloquent JSON never had those (only a nested
    // 'employee' relation object, and 'duration_hours'), a mismatch
    // confirmed empirically (data.employee_name.charAt(0) would throw on
    // every real row once the page's own data ever loaded any). Appended
    // here as accessors reading the eager-loaded relation rather than
    // reshaping the frontend, keeping the same minimal-field discipline
    // AttendanceController::index() already applies to the 'employee'
    // relation itself (no PII beyond name/department).
    protected $appends = ['check_in', 'check_out', 'employee_name', 'department', 'duration'];

    protected $casts = [
        'date' => 'date',
    ];

    public function getCheckInAttribute()
    {
        return $this->attributes['check_in_time'] ?? null;
    }

    public function getCheckOutAttribute()
    {
        return $this->attributes['check_out_time'] ?? null;
    }

    public function getEmployeeNameAttribute(): ?string
    {
        if (! $this->relationLoaded('employee') || ! $this->employee) {
            return null;
        }

        return trim("{$this->employee->first_name} {$this->employee->last_name}") ?: null;
    }

    public function getDepartmentAttribute(): ?string
    {
        if (! $this->relationLoaded('employee') || ! $this->employee?->relationLoaded('department')) {
            return null;
        }

        return $this->employee->department?->name;
    }

    public function getDurationAttribute(): float
    {
        return $this->duration_hours;
    }

    protected static function newFactory()
    {
        return AttendanceFactory::new();
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function scopeByDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeByMonth($query, $month, $year)
    {
        return $query->whereYear('date', $year)
            ->whereMonth('date', $month);
    }

    /**
     * Duration in hours between check_in and check_out.
     */
    public function getDurationHoursAttribute(): float
    {
        if (! $this->check_in || ! $this->check_out) {
            return 0;
        }

        $in  = \Carbon\Carbon::parse($this->check_in);
        $out = \Carbon\Carbon::parse($this->check_out);

        return round($in->diffInMinutes($out) / 60, 2);
    }

    /**
     * Overtime hours beyond an 8-hour workday.
     */
    public function getOvertimeHoursAttribute(): float
    {
        $duration = $this->duration_hours;

        return max(0, $duration - 8);
    }
}
