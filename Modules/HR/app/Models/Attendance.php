<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\HR\Database\Factories\AttendanceFactory;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'hr_attendance';

    protected $fillable = [
        'employee_id',
        'date',
        'check_in',
        'check_out',
        'status',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

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
