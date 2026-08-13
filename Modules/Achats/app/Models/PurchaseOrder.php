<?php

namespace Modules\Achats\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Achats\Database\Factories\PurchaseOrderFactory;
use Modules\Core\Traits\RecordsActivity;
use Modules\Helpdesk\Traits\HelpdeskLinkable;
use Modules\Validation\Models\ApprovalRequest;

/**
 * @property int $id
 * @property string $po_number
 * @property int $supplier_id
 * @property string $status
 * @property Carbon $order_date
 * @property Carbon|null $delivery_date
 * @property string $currency
 * @property string $subtotal
 * @property string $tax_amount
 * @property string $shipping_cost
 * @property string $total
 * @property string|null $notes
 * @property int $requested_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class PurchaseOrder extends Model
{
    use HasFactory, HelpdeskLinkable;
    use RecordsActivity, SoftDeletes;

    protected $table = 'achats_purchase_orders';

    protected static string $auditModule = 'Achats';

    protected static function newFactory(): PurchaseOrderFactory
    {
        return PurchaseOrderFactory::new();
    }

    protected $fillable = [
        'po_number',
        'supplier_id',
        'status',
        'order_date',
        'delivery_date',
        'currency',
        'subtotal',
        'tax_amount',
        'shipping_cost',
        'total',
        'notes',
        'requested_by',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'delivery_date' => 'date',
        'approved_at' => 'datetime',
        'subtotal' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'shipping_cost' => 'decimal:4',
        'total' => 'decimal:4',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'purchase_order_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approval(): MorphOne
    {
        return $this->morphOne(ApprovalRequest::class, 'approvable');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(PurchaseReceipt::class, 'purchase_order_id');
    }

    public function accountingMapping(): HasOne
    {
        return $this->hasOne(PoAccountingMapping::class, 'purchase_order_id');
    }

    public function inventoryMappings(): HasMany
    {
        return $this->hasMany(PoInventoryMapping::class, 'purchase_order_id');
    }

    public function budgetAllocation(): HasOne
    {
        return $this->hasOne(PoBudgetAllocation::class, 'purchase_order_id');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', 'submitted');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeReceived(Builder $query): Builder
    {
        return $query->where('status', 'received');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'submitted';
    }

    public function canApprove(): bool
    {
        return $this->status === 'submitted';
    }

    public function calculateTotals(): array
    {
        $subtotal = $this->lines->sum('line_total');
        $tax_amount = $this->lines->sum(function ($line) {
            return $line->line_total * ($line->tax_rate / 100);
        });
        $total = $subtotal + $tax_amount + ($this->shipping_cost ?? 0);

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $tax_amount,
            'total' => $total,
        ];
    }

    public function getLineTotal(): float
    {
        return (float) $this->subtotal;
    }

    public function getTaxTotal(): float
    {
        return (float) $this->tax_amount;
    }
}
