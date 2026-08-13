<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use App\Traits\AuditableActions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reconciliation extends Model
{
    use AuditableActions, HasFactory;

    protected $table = 'acc_reconciliations';

    protected $auditableFields = ['status', 'bank_statement_balance', 'system_balance', 'difference_amount', 'approved_by_id', 'rejected_by_id'];
    protected $auditModule = 'Accounting';

    protected $fillable = [
        'bank_account_id',
        'reconciliation_date',
        'bank_statement_balance',
        'system_balance',
        'difference_amount',
        'status',
        'reconciled_at',
        'reconciled_by_id',
        'submitted_at',
        'submitted_by_id',
        'approved_at',
        'approved_by_id',
        'approval_notes',
        'rejected_at',
        'rejected_by_id',
        'rejection_reason',
        'auto_approved_at',
    ];

    protected $casts = [
        'reconciliation_date' => 'date',
        'bank_statement_balance' => 'encrypted:decimal:2',
        'system_balance' => 'encrypted:decimal:2',
        'difference_amount' => 'encrypted:decimal:2',
        'reconciled_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'auto_approved_at' => 'datetime',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reconciled_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'submitted_by_id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'rejected_by_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(ReconciliationMatch::class);
    }

    public function outstandingItems(): HasMany
    {
        return $this->hasMany(OutstandingItem::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(ReconciliationException::class);
    }

    public function isBalanced(): bool
    {
        return abs((float) $this->difference_amount) < 0.01;
    }

    public function isReconciled(): bool
    {
        return $this->status === 'reconciled';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
