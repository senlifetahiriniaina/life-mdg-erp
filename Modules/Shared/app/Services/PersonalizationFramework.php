<?php

declare(strict_types=1);

namespace Modules\Shared\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Shared\Exceptions\PersonalizationException;

abstract class PersonalizationFramework extends BaseService
{
    protected const CACHE_TTL = 3600; // 1 hour
    protected const MIN_SEGMENT_SIZE = 10;
    protected const CONFIDENCE_THRESHOLD = 0.65;

    protected string $segmentationStrategy = 'default';
    protected array $userProfile = [];
    protected array $segmentRules = [];

    /**
     * Personalize content for user
     */
    public function personalizeContent(int $userId, array $contentOptions = []): array
    {
        try {
            $this->validateUserExists($userId);
            $userSegment = $this->determineUserSegment($userId);
            $personalization = $this->buildPersonalization($userId, $userSegment, $contentOptions);

            return $personalization;
        } catch (\Throwable $e) {
            Log::error('[Personalization] Personalization failed', [
                'company_id' => $this->companyId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw PersonalizationException::recommendationEngineFailed($e->getMessage());
        }
    }

    /**
     * Get user segment
     */
    public function getUserSegment(int $userId): array
    {
        $cacheKey = "personalization:segment:{$this->companyId}:{$userId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId) {
            return $this->determineUserSegment($userId);
        });
    }

    /**
     * Batch personalize for multiple users
     */
    public function batchPersonalize(array $userIds, array $contentOptions = []): Collection
    {
        return collect($userIds)->map(function ($userId) use ($contentOptions) {
            try {
                return $this->personalizeContent($userId, $contentOptions);
            } catch (\Throwable $e) {
                Log::warning('[Personalization] Batch personalization skipped user', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        })->filter();
    }

    /**
     * Get recommendation score for item
     */
    public function getRecommendationScore(int $userId, int $itemId): float
    {
        try {
            $userSegment = $this->getUserSegment($userId);
            $score = $this->calculateScore($userId, $itemId, $userSegment);

            return max(0, min(1, $score)); // Normalize 0-1
        } catch (\Throwable $e) {
            Log::error('[Personalization] Score calculation failed', [
                'user_id' => $userId,
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return 0.0;
        }
    }

    /**
     * Get recommendations
     */
    public function getRecommendations(int $userId, int $limit = 10): Collection
    {
        try {
            $userSegment = $this->getUserSegment($userId);
            $candidates = $this->getCandidateItems($userId, $userSegment);

            return $candidates
                ->map(fn ($item) => [
                    'item' => $item,
                    'score' => $this->calculateScore($userId, $item['id'], $userSegment),
                ])
                ->sortByDesc('score')
                ->take($limit)
                ->pluck('item');
        } catch (\Throwable $e) {
            Log::error('[Personalization] Recommendations failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Update user preferences
     */
    public function updatePreferences(int $userId, array $preferences): void
    {
        try {
            $this->validateUserExists($userId);
            $this->storePreferences($userId, $preferences);

            // Invalidate cache
            Cache::forget("personalization:segment:{$this->companyId}:{$userId}");

            Log::info('[Personalization] Preferences updated', [
                'user_id' => $userId,
                'company_id' => $this->companyId,
            ]);
        } catch (\Throwable $e) {
            throw PersonalizationException::preferencesNotConfigured($userId, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Track user interaction
     */
    public function trackInteraction(int $userId, int $itemId, string $action, ?float $value = null): void
    {
        try {
            $this->recordInteraction($userId, $itemId, $action, $value);

            // Invalidate segment cache to reflect new interaction
            Cache::forget("personalization:segment:{$this->companyId}:{$userId}");
        } catch (\Throwable $e) {
            Log::warning('[Personalization] Interaction tracking failed', [
                'user_id' => $userId,
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get personalization analytics
     */
    public function getAnalytics(array $filters = []): array
    {
        try {
            return [
                'total_users_segmented' => $this->countSegmentedUsers(),
                'average_recommendation_score' => $this->calculateAverageScore(),
                'segment_distribution' => $this->getSegmentDistribution(),
                'engagement_metrics' => $this->getEngagementMetrics(),
                'personalization_lift' => $this->calculatePersonalizationLift(),
            ];
        } catch (\Throwable $e) {
            Log::error('[Personalization] Analytics calculation failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    // ==================== Protected Abstract Methods ====================

    /**
     * Determine user segment (implement in child classes)
     */
    abstract protected function determineUserSegment(int $userId): array;

    /**
     * Calculate recommendation score (implement in child classes)
     */
    abstract protected function calculateScore(int $userId, int $itemId, array $segment): float;

    /**
     * Get candidate items (implement in child classes)
     */
    abstract protected function getCandidateItems(int $userId, array $segment): Collection;

    /**
     * Store preferences (implement in child classes)
     */
    abstract protected function storePreferences(int $userId, array $preferences): void;

    /**
     * Record interaction (implement in child classes)
     */
    abstract protected function recordInteraction(int $userId, int $itemId, string $action, ?float $value): void;

    // ==================== Protected Helper Methods ====================

    /**
     * Validate user exists
     */
    protected function validateUserExists(int $userId): void
    {
        $exists = DB::table('users')->where('id', $userId)->exists();

        if (!$exists) {
            throw PersonalizationException::userProfileMissing($userId);
        }
    }

    /**
     * Build personalization response
     */
    protected function buildPersonalization(int $userId, array $segment, array $options): array
    {
        return [
            'user_id' => $userId,
            'segment' => $segment,
            'recommendations' => $this->getRecommendations($userId, $options['limit'] ?? 10),
            'personalized_content' => $this->getPersonalizedContent($userId, $segment, $options),
            'confidence' => $this->calculateConfidence($userId, $segment),
        ];
    }

    /**
     * Get personalized content
     */
    protected function getPersonalizedContent(int $userId, array $segment, array $options): array
    {
        // Default implementation - override in child classes
        return [];
    }

    /**
     * Calculate confidence level
     */
    protected function calculateConfidence(int $userId, array $segment): float
    {
        return min(1.0, count($segment) > 0 ? 0.8 : 0.5);
    }

    /**
     * Count segmented users
     */
    protected function countSegmentedUsers(): int
    {
        return DB::table('user_segments')
            ->where('company_id', $this->companyId)
            ->distinct('user_id')
            ->count();
    }

    /**
     * Calculate average score
     */
    protected function calculateAverageScore(): float
    {
        return (float) DB::table('personalization_scores')
            ->where('company_id', $this->companyId)
            ->avg('score') ?? 0.0;
    }

    /**
     * Get segment distribution
     */
    protected function getSegmentDistribution(): array
    {
        return DB::table('user_segments')
            ->where('company_id', $this->companyId)
            ->groupBy('segment_name')
            ->selectRaw('segment_name, COUNT(*) as count')
            ->pluck('count', 'segment_name')
            ->toArray();
    }

    /**
     * Get engagement metrics
     */
    protected function getEngagementMetrics(): array
    {
        return [
            'click_through_rate' => 0.0,
            'conversion_rate' => 0.0,
            'avg_interaction_time' => 0,
        ];
    }

    /**
     * Calculate personalization lift
     */
    protected function calculatePersonalizationLift(): float
    {
        return 1.0; // Baseline
    }
}
