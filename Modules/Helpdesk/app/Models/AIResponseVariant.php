<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int    $id
 * @property int    $template_id
 * @property string $variant_type
 * @property string $content
 * @property string $target_sentiment
 * @property string $target_emotion
 * @property string $target_context
 * @property float  $relevance_score
 * @property array  $triggers
 * @property float  $avg_satisfaction_rating
 * @property int    $usage_count
 * @property int    $positive_feedback_count
 * @property int    $negative_feedback_count
 * @property bool   $ai_generated
 * @property string $generation_model
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $generated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AIResponseVariant extends Model
{
    use HasFactory, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_ai_response_variants';

    protected $fillable = [
        'template_id',
        'variant_type',
        'content',
        'target_sentiment',
        'target_emotion',
        'target_context',
        'relevance_score',
        'triggers',
        'avg_satisfaction_rating',
        'usage_count',
        'positive_feedback_count',
        'negative_feedback_count',
        'ai_generated',
        'generation_model',
        'status',
        'generated_at',
    ];

    protected $casts = [
        'relevance_score' => 'decimal:4',
        'avg_satisfaction_rating' => 'decimal:2',
        'ai_generated' => 'boolean',
        'triggers' => 'json',
        'generated_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ResponseTemplate::class, 'template_id');
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(ResponseSuggestion::class, 'variant_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getSatisfactionRate(): float
    {
        $total = $this->positive_feedback_count + $this->negative_feedback_count;
        if ($total === 0) {
            return 0;
        }

        return $this->positive_feedback_count / $total;
    }

    public function matchesSentiment(string $sentiment): bool
    {
        return $this->target_sentiment === null || $this->target_sentiment === $sentiment;
    }

    public function matchesEmotion(string $emotion): bool
    {
        return $this->target_emotion === null || $this->target_emotion === $emotion;
    }

    public function hasHighRelevance(float $threshold = 0.7): bool
    {
        return $this->relevance_score >= $threshold;
    }
}
