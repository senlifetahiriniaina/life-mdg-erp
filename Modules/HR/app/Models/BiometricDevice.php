<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class BiometricDevice extends Model
{
    use HasFactory;
    use SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'hr_biometric_devices';

    protected $fillable = [
        'device_id',
        'device_name',
        'device_type',
        'manufacturer',
        'model',
        'location',
        'building',
        'floor',
        'zone',
        'latitude',
        'longitude',
        'ip_address',
        'mac_address',
        'status',
        'last_sync',
        'firmware_version',
        'capacity',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'last_sync' => 'datetime',
    ];

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'device_id');
    }

    public function isOnline(): bool
    {
        if (!$this->last_sync) {
            return false;
        }
        return $this->last_sync->greaterThan(now()->subMinutes(5));
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isMaintenanceRequired(): bool
    {
        return $this->status === 'maintenance';
    }

    public function getRecordCount(): int
    {
        return $this->attendanceRecords()->count();
    }

    public function getCapacityPercentage(): float
    {
        $count = $this->getRecordCount();
        return ($count / $this->capacity) * 100;
    }

    public function markOnline(): void
    {
        $this->update([
            'last_sync' => now(),
            'status' => $this->status === 'maintenance' ? 'maintenance' : 'active',
        ]);
    }

    public function markOffline(): void
    {
        // Status remains but tracking indicates offline
    }
}
