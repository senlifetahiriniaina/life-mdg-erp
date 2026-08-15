<?php

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Accounting\Http\Requests\StoreBudgetRequest;
use Modules\Accounting\Http\Requests\UpdateBudgetRequest;
use Modules\Accounting\Http\Resources\BudgetResource;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Services\AccountingService;

/**
 * @group Accounting
 *
 * Manage Budget resources in Accounting module.
 */
class BudgetController extends Controller
{
    public function __construct(protected AccountingService $service) {}

    public function index(Request $request)
    {
        $year = $request->query('fiscal_year', date('Y'));
        $budgets = $this->service->getBudgetsByYear($year);
        $collection = BudgetResource::collection($budgets);
        $data = $collection->toArray($request);
        $items = $data['data'] ?? $data;
        return response()->json([
            'data' => $items,
            'total' => is_countable($items) ? count($items) : 0,
        ]);
    }

    public function store(StoreBudgetRequest $request)
    {
        $this->authorize('create', Budget::class);

        $budget = $this->service->createBudget($request->validated());

        return (new BudgetResource($budget))->response()->setStatusCode(201);
    }

    public function show(Budget $budget)
    {
        $budget->load(['lines', 'budgetLines']);
        $totalBudgeted = $budget->budgetLines->sum('budgeted_amount');
        $totalActual = $budget->budgetLines->sum('actual_amount');
        $completionPct = $totalBudgeted > 0 ? round(($totalActual / $totalBudgeted) * 100, 2) : 0;
        return response()->json([
            'data' => (new BudgetResource($budget))->toArray(request()),
            'budget' => (new BudgetResource($budget))->toArray(request()),
            'variance_summary' => ['total_budgeted' => $totalBudgeted, 'total_actual' => $totalActual],
            'completion_pct' => $completionPct,
            'is_over_budget' => $totalActual > $totalBudgeted,
        ]);
    }

    public function update(UpdateBudgetRequest $request, Budget $budget)
    {
        $this->authorize('update', $budget);

        $updated = $this->service->updateBudget($budget, $request->validated());

        return new BudgetResource($updated);
    }

    public function destroy(Budget $budget)
    {
        $this->authorize('delete', $budget);

        $budget->delete();

        return response()->noContent();
    }

    public function overBudget(Request $request)
    {
        $year = $request->query('fiscal_year', date('Y'));
        $items = $this->service->getOverBudgetItems($year);

        return BudgetResource::collection($items);
    }
}
