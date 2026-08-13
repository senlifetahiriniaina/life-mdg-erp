<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use App\Models\User;
use App\Traits\EncryptableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;
use Modules\Helpdesk\Traits\HelpdeskLinkable;
use Modules\Logistics\Database\Factories\ShipmentFactory;

class Shipment extends Model
{
    use EncryptableTrait, HelpdeskLinkable;
    use HasFactory;
    use RecordsActivity;
    use SoftDeletes;

    protected static string $auditModule = 'Logistics';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->encrypted = ['shipper_address', 'consignee_address', 'special_instructions'];
    }

    protected static function newFactory(): ShipmentFactory
    {
        return ShipmentFactory::new();
    }

    protected $table = 'logistics_shipments';

    protected $fillable = [
        'reference',
        'type',
        'status',
        'carrier_id',
        'carrier_rate_id',
        'route_id',
        'shipper_name',
        'shipper_address',
        'shipper_city',
        'shipper_country',
        'consignee_name',
        'consignee_address',
        'consignee_city',
        'consignee_country',
        'origin_warehouse_id',
        'destination_warehouse_id',
        'origin_location_id',
        'destination_location_id',
        'tracking_number',
        'incoterm',
        'transport_mode',
        'weight_kg',
        'volume_cbm',
        'declared_value',
        'value_currency',
        'estimated_cost',
        'actual_cost',
        'booked_at',
        'picked_up_at',
        'delivered_at',
        'estimated_delivery_at',
        'special_instructions',
        'requires_cold_chain',
        'temperature_min',
        'temperature_max',
        'has_hazmat',
        'co2_kg',
        'created_by',
    ];

    protected $casts = [
        'requires_cold_chain' => 'boolean',
        'has_hazmat' => 'boolean',
        'booked_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'estimated_delivery_at' => 'datetime',
        'weight_kg' => 'decimal:3',
        'volume_cbm' => 'decimal:4',
        'declared_value' => 'decimal:2',
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'co2_kg' => 'decimal:3',
    ];

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function carrierRate(): BelongsTo
    {
        return $this->belongsTo(CarrierRate::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(LogisticsRoute::class, 'route_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ShipmentLine::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(ShipmentPackage::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(TrackingEvent::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'booked'], true);
    }

    public static function generateReference(): static
    {
        $date = now()->format('Ymd');
        $count = static::whereDate('created_at', today())->count() + 1;

        $instance = new static;
        $instance->reference = sprintf('SHP-%s-%04d', $date, $count);

        return $instance;
    }
}
