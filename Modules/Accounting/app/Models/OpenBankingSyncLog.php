<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $connection_id
 * @property Carbon $synced_at
 * @property int $transactions_fetched
 * @property string $status
 * @property string|null $error_message
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class OpenBankingSyncLog extends Model
{
    use HasFactory;
    protected $table = 'acc_openbanking_sync_logs';

    protected $fillable = [
        'connection_id',
        'synced_at',
        'transactions_fetched',
        'status',
        'error_message',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
        'transactions_fetched' => 'integer',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(OpenBankingConnection::class, 'connection_id');
    }
}
