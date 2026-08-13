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
 * @property int    $satisfaction_prediction_id
 * @property int    $predicted_score
 * @property int    $actual_score
 * @property int    $prediction_error
 * @property bool   $prediction_accurate
 * @property string $satisfaction_category
 * @property array  $improvement_actions
 * @property bool   $score_improved
 * @property int    $score_improvement_points
 * @property array  $model_feedback
 * @property float  $model_learning_impact
 * @property string $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SatisfactionHistory extends Model
{
    use HasFactory, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_satisfaction_history';

    protected $fillable = [
        'ticket_id',
        'satisfaction_prediction_id',
        'predicted_score',
        'actual_score',
        'prediction_error',
        'prediction_accurate',
        'satisfaction_category',
        'improvement_actions',
        'score_improved',
        'score_improvement_points',
        'model_feedback',
        'model_learning_impact',
        'notes',
    ];

    protected $casts = [
        'prediction_accurate' => 'boolean',
        'score_improved' => 'boolean',
        'improvement_actions' => 'json',
        'model_feedback' => 'json',
        'model_learning_impact' => 'decimal:4',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function satisfactionPrediction(): BelongsTo
    {
        return $this->belongsTo(SatisfactionPrediction::class, 'satisfaction_prediction_id');
    }

    public function wasPredictionAccurate(): bool
    {
        return $this->prediction_accurate === true;
    }

    public function getAccuracyPercentage(): float
    {
        if ($this->actual_score === null) {
            return 0;
        }

        return (1 - (abs($this->prediction_error) / 100)) * 100;
    }
}
