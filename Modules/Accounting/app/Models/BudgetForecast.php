<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Database\Factories\BudgetForecastFactory;

class BudgetForecast extends Model
{
    use HasFactory;

    protected $table = 'acc_budget_forecasts';

    protected $fillable = [
        'budget_id',
        'budget_line_id',
        'forecast_month',
        'forecasted_amount',
        'forecast_method',
        'confidence_level',
    ];

    protected $casts = [
        'forecast_month' => 'date',
        'forecasted_amount' => 'decimal:4',
        'confidence_level' => 'decimal:2',
    ];

    protected static function newFactory(): BudgetForecastFactory
    {
        return BudgetForecastFactory::new();
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class);
    }
}
