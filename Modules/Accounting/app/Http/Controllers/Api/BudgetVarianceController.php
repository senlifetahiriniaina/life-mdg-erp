<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Services\BudgetVarianceService;

/**
 * @group Accounting
 *
 * Manage BudgetVariance resources in Accounting module.
 */
class BudgetVarianceController extends Controller
{
    public function __construct(private readonly BudgetVarianceService $varianceService) {}

    public function analyze(Request $request)
    {
        $this->authorize('viewAny', Budget::class);

        $request->validate([
            'budget_id' => 'required|exists:acc_budgets,id',
            'variance_threshold' => 'nullable|numeric|min:0|max:100',
        ]);

        $variance = $this->varianceService->analyzeVariance(
            (int) $request->budget_id,
            (float) $request->input('variance_threshold', 10)
        );

        return response()->json($variance);
    }

    public function drilldown(Request $request)
    {
        $this->authorize('viewAny', Budget::class);

        $request->validate([
            'budget_id' => 'required|exists:acc_budgets,id',
            'line_id' => 'nullable|exists:acc_budget_lines,id',
            'depth' => 'nullable|integer|min:1|max:5',
        ]);

        $drilldown = $this->varianceService->drilldownVariance(
            (int) $request->budget_id,
            $request->input('line_id'),
            (int) $request->input('depth', 3)
        );

        return response()->json($drilldown);
    }

    public function byDepartment(Request $request)
    {
        $this->authorize('viewAny', Budget::class);

        $departmentId = $request->input('department_id');
        $variance = $this->varianceService->varianceByDepartment($departmentId);

        return response()->json($variance);
    }

    public function byCategory(Request $request)
    {
        $this->authorize('viewAny', Budget::class);

        $variance = $this->varianceService->varianceByCategory();

        return response()->json($variance);
    }

    public function trending(Request $request)
    {
        $this->authorize('viewAny', Budget::class);

        $request->validate([
            'budget_id' => 'required|exists:acc_budgets,id',
            'months' => 'nullable|integer|min:3|max:24',
        ]);

        $trend = $this->varianceService->getTrending(
            (int) $request->budget_id,
            (int) $request->input('months', 12)
        );

        return response()->json($trend);
    }

    public function explain(Request $request)
    {
        $this->authorize('viewAny', Budget::class);

        $request->validate([
            'line_id' => 'required|exists:acc_budget_lines,id',
        ]);

        $explanation = $this->varianceService->explainVariance(
            (int) $request->line_id
        );

        return response()->json($explanation);
    }
}
