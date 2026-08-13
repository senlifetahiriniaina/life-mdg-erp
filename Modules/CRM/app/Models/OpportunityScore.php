<?php

namespace Modules\CRM\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CRM\Database\Factories\OpportunityScoreFactory;

/**
 * @property int $id
 * @property int $opportunity_id
 * @property int $total_score
 * @property string|null $grade
 * @property int $engagement_score
 * @property int $fit_score
 * @property int $velocity_score
 * @property int $history_score
 * @property string|null $win_probability
 * @property array<string, mixed>|null $score_breakdown
 * @property array<string, mixed>|null $signals_used
 * @property Carbon|null $scored_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class OpportunityScore extends Model
{
    use HasFactory;

    protected $table = 'crm_opportunity_scores';

    protected $fillable = [
        'opportunity_id',
        'total_score',
        'grade',
        'engagement_score',
        'fit_score',
        'velocity_score',
        'history_score',
        'win_probability',
        'score_breakdown',
        'signals_used',
        'scored_at',
    ];

    protected $casts = [
        'total_score' => 'integer',
        'engagement_score' => 'integer',
        'fit_score' => 'integer',
        'velocity_score' => 'integer',
        'history_score' => 'integer',
        'win_probability' => 'decimal:2',
        'score_breakdown' => 'json',
        'signals_used' => 'json',
        'scored_at' => 'datetime',
    ];

    protected static function newFactory(): OpportunityScoreFactory
    {
        return OpportunityScoreFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (OpportunityScore $score) {
            $score->grade = match (true) {
                $score->total_score >= 80 => 'A',
                $score->total_score >= 65 => 'B',
                $score->total_score >= 50 => 'C',
                $score->total_score >= 35 => 'D',
                default => 'F',
            };
        });
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    public function isHighValue(): bool
    {
        return $this->total_score >= 70;
    }

    public function getRecommendedAction(): string
    {
        return match (true) {
            $this->total_score >= 80 => 'close',
            $this->total_score >= 50 => 'nurture',
            $this->total_score >= 30 => 'qualify',
            default => 'disqualify',
        };
    }
}
