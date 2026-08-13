<?php

declare(strict_types=1);

namespace Modules\Workflow\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Execution log for a single trigger-based workflow run.
 *
 * @property int                    $id
 * @property int                    $workflow_definition_id
 * @property int                    $tenant_id
 * @property string                 $trigger_key
 * @property array<string,mixed>|null $context_snapshot
 * @property string                 $status              pending|running|completed|failed
 * @property \Carbon\Carbon|null    $started_at
 * @property \Carbon\Carbon|null    $completed_at
 * @property array<int,array>|null  $result_log
 * @property string|null            $error_message
 * @property \Carbon\Carbon|null    $created_at
 * @property \Carbon\Carbon|null    $updated_at
 */
class WorkflowChainExecution extends Model
{
    use HasFactory;

    protected $table = 'workflow_chain_executions';

    protected $fillable = [
        'workflow_definition_id',
        'tenant_id',
        'trigger_key',
        'context_snapshot',
        'status',
        'started_at',
        'completed_at',
        'result_log',
        'error_message',
    ];

    protected $casts = [
        'context_snapshot' => 'array',
        'result_log'       => 'array',
        'started_at'       => 'datetime',
        'completed_at'     => 'datetime',
    ];

    // ── Computed ──────────────────────────────────────────────────────────────

    /**
     * Duration in milliseconds, null if not yet completed.
     */
    public function getDurationMsAttribute(): ?int
    {
        if ($this->started_at && $this->completed_at) {
            return (int) $this->started_at->diffInMilliseconds($this->completed_at);
        }
        return null;
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowChainDefinition::class, 'workflow_definition_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowExecutionStep::class, 'execution_id')->orderBy('step_index');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }
}
