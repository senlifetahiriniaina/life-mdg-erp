<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Services\BudgetVarianceService;

/**
 * @group Accounting
 *
 * Budget variance analysis. Delegates to BudgetVarianceService's real,
 * already-tested calculation methods.
 */
class BudgetVarianceController extends Controller
{
    public function __construct(private readonly BudgetVarianceService $varianceService) {}

    public function analyze(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Budget::class);

        $request->validate([
            'budget_id' => 'required|exists:acc_budgets,id',
            'variance_threshold' => 'nullable|numeric|min:0|max:100',
        ]);

        $budget = Budget::findOrFail($request->integer('budget_id'));
        $threshold = (float) $request->input('variance_threshold', 10);

        $overview = $this->varianceService->calculateVariance($budget);
        $overBudgetLines = $this->varianceService->getOverBudgetLines($budget)
            ->filter(fn (array $line) => abs($line['variance_percent']) >= $threshold)
            ->values();

        return response()->json($overview + ['over_threshold_lines' => $overBudgetLines]);
    }

    public function report(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Budget::class);

        $request->validate([
            'budget_id' => 'required|exists:acc_budgets,id',
        ]);

        $budget = Budget::findOrFail($request->integer('budget_id'));

        return response()->json($this->varianceService->varianceReport($budget));
    }

    public function byDepartment(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Budget::class);

        return response()->json(
            $this->varianceService->varianceByDepartment($request->input('department'))
        );
    }

    public function byCategory(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Budget::class);

        return response()->json($this->varianceService->varianceByCategory());
    }

    public function trending(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Budget::class);

        $request->validate([
            'budget_id' => 'required|exists:acc_budgets,id',
            'months' => 'nullable|integer|min:3|max:24',
        ]);

        $budget = Budget::findOrFail($request->integer('budget_id'));

        return response()->json(
            $this->varianceService->monthlyTrend($budget)
        );
    }

    public function topVariances(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Budget::class);

        $request->validate([
            'budget_id' => 'required|exists:acc_budgets,id',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $budget = Budget::findOrFail($request->integer('budget_id'));

        return response()->json(
            $this->varianceService->topVariances($budget, (int) $request->input('limit', 5))
        );
    }
}
