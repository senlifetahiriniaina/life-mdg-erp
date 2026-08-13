<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Accounting\Database\Factories\AccBankFeedFactory;

/**
 * @property int $id
 * @property int $connection_id
 * @property string $external_account_id
 * @property string $account_name
 * @property string|null $iban
 * @property string $currency
 * @property string|null $balance
 * @property Carbon|null $last_transaction_date
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read AccOpenBankingConnection $connection
 * @property-read Collection<int, AccBankFeedTransaction> $transactions
 */
class AccBankFeed extends Model
{
    use HasFactory;

    protected static function newFactory(): AccBankFeedFactory
    {
        return AccBankFeedFactory::new();
    }

    protected $table = 'acc_bank_feeds';

    protected $fillable = [
        'connection_id',
        'external_account_id',
        'account_name',
        'iban',
        'currency',
        'balance',
        'last_transaction_date',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'last_transaction_date' => 'date',
    ];

    /** @return BelongsTo<AccOpenBankingConnection, self> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(AccOpenBankingConnection::class, 'connection_id');
    }

    /** @return HasMany<AccBankFeedTransaction, self> */
    public function transactions(): HasMany
    {
        return $this->hasMany(AccBankFeedTransaction::class, 'feed_id');
    }
}
