<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workflow_id
 * @property string $subject_type
 * @property int $subject_id
 * @property int $current_step
 * @property string $status
 * @property int|null $initiated_by
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ApprovalInstance extends Model
{
    use HasFactory;
    protected $table = 'core_approval_instances';

    protected $fillable = [
        'workflow_id',
        'subject_type',
        'subject_id',
        'current_step',
        'status',
        'initiated_by',
        'completed_at',
        'escalated_to',
        'escalation_reason',
        'tenant_id',
    ];

    protected $casts = [
        'current_step' => 'integer',
        'subject_id' => 'integer',
        'completed_at' => 'datetime',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class, 'instance_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['approved', 'rejected', 'cancelled'], true);
    }
}
