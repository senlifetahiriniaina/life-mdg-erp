<?php

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Accounting\Http\Requests\StoreExpenseRequest;
use Modules\Accounting\Http\Requests\UpdateExpenseRequest;
use Modules\Accounting\Http\Resources\ExpenseResource;
use Modules\Accounting\Models\Expense;
use Modules\Accounting\Services\AccountingService;

/**
 * @group Accounting
 *
 * Manage Expense resources in Accounting module.
 */
class ExpenseController extends Controller
{
    public function __construct(protected AccountingService $service) {}

    public function index(Request $request)
    {
        $status = $request->query('status');
        $perPage = $request->query('per_page', 15);

        $query = Expense::query();

        if ($status) {
            $query->where('status', $status);
        }

        $expenses = $query->with('glAccount')->orderBy('created_at', 'desc')->paginate($perPage);

        return ExpenseResource::collection($expenses);
    }

    public function store(StoreExpenseRequest $request)
    {
        $this->authorize('create', Expense::class);

        $expense = $this->service->recordExpense($request->validated());

        return (new ExpenseResource($expense))->response()->setStatusCode(201);
    }

    public function show(Expense $expense)
    {
        return new ExpenseResource($expense);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense)
    {
        $this->authorize('update', $expense);

        $expense->update($request->validated());

        return new ExpenseResource($expense);
    }

    public function destroy(Expense $expense)
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return response()->noContent();
    }

    public function approve(Request $request, Expense $expense)
    {
        $this->authorize('approve', $expense);

        $approved = $this->service->approveExpense($expense, auth()->id());

        return new ExpenseResource($approved);
    }

    public function pending()
    {
        $expenses = $this->service->getPendingExpenses();

        return ExpenseResource::collection($expenses);
    }

    public function byCategory(string $category)
    {
        $expenses = $this->service->getExpensesByCategory($category);

        return ExpenseResource::collection($expenses);
    }
}
