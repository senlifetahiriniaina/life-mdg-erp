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
 * @property date   $metric_date
 * @property int    $tickets_handled
 * @property int    $tickets_resolved
 * @property int    $first_contact_resolution_count
 * @property float  $first_contact_resolution_rate
 * @property int    $avg_resolution_time_minutes
 * @property int    $avg_response_time_seconds
 * @property int    $avg_handle_time_seconds
 * @property float  $avg_satisfaction_rating
 * @property int    $satisfaction_survey_responses
 * @property float  $avg_sentiment_improvement
 * @property int    $escalation_count
 * @property float  $escalation_rate
 * @property int    $repeat_contact_count
 * @property float  $repeat_contact_rate
 * @property int    $quality_audit_score
 * @property int    $nps_detractor_count
 * @property int    $nps_passive_count
 * @property int    $nps_promoter_count
 * @property float  $nps_score
 * @property float  $productivity_score
 * @property float  $quality_score
 * @property float  $overall_performance_score
 * @property string $performance_rating
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AgentMetric extends Model
{
    use HasFactory, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_agent_metrics';

    protected $fillable = [
        'agent_id',
        'metric_date',
        'tickets_handled',
        'tickets_resolved',
        'first_contact_resolution_count',
        'first_contact_resolution_rate',
        'avg_resolution_time_minutes',
        'avg_response_time_seconds',
        'avg_handle_time_seconds',
        'avg_satisfaction_rating',
        'satisfaction_survey_responses',
        'avg_sentiment_improvement',
        'escalation_count',
        'escalation_rate',
        'repeat_contact_count',
        'repeat_contact_rate',
        'quality_audit_score',
        'nps_detractor_count',
        'nps_passive_count',
        'nps_promoter_count',
        'nps_score',
        'productivity_score',
        'quality_score',
        'overall_performance_score',
        'performance_rating',
    ];

    protected $casts = [
        'metric_date' => 'date',
        'first_contact_resolution_rate' => 'decimal:4',
        'avg_satisfaction_rating' => 'decimal:2',
        'avg_sentiment_improvement' => 'decimal:4',
        'escalation_rate' => 'decimal:4',
        'repeat_contact_rate' => 'decimal:4',
        'nps_score' => 'decimal:2',
        'productivity_score' => 'decimal:4',
        'quality_score' => 'decimal:4',
        'overall_performance_score' => 'decimal:4',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'agent_id');
    }

    public function isTopPerformer(): bool
    {
        return $this->overall_performance_score >= 0.85;
    }

    public function needsImprovement(): bool
    {
        return $this->overall_performance_score < 0.6;
    }

    public function getProductivityHours(): float
    {
        return $this->avg_handle_time_seconds ? $this->avg_handle_time_seconds / 3600 : 0;
    }

    public function getResponseTimeMinutes(): int
    {
        return intval(($this->avg_response_time_seconds ?? 0) / 60);
    }

    public function getResolutionTimeHours(): float
    {
        return $this->avg_resolution_time_minutes ? $this->avg_resolution_time_minutes / 60 : 0;
    }
}
