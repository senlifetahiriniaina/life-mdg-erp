<?php

namespace Modules\Achats\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property string $receipt_number
 * @property Carbon $receipt_date
 * @property int $received_by
 * @property string|null $warehouse_location
 * @property string|null $notes
 * @property string $total_received_value
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class PurchaseReceipt extends Model
{
    use HasFactory;
    use RecordsActivity, SoftDeletes;

    protected $table = 'achats_purchase_receipts';

    protected static string $auditModule = 'Achats';

    protected $fillable = [
        'purchase_order_id',
        'receipt_number',
        'receipt_date',
        'received_by',
        'warehouse_location',
        'notes',
        'total_received_value',
        'status',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'total_received_value' => 'decimal:4',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseReceiptLine::class, 'receipt_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function getTotalReceived(): float
    {
        return $this->lines->sum(function ($line) {
            return (float) $line->quantity_received;
        });
    }

    public function hasDiscrepancies(): bool
    {
        return $this->lines()
            ->whereIn('quality_status', ['damaged', 'missing'])
            ->exists();
    }

    public function markReceived(): void
    {
        $this->update(['status' => 'completed']);
    }
}
