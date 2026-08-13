<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsolidationEntry extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'consolidation_entries';

    protected $fillable = [
        'consolidation_period_id',
        'company_id',
        'gl_account_id',
        'opening_balance',
        'debit_amount',
        'credit_amount',
        'closing_balance',
        'consolidation_adjustment',
        'consolidated_amount',
        'notes',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'consolidation_adjustment' => 'decimal:2',
        'consolidated_amount' => 'decimal:2',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(ConsolidationPeriod::class, 'consolidation_period_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class, 'company_id');
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\GlAccount::class, 'gl_account_id');
    }
}
