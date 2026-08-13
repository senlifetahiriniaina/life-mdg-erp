<?php

declare(strict_types=1);

namespace Modules\Projects\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ProjectRisk — project risk register with probability/impact scoring.
 *
 * Risk score = probability_score × impact_score (1–9 range).
 *
 * @property int $id
 * @property int $project_id
 * @property string $title
 * @property string|null $description
 * @property string $status       — open | in_review | mitigated | closed
 * @property string $probability  — low | medium | high
 * @property string $impact       — low | medium | high
 * @property int $probability_score   — 1|2|3
 * @property int $impact_score        — 1|2|3
 * @property string|null $mitigation_plan
 * @property int|null $owner_id
 * @property string|null $due_date
 *
 * Computed:
 * @property-read int $risk_score  — 1–9 (probability × impact)
 */
class ProjectRisk extends Model
{
    use HasFactory, SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'prj_risks';

    protected $fillable = [
        'project_id', 'title', 'description', 'status',
        'probability', 'impact', 'probability_score', 'impact_score',
        'mitigation_plan', 'owner_id', 'due_date',
    ];

    protected $casts = [
        'probability_score' => 'integer',
        'impact_score'      => 'integer',
        'due_date'          => 'date',
    ];

    // -------------------------------------------------------------------------
    // Score map
    // -------------------------------------------------------------------------

    private const SCORE_MAP = [
        'low'    => 1,
        'medium' => 2,
        'high'   => 3,
    ];

    // -------------------------------------------------------------------------
    // Accessors (Phase 49)
    // -------------------------------------------------------------------------

    /**
     * Risk score = probability_score × impact_score.
     * If scores are stored, use them; otherwise derive from probability/impact labels.
     */
    public function getRiskScoreAttribute(): int
    {
        $prob   = (int) ($this->probability_score ?: (self::SCORE_MAP[$this->probability] ?? 1));
        $impact = (int) ($this->impact_score      ?: (self::SCORE_MAP[$this->impact]      ?? 1));

        return $prob * $impact; // 1–9 range
    }

    /**
     * Human-readable risk severity label.
     */
    public function getRiskSeverityAttribute(): string
    {
        $score = $this->getRiskScoreAttribute();
        if ($score >= 7) return 'critical';
        if ($score >= 4) return 'high';
        if ($score >= 2) return 'medium';
        return 'low';
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'in_review']);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
