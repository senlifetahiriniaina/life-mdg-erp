<?php

declare(strict_types=1);

namespace Modules\CRM\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\PipelineSnapshot;
use Modules\CRM\Models\WinLossRecord;

class PipelineAnalyticsService
{
    /**
     * Record a win for an opportunity.
     */
    public function recordWin(int $opportunityId, array $data = []): WinLossRecord
    {
        return $this->recordOutcome($opportunityId, 'won', 'closed_won', $data);
    }

    /**
     * Record a loss for an opportunity.
     */
    public function recordLoss(int $opportunityId, array $data = []): WinLossRecord
    {
        return $this->recordOutcome($opportunityId, 'lost', 'closed_lost', $data);
    }

    private function recordOutcome(int $opportunityId, string $outcome, string $newStatus, array $data): WinLossRecord
    {
        $opportunity = DB::table('crm_opportunities')->where('id', $opportunityId)->first();

        $salesCycleDays = null;
        if ($opportunity && $opportunity->created_at) {
            $createdAt = Carbon::parse($opportunity->created_at);
            $salesCycleDays = (int) $createdAt->diffInDays(now());
        }

        $dealValue = $opportunity ? (float) ($opportunity->amount ?? 0) : 0.0;

        $record = WinLossRecord::create([
            'opportunity_id' => $opportunityId,
            'outcome' => $outcome,
            'reason' => $data['reason'] ?? null,
            'competitor' => $data['competitor'] ?? null,
            'deal_value' => $dealValue,
            'sales_cycle_days' => $salesCycleDays,
            'recorded_by' => $data['recorded_by'] ?? auth()->id(),
            'recorded_at' => now(),
        ]);

        DB::table('crm_opportunities')
            ->where('id', $opportunityId)
            ->update(['status' => $newStatus]);

        return $record;
    }

    /**
     * Get win rate as a percentage (0-100).
     */
    public function getWinRate(?Carbon $from = null, ?Carbon $to = null): float
    {
        $query = WinLossRecord::query();

        if ($from !== null) {
            $query->where('recorded_at', '>=', $from);
        }
        if ($to !== null) {
            $query->where('recorded_at', '<=', $to);
        }

        $total = $query->count();
        if ($total === 0) {
            return 0.0;
        }

        $won = (clone $query)->where('outcome', 'won')->count();

        return round(($won / $total) * 100, 2);
    }

    /**
     * Get conversion funnel for a pipeline.
     */
    public function getConversionFunnel(int $pipelineId): array
    {
        $stages = DB::table('crm_opportunities')
            ->where('pipeline_id', $pipelineId)
            ->select('stage', DB::raw('count(*) as count'), DB::raw('sum(amount) as value'))
            ->groupBy('stage')
            ->get();

        if ($stages->isEmpty()) {
            return [];
        }

        $firstCount = (int) $stages->first()->count;

        return $stages->map(function ($row) use ($firstCount) {
            $count = (int) $row->count;
            $conversionRate = $firstCount > 0 ? round(($count / $firstCount) * 100, 2) : 0.0;

            return [
                'stage' => $row->stage,
                'count' => $count,
                'value' => (float) ($row->value ?? 0),
                'conversion_rate' => $conversionRate,
            ];
        })->values()->all();
    }

    /**
     * Get sales velocity (deals per day value).
     */
    public function getSalesVelocity(?Carbon $from = null, ?Carbon $to = null): float
    {
        $query = WinLossRecord::where('outcome', 'won');

        if ($from !== null) {
            $query->where('recorded_at', '>=', $from);
        }
        if ($to !== null) {
            $query->where('recorded_at', '<=', $to);
        }

        $wonDeals = $query->get();

        if ($wonDeals->isEmpty()) {
            return 0.0;
        }

        $opportunities = $wonDeals->count();
        $winRate = $this->getWinRate($from, $to) / 100;
        $avgDealSize = $wonDeals->avg('deal_value') ?? 0.0;
        $avgSalesCycle = $wonDeals->whereNotNull('sales_cycle_days')->avg('sales_cycle_days') ?? 0.0;

        if ($avgSalesCycle == 0) {
            return 0.0;
        }

        return round(($opportunities * $winRate * $avgDealSize) / $avgSalesCycle, 4);
    }

    /**
     * Get stage distribution for a pipeline.
     */
    public function getStageDistribution(int $pipelineId): array
    {
        $rows = DB::table('crm_opportunities')
            ->where('pipeline_id', $pipelineId)
            ->select('stage', DB::raw('count(*) as count'), DB::raw('sum(amount) as total_value'))
            ->groupBy('stage')
            ->get();

        $totalCount = $rows->sum('count');

        return $rows->map(function ($row) use ($totalCount) {
            $count = (int) $row->count;
            $percentage = $totalCount > 0 ? round(($count / $totalCount) * 100, 2) : 0.0;

            return [
                'stage' => $row->stage,
                'count' => $count,
                'total_value' => (float) ($row->total_value ?? 0),
                'percentage' => $percentage,
            ];
        })->values()->all();
    }

    /**
     * Take a pipeline snapshot.
     */
    public function takeSnapshot(int $pipelineId): PipelineSnapshot
    {
        $rows = DB::table('crm_opportunities')
            ->where('pipeline_id', $pipelineId)
            ->whereNull('deleted_at')
            ->select('stage', DB::raw('count(*) as count'), DB::raw('sum(amount) as value'))
            ->groupBy('stage')
            ->get();

        $totalValue = $rows->reduce(fn (float $carry, \stdClass $row) => $carry + (float) ($row->value ?? 0), 0.0);
        $dealCount = $rows->reduce(fn (int $carry, \stdClass $row) => $carry + (int) ($row->count ?? 0), 0);
        $avgDealSize = $dealCount > 0 ? round($totalValue / $dealCount, 4) : 0.0;

        $stageData = $rows->map(fn ($row) => [
            'stage' => $row->stage,
            'count' => (int) $row->count,
            'value' => (float) ($row->value ?? 0),
        ])->values()->all();

        return PipelineSnapshot::create([
            'pipeline_id' => $pipelineId,
            'snapshot_date' => now()->toDateString(),
            'total_value' => $totalValue,
            'deal_count' => $dealCount,
            'avg_deal_size' => $avgDealSize,
            'stage_data' => $stageData,
        ]);
    }

    /**
     * Get pipeline trend (snapshots over time).
     */
    public function getPipelineTrend(int $pipelineId, int $days = 30): array
    {
        $from = now()->subDays($days);

        return PipelineSnapshot::where('pipeline_id', $pipelineId)
            ->where('snapshot_date', '>=', $from->toDateString())
            ->orderBy('snapshot_date')
            ->get()
            ->map(fn ($snap) => [
                'date' => $snap->snapshot_date->toDateString(),
                'total_value' => (float) $snap->total_value,
                'deal_count' => (int) $snap->deal_count,
            ])
            ->values()
            ->all();
    }

    /**
     * Get top performers by won deals.
     */
    public function getTopPerformers(int $limit = 5): array
    {
        return DB::table('crm_win_loss_records')
            ->where('crm_win_loss_records.outcome', 'won')
            ->join('users', 'users.id', '=', 'crm_win_loss_records.recorded_by')
            ->select(
                'crm_win_loss_records.recorded_by as user_id',
                'users.name',
                DB::raw('count(*) as won_count'),
                DB::raw('sum(crm_win_loss_records.deal_value) as total_value')
            )
            ->groupBy('crm_win_loss_records.recorded_by', 'users.name')
            ->orderByDesc('won_count')
            ->limit($limit)
            ->get()
            ->map(fn (\stdClass $row) => [
                'user_id' => (int) $row->user_id,
                'name' => (string) $row->name,
                'won_count' => (int) $row->won_count,
                'total_value' => (float) $row->total_value,
            ])
            ->values()
            ->all();
    }

    /**
     * Get win/loss reasons breakdown.
     */
    public function getWinLossReasons(string $outcome = 'lost'): array
    {
        $rows = DB::table('crm_win_loss_records')
            ->where('outcome', $outcome)
            ->whereNotNull('reason')
            ->select('reason', DB::raw('count(*) as count'))
            ->groupBy('reason')
            ->orderByDesc('count')
            ->get();

        $total = $rows->reduce(fn (int $carry, \stdClass $row) => $carry + (int) ($row->count ?? 0), 0);

        return $rows->map(function (\stdClass $row) use ($total) {
            $count = (int) $row->count;
            $percentage = $total > 0 ? round(($count / $total) * 100, 2) : 0.0;

            return [
                'reason' => (string) $row->reason,
                'count' => $count,
                'percentage' => $percentage,
            ];
        })->values()->all();
    }

    /**
     * Get average sales cycle in days (from won deals).
     */
    public function getAvgSalesCycle(): float
    {
        $avg = WinLossRecord::where('outcome', 'won')
            ->whereNotNull('sales_cycle_days')
            ->avg('sales_cycle_days');

        return round((float) ($avg ?? 0), 2);
    }

    /**
     * Get overall analytics dashboard.
     */
    public function getDashboard(): array
    {
        $winRate = $this->getWinRate();
        $openDeals = (int) DB::table('crm_opportunities')
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['closed_won', 'closed_lost'])
            ->count();
        $pipelineValue = (float) DB::table('crm_opportunities')
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['closed_won', 'closed_lost'])
            ->sum('amount');
        $avgDealSize = $openDeals > 0 ? round($pipelineValue / $openDeals, 4) : 0.0;
        $salesVelocity = $this->getSalesVelocity();
        $avgSalesCycle = $this->getAvgSalesCycle();

        return [
            'win_rate' => $winRate,
            'total_pipeline_value' => $pipelineValue,
            'avg_deal_size' => $avgDealSize,
            'sales_velocity' => $salesVelocity,
            'avg_sales_cycle_days' => $avgSalesCycle,
            'open_deals' => $openDeals,
        ];
    }
}
