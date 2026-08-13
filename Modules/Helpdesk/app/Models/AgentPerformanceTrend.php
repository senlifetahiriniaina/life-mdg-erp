<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int    $id
 * @property int    $agent_id
 * @property string $period_type
 * @property date   $period_start_date
 * @property date   $period_end_date
 * @property float  $satisfaction_trend
 * @property float  $resolution_time_trend
 * @property float  $productivity_trend
 * @property float  $quality_trend
 * @property float  $escalation_trend
 * @property array  $trend_summary
 * @property string $performance_direction
 * @property int    $improvement_points
 * @property int    $decline_points
 * @property array  $top_improvements
 * @property array  $areas_needing_improvement
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AgentPerformanceTrend extends Model
{
    use HasFactory, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_agent_performance_trends';

    protected $fillable = [
        'agent_id',
        'period_type',
        'period_start_date',
        'period_end_date',
        'satisfaction_trend',
        'resolution_time_trend',
        'productivity_trend',
        'quality_trend',
        'escalation_trend',
        'trend_summary',
        'performance_direction',
        'improvement_points',
        'decline_points',
        'top_improvements',
        'areas_needing_improvement',
    ];

    protected $casts = [
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'satisfaction_trend' => 'decimal:4',
        'resolution_time_trend' => 'decimal:4',
        'productivity_trend' => 'decimal:4',
        'quality_trend' => 'decimal:4',
        'escalation_trend' => 'decimal:4',
        'trend_summary' => 'json',
        'top_improvements' => 'json',
        'areas_needing_improvement' => 'json',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'agent_id');
    }

    public function isImproving(): bool
    {
        return $this->performance_direction === 'improving';
    }

    public function isDeclining(): bool
    {
        return $this->performance_direction === 'declining';
    }

    public function isStable(): bool
    {
        return $this->performance_direction === 'stable';
    }

    public function getMostImprovedMetric(): ?string
    {
        return $this->top_improvements[0] ?? null;
    }

    public function getMostNeedingImprovement(): ?string
    {
        return $this->areas_needing_improvement[0] ?? null;
    }
}
