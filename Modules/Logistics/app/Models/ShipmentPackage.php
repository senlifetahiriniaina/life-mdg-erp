<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Logistics\Database\Factories\ShipmentPackageFactory;

class ShipmentPackage extends Model
{
    use HasFactory;

    protected static function newFactory(): ShipmentPackageFactory
    {
        return ShipmentPackageFactory::new();
    }

    protected $table = 'logistics_shipment_packages';

    protected $fillable = [
        'shipment_id',
        'package_number',
        'type',
        'weight_kg',
        'length_cm',
        'width_cm',
        'height_cm',
        'tracking_number',
        'seal_number',
        'is_fragile',
    ];

    protected $casts = [
        'weight_kg' => 'decimal:3',
        'length_cm' => 'decimal:2',
        'width_cm' => 'decimal:2',
        'height_cm' => 'decimal:2',
        'is_fragile' => 'boolean',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
