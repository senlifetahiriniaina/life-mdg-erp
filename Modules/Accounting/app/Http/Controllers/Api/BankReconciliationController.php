<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\BankStatement;
use Modules\Accounting\Models\BankTransaction;
use Modules\Accounting\Services\BankReconciliationService;

/**
 * @group Accounting
 *
 * Manage BankReconciliation resources in Accounting module.
 */
class BankReconciliationController extends Controller
{
    public function __construct(
        private readonly BankReconciliationService $service,
    ) {}

    // ─── Bank Accounts ────────────────────────────────────────────────────────

    public function indexAccounts(): JsonResponse
    {
        $accounts = BankAccount::latest()->get();

        return response()->json($accounts);
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:100',
            'currency' => 'nullable|string|size:3',
            'current_balance' => 'nullable|numeric',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $account = $this->service->createBankAccount($validated);

        return response()->json($account, 201);
    }

    public function showAccount(BankAccount $account): JsonResponse
    {
        return response()->json($account);
    }

    public function updateAccount(Request $request, BankAccount $account): JsonResponse
    {
        $this->authorize('update', $account);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'bank_name' => 'sometimes|required|string|max:255',
            'account_number' => 'nullable|string|max:100',
            'currency' => 'nullable|string|size:3',
            'current_balance' => 'nullable|numeric',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $account->update($validated);

        return response()->json($account->fresh());
    }

    public function destroyAccount(BankAccount $account): JsonResponse
    {
        $this->authorize('delete', $account);

        $account->delete();

        return response()->json(null, 204);
    }

    // ─── Statements ───────────────────────────────────────────────────────────

    public function accountStatements(BankAccount $account): JsonResponse
    {
        $statements = $this->service->getAccountStatements($account);

        return response()->json($statements);
    }

    public function importStatement(Request $request, BankAccount $bank): JsonResponse
    {
        $validated = $request->validate([
            'statement_date' => 'required|date',
            'opening_balance' => 'required|numeric',
            'closing_balance' => 'required|numeric',
            'transactions' => 'nullable|array',
            'transactions.*.date' => 'required|date',
            'transactions.*.description' => 'required|string',
            'transactions.*.amount' => 'required|numeric',
            'transactions.*.reference' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $transactions = $validated['transactions'] ?? [];
        unset($validated['transactions']);

        $statement = $this->service->importStatement($bank, $validated, $transactions);

        return response()->json($statement->load('transactions'), 201);
    }

    public function showStatement(BankStatement $statement): JsonResponse
    {
        return response()->json($statement->load('transactions'));
    }

    // ─── Auto-Match ───────────────────────────────────────────────────────────

    public function autoMatch(BankStatement $statement): JsonResponse
    {
        $count = $this->service->autoMatch($statement);

        return response()->json([
            'matched_count' => $count,
            'statement' => $statement->fresh(),
        ]);
    }

    // ─── Transaction Actions ─────────────────────────────────────────────────

    public function matchTransaction(Request $request, BankTransaction $transaction): JsonResponse
    {
        $validated = $request->validate([
            'entry_id' => 'required|integer',
        ]);

        $transaction = $this->service->matchTransaction($transaction, $validated['entry_id']);

        return response()->json($transaction);
    }

    public function ignoreTransaction(BankTransaction $transaction): JsonResponse
    {
        $transaction = $this->service->ignoreTransaction($transaction);

        return response()->json($transaction);
    }

    // ─── Reconcile ───────────────────────────────────────────────────────────

    public function reconcileStatement(BankStatement $statement): JsonResponse
    {
        $statement = $this->service->reconcileStatement($statement);

        return response()->json($statement);
    }

    public function unmatched(BankStatement $statement): JsonResponse
    {
        $transactions = $this->service->getUnmatched($statement);

        return response()->json($transactions);
    }

    // ─── Summary ─────────────────────────────────────────────────────────────

    public function summary(): JsonResponse
    {
        return response()->json($this->service->getReconciliationSummary());
    }
}
