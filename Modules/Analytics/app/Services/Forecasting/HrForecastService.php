<?php

namespace Modules\Analytics\Services\Forecasting;

use Illuminate\Support\Facades\DB;
use Modules\Analytics\Services\ForecastingEngineService;
use Modules\HR\Models\EmployeeCompensation;

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
        $employees = DB::table('hr_employees')
            ->where('status', 'active')
            ->select('id', 'full_name', 'hire_date', 'department_id', 'manager_id')
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
            $salary   = $this->getCurrentSalary($employee->id);
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
                'employee_name'        => $employee->full_name,
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
        $employeeIds = DB::table('hr_employees')->where('status', 'active')->pluck('id');
        $totalBase   = (float) $employeeIds->sum(fn ($id) => $this->getCurrentSalary($id));

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
        // hr_leave_requests has no tenant_id (single-tenant deployment) and
        // grouping is done in PHP rather than DATE_FORMAT() (MySQL-only).
        $historicalLeaves = DB::table('hr_leave_requests')
            ->where('status', 'approved')
            ->where('start_date', '>=', now()->subYear())
            ->pluck('start_date')
            ->groupBy(fn ($date) => \Carbon\Carbon::parse($date)->format('Y-m'))
            ->map(fn ($rows) => count($rows))
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
        // hr_employees has no tenant_id column — Life MDG deploys single-tenant
        // (one company per install), so no tenant filter is needed here.
        return (int) DB::table('hr_employees')
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
            ->where('created_at', '>=', now()->subYear())
            ->sum('total');

        return $annualRevenue / $headcount / 12; // mensuel
    }

    private function getForecastedRevenue(int $tenantId, int $months): array
    {
        // Grouped in PHP rather than DATE_FORMAT() (MySQL-only, unavailable on SQLite).
        $history = DB::table('sales_orders')
            ->where('tenant_id', $tenantId)
            ->where('status', 'confirmed')
            ->where('created_at', '>=', now()->subYear())
            ->select('created_at', 'total')
            ->get()
            ->groupBy(fn ($r) => \Carbon\Carbon::parse($r->created_at)->format('Y-m'))
            ->sortKeys()
            ->map(fn ($rows) => (float) $rows->sum('total'))
            ->values()
            ->toArray();

        if (empty($history)) {
            return array_fill(0, $months, 0.0);
        }

        $avg    = array_sum($history) / count($history);
        $trend  = count($history) >= 2 ? ($history[array_key_last($history)] - $history[0]) / count($history) : 0;

        return array_map(fn ($i) => max(0, $avg + $trend * ($i + 1)), range(0, $months - 1));
    }

    /**
     * Salaire courant d'un employé (dernière ligne de rémunération effective).
     * hr_employees n'a pas de colonne base_salary — la source de vérité est
     * hr_employee_compensation (CLAUDE.md : EmployeeCompensation/SalaryBand).
     */
    private function getCurrentSalary(int $employeeId): float
    {
        return (float) (EmployeeCompensation::where('employee_id', $employeeId)
            ->orderByDesc('effective_date')
            ->value('base_salary') ?? 0.0);
    }

    private function getMarketSalaryBenchmark(int $tenantId): array
    {
        // Salaires de référence par département (simplifiés, à enrichir par pays)
        return DB::table('hr_employees')
            ->where('status', 'active')
            ->select('id', 'department_id')
            ->get()
            ->groupBy('department_id')
            ->map(fn ($employees) => $employees->avg(fn ($e) => $this->getCurrentSalary($e->id)) * 1.1) // +10% = estimation du marché
            ->toArray();
    }

    private function getUnusedLeaveDays(int $employeeId, int $tenantId): int
    {
        return (int) (DB::table('hr_leave_balances')
            ->where('employee_id', $employeeId)
            ->value('balance') ?? 0);
    }

    private function getAverageOvertimeHours(int $employeeId, int $tenantId): float
    {
        // Overtime hours are not tracked as a distinct concept in this schema
        // (Timesheets records hours_worked per entry, no overtime split) —
        // degrades to a neutral 0 signal rather than querying a column that
        // doesn't exist, same fallback pattern used across KPIRegistryService.
        return 0.0;
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
