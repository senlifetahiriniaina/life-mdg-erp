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
 * @property int                       $model_id
 * @property int                       $created_by
 * @property string                    $name
 * @property string|null               $description
 * @property string                    $scenario_type
 * @property array<string, mixed>      $parameters
 * @property string|null               $growth_rate_adjustment
 * @property string|null               $volatility_adjustment
 * @property \Carbon\Carbon            $created_at
 * @property \Carbon\Carbon            $updated_at
 * @property \Carbon\Carbon|null       $deleted_at
 * @property-read ForecastModel        $model
 * @property-read \App\Models\User     $creator
 */
class ForecastScenario extends Model
{
    use HasFactory, SoftDeletes;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_forecast_scenarios';

    protected $fillable = [
        'model_id',
        'created_by',
        'name',
        'description',
        'scenario_type',
        'parameters',
        'growth_rate_adjustment',
        'volatility_adjustment',
    ];

    protected $casts = [
        'parameters'                => 'array',
        'growth_rate_adjustment'    => 'decimal:2',
        'volatility_adjustment'     => 'decimal:2',
    ];

    public function model(): BelongsTo
    {
        return $this->belongsTo(ForecastModel::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(ScenarioPrediction::class, 'scenario_id');
    }

    public function getScenarioLabel(): string
    {
        return match ($this->scenario_type) {
            'best_case'  => 'Best Case',
            'worst_case' => 'Worst Case',
            'realistic'  => 'Realistic',
            'custom'     => $this->name,
            default      => $this->name,
        };
    }

    public function getGrowthRateAdjustment(): float
    {
        return (float) ($this->growth_rate_adjustment ?? 0);
    }

    public function getVolatilityAdjustment(): float
    {
        return (float) ($this->volatility_adjustment ?? 0);
    }
}
