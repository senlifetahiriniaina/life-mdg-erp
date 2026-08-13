<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Inventory\Database\Factories\ShipmentEventFactory;

/**
 * @property int $id
 * @property int $shipment_id
 * @property string $status
 * @property string|null $location
 * @property string|null $description
 * @property Carbon $occurred_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Shipment $shipment
 */
class ShipmentEvent extends Model
{
    use HasFactory;

    protected static function newFactory(): ShipmentEventFactory
    {
        return ShipmentEventFactory::new();
    }

    protected $table = 'inventory_shipment_events';

    protected $fillable = [
        'shipment_id',
        'status',
        'location',
        'description',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    /** @return BelongsTo<Shipment, self> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }
}
