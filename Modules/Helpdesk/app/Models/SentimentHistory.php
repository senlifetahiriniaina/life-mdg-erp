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
 * @property string $sentiment_trend
 * @property float  $sentiment_change
 * @property float  $positive_score
 * @property float  $negative_score
 * @property float  $neutral_score
 * @property string $trigger_event
 * @property int    $message_count
 * @property array  $metrics
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SentimentHistory extends Model
{
    use HasFactory, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_sentiment_history';

    protected $fillable = [
        'ticket_id',
        'sentiment_score_id',
        'sentiment_trend',
        'sentiment_change',
        'positive_score',
        'negative_score',
        'neutral_score',
        'trigger_event',
        'message_count',
        'metrics',
    ];

    protected $casts = [
        'sentiment_change' => 'decimal:4',
        'positive_score' => 'decimal:4',
        'negative_score' => 'decimal:4',
        'neutral_score' => 'decimal:4',
        'metrics' => 'json',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function sentimentScore(): BelongsTo
    {
        return $this->belongsTo(SentimentScore::class, 'sentiment_score_id');
    }

    public function isImproving(): bool
    {
        return $this->sentiment_trend === 'improving';
    }

    public function isDeterirating(): bool
    {
        return $this->sentiment_trend === 'deteriorating';
    }
}
