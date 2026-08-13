<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\BI\Database\Factories\KpiAlertFactory;

/**
 * @property int $id
 * @property string $name
 * @property int|null $kpi_id
 * @property string $metric_name
 * @property string $condition
 * @property float $threshold
 * @property float|null $comparison_value
 * @property string $severity
 * @property bool $is_active
 * @property Carbon|null $last_triggered_at
 * @property int $trigger_count
 * @property array<string, mixed>|null $notification_channels
 * @property array<string, mixed>|null $recipients
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class KpiAlert extends Model
{
    use HasFactory;

    protected $table = 'bi_kpi_alerts';

    protected $fillable = [
        'name',
        'kpi_id',
        'metric_name',
        'condition',
        'threshold',
        'comparison_value',
        'severity',
        'is_active',
        'last_triggered_at',
        'trigger_count',
        'notification_channels',
        'recipients',
        'created_by',
    ];

    protected $casts = [
        'threshold' => 'decimal:4',
        'comparison_value' => 'decimal:4',
        'is_active' => 'boolean',
        'notification_channels' => 'array',
        'recipients' => 'array',
        'last_triggered_at' => 'datetime',
        'trigger_count' => 'integer',
    ];

    protected static function newFactory(): KpiAlertFactory
    {
        return KpiAlertFactory::new();
    }

    public function events(): HasMany
    {
        return $this->hasMany(AlertEvent::class, 'alert_id');
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    public function evaluate(float $currentValue): bool
    {
        return match ($this->condition) {
            'above' => $currentValue > (float) $this->threshold,
            'below' => $currentValue < (float) $this->threshold,
            'equals' => abs($currentValue - (float) $this->threshold) < 0.0001,
            'change_pct' => $this->comparison_value !== null
                && abs(
                    ($currentValue - (float) $this->comparison_value)
                    / max((float) $this->comparison_value, 0.0001)
                    * 100
                ) > (float) $this->threshold,
            default => false,
        };
    }

    public function trigger(float $value): void
    {
        $this->increment('trigger_count');
        $this->update(['last_triggered_at' => now()]);
    }
}
