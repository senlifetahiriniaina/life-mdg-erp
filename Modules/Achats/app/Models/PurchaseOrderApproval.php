<?php

declare(strict_types=1);

namespace Modules\Achats\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * @property int $id
 * @property int $purchase_order_id
 * @property int $approver_id
 * @property string $approval_level
 * @property string $status
 * @property string|null $rejection_reason
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PurchaseOrderApproval extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'achats_purchase_order_approvals';

    protected $fillable = [
        'purchase_order_id',
        'approver_id',
        'approval_level',
        'status',
        'rejection_reason',
        'notes',
    ];

    protected $casts = [
        'purchase_order_id' => 'integer',
        'approver_id'       => 'integer',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approver_id');
    }

    /**
     * Mark this approval record as approved.
     */
    public function approve(): void
    {
        $this->update(['status' => 'approved']);
    }

    /**
     * Mark this approval record as rejected with a reason.
     */
    public function reject(string $reason): void
    {
        $this->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
