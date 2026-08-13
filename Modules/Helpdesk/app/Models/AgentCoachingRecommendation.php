<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int    $id
 * @property int    $agent_id
 * @property int    $generated_by
 * @property string $recommendation_type
 * @property string $priority
 * @property string $description
 * @property string $target_metric
 * @property int    $current_performance
 * @property int    $target_performance
 * @property array  $recommended_actions
 * @property string $training_program
 * @property array  $resources
 * @property date   $target_completion_date
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $acknowledged_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property float  $expected_improvement
 * @property float  $actual_improvement
 * @property string $feedback_notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AgentCoachingRecommendation extends Model
{
    use HasFactory, RecordsActivity, SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_agent_coaching_recommendations';

    protected $fillable = [
        'agent_id',
        'generated_by',
        'recommendation_type',
        'priority',
        'description',
        'target_metric',
        'current_performance',
        'target_performance',
        'recommended_actions',
        'training_program',
        'resources',
        'target_completion_date',
        'status',
        'acknowledged_at',
        'completed_at',
        'expected_improvement',
        'actual_improvement',
        'feedback_notes',
    ];

    protected $casts = [
        'recommended_actions' => 'json',
        'resources' => 'json',
        'target_completion_date' => 'date',
        'acknowledged_at' => 'datetime',
        'completed_at' => 'datetime',
        'expected_improvement' => 'decimal:4',
        'actual_improvement' => 'decimal:4',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'agent_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'generated_by');
    }

    public function isCritical(): bool
    {
        return $this->priority === 'critical';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAcknowledged(): bool
    {
        return $this->status === 'acknowledged' && $this->acknowledged_at !== null;
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed' && $this->completed_at !== null;
    }

    public function isOverdue(): bool
    {
        return $this->target_completion_date && $this->target_completion_date < now()->toDateString() && !$this->isCompleted();
    }

    public function acknowledge(): void
    {
        $this->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
        ]);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function getPerformanceGap(): int
    {
        return $this->target_performance - ($this->current_performance ?? 0);
    }
}
