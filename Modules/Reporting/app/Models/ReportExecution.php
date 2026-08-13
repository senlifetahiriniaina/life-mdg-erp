<?php

declare(strict_types=1);

namespace Modules\Reporting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Traits\AuditableActions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $report_definition_id
 * @property int $executed_by
 * @property array<string,mixed>|null $parameters
 * @property string $status
 * @property int|null $result_count
 * @property array<mixed>|null $result_data
 * @property string|null $file_path
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property string|null $error_message
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class ReportExecution extends Model
{
    use AuditableActions;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'report_executions';

    protected $fillable = [
        'tenant_id',
        'report_definition_id',
        'executed_by',
        'parameters',
        'status',
        'result_count',
        'result_data',
        'file_path',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected $casts = [
        'parameters'   => 'array',
        'result_data'  => 'array',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ReportDefinition::class, 'report_definition_id');
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'executed_by');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function hasFile(): bool
    {
        return $this->file_path !== null;
    }

    public function getDurationSeconds(): ?float
    {
        if ($this->started_at === null || $this->completed_at === null) {
            return null;
        }

        return $this->completed_at->diffInSeconds($this->started_at);
    }
}
