<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                  $id
 * @property int                  $rule_id
 * @property int                  $escalation_level
 * @property string               $trigger_condition
 * @property int                  $trigger_value
 * @property array<string, mixed> $escalation_recipients
 * @property bool                 $is_active
 * @property \Carbon\Carbon       $created_at
 * @property \Carbon\Carbon       $updated_at
 * @property-read AlertRule       $rule
 */
class AlertEscalation extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_alert_escalations';

    protected $fillable = [
        'rule_id',
        'escalation_level',
        'trigger_condition',
        'trigger_value',
        'escalation_recipients',
        'is_active',
    ];

    protected $casts = [
        'escalation_recipients' => 'array',
        'is_active'             => 'boolean',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class);
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function getTriggerConditionLabel(): string
    {
        return match ($this->trigger_condition) {
            'after_time'     => 'After Time',
            'after_attempts' => 'After Attempts',
            default          => $this->trigger_condition,
        };
    }

    public function getEscalationRecipients(): array
    {
        return $this->escalation_recipients ?? [];
    }
}
