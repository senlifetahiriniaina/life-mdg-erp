<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\BI\Database\Factories\BiAnomalyFactory;

/**
 * @property int $id
 * @property string $entity_type
 * @property int|null $entity_id
 * @property string $metric_name
 * @property Carbon $detected_at
 * @property Carbon $anomaly_date
 * @property float $expected_value
 * @property float $actual_value
 * @property float $deviation_percent
 * @property string $severity
 * @property string $status
 * @property string|null $description
 * @property Carbon|null $acknowledged_at
 * @property int|null $acknowledged_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BiAnomaly extends Model
{
    use HasFactory;

    protected $table = 'bi_anomalies';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'metric_name',
        'detected_at',
        'anomaly_date',
        'expected_value',
        'actual_value',
        'deviation_percent',
        'severity',
        'status',
        'description',
        'acknowledged_at',
        'acknowledged_by',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'anomaly_date' => 'date',
        'expected_value' => 'decimal:2',
        'actual_value' => 'decimal:2',
        'deviation_percent' => 'decimal:2',
        'acknowledged_at' => 'datetime',
    ];

    protected static function newFactory(): BiAnomalyFactory
    {
        return BiAnomalyFactory::new();
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    public function isNew(): bool
    {
        return $this->status === 'new';
    }

    public function acknowledge(int $userId): self
    {
        $this->update([
            'status' => 'acknowledged',
            'acknowledged_by' => $userId,
            'acknowledged_at' => now(),
        ]);

        return $this;
    }
}
