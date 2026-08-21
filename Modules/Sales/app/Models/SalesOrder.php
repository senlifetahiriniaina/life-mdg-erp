<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Traits\AuditableActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Helpdesk\Traits\HelpdeskLinkable;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * @property int $id
 * @property int $tenant_id
 * @property string $reference
 * @property int|null $contact_id
 * @property int|null $account_id
 * @property int|null $opportunity_id
 * @property string $status
 * @property string $currency
 * @property float $subtotal
 * @property float $discount_amount
 * @property float $tax_amount
 * @property float $total
 * @property string|null $notes
 * @property array<string,mixed>|null $shipping_address
 * @property \Carbon\Carbon|null $expected_delivery_date
 * @property \Carbon\Carbon|null $confirmed_at
 * @property \Carbon\Carbon|null $cancelled_at
 * @property int $created_by
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class SalesOrder extends Model
{
    use HasFactory;
    use AuditableActions, HelpdeskLinkable, SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'sales_orders';

    protected string $auditModule = 'Sales';
    protected array $auditableFields = ['status', 'total', 'confirmed_at', 'cancelled_at'];

    protected $fillable = [
        'tenant_id',
        'reference',
        'contact_id',
        'account_id',
        'opportunity_id',
        'status',
        'currency',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total',
        'notes',
        'shipping_address',
        'expected_delivery_date',
        'confirmed_at',
        'cancelled_at',
        'created_by',
        // Chantier 26 (volet B — objectifs commerciaux) : le commercial
        // responsable du compte, distinct de created_by (qui a saisi).
        'sales_rep_id',
        // Chantier 22 (volet B — cycle acompte/solde).
        'deposit_percent',
        'deposit_required_amount',
        'deposit_invoice_id',
        'balance_invoice_id',
    ];

    protected $casts = [
        'subtotal'              => 'decimal:2',
        'discount_amount'       => 'decimal:2',
        'tax_amount'            => 'decimal:2',
        'total'                 => 'decimal:2',
        'shipping_address'      => 'array',
        'expected_delivery_date' => 'date',
        'confirmed_at'          => 'datetime',
        'cancelled_at'          => 'datetime',
        'deposit_percent'       => 'decimal:2',
        'deposit_required_amount' => 'decimal:2',
    ];

    protected $appends = ['payment_stage'];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'sales_rep_id');
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
     * Dérivé des 2 factures liées plutôt que stocké — une seule source de
     * vérité (le statut/amount_paid réel de chaque Invoice), pas de
     * double comptabilité à synchroniser. Valeurs : none, deposit_invoiced,
     * deposit_paid, balance_invoiced, paid_in_full.
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

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['cancelled', 'returned']);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['draft', 'confirmed', 'processing'], true);
    }
}
