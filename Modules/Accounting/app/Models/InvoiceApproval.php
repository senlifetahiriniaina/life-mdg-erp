<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class InvoiceApproval extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'accounting_invoice_approvals';

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'approval_request_id',
        'invoice_type',
        'invoice_number',
        'amount',
        'currency',
        'status',
        'submitted_by',
        'submitted_at',
        'approval_chain',
        'current_level',
        'rejection_reason',
    ];

    protected $casts = [
        'approval_chain' => 'array',
        'submitted_at'   => 'datetime',
        'amount'         => 'decimal:2',
        'current_level'  => 'integer',
    ];

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class, 'approval_id')->orderBy('level');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public function currentStep(): ?ApprovalStep
    {
        return $this->steps()->where('level', $this->current_level)->first();
    }

    public function isFullyApproved(): bool
    {
        return $this->steps()->where('action', '!=', 'approved')->doesntExist();
    }
}
