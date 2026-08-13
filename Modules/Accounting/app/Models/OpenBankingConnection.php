<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $bank_account_id
 * @property string $provider
 * @property string $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property string|null $requisition_id
 * @property string $status
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class OpenBankingConnection extends Model
{
    use HasFactory;
    protected $table = 'acc_openbanking_connections';

    protected $fillable = [
        'bank_account_id',
        'provider',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'requisition_id',
        'status',
        'created_by',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(OpenBankingSyncLog::class, 'connection_id');
    }
}
