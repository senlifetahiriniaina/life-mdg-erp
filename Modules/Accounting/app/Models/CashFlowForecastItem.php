<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Accounting\Database\Factories\CashFlowForecastItemFactory;

/**
 * @property int $id
 * @property int $forecast_id
 * @property Carbon $date
 * @property string $category
 * @property string $type
 * @property string|null $source
 * @property string|null $description
 * @property string $amount
 * @property string $probability
 * @property string $weighted_amount
 * @property bool $is_actual
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CashFlowForecast $forecast
 */
class CashFlowForecastItem extends Model
{
    use HasFactory;

    protected static function newFactory(): CashFlowForecastItemFactory
    {
        return CashFlowForecastItemFactory::new();
    }

    protected $table = 'acc_cash_flow_forecast_items';

    protected $fillable = [
        'forecast_id',
        'date',
        'category',
        'type',
        'source',
        'description',
        'amount',
        'probability',
        'weighted_amount',
        'is_actual',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'probability' => 'decimal:2',
        'weighted_amount' => 'decimal:2',
        'is_actual' => 'boolean',
    ];

    public function forecast(): BelongsTo
    {
        return $this->belongsTo(CashFlowForecast::class, 'forecast_id');
    }
}
