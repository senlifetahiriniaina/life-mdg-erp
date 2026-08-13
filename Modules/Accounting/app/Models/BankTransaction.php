<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Database\Factories\BankTransactionFactory;

/**
 * @property int $id
 * @property int $statement_id
 * @property Carbon $transaction_date
 * @property string $description
 * @property string $amount
 * @property string|null $reference
 * @property string $status
 * @property int|null $matched_entry_id
 * @property Carbon|null $matched_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BankStatement $statement
 */
class BankTransaction extends Model
{
    use HasFactory;

    protected static function newFactory(): BankTransactionFactory
    {
        return BankTransactionFactory::new();
    }

    protected $table = 'acc_bank_transactions';

    protected $fillable = [
        'statement_id',
        'transaction_date',
        'description',
        'amount',
        'reference',
        'status',
        'matched_entry_id',
        'matched_at',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'encrypted:decimal:4',
        'matched_at' => 'datetime',
    ];

    // ─── Relations ───────────────────────────────────────────────────────────

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'statement_id');
    }

    // ─── Business Methods ────────────────────────────────────────────────────

    public function isCredit(): bool
    {
        return (float) $this->amount > 0;
    }

    public function isDebit(): bool
    {
        return (float) $this->amount < 0;
    }

    public function isMatched(): bool
    {
        return $this->status === 'matched';
    }

    public function match(int $entryId): void
    {
        $this->update([
            'status' => 'matched',
            'matched_entry_id' => $entryId,
            'matched_at' => now(),
        ]);
    }

    public function ignore(): void
    {
        $this->update(['status' => 'ignored']);
    }

    public function absoluteAmount(): float
    {
        return abs((float) $this->amount);
    }
}
