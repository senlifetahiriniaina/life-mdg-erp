<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseMovement extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    // Only created_at; no updated_at column
    public const UPDATED_AT = null;

    protected $table = 'wh_movements';

    protected $fillable = [
        'company_id',
        'warehouse_id',
        'from_location_id',
        'to_location_id',
        'product_id',
        'lot_number',
        'serial_number',
        'qty',
        'unit',
        'type',
        'reference',
        'reference_type',
        'operator_id',
        'notes',
    ];

    protected $casts = [
        'qty'        => 'decimal:4',
        'created_at' => 'datetime',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'to_location_id');
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeForWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForReference(Builder $query, string $reference, ?string $type = null): Builder
    {
        $query->where('reference', $reference);

        if ($type !== null) {
            $query->where('reference_type', $type);
        }

        return $query;
    }
}
