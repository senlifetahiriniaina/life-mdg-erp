<?php

namespace Modules\API\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class GraphQLSubscriptionManagerService
{
    const CACHE_TTL = 86400;
    const MAX_SUBSCRIBERS_PER_CHANNEL = 10000;

    /**
     * Create subscription
     */
    public function createSubscription(int $userId, string $topic, array $config = []): array
    {
        $subscriptionId = uniqid('sub_');

        $subscription = [
            'id' => $subscriptionId,
            'user_id' => $userId,
            'topic' => $topic,
            'filters' => $config['filters'] ?? [],
            'fields' => $config['fields'] ?? [],
            'polling_interval' => $config['polling_interval'] ?? 1000,
            'status' => 'active',
            'created_at' => now()->toIso8601String(),
        ];

        Cache::put("graphql:subscription:{$subscriptionId}", $subscription, now()->addDays(365));

        // Add to topic subscribers
        $subscribers = Cache::get("graphql:subscribers:{$topic}", []);

        if (count($subscribers) >= self::MAX_SUBSCRIBERS_PER_CHANNEL) {
            return ['error' => 'Maximum subscribers reached for this topic'];
        }

        $subscribers[] = $subscriptionId;
        Cache::put("graphql:subscribers:{$topic}", $subscribers, now()->addDays(365));

        // Subscribe to Redis channel
        Redis::subscribe(["graphql:topic:{$topic}"], function ($message) use ($subscriptionId, $userId) {
            $this->broadcastToSubscriber($subscriptionId, $userId, $message);
        });

        return [
            'subscription_id' => $subscriptionId,
            'topic' => $topic,
            'status' => 'created',
        ];
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(int $userId, string $subscriptionId): array
    {
        $subscription = Cache::get("graphql:subscription:{$subscriptionId}");

        if (!$subscription || $subscription['user_id'] !== $userId) {
            return ['error' => 'Subscription not found'];
        }

        $topic = $subscription['topic'];
        $subscribers = Cache::get("graphql:subscribers:{$topic}", []);
        $subscribers = array_filter($subscribers, fn($s) => $s !== $subscriptionId);

        Cache::put("graphql:subscribers:{$topic}", array_values($subscribers), now()->addDays(365));
        Cache::forget("graphql:subscription:{$subscriptionId}");

        return [
            'subscription_id' => $subscriptionId,
            'status' => 'cancelled',
        ];
    }

    /**
     * Publish event to topic
     */
    public function publishEvent(string $topic, array $data, array $metadata = []): array
    {
        $eventId = uniqid('event_');

        $event = [
            'id' => $eventId,
            'topic' => $topic,
            'data' => $data,
            'metadata' => $metadata,
            'timestamp' => now()->toIso8601String(),
        ];

        // Publish to Redis for real-time delivery
        Redis::publish("graphql:topic:{$topic}", json_encode($event));

        // Store in cache for recent events
        $recentEvents = Cache::get("graphql:events:{$topic}", []);
        array_unshift($recentEvents, $event);
        $recentEvents = array_slice($recentEvents, 0, 100); // Keep last 100 events
        Cache::put("graphql:events:{$topic}", $recentEvents, now()->addHours(1));

        return [
            'event_id' => $eventId,
            'topic' => $topic,
            'subscribers_count' => count(Cache::get("graphql:subscribers:{$topic}", [])),
            'status' => 'published',
        ];
    }

    /**
     * Get subscription details
     */
    public function getSubscription(string $subscriptionId): ?array
    {
        return Cache::get("graphql:subscription:{$subscriptionId}");
    }

    /**
     * Get user subscriptions
     */
    public function getUserSubscriptions(int $userId): array
    {
        $keys = Cache::getRedis()->keys('graphql:subscription:*');
        $userSubscriptions = [];

        foreach ($keys as $key) {
            $subscription = Cache::get($key);

            if ($subscription && $subscription['user_id'] === $userId) {
                $userSubscriptions[] = [
                    'subscription_id' => $subscription['id'],
                    'topic' => $subscription['topic'],
                    'status' => $subscription['status'],
                    'created_at' => $subscription['created_at'],
                ];
            }
        }

        return $userSubscriptions;
    }

    /**
     * Get topic subscribers count
     */
    public function getSubscribersCount(string $topic): int
    {
        return count(Cache::get("graphql:subscribers:{$topic}", []));
    }

    /**
     * Get recent events for topic
     */
    public function getRecentEvents(string $topic, int $limit = 10): array
    {
        $events = Cache::get("graphql:events:{$topic}", []);

        return array_slice($events, 0, $limit);
    }

    /**
     * Broadcast to subscriber
     */
    private function broadcastToSubscriber(string $subscriptionId, int $userId, string $message): void
    {
        $event = json_decode($message, true);

        // Filter event based on subscription filters
        $subscription = Cache::get("graphql:subscription:{$subscriptionId}");

        if ($subscription && $this->eventMatchesFilters($event, $subscription['filters'])) {
            // Send to user via WebSocket or polling
            Cache::put(
                "graphql:subscription:message:{$subscriptionId}",
                $event,
                now()->addHours(1)
            );
        }
    }

    /**
     * Check if event matches subscription filters
     */
    private function eventMatchesFilters(array $event, array $filters): bool
    {
        if (empty($filters)) {
            return true;
        }

        foreach ($filters as $filter) {
            $field = $filter['field'];
            $operator = $filter['operator'] ?? 'equals';
            $value = $filter['value'];

            $eventValue = $event['data'][$field] ?? null;

            if (!$this->evaluateFilter($eventValue, $operator, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate filter condition
     */
    private function evaluateFilter($eventValue, string $operator, $filterValue): bool
    {
        return match($operator) {
            'equals' => $eventValue === $filterValue,
            'not_equals' => $eventValue !== $filterValue,
            'greater_than' => $eventValue > $filterValue,
            'less_than' => $eventValue < $filterValue,
            'contains' => strpos((string)$eventValue, (string)$filterValue) !== false,
            'in' => in_array($eventValue, (array)$filterValue),
            default => true,
        };
    }

    /**
     * Get pending messages for subscription
     */
    public function getPendingMessages(string $subscriptionId): array
    {
        $message = Cache::get("graphql:subscription:message:{$subscriptionId}");

        if ($message) {
            Cache::forget("graphql:subscription:message:{$subscriptionId}");

            return [$message];
        }

        return [];
    }
}
