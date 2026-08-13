<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\Expense;
use Modules\Accounting\Models\GLAccount;
use Modules\Accounting\Models\GLJournal;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\Reconciliation;

class AccountingService
{
    // GL Account Management
    public function createGLAccount(array $data): GLAccount
    {
        return GLAccount::create($data);
    }

    public function updateGLAccount(GLAccount $account, array $data): GLAccount
    {
        $account->update($data);

        return $account;
    }

    public function getAllGLAccounts($perPage = 15)
    {
        return GLAccount::active()
            ->orderBy('account_number')
            ->paginate($perPage);
    }

    public function getGLAccountsByType($type, $perPage = 15)
    {
        return GLAccount::byType($type)
            ->active()
            ->paginate($perPage);
    }

    // Invoice Management
    public function createInvoice(array $data): Invoice
    {
        $data['created_by'] = auth()->id();

        return Invoice::create($data);
    }

    public function updateInvoice(Invoice $invoice, array $data): Invoice
    {
        $invoice->update($data);

        return $invoice;
    }

    public function getAllInvoices($perPage = 15)
    {
        return Invoice::orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getOutstandingInvoices()
    {
        return Invoice::outstanding()->get();
    }

    public function getOverdueInvoices()
    {
        return Invoice::overdue()->get();
    }

    public function recordPayment(Invoice $invoice, $amount): Invoice
    {
        $newPaidAmount = min($invoice->amount_paid + $amount, $invoice->total);

        $invoice->update([
            'amount_paid' => $newPaidAmount,
            'status' => $newPaidAmount >= $invoice->total ? 'paid' : 'received',
        ]);

        return $invoice;
    }

    // Expense Management
    public function recordExpense(array $data): Expense
    {
        $data['created_by'] = auth()->id();

        return Expense::create($data);
    }

    public function approveExpense(Expense $expense, int $approverId): Expense
    {
        $expense->update([
            'status' => 'approved',
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);

        return $expense;
    }

    public function getExpensesByCategory($category, $perPage = 15)
    {
        return Expense::where('category', $category)
            ->with('glAccount')
            ->orderBy('expense_date', 'desc')
            ->paginate($perPage);
    }

    public function getPendingExpenses()
    {
        return Expense::where('status', 'recorded')
            ->with('glAccount')
            ->get();
    }

    // Journal Entry Management

    public function postJournal(GLJournal $journal): GLJournal
    {
        if ($journal->status === 'posted') {
            throw new \LogicException('Journal is already posted.');
        }
        $journal->status = 'posted';
        $journal->save();

        // Create audit trail
        try {
            \Modules\Accounting\Models\AuditLog::log(
                'GLJournal',
                $journal->id,
                'post',
                null,
                ['status' => 'posted']
            );
        } catch (\Throwable $e) {
            // Audit log failure should not block posting
        }

        return $journal;
    }

    public function postJournalEntry(array $data): JournalEntry
    {
        return JournalEntry::create($data);
    }

    public function getJournalEntriesByAccount(GLAccount $account)
    {
        return $account->journalEntries()
            ->orderBy('entry_date', 'desc')
            ->get();
    }

    // Budget Management
    public function createBudget(array $data): Budget
    {
        $data['created_by'] = auth()->id();

        return Budget::create($data);
    }

    public function updateBudget(Budget $budget, array $data): Budget
    {
        $budget->update($data);

        return $budget;
    }

    public function getBudgetsByYear($year, $perPage = 15)
    {
        return Budget::where('fiscal_year', $year)
            ->with('glAccount')
            ->paginate($perPage);
    }

    public function getOverBudgetItems($year)
    {
        return Budget::where('fiscal_year', $year)
            ->whereRaw('total_expense_budget > total_revenue_budget')
            ->get();
    }

    // Reconciliation
    public function createReconciliation(array $data): Reconciliation
    {
        $data['difference'] = abs($data['book_balance'] - $data['bank_balance']);

        return Reconciliation::create($data);
    }

    public function getReconciliationsByAccount(GLAccount $account)
    {
        return $account->reconciliations()
            ->orderBy('reconciliation_date', 'desc')
            ->get();
    }

    // Financial Reports
    public function getFinancialMetrics()
    {
        $assets = GLAccount::byType('asset')->sum('balance');
        $liabilities = GLAccount::byType('liability')->sum('balance');
        $equity = GLAccount::byType('equity')->sum('balance');
        $revenue = GLAccount::byType('revenue')->sum('balance');
        $expenses = GLAccount::byType('expense')->sum('balance');

        return [
            'total_assets' => $assets,
            'total_liabilities' => $liabilities,
            'total_equity' => $equity,
            'total_revenue' => $revenue,
            'total_expenses' => $expenses,
            'net_income' => $revenue - $expenses,
            'outstanding_invoices_count' => Invoice::outstanding()->count(),
            'outstanding_invoices_total' => Invoice::outstanding()->sum('total_amount'),
            'pending_expenses_count' => Expense::where('status', 'recorded')->count(),
        ];
    }

    public function getIncomeStatement($startDate, $endDate)
    {
        $revenue = JournalEntry::where('entry_type', 'credit')
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->sum('amount');

        $expenses = JournalEntry::where('entry_type', 'debit')
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->sum('amount');

        return [
            'revenue' => $revenue,
            'expenses' => $expenses,
            'net_income' => $revenue - $expenses,
        ];
    }

    public function getBalanceSheet()
    {
        return [
            'assets' => GLAccount::byType('asset')->get(),
            'liabilities' => GLAccount::byType('liability')->get(),
            'equity' => GLAccount::byType('equity')->get(),
        ];
    }
}
