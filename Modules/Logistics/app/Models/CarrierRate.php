<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Traits\RecordsActivity;
use Modules\Logistics\Database\Factories\CarrierRateFactory;

class CarrierRate extends Model
{
    use HasFactory;
    use RecordsActivity;

    protected static string $auditModule = 'Logistics';

    protected static function newFactory(): CarrierRateFactory
    {
        return CarrierRateFactory::new();
    }

    protected $table = 'logistics_carrier_rates';

    protected $fillable = [
        'carrier_id',
        'name',
        'mode',
        'origin_country',
        'destination_country',
        'origin_zone',
        'destination_zone',
        'rate_type',
        'base_rate',
        'fuel_surcharge_pct',
        'insurance_rate_pct',
        'min_charge',
        'currency',
        'transit_days',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'base_rate' => 'decimal:4',
        'fuel_surcharge_pct' => 'decimal:2',
        'insurance_rate_pct' => 'decimal:2',
        'min_charge' => 'decimal:4',
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    /**
     * Estimate shipping cost for given weight (kg) and distance (km).
     */
    public function estimateCost(float $weight, float $distance): float
    {
        $base = (float) $this->base_rate;

        $raw = match ($this->rate_type) {
            'per_kg' => $base * $weight,
            'per_km' => $base * $distance,
            'per_piece' => $base,
            'per_cbm' => $base,
            default => $base, // flat
        };

        $fuel = $raw * ((float) $this->fuel_surcharge_pct / 100);
        $insurance = $raw * ((float) $this->insurance_rate_pct / 100);
        $total = $raw + $fuel + $insurance;

        return max($total, (float) $this->min_charge);
    }
}
