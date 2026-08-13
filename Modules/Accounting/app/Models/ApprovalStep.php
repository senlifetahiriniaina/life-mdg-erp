<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalStep extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'accounting_approval_steps';

    protected $fillable = [
        'approval_id',
        'level',
        'required_role',
        'approver_id',
        'approved_at',
        'action',
        'comment',
        'threshold_amount',
    ];

    protected $casts = [
        'approved_at'      => 'datetime',
        'threshold_amount' => 'decimal:2',
        'level'            => 'integer',
    ];

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function approval(): BelongsTo
    {
        return $this->belongsTo(InvoiceApproval::class, 'approval_id');
    }
}
