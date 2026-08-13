<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Database\Factories\JournalFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $type
 * @property string|null $currency
 * @property int|null $default_account_id
 * @property bool $is_active
 */
class Journal extends Model
{
    use HasFactory;

    protected static function newFactory(): JournalFactory
    {
        return JournalFactory::new();
    }

    protected $table = 'acc_journals';

    protected $fillable = ['name', 'code', 'type', 'currency', 'default_account_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'journal_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'journal_id');
    }

    public function canBeDeleted(): bool
    {
        return !$this->invoices()->exists() && !$this->entries()->exists();
    }
}
