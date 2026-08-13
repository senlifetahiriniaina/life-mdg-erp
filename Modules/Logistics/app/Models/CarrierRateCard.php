<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarrierRateCard extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'lgx_carrier_rate_cards';

    protected $fillable = [
        'carrier_id',
        'origin_country',
        'dest_country',
        'service_type',
        'weight_min_kg',
        'weight_max_kg',
        'base_rate',
        'per_kg_rate',
        'currency',
        'transit_days_min',
        'transit_days_max',
        'is_active',
    ];

    protected $casts = [
        'weight_min_kg'    => 'decimal:3',
        'weight_max_kg'    => 'decimal:3',
        'base_rate'        => 'decimal:2',
        'per_kg_rate'      => 'decimal:4',
        'transit_days_min' => 'integer',
        'transit_days_max' => 'integer',
        'is_active'        => 'boolean',
    ];

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function calculateRate(float $weightKg): float
    {
        return (float) $this->base_rate
            + ((float) $this->per_kg_rate * max($weightKg, (float) $this->weight_min_kg));
    }

    public function coversWeight(float $weightKg): bool
    {
        return $weightKg >= (float) $this->weight_min_kg
            && $weightKg <= (float) $this->weight_max_kg;
    }
}
