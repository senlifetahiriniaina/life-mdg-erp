<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;
use Modules\Logistics\Database\Factories\LogisticsRouteFactory;

class LogisticsRoute extends Model
{
    use HasFactory;
    use RecordsActivity;
    use SoftDeletes;

    protected static string $auditModule = 'Logistics';

    protected static function newFactory(): LogisticsRouteFactory
    {
        return LogisticsRouteFactory::new();
    }

    protected $table = 'logistics_routes';

    protected $fillable = [
        'name',
        'code',
        'origin_name',
        'origin_country',
        'origin_address',
        'destination_name',
        'destination_country',
        'destination_address',
        'mode',
        'distance_km',
        'estimated_transit_days',
        'carrier_id',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class, 'carrier_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'route_id');
    }
}
