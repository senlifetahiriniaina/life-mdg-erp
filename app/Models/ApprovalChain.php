<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalChain extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'step_log_id',
        'approval_type',
        'approver_sequence',
        'required_approvals',
        'approved_count',
        'started_at',
        'completed_at',
        'escalated_at',
    ];

    protected $casts = [
        'approver_sequence' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'escalated_at' => 'datetime',
    ];

    public function stepLog(): BelongsTo
    {
        return $this->belongsTo(WorkflowStepLog::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 'chain_id');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isPending(): bool
    {
        return $this->completed_at === null;
    }

    public function getNextApprover(): ?Approval
    {
        return $this->approvals()
            ->where('status', 'pending')
            ->orderBy('sequence_order')
            ->first();
    }

    public function markApproved(int $approverId, string $comments = ''): void
    {
        $approval = $this->approvals()
            ->where('approver_id', $approverId)
            ->where('status', 'pending')
            ->first();

        if ($approval) {
            $approval->update([
                'status' => 'approved',
                'comments' => $comments,
                'approved_at' => now(),
            ]);

            $this->increment('approved_count');

            if ($this->isApprovalComplete()) {
                $this->update(['completed_at' => now()]);
            }
        }
    }

    public function markRejected(int $approverId, string $comments = ''): void
    {
        $approval = $this->approvals()
            ->where('approver_id', $approverId)
            ->where('status', 'pending')
            ->first();

        if ($approval) {
            $approval->update([
                'status' => 'rejected',
                'comments' => $comments,
                'approved_at' => now(),
            ]);

            $this->update(['completed_at' => now()]);
        }
    }

    private function isApprovalComplete(): bool
    {
        return match ($this->approval_type) {
            'sequential' => $this->getPendingCount() === 0,
            'parallel' => $this->approved_count >= $this->required_approvals,
            'quorum' => $this->approved_count >= $this->required_approvals,
            default => false,
        };
    }

    private function getPendingCount(): int
    {
        return $this->approvals()
            ->where('status', 'pending')
            ->count();
    }
}
