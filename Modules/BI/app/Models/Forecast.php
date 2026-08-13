<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\BI\Database\Factories\ForecastFactory;

/**
 * @property int $id
 * @property int $predictive_model_id
 * @property Carbon $forecast_date
 * @property float $forecast_value
 * @property float|null $lower_bound
 * @property float|null $upper_bound
 * @property float|null $actual_value
 * @property float|null $error_percent
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Forecast extends Model
{
    use HasFactory;

    protected $table = 'bi_forecasts';

    protected $fillable = [
        'predictive_model_id',
        'forecast_date',
        'forecast_value',
        'lower_bound',
        'upper_bound',
        'actual_value',
        'error_percent',
    ];

    protected $casts = [
        'forecast_date' => 'date',
        'forecast_value' => 'decimal:2',
        'lower_bound' => 'decimal:2',
        'upper_bound' => 'decimal:2',
        'actual_value' => 'decimal:2',
        'error_percent' => 'decimal:4',
    ];

    protected static function newFactory(): ForecastFactory
    {
        return ForecastFactory::new();
    }

    public function predictiveModel(): BelongsTo
    {
        return $this->belongsTo(PredictiveModel::class, 'predictive_model_id');
    }

    public function isAccurate(float $threshold = 10.0): bool
    {
        if ($this->error_percent === null) {
            return true;
        }

        return (float) $this->error_percent <= $threshold;
    }

    public function confidenceRange(): float
    {
        if ($this->upper_bound === null || $this->lower_bound === null) {
            return 0.0;
        }

        return (float) $this->upper_bound - (float) $this->lower_bound;
    }
}
