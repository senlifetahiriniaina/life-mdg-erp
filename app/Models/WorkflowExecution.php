<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowExecution extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'workflow_id',
        'triggered_by',
        'trigger_data',
        'status',
        'context',
        'started_at',
        'completed_at',
        'duration_ms',
        'error_message',
    ];

    protected $casts = [
        'trigger_data' => 'array',
        'context' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function stepLogs(): HasMany
    {
        return $this->hasMany(WorkflowStepLog::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(WorkflowAuditLog::class);
    }

    public function isSuccess(): bool
    {
        return $this->status === 'completed' && !$this->error_message;
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }
}
