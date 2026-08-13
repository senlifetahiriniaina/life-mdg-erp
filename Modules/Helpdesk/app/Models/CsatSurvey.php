<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Helpdesk\Database\Factories\CsatSurveyFactory;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int|null $campaign_id
 * @property int|null $score
 * @property string|null $comment
 * @property Carbon|null $sent_at
 * @property Carbon|null $responded_at
 * @property int|null $agent_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CsatSurvey extends Model
{
    use HasFactory;

    protected static function newFactory(): CsatSurveyFactory
    {
        return CsatSurveyFactory::new();
    }

    protected $table = 'helpdesk_csat_surveys';

    protected $fillable = [
        'ticket_id',
        'campaign_id',
        'score',
        'comment',
        'sent_at',
        'responded_at',
        'agent_id',
    ];

    protected $casts = [
        'score' => 'integer',
        'sent_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CsatCampaign::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
