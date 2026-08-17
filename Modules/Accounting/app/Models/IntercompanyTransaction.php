<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Accounting\Database\Factories\IntercompanyTransactionFactory;

/**
 * @property int $id
 * @property int $from_company_id
 * @property int $to_company_id
 * @property int|null $journal_entry_id
 * @property \Carbon\Carbon $transaction_date
 * @property string $amount
 * @property string $currency
 * @property string|null $description
 * @property bool $is_eliminated
 * @property \Carbon\Carbon|null $eliminated_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read Company $fromCompany
 * @property-read Company $toCompany
 * @property-read JournalEntry|null $journalEntry
 */
class IntercompanyTransaction extends Model
{
    use HasFactory;

    protected $table = 'acc_intercompany_transactions';

    protected static function newFactory(): IntercompanyTransactionFactory
    {
        return IntercompanyTransactionFactory::new();
    }

    protected $fillable = [
        'from_company_id',
        'to_company_id',
        'journal_entry_id',
        'consolidation_group_id',
        'transaction_type',
        'reference_number',
        'exchange_rate',
        'elimination_notes',
        'related_transaction_id',
        'transaction_date',
        'amount',
        'currency',
        'description',
        'is_eliminated',
        'eliminated_at',
    ];

    protected $casts = [
        'amount' => 'encrypted:decimal:2',
        'transaction_date' => 'date',
        'is_eliminated' => 'boolean',
        'eliminated_at' => 'datetime',
    ];

    public function fromCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'from_company_id');
    }

    public function toCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'to_company_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function isEliminated(): bool
    {
        return $this->is_eliminated;
    }

    public function eliminate(): self
    {
        $this->update([
            'is_eliminated' => true,
            'eliminated_at' => Carbon::now(),
        ]);

        return $this;
    }
}
