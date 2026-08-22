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

    /**
     * Chantier 32.23 (deep 14-layer audit): $fillable was missing 6 real,
     * physically-migrated columns (location_id, sequence, delivery_window,
     * address, contact_name, notes) that DeliveryRoundController::store()/
     * addStop() both validate and pass through unconditionally — confirmed
     * empirically via `php artisan tinker` that every one of them was
     * silently dropped on mass-assignment (create() with all 6 keys present
     * left every one of them NULL). A delivery round's stops have never
     * actually recorded which address to deliver to, who the contact is, or
     * any delivery-window/notes since this model was built — only
     * shipment_id/stop_order ever persisted. `sequence` is kept as an alias
     * column (the controller itself already reconciles it into stop_order
     * when stop_order is absent) so a caller sending either key round-trips.
     */
    protected $fillable = [
        'delivery_round_id',
        'stop_order',
        'sequence',
        'shipment_id',
        'location_id',
        'delivery_window',
        'address',
        'contact_name',
        'notes',
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
