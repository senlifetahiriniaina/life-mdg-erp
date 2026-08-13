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
 * @property float  $wait_time_hours
 * @property float  $sentiment_factor
 * @property float  $issue_complexity_factor
 * @property float  $agent_skill_factor
 * @property float  $customer_vip_factor
 * @property float  $sla_breach_factor
 * @property float  $repeat_issue_factor
 * @property float  $channel_factor
 * @property float  $business_hours_factor
 * @property float  $concurrent_escalations_factor
 * @property float  $total_urgency_score
 * @property array  $factor_breakdown
 * @property \Illuminate\Support\Carbon|null $calculated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class UrgencyFactor extends Model
{
    use HasFactory, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_urgency_factors';

    protected $fillable = [
        'ticket_id',
        'wait_time_hours',
        'sentiment_factor',
        'issue_complexity_factor',
        'agent_skill_factor',
        'customer_vip_factor',
        'sla_breach_factor',
        'repeat_issue_factor',
        'channel_factor',
        'business_hours_factor',
        'concurrent_escalations_factor',
        'total_urgency_score',
        'factor_breakdown',
        'calculated_at',
    ];

    protected $casts = [
        'wait_time_hours' => 'decimal:2',
        'sentiment_factor' => 'decimal:4',
        'issue_complexity_factor' => 'decimal:4',
        'agent_skill_factor' => 'decimal:4',
        'customer_vip_factor' => 'decimal:4',
        'sla_breach_factor' => 'decimal:4',
        'repeat_issue_factor' => 'decimal:4',
        'channel_factor' => 'decimal:4',
        'business_hours_factor' => 'decimal:4',
        'concurrent_escalations_factor' => 'decimal:4',
        'total_urgency_score' => 'decimal:4',
        'factor_breakdown' => 'json',
        'calculated_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function isHighUrgency(): bool
    {
        return $this->total_urgency_score >= 0.7;
    }

    public function isLowUrgency(): bool
    {
        return $this->total_urgency_score < 0.3;
    }

    public function getMostInfluencingFactor(): ?string
    {
        if (!$this->factor_breakdown) {
            return null;
        }

        return collect($this->factor_breakdown)
            ->sortByDesc(function ($value) {
                return abs($value);
            })
            ->keys()
            ->first();
    }

    public function calculateFromComponents(): void
    {
        $this->update([
            'total_urgency_score' => (
                $this->sentiment_factor +
                $this->issue_complexity_factor +
                $this->agent_skill_factor +
                $this->customer_vip_factor +
                $this->sla_breach_factor +
                $this->repeat_issue_factor +
                $this->channel_factor +
                $this->business_hours_factor +
                $this->concurrent_escalations_factor
            ) / 9,
            'calculated_at' => now(),
        ]);
    }
}
