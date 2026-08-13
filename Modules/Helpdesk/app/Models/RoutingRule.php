<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int    $id
 * @property int    $team_id
 * @property string $name
 * @property string $rule_type
 * @property string $sentiment_trigger
 * @property string $target_queue
 * @property int    $priority_boost
 * @property bool   $requires_specialist
 * @property string $skill_required
 * @property array  $routing_conditions
 * @property string $escalation_path
 * @property int    $max_wait_minutes
 * @property int    $sla_hours_override
 * @property bool   $notify_customer
 * @property string $notification_message
 * @property bool   $is_active
 * @property int    $order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class RoutingRule extends Model
{
    use HasFactory, SoftDeletes, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_routing_rules';

    protected $fillable = [
        'team_id',
        'name',
        'rule_type',
        'sentiment_trigger',
        'target_queue',
        'priority_boost',
        'requires_specialist',
        'skill_required',
        'routing_conditions',
        'escalation_path',
        'max_wait_minutes',
        'sla_hours_override',
        'notify_customer',
        'notification_message',
        'is_active',
        'order',
    ];

    protected $casts = [
        'requires_specialist' => 'boolean',
        'notify_customer' => 'boolean',
        'is_active' => 'boolean',
        'routing_conditions' => 'json',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function shouldRoute(string $sentiment): bool
    {
        return $this->is_active && ($this->sentiment_trigger === null || $this->sentiment_trigger === $sentiment);
    }
}
