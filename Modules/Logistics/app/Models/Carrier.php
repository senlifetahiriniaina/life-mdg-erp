<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;
use Modules\Logistics\Database\Factories\CarrierFactory;

class Carrier extends Model
{
    use HasFactory;
    use RecordsActivity;
    use SoftDeletes;

    protected static string $auditModule = 'Logistics';

    protected static function newFactory(): CarrierFactory
    {
        return CarrierFactory::new();
    }

    protected $table = 'logistics_carriers';

    protected $fillable = [
        'name',
        'code',
        'type',
        'contact_email',
        'contact_phone',
        'website',
        'country',
        'tracking_url_template',
        'api_provider',
        'api_credentials',
        'rating',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'api_credentials' => 'encrypted:array',
        'is_active' => 'boolean',
        'rating' => 'decimal:2',
    ];

    public function rates(): HasMany
    {
        return $this->hasMany(CarrierRate::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(LogisticsRoute::class);
    }

    public function deliveryRounds(): HasMany
    {
        return $this->hasMany(DeliveryRound::class);
    }

    /**
     * Calculate a performance score (0–5) based on recent delivered shipments.
     */
    public function calculatePerformanceScore(): float
    {
        $recent = $this->shipments()
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->subDays(90))
            ->get();

        if ($recent->isEmpty()) {
            return (float) ($this->rating ?? 0.0);
        }

        $onTime = $recent->filter(function (Shipment $s): bool {
            return $s->delivered_at !== null
                && $s->estimated_delivery_at !== null
                && $s->delivered_at->lte($s->estimated_delivery_at);
        })->count();

        return round(($onTime / $recent->count()) * 5, 2);
    }
}
