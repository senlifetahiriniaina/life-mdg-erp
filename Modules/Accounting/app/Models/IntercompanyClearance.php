<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class IntercompanyClearance extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'intercompany_clearances';

    protected $fillable = [
        'sending_company_id',
        'receiving_company_id',
        'transaction_date',
        'transaction_type',
        'amount',
        'currency',
        'status',
        'sending_gl_account_id',
        'receiving_gl_account_id',
        'description',
        'due_date',
        'cleared_at',
        'documents',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'cleared_at' => 'datetime',
        'documents' => 'json',
    ];

    public function sendingCompany(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\Company::class, 'sending_company_id');
    }

    public function receivingCompany(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\Company::class, 'receiving_company_id');
    }

    public function sendingGlAccount(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\GlAccount::class, 'sending_gl_account_id');
    }

    public function receivingGlAccount(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\GlAccount::class, 'receiving_gl_account_id');
    }
}
