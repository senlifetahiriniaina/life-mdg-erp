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
 * @property int|null $rejected_by
 * @property Carbon|null $rejected_at
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
        'rejected_by',
        'rejected_at',
        'created_by',
        // Chantier 19: this module had no company scoping at all.
        'company_id',
        // Chantier 22 (volet B — cycle acompte/solde).
        'deposit_percent',
        'deposit_required_amount',
        'deposit_invoice_id',
        'balance_invoice_id',
        // Chantier 24 (volet D — traçabilité bout-en-bout). Lien souple,
        // pas de contrainte FK — voir la migration pour le rationnel.
        'production_order_id',
    ];

    protected $casts = [
        'order_date' => 'date',
        'delivery_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'subtotal' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'shipping_cost' => 'decimal:4',
        'total' => 'decimal:4',
        'deposit_percent' => 'decimal:2',
        'deposit_required_amount' => 'decimal:2',
    ];

    protected $appends = ['payment_stage'];

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

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Chantier 31: a plain, explicit-value HasOne rather than
     * morphOne(ApprovalRequest::class, 'approvable') — confirmed empirically
     * (php artisan tinker) that the latter ALWAYS resolved to null. Root
     * cause: Modules\Validation\Providers\ValidationServiceProvider AND
     * Modules\Helpdesk\Providers\HelpdeskServiceProvider both register a
     * global Relation::morphMap() alias ('purchase_order' =>
     * PurchaseOrder::class), which makes $this->getMorphClass() — the value
     * every morphOne()/morphMany() relation auto-constrains against —
     * resolve to the alias 'purchase_order' instead of the real class name.
     * But Modules\Validation\Services\ApprovalRequestService::
     * createApprovalRequest() writes 'approvable_type' via a plain
     * get_class($approvable) (bypassing the morph map entirely), so the
     * real stored value is always the raw FQCN
     * 'Modules\Achats\Models\PurchaseOrder' — the exact same raw-FQCN value
     * every other Achats query already filters by
     * (PurchaseOrderController/PurchaseOrderService/ApprovalRoutingService
     * all query `where('approvable_type', PurchaseOrder::class)` directly).
     * Overriding PurchaseOrder::getMorphClass() app-wide to fix this was
     * considered and rejected — this model also uses HelpdeskLinkable's
     * morphMany('source'), whose write path (TicketService::
     * createFromSource(), fixed in an earlier chantier) DOES correctly use
     * the alias-resolving getMorphClass(), so overriding it here would
     * silently re-break that already-fixed, unrelated relation instead.
     * Fixing the true root cause (ApprovalRequestService's writer) is out
     * of this chantier's scope (Modules\Validation). This HasOne matches
     * the real stored data exactly, with zero blast radius outside this one
     * relation.
     */
    public function approval(): HasOne
    {
        return $this->hasOne(ApprovalRequest::class, 'approvable_id')
            ->where('approvable_type', static::class);
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

    public function depositInvoice(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\Invoice::class, 'deposit_invoice_id');
    }

    public function balanceInvoice(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\Invoice::class, 'balance_invoice_id');
    }

    /**
     * Same derivation logic as Modules\Sales\Models\SalesOrder — see its
     * own docblock. Deliberately duplicated rather than shared, matching
     * this app's established precedent of duplicating small per-module
     * logic (e.g. currency conversion) rather than a cross-module trait.
     */
    public function getPaymentStageAttribute(): string
    {
        $deposit = $this->depositInvoice;
        $balance = $this->balanceInvoice;

        if ($balance !== null && (float) $balance->amount_paid >= (float) $balance->total && (float) $balance->total > 0) {
            return 'paid_in_full';
        }
        if ($balance !== null) {
            return 'balance_invoiced';
        }
        if ($deposit !== null && (float) $deposit->amount_paid >= (float) $deposit->total && (float) $deposit->total > 0) {
            return 'deposit_paid';
        }
        if ($deposit !== null) {
            return 'deposit_invoiced';
        }

        return 'none';
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
