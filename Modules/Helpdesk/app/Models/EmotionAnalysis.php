<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int    $id
 * @property int    $ticket_id
 * @property int    $sentiment_score_id
 * @property float  $anger_score
 * @property float  $frustration_score
 * @property float  $satisfaction_score
 * @property float  $confusion_score
 * @property float  $urgency_score
 * @property float  $disappointment_score
 * @property string $dominant_emotion
 * @property string $emotional_state
 * @property int    $emotional_intensity
 * @property bool   $sentiment_shift_detected
 * @property array  $emotion_sequence
 * @property string $context_notes
 * @property \Illuminate\Support\Carbon|null $analyzed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class EmotionAnalysis extends Model
{
    use HasFactory, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_emotion_analysis';

    protected $fillable = [
        'ticket_id',
        'sentiment_score_id',
        'anger_score',
        'frustration_score',
        'satisfaction_score',
        'confusion_score',
        'urgency_score',
        'disappointment_score',
        'dominant_emotion',
        'emotional_state',
        'emotional_intensity',
        'sentiment_shift_detected',
        'emotion_sequence',
        'context_notes',
        'analyzed_at',
    ];

    protected $casts = [
        'anger_score' => 'decimal:4',
        'frustration_score' => 'decimal:4',
        'satisfaction_score' => 'decimal:4',
        'confusion_score' => 'decimal:4',
        'urgency_score' => 'decimal:4',
        'disappointment_score' => 'decimal:4',
        'sentiment_shift_detected' => 'boolean',
        'emotion_sequence' => 'json',
        'analyzed_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function sentimentScore(): BelongsTo
    {
        return $this->belongsTo(SentimentScore::class, 'sentiment_score_id');
    }

    public function isNegative(): bool
    {
        return in_array($this->emotional_state, ['upset', 'very_upset']);
    }

    public function isPositive(): bool
    {
        return $this->emotional_state === 'satisfied';
    }

    public function getDominantScore(): float
    {
        return max(
            $this->anger_score,
            $this->frustration_score,
            $this->satisfaction_score,
            $this->confusion_score,
            $this->urgency_score,
            $this->disappointment_score
        );
    }

    public function requiresIntervention(): bool
    {
        return $this->emotional_intensity >= 70 || $this->isNegative();
    }
}
