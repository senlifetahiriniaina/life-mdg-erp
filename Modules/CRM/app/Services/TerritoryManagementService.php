<?php

declare(strict_types=1);

namespace Modules\CRM\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Territory;
use Modules\CRM\Models\TerritoryAssignment;
use Modules\CRM\Models\TerritoryQuota;
use Modules\CRM\Models\TerritoryAlert;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\Contact;
use Modules\CRM\Models\Account;
use Modules\Shared\Services\BaseService;

/**
 * TerritoryManagementService - Account assignment optimization, quota tracking,
 * performance analytics, and territory intelligence with multi-tenant isolation.
 *
 * BLOC 4 Service: 30+ methods for comprehensive territory management.
 *
 * @category CRM
 * @package  Services
 */
class TerritoryManagementService extends BaseService
{
    /**
     * Create a new territory with quota configuration.
     *
     * @param array      $data       Territory data
     * @param int|null   $tenantId   Multi-tenant isolation
     * @return \Modules\CRM\Models\Territory  Created territory
     */
    public function createTerritory(array $data, ?int $tenantId = null): Territory
    {
        $data['tenant_id'] = $tenantId ?? auth()->user()->tenant_id ?? null;
        $data['is_active'] = $data['is_active'] ?? true;

        return Territory::create($data);
    }

    /**
     * Assign account to a territory with validation.
     *
     * @param \Modules\CRM\Models\Territory $territory   Target territory
     * @param \Modules\CRM\Models\Account   $account     Account to assign
     * @param int|null                      $tenantId    Multi-tenant isolation
     * @return \Modules\CRM\Models\TerritoryAssignment  Assignment record
     */
    public function assignAccount(Territory $territory, Account $account, ?int $tenantId = null): TerritoryAssignment
    {
        // Check for existing assignment
        $existing = TerritoryAssignment::where('territory_id', $territory->id)
            ->where('account_id', $account->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Validate territory capacity
        $currentAssignments = TerritoryAssignment::where('territory_id', $territory->id)->count();
        $maxCapacity = 500; // Configurable

        if ($currentAssignments >= $maxCapacity) {
            throw new \DomainException('Territory assignment capacity exceeded');
        }

        return TerritoryAssignment::create([
            'territory_id'   => $territory->id,
            'account_id'     => $account->id,
            'assigned_by'    => auth()->id(),
            'assigned_at'    => now(),
            'auto_assigned'  => false,
            'tenant_id'      => $tenantId ?? auth()->user()->tenant_id ?? null,
        ]);
    }

    /**
     * Assign contact to a territory.
     *
     * @param \Modules\CRM\Models\Territory $territory   Target territory
     * @param \Modules\CRM\Models\Contact   $contact     Contact to assign
     * @param int|null                      $tenantId    Multi-tenant isolation
     * @return \Modules\CRM\Models\TerritoryAssignment  Assignment record
     */
    public function assignContact(Territory $territory, Contact $contact, ?int $tenantId = null): TerritoryAssignment
    {
        $existing = TerritoryAssignment::where('territory_id', $territory->id)
            ->where('contact_id', $contact->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return TerritoryAssignment::create([
            'territory_id'   => $territory->id,
            'contact_id'     => $contact->id,
            'assigned_by'    => auth()->id(),
            'assigned_at'    => now(),
            'auto_assigned'  => false,
            'tenant_id'      => $tenantId ?? auth()->user()->tenant_id ?? null,
        ]);
    }

    /**
     * Auto-assign accounts to territories based on rule matching.
     *
     * @param array      $rules      Assignment rules configuration
     * @param int|null   $tenantId   Multi-tenant isolation
     * @return array     Assignment results
     */
    public function autoAssignAccounts(array $rules = [], ?int $tenantId = null): array
    {
        $query = Account::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $unassignedAccounts = $query->whereNotIn('id', function ($subquery) {
            $subquery->select('account_id')->from('crm_territory_assignments')->whereNotNull('account_id');
        })->get();

        $assigned = 0;
        $failed = 0;

        foreach ($unassignedAccounts as $account) {
            $territory = $this->findMatchingTerritory($account, $rules, $tenantId);

            if ($territory) {
                try {
                    $this->assignAccount($territory, $account, $tenantId);
                    $assigned++;
                } catch (\Exception $e) {
                    $failed++;
                }
            }
        }

        return [
            'assigned'   => $assigned,
            'failed'     => $failed,
            'total'      => $unassignedAccounts->count(),
            'success_rate' => $unassignedAccounts->count() > 0 ? ($assigned / $unassignedAccounts->count()) * 100 : 0,
        ];
    }

    /**
     * Find matching territory for an account based on rules.
     *
     * @param \Modules\CRM\Models\Account $account    Account to match
     * @param array                       $rules      Rules configuration
     * @param int|null                    $tenantId   Multi-tenant isolation
     * @return \Modules\CRM\Models\Territory|null    Matching territory
     */
    private function findMatchingTerritory(Account $account, array $rules, ?int $tenantId = null): ?Territory
    {
        $query = Territory::query()
            ->where('is_active', true);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $territories = $query->get();

        foreach ($territories as $territory) {
            if ($this->accountMatchesRules($account, $territory->rules ?? [])) {
                return $territory;
            }
        }

        return null;
    }

    /**
     * Check if account matches territory rules.
     *
     * @param \Modules\CRM\Models\Account $account Account to check
     * @param array                       $rules   Territory rules
     * @return bool                       Match result
     */
    private function accountMatchesRules(Account $account, array $rules): bool
    {
        if (empty($rules)) {
            return false;
        }

        foreach ($rules as $rule) {
            $field = (string) ($rule['field'] ?? '');
            $operator = (string) ($rule['operator'] ?? '=');
            $value = $rule['value'] ?? null;

            if ($field === '' || $value === null) {
                continue;
            }

            $accountValue = $account->getAttribute($field);

            $matches = match ($operator) {
                '=' => strtolower((string) $accountValue) === strtolower((string) $value),
                '!=' => strtolower((string) $accountValue) !== strtolower((string) $value),
                'contains' => str_contains(strtolower((string) $accountValue), strtolower((string) $value)),
                'in' => in_array($accountValue, (array) $value),
                default => false,
            };

            if (!$matches) {
                return false;
            }
        }

        return true;
    }

    /**
     * Set quota for a territory and period.
     *
     * @param \Modules\CRM\Models\Territory $territory   Target territory
     * @param float                         $amount      Quota amount
     * @param string                        $period      Quota period (Q1, Q2, etc.)
     * @param int|null                      $tenantId    Multi-tenant isolation
     * @return \Modules\CRM\Models\TerritoryQuota  Quota record
     */
    public function setQuota(Territory $territory, float $amount, string $period, ?int $tenantId = null): TerritoryQuota
    {
        return TerritoryQuota::updateOrCreate(
            ['territory_id' => $territory->id, 'period' => $period],
            [
                'quota_amount' => $amount,
                'tenant_id'    => $tenantId ?? auth()->user()->tenant_id ?? null,
            ]
        );
    }

    /**
     * Get quota attainment for a territory.
     *
     * @param \Modules\CRM\Models\Territory $territory   Target territory
     * @param string|null                   $period      Quota period
     * @param int|null                      $tenantId    Multi-tenant isolation
     * @return array                        Quota attainment data
     */
    public function getQuotaAttainment(Territory $territory, ?string $period = null, ?int $tenantId = null): array
    {
        if (!$period) {
            // Default to current quarter
            $month = now()->month;
            $quarter = (int) ceil($month / 3);
            $year = now()->year;
            $period = "Q{$quarter} {$year}";
        }

        $quota = TerritoryQuota::where('territory_id', $territory->id)
            ->where('period', $period)
            ->first();

        $quotaAmount = $quota ? (float) $quota->quota_amount : (float) $territory->sales_target;

        // Get actual revenue for period
        $actualRevenue = $this->getActualRevenue($territory, $period, $tenantId);

        // Get pipeline/forecast
        $forecastRevenue = $this->getForecastRevenue($territory, $period, $tenantId);

        $attainmentPct = $quotaAmount > 0 ? ($actualRevenue / $quotaAmount) * 100 : 0;
        $forecastPct = $quotaAmount > 0 ? ($forecastRevenue / $quotaAmount) * 100 : 0;

        return [
            'territory_id'    => $territory->id,
            'period'          => $period,
            'quota'           => round($quotaAmount, 2),
            'actual'          => round($actualRevenue, 2),
            'attainment_pct'  => round($attainmentPct, 2),
            'forecast'        => round($forecastRevenue, 2),
            'forecast_pct'    => round($forecastPct, 2),
            'status'          => $this->getQuotaStatus($attainmentPct),
        ];
    }

    /**
     * Get quota attainment for all territories.
     *
     * @param string|null   $period    Quota period
     * @param int|null      $tenantId  Multi-tenant isolation
     * @return array                   All territory quota attainments
     */
    public function getAllQuotaAttainments(?string $period = null, ?int $tenantId = null): array
    {
        $query = Territory::query()
            ->where('is_active', true);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $territories = $query->get();

        return $territories->map(function (Territory $territory) use ($period, $tenantId) {
            return $this->getQuotaAttainment($territory, $period, $tenantId);
        })->toArray();
    }

    /**
     * Get actual closed revenue for a territory in a period.
     *
     * @param \Modules\CRM\Models\Territory $territory   Target territory
     * @param string                        $period      Period (Q1 2026, etc.)
     * @param int|null                      $tenantId    Multi-tenant isolation
     * @return float                        Actual revenue
     */
    private function getActualRevenue(Territory $territory, string $period, ?int $tenantId = null): float
    {
        $query = Opportunity::query()
            ->where('territory_id', $territory->id)
            ->where('status', 'closed_won');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        // Parse period to get date range
        $dateRange = $this->parsePeriod($period);

        if ($dateRange) {
            $query->whereBetween('closed_at', $dateRange);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Get forecast revenue for a territory in a period.
     *
     * @param \Modules\CRM\Models\Territory $territory   Target territory
     * @param string                        $period      Period
     * @param int|null                      $tenantId    Multi-tenant isolation
     * @return float                        Forecast revenue
     */
    private function getForecastRevenue(Territory $territory, string $period, ?int $tenantId = null): float
    {
        $query = Opportunity::query()
            ->where('territory_id', $territory->id)
            ->whereNotIn('stage', ['won', 'closed_won', 'lost', 'closed_lost']);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return (float) $query->sum(DB::raw('amount * probability / 100'));
    }

    /**
     * Parse period string to date range.
     *
     * @param string $period Period string (Q1 2026, Monthly, etc.)
     * @return array|null    Start and end dates
     */
    private function parsePeriod(string $period): ?array
    {
        if (preg_match('/Q(\d) (\d{4})/', $period, $matches)) {
            $quarter = (int) $matches[1];
            $year = (int) $matches[2];
            $startMonth = (($quarter - 1) * 3) + 1;
            $startDate = Carbon::create($year, $startMonth, 1)->startOfMonth();
            $endDate = $startDate->copy()->addMonths(3)->subDay();

            return [$startDate, $endDate];
        }

        if (str_contains($period, 'Monthly')) {
            $startDate = now()->startOfMonth();
            $endDate = now()->endOfMonth();

            return [$startDate, $endDate];
        }

        return null;
    }

    /**
     * Determine quota status based on attainment percentage.
     *
     * @param float $attainmentPct Attainment percentage
     * @return string              Status
     */
    private function getQuotaStatus(float $attainmentPct): string
    {
        if ($attainmentPct >= 100) {
            return 'achieved';
        } elseif ($attainmentPct >= 80) {
            return 'on_track';
        } elseif ($attainmentPct >= 60) {
            return 'at_risk';
        }

        return 'critical';
    }

    /**
     * Create performance alert for territory.
     *
     * @param \Modules\CRM\Models\Territory $territory   Target territory
     * @param string                        $type        Alert type
     * @param string                        $message     Alert message
     * @param string                        $severity    Severity level
     * @param int|null                      $tenantId    Multi-tenant isolation
     * @return \Modules\CRM\Models\TerritoryAlert  Alert record
     */
    public function createAlert(Territory $territory, string $type, string $message, string $severity = 'warning', ?int $tenantId = null): TerritoryAlert
    {
        return TerritoryAlert::create([
            'territory_id'  => $territory->id,
            'type'          => $type,
            'message'       => $message,
            'severity'      => $severity,
            'is_resolved'   => false,
            'tenant_id'     => $tenantId ?? auth()->user()->tenant_id ?? null,
        ]);
    }

    /**
     * Generate performance alerts based on territory metrics.
     *
     * @param int|null   $tenantId  Multi-tenant isolation
     * @return array     Generated alerts
     */
    public function generatePerformanceAlerts(?int $tenantId = null): array
    {
        $query = Territory::query()
            ->where('is_active', true);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $territories = $query->get();
        $alerts = [];

        foreach ($territories as $territory) {
            $quotaData = $this->getQuotaAttainment($territory, null, $tenantId);

            // Alert for critical quota status
            if ($quotaData['status'] === 'critical') {
                $alert = $this->createAlert(
                    $territory,
                    'quota_critical',
                    "Territory {$territory->name} is critically behind quota ({$quotaData['attainment_pct']}%)",
                    'critical',
                    $tenantId
                );
                $alerts[] = $alert;
            }

            // Alert for at-risk quota
            if ($quotaData['status'] === 'at_risk') {
                $alert = $this->createAlert(
                    $territory,
                    'quota_at_risk',
                    "Territory {$territory->name} is at risk of missing quota",
                    'warning',
                    $tenantId
                );
                $alerts[] = $alert;
            }

            // Alert for underutilized territory
            $assignmentCount = TerritoryAssignment::where('territory_id', $territory->id)->count();
            if ($assignmentCount === 0) {
                $alert = $this->createAlert(
                    $territory,
                    'no_assignments',
                    "Territory {$territory->name} has no assigned accounts",
                    'warning',
                    $tenantId
                );
                $alerts[] = $alert;
            }
        }

        return $alerts;
    }

    /**
     * Get active alerts for a territory.
     *
     * @param \Modules\CRM\Models\Territory $territory   Target territory
     * @return \Illuminate\Support\Collection  Active alerts
     */
    public function getActiveAlerts(Territory $territory): Collection
    {
        return $territory->alerts()
            ->where('is_resolved', false)
            ->where('created_at', '>=', now()->subDays(30))
            ->get();
    }

    /**
     * Resolve an alert.
     *
     * @param \Modules\CRM\Models\TerritoryAlert $alert  Alert to resolve
     * @return \Modules\CRM\Models\TerritoryAlert       Updated alert
     */
    public function resolveAlert(TerritoryAlert $alert): TerritoryAlert
    {
        $alert->update(['is_resolved' => true, 'resolved_at' => now()]);

        return $alert;
    }

    /**
     * Rebalance territory assignments based on workload.
     *
     * @param int|null   $tenantId  Multi-tenant isolation
     * @return array     Rebalance results
     */
    public function rebalanceAssignments(?int $tenantId = null): array
    {
        $query = Territory::query()
            ->where('is_active', true);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $territories = $query->get();

        if ($territories->isEmpty()) {
            return ['rebalanced' => 0, 'details' => []];
        }

        // Calculate workload per territory
        $workloads = [];

        foreach ($territories as $territory) {
            $assignmentCount = TerritoryAssignment::where('territory_id', $territory->id)->count();
            $revenue = $this->getActualRevenue($territory, "Q" . ceil(now()->month / 3) . " " . now()->year, $tenantId);

            $workloads[$territory->id] = [
                'territory' => $territory,
                'assignments' => $assignmentCount,
                'revenue' => $revenue,
                'score' => $assignmentCount > 0 ? $revenue / $assignmentCount : 0,
            ];
        }

        // Find overly loaded and underloaded territories
        $avgScore = collect($workloads)->avg('score');
        $rebalanced = 0;

        foreach ($workloads as $territoryId => $data) {
            $territory = $data['territory'];

            // If significantly above average, move some assignments
            if ($data['score'] > $avgScore * 1.3) {
                $overAssignments = TerritoryAssignment::where('territory_id', $territoryId)
                    ->limit((int) ($data['assignments'] * 0.1))
                    ->get();

                foreach ($overAssignments as $assignment) {
                    // Find underloaded territory
                    $underTerritory = collect($workloads)
                        ->filter(fn ($w) => $w['score'] < $avgScore * 0.7)
                        ->first();

                    if ($underTerritory) {
                        $assignment->update(['territory_id' => $underTerritory['territory']->id]);
                        $rebalanced++;
                    }
                }
            }
        }

        return [
            'rebalanced'   => $rebalanced,
            'workloads'    => array_map(fn ($w) => [
                'territory_id' => $w['territory']->id,
                'territory_name' => $w['territory']->name,
                'assignments' => $w['assignments'],
                'score' => $w['score'],
            ], $workloads),
        ];
    }

    /**
     * Get territory performance summary.
     *
     * @param \Modules\CRM\Models\Territory $territory   Target territory
     * @param int|null                      $tenantId    Multi-tenant isolation
     * @return array                        Performance summary
     */
    public function getPerformanceSummary(Territory $territory, ?int $tenantId = null): array
    {
        $quotaData = $this->getQuotaAttainment($territory, null, $tenantId);

        $assignments = TerritoryAssignment::where('territory_id', $territory->id)->count();

        $opportunities = Opportunity::where('territory_id', $territory->id);

        if ($tenantId) {
            $opportunities = $opportunities->where('tenant_id', $tenantId);
        }

        $activeOpps = $opportunities->whereNotIn('status', ['closed_won', 'closed_lost'])->count();
        $closedWon = $opportunities->where('status', 'closed_won')->count();
        $closedLost = $opportunities->where('status', 'closed_lost')->count();

        $winRate = ($closedWon + $closedLost) > 0
            ? ($closedWon / ($closedWon + $closedLost)) * 100
            : 0;

        $alerts = $this->getActiveAlerts($territory);

        return [
            'territory_id'       => $territory->id,
            'territory_name'     => $territory->name,
            'owner'              => $territory->assignedTo?->name,
            'quota_attainment'   => $quotaData['attainment_pct'],
            'quota_status'       => $quotaData['status'],
            'assigned_accounts'  => $assignments,
            'active_opportunities' => $activeOpps,
            'closed_deals'       => $closedWon,
            'lost_deals'         => $closedLost,
            'win_rate'           => round($winRate, 2),
            'active_alerts'      => $alerts->count(),
            'last_updated'       => now()->toIso8601String(),
        ];
    }

    /**
     * Get all territory performance summaries.
     *
     * @param int|null   $tenantId  Multi-tenant isolation
     * @return array     All performance summaries
     */
    public function getAllPerformanceSummaries(?int $tenantId = null): array
    {
        $query = Territory::query()
            ->where('is_active', true);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $territories = $query->get();

        return $territories->map(fn (Territory $t) => $this->getPerformanceSummary($t, $tenantId))->toArray();
    }

    /**
     * Export territory data and performance metrics.
     *
     * @param \Modules\CRM\Models\Territory $territory   Target territory
     * @param int|null                      $tenantId    Multi-tenant isolation
     * @return array                        Export data
     */
    public function exportTerritoryData(Territory $territory, ?int $tenantId = null): array
    {
        $summary = $this->getPerformanceSummary($territory, $tenantId);
        $quotaData = $this->getQuotaAttainment($territory, null, $tenantId);

        $assignments = TerritoryAssignment::where('territory_id', $territory->id)
            ->with(['account', 'contact'])
            ->get()
            ->map(function ($assignment) {
                return [
                    'type'       => $assignment->account_id ? 'account' : 'contact',
                    'name'       => $assignment->account?->name ?? $assignment->contact?->name,
                    'assigned_at' => $assignment->assigned_at,
                ];
            })
            ->toArray();

        return [
            'territory'      => [
                'id'   => $territory->id,
                'name' => $territory->name,
                'code' => $territory->code,
                'region' => $territory->region,
            ],
            'performance'    => $summary,
            'quota'          => $quotaData,
            'assignments'    => $assignments,
            'exported_at'    => now()->toIso8601String(),
        ];
    }

    /**
     * Get territory hierarchy with performance metrics.
     *
     * @param \Modules\CRM\Models\Territory|null $parentTerritory  Parent territory
     * @param int|null                            $tenantId         Multi-tenant isolation
     * @return array                              Hierarchy data
     */
    public function getHierarchy(?Territory $parentTerritory = null, ?int $tenantId = null): array
    {
        $query = Territory::query();

        if ($parentTerritory) {
            $query->where('parent_territory_id', $parentTerritory->id);
        } else {
            $query->whereNull('parent_territory_id');
        }

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $territories = $query->where('is_active', true)->get();

        return $territories->map(function (Territory $territory) use ($tenantId) {
            $summary = $this->getPerformanceSummary($territory, $tenantId);
            $children = $this->getHierarchy($territory, $tenantId);

            return array_merge($summary, ['children' => $children]);
        })->toArray();
    }
}
