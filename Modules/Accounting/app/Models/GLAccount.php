<?php

namespace Modules\Accounting\Models;

use App\Traits\AuditableActions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Accounting\Database\Factories\GLAccountFactory;

class GLAccount extends Model
{
    use AuditableActions, HasFactory, SoftDeletes;

    protected $table = 'acc_gl_accounts';

    protected $auditableFields = ['status', 'balance', 'description'];
    protected $auditModule = 'Accounting';

    protected $fillable = [
        'account_number',
        'account_name',
        'name',
        'account_type',
        'normal_balance',
        'description',
        'balance',
        'status',
    ];

    protected $casts = [
        'balance' => 'encrypted:decimal:2',
        'description' => 'encrypted',
        'account_name' => 'encrypted',
    ];

    protected static function newFactory()
    {
        return GLAccountFactory::new();
    }

    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class, 'gl_account_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'gl_account_id');
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class, 'gl_account_id');
    }

    public function reconciliations()
    {
        return $this->hasMany(Reconciliation::class, 'gl_account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('account_type', $type);
    }

    public function getBalance(): float
    {
        return (float) $this->balance;
    }

    public function getNameAttribute(): ?string
    {
        return $this->getRawOriginal('account_name') !== null
            ? $this->account_name
            : null;
    }

    public function setNameAttribute(?string $value): void
    {
        $this->account_name = $value;
    }

    public function glEntries()
    {
        return $this->hasMany(GLEntry::class, 'gl_account_id');
    }

    public function getDebitBalanceAttribute(): float
    {
        return (float) $this->glEntries()->sum('debit_amount');
    }

    public function getCreditBalanceAttribute(): float
    {
        return (float) $this->glEntries()->sum('credit_amount');
    }
}
