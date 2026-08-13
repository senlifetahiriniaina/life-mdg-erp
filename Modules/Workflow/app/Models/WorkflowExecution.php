<?php

declare(strict_types=1);

namespace Modules\Workflow\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $workflow_id
 * @property array<string,mixed>|null $trigger_data
 * @property string $status
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property string|null $error_message
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class WorkflowExecution extends Model
{
    use HasFactory;
    protected $table = 'wfd_executions';

    protected $fillable = [
        'tenant_id',
        'workflow_id',
        'trigger_data',
        'status',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected $casts = [
        'trigger_data'  => 'array',
        'started_at'    => 'datetime',
        'completed_at'  => 'datetime',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WorkflowExecutionLog::class, 'execution_id');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForWorkflow(Builder $query, int $workflowId): Builder
    {
        return $query->where('workflow_id', $workflowId);
    }
}
