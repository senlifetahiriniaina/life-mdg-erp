<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetLine extends Model
{
    use HasFactory;

    protected $table = 'acc_budget_lines';

    protected $fillable = [
        'budget_id',
        'gl_account_id',
        'account_id',
        'cost_center_id',
        'department_id',
        'budget_amount',
        'budgeted_amount',
        'actual_amount',
        'variance',
        'spent_amount',
        'category',
        'description',
        'notes',
        'period',
        'month',
        'quarter',
        'period_month',
        'period_year',
    ];

    protected $casts = [
        'budget_amount' => 'decimal:2',
        'budgeted_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'variance' => 'decimal:2',
        'spent_amount' => 'decimal:2',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(GLAccount::class, 'gl_account_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\ChartOfAccount::class, 'account_id');
    }

    public function budgetActuals(): HasMany
    {
        return $this->hasMany(BudgetActual::class);
    }

    // ─── Computed helpers ────────────────────────────────────────────────────

    public function effectiveBudgetedAmount(): float
    {
        return (float) ($this->budgeted_amount ?? $this->budget_amount ?? 0);
    }

    public function variance(): float
    {
        $budgeted = $this->effectiveBudgetedAmount();
        $actual = (float) ($this->spent_amount ?? $this->actual_amount ?? 0);
        return round($budgeted - $actual, 2);
    }

    public function variancePercent(): float
    {
        $budgeted = $this->effectiveBudgetedAmount();
        if ($budgeted == 0) return 0.0;
        return round((abs($this->variance()) / $budgeted) * 100, 2);
    }

    public function remainingAmount(): float
    {
        return round($this->effectiveBudgetedAmount() - (float) ($this->spent_amount ?? $this->actual_amount ?? 0), 2);
    }

    public function utilizationRate(): float
    {
        $budgeted = $this->effectiveBudgetedAmount();
        if ($budgeted == 0) return 0.0;
        $spent = (float) ($this->spent_amount ?? $this->actual_amount ?? 0);
        return round(($spent / $budgeted) * 100, 2);
    }

    public function recordSpend(float $amount): void
    {
        $this->spent_amount = (float) ($this->spent_amount ?? 0) + $amount;
        $this->save();
        $this->budget->recompute();
    }

    public function getTotalActual(): float
    {
        return (float) $this->budgetActuals()->sum('actual_amount');
    }

    public function getVariance(): float
    {
        return $this->getTotalActual() - (float) ($this->budget_amount ?? $this->budgeted_amount ?? 0);
    }

    public function getVariancePercent(): float
    {
        $budget = (float) ($this->budget_amount ?? $this->budgeted_amount ?? 0);
        return $budget > 0 ? ($this->getVariance() / $budget) * 100 : 0;
    }

    public function isOverBudget(): bool
    {
        $budgeted = $this->effectiveBudgetedAmount();
        $spent = (float) ($this->spent_amount ?? $this->actual_amount ?? 0);
        return $spent > $budgeted;
    }
}
