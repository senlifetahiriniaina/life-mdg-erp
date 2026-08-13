<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Accounting\Http\Requests\ImportTransactionsRequest;
use Modules\Accounting\Http\Requests\StoreBankAccountRequest;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Services\BankReconciliationService;

/**
 * @group Accounting
 *
 * Manage BankAccount resources in Accounting module.
 */
class BankAccountController extends Controller
{
    public function __construct(
        private readonly BankReconciliationService $service,
    ) {}

    public function index(): JsonResponse
    {
        $accounts = BankAccount::withCount([
            'transactions as unreconciled_count' => fn ($q) => $q->where('reconciled', false),
        ])->get();

        return response()->json($accounts);
    }

    public function store(StoreBankAccountRequest $request): JsonResponse
    {
        $account = BankAccount::create($request->validated());

        return response()->json($account, 201);
    }

    public function show(BankAccount $bank): JsonResponse
    {
        return response()->json($bank->load('glAccount'));
    }

    public function update(StoreBankAccountRequest $request, BankAccount $bank): JsonResponse
    {
        $bank->update($request->validated());

        return response()->json($bank->fresh());
    }

    public function destroy(BankAccount $bank): JsonResponse
    {
        $bank->delete();

        return response()->json(null, 204);
    }

    public function importTransactions(ImportTransactionsRequest $request, BankAccount $bank): JsonResponse
    {
        $count = $this->service->importTransactions($bank, $request->validated('transactions'));

        return response()->json(['imported' => $count]);
    }

    public function statements(BankAccount $bank): JsonResponse
    {
        return response()->json($bank->statements()->latest()->get());
    }

    public function summary(): JsonResponse
    {
        $summary = $this->service->getReconciliationSummary();

        return response()->json($summary);
    }
}
