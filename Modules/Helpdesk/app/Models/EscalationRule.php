<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $sla_policy_id
 * @property string $name
 * @property string $trigger_type
 * @property float $trigger_hours
 * @property string $action_type
 * @property array<string,mixed>|null $action_config
 * @property bool $is_active
 * @property int $priority
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EscalationRule extends Model
{
    use HasFactory;
    protected $table = 'hd_escalation_rules';

    protected $fillable = [
        'sla_policy_id',
        'name',
        'trigger_type',
        'trigger_hours',
        'action_type',
        'action_config',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'trigger_hours' => 'float',
        'action_config' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(HelpdeskSlaPolicy::class, 'sla_policy_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(EscalationEvent::class, 'rule_id');
    }
}
