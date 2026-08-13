<?php

namespace Modules\Analytics\Services\Forecasting;

use Illuminate\Support\Facades\DB;
use Modules\Analytics\Services\ForecastingEngineService;

/**
 * Service de prévision des ressources humaines.
 *
 * Africa First :
 *   - Prise en compte des périodes de congés en Afrique (fêtes religieuses, saisons agricoles)
 *   - Indemnités OHADA (SMIG, préavis légal, prime d'ancienneté)
 *   - Patterns de départs liés aux migrations saisonnières
 */
class HrForecastService
{
    public function __construct(private readonly ForecastingEngineService $engine) {}

    /**
     * Prévision des besoins en effectifs sur N mois.
     *
     * @return array<int, array{month: string, required_headcount: int, current_headcount: int, gap: int, roles_needed: array}>
     */
    public function forecastHeadcount(int $tenantId, int $months = 6): array
    {
        $currentHeadcount = $this->getCurrentHeadcount($tenantId);
        $revenuePerHead   = $this->getRevenuePerEmployee($tenantId);
        $forecastedRevenue = $this->getForecastedRevenue($tenantId, $months);

        $result = [];
        for ($i = 1; $i <= $months; $i++) {
            $month           = now()->addMonths($i)->format('Y-m');
            $revenue         = $forecastedRevenue[$i - 1] ?? ($revenuePerHead * $currentHeadcount);
            $required        = $revenuePerHead > 0
                ? (int) ceil($revenue / $revenuePerHead)
                : $currentHeadcount;
            $gap             = $required - $currentHeadcount;

            $result[] = [
                'month'             => $month,
                'required_headcount' => $required,
                'current_headcount'  => $currentHeadcount,
                'gap'               => $gap,
                'roles_needed'      => $gap > 0 ? $this->suggestRoles($gap, $tenantId) : [],
                'action'            => $gap > 0 ? 'recruter' : ($gap < -2 ? 'réaffecter' : 'stable'),
            ];
        }

        return $result;
    }

    /**
     * Prédit le risque de départ pour chaque employé actif.
     *
     * @return array<int, array{employee_id: int, risk_score: float, risk_level: string, risk_factors: string[], retention_suggestions: string[]}>
     */
    public function predictTurnoverRisk(int $tenantId): array
    {
        $employees = DB::table('employees')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->select('id', 'name', 'hire_date', 'base_salary', 'department_id', 'manager_id')
            ->get();

        $marketSalary = $this->getMarketSalaryBenchmark($tenantId);
        $results      = [];

        foreach ($employees as $employee) {
            $score   = 0.0;
            $factors = [];

            // Facteur 1 : ancienneté (< 2 ans = risque élevé)
            $tenure = now()->diffInMonths($employee->hire_date);
            if ($tenure < 12) {
                $score   += 0.30;
                $factors[] = 'Ancienneté < 12 mois (période critique de rétention)';
            } elseif ($tenure < 24) {
                $score   += 0.15;
                $factors[] = 'Ancienneté < 24 mois';
            }

            // Facteur 2 : salaire vs marché
            $salary   = (float) $employee->base_salary;
            $market   = (float) ($marketSalary[$employee->department_id] ?? $salary);
            $gap      = $market > 0 ? ($market - $salary) / $market : 0;
            if ($gap > 0.20) {
                $score   += 0.35;
                $factors[] = "Salaire " . round($gap * 100) . "% en dessous du marché";
            } elseif ($gap > 0.10) {
                $score   += 0.15;
                $factors[] = "Salaire légèrement en dessous du marché";
            }

            // Facteur 3 : congés non pris
            $unusedLeave = $this->getUnusedLeaveDays($employee->id, $tenantId);
            if ($unusedLeave > 20) {
                $score   += 0.15;
                $factors[] = "Solde de congés élevé ({$unusedLeave} jours) — signal de surcharge";
            }

            // Facteur 4 : heures supplémentaires excessives
            $overtime = $this->getAverageOvertimeHours($employee->id, $tenantId);
            if ($overtime > 10) {
                $score   += 0.20;
                $factors[] = "Heures supplémentaires : {$overtime}h/semaine en moyenne";
            }

            $score = min($score, 1.0);
            $level = $score >= 0.7 ? 'élevé' : ($score >= 0.4 ? 'moyen' : 'faible');

            $results[] = [
                'employee_id'          => $employee->id,
                'employee_name'        => $employee->name,
                'risk_score'           => round($score, 2),
                'risk_level'           => $level,
                'risk_factors'         => $factors,
                'retention_suggestions' => $this->buildRetentionSuggestions($factors),
            ];
        }

        usort($results, fn ($a, $b) => $b['risk_score'] <=> $a['risk_score']);

        return $results;
    }

    /**
     * Prévision du coût de la masse salariale sur N mois.
     *
     * @return array<int, array{month: string, base_salary: float, bonuses: float, leave_accrual: float, total: float}>
     */
    public function forecastPayrollCost(int $tenantId, int $months = 6): array
    {
        $totalBase    = (float) DB::table('employees')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->sum('base_salary');

        $result = [];
        for ($i = 1; $i <= $months; $i++) {
            $month       = now()->addMonths($i)->format('Y-m');
            $monthNum    = (int) now()->addMonths($i)->format('n');

            // Prime annuelle (13e mois en décembre, courant en Afrique)
            $bonus       = $monthNum === 12 ? $totalBase * 0.5 : 0.0;
            // Congés payés : provision mensuelle (21 jours ouvrables / 12)
            $leaveAccrual = $totalBase * (21 / 252);
            $total        = $totalBase + $bonus + $leaveAccrual;

            $result[] = [
                'month'         => $month,
                'base_salary'   => round($totalBase, 2),
                'bonuses'       => round($bonus, 2),
                'leave_accrual' => round($leaveAccrual, 2),
                'total'         => round($total, 2),
            ];
        }

        return $result;
    }

    /**
     * Prévision de la demande de congés par période.
     *
     * @return array<int, array{period: string, expected_requests: int, peak: bool, africa_event: string|null}>
     */
    public function forecastLeaveDemand(int $tenantId): array
    {
        $historicalLeaves = DB::table('leave_requests')
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->where('start_date', '>=', now()->subYear())
            ->selectRaw('DATE_FORMAT(start_date, \'%Y-%m\') as period, COUNT(*) as total')
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->pluck('total', 'period')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        $avgMonthly = count($historicalLeaves) > 0
            ? array_sum($historicalLeaves) / count($historicalLeaves)
            : 5;

        $africanPeaks = [
            '03' => ['event' => 'Ramadan', 'multiplier' => 1.5],
            '06' => ['event' => 'Tabaski / Aïd al-Adha', 'multiplier' => 1.4],
            '08' => ['event' => 'Congés estivaux', 'multiplier' => 1.8],
            '09' => ['event' => 'Rentrée scolaire (parents)', 'multiplier' => 1.3],
            '12' => ['event' => 'Fêtes de fin d\'année', 'multiplier' => 2.0],
        ];

        $result = [];
        for ($i = 1; $i <= 12; $i++) {
            $date       = now()->addMonths($i);
            $month      = $date->format('m');
            $period     = $date->format('Y-m');
            $peakInfo   = $africanPeaks[$month] ?? null;
            $multiplier = $peakInfo ? $peakInfo['multiplier'] : 1.0;
            $expected   = (int) round($avgMonthly * $multiplier);

            $result[] = [
                'period'            => $period,
                'expected_requests' => $expected,
                'peak'              => $peakInfo !== null,
                'africa_event'      => $peakInfo['event'] ?? null,
                'multiplier'        => $multiplier,
            ];
        }

        return $result;
    }

    // ─── Méthodes privées ─────────────────────────────────────────

    private function getCurrentHeadcount(int $tenantId): int
    {
        return (int) DB::table('employees')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();
    }

    private function getRevenuePerEmployee(int $tenantId): float
    {
        $headcount = $this->getCurrentHeadcount($tenantId);
        if ($headcount === 0) {
            return 0.0;
        }

        $annualRevenue = (float) DB::table('sales_orders')
            ->where('tenant_id', $tenantId)
            ->where('status', 'confirmed')
            ->where('ordered_at', '>=', now()->subYear())
            ->sum('total_amount');

        return $annualRevenue / $headcount / 12; // mensuel
    }

    private function getForecastedRevenue(int $tenantId, int $months): array
    {
        $history = DB::table('sales_orders')
            ->where('tenant_id', $tenantId)
            ->where('status', 'confirmed')
            ->where('ordered_at', '>=', now()->subYear())
            ->selectRaw('DATE_FORMAT(ordered_at, \'%Y-%m\') as period, SUM(total_amount) as total')
            ->groupBy('period')
            ->orderBy('period')
            ->pluck('total')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        if (empty($history)) {
            return array_fill(0, $months, 0.0);
        }

        $avg    = array_sum($history) / count($history);
        $trend  = count($history) >= 2 ? ($history[array_key_last($history)] - $history[0]) / count($history) : 0;

        return array_map(fn ($i) => max(0, $avg + $trend * ($i + 1)), range(0, $months - 1));
    }

    private function getMarketSalaryBenchmark(int $tenantId): array
    {
        // Salaires de référence par département (simplifiés, à enrichir par pays)
        return DB::table('employees')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->groupBy('department_id')
            ->selectRaw('department_id, AVG(base_salary) as avg')
            ->get()
            ->pluck('avg', 'department_id')
            ->map(fn ($v) => (float) $v * 1.1) // +10% = estimation du marché
            ->toArray();
    }

    private function getUnusedLeaveDays(int $employeeId, int $tenantId): int
    {
        return (int) DB::table('leave_balances')
            ->where('employee_id', $employeeId)
            ->where('tenant_id', $tenantId)
            ->value('balance_days') ?? 0;
    }

    private function getAverageOvertimeHours(int $employeeId, int $tenantId): float
    {
        return (float) DB::table('timesheets')
            ->where('employee_id', $employeeId)
            ->where('tenant_id', $tenantId)
            ->where('date', '>=', now()->subMonths(3))
            ->avg('overtime_hours') ?? 0.0;
    }

    private function suggestRoles(int $gap, int $tenantId): array
    {
        // Récupérer les rôles les plus demandés parmi les offres récentes
        $roles = DB::table('job_postings')
            ->where('tenant_id', $tenantId)
            ->where('status', 'open')
            ->select('title', 'department')
            ->limit($gap)
            ->get()
            ->map(fn ($r) => ['title' => $r->title, 'department' => $r->department])
            ->toArray();

        if (empty($roles)) {
            return array_fill(0, min($gap, 3), ['title' => 'Poste à définir', 'department' => null]);
        }

        return $roles;
    }

    private function buildRetentionSuggestions(array $factors): array
    {
        $suggestions = [];
        foreach ($factors as $factor) {
            if (str_contains($factor, 'salaire') || str_contains($factor, 'Salaire')) {
                $suggestions[] = 'Réviser la grille salariale pour aligner avec le marché';
            }
            if (str_contains($factor, 'congés') || str_contains($factor, 'Congés')) {
                $suggestions[] = 'Planifier une période de congés dans les 30 jours';
            }
            if (str_contains($factor, 'Heures supplémentaires')) {
                $suggestions[] = 'Réduire la charge de travail ou recruter un renfort';
            }
            if (str_contains($factor, 'Ancienneté')) {
                $suggestions[] = 'Programme de mentorat et plan de carrière formalisé';
            }
        }

        return array_unique($suggestions);
    }
}
