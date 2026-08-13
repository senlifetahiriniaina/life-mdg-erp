<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\BI\Database\Factories\PredictiveModelFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $entity_type
 * @property string $model_type
 * @property array<string, mixed>|null $training_data
 * @property array<string, mixed>|null $coefficients
 * @property float|null $accuracy_score
 * @property Carbon|null $last_trained_at
 * @property int $forecast_horizon_days
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PredictiveModel extends Model
{
    use HasFactory;

    protected $table = 'bi_predictive_models';

    protected $fillable = [
        'name',
        'entity_type',
        'model_type',
        'training_data',
        'coefficients',
        'accuracy_score',
        'last_trained_at',
        'forecast_horizon_days',
        'is_active',
    ];

    protected $casts = [
        'training_data' => 'json',
        'coefficients' => 'json',
        'accuracy_score' => 'decimal:4',
        'is_active' => 'boolean',
        'last_trained_at' => 'datetime',
        'forecast_horizon_days' => 'integer',
    ];

    protected static function newFactory(): PredictiveModelFactory
    {
        return PredictiveModelFactory::new();
    }

    /** @return HasMany<Forecast, $this> */
    public function forecasts(): HasMany
    {
        return $this->hasMany(Forecast::class, 'predictive_model_id');
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function latestForecast(): ?Forecast
    {
        return $this->forecasts()->orderByDesc('forecast_date')->first();
    }

    public function forecastAccuracy(): float
    {
        $avg = $this->forecasts()
            ->whereNotNull('error_percent')
            ->avg('error_percent');

        return (float) ($avg ?? 0.0);
    }
}
