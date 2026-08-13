<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                  $id
 * @property int                  $rule_id
 * @property string               $status
 * @property string               $severity
 * @property string               $triggered_value
 * @property array<string, mixed> $condition_results
 * @property string|null          $message
 * @property int|null             $acknowledged_by
 * @property \Carbon\Carbon|null  $acknowledged_at
 * @property string|null          $acknowledgment_note
 * @property \Carbon\Carbon|null  $resolved_at
 * @property \Carbon\Carbon       $triggered_at
 * @property \Carbon\Carbon       $created_at
 * @property \Carbon\Carbon       $updated_at
 * @property-read AlertRule       $rule
 * @property-read \App\Models\User|null $acknowledgedBy
 */
class AlertHistory extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_alert_history';

    protected $fillable = [
        'rule_id',
        'status',
        'severity',
        'triggered_value',
        'condition_results',
        'message',
        'acknowledged_by',
        'acknowledged_at',
        'acknowledgment_note',
        'resolved_at',
        'triggered_at',
    ];

    protected $casts = [
        'condition_results'  => 'array',
        'triggered_value'    => 'decimal:4',
        'triggered_at'       => 'datetime',
        'acknowledged_at'    => 'datetime',
        'resolved_at'        => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'acknowledged_by');
    }

    public function acknowledge(int $userId, string $note = ''): void
    {
        $this->update([
            'status'              => 'acknowledged',
            'acknowledged_by'     => $userId,
            'acknowledged_at'     => now(),
            'acknowledgment_note' => $note,
        ]);
    }

    public function resolve(): void
    {
        $this->update([
            'status'      => 'resolved',
            'resolved_at' => now(),
        ]);
    }

    public function isTriggered(): bool
    {
        return $this->status === 'triggered';
    }

    public function isAcknowledged(): bool
    {
        return $this->status === 'acknowledged';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isEscalated(): bool
    {
        return $this->status === 'escalated';
    }

    public function getSeverityLabel(): string
    {
        return match ($this->severity) {
            'low'      => 'Low',
            'medium'   => 'Medium',
            'high'     => 'High',
            'critical' => 'Critical',
            default    => $this->severity,
        };
    }
}
