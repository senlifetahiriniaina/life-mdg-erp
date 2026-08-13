<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int            $id
 * @property int            $rule_id
 * @property string         $recipient_type
 * @property string         $recipient_value
 * @property string         $notification_channel
 * @property bool           $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read AlertRule $rule
 */
class AlertRecipient extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_alert_recipients';

    protected $fillable = [
        'rule_id',
        'recipient_type',
        'recipient_value',
        'notification_channel',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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

    public function getNotificationChannelLabel(): string
    {
        return match ($this->notification_channel) {
            'email'   => 'Email',
            'sms'     => 'SMS',
            'slack'   => 'Slack',
            'webhook' => 'Webhook',
            'in_app'  => 'In-App',
            default   => $this->notification_channel,
        };
    }
}
