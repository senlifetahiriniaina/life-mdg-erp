<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    use HasFactory;

    protected $table = 'acc_expenses';

    protected $fillable = [
        'company_id',
        'expense_number',
        'expense_reference',
        'gl_account_id',
        'employee_id',
        'expense_category_id',
        'category',
        'vendor',
        'receipt_number',
        'expense_date',
        'description',
        'amount',
        'currency',
        'amount_approved',
        'amount_reimbursed',
        'tax_amount',
        'tax_category_id',
        'status',
        'priority',
        'submitted_at',
        'submitted_by_id',
        'approved_at',
        'approved_by_id',
        'rejected_reason',
        'notes',
        'created_by',
        'created_by_id',
        'payment_method',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'encrypted:decimal:2',
        'amount_approved' => 'encrypted:decimal:2',
        'amount_reimbursed' => 'encrypted:decimal:2',
        'tax_amount' => 'encrypted:decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'description' => 'encrypted',
        'expense_reference' => 'string',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\Company::class, 'company_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'employee_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function taxCategory(): BelongsTo
    {
        return $this->belongsTo(TaxCategory::class, 'tax_category_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ExpenseApproval::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(ExpenseReceipt::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function getAmountRemaining(): float
    {
        return (float) $this->amount_approved - (float) $this->amount_reimbursed;
    }

    public function markAsRejected(string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'rejected_reason' => $reason,
        ]);
    }

    public function markAsApproved(float $approvedAmount): void
    {
        $this->update([
            'status' => 'approved',
            'amount_approved' => $approvedAmount,
            'approved_at' => now(),
        ]);
    }

    public function glAccount(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(GLAccount::class, 'gl_account_id');
    }
}
