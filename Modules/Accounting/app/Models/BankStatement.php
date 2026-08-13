<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Database\Factories\BankStatementFactory;

/**
 * @property int $id
 * @property int $bank_account_id
 * @property Carbon $statement_date
 * @property string $opening_balance
 * @property string $closing_balance
 * @property string $status
 * @property int $transaction_count
 * @property int $matched_count
 * @property Carbon|null $reconciled_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BankAccount $bankAccount
 * @property-read Collection<int, BankTransaction> $transactions
 */
class BankStatement extends Model
{
    use HasFactory;

    protected static function newFactory(): BankStatementFactory
    {
        return BankStatementFactory::new();
    }

    protected $table = 'acc_bank_statements';

    protected $fillable = [
        'bank_account_id',
        'statement_date',
        'opening_balance',
        'closing_balance',
        'status',
        'transaction_count',
        'matched_count',
        'reconciled_at',
        'notes',
    ];

    protected $casts = [
        'statement_date' => 'date',
        'opening_balance' => 'encrypted:decimal:4',
        'closing_balance' => 'encrypted:decimal:4',
        'reconciled_at' => 'datetime',
    ];

    // ─── Relations ───────────────────────────────────────────────────────────

    /** @return HasMany<BankTransaction, self> */
    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class, 'statement_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    // ─── Business Methods ────────────────────────────────────────────────────

    public function isReconciled(): bool
    {
        return $this->status === 'reconciled';
    }

    public function netChange(): float
    {
        return (float) $this->closing_balance - (float) $this->opening_balance;
    }

    public function matchRate(): float
    {
        return $this->transaction_count > 0
            ? (float) $this->matched_count / (float) $this->transaction_count * 100
            : 0.0;
    }

    public function reconcile(): void
    {
        $this->update([
            'status' => 'reconciled',
            'reconciled_at' => now(),
        ]);
    }
}
