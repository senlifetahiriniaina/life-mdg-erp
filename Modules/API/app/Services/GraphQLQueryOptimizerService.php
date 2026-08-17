<?php

namespace Modules\API\Services;

use Illuminate\Support\Facades\Cache;

class GraphQLQueryOptimizerService
{
    const CACHE_TTL = 3600;
    const MAX_QUERY_DEPTH = 10;
    const MAX_QUERY_COMPLEXITY = 1000;

    /**
     * Optimize GraphQL query
     */
    public function optimizeQuery(array $query): array
    {
        $queryId = uniqid('query_');

        $optimized = [
            'id' => $queryId,
            'original_query' => $query,
            'depth' => $this->calculateDepth($query),
            'complexity' => $this->calculateComplexity($query),
            'fields' => $this->extractFields($query),
            'batches' => $this->identifyBatches($query),
            'eager_loads' => $this->suggestEagerLoads($query),
            'caching' => $this->suggestCaching($query),
        ];

        Cache::put("graphql:query:{$queryId}", $optimized, now()->addHours(1));

        return [
            'query_id' => $queryId,
            'status' => 'optimized',
            'depth' => $optimized['depth'],
            'complexity' => $optimized['complexity'],
        ];
    }

    /**
     * Calculate query depth
     */
    private function calculateDepth(array $query, int $depth = 0): int
    {
        $maxDepth = $depth;

        if (isset($query['selections'])) {
            foreach ($query['selections'] as $selection) {
                $childDepth = $this->calculateDepth($selection, $depth + 1);
                $maxDepth = max($maxDepth, $childDepth);
            }
        }

        return $maxDepth;
    }

    /**
     * Calculate query complexity
     */
    private function calculateComplexity(array $query, int $complexity = 0): int
    {
        $baseComplexity = $complexity;

        if (isset($query['selections'])) {
            foreach ($query['selections'] as $selection) {
                // Each field adds 1 to complexity
                $baseComplexity += 1;

                // Multiplier for arrays. Bug fix: not every selection carries a 'type' key
                // (e.g. a plain scalar field selection is just ['name' => 'id']) --
                // accessing $selection['type'] directly threw on undefined array key.
                if (($selection['type'] ?? null) === 'array') {
                    $baseComplexity *= 10;
                }

                // Recursive complexity for nested selections
                $baseComplexity += $this->calculateComplexity($selection, 0);
            }
        }

        return $baseComplexity;
    }

    /**
     * Extract field selections from query
     */
    private function extractFields(array $query, array $prefix = []): array
    {
        $fields = [];

        if (isset($query['selections'])) {
            foreach ($query['selections'] as $selection) {
                $path = array_merge($prefix, [$selection['name']]);
                $fields[] = implode('.', $path);

                if (isset($selection['selections'])) {
                    $fields = array_merge($fields, $this->extractFields($selection, $path));
                }
            }
        }

        return $fields;
    }

    /**
     * Identify fields suitable for batch loading
     */
    private function identifyBatches(array $query): array
    {
        $batches = [];

        if (isset($query['selections'])) {
            foreach ($query['selections'] as $selection) {
                // Same undefined-array-key bug as calculateComplexity() above -- not every
                // selection carries a 'type' key.
                if (($selection['type'] ?? null) === 'array' || ($selection['multiplicity'] ?? false)) {
                    $batches[] = [
                        'field' => $selection['name'],
                        'batch_size' => 100,
                        'type' => 'dataloader',
                    ];
                }
            }
        }

        return $batches;
    }

    /**
     * Suggest eager loading
     */
    private function suggestEagerLoads(array $query, array $path = []): array
    {
        $suggestions = [];

        if (isset($query['selections'])) {
            foreach ($query['selections'] as $selection) {
                if (isset($selection['selections']) && !empty($selection['selections'])) {
                    // This selection has nested queries, suggest eager loading
                    $suggestions[] = [
                        'field' => $selection['name'],
                        'relations' => $this->extractFields($selection),
                        'priority' => 'high',
                    ];
                }
            }
        }

        return $suggestions;
    }

    /**
     * Suggest caching strategy
     */
    private function suggestCaching(array $query): array
    {
        return [
            'strategy' => 'query-result',
            'ttl' => 300,
            'invalidate_on' => ['mutation'],
            'key' => 'graphql:' . md5(json_encode($query)),
        ];
    }

    /**
     * Validate query complexity
     */
    public function validateComplexity(string $queryId): array
    {
        $query = Cache::get("graphql:query:{$queryId}");

        if (!$query) {
            return ['error' => 'Query not found'];
        }

        $depth = $query['depth'];
        $complexity = $query['complexity'];

        $valid = true;
        $errors = [];

        if ($depth > self::MAX_QUERY_DEPTH) {
            $valid = false;
            // Bug fix: `$this->MAX_QUERY_DEPTH` referenced a nonexistent instance property
            // (this is a class constant, only accessible via self::) -- PHP also doesn't
            // support `self::CONST` interpolation inside a string, so the constant is
            // extracted to a local variable first.
            $maxDepth = self::MAX_QUERY_DEPTH;
            $errors[] = "Query depth {$depth} exceeds maximum {$maxDepth}";
        }

        if ($complexity > self::MAX_QUERY_COMPLEXITY) {
            $valid = false;
            $maxComplexity = self::MAX_QUERY_COMPLEXITY;
            $errors[] = "Query complexity {$complexity} exceeds maximum {$maxComplexity}";
        }

        return [
            'query_id' => $queryId,
            'valid' => $valid,
            'depth' => $depth,
            'complexity' => $complexity,
            'errors' => $errors,
        ];
    }

    /**
     * Batch dataloader queries
     */
    public function createDataLoader(string $loaderName, callable $batchFn): array
    {
        $loaderId = uniqid('loader_');

        $loader = [
            'id' => $loaderId,
            'name' => $loaderName,
            'batch_size' => 100,
            'cache' => true,
            'batch_fn' => 'dataloader_' . $loaderName,
            'created_at' => now()->toIso8601String(),
        ];

        Cache::put("graphql:dataloader:{$loaderId}", $loader, now()->addDays(365));

        return [
            'loader_id' => $loaderId,
            'name' => $loaderName,
            'status' => 'created',
        ];
    }

    /**
     * Queue for batch processing
     */
    public function queueForBatch(string $loaderId, string $key): array
    {
        $loader = Cache::get("graphql:dataloader:{$loaderId}");

        if (!$loader) {
            return ['error' => 'Loader not found'];
        }

        $queue = Cache::get("graphql:batch:queue:{$loaderId}", []);
        $queue[] = $key;

        Cache::put("graphql:batch:queue:{$loaderId}", $queue, now()->addSeconds(1));

        return [
            'loader_id' => $loaderId,
            'key' => $key,
            'queue_size' => count($queue),
        ];
    }

    /**
     * Process batch
     */
    public function processBatch(string $loaderId): array
    {
        $loader = Cache::get("graphql:dataloader:{$loaderId}");

        if (!$loader) {
            return ['error' => 'Loader not found'];
        }

        $queue = Cache::get("graphql:batch:queue:{$loaderId}", []);

        if (empty($queue)) {
            return [
                'loader_id' => $loaderId,
                'processed' => 0,
            ];
        }

        $results = [];

        foreach (array_chunk($queue, $loader['batch_size']) as $batch) {
            // Batch database query here
            $results = array_merge($results, $batch);
        }

        Cache::forget("graphql:batch:queue:{$loaderId}");

        return [
            'loader_id' => $loaderId,
            'processed' => count($results),
            'results' => $results,
        ];
    }

    /**
     * Get query optimization report
     */
    public function getOptimizationReport(string $queryId): array
    {
        $query = Cache::get("graphql:query:{$queryId}");

        if (!$query) {
            return ['error' => 'Query not found'];
        }

        return [
            'query_id' => $queryId,
            'depth' => $query['depth'],
            'complexity' => $query['complexity'],
            'fields_selected' => count($query['fields']),
            'batch_candidates' => count($query['batches']),
            'eager_load_suggestions' => count($query['eager_loads']),
            'estimated_queries_without_optimization' => count($query['fields']) + count($query['eager_loads']),
            'estimated_queries_with_optimization' => count($query['batches']) + 1,
            'improvement_ratio' => round(
                (count($query['fields']) + count($query['eager_loads'])) /
                max(count($query['batches']) + 1, 1),
                2
            ),
        ];
    }
}
