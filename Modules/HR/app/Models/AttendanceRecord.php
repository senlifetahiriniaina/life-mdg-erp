<?php

declare(strict_types=1);

namespace Modules\HR\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property Carbon $clock_in
 * @property Carbon|null $clock_out
 * @property int $break_minutes
 * @property string $type
 * @property string|null $notes
 * @property string|null $ip_address
 * @property string|null $location_lat
 * @property string|null $location_lng
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AttendanceRecord extends Model
{
    use HasFactory;
    protected $table = 'hr_attendance_records';

    protected $fillable = [
        'employee_id', 'clock_in', 'clock_out', 'break_minutes',
        'type', 'notes', 'ip_address', 'location_lat', 'location_lng',
        'device_id', 'clock_in_method', 'device_name', 'verification_status',
        'location',
    ];

    protected $casts = [
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'location_lat' => 'decimal:7',
        'location_lng' => 'decimal:7',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'device_id');
    }

    /**
     * Get worked hours (excluding break).
     */
    public function getWorkedHoursAttribute(): float
    {
        if ($this->clock_out === null) {
            return 0.0;
        }

        $totalMinutes = $this->clock_in->diffInMinutes($this->clock_out);

        return round(($totalMinutes - $this->break_minutes) / 60, 2);
    }

    public function clockOut(): void
    {
        $this->update(['clock_out' => now()]);
    }

    public function verify(): void
    {
        $this->update(['verification_status' => 'verified']);
    }
}
