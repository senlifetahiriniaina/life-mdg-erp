<?php

namespace Modules\Achats\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\Product;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property int|null $product_id
 * @property string $description
 * @property string $quantity
 * @property string $unit
 * @property string $unit_price
 * @property string $tax_rate
 * @property string $line_total
 * @property string $received_qty
 * @property string $invoiced_qty
 * @property string $line_status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PurchaseOrderLine extends Model
{
    use HasFactory;
    protected $table = 'achats_purchase_order_lines';

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'tax_rate',
        'line_total',
        'received_qty',
        'invoiced_qty',
        'line_status',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'tax_rate' => 'decimal:2',
        'line_total' => 'decimal:4',
        'received_qty' => 'decimal:4',
        'invoiced_qty' => 'decimal:4',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function calculateLineTotal(): float
    {
        return (float) $this->quantity * (float) $this->unit_price;
    }

    public function getTaxAmount(): float
    {
        return (float) $this->line_total * ((float) $this->tax_rate / 100);
    }
}
