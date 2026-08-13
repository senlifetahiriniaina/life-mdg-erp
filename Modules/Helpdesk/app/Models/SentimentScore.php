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
 * @property int    $ticket_id
 * @property int    $sentiment_model_id
 * @property string $language_detected
 * @property string $sentiment
 * @property float  $positive_score
 * @property float  $negative_score
 * @property float  $neutral_score
 * @property float  $confidence
 * @property string $analyzed_text
 * @property array  $tokens
 * @property string $status
 * @property string $error_message
 * @property \Illuminate\Support\Carbon|null $analyzed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SentimentScore extends Model
{
    use HasFactory, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_sentiment_scores';

    protected $fillable = [
        'ticket_id',
        'sentiment_model_id',
        'language_detected',
        'sentiment',
        'positive_score',
        'negative_score',
        'neutral_score',
        'confidence',
        'analyzed_text',
        'tokens',
        'status',
        'error_message',
        'analyzed_at',
    ];

    protected $casts = [
        'positive_score' => 'decimal:4',
        'negative_score' => 'decimal:4',
        'neutral_score' => 'decimal:4',
        'confidence' => 'decimal:4',
        'tokens' => 'json',
        'analyzed_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function sentimentModel(): BelongsTo
    {
        return $this->belongsTo(SentimentModel::class, 'sentiment_model_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(SentimentHistory::class, 'sentiment_score_id');
    }

    public function emotionAnalysis(): HasMany
    {
        return $this->hasMany(EmotionAnalysis::class, 'sentiment_score_id');
    }

    public function isDominated(string $sentiment): bool
    {
        return $this->sentiment === $sentiment;
    }

    public function isHighConfidence(float $threshold = 0.8): bool
    {
        return $this->confidence >= $threshold;
    }

    public function getDominantScore(): float
    {
        return max(
            $this->positive_score,
            $this->negative_score,
            $this->neutral_score
        );
    }
}
