<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Logistics\Database\Factories\DeliveryStopFactory;

class DeliveryStop extends Model
{
    use HasFactory;

    protected static function newFactory(): DeliveryStopFactory
    {
        return DeliveryStopFactory::new();
    }

    protected $table = 'logistics_delivery_stops';

    protected $fillable = [
        'delivery_round_id',
        'stop_order',
        'shipment_id',
        'recipient_name',
        'recipient_address',
        'recipient_city',
        'recipient_phone',
        'latitude',
        'longitude',
        'status',
        'arrived_at',
        'completed_at',
        'pod_signature',
        'pod_photo_path',
        'pod_note',
        'failure_reason',
    ];

    protected $casts = [
        'arrived_at' => 'datetime',
        'completed_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function deliveryRound(): BelongsTo
    {
        return $this->belongsTo(DeliveryRound::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
