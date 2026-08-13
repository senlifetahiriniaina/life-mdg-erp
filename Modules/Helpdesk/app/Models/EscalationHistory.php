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
 * @property int    $escalation_prediction_id
 * @property float  $predicted_urgency
 * @property float  $predicted_probability
 * @property bool   $prediction_correct
 * @property float  $actual_escalation_probability
 * @property string $actual_action
 * @property bool   $was_escalated
 * @property int    $escalation_delay_minutes
 * @property float  $model_accuracy_impact
 * @property array  $feedback
 * @property string $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class EscalationHistory extends Model
{
    use HasFactory, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_escalation_history';

    protected $fillable = [
        'ticket_id',
        'escalation_prediction_id',
        'predicted_urgency',
        'predicted_probability',
        'prediction_correct',
        'actual_escalation_probability',
        'actual_action',
        'was_escalated',
        'escalation_delay_minutes',
        'model_accuracy_impact',
        'feedback',
        'notes',
    ];

    protected $casts = [
        'predicted_urgency' => 'decimal:4',
        'predicted_probability' => 'decimal:4',
        'actual_escalation_probability' => 'decimal:4',
        'model_accuracy_impact' => 'decimal:4',
        'prediction_correct' => 'boolean',
        'was_escalated' => 'boolean',
        'feedback' => 'json',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function escalationPrediction(): BelongsTo
    {
        return $this->belongsTo(EscalationPrediction::class, 'escalation_prediction_id');
    }

    public function wasPredictionAccurate(): bool
    {
        return $this->prediction_correct === true;
    }

    public function wasFalsePositive(): bool
    {
        return $this->prediction_correct === false && !$this->was_escalated;
    }

    public function wasFalseNegative(): bool
    {
        return $this->prediction_correct === false && $this->was_escalated;
    }
}
