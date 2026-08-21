<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\BudgetLine;
use Modules\Sales\Models\SalesObjective;

/**
 * Chantier 26 (volet C) — génère un budget pour l'ensemble de l'entreprise à
 * partir de l'historique réel du grand livre OHADA (acc_journal_entry_lines/
 * acc_journal_entries/acc_chart_of_accounts, comptes de charges classe 6 et
 * de produits classe 7), et le corrèle avec les objectifs commerciaux
 * validés (Chantier 26 volet B, Modules\Sales\Models\SalesObjective).
 *
 * Méthodologie, volontairement simple et documentée :
 *   - Dépenses (classe 6) : moyenne mensuelle historique réelle, appliquée
 *     à plat sur chaque mois de la période cible — aucune croissance
 *     inventée, un budget de dépenses doit rester une base prudente, pas
 *     une ambition (asymétrie volontaire avec le volet B, où l'ambition
 *     commerciale, elle, est délibérément choisie par l'utilisateur).
 *   - Revenus (classe 7) : si un ou plusieurs objectifs commerciaux
 *     globaux validés couvrent (totalement, à l'intérieur) la période du
 *     budget, leur somme remplace la simple moyenne historique — chaque
 *     compte de revenu est mis à l'échelle proportionnellement à son poids
 *     historique, garantissant par construction que la somme des lignes de
 *     revenu du budget est exactement égale à la somme des objectifs
 *     validés. Sans objectif validé, repli sur la moyenne historique brute
 *     (fallback-first, même principe que partout ailleurs dans cette app).
 */
class BudgetGenerationService
{
    private const LOOKBACK_MONTHS = 6;

    public function generateFromHistory(
        int $companyId,
        int $fiscalYear,
        ?Carbon $periodStart = null,
        ?Carbon $periodEnd = null,
        ?int $createdBy = null,
    ): Budget {
        $periodStart ??= Carbon::create($fiscalYear, 1, 1)->startOfDay();
        $periodEnd ??= Carbon::create($fiscalYear, 12, 31)->endOfDay();

        $lookbackFrom = $periodStart->copy()->subMonths(self::LOOKBACK_MONTHS)->startOfDay();
        $lookbackTo   = $periodStart->copy()->subDay()->endOfDay();

        $accounts     = $this->historicalMonthlyAveragePerAccount($lookbackFrom, $lookbackTo);
        $periodMonths = max(1, (int) ceil($periodStart->diffInDays($periodEnd) / 30));

        $totalHistoricalRevenue = 0.0;
        foreach ($accounts as $a) {
            if ($a['type'] === 'revenue') {
                $totalHistoricalRevenue += $a['avg_monthly'] * $periodMonths;
            }
        }

        $validatedRevenueTarget = $this->matchingValidatedRevenueTarget($companyId, $periodStart, $periodEnd);
        $revenueScale = ($validatedRevenueTarget !== null && $totalHistoricalRevenue > 0)
            ? $validatedRevenueTarget / $totalHistoricalRevenue
            : 1.0;

        $budget = Budget::create([
            'company_id'           => $companyId,
            'name'                 => "Budget {$fiscalYear} (généré depuis l'historique)",
            'description'          => $validatedRevenueTarget !== null
                ? "Dépenses projetées sur la moyenne historique des {$this->lookbackLabel()} précédents. Revenus corrélés aux objectifs commerciaux validés (".number_format($validatedRevenueTarget, 0, ',', ' ').")."
                : "Dépenses et revenus projetés sur la moyenne historique des {$this->lookbackLabel()} précédents — aucun objectif commercial validé ne couvre cette période.",
            'budget_period_start'  => $periodStart->toDateString(),
            'budget_period_end'    => $periodEnd->toDateString(),
            'fiscal_year'          => $fiscalYear,
            'fiscal_year_start'    => $periodStart->toDateString(),
            'fiscal_year_end'      => $periodEnd->toDateString(),
            'status'               => 'draft',
            'currency'             => $this->tenantCurrency($companyId),
            'created_by'           => $createdBy,
        ]);

        $totalRevenue = 0.0;
        $totalExpense = 0.0;

        foreach ($accounts as $accountId => $a) {
            $isRevenue    = $a['type'] === 'revenue';
            $monthlyAmount = $isRevenue ? $a['avg_monthly'] * $revenueScale : $a['avg_monthly'];

            for ($m = 0; $m < $periodMonths; $m++) {
                $monthDate = $periodStart->copy()->addMonths($m);

                // Chantier 26 (volet C) — real bug found empirically: BudgetLine::
                // $fillable lists 'budget_amount'/'cost_center_id'/'department_id'
                // as mass-assignable, but none of the three exist on the real
                // acc_budget_lines table (confirmed via Schema::getColumnListing) —
                // a guaranteed "no such column" the moment any real write ever
                // included them, which none had until this service's first real
                // insert. Only the real 'budgeted_amount' column is written here;
                // the model's own dead $fillable entries are left undisturbed
                // (out of this volet's scope), documented in CLAUDE.md.
                BudgetLine::create([
                    'budget_id'       => $budget->id,
                    'account_id'      => $accountId,
                    'budgeted_amount' => round($monthlyAmount, 2),
                    'category'        => $a['code'],
                    'description'     => $a['name'],
                    'period'          => $monthDate->format('Y-m'),
                    'month'           => $monthDate->format('F'),
                    'period_month'    => $monthDate->month,
                    'period_year'     => $monthDate->year,
                ]);

                $isRevenue ? $totalRevenue += $monthlyAmount : $totalExpense += $monthlyAmount;
            }
        }

        $budget->update([
            'total_revenue_budget' => round($totalRevenue, 2),
            'total_expense_budget' => round($totalExpense, 2),
            // Convention déjà établie par ce module (Budget::isOverBudget()/varianceSummary()) :
            // total_budget représente l'enveloppe de dépenses, comparée au réalisé.
            'total_budget'         => round($totalExpense, 2),
        ]);

        return $budget->fresh('lines');
    }

    /**
     * Moyenne mensuelle réelle par compte de charges/produits (classes 6/7),
     * sur la fenêtre [from, to]. Agrégée en PHP plutôt qu'en SQL — un
     * GROUP BY sur une expression de date portable entre SQLite (dev/test/
     * CI) et MySQL (prod) est le piège déjà documenté au Chantier 26 volet
     * A, évité ici de la même façon qu'au volet B.
     *
     * @return array<int, array{code: string, name: string, type: string, avg_monthly: float}>
     */
    private function historicalMonthlyAveragePerAccount(Carbon $from, Carbon $to): array
    {
        $months = max(1, $from->diffInMonths($to) + 1);

        $rows = DB::table('acc_journal_entry_lines as jel')
            ->join('acc_journal_entries as je', 'je.id', '=', 'jel.entry_id')
            ->join('acc_chart_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
            ->whereIn('coa.type', ['revenue', 'expense'])
            ->whereBetween('je.entry_date', [$from, $to])
            ->select('coa.id as account_id', 'coa.code', 'coa.name', 'coa.type', 'jel.debit', 'jel.credit')
            ->get();

        $accounts = [];
        foreach ($rows as $row) {
            $accounts[$row->account_id] ??= [
                'code' => $row->code,
                'name' => $row->name,
                'type' => $row->type,
                'total' => 0.0,
            ];

            // Convention OHADA : un compte de produit (classe 7) augmente au
            // crédit, un compte de charge (classe 6) augmente au débit.
            $accounts[$row->account_id]['total'] += $row->type === 'revenue'
                ? ((float) $row->credit - (float) $row->debit)
                : ((float) $row->debit - (float) $row->credit);
        }

        $result = [];
        foreach ($accounts as $accountId => $a) {
            $avgMonthly = $a['total'] / $months;
            // Un compte dont le solde moyen est négatif ou nul sur la période
            // (charges annulées, avoirs...) n'a rien de réel à budgéter.
            if ($avgMonthly > 0) {
                $result[$accountId] = [
                    'code'        => $a['code'],
                    'name'        => $a['name'],
                    'type'        => $a['type'],
                    'avg_monthly' => $avgMonthly,
                ];
            }
        }

        return $result;
    }

    /**
     * Somme des objectifs commerciaux globaux validés dont la période est
     * entièrement contenue dans la période du budget — couvre à la fois le
     * cas d'un objectif annuel unique et celui de plusieurs objectifs
     * mensuels/trimestriels validés successivement.
     */
    private function matchingValidatedRevenueTarget(int $companyId, Carbon $periodStart, Carbon $periodEnd): ?float
    {
        $sum = (float) SalesObjective::query()
            ->where('tenant_id', $companyId)
            ->where('scope', 'global')
            ->where('status', 'validated')
            ->whereDate('period_start', '>=', $periodStart)
            ->whereDate('period_end', '<=', $periodEnd)
            ->sum('target_amount');

        return $sum > 0 ? $sum : null;
    }

    private function tenantCurrency(int $companyId): string
    {
        return DB::table('companies')->where('id', $companyId)->value('currency') ?? 'MGA';
    }

    private function lookbackLabel(): string
    {
        return self::LOOKBACK_MONTHS.' mois';
    }
}
