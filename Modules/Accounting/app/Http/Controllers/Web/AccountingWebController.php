<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\ExchangeRate;
use Modules\Accounting\Models\ExpenseReport;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\OpenBankingConnection;

class AccountingWebController extends Controller
{
    public function journalEntries(Request $request): Response
    {
        $entries = JournalEntry::query()
            ->with(['journal:id,code,name', 'createdBy:id,name'])
            ->when($request->filled('journal_id'), fn ($q) => $q->where('journal_id', $request->journal_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('entry_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('entry_date', '<=', $request->date_to))
            ->latest('entry_date')
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($e) => [
                'id' => $e->id,
                'reference' => $e->reference,
                'journal' => $e->journal ? ['id' => $e->journal->id, 'code' => $e->journal->code, 'name' => $e->journal->name] : null,
                'entry_date' => $e->entry_date?->format('Y-m-d'),
                'description' => $e->description,
                'total_debit' => $e->total_debit,
                'status' => $e->status,
                'created_by' => $e->createdBy?->name,
            ]);

        $journals = Journal::select('id', 'code', 'name')->where('is_active', true)->get();

        $stats = [
            'total' => JournalEntry::count(),
            'draft' => JournalEntry::where('status', 'draft')->count(),
            'posted' => JournalEntry::where('status', 'posted')->count(),
        ];

        return Inertia::render('Accounting/JournalEntries/Index', [
            'entries' => $entries,
            'journals' => $journals,
            'stats' => $stats,
            'filters' => $request->only(['journal_id', 'status', 'date_from', 'date_to']),
        ]);
    }

    public function chartOfAccounts(Request $request): Response
    {
        $accounts = ChartOfAccount::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('search'), fn ($q) => $q->where('code', 'like', "%{$request->search}%")
                ->orWhere('name', 'like', "%{$request->search}%")
            )
            ->orderBy('code')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Accounting/ChartOfAccounts/Index', [
            'accounts' => $accounts,
            'filters' => $request->only(['type', 'search']),
        ]);
    }

    public function balanceSheet(Request $request): Response
    {
        return Inertia::render('Accounting/BalanceSheet', [
            'date' => $request->input('date', now()->format('Y-m-d')),
            'filters' => $request->only(['date']),
        ]);
    }

    public function incomeStatement(Request $request): Response
    {
        return Inertia::render('Accounting/IncomeStatement', [
            'date_from' => $request->input('date_from', now()->startOfYear()->format('Y-m-d')),
            'date_to' => $request->input('date_to', now()->format('Y-m-d')),
            'filters' => $request->only(['date_from', 'date_to']),
        ]);
    }

    public function expenses(Request $request): Response
    {
        $reports = ExpenseReport::query()
            ->with(['employee:id,first_name,last_name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($r) => [
                'id' => $r->id,
                'reference' => $r->reference,
                'employee_name' => $r->employee ? trim("{$r->employee->first_name} {$r->employee->last_name}") : '—',
                'total_amount' => $r->total_amount,
                'currency' => $r->currency,
                'status' => $r->status,
                'submitted_at' => $r->submitted_at?->format('M d, Y'),
            ]);

        $stats = [
            'total' => ExpenseReport::count(),
            'draft' => ExpenseReport::where('status', 'draft')->count(),
            'pending' => ExpenseReport::where('status', 'submitted')->count(),
            'approved' => ExpenseReport::where('status', 'approved')->count(),
        ];

        return Inertia::render('Accounting/Expenses/Index', [
            'reports' => $reports,
            'stats' => $stats,
            'filters' => $request->only(['status']),
        ]);
    }

    public function currency(Request $request): Response
    {
        $rates = ExchangeRate::query()
            ->when($request->filled('currency'), fn ($q) => $q->where('currency', $request->currency))
            ->latest('effective_date')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Accounting/Currency/Index', [
            'rates' => $rates,
            'filters' => $request->only(['currency']),
        ]);
    }

    public function openBanking(Request $request): Response
    {
        $connections = OpenBankingConnection::query()
            ->latest()
            ->get();

        return Inertia::render('Accounting/OpenBanking/Index', [
            'connections' => $connections,
        ]);
    }

    public function lettrage(Request $request): Response
    {
        return Inertia::render('Accounting/Lettrage', [
            'filters' => $request->only(['account_id', 'date_from', 'date_to']),
        ]);
    }
}
