<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'chain_id',
        'approver_id',
        'sequence_order',
        'status',
        'comments',
        'approved_at',
        'due_at',
        'escalated',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'due_at' => 'datetime',
        'escalated' => 'boolean',
    ];

    public function chain(): BelongsTo
    {
        return $this->belongsTo(ApprovalChain::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
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

    public function isOverdue(): bool
    {
        return $this->due_at && $this->due_at->isPast() && $this->isPending();
    }
}
