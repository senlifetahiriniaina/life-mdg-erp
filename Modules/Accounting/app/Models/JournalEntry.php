<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Accounting\Database\Factories\JournalEntryFactory;

class JournalEntry extends Model
{
    use HasFactory;

    protected $table = 'acc_journal_entries';

    protected $fillable = [
        'entry_number',
        'entry_date',
        'date',
        'gl_account_id',
        'entry_type',
        'amount',
        'reference_type',
        'reference_id',
        'description',
        'status',
        'created_by',
        'contract_id',
        'fiscal_year_id',
        'journal_id',
        'debit_amount',
        'credit_amount',
        'source_type',
        'invoice_id',
        'type',
        'debit',
        'credit',
    ];

    protected $casts = [
        'amount' => 'encrypted:decimal:2',
        'entry_date' => 'date',
        'description' => 'encrypted',
        'entry_number' => 'encrypted',
    ];

    protected static function newFactory()
    {
        return JournalEntryFactory::new();
    }

    // Relations
    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(GLAccount::class, 'gl_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function referenceable(): MorphTo
    {
        return $this->morphTo('referenceable', 'reference_type', 'reference_id');
    }

    public function scopeDebit($query)
    {
        return $query->where('entry_type', 'debit');
    }

    public function scopeCredit($query)
    {
        return $query->where('entry_type', 'credit');
    }
}
