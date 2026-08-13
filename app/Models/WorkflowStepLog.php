<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkflowStepLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'execution_id',
        'step_name',
        'step_type',
        'input_data',
        'output_data',
        'status',
        'error_message',
        'started_at',
        'completed_at',
        'duration_ms',
    ];

    protected $casts = [
        'input_data' => 'array',
        'output_data' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function execution(): BelongsTo
    {
        return $this->belongsTo(WorkflowExecution::class);
    }

    public function approvalChain(): HasOne
    {
        return $this->hasOne(ApprovalChain::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' || $this->status === 'waiting';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
