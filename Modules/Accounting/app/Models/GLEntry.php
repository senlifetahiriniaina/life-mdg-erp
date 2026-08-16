<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Database\Factories\GLEntryFactory;

class GLEntry extends Model
{
    use HasFactory;

    protected $table = 'acc_gl_entries';

    protected $fillable = [
        'gl_account_id',
        'entry_date',
        'debit_amount',
        'credit_amount',
        'reference_type',
        'reference_id',
        'description',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'debit_amount' => 'decimal:4',
        'credit_amount' => 'decimal:4',
    ];

    protected static function newFactory(): GLEntryFactory
    {
        return GLEntryFactory::new();
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(GLAccount::class, 'gl_account_id');
    }
}
