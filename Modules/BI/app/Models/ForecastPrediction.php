<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int            $id
 * @property int            $model_id
 * @property \Carbon\Carbon $prediction_date
 * @property string         $predicted_value
 * @property string         $lower_bound
 * @property string         $upper_bound
 * @property string         $confidence_level
 * @property string|null    $actual_value
 * @property string|null    $error_percent
 * @property bool           $is_outlier
 * @property array<string, mixed>|null $feature_importance
 * @property \Carbon\Carbon $generated_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read ForecastModel $model
 */
class ForecastPrediction extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_forecast_predictions';

    protected $fillable = [
        'model_id',
        'prediction_date',
        'predicted_value',
        'lower_bound',
        'upper_bound',
        'confidence_level',
        'actual_value',
        'error_percent',
        'is_outlier',
        'feature_importance',
        'generated_at',
    ];

    protected $casts = [
        'predicted_value'    => 'decimal:4',
        'lower_bound'        => 'decimal:4',
        'upper_bound'        => 'decimal:4',
        'confidence_level'   => 'decimal:2',
        'actual_value'       => 'decimal:4',
        'error_percent'      => 'decimal:4',
        'is_outlier'         => 'boolean',
        'feature_importance' => 'array',
        'prediction_date'    => 'datetime',
        'generated_at'       => 'datetime',
    ];

    public function model(): BelongsTo
    {
        return $this->belongsTo(ForecastModel::class);
    }

    public function getPredictedValue(): float
    {
        return (float) $this->predicted_value;
    }

    public function getConfidenceInterval(): array
    {
        return [
            'lower' => (float) $this->lower_bound,
            'upper' => (float) $this->upper_bound,
        ];
    }

    public function isAccurate(float $threshold = 10.0): bool
    {
        if ($this->error_percent === null) {
            return true;
        }
        return (float) $this->error_percent <= $threshold;
    }

    public function recordActual(float $actual): void
    {
        $predicted = (float) $this->predicted_value;
        $error = abs($actual - $predicted) / $actual * 100;

        $this->update([
            'actual_value'  => $actual,
            'error_percent' => $error,
            'is_outlier'    => $error > 25.0, // mark as outlier if error > 25%
        ]);
    }

    public function getTopFactors(int $limit = 5): array
    {
        $importance = $this->feature_importance ?? [];
        arsort($importance);
        return array_slice($importance, 0, $limit, true);
    }
}
