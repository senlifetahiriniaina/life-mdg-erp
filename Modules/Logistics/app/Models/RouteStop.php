<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteStop extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'lgx_route_stops';

    protected $fillable = [
        'route_id',
        'shipment_id',
        'sequence',
        'type',
        'address',
        'lat',
        'lng',
        'planned_arrival',
        'actual_arrival',
        'planned_duration_min',
        'status',
        'proof_of_delivery',
        'signature_url',
        'notes',
    ];

    protected $casts = [
        'lat'                  => 'decimal:7',
        'lng'                  => 'decimal:7',
        'actual_arrival'       => 'datetime',
        'planned_duration_min' => 'integer',
        'sequence'             => 'integer',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(DeliveryRoute::class, 'route_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * Whether the stop was completed later than its planned arrival time.
     */
    public function isLate(): bool
    {
        if ($this->actual_arrival === null || $this->planned_arrival === null) {
            return false;
        }

        $routeDate = optional($this->route)->date ?? now()->toDateString();
        $plannedDt = Carbon::parse($routeDate . ' ' . $this->planned_arrival);

        return $this->actual_arrival->gt($plannedDt);
    }

    public function getFormattedAddressAttribute(): string
    {
        return $this->address ?? '';
    }

    public function hasCoordinates(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }
}
