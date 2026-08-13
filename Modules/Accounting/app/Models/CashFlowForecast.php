<?php

namespace Modules\Accounting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Accounting\Database\Factories\CashFlowForecastFactory;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property Carbon $base_date
 * @property string $horizon
 * @property Carbon|null $end_date
 * @property string $scenario
 * @property string $opening_balance
 * @property string $projected_closing_balance
 * @property string|null $minimum_balance_threshold
 * @property array<string, mixed>|null $assumptions
 * @property string $status
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $createdBy
 * @property-read Collection<int, CashFlowForecastItem> $items
 */
class CashFlowForecast extends Model
{
    use HasFactory;

    protected static function newFactory(): CashFlowForecastFactory
    {
        return CashFlowForecastFactory::new();
    }

    protected $table = 'acc_cash_flow_forecasts';

    protected $fillable = [
        'name',
        'description',
        'base_date',
        'horizon',
        'end_date',
        'scenario',
        'opening_balance',
        'projected_closing_balance',
        'minimum_balance_threshold',
        'assumptions',
        'status',
        'created_by',
    ];

    protected $casts = [
        'base_date' => 'date',
        'end_date' => 'date',
        'opening_balance' => 'decimal:2',
        'projected_closing_balance' => 'decimal:2',
        'minimum_balance_threshold' => 'decimal:2',
        'assumptions' => 'json',
    ];

    /** @return HasMany<CashFlowForecastItem, CashFlowForecast> */
    public function items(): HasMany
    {
        return $this->hasMany(CashFlowForecastItem::class, 'forecast_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isBreachingMinimum(): bool
    {
        return (float) $this->projected_closing_balance < (float) $this->minimum_balance_threshold;
    }
}
