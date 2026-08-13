<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $receipt_id
 * @property int|null $purchase_order_item_id
 * @property int $product_id
 * @property float $quantity_ordered
 * @property float $quantity_received
 * @property string|null $lot_number
 * @property Carbon|null $expiry_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PoReceipt $receipt
 * @property-read Product $product
 */
class PoReceiptLine extends Model
{
    use HasFactory;
    protected $table = 'inventory_po_receipt_lines';

    protected $fillable = [
        'receipt_id',
        'purchase_order_item_id',
        'product_id',
        'quantity_ordered',
        'quantity_received',
        'lot_number',
        'expiry_date',
    ];

    protected $casts = [
        'quantity_ordered' => 'float',
        'quantity_received' => 'float',
        'expiry_date' => 'date',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PoReceipt::class, 'receipt_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
