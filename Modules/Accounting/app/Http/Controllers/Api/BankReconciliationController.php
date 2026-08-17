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

    public function autoMatchAccount(BankAccount $bankAccount): JsonResponse
    {
        $count = $this->service->autoMatchAccount($bankAccount);

        return response()->json(['matched_count' => $count]);
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
