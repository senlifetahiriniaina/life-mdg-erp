<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Http\Requests\MatchTransactionRequest;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\BankTransaction;
use Modules\Accounting\Services\BankReconciliationService;

/**
 * @group Accounting
 *
 * Manage BankTransaction resources in Accounting module.
 */
class BankTransactionController extends Controller
{
    public function __construct(
        private readonly BankReconciliationService $service,
    ) {}

    public function index(Request $request, BankAccount $bankAccount): JsonResponse
    {
        $query = BankTransaction::where('bank_account_id', $bankAccount->id)
            ->when($request->boolean('unreconciled'), fn ($q) => $q->where('reconciled', false))
            ->when($request->reconciled !== null && ! $request->boolean('unreconciled'),
                fn ($q) => $q->where('reconciled', $request->boolean('reconciled')))
            ->when($request->from, fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('date', '<=', $v));

        return response()->json($query->latest('date')->paginate(50));
    }

    public function match(Request $request, BankTransaction $transaction): JsonResponse
    {
        $validated = $request->validate([
            'entry_id' => 'required|integer',
        ]);

        $transaction = $this->service->matchTransaction($transaction, (int) $validated['entry_id']);

        return response()->json($transaction->fresh());
    }

    public function unmatch(Request $request): JsonResponse
    {
        $request->validate([
            'transaction_id' => ['required', 'exists:acc_bank_transactions,id'],
        ]);

        /** @var BankTransaction $tx */
        $tx = BankTransaction::findOrFail($request->transaction_id);

        $tx->update([
            'journal_entry_id' => null,
            'reconciled' => false,
            'reconciled_at' => null,
            'reconciled_by' => null,
        ]);

        return response()->json($tx->fresh());
    }

    public function ignore(Request $request, BankTransaction $transaction): JsonResponse
    {
        $transaction = $this->service->ignoreTransaction($transaction);

        return response()->json($transaction->fresh());
    }
}
