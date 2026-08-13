<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Inventory\Database\Factories\CarrierFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $tracking_url_template
 * @property string|null $api_key
 * @property bool $active
 * @property array<string, mixed>|null $settings
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, Shipment> $shipments
 */
class Carrier extends Model
{
    use HasFactory;

    protected static function newFactory(): CarrierFactory
    {
        return CarrierFactory::new();
    }

    protected $table = 'inventory_carriers';

    protected $fillable = [
        'name',
        'code',
        'tracking_url_template',
        'api_key',
        'active',
        'settings',
    ];

    protected $casts = [
        'active' => 'boolean',
        'settings' => 'array',
    ];

    /** @return HasMany<Shipment, self> */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'carrier_id');
    }
}
