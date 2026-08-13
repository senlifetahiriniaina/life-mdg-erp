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
 * @property string $skill_category
 * @property string $skill_name
 * @property string $skill_level
 * @property int    $proficiency_score
 * @property int    $tickets_handled_for_skill
 * @property float  $avg_satisfaction_for_skill
 * @property float  $first_contact_resolution_rate_for_skill
 * @property int    $avg_resolution_time_for_skill_minutes
 * @property int    $escalation_count_for_skill
 * @property float  $escalation_rate_for_skill
 * @property int    $skill_improvement_points
 * @property \Illuminate\Support\Carbon|null $skill_certified_at
 * @property \Illuminate\Support\Carbon|null $skill_last_practiced_at
 * @property string $proficiency_trend
 * @property int    $days_since_practice
 * @property bool   $needs_training
 * @property array  $training_recommendations
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AgentSkillAnalysis extends Model
{
    use HasFactory, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_agent_skill_analysis';

    protected $fillable = [
        'agent_id',
        'skill_category',
        'skill_name',
        'skill_level',
        'proficiency_score',
        'tickets_handled_for_skill',
        'avg_satisfaction_for_skill',
        'first_contact_resolution_rate_for_skill',
        'avg_resolution_time_for_skill_minutes',
        'escalation_count_for_skill',
        'escalation_rate_for_skill',
        'skill_improvement_points',
        'skill_certified_at',
        'skill_last_practiced_at',
        'proficiency_trend',
        'days_since_practice',
        'needs_training',
        'training_recommendations',
    ];

    protected $casts = [
        'avg_satisfaction_for_skill' => 'decimal:2',
        'first_contact_resolution_rate_for_skill' => 'decimal:4',
        'escalation_rate_for_skill' => 'decimal:4',
        'needs_training' => 'boolean',
        'skill_certified_at' => 'datetime',
        'skill_last_practiced_at' => 'datetime',
        'training_recommendations' => 'json',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'agent_id');
    }

    public function isExpert(): bool
    {
        return $this->skill_level === 'expert' && $this->proficiency_score >= 90;
    }

    public function isAdvanced(): bool
    {
        return $this->skill_level === 'advanced' && $this->proficiency_score >= 70;
    }

    public function needsCertification(): bool
    {
        return $this->skill_certified_at === null && $this->proficiency_score >= 70;
    }

    public function isImproving(): bool
    {
        return $this->proficiency_trend === 'improving';
    }

    public function isDeclining(): bool
    {
        return $this->proficiency_trend === 'declining';
    }

    public function needsRefresh(): bool
    {
        return $this->days_since_practice && $this->days_since_practice > 90;
    }

    public function getResolutionTimeHours(): float
    {
        return $this->avg_resolution_time_for_skill_minutes ? $this->avg_resolution_time_for_skill_minutes / 60 : 0;
    }
}
