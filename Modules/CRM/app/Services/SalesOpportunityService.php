<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\Cache;

class SalesOpportunityService
{
    const CACHE_TTL = 86400;

    /**
     * Create sales opportunity
     */
    public function createOpportunity(array $data): array
    {
        $opportunityId = uniqid('opp_');

        $opportunity = [
            'id' => $opportunityId,
            'customer_id' => $data['customer_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'amount' => $data['amount'],
            'probability' => $data['probability'] ?? 50,
            'stage' => $data['stage'] ?? 'prospecting',
            'close_date' => $data['close_date'],
            'owner_id' => auth()->id(),
            'status' => 'active',
            'created_at' => now()->toIso8601String(),
        ];

        Cache::put("opportunity:{$opportunityId}", $opportunity, self::CACHE_TTL);

        return [
            'opportunity_id' => $opportunityId,
            'status' => 'created',
            'expected_value' => $this->calculateExpectedValue($opportunity),
        ];
    }

    /**
     * Update opportunity stage
     */
    public function updateStage(string $opportunityId, string $newStage): array
    {
        $opportunity = Cache::get("opportunity:{$opportunityId}");

        if (!$opportunity) {
            return ['error' => 'Opportunity not found'];
        }

        $oldStage = $opportunity['stage'];
        $opportunity['stage'] = $newStage;
        $opportunity['updated_at'] = now()->toIso8601String();

        Cache::put("opportunity:{$opportunityId}", $opportunity, self::CACHE_TTL);

        return [
            'opportunity_id' => $opportunityId,
            'old_stage' => $oldStage,
            'new_stage' => $newStage,
            'message' => 'Stage updated successfully',
        ];
    }

    /**
     * Calculate expected value
     */
    private function calculateExpectedValue(array $opportunity): float
    {
        return ($opportunity['amount'] * $opportunity['probability']) / 100;
    }

    /**
     * Get pipeline summary
     */
    public function getPipelineSummary(int $customerId): array
    {
        $stages = ['prospecting', 'qualification', 'proposal', 'negotiation', 'closed_won', 'closed_lost'];
        $summary = [];
        $totalValue = 0;
        $totalExpectedValue = 0;

        foreach ($stages as $stage) {
            $opportunities = []; // Get from database in real implementation
            $count = count($opportunities);
            $stageValue = array_sum(array_column($opportunities, 'amount'));
            $stageExpectedValue = array_sum(array_map(
                fn($opp) => $this->calculateExpectedValue($opp),
                $opportunities
            ));

            $summary[$stage] = [
                'count' => $count,
                'value' => $stageValue,
                'expected_value' => $stageExpectedValue,
            ];

            $totalValue += $stageValue;
            $totalExpectedValue += $stageExpectedValue;
        }

        return [
            'customer_id' => $customerId,
            'total_pipeline_value' => $totalValue,
            'total_expected_value' => $totalExpectedValue,
            'by_stage' => $summary,
        ];
    }

    /**
     * Get opportunities by status
     */
    public function getOpportunitiesByStatus(string $status, int $limit = 50): array
    {
        $cacheKey = "opportunities:status:{$status}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($status, $limit) {
            return []; // Query database in real implementation
        });
    }

    /**
     * Close opportunity
     */
    public function closeOpportunity(string $opportunityId, string $outcome, ?string $reason = null): array
    {
        $opportunity = Cache::get("opportunity:{$opportunityId}");

        if (!$opportunity) {
            return ['error' => 'Opportunity not found'];
        }

        $opportunity['status'] = 'closed';
        $opportunity['outcome'] = $outcome; // 'won' or 'lost'
        $opportunity['close_reason'] = $reason;
        $opportunity['closed_at'] = now()->toIso8601String();

        Cache::put("opportunity:{$opportunityId}", $opportunity, self::CACHE_TTL);

        return [
            'opportunity_id' => $opportunityId,
            'outcome' => $outcome,
            'final_value' => $outcome === 'won' ? $opportunity['amount'] : 0,
            'message' => "Opportunity closed as {$outcome}",
        ];
    }
}
