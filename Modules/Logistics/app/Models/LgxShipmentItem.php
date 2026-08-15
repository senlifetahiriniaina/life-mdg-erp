<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class LgxShipmentItem extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'lgx_shipment_items';

    protected $fillable = [
        'shipment_id',
        'product_id',
        'lot_number',
        'serial_number',
        'qty_ordered',
        'qty_shipped',
        'unit',
        'location_id',
    ];

    protected $casts = [
        'qty_ordered' => 'decimal:4',
        'qty_shipped' => 'decimal:4',
        'product_id'  => 'integer',
        'location_id' => 'integer',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(LgxShipment::class, 'shipment_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    // ---------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------

    public function getFulfillmentRateAttribute(): float
    {
        if ((float) $this->qty_ordered === 0.0) {
            return 0.0;
        }

        return round(((float) $this->qty_shipped / (float) $this->qty_ordered) * 100, 2);
    }

    public function isFullyShipped(): bool
    {
        return (float) $this->qty_shipped >= (float) $this->qty_ordered;
    }
}
