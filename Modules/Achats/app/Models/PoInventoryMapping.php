<?php

namespace Modules\Achats\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Achats\Events\PurchaseReceiptCompletedForInventory;
use Modules\Inventory\Models\Product;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property int|null $receipt_id
 * @property int $product_id
 * @property string $received_qty
 * @property string $status
 * @property Carbon|null $synced_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PoInventoryMapping extends Model
{
    use HasFactory;
    protected $table = 'achats_po_inventory_mappings';

    protected $fillable = [
        'purchase_order_id',
        'receipt_id',
        'product_id',
        'received_qty',
        'status',
        'synced_at',
    ];

    protected $casts = [
        'received_qty' => 'decimal:4',
        'synced_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function syncToInventory(): void
    {
        // Dispatch event to sync with Inventory module
        event(new PurchaseReceiptCompletedForInventory($this->purchaseOrder));
    }
}
