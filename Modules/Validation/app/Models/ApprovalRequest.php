<?php

namespace Modules\Validation\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Validation\Database\Factories\ApprovalRequestFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int $id
 * @property int $workflow_id
 * @property string $approvable_type
 * @property int $approvable_id
 * @property string $status
 * @property int $requested_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class ApprovalRequest extends Model
{
    use HasFactory;
    use RecordsActivity, SoftDeletes;

    protected static function newFactory(): ApprovalRequestFactory
    {
        return ApprovalRequestFactory::new();
    }

    protected $table = 'validation_approval_requests';

    protected static string $auditModule = 'Validation';

    protected $fillable = [
        'workflow_id',
        'approvable_type',
        'approvable_id',
        'status',
        'requested_by',
        'approver_id',
        'approved_by',
        'approved_at',
        'rejected_at',
        'hierarchy_id',
        'current_level',
        'total_levels',
        'escalated_from_id',
        'escalation_reason',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function hierarchy(): BelongsTo
    {
        return $this->belongsTo(ApprovalHierarchy::class, 'hierarchy_id');
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class, 'request_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ApprovalHistory::class, 'request_id')->orderBy('changed_at', 'desc');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
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

    public function approve(User $approver, ?string $comment = null): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        ApprovalAction::create([
            'request_id' => $this->id,
            'approver_id' => $approver->id,
            'action' => 'approved',
            'comment' => $comment,
            'acted_at' => now(),
        ]);

        ApprovalHistory::create([
            'request_id' => $this->id,
            'action' => 'approved',
            'old_status' => 'pending',
            'new_status' => 'approved',
            'changed_by' => $approver->id,
            'changed_at' => now(),
        ]);
    }

    public function reject(User $approver, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'rejected_at' => now(),
        ]);

        ApprovalAction::create([
            'request_id' => $this->id,
            'approver_id' => $approver->id,
            'action' => 'rejected',
            'comment' => $reason,
            'acted_at' => now(),
        ]);

        ApprovalHistory::create([
            'request_id' => $this->id,
            'action' => 'rejected',
            'old_status' => 'pending',
            'new_status' => 'rejected',
            'changed_by' => $approver->id,
            'changed_at' => now(),
        ]);
    }

    public function markCompleted(): void
    {
        if ($this->status === 'approved') {
            $this->update(['status' => 'completed']);
        }
    }
}
