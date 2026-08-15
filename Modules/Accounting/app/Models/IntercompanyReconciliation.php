<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class IntercompanyReconciliation extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'intercompany_reconciliations';

    protected $fillable = [
        'company_a_id',
        'company_b_id',
        'reconciliation_date',
        'company_a_balance',
        'company_b_balance',
        'difference',
        'status',
        'reconciliation_notes',
        'reconciled_at',
    ];

    protected $casts = [
        'reconciliation_date' => 'date',
        'company_a_balance' => 'decimal:2',
        'company_b_balance' => 'decimal:2',
        'difference' => 'decimal:2',
        'reconciled_at' => 'datetime',
    ];

    public function companyA(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\Company::class, 'company_a_id');
    }

    public function companyB(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\Company::class, 'company_b_id');
    }
}
