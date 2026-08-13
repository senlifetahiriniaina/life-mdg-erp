<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Accounting\Database\Factories\AccBankFeedTransactionFactory;

/**
 * @property int $id
 * @property int $feed_id
 * @property string $external_id
 * @property Carbon $date
 * @property string $amount
 * @property string $description
 * @property string|null $category
 * @property string|null $merchant
 * @property string $status
 * @property int|null $journal_entry_id
 * @property string|null $ai_category_suggestion
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read AccBankFeed $feed
 */
class AccBankFeedTransaction extends Model
{
    use HasFactory;

    protected static function newFactory(): AccBankFeedTransactionFactory
    {
        return AccBankFeedTransactionFactory::new();
    }

    protected $table = 'acc_bank_feed_transactions';

    protected $fillable = [
        'feed_id',
        'external_id',
        'date',
        'amount',
        'description',
        'category',
        'merchant',
        'status',
        'journal_entry_id',
        'ai_category_suggestion',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    /** @return BelongsTo<AccBankFeed, self> */
    public function feed(): BelongsTo
    {
        return $this->belongsTo(AccBankFeed::class, 'feed_id');
    }
}
