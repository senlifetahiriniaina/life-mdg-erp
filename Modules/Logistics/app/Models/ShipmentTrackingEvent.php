<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Shipment tracking event log.
 *
 * @property int    $id
 * @property int    $shipment_id
 * @property string $provider       marinetraffic|flexport|flightaware|fallback|ocean|air
 * @property array  $raw_payload    Raw provider response payload
 * @property string $event_type     departed|arrived|transshipment|in_transit|update
 * @property string $event_at
 * @property string|null $location  LOCODE or IATA code
 */
class ShipmentTrackingEvent extends Model
{
    use HasFactory;

    protected $table = 'shipment_tracking_events';

    protected $fillable = [
        'shipment_id',
        'provider',
        'raw_payload',
        'event_type',
        'event_at',
        'location',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'event_at'    => 'datetime',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
