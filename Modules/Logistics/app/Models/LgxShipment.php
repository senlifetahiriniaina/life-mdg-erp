<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LgxShipment extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'lgx_shipments';

    protected $fillable = [
        'company_id',
        'reference',
        'type',
        'status',
        'carrier_id',
        'carrier_service',
        'origin_warehouse_id',
        'origin_address',
        'dest_warehouse_id',
        'dest_address',
        'incoterm',
        'weight_kg',
        'volume_m3',
        'declared_value',
        'currency',
        'tracking_number',
        'estimated_delivery',
        'actual_delivery',
        'proof_of_delivery',
        'pod_signed_by',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'origin_address'    => 'array',
        'dest_address'      => 'array',
        'weight_kg'         => 'decimal:3',
        'volume_m3'         => 'decimal:4',
        'declared_value'    => 'decimal:2',
        'estimated_delivery' => 'date',
        'actual_delivery'   => 'datetime',
    ];

    // Status → Tailwind colour mapping
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
    ];

    // Incoterm → plain-language description
    private const INCOTERM_DESCRIPTIONS = [
        'EXW' => "Ex Works — buyer bears all transport costs from seller's premises",
        'FCA' => 'Free Carrier — seller delivers to named carrier',
        'FAS' => 'Free Alongside Ship — seller delivers goods alongside the vessel',
        'FOB' => 'Free On Board — seller loads goods on the vessel',
        'CFR' => 'Cost and Freight — seller pays freight to destination port',
        'CIF' => 'Cost, Insurance and Freight — seller pays freight and insurance',
        'CPT' => 'Carriage Paid To — seller pays freight to named destination',
        'CIP' => 'Carriage and Insurance Paid To — seller pays freight & insurance',
        'DAP' => 'Delivered At Place — seller delivers at named destination, unloaded',
        'DDP' => 'Delivered Duty Paid — seller bears all costs including import duties',
        'DPU' => 'Delivered at Place Unloaded — seller unloads at destination',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(LgxCarrier::class, 'carrier_id');
    }

    public function originWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'origin_warehouse_id');
    }

    public function destWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'dest_warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LgxShipmentItem::class, 'shipment_id');
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(LgxTrackingEvent::class, 'shipment_id')
            ->orderBy('occurred_at', 'asc');
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    // ---------------------------------------------------------------
    // Accessors (attribute-style)
    // ---------------------------------------------------------------

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    public function getIncoTermDescriptionAttribute(): ?string
    {
        if ($this->incoterm === null) {
            return null;
        }

        return self::INCOTERM_DESCRIPTIONS[$this->incoterm] ?? $this->incoterm;
    }

    // ---------------------------------------------------------------
    // Business logic helpers
    // ---------------------------------------------------------------

    public function isOverdue(): bool
    {
        if ($this->estimated_delivery === null) {
            return false;
        }

        if (in_array($this->status, ['delivered', 'returned', 'failed'], true)) {
            return false;
        }

        return Carbon::today()->greaterThan($this->estimated_delivery);
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isInTransit(): bool
    {
        return in_array($this->status, ['dispatched', 'in_transit'], true);
    }

    /**
     * Return the public tracking URL for this shipment, if available.
     */
    public function getTrackingUrlAttribute(): ?string
    {
        if ($this->tracking_number === null || $this->carrier === null) {
            return null;
        }

        $pattern = $this->carrier->tracking_url_pattern;

        if ($pattern === null) {
            return null;
        }

        return str_replace('{tracking_number}', $this->tracking_number, $pattern);
    }
}
