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
 * @property int    $satisfaction_model_id
 * @property int    $predicted_satisfaction_score
 * @property float  $confidence
 * @property string $satisfaction_category
 * @property array  $contributing_factors
 * @property array  $risk_factors
 * @property array  $improvement_suggestions
 * @property string $status
 * @property bool   $prediction_correct
 * @property int    $actual_satisfaction_score
 * @property int    $prediction_error
 * @property \Illuminate\Support\Carbon|null $predicted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SatisfactionPrediction extends Model
{
    use HasFactory, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_satisfaction_predictions';

    protected $fillable = [
        'ticket_id',
        'satisfaction_model_id',
        'predicted_satisfaction_score',
        'confidence',
        'satisfaction_category',
        'contributing_factors',
        'risk_factors',
        'improvement_suggestions',
        'status',
        'prediction_correct',
        'actual_satisfaction_score',
        'prediction_error',
        'predicted_at',
    ];

    protected $casts = [
        'confidence' => 'decimal:4',
        'contributing_factors' => 'json',
        'risk_factors' => 'json',
        'improvement_suggestions' => 'json',
        'prediction_correct' => 'boolean',
        'predicted_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function satisfactionModel(): BelongsTo
    {
        return $this->belongsTo(SatisfactionModel::class, 'satisfaction_model_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(SatisfactionHistory::class, 'satisfaction_prediction_id');
    }

    public function isHighRisk(): bool
    {
        return $this->predicted_satisfaction_score < 60;
    }

    public function isPredictionAccurate(): bool
    {
        return abs($this->prediction_error ?? 0) <= 10;
    }

    public function recordActualScore(int $score): void
    {
        $error = $score - $this->predicted_satisfaction_score;
        $this->update([
            'actual_satisfaction_score' => $score,
            'prediction_error' => $error,
            'prediction_correct' => abs($error) <= 10,
            'status' => 'completed',
        ]);
    }

    public function getSatisfactionCategory(): string
    {
        if ($this->predicted_satisfaction_score <= 20) {
            return 'very_unsatisfied';
        } elseif ($this->predicted_satisfaction_score <= 40) {
            return 'unsatisfied';
        } elseif ($this->predicted_satisfaction_score <= 60) {
            return 'neutral';
        } elseif ($this->predicted_satisfaction_score <= 80) {
            return 'satisfied';
        }

        return 'very_satisfied';
    }
}
