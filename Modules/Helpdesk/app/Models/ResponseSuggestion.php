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
 * @property int    $template_id
 * @property int    $variant_id
 * @property string $suggested_response
 * @property string $suggestion_reason
 * @property float  $relevance_score
 * @property float  $confidence
 * @property array  $matching_factors
 * @property bool   $used
 * @property bool   $accepted
 * @property string $modified_response
 * @property bool   $modification_significant
 * @property float  $feedback_rating
 * @property string $feedback_type
 * @property string $feedback_notes
 * @property \Illuminate\Support\Carbon|null $suggested_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ResponseSuggestion extends Model
{
    use HasFactory, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_response_suggestions';

    protected $fillable = [
        'ticket_id',
        'template_id',
        'variant_id',
        'suggested_response',
        'suggestion_reason',
        'relevance_score',
        'confidence',
        'matching_factors',
        'used',
        'accepted',
        'modified_response',
        'modification_significant',
        'feedback_rating',
        'feedback_type',
        'feedback_notes',
        'suggested_at',
    ];

    protected $casts = [
        'relevance_score' => 'decimal:4',
        'confidence' => 'decimal:4',
        'used' => 'boolean',
        'accepted' => 'boolean',
        'modification_significant' => 'boolean',
        'feedback_rating' => 'decimal:2',
        'matching_factors' => 'json',
        'suggested_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ResponseTemplate::class, 'template_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(AIResponseVariant::class, 'variant_id');
    }

    public function wasAccepted(): bool
    {
        return $this->accepted === true;
    }

    public function wasRejected(): bool
    {
        return $this->accepted === false;
    }

    public function isSuggestionRelevant(): bool
    {
        return $this->relevance_score >= 0.7 && $this->confidence >= 0.7;
    }

    public function recordFeedback(string $type, ?float $rating = null, ?string $notes = null): void
    {
        $this->update([
            'feedback_type' => $type,
            'feedback_rating' => $rating,
            'feedback_notes' => $notes,
        ]);
    }
}
