<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $bank_account_id
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property string $status
 * @property float $opening_balance
 * @property float|null $closing_balance
 * @property int $created_by
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ReconciliationSession extends Model
{
    use HasFactory;
    protected $table = 'acc_reconciliation_sessions';

    protected $fillable = [
        'bank_account_id',
        'period_start',
        'period_end',
        'status',
        'opening_balance',
        'closing_balance',
        'created_by',
        'completed_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
