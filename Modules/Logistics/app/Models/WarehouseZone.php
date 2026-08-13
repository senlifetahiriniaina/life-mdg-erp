<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseZone extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'wh_zones';

    protected $fillable = [
        'warehouse_id',
        'name',
        'code',
        'type',
        'temperature_min',
        'temperature_max',
    ];

    protected $casts = [
        'temperature_min' => 'decimal:2',
        'temperature_max' => 'decimal:2',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class, 'zone_id');
    }

    public function putAwayRules(): HasMany
    {
        return $this->hasMany(WarehousePutAwayRule::class, 'preferred_zone_id');
    }

    // ---------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------

    public function getTemperatureRangeAttribute(): ?string
    {
        if ($this->temperature_min === null && $this->temperature_max === null) {
            return null;
        }

        return "{$this->temperature_min}°C – {$this->temperature_max}°C";
    }

    public function isTemperatureControlled(): bool
    {
        return $this->temperature_min !== null || $this->temperature_max !== null;
    }
}
