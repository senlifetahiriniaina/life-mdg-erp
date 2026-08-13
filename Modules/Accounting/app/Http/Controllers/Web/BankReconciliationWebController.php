<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\JournalEntry;

class BankReconciliationWebController extends Controller
{
    public function index(): Response
    {
        $accounts = BankAccount::withCount([
            'transactions as unreconciled_count' => fn ($q) => $q->where('reconciled', false),
        ])->with('openBankingConnections')->get();

        return Inertia::render('Accounting/BankReconciliation/Index', [
            'accounts' => $accounts,
        ]);
    }

    public function reconcile(BankAccount $account): Response
    {
        $transactions = $account->transactions()
            ->with('journalEntry')
            ->latest('date')
            ->paginate(50);

        $glEntries = JournalEntry::with('lines')
            ->latest('date')
            ->limit(100)
            ->get();

        return Inertia::render('Accounting/BankReconciliation/Reconcile', [
            'account' => $account,
            'transactions' => $transactions,
            'glEntries' => $glEntries,
        ]);
    }
}
