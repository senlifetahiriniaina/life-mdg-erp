<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Budget;
use Modules\Sales\Models\SalesObjective;
use Modules\Sales\Models\SalesOrder;

/**
 * Chantier 26 (volet D) — revue finance mensuelle/trimestrielle. Calcule EN
 * DIRECT (jamais stocké) la réalisation des objectifs commerciaux validés
 * (Modules\Sales\Models\SalesObjective, volet B) et la réalisation d'un
 * budget généré (Modules\Accounting\Models\Budget, volet C) sur une
 * période de revue donnée — jamais via BudgetLine::actual_amount, qui n'est
 * alimenté par aucun chemin d'écriture réel dans cette application
 * (confirmé par investigation avant construction, voir CLAUDE.md).
 */
class FinanceReviewService
{
    /**
     * Réalisation des objectifs commerciaux validés dont la période
     * chevauche la période de revue — chiffre d'affaires réel (vraies
     * SalesOrder confirmées) comparé à une cible proratisée sur
     * l'intersection des deux périodes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function objectiveRealization(int $tenantId, Carbon $reviewStart, Carbon $reviewEnd): array
    {
        $objectives = SalesObjective::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'validated')
            ->whereDate('period_start', '<=', $reviewEnd)
            ->whereDate('period_end', '>=', $reviewStart)
            ->get();

        $results = [];
        foreach ($objectives as $objective) {
            $intersectStart = $objective->period_start->greaterThan($reviewStart) ? $objective->period_start : $reviewStart;
            $intersectEnd   = $objective->period_end->lessThan($reviewEnd) ? $objective->period_end : $reviewEnd;

            if ($intersectStart->greaterThan($intersectEnd)) {
                continue;
            }

            $totalDays       = max(1, $objective->period_start->diffInDays($objective->period_end) + 1);
            $intersectDays   = $intersectStart->diffInDays($intersectEnd) + 1;
            $proratedTarget  = round((float) $objective->target_amount * ($intersectDays / $totalDays), 2);

            $actual = $this->actualRevenue($tenantId, $objective->scope, $objective->scope_ref_id, $intersectStart, $intersectEnd);

            $results[] = [
                'objective_id'        => $objective->id,
                'scope'                => $objective->scope,
                'scope_ref_id'         => $objective->scope_ref_id,
                'proposal_label'       => $objective->proposal_label,
                'intersection_start'   => $intersectStart->toDateString(),
                'intersection_end'     => $intersectEnd->toDateString(),
                'prorated_target'      => $proratedTarget,
                'actual'               => round($actual, 2),
                'realization_percent'  => $proratedTarget > 0 ? round($actual / $proratedTarget * 100, 1) : null,
                'currency'             => $objective->currency,
            ];
        }

        return $results;
    }

    private function actualRevenue(int $tenantId, string $scope, ?int $scopeRefId, Carbon $from, Carbon $to): float
    {
        if ($scope === 'category') {
            return (float) DB::table('sales_order_lines as sol')
                ->join('sales_orders as so', 'so.id', '=', 'sol.sales_order_id')
                ->join('inventory_products as p', 'p.id', '=', 'sol.product_id')
                ->where('so.tenant_id', $tenantId)
                ->whereNotIn('so.status', ['cancelled', 'returned'])
                ->whereBetween('so.confirmed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->when($scopeRefId, fn ($q) => $q->where('p.category_id', $scopeRefId))
                ->sum('sol.line_total');
        }

        $query = SalesOrder::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled', 'returned'])
            ->whereBetween('confirmed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        match ($scope) {
            'rep'    => $query->where('sales_rep_id', $scopeRefId),
            'client' => $query->where('contact_id', $scopeRefId),
            default  => null, // 'global'
        };

        return (float) $query->sum('total');
    }

    /**
     * Réalisation d'un budget sur la période de revue — pour chaque ligne
     * de budget dont le mois/année tombe dans la période, calcule le
     * mouvement RÉEL du compte concerné sur le grand livre OHADA (jamais
     * BudgetLine::actual_amount, non alimenté). Convention déjà établie
     * (BudgetGenerationService) : un compte de produit (classe 7) augmente
     * au crédit, un compte de charge (classe 6) augmente au débit.
     *
     * @return array<int, array<string, mixed>>
     */
    public function budgetRealization(Budget $budget, Carbon $reviewStart, Carbon $reviewEnd): array
    {
        $lines = $budget->lines()
            ->get()
            ->filter(function ($line) use ($reviewStart, $reviewEnd) {
                if (! $line->period_year || ! $line->period_month) {
                    return false;
                }
                $lineDate = Carbon::create((int) $line->period_year, (int) $line->period_month, 1)->startOfMonth();

                return $lineDate->between($reviewStart->copy()->startOfMonth(), $reviewEnd->copy()->endOfMonth());
            });

        $results = [];
        foreach ($lines as $line) {
            $account = DB::table('acc_chart_of_accounts')->where('id', $line->account_id)->first(['code', 'name', 'type']);
            if (! $account) {
                continue;
            }

            $lineFrom = Carbon::create((int) $line->period_year, (int) $line->period_month, 1)->startOfMonth();
            $lineTo   = $lineFrom->copy()->endOfMonth();

            $movement = DB::table('acc_journal_entry_lines as jel')
                ->join('acc_journal_entries as je', 'je.id', '=', 'jel.entry_id')
                ->where('jel.account_id', $line->account_id)
                ->whereBetween('je.entry_date', [$lineFrom, $lineTo])
                ->selectRaw('COALESCE(SUM(jel.debit),0) as total_debit, COALESCE(SUM(jel.credit),0) as total_credit')
                ->first();

            $actual = $account->type === 'revenue'
                ? (float) $movement->total_credit - (float) $movement->total_debit
                : (float) $movement->total_debit - (float) $movement->total_credit;

            $budgeted = (float) $line->budgeted_amount;

            $results[] = [
                'budget_line_id'      => $line->id,
                'account_code'         => $account->code,
                'account_name'         => $account->name,
                'account_type'         => $account->type,
                'period'               => $lineFrom->format('Y-m'),
                'budgeted_amount'      => round($budgeted, 2),
                'actual_amount'        => round($actual, 2),
                'variance'             => round($actual - $budgeted, 2),
                'realization_percent'  => $budgeted > 0 ? round($actual / $budgeted * 100, 1) : null,
            ];
        }

        return $results;
    }
}
