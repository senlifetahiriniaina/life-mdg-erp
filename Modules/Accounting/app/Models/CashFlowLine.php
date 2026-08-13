<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Database\Factories\CashFlowLineFactory;

/**
 * @property int $id
 * @property int $forecast_id
 * @property string $category
 * @property string $flow_type
 * @property string $amount
 * @property string|null $description
 * @property Carbon|null $expected_date
 * @property bool $is_recurring
 * @property string|null $recurrence_period
 * @property string $probability
 * @property string|null $actual_amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TreasuryForecast $forecast
 */
class CashFlowLine extends Model
{
    use HasFactory;

    protected $table = 'acc_treasury_lines';

    protected static function newFactory(): CashFlowLineFactory
    {
        return CashFlowLineFactory::new();
    }

    protected $fillable = [
        'forecast_id',
        'category',
        'flow_type',
        'amount',
        'description',
        'expected_date',
        'is_recurring',
        'recurrence_period',
        'probability',
        'actual_amount',
    ];

    protected $casts = [
        'expected_date' => 'date',
        'amount' => 'decimal:4',
        'actual_amount' => 'decimal:4',
        'probability' => 'decimal:4',
        'is_recurring' => 'boolean',
    ];

    public function forecast(): BelongsTo
    {
        return $this->belongsTo(TreasuryForecast::class, 'forecast_id');
    }

    public function isInflow(): bool
    {
        return $this->flow_type === 'inflow';
    }

    public function isOutflow(): bool
    {
        return $this->flow_type === 'outflow';
    }

    public function weightedAmount(): float
    {
        return (float) $this->amount * ((float) $this->probability / 100);
    }

    public function isRealized(): bool
    {
        return $this->actual_amount !== null;
    }

    public function variance(): float
    {
        if ($this->isRealized()) {
            return (float) $this->actual_amount - (float) $this->amount;
        }

        return 0.0;
    }
}
