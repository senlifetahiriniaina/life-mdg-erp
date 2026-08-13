<?php

declare(strict_types=1);

namespace Modules\Achats\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $tenant_id
 * @property int $purchase_receipt_id
 * @property int $purchase_order_id
 * @property int|null $invoice_id
 * @property float $quantity_variance
 * @property float $price_variance
 * @property string $match_result
 * @property array|null $mismatch_details
 * @property string $status
 * @property int|null $resolved_by
 * @property \Carbon\Carbon|null $resolved_at
 * @property string|null $resolution_notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PurchaseInvoiceMatch extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'achats_purchase_invoice_matches';

    protected $fillable = [
        'tenant_id',
        'purchase_receipt_id',
        'purchase_order_id',
        'invoice_id',
        'quantity_variance',
        'price_variance',
        'match_result',
        'mismatch_details',
        'status',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'tenant_id'           => 'integer',
        'purchase_receipt_id' => 'integer',
        'purchase_order_id'   => 'integer',
        'invoice_id'          => 'integer',
        'quantity_variance'   => 'decimal:4',
        'price_variance'      => 'decimal:4',
        'mismatch_details'    => 'array',
        'resolved_by'         => 'integer',
        'resolved_at'         => 'datetime',
    ];

    public function purchaseReceipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'resolved_by');
    }

    /**
     * Resolve this match record, recording who resolved it and when.
     */
    public function resolve(int $userId, string $status = 'approved'): void
    {
        $this->update([
            'status'      => $status,
            'resolved_by' => $userId,
            'resolved_at' => now(),
        ]);
    }

    public function isFlagged(): bool
    {
        return $this->status === 'flagged';
    }

    public function isResolved(): bool
    {
        return in_array($this->status, ['resolved', 'approved'], true);
    }

    public function isMatched(): bool
    {
        return $this->match_result === 'matched';
    }
}
