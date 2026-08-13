<?php

namespace Modules\Accounting\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Database\Factories\BudgetScenarioFactory;

/**
 * @property int $id
 * @property string $name
 * @property int $base_budget_id
 * @property string $scenario_type
 * @property string $adjustment_type
 * @property string $revenue_adjustment
 * @property string $expense_adjustment
 * @property string|null $description
 * @property array<string, mixed>|null $assumptions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Budget|null $baseBudget
 */
class BudgetScenario extends Model
{
    use HasFactory;

    protected static function newFactory(): BudgetScenarioFactory
    {
        return BudgetScenarioFactory::new();
    }

    protected $table = 'acc_budget_scenarios';

    protected $fillable = [
        'name',
        'base_budget_id',
        'scenario_type',
        'adjustment_type',
        'revenue_adjustment',
        'expense_adjustment',
        'description',
        'assumptions',
    ];

    protected $casts = [
        'revenue_adjustment' => 'decimal:4',
        'expense_adjustment' => 'decimal:4',
        'assumptions' => 'json',
    ];

    // ─── Relations ───────────────────────────────────────────────────────────

    public function baseBudget(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'base_budget_id');
    }

    // ─── Business Methods ────────────────────────────────────────────────────

    /**
     * Projected revenue after applying adjustments.
     */
    public function projectedRevenue(): float
    {
        $base = $this->baseBudget;
        if (! $base) {
            return 0.0;
        }

        $budgeted = $base->totalBudgeted();
        $revenue = (float) $budgeted['revenue'];

        if ($this->adjustment_type === 'percentage') {
            return round($revenue * (1 + (float) $this->revenue_adjustment), 2);
        }

        return round($revenue + (float) $this->revenue_adjustment, 2);
    }

    /**
     * Projected expenses after applying adjustments.
     */
    public function projectedExpenses(): float
    {
        $base = $this->baseBudget;
        if (! $base) {
            return 0.0;
        }

        $budgeted = $base->totalBudgeted();
        $expenses = (float) $budgeted['expenses'];

        if ($this->adjustment_type === 'percentage') {
            return round($expenses * (1 + (float) $this->expense_adjustment), 2);
        }

        return round($expenses + (float) $this->expense_adjustment, 2);
    }

    /**
     * Projected profit (revenue - expenses).
     */
    public function projectedProfit(): float
    {
        return round($this->projectedRevenue() - $this->projectedExpenses(), 2);
    }

    /**
     * Apply scenario adjustments to budget lines and return projected lines array
     * without persisting.
     *
     * @return array<int, array<string, mixed>>
     */
    public function applyTo(Budget $budget): array
    {
        $lines = $budget->lines()->with('account')->get();

        $result = [];

        foreach ($lines as $line) {
            $account = $line->account;
            $budgeted = (float) $line->budgeted_amount;
            $type = $account ? $account->type : 'unknown';

            if ($this->adjustment_type === 'percentage') {
                $adjustment = $type === 'revenue'
                    ? (float) $this->revenue_adjustment
                    : (float) $this->expense_adjustment;

                $projected = round($budgeted * (1 + $adjustment), 2);
            } else {
                $adjustment = $type === 'revenue'
                    ? (float) $this->revenue_adjustment
                    : (float) $this->expense_adjustment;

                $projected = round($budgeted + $adjustment, 2);
            }

            $result[] = [
                'budget_line_id' => $line->id,
                'account_id' => $line->account_id,
                'account_name' => $account ? $account->name : null,
                'account_type' => $type,
                'period_month' => $line->period_month,
                'period_year' => $line->period_year,
                'original_amount' => $budgeted,
                'projected_amount' => $projected,
                'adjustment_amount' => round($projected - $budgeted, 2),
            ];
        }

        return $result;
    }
}
