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
 * @property int    $resolution_time_minutes
 * @property float  $resolution_time_factor
 * @property int    $first_contact_resolution
 * @property string $agent_professionalism
 * @property float  $agent_professionalism_factor
 * @property string $agent_friendliness
 * @property float  $agent_friendliness_factor
 * @property string $agent_knowledge_level
 * @property float  $agent_knowledge_factor
 * @property string $communication_quality
 * @property float  $communication_factor
 * @property string $problem_understanding
 * @property float  $problem_understanding_factor
 * @property string $solution_effectiveness
 * @property float  $solution_effectiveness_factor
 * @property bool   $customer_expectation_met
 * @property float  $expectation_factor
 * @property int    $follow_up_quality_rating
 * @property float  $follow_up_factor
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SatisfactionFactor extends Model
{
    use HasFactory, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_satisfaction_factors';

    protected $fillable = [
        'ticket_id',
        'resolution_time_minutes',
        'resolution_time_factor',
        'first_contact_resolution',
        'agent_professionalism',
        'agent_professionalism_factor',
        'agent_friendliness',
        'agent_friendliness_factor',
        'agent_knowledge_level',
        'agent_knowledge_factor',
        'communication_quality',
        'communication_factor',
        'problem_understanding',
        'problem_understanding_factor',
        'solution_effectiveness',
        'solution_effectiveness_factor',
        'customer_expectation_met',
        'expectation_factor',
        'follow_up_quality_rating',
        'follow_up_factor',
    ];

    protected $casts = [
        'resolution_time_factor' => 'decimal:4',
        'agent_professionalism_factor' => 'decimal:4',
        'agent_friendliness_factor' => 'decimal:4',
        'agent_knowledge_factor' => 'decimal:4',
        'communication_factor' => 'decimal:4',
        'problem_understanding_factor' => 'decimal:4',
        'solution_effectiveness_factor' => 'decimal:4',
        'expectation_factor' => 'decimal:4',
        'follow_up_factor' => 'decimal:4',
        'customer_expectation_met' => 'boolean',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function getStrongestFactor(): string
    {
        $factors = [
            'resolution_time' => $this->resolution_time_factor,
            'professionalism' => $this->agent_professionalism_factor,
            'friendliness' => $this->agent_friendliness_factor,
            'knowledge' => $this->agent_knowledge_factor,
            'communication' => $this->communication_factor,
            'understanding' => $this->problem_understanding_factor,
            'solution' => $this->solution_effectiveness_factor,
            'expectations' => $this->expectation_factor,
            'follow_up' => $this->follow_up_factor,
        ];

        return collect($factors)
            ->sortByDesc(function ($value) {
                return abs($value);
            })
            ->keys()
            ->first() ?? 'unknown';
    }

    public function getWeakestFactor(): string
    {
        $factors = [
            'resolution_time' => $this->resolution_time_factor,
            'professionalism' => $this->agent_professionalism_factor,
            'friendliness' => $this->agent_friendliness_factor,
            'knowledge' => $this->agent_knowledge_factor,
            'communication' => $this->communication_factor,
            'understanding' => $this->problem_understanding_factor,
            'solution' => $this->solution_effectiveness_factor,
            'expectations' => $this->expectation_factor,
            'follow_up' => $this->follow_up_factor,
        ];

        return collect($factors)
            ->sortBy(function ($value) {
                return $value;
            })
            ->keys()
            ->first() ?? 'unknown';
    }

    public function getOverallScore(): float
    {
        return (
            $this->resolution_time_factor +
            $this->agent_professionalism_factor +
            $this->agent_friendliness_factor +
            $this->agent_knowledge_factor +
            $this->communication_factor +
            $this->problem_understanding_factor +
            $this->solution_effectiveness_factor +
            $this->expectation_factor +
            $this->follow_up_factor
        ) / 9;
    }
}
