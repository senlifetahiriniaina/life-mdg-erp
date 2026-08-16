<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Logistics\Database\Factories\TrackingEventFactory;

class TrackingEvent extends Model
{
    use HasFactory;

    protected static function newFactory(): TrackingEventFactory
    {
        return TrackingEventFactory::new();
    }

    protected $table = 'logistics_tracking_events';

    protected $fillable = [
        'shipment_id',
        'event_type',
        'status_detail',
        'location_name',
        'location_city',
        'location_country',
        'latitude',
        'longitude',
        'carrier_ref',
        'is_exception',
        'exception_reason',
        'recorded_at',
        'recorded_by',
        'provider_event_id',
        'idempotency_key',
    ];

    protected $casts = [
        'is_exception' => 'boolean',
        'recorded_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
