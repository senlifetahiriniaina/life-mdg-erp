<?php

namespace Modules\Achats\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\Product;

/**
 * @property int $id
 * @property int $receipt_id
 * @property int $purchase_order_line_id
 * @property int|null $product_id
 * @property string $quantity_received
 * @property string $quality_status
 * @property string $variance_qty
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PurchaseReceiptLine extends Model
{
    use HasFactory;
    protected $table = 'achats_purchase_receipt_lines';

    protected $fillable = [
        'receipt_id',
        'purchase_order_line_id',
        'product_id',
        'quantity_received',
        'quality_status',
        'variance_qty',
        'notes',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:4',
        'variance_qty' => 'decimal:4',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class, 'receipt_id');
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function getVariance(): float
    {
        return (float) $this->variance_qty;
    }

    public function isQualityIssue(): bool
    {
        return in_array($this->quality_status, ['damaged', 'missing']);
    }
}
