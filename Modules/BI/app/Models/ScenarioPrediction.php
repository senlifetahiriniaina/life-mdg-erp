<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int            $id
 * @property int            $scenario_id
 * @property \Carbon\Carbon $prediction_date
 * @property string         $predicted_value
 * @property string         $lower_bound
 * @property string         $upper_bound
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read ForecastScenario $scenario
 */
class ScenarioPrediction extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_scenario_predictions';

    protected $fillable = [
        'scenario_id',
        'prediction_date',
        'predicted_value',
        'lower_bound',
        'upper_bound',
    ];

    protected $casts = [
        'predicted_value' => 'decimal:4',
        'lower_bound'     => 'decimal:4',
        'upper_bound'     => 'decimal:4',
        'prediction_date' => 'datetime',
    ];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(ForecastScenario::class);
    }

    public function getPredictedValue(): float
    {
        return (float) $this->predicted_value;
    }

    public function getRange(): array
    {
        return [
            'lower' => (float) $this->lower_bound,
            'upper' => (float) $this->upper_bound,
        ];
    }
}
