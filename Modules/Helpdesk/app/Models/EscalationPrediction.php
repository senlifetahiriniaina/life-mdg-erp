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
 * @property int    $escalation_model_id
 * @property float  $urgency_score
 * @property float  $escalation_probability
 * @property float  $confidence
 * @property string $recommended_action
 * @property string $escalation_level
 * @property int    $estimated_resolution_hours
 * @property array  $contributing_factors
 * @property string $status
 * @property bool   $escalated
 * @property \Illuminate\Support\Carbon|null $escalated_at
 * @property string $notes
 * @property \Illuminate\Support\Carbon|null $predicted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class EscalationPrediction extends Model
{
    use HasFactory, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_escalation_predictions';

    protected $fillable = [
        'ticket_id',
        'escalation_model_id',
        'urgency_score',
        'escalation_probability',
        'confidence',
        'recommended_action',
        'escalation_level',
        'estimated_resolution_hours',
        'contributing_factors',
        'status',
        'escalated',
        'escalated_at',
        'notes',
        'predicted_at',
    ];

    protected $casts = [
        'urgency_score' => 'decimal:4',
        'escalation_probability' => 'decimal:4',
        'confidence' => 'decimal:4',
        'escalated' => 'boolean',
        'contributing_factors' => 'json',
        'escalated_at' => 'datetime',
        'predicted_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function escalationModel(): BelongsTo
    {
        return $this->belongsTo(EscalationModel::class, 'escalation_model_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(EscalationHistory::class, 'escalation_prediction_id');
    }

    public function requiresEscalation(): bool
    {
        return $this->escalation_probability >= 0.7;
    }

    public function markEscalated(): void
    {
        $this->update([
            'escalated' => true,
            'escalated_at' => now(),
            'status' => 'acted_upon',
        ]);
    }

    public function isHighUrgency(): bool
    {
        return $this->urgency_score >= 0.7;
    }
}
