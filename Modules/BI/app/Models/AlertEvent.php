<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\BI\Database\Factories\AlertEventFactory;

/**
 * @property int $id
 * @property int $alert_id
 * @property float $triggered_value
 * @property float $threshold
 * @property string $message
 * @property string $severity
 * @property bool $acknowledged
 * @property Carbon|null $acknowledged_at
 * @property int|null $acknowledged_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read KpiAlert    $alert
 */
class AlertEvent extends Model
{
    use HasFactory;

    protected $table = 'bi_alert_events';

    protected $fillable = [
        'alert_id',
        'triggered_value',
        'threshold',
        'message',
        'severity',
        'acknowledged',
        'acknowledged_at',
        'acknowledged_by',
    ];

    protected $casts = [
        'triggered_value' => 'decimal:4',
        'threshold' => 'decimal:4',
        'acknowledged' => 'boolean',
        'acknowledged_at' => 'datetime',
    ];

    protected static function newFactory(): AlertEventFactory
    {
        return AlertEventFactory::new();
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(KpiAlert::class, 'alert_id');
    }

    public function isAcknowledged(): bool
    {
        return (bool) $this->acknowledged;
    }

    public function acknowledge(int $userId): void
    {
        $this->update([
            'acknowledged' => true,
            'acknowledged_at' => now(),
            'acknowledged_by' => $userId,
        ]);
    }

    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }
}
