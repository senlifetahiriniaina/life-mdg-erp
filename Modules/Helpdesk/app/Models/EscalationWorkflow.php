<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int    $id
 * @property string $name
 * @property string $description
 * @property string $escalation_level
 * @property int    $min_urgency_score
 * @property int    $approval_required
 * @property array  $approval_roles
 * @property int    $escalation_time_minutes
 * @property string $next_level
 * @property bool   $send_notifications
 * @property array  $notification_recipients
 * @property string $notification_template
 * @property array  $reassignment_rules
 * @property string $priority_level
 * @property bool   $is_active
 * @property int    $order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class EscalationWorkflow extends Model
{
    use HasFactory, SoftDeletes, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_escalation_workflows';

    protected $fillable = [
        'name',
        'description',
        'escalation_level',
        'min_urgency_score',
        'approval_required',
        'approval_roles',
        'escalation_time_minutes',
        'next_level',
        'send_notifications',
        'notification_recipients',
        'notification_template',
        'reassignment_rules',
        'priority_level',
        'is_active',
        'order',
    ];

    protected $casts = [
        'send_notifications' => 'boolean',
        'is_active' => 'boolean',
        'approval_roles' => 'json',
        'notification_recipients' => 'json',
        'reassignment_rules' => 'json',
    ];

    public function canApplyTo(float $urgencyScore): bool
    {
        return $this->is_active && $urgencyScore >= $this->min_urgency_score;
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function requiresApproval(): bool
    {
        return $this->approval_required > 0;
    }

    public function getNextWorkflow(): ?EscalationWorkflow
    {
        if (!$this->next_level) {
            return null;
        }

        return static::where('escalation_level', $this->next_level)
            ->where('is_active', true)
            ->first();
    }
}
