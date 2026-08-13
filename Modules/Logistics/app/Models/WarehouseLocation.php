<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseLocation extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'wh_locations';

    protected $fillable = [
        'zone_id',
        'warehouse_id',
        'code',
        'type',
        'max_weight_kg',
        'max_volume_m3',
        'is_occupied',
        'current_product_id',
        'current_lot_id',
    ];

    protected $casts = [
        'max_weight_kg'     => 'decimal:2',
        'max_volume_m3'     => 'decimal:4',
        'is_occupied'       => 'boolean',
        'current_product_id' => 'integer',
        'current_lot_id'    => 'integer',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function zone(): BelongsTo
    {
        return $this->belongsTo(WarehouseZone::class, 'zone_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(WarehouseMovement::class, 'to_location_id');
    }

    public function outgoingMovements(): HasMany
    {
        return $this->hasMany(WarehouseMovement::class, 'from_location_id');
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_occupied', 0);
    }

    public function scopeOccupied(Builder $query): Builder
    {
        return $query->where('is_occupied', 1);
    }

    public function scopeInZone(Builder $query, int $zoneId): Builder
    {
        return $query->where('zone_id', $zoneId);
    }

    public function scopeForWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    // ---------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------

    /**
     * Parse the location code (A-01-01-01) into its components.
     *
     * @return array{aisle: string, row: string, shelf: string, bin: string}
     */
    public function getParsedCodeAttribute(): array
    {
        $parts = explode('-', $this->code);

        return [
            'aisle' => $parts[0] ?? '',
            'row'   => $parts[1] ?? '',
            'shelf' => $parts[2] ?? '',
            'bin'   => $parts[3] ?? '',
        ];
    }
}
