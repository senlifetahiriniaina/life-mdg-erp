<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Inventory\Database\Factories\ShipmentFactory;

/**
 * @property int $id
 * @property int $carrier_id
 * @property string $reference
 * @property int|null $order_id
 * @property string $status
 * @property string|null $tracking_number
 * @property string|null $label_url
 * @property array<string, mixed> $origin_address
 * @property array<string, mixed> $destination_address
 * @property string $weight_kg
 * @property array<string, mixed>|null $dimensions
 * @property string|null $service_type
 * @property string|null $estimated_cost
 * @property string|null $actual_cost
 * @property Carbon|null $shipped_at
 * @property string|null $estimated_delivery_at
 * @property Carbon|null $delivered_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Carrier $carrier
 * @property-read Collection<int, ShipmentEvent> $events
 */
class Shipment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected static function newFactory(): ShipmentFactory
    {
        return ShipmentFactory::new();
    }

    protected $table = 'inventory_shipments';

    protected $fillable = [
        'carrier_id',
        'reference',
        'order_id',
        'status',
        'tracking_number',
        'label_url',
        'origin_address',
        'destination_address',
        'weight_kg',
        'dimensions',
        'service_type',
        'estimated_cost',
        'actual_cost',
        'shipped_at',
        'estimated_delivery_at',
        'delivered_at',
    ];

    protected $casts = [
        'origin_address' => 'array',
        'destination_address' => 'array',
        'dimensions' => 'array',
        'weight_kg' => 'decimal:3',
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    /** @return BelongsTo<Carrier, self> */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class, 'carrier_id');
    }

    /** @return HasMany<ShipmentEvent, self> */
    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class, 'shipment_id')->orderBy('occurred_at');
    }
}
