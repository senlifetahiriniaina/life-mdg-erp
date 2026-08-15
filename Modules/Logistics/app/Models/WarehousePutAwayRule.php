<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class WarehousePutAwayRule extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'wh_put_away_rules';

    protected $fillable = [
        'warehouse_id',
        'product_category',
        'product_id',
        'preferred_zone_id',
        'strategy',
        'priority',
    ];

    protected $casts = [
        'priority'   => 'integer',
        'product_id' => 'integer',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function preferredZone(): BelongsTo
    {
        return $this->belongsTo(WarehouseZone::class, 'preferred_zone_id');
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopeForWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('priority');
    }

    public function scopeForProduct(Builder $query, ?int $productId, ?string $category): Builder
    {
        return $query->where(function (Builder $q) use ($productId, $category): void {
            if ($productId !== null) {
                $q->where('product_id', $productId);
            }
            if ($category !== null) {
                $q->orWhere('product_category', $category);
            }
            // catch-all rules (no product / category filter)
            $q->orWhereNull('product_id')->whereNull('product_category');
        });
    }
}
