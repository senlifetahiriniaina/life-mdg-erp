<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int    $id
 * @property int    $team_id
 * @property date   $benchmark_date
 * @property int    $team_size
 * @property float  $avg_satisfaction_rating
 * @property float  $median_satisfaction_rating
 * @property float  $top_performer_satisfaction
 * @property float  $bottom_performer_satisfaction
 * @property int    $avg_resolution_time_minutes
 * @property int    $best_resolution_time_minutes
 * @property int    $worst_resolution_time_minutes
 * @property float  $avg_escalation_rate
 * @property float  $avg_first_contact_resolution_rate
 * @property float  $avg_nps_score
 * @property float  $avg_quality_score
 * @property float  $avg_productivity_score
 * @property int    $top_performer_rank
 * @property int    $bottom_performer_rank
 * @property array  $performance_distribution
 * @property array  $strengths
 * @property array  $improvement_areas
 * @property float  $team_trend
 * @property string $team_performance_rating
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class TeamBenchmarking extends Model
{
    use HasFactory, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_team_benchmarking';

    protected $fillable = [
        'team_id',
        'benchmark_date',
        'team_size',
        'avg_satisfaction_rating',
        'median_satisfaction_rating',
        'top_performer_satisfaction',
        'bottom_performer_satisfaction',
        'avg_resolution_time_minutes',
        'best_resolution_time_minutes',
        'worst_resolution_time_minutes',
        'avg_escalation_rate',
        'avg_first_contact_resolution_rate',
        'avg_nps_score',
        'avg_quality_score',
        'avg_productivity_score',
        'top_performer_rank',
        'bottom_performer_rank',
        'performance_distribution',
        'strengths',
        'improvement_areas',
        'team_trend',
        'team_performance_rating',
    ];

    protected $casts = [
        'benchmark_date' => 'date',
        'avg_satisfaction_rating' => 'decimal:2',
        'median_satisfaction_rating' => 'decimal:2',
        'top_performer_satisfaction' => 'decimal:2',
        'bottom_performer_satisfaction' => 'decimal:2',
        'avg_escalation_rate' => 'decimal:4',
        'avg_first_contact_resolution_rate' => 'decimal:4',
        'avg_nps_score' => 'decimal:2',
        'avg_quality_score' => 'decimal:4',
        'avg_productivity_score' => 'decimal:4',
        'team_trend' => 'decimal:4',
        'performance_distribution' => 'json',
        'strengths' => 'json',
        'improvement_areas' => 'json',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isHighPerforming(): bool
    {
        return $this->team_performance_rating === 'excellent';
    }

    public function isImproving(): bool
    {
        return $this->team_trend > 0;
    }

    public function isDeclining(): bool
    {
        return $this->team_trend < 0;
    }

    public function getPerformanceGap(): float
    {
        return $this->top_performer_satisfaction - ($this->bottom_performer_satisfaction ?? 0);
    }

    public function getAvgResolutionTimeHours(): float
    {
        return $this->avg_resolution_time_minutes ? $this->avg_resolution_time_minutes / 60 : 0;
    }

    public function getTopStrength(): ?string
    {
        return $this->strengths[0] ?? null;
    }

    public function getTopImprovementArea(): ?string
    {
        return $this->improvement_areas[0] ?? null;
    }
}
