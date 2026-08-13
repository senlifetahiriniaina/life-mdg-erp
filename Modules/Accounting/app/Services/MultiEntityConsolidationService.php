<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\ConsolidationEntry;
use Modules\Accounting\Models\ConsolidationElimination;
use Modules\Accounting\Models\ConsolidationHierarchy;
use Modules\Accounting\Models\ConsolidationPeriod;
use Illuminate\Support\Collection;
use Modules\Shared\Services\BaseService;

class MultiEntityConsolidationService extends BaseService
{
    /**
     * Create a new consolidation hierarchy for a parent-subsidiary structure
     */
    public function createHierarchy(array $data): ConsolidationHierarchy
    {
        return ConsolidationHierarchy::create($data);
    }

    /**
     * Create a consolidation period for a hierarchy
     */
    public function createPeriod(
        ConsolidationHierarchy $hierarchy,
        \Carbon\Carbon $periodStart,
        \Carbon\Carbon $periodEnd,
        string $frequency = 'monthly'
    ): ConsolidationPeriod {
        return $hierarchy->periods()->create([
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'frequency' => $frequency,
            'status' => 'draft',
        ]);
    }

    /**
     * Record trial balance entries for consolidation
     */
    public function recordTrialBalanceEntry(
        ConsolidationPeriod $period,
        int $companyId,
        int $glAccountId,
        array $balanceData
    ): ConsolidationEntry {
        return $period->entries()->create([
            'company_id' => $companyId,
            'gl_account_id' => $glAccountId,
            'opening_balance' => $balanceData['opening_balance'] ?? 0,
            'debit_amount' => $balanceData['debit_amount'] ?? 0,
            'credit_amount' => $balanceData['credit_amount'] ?? 0,
            'closing_balance' => $balanceData['closing_balance'] ?? 0,
            'consolidated_amount' => $balanceData['closing_balance'] ?? 0,
        ]);
    }

    /**
     * Create an elimination entry
     */
    public function createElimination(
        ConsolidationHierarchy $hierarchy,
        ConsolidationPeriod $period,
        string $eliminationType,
        int $glAccountId,
        float $debitAmount,
        float $creditAmount,
        array $calculationMethod = []
    ): ConsolidationElimination {
        return $hierarchy->eliminations()->create([
            'consolidation_period_id' => $period->id,
            'elimination_type' => $eliminationType,
            'gl_account_id' => $glAccountId,
            'debit_amount' => $debitAmount,
            'credit_amount' => $creditAmount,
            'calculation_method' => $calculationMethod,
            'is_manual' => !empty($calculationMethod),
        ]);
    }

    /**
     * Eliminate intercompany sales
     */
    public function eliminateIntercompanySales(
        ConsolidationPeriod $period,
        float $salesAmount,
        float $costOfGoodsAmount,
        int $salesAccountId,
        int $costAccountId
    ): void {
        $hierarchy = $period->hierarchy;

        // Eliminate intercompany sales
        $this->createElimination(
            $hierarchy,
            $period,
            'intercompany_sales',
            $salesAccountId,
            $salesAmount,
            0,
            ['type' => 'sales', 'direction' => 'debit']
        );

        // Eliminate intercompany cost
        $this->createElimination(
            $hierarchy,
            $period,
            'intercompany_sales',
            $costAccountId,
            0,
            $costOfGoodsAmount,
            ['type' => 'cost', 'direction' => 'credit']
        );
    }

    /**
     * Calculate minority interest
     */
    public function calculateMinorityInterest(ConsolidationPeriod $period): float
    {
        $hierarchy = $period->hierarchy;

        if (!$hierarchy->isHolding()) {
            return 0;
        }

        $minorityInterest = $hierarchy->minorityInterest();
        $equityAmount = $period->entries()
            ->whereHas('glAccount', fn ($q) => $q->whereIn('account_type', ['equity', 'retained_earnings']))
            ->sum('consolidated_amount');

        return (float)$equityAmount * $minorityInterest;
    }

    /**
     * Consolidate all entries for a period
     */
    public function consolidatePeriod(ConsolidationPeriod $period): void
    {
        // Get all companies in the hierarchy
        $hierarchy = $period->hierarchy;
        $companies = $hierarchy->company->allSubsidiaries();

        // Consolidate entries for each GL account
        $entries = $period->entries()
            ->with('glAccount')
            ->get()
            ->groupBy('gl_account_id');

        foreach ($entries as $accountId => $accountEntries) {
            $totalConsolidated = 0;

            /** @var ConsolidationEntry $entry */
            foreach ($accountEntries as $entry) {
                $company = $companies->find($entry->company_id);
                if ($company) {
                    $ownershipPercent = $this->getOwnershipPercentage($hierarchy, $company);
                    $consolidatedAmount = (float)$entry->getUnadjustedBalance() * ($ownershipPercent / 100);
                    $entry->update(['consolidated_amount' => $consolidatedAmount]);
                    $totalConsolidated += $consolidatedAmount;
                }
            }

            // Apply eliminations
            $eliminations = $period->eliminations()
                ->where('gl_account_id', $accountId)
                ->get();

            foreach ($eliminations as $elimination) {
                $totalConsolidated += (float)$elimination->debit_amount - (float)$elimination->credit_amount;
            }
        }

        // Mark period as completed
        $period->markCompleted(auth()->id() ?? 0);
    }

    /**
     * Get ownership percentage for a subsidiary in the hierarchy
     */
    private function getOwnershipPercentage(ConsolidationHierarchy $hierarchy, $company): float
    {
        if ($hierarchy->company_id === $company->id) {
            return 100;
        }

        return (float)$hierarchy->ownership_percentage;
    }

    /**
     * Generate consolidated financial statements
     */
    public function generateConsolidatedStatements(ConsolidationPeriod $period): array
    {
        $entries = $period->entries()->with('glAccount')->get();

        $balanceSheetAccounts = $entries->filter(
            fn ($e) => in_array($e->glAccount->account_type, ['asset', 'liability', 'equity'])
        );

        $incomeStatementAccounts = $entries->filter(
            fn ($e) => in_array($e->glAccount->account_type, ['revenue', 'expense'])
        );

        return [
            'balance_sheet' => $this->formatBalanceSheet($balanceSheetAccounts),
            'income_statement' => $this->formatIncomeStatement($incomeStatementAccounts),
            'cash_flow' => $this->formatCashFlow($entries),
            'consolidation_date' => $period->period_end,
            'minority_interest' => $this->calculateMinorityInterest($period),
        ];
    }

    private function formatBalanceSheet(Collection $accounts): array
    {
        $assets = $accounts->where('glAccount.account_type', 'asset')->sum('consolidated_amount');
        $liabilities = $accounts->where('glAccount.account_type', 'liability')->sum('consolidated_amount');
        $equity = $accounts->where('glAccount.account_type', 'equity')->sum('consolidated_amount');

        return [
            'total_assets' => $assets,
            'total_liabilities' => $liabilities,
            'total_equity' => $equity,
            'balance_check' => ($assets - $liabilities - $equity) < 0.01,
        ];
    }

    private function formatIncomeStatement(Collection $accounts): array
    {
        $revenue = $accounts->where('glAccount.account_type', 'revenue')->sum('consolidated_amount');
        $expenses = $accounts->where('glAccount.account_type', 'expense')->sum('consolidated_amount');

        return [
            'total_revenue' => $revenue,
            'total_expenses' => $expenses,
            'net_income' => $revenue - $expenses,
        ];
    }

    private function formatCashFlow(Collection $accounts): array
    {
        return [
            'operating_activities' => 0,
            'investing_activities' => 0,
            'financing_activities' => 0,
        ];
    }
}
