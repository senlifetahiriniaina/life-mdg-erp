<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property int|null $product_id
 * @property string $product_name
 * @property string|null $sku
 * @property string $quantity_ordered
 * @property string $quantity_received
 * @property string $unit_price
 * @property string $tax_rate
 * @property string $total_price
 */
class PurchaseOrderItem extends Model
{
    use HasFactory;
    protected $table = 'inventory_purchase_order_items';

    protected $fillable = [
        'purchase_order_id', 'product_id', 'product_name', 'sku',
        'quantity_ordered', 'quantity_received', 'unit_price', 'tax_rate', 'total_price',
    ];

    protected $casts = [
        'quantity_ordered' => 'decimal:4',
        'quantity_received' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'tax_rate' => 'decimal:2',
        'total_price' => 'decimal:4',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function remainingQty(): float
    {
        return max(0, (float) $this->quantity_ordered - (float) $this->quantity_received);
    }
}
