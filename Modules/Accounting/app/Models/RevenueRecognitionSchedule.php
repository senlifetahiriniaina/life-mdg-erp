<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class RevenueRecognitionSchedule extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'revenue_recognition_schedules';

    protected $fillable = [
        'revenue_contract_id',
        'recognition_date',
        'amount',
        'tax_amount',
        'status',
        'journal_entry_id',
        'gl_account_id',
        'description',
        'recognized_at',
        'asc606_details',
    ];

    protected $casts = [
        'recognition_date' => 'date',
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'recognized_at' => 'datetime',
        'asc606_details' => 'json',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(RevenueContract::class, 'revenue_contract_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\JournalEntry::class, 'journal_entry_id');
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\GlAccount::class, 'gl_account_id');
    }
}
