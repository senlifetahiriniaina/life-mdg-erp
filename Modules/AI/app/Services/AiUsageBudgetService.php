<?php

declare(strict_types=1);

namespace Modules\AI\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\AI\Models\AiUsageLimit;

class AiUsageBudgetService
{
    // claude-sonnet-4-6 pricing (USD per million tokens)
    private const COST_PER_M_INPUT  = 3.0;
    private const COST_PER_M_OUTPUT = 15.0;

    /**
     * Get period start and end timestamps for the given period type.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    private function getPeriodBounds(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'daily'   => ['start' => $now->copy()->startOfDay(),   'end' => $now->copy()->endOfDay()],
            'weekly'  => ['start' => $now->copy()->startOfWeek(),  'end' => $now->copy()->endOfWeek()],
            'monthly' => ['start' => $now->copy()->startOfMonth(), 'end' => $now->copy()->endOfMonth()],
            default   => ['start' => $now->copy()->startOfMonth(), 'end' => $now->copy()->endOfMonth()],
        };
    }

    /**
     * Get aggregated usage for a user within the given period.
     *
     * @return array{tokens_in: int, tokens_out: int, cost_usd: float, call_count: int, period_start: string, period_end: string}
     */
    public function getUsage(int $userId, int $tenantId, string $period = 'monthly'): array
    {
        $bounds = $this->getPeriodBounds($period);

        $row = DB::table('ai_usage_logs')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$bounds['start'], $bounds['end']])
            ->selectRaw('
                COALESCE(SUM(tokens_in), 0)   AS tokens_in,
                COALESCE(SUM(tokens_out), 0)  AS tokens_out,
                COALESCE(SUM(cost_usd), 0)    AS cost_usd,
                COUNT(*)                       AS call_count
            ')
            ->first();

        return [
            'tokens_in'    => (int)   ($row->tokens_in    ?? 0),
            'tokens_out'   => (int)   ($row->tokens_out   ?? 0),
            'cost_usd'     => (float) ($row->cost_usd     ?? 0.0),
            'call_count'   => (int)   ($row->call_count   ?? 0),
            'period_start' => $bounds['start']->toIso8601String(),
            'period_end'   => $bounds['end']->toIso8601String(),
        ];
    }

    /**
     * Get the applicable limit for a user: user-specific first, then tenant global, then null.
     */
    public function getLimit(int $userId, int $tenantId): ?AiUsageLimit
    {
        // User-specific limit
        $limit = AiUsageLimit::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('active', true)
            ->first();

        if ($limit) {
            return $limit;
        }

        // Tenant-wide default (user_id IS NULL)
        return AiUsageLimit::where('tenant_id', $tenantId)
            ->whereNull('user_id')
            ->where('active', true)
            ->first();
    }

    /**
     * Check whether a user is allowed to make another AI call.
     *
     * @return array{
     *   allowed: bool,
     *   remaining_usd: float,
     *   remaining_tokens: int,
     *   usage_pct: float,
     *   limit: AiUsageLimit|null
     * }
     */
    public function checkLimit(int $userId, int $tenantId): array
    {
        $limit = $this->getLimit($userId, $tenantId);

        if (!$limit) {
            return [
                'allowed'          => true,
                'remaining_usd'    => PHP_FLOAT_MAX,
                'remaining_tokens' => PHP_INT_MAX,
                'usage_pct'        => 0.0,
                'limit'            => null,
            ];
        }

        $usage   = $this->getUsage($userId, $tenantId, $limit->period);
        $limitVal = (float) $limit->limit_value;

        if ($limit->limit_type === 'usd') {
            $used      = $usage['cost_usd'];
            $remaining = max(0.0, $limitVal - $used);
            $pct       = $limitVal > 0 ? min(100.0, ($used / $limitVal) * 100) : 0.0;
            $exceeded  = $used >= $limitVal;

            return [
                'allowed'          => !($exceeded && $limit->block_on_exceed),
                'remaining_usd'    => round($remaining, 6),
                'remaining_tokens' => PHP_INT_MAX,
                'usage_pct'        => round($pct, 2),
                'limit'            => $limit,
            ];
        }

        // token-based limit
        $usedTokens      = $usage['tokens_in'] + $usage['tokens_out'];
        $remainingTokens = max(0, (int) $limitVal - $usedTokens);
        $pct             = $limitVal > 0 ? min(100.0, ($usedTokens / $limitVal) * 100) : 0.0;
        $exceeded        = $usedTokens >= (int) $limitVal;

        return [
            'allowed'          => !($exceeded && $limit->block_on_exceed),
            'remaining_usd'    => PHP_FLOAT_MAX,
            'remaining_tokens' => $remainingTokens,
            'usage_pct'        => round($pct, 2),
            'limit'            => $limit,
        ];
    }

    /**
     * Compute cost in USD from token counts.
     */
    public function computeCost(int $tokensIn, int $tokensOut): float
    {
        return ($tokensIn / 1_000_000 * self::COST_PER_M_INPUT)
             + ($tokensOut / 1_000_000 * self::COST_PER_M_OUTPUT);
    }

    /**
     * Log an AI usage record to ai_usage_logs.
     */
    public function logUsage(
        int    $userId,
        int    $tenantId,
        string $module,
        string $action,
        int    $tokensIn,
        int    $tokensOut,
        string $model        = 'claude-sonnet-4-6',
        string $endpointType = 'assist',
        array  $contextSummary = [],
    ): void {
        $costUsd = $this->computeCost($tokensIn, $tokensOut);

        DB::table('ai_usage_logs')->insert([
            'tenant_id'       => $tenantId,
            'user_id'         => $userId,
            'module'          => $module,
            'action'          => $action,
            'locale'          => 'fr',
            'tokens_used'     => $tokensIn + $tokensOut,
            'tokens_in'       => $tokensIn,
            'tokens_out'      => $tokensOut,
            'cost_usd'        => $costUsd,
            'model'           => $model,
            'endpoint_type'   => $endpointType,
            'context_summary' => empty($contextSummary) ? null : json_encode($contextSummary),
            'from_cache'      => false,
            'ai_enabled'      => true,
            'response_time_ms'=> 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    /**
     * Create or update a usage limit for a user (or tenant-wide if $userId is null).
     */
    public function setLimit(
        int    $tenantId,
        ?int   $userId,
        string $limitType,
        float  $limitValue,
        string $period,
        bool   $blockOnExceed = false,
    ): AiUsageLimit {
        return AiUsageLimit::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'user_id'   => $userId,
                'period'    => $period,
            ],
            [
                'limit_type'      => $limitType,
                'limit_value'     => $limitValue,
                'block_on_exceed' => $blockOnExceed,
                'active'          => true,
            ]
        );
    }

    /**
     * Get admin summary: top users by cost, totals, call counts for a tenant.
     *
     * @return array{
     *   total_cost_usd: float,
     *   total_calls: int,
     *   total_tokens_in: int,
     *   total_tokens_out: int,
     *   period_start: string,
     *   period_end: string,
     *   top_users: array<int, array{user_id: int, cost_usd: float, call_count: int, tokens_in: int, tokens_out: int}>
     * }
     */
    public function getAdminSummary(int $tenantId, string $period = 'monthly', int $topN = 10): array
    {
        $bounds = $this->getPeriodBounds($period);

        $totals = DB::table('ai_usage_logs')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$bounds['start'], $bounds['end']])
            ->selectRaw('
                COALESCE(SUM(cost_usd), 0)   AS total_cost_usd,
                COUNT(*)                      AS total_calls,
                COALESCE(SUM(tokens_in), 0)  AS total_tokens_in,
                COALESCE(SUM(tokens_out), 0) AS total_tokens_out
            ')
            ->first();

        $topUsers = DB::table('ai_usage_logs')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$bounds['start'], $bounds['end']])
            ->whereNotNull('user_id')
            ->selectRaw('
                user_id,
                COALESCE(SUM(cost_usd), 0)   AS cost_usd,
                COUNT(*)                      AS call_count,
                COALESCE(SUM(tokens_in), 0)  AS tokens_in,
                COALESCE(SUM(tokens_out), 0) AS tokens_out
            ')
            ->groupBy('user_id')
            ->orderByDesc('cost_usd')
            ->limit($topN)
            ->get()
            ->map(fn ($r) => [
                'user_id'    => (int)   $r->user_id,
                'cost_usd'   => (float) $r->cost_usd,
                'call_count' => (int)   $r->call_count,
                'tokens_in'  => (int)   $r->tokens_in,
                'tokens_out' => (int)   $r->tokens_out,
            ])
            ->toArray();

        return [
            'total_cost_usd'   => (float) ($totals->total_cost_usd   ?? 0.0),
            'total_calls'      => (int)   ($totals->total_calls       ?? 0),
            'total_tokens_in'  => (int)   ($totals->total_tokens_in   ?? 0),
            'total_tokens_out' => (int)   ($totals->total_tokens_out  ?? 0),
            'period_start'     => $bounds['start']->toIso8601String(),
            'period_end'       => $bounds['end']->toIso8601String(),
            'top_users'        => $topUsers,
        ];
    }
}
