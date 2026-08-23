<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\FinancialSimulation;
use Modules\Accounting\Models\FinancialSimulationLine;
use Modules\Accounting\Models\JournalEntry;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Services\PurchaseOrderService;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Services\SalesService;

/**
 * Chantier 18 — upmetrics-style financial simulation: bottom-up projection of
 * manually-entered future sale/purchase assumptions into a period-by-period
 * Compte de résultat / Trésorerie / Bilan simplifié, plus a "realize" action
 * that turns one simulated line into a real SalesOrder/PurchaseOrder + a
 * balanced journal entry.
 *
 * Simplifications, documented rather than silently guessed (matching
 * ScenarioPlanningService's own existing "no fixed/variable cost split or
 * cash-timing model" caveat):
 *  - Cash settles immediately (no AR/AP timing lag).
 *  - The projected Bilan only rolls forward Trésorerie + Capitaux propres;
 *    every other rubrique ("autres actifs"/"autres passifs") is held flat
 *    at today's real value. It still balances by construction, since the
 *    real anchor (FinancialReportService::balanceSheet(today)) already
 *    balances and both sides are shifted by the same cumulative net result.
 *  - realizeLine() posts HT-only (no VAT split), matching TreasuryImportService.
 */
class FinancialSimulationService
{
    public function __construct(
        protected FinancialReportService $reportService,
        protected SalesService $salesService,
        protected PurchaseOrderService $purchaseOrderService,
        protected AccountRoleService $accountRoles,
    ) {
    }

    /**
     * Project a simulation across its full horizon.
     */
    public function project(FinancialSimulation $simulation): array
    {
        $simulation->loadMissing('lines');

        $periods = $this->buildPeriodBuckets($simulation);
        $occurrences = $simulation->lines->map(fn (FinancialSimulationLine $line) => [
            'line' => $line,
            'occurrences' => $line->status === 'realized' ? [] : $this->expandOccurrences($line, $periods),
        ]);

        $openingCash = $simulation->opening_cash_balance !== null
            ? (float) $simulation->opening_cash_balance
            : (float) BankAccount::where('is_active', true)->sum('current_balance');

        $baseline = $this->reportService->balanceSheet(Carbon::today());
        $baselineAssets = (float) $baseline['assets']['total'];
        $baselineLiabilities = (float) $baseline['liabilities']['total'];
        $baselineEquity = (float) $baseline['equity']['total'];
        $otherAssets = $baselineAssets - $openingCash;

        $runningCash = $openingCash;
        $cumulativeResult = 0.0;
        $result = [];

        foreach ($periods as $period) {
            $revenue = 0.0;
            $expenses = 0.0;

            foreach ($occurrences as $entry) {
                /** @var FinancialSimulationLine $line */
                $line = $entry['line'];
                foreach ($entry['occurrences'] as $occ) {
                    if ($occ['date']->between($period['start'], $period['end'])) {
                        if ($line->isSale()) {
                            $revenue += $occ['amount'];
                        } else {
                            $expenses += $occ['amount'];
                        }
                    }
                }
            }

            $netResult = $revenue - $expenses;
            $cumulativeResult += $netResult;
            $openingCashPeriod = $runningCash;
            $runningCash += $netResult;

            $capitauxPropres = $baselineEquity + $cumulativeResult;
            $actifTotal = $runningCash + $otherAssets;
            $passifTotal = $capitauxPropres + $baselineLiabilities;

            $result[] = [
                'label' => $period['label'],
                'start' => $period['start']->toDateString(),
                'end' => $period['end']->toDateString(),
                'compte_de_resultat' => [
                    'chiffre_affaires' => round($revenue, 2),
                    'charges' => round($expenses, 2),
                    'resultat_net' => round($netResult, 2),
                    'resultat_cumule' => round($cumulativeResult, 2),
                ],
                'tresorerie' => [
                    'ouverture' => round($openingCashPeriod, 2),
                    'fermeture' => round($runningCash, 2),
                ],
                'bilan_simplifie' => [
                    'actif' => [
                        'tresorerie' => round($runningCash, 2),
                        'autres_actifs' => round($otherAssets, 2),
                        'total' => round($actifTotal, 2),
                    ],
                    'passif' => [
                        'capitaux_propres' => round($capitauxPropres, 2),
                        'autres_passifs' => round($baselineLiabilities, 2),
                        'total' => round($passifTotal, 2),
                    ],
                    'equilibre' => abs($actifTotal - $passifTotal) < 0.01,
                ],
            ];
        }

        return [
            'simulation' => [
                'id' => $simulation->id,
                'name' => $simulation->name,
                'granularity' => $simulation->granularity,
                'start_date' => $simulation->start_date->toDateString(),
                'horizon_periods' => $simulation->horizon_periods,
            ],
            'anchor' => [
                'as_of' => Carbon::today()->toDateString(),
                'opening_cash' => round($openingCash, 2),
                'baseline_assets' => round($baselineAssets, 2),
                'baseline_liabilities' => round($baselineLiabilities, 2),
                'baseline_equity' => round($baselineEquity, 2),
            ],
            'periods' => $result,
        ];
    }

    /**
     * Turn a simulated line into a real order + a balanced journal entry.
     *
     * @return array{line: FinancialSimulationLine, journal_entry: JournalEntry}
     */
    public function realizeLine(FinancialSimulationLine $line, int $companyId, int $userId): array
    {
        if ($line->status !== 'simulated') {
            throw new \RuntimeException("Only simulated lines can be realized. Current status: {$line->status}");
        }

        $accountCode = $line->effectiveCounterpartAccountCode();
        if (! $accountCode) {
            throw new \RuntimeException('No counterpart chart-of-account code — set one on the line or on its product\'s category.');
        }

        $counterpartAccount = ChartOfAccount::where('code', $accountCode)->first();
        if (! $counterpartAccount) {
            throw new \RuntimeException("Chart of accounts has no account with code {$accountCode}.");
        }

        $unitPrice = $line->effectiveUnitPrice();
        $amount = round((float) $line->quantity * $unitPrice, 2);
        if ($amount <= 0) {
            throw new \RuntimeException('Cannot realize a line with a zero or negative amount — set a price on the line or its product.');
        }

        return DB::transaction(function () use ($line, $companyId, $userId, $counterpartAccount, $amount, $unitPrice) {
            if ($line->isSale()) {
                $order = $this->salesService->createOrder([
                    'tenant_id' => $companyId,
                    'created_by' => $userId,
                    'contact_id' => $line->contact_id,
                    'notes' => "Réalisé depuis la simulation financière #{$line->financial_simulation_id}",
                    'lines' => [[
                        'product_id' => $line->product_id,
                        'description' => $line->label ?? $line->product?->name ?? 'Vente simulée',
                        'quantity' => (float) $line->quantity,
                        'unit_price' => $unitPrice,
                    ]],
                ]);
                $order = $this->salesService->confirmOrder($order);
                $realizedType = SalesOrder::class;
                $realizedId = $order->id;

                // Chantier 37 : compte résolu via AccountRoleService, configurable
                // par tenant (même valeur par défaut que le remap Chantier 36, '41').
                $clientsAccount = $this->accountRoles->resolveAccount('default_clients_account');
                $entry = $this->postJournalEntry(
                    description: "Vente réalisée depuis simulation — {$order->reference}",
                    debitAccountId: $clientsAccount->id,
                    creditAccountId: $counterpartAccount->id,
                    amount: $amount,
                    referenceType: SalesOrder::class,
                    referenceId: $order->id,
                    userId: $userId,
                );
            } else {
                $order = $this->purchaseOrderService->createPurchaseOrder([
                    'supplier_id' => $line->supplier_id,
                    'requested_by' => $userId,
                    'notes' => "Réalisé depuis la simulation financière #{$line->financial_simulation_id}",
                    'subtotal' => $amount,
                    'tax_amount' => 0,
                    'shipping_cost' => 0,
                    'total' => $amount,
                ]);
                $this->purchaseOrderService->addLineItem($order, [
                    'product_id' => $line->product_id,
                    'description' => $line->label ?? $line->product?->name ?? 'Achat simulé',
                    'quantity' => (float) $line->quantity,
                    'unit_price' => $unitPrice,
                ]);
                $realizedType = PurchaseOrder::class;
                $realizedId = $order->id;

                // Chantier 37 : compte résolu via AccountRoleService, configurable
                // par tenant (même valeur par défaut que le remap Chantier 36, '40').
                $suppliersAccount = $this->accountRoles->resolveAccount('default_suppliers_account');
                $entry = $this->postJournalEntry(
                    description: "Achat réalisé depuis simulation — {$order->po_number}",
                    debitAccountId: $counterpartAccount->id,
                    creditAccountId: $suppliersAccount->id,
                    amount: $amount,
                    referenceType: PurchaseOrder::class,
                    referenceId: $order->id,
                    userId: $userId,
                );
            }

            $line->update([
                'status' => 'realized',
                'realized_at' => now(),
                'realized_type' => $realizedType,
                'realized_id' => $realizedId,
            ]);

            return ['line' => $line->fresh(), 'journal_entry' => $entry];
        });
    }

    /**
     * Balanced 2-line journal entry, same resolve-and-post pattern as
     * JournalEntryApiController::store()/TreasuryImportService (Chantier 15).
     */
    private function postJournalEntry(
        string $description,
        ?int $debitAccountId,
        ?int $creditAccountId,
        float $amount,
        string $referenceType,
        int $referenceId,
        int $userId,
    ): JournalEntry {
        if (! $debitAccountId || ! $creditAccountId) {
            // In practice always caught earlier by AccountRoleService::resolveAccount()
            // (Chantier 37) for the two callers above — kept as defense-in-depth
            // for postJournalEntry()'s own contract.
            throw new \RuntimeException('Missing a required chart-of-account — check the accounting role settings (Comptabilité > Comptes de rôle).');
        }

        $entry = JournalEntry::create([
            'entry_number' => 'SIM-' . now()->format('YmdHis') . '-' . $referenceId,
            'date' => now()->toDateString(),
            'description' => $description,
            'status' => 'posted',
            'currency' => 'MGA',
            'posted_at' => now(),
            'created_by' => $userId,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);

        $entry->lines()->create([
            'account_id' => $debitAccountId,
            'description' => $description,
            'debit' => $amount,
            'credit' => 0,
            'currency' => 'MGA',
        ]);
        $entry->lines()->create([
            'account_id' => $creditAccountId,
            'description' => $description,
            'debit' => 0,
            'credit' => $amount,
            'currency' => 'MGA',
        ]);

        return $entry;
    }

    /**
     * @return array<int, array{start: Carbon, end: Carbon, label: string}>
     */
    private function buildPeriodBuckets(FinancialSimulation $simulation): array
    {
        $buckets = [];
        $cursor = Carbon::parse($simulation->start_date);

        for ($i = 0; $i < $simulation->horizon_periods; $i++) {
            if ($simulation->granularity === 'week') {
                $start = $cursor->copy();
                $end = $cursor->copy()->addDays(6);
                $label = 'Semaine du ' . $start->format('d/m/Y');
                $cursor = $cursor->copy()->addDays(7);
            } else {
                $start = $cursor->copy()->startOfMonth();
                $end = $cursor->copy()->endOfMonth();
                $label = ucfirst($start->translatedFormat('F Y'));
                $cursor = $cursor->copy()->addMonthNoOverflow()->startOfMonth();
            }

            $buckets[] = ['start' => $start, 'end' => $end, 'label' => $label];
        }

        return $buckets;
    }

    /**
     * Expand a line's recurrence into individual dated occurrences across
     * the simulation's full horizon, applying growth_rate_percent per cycle.
     *
     * @param  array<int, array{start: Carbon, end: Carbon, label: string}>  $periods
     * @return array<int, array{date: Carbon, amount: float}>
     */
    private function expandOccurrences(FinancialSimulationLine $line, array $periods): array
    {
        if (empty($periods)) {
            return [];
        }

        $horizonEnd = end($periods)['end'];
        $lineEnd = $line->end_date ? Carbon::parse($line->end_date) : $horizonEnd;
        $effectiveEnd = $lineEnd->lessThan($horizonEnd) ? $lineEnd : $horizonEnd;

        $baseAmount = (float) $line->quantity * $line->effectiveUnitPrice();
        $growthRate = (float) $line->growth_rate_percent / 100;

        $occurrences = [];
        $date = Carbon::parse($line->start_date);
        $cycle = 0;

        if ($line->recurrence === 'once') {
            if ($date->lessThanOrEqualTo($effectiveEnd)) {
                $occurrences[] = ['date' => $date, 'amount' => round($baseAmount, 2)];
            }

            return $occurrences;
        }

        while ($date->lessThanOrEqualTo($effectiveEnd)) {
            $amount = $baseAmount * ((1 + $growthRate) ** $cycle);
            $occurrences[] = ['date' => $date->copy(), 'amount' => round($amount, 2)];

            $date = $line->recurrence === 'weekly'
                ? $date->copy()->addDays(7)
                : $date->copy()->addMonthNoOverflow();
            $cycle++;
        }

        return $occurrences;
    }
}
