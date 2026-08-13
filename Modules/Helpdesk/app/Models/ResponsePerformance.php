<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int    $id
 * @property int    $template_id
 * @property int    $variant_id
 * @property int    $ticket_id
 * @property string $response_type
 * @property float  $satisfaction_rating
 * @property string $feedback_category
 * @property int    $response_time_seconds
 * @property int    $ticket_resolution_time_minutes
 * @property bool   $issue_resolved
 * @property float  $resolution_effectiveness
 * @property int    $follow_up_count
 * @property bool   $required_escalation
 * @property bool   $required_additional_response
 * @property string $customer_sentiment_after
 * @property array  $metrics
 * @property string $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ResponsePerformance extends Model
{
    use HasFactory, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_response_performance';

    protected $fillable = [
        'template_id',
        'variant_id',
        'ticket_id',
        'response_type',
        'satisfaction_rating',
        'feedback_category',
        'response_time_seconds',
        'ticket_resolution_time_minutes',
        'issue_resolved',
        'resolution_effectiveness',
        'follow_up_count',
        'required_escalation',
        'required_additional_response',
        'customer_sentiment_after',
        'metrics',
        'notes',
    ];

    protected $casts = [
        'satisfaction_rating' => 'decimal:2',
        'resolution_effectiveness' => 'decimal:4',
        'issue_resolved' => 'boolean',
        'required_escalation' => 'boolean',
        'required_additional_response' => 'boolean',
        'metrics' => 'json',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ResponseTemplate::class, 'template_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(AIResponseVariant::class, 'variant_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function wasSatisfactory(): bool
    {
        return $this->satisfaction_rating >= 4;
    }

    public function wasSuccessful(): bool
    {
        return $this->issue_resolved && !$this->required_escalation;
    }

    public function getResolutionTimeHours(): float
    {
        return $this->ticket_resolution_time_minutes ? $this->ticket_resolution_time_minutes / 60 : 0;
    }

    public function getResponseTimeMinutes(): int
    {
        return intval($this->response_time_seconds / 60);
    }
}
