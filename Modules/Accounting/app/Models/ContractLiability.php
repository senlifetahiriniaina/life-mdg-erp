<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class ContractLiability extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'contract_liabilities';

    protected $fillable = [
        'revenue_contract_id',
        'liability_amount',
        'recognized_amount',
        'remaining_amount',
        'status',
        'deferred_revenue_account_id',
        'expected_recognition_date',
    ];

    protected $casts = [
        'liability_amount' => 'decimal:2',
        'recognized_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'expected_recognition_date' => 'date',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(RevenueContract::class, 'revenue_contract_id');
    }

    public function deferredRevenueAccount(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\GlAccount::class, 'deferred_revenue_account_id');
    }
}
