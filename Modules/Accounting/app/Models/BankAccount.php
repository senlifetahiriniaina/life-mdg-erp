<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use App\Traits\AuditableActions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Database\Factories\BankAccountFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $bank_name
 * @property string $account_number
 * @property string $currency
 * @property string $current_balance
 * @property Carbon|null $last_reconciled_at
 * @property string $last_reconciled_balance
 * @property bool $is_active
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, BankStatement> $statements
 */
class BankAccount extends Model
{
    use AuditableActions, HasFactory;

    protected static function newFactory(): BankAccountFactory
    {
        return BankAccountFactory::new();
    }

    protected $table = 'acc_bank_accounts';

    protected $auditableFields = ['current_balance', 'last_reconciled_balance', 'last_reconciled_at', 'is_active'];
    protected $auditModule = 'Accounting';

    protected $fillable = [
        'name',
        'bank_name',
        'account_number',
        'currency',
        'current_balance',
        'last_reconciled_at',
        'last_reconciled_balance',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'current_balance' => 'encrypted:decimal:4',
        'last_reconciled_balance' => 'encrypted:decimal:4',
        'is_active' => 'boolean',
        'last_reconciled_at' => 'datetime',
        'account_number' => 'encrypted',
        'bank_name' => 'encrypted',
        'notes' => 'encrypted',
    ];

    // ─── Relations ───────────────────────────────────────────────────────────

    /** @return HasMany<BankStatement, self> */
    public function statements(): HasMany
    {
        return $this->hasMany(BankStatement::class, 'bank_account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class, 'bank_account_id');
    }

    public function glAccount(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(GLAccount::class, 'gl_account_id');
    }

    /** @return HasMany<OpenBankingConnection, self> */
    public function openBankingConnections(): HasMany
    {
        return $this->hasMany(OpenBankingConnection::class, 'bank_account_id');
    }

    // ─── Business Methods ────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function unreconciledBalance(): float
    {
        return (float) $this->current_balance - (float) $this->last_reconciled_balance;
    }

    public function updateBalance(float $newBalance): void
    {
        $this->update(['current_balance' => $newBalance]);
    }

    public function markReconciled(float $balance): void
    {
        $this->update([
            'last_reconciled_at' => now(),
            'last_reconciled_balance' => $balance,
        ]);
    }
}
