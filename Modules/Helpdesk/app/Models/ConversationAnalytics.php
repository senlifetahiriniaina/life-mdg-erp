<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int    $id
 * @property int    $company_id
 * @property int    $ticket_id
 * @property string $channel
 * @property int    $message_count
 * @property int    $response_count
 * @property int|null $first_response_time_seconds
 * @property int|null $avg_response_time_seconds
 * @property int|null $resolution_time_seconds
 * @property float|null $sentiment_score
 * @property float $delivery_rate
 * @property float $read_rate
 * @property float $click_rate
 * @property int $escalations_count
 * @property int $transfers_count
 */
class ConversationAnalytics extends Model
{
    use HasFactory, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'hd_conversation_analytics';

    protected $fillable = [
        'company_id',
        'ticket_id',
        'channel',
        'message_count',
        'response_count',
        'first_response_time_seconds',
        'avg_response_time_seconds',
        'resolution_time_seconds',
        'sentiment_score',
        'delivery_rate',
        'read_rate',
        'click_rate',
        'escalations_count',
        'transfers_count',
    ];

    protected $casts = [
        'message_count' => 'integer',
        'response_count' => 'integer',
        'first_response_time_seconds' => 'integer',
        'avg_response_time_seconds' => 'integer',
        'resolution_time_seconds' => 'integer',
        'sentiment_score' => 'float',
        'delivery_rate' => 'float',
        'read_rate' => 'float',
        'click_rate' => 'float',
        'escalations_count' => 'integer',
        'transfers_count' => 'integer',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
