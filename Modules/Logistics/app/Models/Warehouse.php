<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'wh_warehouses';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'type',
        'address',
        'city',
        'country_code',
        'lat',
        'lng',
        'surface_m2',
        'status',
        'manager_id',
    ];

    protected $casts = [
        'lat'        => 'decimal:7',
        'lng'        => 'decimal:7',
        'surface_m2' => 'decimal:2',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function zones(): HasMany
    {
        return $this->hasMany(WarehouseZone::class, 'warehouse_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class, 'warehouse_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(WarehouseMovement::class, 'warehouse_id');
    }

    public function putAwayRules(): HasMany
    {
        return $this->hasMany(WarehousePutAwayRule::class, 'warehouse_id');
    }

    public function outboundShipments(): HasMany
    {
        return $this->hasMany(LgxShipment::class, 'origin_warehouse_id');
    }

    public function inboundShipments(): HasMany
    {
        return $this->hasMany(LgxShipment::class, 'dest_warehouse_id');
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    // ---------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------

    public function getOccupancyRateAttribute(): float
    {
        $total    = $this->locations()->count();
        $occupied = $this->locations()->where('is_occupied', 1)->count();

        return $total > 0 ? round(($occupied / $total) * 100, 2) : 0.0;
    }

    public function getFullAddressAttribute(): string
    {
        return implode(', ', array_filter([
            $this->address,
            $this->city,
            $this->country_code,
        ]));
    }
}
