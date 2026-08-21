<?php

namespace Modules\Analytics\Services\Forecasting;

use Illuminate\Support\Facades\DB;
use Modules\Analytics\Services\ForecastingEngineService;

/**
 * Service de prévision de trésorerie.
 *
 * Africa First / OHADA :
 *   - Classe 5 Trésorerie (OHADA SYSCOHADA révisé)
 *   - Devises : XOF (UEMOA), XAF (CEMAC), plus toute devise configurée par le tenant
 *   - Prise en compte du Mobile Money (Orange Money, Wave, MTN MoMo)
 */
class CashflowForecastService
{
    /**
     * Nombre de jours d'historique utilisés pour estimer un flux quotidien
     * moyen (encaissement/décaissement) hors factures/paie connues —
     * 180 jours ≈ 6 mois, la base minimale demandée pour ce module.
     */
    private const TREASURY_LOOKBACK_DAYS = 180;

    public function __construct(private readonly ForecastingEngineService $engine) {}

    /**
     * Prévision de trésorerie sur un horizon donné (90 jours par défaut).
     *
     * @return array{daily: array, summary: array, gaps: array, narrative: string}
     */
    public function forecast90Days(int $tenantId, int $days = 90): array
    {
        $projection = $this->getDailyProjection($tenantId, $days);
        $gaps       = $this->detectGaps($tenantId, days: $days);
        $ohada      = $this->getOhadaProjection($tenantId);

        $values          = array_column($projection, 'running_balance');
        $minBalance      = count($values) > 0 ? min($values) : 0.0;
        $maxBalance      = count($values) > 0 ? max($values) : 0.0;
        $finalBalance    = count($values) > 0 ? end($values) : 0.0;
        $totalInflow     = array_sum(array_column($projection, 'inflow'));
        $totalOutflow    = array_sum(array_column($projection, 'outflow'));

        return [
            'tenant_id'       => $tenantId,
            'horizon_days'    => $days,
            'daily'           => $projection,
            'gaps'            => $gaps,
            'ohada'           => $ohada,
            'summary'         => [
                'total_inflow'    => round($totalInflow, 2),
                'total_outflow'   => round($totalOutflow, 2),
                'net_cashflow'    => round($totalInflow - $totalOutflow, 2),
                'min_balance'     => round($minBalance, 2),
                'max_balance'     => round($maxBalance, 2),
                'final_balance'   => round($finalBalance, 2),
                'has_deficit'     => $minBalance < 0,
                'currency'        => $this->getTenantCurrency($tenantId),
            ],
            'narrative'       => $this->buildNarrative($projection, $gaps, $this->getTenantCurrency($tenantId)),
        ];
    }

    /**
     * Projection journalière avec solde cumulatif.
     *
     * @return array<int, array{date: string, inflow: float, outflow: float, net: float, running_balance: float}>
     */
    public function getDailyProjection(int $tenantId, int $days = 90): array
    {
        $startBalance = $this->getCurrentBalance($tenantId);

        // Entrées prévisibles : factures en attente de paiement
        $expectedInflows = $this->getExpectedInflows($tenantId, $days);

        // Sorties récurrentes : salaires, loyers, abonnements
        $recurringOutflows = $this->getRecurringOutflows($tenantId, $days);

        // Sorties prévues : factures fournisseurs à payer
        $expectedOutflows = $this->getExpectedOutflows($tenantId, $days);

        // Flux quotidien moyen (hors factures/paie connues) — moyenne
        // réelle sur les 6 derniers mois du grand livre, calculée une seule
        // fois plutôt qu'à chaque itération de la boucle ci-dessous.
        $avgDailyInflow  = $this->estimateDailyInflow($tenantId);
        $avgDailyOutflow = $this->estimateDailyOutflow($tenantId);

        $projection = [];
        $balance    = $startBalance;

        for ($i = 1; $i <= $days; $i++) {
            $date         = now()->addDays($i)->toDateString();
            $inflow       = (float) ($expectedInflows[$date] ?? $avgDailyInflow);
            $knownOutflow = (float) ($recurringOutflows[$date] ?? 0) + (float) ($expectedOutflows[$date] ?? 0);
            $outflow      = $knownOutflow > 0 ? $knownOutflow : $avgDailyOutflow;
            $net          = $inflow - $outflow;
            $balance     += $net;

            $projection[] = [
                'date'            => $date,
                'inflow'          => round($inflow, 2),
                'outflow'         => round($outflow, 2),
                'net'             => round($net, 2),
                'running_balance' => round($balance, 2),
            ];
        }

        return $projection;
    }

    /**
     * Détecte les périodes où le solde tombe en dessous du seuil.
     *
     * @return array<int, array{start_date: string, end_date: string, min_balance: float, severity: string}>
     */
    public function detectGaps(int $tenantId, float $threshold = 0, int $days = 90): array
    {
        $projection = $this->getDailyProjection($tenantId, $days);
        $gaps       = [];
        $inGap      = false;
        $gapStart   = null;
        $gapMin     = PHP_FLOAT_MAX;

        foreach ($projection as $day) {
            if ($day['running_balance'] < $threshold) {
                if (! $inGap) {
                    $inGap    = true;
                    $gapStart = $day['date'];
                    $gapMin   = $day['running_balance'];
                } else {
                    $gapMin = min($gapMin, $day['running_balance']);
                }
            } else {
                if ($inGap) {
                    $gaps[]  = [
                        'start_date'  => $gapStart,
                        'end_date'    => $day['date'],
                        'min_balance' => round($gapMin, 2),
                        'severity'    => $gapMin < -500000 ? 'critical' : ($gapMin < 0 ? 'warning' : 'info'),
                    ];
                    $inGap   = false;
                    $gapMin  = PHP_FLOAT_MAX;
                }
            }
        }

        // Fermer un gap en cours de fin de période
        if ($inGap && $gapStart) {
            $last   = end($projection);
            $gaps[] = [
                'start_date'  => $gapStart,
                'end_date'    => $last['date'],
                'min_balance' => round($gapMin, 2),
                'severity'    => $gapMin < -500000 ? 'critical' : 'warning',
            ];
        }

        return $gaps;
    }

    /**
     * Projection OHADA par classe de compte (Cl.5 Trésorerie).
     *
     * @return array{classe5: array, total: float, currency: string}
     */
    public function getOhadaProjection(int $tenantId): array
    {
        // Real per-account Classe 5 balance: LEFT JOIN so an account with
        // zero movements so far still appears (at 0), matching the module's
        // seeded chart (512 Banques, 514 CCP, 530 Caisse, 531 Mvola,
        // 532 Airtel Money, 540 Régies) rather than only accounts already
        // touched by a journal entry. acc_journal_entries has no
        // tenant/company column (single shared ledger, same established
        // precedent as OhadaReportService/JournalEntryApiController), so
        // $tenantId is kept for signature compatibility but unused to filter.
        $accounts = DB::table('acc_chart_of_accounts as coa')
            ->leftJoin('acc_journal_entry_lines as jel', 'jel.account_id', '=', 'coa.id')
            ->where('coa.code', 'like', '5%')
            ->where('coa.type', 'asset')
            ->selectRaw('coa.code as account_code, coa.name as account_name, COALESCE(SUM(jel.debit - jel.credit), 0) as balance')
            ->groupBy('coa.id', 'coa.code', 'coa.name')
            ->orderBy('coa.code')
            ->get()
            ->map(fn ($a) => [
                'account_code' => $a->account_code,
                'account_name' => $a->account_name,
                'balance'      => round((float) $a->balance, 2),
            ])
            ->toArray();

        return [
            'classe5'  => $accounts,
            'total'    => round(array_sum(array_column($accounts, 'balance')), 2),
            'currency' => $this->getTenantCurrency($tenantId),
            'label'    => 'Trésorerie OHADA (Classe 5)',
        ];
    }

    // ─── Méthodes privées ─────────────────────────────────────────

    /**
     * Requête de base sur les lignes d'écriture touchant un compte de
     * Trésorerie (Classe 5 OHADA — banques/caisse/mobile money), utilisée
     * pour le solde courant et la moyenne des flux quotidiens.
     */
    private function treasuryLinesQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('acc_journal_entry_lines as jel')
            ->join('acc_journal_entries as je', 'je.id', '=', 'jel.entry_id')
            ->join('acc_chart_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
            ->where('coa.code', 'like', '5%')
            ->where('coa.type', 'asset');
    }

    private function getCurrentBalance(int $tenantId): float
    {
        // Real Classe 5 balance = débit − crédit cumulés sur les comptes de
        // trésorerie du grand livre réel (acc_journal_entry_lines, posté
        // pour de vrai depuis le Chantier 15 — import caisse/banque — et le
        // Chantier 22 — écritures d'acompte/solde). Un débit augmente un
        // compte d'actif comme la trésorerie.
        $row = $this->treasuryLinesQuery()
            ->selectRaw('COALESCE(SUM(jel.debit), 0) as total_debit, COALESCE(SUM(jel.credit), 0) as total_credit')
            ->first();

        return round((float) ($row->total_debit ?? 0) - (float) ($row->total_credit ?? 0), 2);
    }

    private function getExpectedInflows(int $tenantId, int $days): array
    {
        // acc_invoices has no tenant_id (single-tenant deployment); type is
        // invoice|bill|credit_note and status is draft|posted|paid|cancelled
        // (not the sale/purchase/sent/received values this used to filter on).
        $rows = DB::table('acc_invoices')
            ->where('type', 'invoice')
            ->where('status', 'posted')
            ->whereDate('due_date', '<=', now()->addDays($days))
            ->selectRaw('DATE(due_date) as date, SUM(amount_due) as amount')
            ->groupBy('date')
            ->get();

        return $rows->pluck('amount', 'date')->map(fn ($v) => (float) $v)->toArray();
    }

    private function getExpectedOutflows(int $tenantId, int $days): array
    {
        $rows = DB::table('acc_invoices')
            ->where('type', 'bill')
            ->where('status', 'posted')
            ->whereDate('due_date', '<=', now()->addDays($days))
            ->selectRaw('DATE(due_date) as date, SUM(amount_due) as amount')
            ->groupBy('date')
            ->get();

        return $rows->pluck('amount', 'date')->map(fn ($v) => (float) $v)->toArray();
    }

    private function getRecurringOutflows(int $tenantId, int $days): array
    {
        // Charges récurrentes : salaires (fin de mois), loyers (1er du mois)
        $outflows = [];

        // Masse salariale mensuelle (hr_employees has no tenant_id — single-tenant
        // deployment — and no base_salary column; the real source is
        // hr_employee_compensation, same as HrForecastService::getCurrentSalary()).
        $employeeIds = DB::table('hr_employees')->where('status', 'active')->pluck('id');
        $payroll     = (float) $employeeIds->sum(
            fn ($id) => (float) (\Modules\HR\Models\EmployeeCompensation::where('employee_id', $id)
                ->orderByDesc('effective_date')
                ->value('base_salary') ?? 0.0)
        );

        // Dernier jour du mois = paiement des salaires
        for ($i = 1; $i <= $days; $i++) {
            $date = now()->addDays($i);
            if ($date->isLastOfMonth()) {
                $outflows[$date->toDateString()] = ($outflows[$date->toDateString()] ?? 0) + $payroll;
            }
        }

        return $outflows;
    }

    /**
     * Moyenne réelle des encaissements quotidiens des 6 derniers mois
     * (débits sur les comptes de trésorerie), utilisée comme flux de
     * repli pour un jour sans facture client échue connue.
     */
    private function estimateDailyInflow(int $tenantId): float
    {
        return $this->averageDailyTreasuryMovement('debit');
    }

    /**
     * Symétrique de estimateDailyInflow() côté sorties — moyenne réelle des
     * décaissements quotidiens des 6 derniers mois (crédits sur les comptes
     * de trésorerie), utilisée comme flux de repli pour un jour sans
     * facture fournisseur échue ni paie connue.
     */
    private function estimateDailyOutflow(int $tenantId): float
    {
        return $this->averageDailyTreasuryMovement('credit');
    }

    private function averageDailyTreasuryMovement(string $column): float
    {
        $since = now()->subDays(self::TREASURY_LOOKBACK_DAYS);

        $total = (float) $this->treasuryLinesQuery()
            ->where('je.entry_date', '>=', $since)
            ->sum("jel.{$column}");

        $daysElapsed = max(1, (int) $since->diffInDays(now()));

        return round($total / $daysElapsed, 2);
    }

    private function getTenantCurrency(int $tenantId): string
    {
        return DB::table('companies')
            ->where('id', $tenantId)
            ->value('currency') ?? 'XOF';
    }

    private function buildNarrative(array $projection, array $gaps, string $currency): string
    {
        $values       = array_column($projection, 'running_balance');
        $finalBalance = count($values) > 0 ? end($values) : 0.0;
        $minBalance   = count($values) > 0 ? min($values) : 0.0;

        $narrative = "Solde final estimé dans 90 jours : " . number_format($finalBalance, 0, ',', ' ') . " {$currency}. ";

        if (! empty($gaps)) {
            $narrative .= count($gaps) . " période(s) de déficit détectée(s). ";
            $narrative .= "Solde minimum : " . number_format($minBalance, 0, ',', ' ') . " {$currency}. ";
            $narrative .= "Recommandation : prévoir une ligne de crédit ou accélérer les encaissements.";
        } else {
            $narrative .= "Aucun déficit de trésorerie prévu sur la période.";
        }

        return $narrative;
    }
}
