<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;
use Modules\Inventory\Database\Factories\PurchaseOrderFactory;

/**
 * @property int $id
 * @property string $reference
 * @property int $supplier_id
 * @property int|null $warehouse_id
 * @property int|null $created_by
 * @property string $status
 * @property string $currency
 * @property string $subtotal
 * @property string $tax_total
 * @property string $shipping_cost
 * @property string $grand_total
 * @property string|null $notes
 * @property Carbon|null $expected_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $received_at
 */
class PurchaseOrder extends Model
{
    use HasFactory, RecordsActivity, SoftDeletes;

    protected static string $auditModule = 'Inventory';

    protected static function newFactory(): PurchaseOrderFactory
    {
        return PurchaseOrderFactory::new();
    }

    protected $table = 'inventory_purchase_orders';

    protected $fillable = [
        'reference', 'supplier_id', 'warehouse_id', 'created_by',
        'status', 'currency', 'subtotal', 'tax_total', 'shipping_cost', 'grand_total',
        'notes', 'expected_at', 'sent_at', 'received_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:4',
        'tax_total' => 'decimal:4',
        'shipping_cost' => 'decimal:4',
        'grand_total' => 'decimal:4',
        'expected_at' => 'datetime',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<PurchaseOrderItem, PurchaseOrder> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function recalculateTotals(): void
    {
        $subtotal = $this->items->sum('total_price');
        $this->update(['subtotal' => $subtotal, 'grand_total' => $subtotal + (float) $this->tax_total + (float) $this->shipping_cost]);
    }
}
