<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Database\Factories\TreasuryForecastFactory;

/**
 * @property int $id
 * @property string $name
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property string $status
 * @property string $opening_balance
 * @property string $total_inflows
 * @property string $total_outflows
 * @property string $closing_balance
 * @property string|null $currency
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CashFlowLine> $lines
 * @property-read Collection<int, TreasuryScenario> $scenarios
 * @property-read User|null $createdBy
 */
class TreasuryForecast extends Model
{
    use HasFactory;

    protected $table = 'acc_treasury_forecasts';

    protected static function newFactory(): TreasuryForecastFactory
    {
        return TreasuryForecastFactory::new();
    }

    protected $fillable = [
        'name',
        'period_start',
        'period_end',
        'status',
        'opening_balance',
        'total_inflows',
        'total_outflows',
        'closing_balance',
        'currency',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'opening_balance' => 'decimal:4',
        'total_inflows' => 'decimal:4',
        'total_outflows' => 'decimal:4',
        'closing_balance' => 'decimal:4',
    ];

    /** @return HasMany<CashFlowLine, self> */
    public function lines(): HasMany
    {
        return $this->hasMany(CashFlowLine::class, 'forecast_id');
    }

    /** @return HasMany<TreasuryScenario, self> */
    public function scenarios(): HasMany
    {
        return $this->hasMany(TreasuryScenario::class, 'forecast_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function netCashFlow(): float
    {
        return (float) $this->total_inflows - (float) $this->total_outflows;
    }

    public function isPositive(): bool
    {
        return (float) $this->closing_balance >= 0;
    }

    public function recompute(): void
    {
        $inflows = (float) $this->lines()->where('flow_type', 'inflow')->sum('amount');
        $outflows = (float) $this->lines()->where('flow_type', 'outflow')->sum('amount');
        $closing = (float) $this->opening_balance + $inflows - $outflows;

        $this->update([
            'total_inflows' => $inflows,
            'total_outflows' => $outflows,
            'closing_balance' => $closing,
        ]);
    }

    public function activate(): void
    {
        $this->update(['status' => 'active']);
    }

    public function coverageRatio(): float
    {
        $outflows = (float) $this->total_outflows;
        if ($outflows === 0.0) {
            return 0.0;
        }
        $inflows = (float) $this->total_inflows;

        return $inflows > 0 ? $inflows / $outflows : 0.0;
    }
}
