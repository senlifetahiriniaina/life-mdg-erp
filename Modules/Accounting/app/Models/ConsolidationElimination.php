<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsolidationElimination extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'consolidation_eliminations';

    protected $fillable = [
        'consolidation_hierarchy_id',
        'consolidation_period_id',
        'elimination_type',
        'gl_account_id',
        'debit_amount',
        'credit_amount',
        'description',
        'calculation_method',
        'is_manual',
    ];

    protected $casts = [
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'calculation_method' => 'json',
        'is_manual' => 'boolean',
    ];

    public function hierarchy(): BelongsTo
    {
        return $this->belongsTo(ConsolidationHierarchy::class, 'consolidation_hierarchy_id');
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\GlAccount::class, 'gl_account_id');
    }
}
