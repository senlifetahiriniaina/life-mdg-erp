<?php

namespace Modules\Accounting\Models;

use App\Traits\AuditableActions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Accounting\Database\Factories\InvoiceFactory;
use Modules\Accounting\Models\InvoicePayment;
use Modules\Helpdesk\Traits\HelpdeskLinkable;

/**
 * @property int $id
 * @property int $journal_id
 * @property int $created_by
 * @property string $number
 * @property string $type
 * @property int $partner_id
 * @property string $partner_name
 * @property string $partner_type
 * @property Carbon $invoice_date
 * @property Carbon $due_date
 * @property string $status
 * @property string $currency
 * @property float $exchange_rate
 * @property float $subtotal
 * @property float $tax_amount
 * @property float $total
 * @property float $amount_paid
 * @property float $amount_due
 * @property string $notes
 * @property string $payment_terms
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Invoice extends Model
{
    use AuditableActions, HasFactory, HelpdeskLinkable, SoftDeletes;

    protected $table = 'acc_invoices';

    protected string $auditModule = 'Accounting';

    protected array $auditableFields = ['status', 'amount_paid', 'paid_at'];

    protected $fillable = [
        'journal_id',
        'created_by',
        'number',
        'type',
        'partner_id',
        'customer_id',
        'partner_name',
        'partner_type',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_address',
        'invoice_date',
        'due_date',
        'status',
        'approval_status',
        'currency',
        'exchange_rate',
        'subtotal',
        'tax_amount',
        'total',
        'amount_paid',
        'paid_amount',
        'amount_due',
        'notes',
        'payment_terms',
        'paid_at',
        'sent_at',
    ];

    protected $casts = [
        'subtotal' => 'encrypted:decimal:4',
        'tax_amount' => 'encrypted:decimal:4',
        'total' => 'encrypted:decimal:4',
        'amount_paid' => 'encrypted:decimal:4',
        'amount_due' => 'encrypted:decimal:4',
        'exchange_rate' => 'decimal:6',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'sent_at' => 'datetime',
        'partner_name' => 'encrypted',
        'notes' => 'encrypted',
    ];

    protected static function newFactory()
    {
        return InvoiceFactory::new();
    }

    // Relations
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(GLAccount::class, 'account_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(GLAccount::class, 'account_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'partner_id');
    }

    public function scopeOutstanding($query)
    {
        return $query->where('status', 'received')
            ->whereRaw('amount_paid < total');
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now()->toDateString())
            ->where('status', '!=', 'paid');
    }

    public function scopeUnpaid($query)
    {
        return $query->whereNotIn('status', ['paid', 'cancelled']);
    }

    public function getOutstandingBalance()
    {
        return $this->total - $this->amount_paid;
    }

    public function markAsPaid()
    {
        $this->update([
            'amount_paid' => $this->total,
            'status' => 'paid',
        ]);
    }
}
