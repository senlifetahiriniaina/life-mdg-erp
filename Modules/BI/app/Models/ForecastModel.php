<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int                       $id
 * @property int                       $company_id
 * @property int                       $created_by
 * @property string                    $name
 * @property string|null               $description
 * @property string                    $model_type
 * @property string                    $status
 * @property string                    $metric_name
 * @property int                       $metric_source_id
 * @property string                    $data_frequency
 * @property int                       $lookback_days
 * @property int                       $forecast_horizon
 * @property array<string, mixed>      $model_parameters
 * @property string|null               $rmse
 * @property string|null               $mae
 * @property string|null               $mape
 * @property string|null               $r_squared
 * @property \Carbon\Carbon|null       $trained_at
 * @property \Carbon\Carbon|null       $last_retrained_at
 * @property \Carbon\Carbon|null       $next_retraining_at
 * @property \Carbon\Carbon            $created_at
 * @property \Carbon\Carbon            $updated_at
 * @property \Carbon\Carbon|null       $deleted_at
 * @property-read \App\Models\User     $creator
 * @property-read \App\Models\Company  $company
 */
class ForecastModel extends Model
{
    use HasFactory, SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_forecast_models';

    protected $fillable = [
        'company_id',
        'created_by',
        'name',
        'description',
        'model_type',
        'status',
        'metric_name',
        'metric_source_id',
        'data_frequency',
        'lookback_days',
        'forecast_horizon',
        'model_parameters',
        'rmse',
        'mae',
        'mape',
        'r_squared',
        'trained_at',
        'last_retrained_at',
        'next_retraining_at',
    ];

    protected $casts = [
        'model_parameters'   => 'array',
        'rmse'               => 'decimal:4',
        'mae'                => 'decimal:4',
        'mape'               => 'decimal:2',
        'r_squared'          => 'decimal:4',
        'trained_at'         => 'datetime',
        'last_retrained_at'  => 'datetime',
        'next_retraining_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function timeseriesData(): HasMany
    {
        return $this->hasMany(TimeseriesData::class, 'model_id');
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(ForecastPrediction::class, 'model_id');
    }

    public function scenarios(): HasMany
    {
        return $this->hasMany(ForecastScenario::class, 'model_id');
    }

    public function seasonalityPatterns(): HasMany
    {
        return $this->hasMany(SeasonalityPattern::class, 'model_id');
    }

    public function trendAnalysis(): HasMany
    {
        return $this->hasMany(TrendAnalysis::class, 'model_id');
    }

    public function retrainingLogs(): HasMany
    {
        return $this->hasMany(ModelRetrainingLog::class, 'model_id')->latest();
    }

    public function train(): void
    {
        $this->update([
            'status'    => 'trained',
            'trained_at' => now(),
        ]);
    }

    public function deploy(): void
    {
        $this->update(['status' => 'deployed']);
    }

    public function archive(): void
    {
        $this->update(['status' => 'archived']);
    }

    public function markForRetraining(): void
    {
        $this->update(['next_retraining_at' => now()->addDays(7)]);
    }

    public function isTrained(): bool
    {
        return $this->status === 'trained' || $this->status === 'deployed';
    }

    public function isDeployed(): bool
    {
        return $this->status === 'deployed';
    }

    public function getAccuracyPercentage(): float
    {
        if ($this->mape === null) {
            return 0.0;
        }
        return max(0.0, 100.0 - (float) $this->mape);
    }
}
