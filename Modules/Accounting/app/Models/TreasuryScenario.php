<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Database\Factories\TreasuryScenarioFactory;

/**
 * @property int $id
 * @property int $forecast_id
 * @property string $name
 * @property string $type
 * @property string $adjustment_factor
 * @property string $scenario_balance
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TreasuryForecast $forecast
 */
class TreasuryScenario extends Model
{
    use HasFactory;

    protected $table = 'acc_treasury_scenarios';

    protected static function newFactory(): TreasuryScenarioFactory
    {
        return TreasuryScenarioFactory::new();
    }

    protected $fillable = [
        'forecast_id',
        'name',
        'type',
        'adjustment_factor',
        'scenario_balance',
        'notes',
    ];

    protected $casts = [
        'adjustment_factor' => 'decimal:4',
        'scenario_balance' => 'decimal:4',
    ];

    public function forecast(): BelongsTo
    {
        return $this->belongsTo(TreasuryForecast::class, 'forecast_id');
    }

    public function isOptimistic(): bool
    {
        return $this->type === 'optimistic';
    }

    public function isPessimistic(): bool
    {
        return $this->type === 'pessimistic';
    }

    public function isBase(): bool
    {
        return $this->type === 'base';
    }

    public function computeBalance(TreasuryForecast $forecast): float
    {
        return ((float) $forecast->total_inflows - (float) $forecast->total_outflows)
            * (float) $this->adjustment_factor
            + (float) $forecast->opening_balance;
    }
}
