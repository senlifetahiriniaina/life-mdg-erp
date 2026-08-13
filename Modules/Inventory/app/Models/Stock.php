<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $product_id
 * @property int $warehouse_id
 * @property int|null $location_id
 * @property float $quantity
 * @property float $reserved_quantity
 * @property float $avg_cost
 */
class Stock extends Model
{
    use HasFactory;
    protected $table = 'inventory_stock';

    protected $fillable = ['product_id', 'warehouse_id', 'location_id', 'quantity', 'reserved_quantity', 'avg_cost'];

    protected $casts = [
        'quantity' => 'decimal:4',
        'reserved_quantity' => 'decimal:4',
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

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}
