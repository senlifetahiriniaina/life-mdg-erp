<?php

declare(strict_types=1);

namespace Modules\Workflow\Models\Automation;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $flow_id
 * @property int $tenant_id
 * @property array<string,mixed>|null $trigger_data
 * @property string $status  queued|running|completed|failed|paused
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property array<int,mixed>|null $node_results  [{node_id, output, duration_ms, status}]
 * @property int|null $error_node_id
 * @property int|null $duration_ms
 */
class AutomationExecution extends Model
{
    use HasFactory;
    protected $table = 'automation_executions';

    protected $fillable = [
        'flow_id',
        'flow_key',
        'tenant_id',
        'trigger_data',
        'context',
        'status',
        'started_at',
        'completed_at',
        'ended_at',
        'node_results',
        'error_node_id',
        'duration_ms',
    ];

    protected $casts = [
        'trigger_data' => 'array',
        'context'      => 'array',
        'node_results' => 'array',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'ended_at'     => 'datetime',
        'duration_ms'  => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────────────────────

    public function flow(): BelongsTo
    {
        return $this->belongsTo(AutomationFlow::class, 'flow_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────────

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['completed', 'failed'], true);
    }

    /**
     * Append a node result to the node_results JSON array.
     */
    public function appendNodeResult(int $nodeId, array $output, int $durationMs, string $status): void
    {
        $results = $this->node_results ?? [];
        $results[] = [
            'node_id'     => $nodeId,
            'output'      => $output,
            'duration_ms' => $durationMs,
            'status'      => $status,
            'timestamp'   => now()->toIso8601String(),
        ];
        $this->node_results = $results;
        $this->save();
    }

    public function markCompleted(): void
    {
        $this->update([
            'status'       => 'completed',
            'completed_at' => now(),
            'duration_ms'  => $this->started_at
                ? (int) $this->started_at->diffInMilliseconds(now())
                : null,
        ]);
    }

    public function markFailed(?int $errorNodeId = null): void
    {
        $this->update([
            'status'        => 'failed',
            'completed_at'  => now(),
            'error_node_id' => $errorNodeId,
            'duration_ms'   => $this->started_at
                ? (int) $this->started_at->diffInMilliseconds(now())
                : null,
        ]);
    }
}
