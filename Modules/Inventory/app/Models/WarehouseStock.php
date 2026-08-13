<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\WarehouseStockFactory;

/**
 * Per-warehouse stock level for a product (maps to inventory_stock).
 *
 * @property int $product_id
 * @property int $warehouse_id
 * @property int $quantity
 */
class WarehouseStock extends Model
{
    use HasFactory;

    protected $table = 'inventory_stock';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'location_id',
        'quantity',
        'reserved_quantity',
        'avg_cost',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'avg_cost' => 'decimal:4',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    protected static function newFactory(): WarehouseStockFactory
    {
        return WarehouseStockFactory::new();
    }
}
