<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Logistics\Database\Factories\ShipmentLineFactory;

class ShipmentLine extends Model
{
    use HasFactory;

    protected static function newFactory(): ShipmentLineFactory
    {
        return ShipmentLineFactory::new();
    }

    protected $table = 'logistics_shipment_lines';

    protected $fillable = [
        'shipment_id',
        'product_id',
        'description',
        'sku',
        'hs_code',
        'quantity',
        'unit',
        'unit_value',
        'country_of_origin',
        'lot_number',
        'serial_number',
        'expiry_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_value' => 'decimal:4',
        'expiry_date' => 'date',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
