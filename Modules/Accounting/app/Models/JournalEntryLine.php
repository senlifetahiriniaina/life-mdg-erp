<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Database\Factories\JournalEntryLineFactory;

/**
 * @property int $id
 * @property int $account_id
 * @property string|null $description
 * @property string $debit
 * @property string $credit
 */
class JournalEntryLine extends Model
{
    use HasFactory;

    protected $table = 'acc_journal_entry_lines';

    protected $fillable = [
        'entry_id', 'account_id', 'description', 'debit', 'credit', 'currency', 'currency_amount',
    ];

    protected $casts = [
        'debit' => 'encrypted:decimal:2',
        'credit' => 'encrypted:decimal:2',
        'currency_amount' => 'encrypted:decimal:2',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    protected static function newFactory()
    {
        return JournalEntryLineFactory::new();
    }
}
