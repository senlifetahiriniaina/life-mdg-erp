<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'lgx_vehicles';

    protected $fillable = [
        'company_id',
        'name',
        'plate_number',
        'type',
        'max_weight_kg',
        'max_volume_m3',
        'status',
        'driver_id',
        'fuel_type',
        'fuel_consumption_per_100km',
    ];

    protected $casts = [
        'max_weight_kg'              => 'decimal:2',
        'max_volume_m3'              => 'decimal:3',
        'fuel_consumption_per_100km' => 'decimal:2',
    ];

    public function routes(): HasMany
    {
        return $this->hasMany(DeliveryRoute::class, 'vehicle_id');
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'available'   => 'green',
            'on_route'    => 'blue',
            'maintenance' => 'yellow',
            'inactive'    => 'red',
            default       => 'gray',
        };
    }

    /**
     * Estimate fuel cost in XOF for a given distance.
     * Default fuel price 700 XOF/litre (Dakar pump price ~2026).
     */
    public function estimateFuelCost(float $distanceKm, float $fuelPricePerLitre = 700.0): float
    {
        $consumption = (float) ($this->fuel_consumption_per_100km ?? 10.0);

        return ($distanceKm / 100) * $consumption * $fuelPricePerLitre;
    }
}
