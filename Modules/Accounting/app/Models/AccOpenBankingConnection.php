<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Accounting\Database\Factories\AccOpenBankingConnectionFactory;

/**
 * @property int $id
 * @property string $bank_name
 * @property string $bank_code
 * @property string $status
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property Carbon|null $last_synced_at
 * @property array<int,string>|null $external_account_ids
 * @property string|null $error_message
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, AccBankFeed> $feeds
 */
class AccOpenBankingConnection extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected static function newFactory(): AccOpenBankingConnectionFactory
    {
        return AccOpenBankingConnectionFactory::new();
    }

    protected $table = 'acc_open_banking_connections';

    protected $fillable = [
        'bank_name',
        'bank_code',
        'status',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'last_synced_at',
        'external_account_ids',
        'error_message',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'external_account_ids' => 'array',
    ];

    /** @return HasMany<AccBankFeed, self> */
    public function feeds(): HasMany
    {
        return $this->hasMany(AccBankFeed::class, 'connection_id');
    }
}
