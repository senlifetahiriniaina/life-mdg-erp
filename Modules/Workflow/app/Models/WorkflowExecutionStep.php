<?php

declare(strict_types=1);

namespace Modules\Workflow\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-step execution record for a workflow chain run.
 *
 * @property int                         $id
 * @property int                         $execution_id
 * @property int                         $tenant_id
 * @property int                         $step_index
 * @property string                      $action_key
 * @property array<string,mixed>|null    $input_context
 * @property array<string,mixed>|null    $output
 * @property string                      $status         pending|running|completed|failed|skipped
 * @property int|null                    $duration_ms
 * @property \Carbon\Carbon|null         $executed_at
 * @property string|null                 $error_message
 * @property \Carbon\Carbon|null         $created_at
 * @property \Carbon\Carbon|null         $updated_at
 */
class WorkflowExecutionStep extends Model
{
    use HasFactory;
    protected $table = 'workflow_execution_steps';

    protected $fillable = [
        'execution_id',
        'tenant_id',
        'step_index',
        'action_key',
        'input_context',
        'output',
        'status',
        'duration_ms',
        'executed_at',
        'error_message',
    ];

    protected $casts = [
        'input_context' => 'array',
        'output'        => 'array',
        'executed_at'   => 'datetime',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function execution(): BelongsTo
    {
        return $this->belongsTo(WorkflowChainExecution::class, 'execution_id');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForExecution(Builder $query, int $executionId): Builder
    {
        return $query->where('execution_id', $executionId);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public function isSuccess(): bool
    {
        return in_array($this->status, ['completed', 'skipped'], true);
    }

    public function durationForHumans(): string
    {
        if ($this->duration_ms === null) {
            return '-';
        }

        return $this->duration_ms < 1000
            ? "{$this->duration_ms}ms"
            : round($this->duration_ms / 1000, 2) . 's';
    }
}
