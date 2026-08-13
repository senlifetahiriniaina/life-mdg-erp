<?php

namespace Modules\Achats\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Achats\Events\PurchaseOrderReadyForAccounting;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property int|null $invoice_id
 * @property int|null $journal_id
 * @property int|null $gl_account_id
 * @property string $status
 * @property string|null $synced_amount
 * @property Carbon|null $synced_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PoAccountingMapping extends Model
{
    use HasFactory;
    protected $table = 'achats_po_accounting_mappings';

    protected $fillable = [
        'purchase_order_id',
        'invoice_id',
        'journal_id',
        'gl_account_id',
        'status',
        'synced_amount',
        'synced_at',
    ];

    protected $casts = [
        'synced_amount' => 'decimal:4',
        'synced_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function syncToAccounting(): void
    {
        // Dispatch event to sync with Accounting module
        event(new PurchaseOrderReadyForAccounting($this->purchaseOrder));
    }
}
