<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LgxTrackingEvent extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    // Only created_at; no updated_at column
    public const UPDATED_AT = null;

    protected $table = 'lgx_tracking_events';

    protected $fillable = [
        'shipment_id',
        'status',
        'location',
        'lat',
        'lng',
        'description',
        'carrier_event_code',
        'occurred_at',
    ];

    protected $casts = [
        'lat'         => 'decimal:7',
        'lng'         => 'decimal:7',
        'occurred_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    // Status → colour mapping for timeline chips
    private const STATUS_COLORS = [
        'draft'      => 'gray',
        'confirmed'  => 'blue',
        'picked'     => 'indigo',
        'packed'     => 'purple',
        'dispatched' => 'orange',
        'in_transit' => 'yellow',
        'delivered'  => 'green',
        'failed'     => 'red',
        'returned'   => 'pink',
        'exception'  => 'red',
        'customs'    => 'yellow',
        'out_for_delivery' => 'teal',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(LgxShipment::class, 'shipment_id');
    }

    // ---------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    public function hasCoordinates(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }
}
