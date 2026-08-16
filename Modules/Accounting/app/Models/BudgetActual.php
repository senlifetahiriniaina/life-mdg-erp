<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Database\Factories\BudgetActualFactory;

class BudgetActual extends Model
{
    use HasFactory;

    protected $table = 'acc_budget_actuals';

    protected $fillable = [
        'budget_id',
        'budget_line_id',
        'period_month',
        'actual_amount',
    ];

    protected $casts = [
        'period_month' => 'date',
        'actual_amount' => 'decimal:4',
    ];

    protected static function newFactory(): BudgetActualFactory
    {
        return BudgetActualFactory::new();
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
