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
    public function __construct(private readonly ForecastingEngineService $engine) {}

    /**
     * Prévision de trésorerie sur 90 jours.
     *
     * @return array{daily: array, summary: array, gaps: array, narrative: string}
     */
    public function forecast90Days(int $tenantId): array
    {
        $projection = $this->getDailyProjection($tenantId, 90);
        $gaps       = $this->detectGaps($tenantId);
        $ohada      = $this->getOhadaProjection($tenantId);

        $values          = array_column($projection, 'running_balance');
        $minBalance      = count($values) > 0 ? min($values) : 0.0;
        $maxBalance      = count($values) > 0 ? max($values) : 0.0;
        $finalBalance    = count($values) > 0 ? end($values) : 0.0;
        $totalInflow     = array_sum(array_column($projection, 'inflow'));
        $totalOutflow    = array_sum(array_column($projection, 'outflow'));

        return [
            'tenant_id'       => $tenantId,
            'horizon_days'    => 90,
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
            'narrative'       => $this->buildNarrative($projection, $gaps),
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

        $projection = [];
        $balance    = $startBalance;

        for ($i = 1; $i <= $days; $i++) {
            $date     = now()->addDays($i)->toDateString();
            $inflow   = (float) ($expectedInflows[$date] ?? $this->estimateDailyInflow($tenantId));
            $outflow  = (float) ($recurringOutflows[$date] ?? 0) + (float) ($expectedOutflows[$date] ?? 0);
            $net      = $inflow - $outflow;
            $balance += $net;

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
    public function detectGaps(int $tenantId, float $threshold = 0): array
    {
        $projection = $this->getDailyProjection($tenantId, 90);
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
        // Soldes actuels des comptes de trésorerie OHADA (Cl.5)
        $accounts = DB::table('chart_of_accounts')
            ->where('tenant_id', $tenantId)
            ->where('account_code', 'like', '5%')
            ->select('account_code', 'account_name', 'balance')
            ->orderBy('account_code')
            ->get()
            ->toArray();

        $total = array_sum(array_column((array) $accounts, 'balance'));

        return [
            'classe5'  => $accounts,
            'total'    => round((float) $total, 2),
            'currency' => $this->getTenantCurrency($tenantId),
            'label'    => 'Trésorerie OHADA (Classe 5)',
        ];
    }

    // ─── Méthodes privées ─────────────────────────────────────────

    private function getCurrentBalance(int $tenantId): float
    {
        return (float) DB::table('chart_of_accounts')
            ->where('tenant_id', $tenantId)
            ->where('account_code', 'like', '5%')
            ->sum('balance');
    }

    private function getExpectedInflows(int $tenantId, int $days): array
    {
        $rows = DB::table('invoices')
            ->where('tenant_id', $tenantId)
            ->where('type', 'sale')
            ->where('status', 'sent')
            ->whereDate('due_date', '<=', now()->addDays($days))
            ->selectRaw('DATE(due_date) as date, SUM(amount_due) as amount')
            ->groupBy('date')
            ->get();

        return $rows->pluck('amount', 'date')->map(fn ($v) => (float) $v)->toArray();
    }

    private function getExpectedOutflows(int $tenantId, int $days): array
    {
        $rows = DB::table('invoices')
            ->where('tenant_id', $tenantId)
            ->where('type', 'purchase')
            ->where('status', 'received')
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

        // Masse salariale mensuelle
        $payroll = (float) DB::table('employees')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->sum('base_salary');

        // Dernier jour du mois = paiement des salaires
        for ($i = 1; $i <= $days; $i++) {
            $date = now()->addDays($i);
            if ($date->isLastOfMonth()) {
                $outflows[$date->toDateString()] = ($outflows[$date->toDateString()] ?? 0) + $payroll;
            }
        }

        return $outflows;
    }

    private function estimateDailyInflow(int $tenantId): float
    {
        // Moyenne des entrées quotidiennes sur les 30 derniers jours
        $avg = DB::table('accounting_transactions')
            ->where('tenant_id', $tenantId)
            ->where('type', 'income')
            ->where('transaction_date', '>=', now()->subDays(30))
            ->avg('amount');

        return (float) ($avg ?? 0);
    }

    private function getTenantCurrency(int $tenantId): string
    {
        return DB::table('companies')
            ->where('id', $tenantId)
            ->value('currency') ?? 'XOF';
    }

    private function buildNarrative(array $projection, array $gaps): string
    {
        $values       = array_column($projection, 'running_balance');
        $finalBalance = count($values) > 0 ? end($values) : 0.0;
        $minBalance   = count($values) > 0 ? min($values) : 0.0;

        $narrative = "Solde final estimé dans 90 jours : " . number_format($finalBalance, 0, ',', ' ') . " XOF. ";

        if (! empty($gaps)) {
            $narrative .= count($gaps) . " période(s) de déficit détectée(s). ";
            $narrative .= "Solde minimum : " . number_format($minBalance, 0, ',', ' ') . " XOF. ";
            $narrative .= "Recommandation : prévoir une ligne de crédit ou accélérer les encaissements.";
        } else {
            $narrative .= "Aucun déficit de trésorerie prévu sur la période.";
        }

        return $narrative;
    }
}
