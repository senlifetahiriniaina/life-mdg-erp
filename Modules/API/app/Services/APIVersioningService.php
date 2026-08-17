<?php

namespace Modules\API\Services;

use Illuminate\Support\Facades\Cache;

class APIVersioningService
{
    const CACHE_TTL = 86400;
    const LATEST_VERSION = '2.0.0';

    /**
     * Get API version info
     */
    public function getVersionInfo(string $version = null): array
    {
        // $versionInfo below is keyed 'v1.0.0'/'v2.0.0'/... but LATEST_VERSION
        // itself is unprefixed ('2.0.0') -- other code/tests assert that
        // constant's raw value, so prefix only at lookup time here.
        $version = $version ?? ('v'.self::LATEST_VERSION);

        $versionInfo = [
            'v1.0.0' => [
                'version' => 'v1.0.0',
                'released' => '2024-01-01',
                'status' => 'deprecated',
                'deprecated_at' => '2026-05-01',
                'sunset_date' => '2026-12-31',
                'features' => [
                    'REST API',
                    'Basic CRUD operations',
                    'Role-based access control',
                    'Rate limiting',
                ],
            ],
            'v1.5.0' => [
                'version' => 'v1.5.0',
                'released' => '2025-06-01',
                'status' => 'supported',
                'support_until' => '2026-12-31',
                'features' => [
                    'REST API enhancements',
                    'Webhook support',
                    'Advanced filtering',
                    'Pagination improvements',
                    'Bulk operations',
                ],
            ],
            'v2.0.0' => [
                'version' => 'v2.0.0',
                'released' => '2026-05-15',
                'status' => 'latest',
                'features' => [
                    'GraphQL API',
                    'Real-time subscriptions',
                    'Query optimization',
                    'Dataloader support',
                    'Backward compatibility with v1',
                    'Schema stitching',
                    'Federated graphs',
                ],
            ],
        ];

        return $versionInfo[$version] ?? ['error' => 'Version not found'];
    }

    /**
     * Define breaking changes
     */
    public function getBreakingChanges(string $fromVersion, string $toVersion): array
    {
        $breakingChanges = [
            'v1.0.0_to_v1.5.0' => [
                'deprecations' => [
                    ['field' => 'user.metadata', 'replacement' => 'user.attributes', 'timeline' => '2026-12-31'],
                ],
                'removals' => [],
                'changes' => [
                    ['endpoint' => '/api/users', 'change' => 'Pagination default changed from 10 to 20'],
                ],
            ],
            'v1.5.0_to_v2.0.0' => [
                'deprecations' => [
                    ['endpoint' => '/api/v1/*', 'replacement' => '/graphql', 'timeline' => '2026-12-31'],
                    ['header' => 'X-Custom-Auth', 'replacement' => 'Authorization: Bearer', 'timeline' => '2026-12-31'],
                ],
                'removals' => [
                    ['endpoint' => '/api/v1/deprecated-route', 'reason' => 'Functionality moved to GraphQL'],
                ],
                'changes' => [
                    ['field' => 'response.meta', 'change' => 'Replaced with response.metadata'],
                    ['error_format' => 'Standard GraphQL errors', 'previous' => 'Custom error format'],
                ],
            ],
        ];

        $key = "{$fromVersion}_to_{$toVersion}";

        return $breakingChanges[$key] ?? ['no_breaking_changes' => true];
    }

    /**
     * Get migration guide
     */
    public function getMigrationGuide(string $fromVersion, string $toVersion): array
    {
        $guides = [
            'v1.5.0_to_v2.0.0' => [
                'title' => 'Migrating from REST API v1.5 to GraphQL API v2.0',
                'introduction' => 'This guide will help you migrate from the REST API to the new GraphQL API.',
                'steps' => [
                    [
                        'step' => 1,
                        'title' => 'Update authentication headers',
                        'description' => 'Replace X-API-Key with Authorization: Bearer token',
                        'code' => 'Authorization: Bearer $ACCESS_TOKEN',
                    ],
                    [
                        'step' => 2,
                        'title' => 'Convert REST endpoints to GraphQL queries',
                        'description' => 'Map your REST endpoints to equivalent GraphQL queries',
                        'examples' => [
                            'GET /api/users → query { users { id name email } }',
                            'POST /api/users → mutation { createUser(input: {name: "..."}) { id } }',
                        ],
                    ],
                    [
                        'step' => 3,
                        'title' => 'Update error handling',
                        'description' => 'Handle GraphQL errors in the errors array of response',
                        'code' => 'response.errors.map(e => e.message)',
                    ],
                    [
                        'step' => 4,
                        'title' => 'Implement subscriptions for real-time data',
                        'description' => 'Use WebSocket subscriptions for real-time updates',
                        'code' => 'subscription { userCreated { id name } }',
                    ],
                ],
                'backwards_compatibility' => 'REST API will continue to work in 2.0.0 until 2026-12-31',
            ],
        ];

        $key = "{$fromVersion}_to_{$toVersion}";

        return $guides[$key] ?? ['error' => 'Migration guide not found'];
    }

    /**
     * Get deprecated endpoints
     */
    public function getDeprecatedEndpoints(string $version): array
    {
        $deprecations = Cache::get("api:deprecations:{$version}", []);

        if (empty($deprecations)) {
            $deprecations = [
                'v1.0.0' => [],
                'v1.5.0' => [
                    [
                        'endpoint' => 'GET /api/users/:id/metadata',
                        'replacement' => 'GET /api/users/:id (includes attributes field)',
                        'sunset_date' => '2026-12-31',
                        'warning_header' => 'Deprecation: 2026-12-31',
                    ],
                ],
                'v2.0.0' => [
                    [
                        'endpoint' => 'REST /api/v1/* (entire v1 API)',
                        'replacement' => 'GraphQL /graphql',
                        'sunset_date' => '2026-12-31',
                        'status' => 'use_new_api',
                    ],
                ],
            ];

            Cache::put("api:deprecations:{$version}", $deprecations[$version] ?? [], now()->addDays(365));
        }

        return $deprecations[$version] ?? [];
    }

    /**
     * Create API version redirect
     */
    public function createVersionRedirect(string $oldVersion, string $newVersion, string $endpoint): array
    {
        $redirectId = uniqid('redirect_');

        $redirect = [
            'id' => $redirectId,
            'old_version' => $oldVersion,
            'new_version' => $newVersion,
            'old_endpoint' => $endpoint,
            'new_endpoint' => $this->mapEndpoint($endpoint, $oldVersion, $newVersion),
            'status_code' => 301,
            'created_at' => now()->toIso8601String(),
        ];

        Cache::put("api:redirect:{$redirectId}", $redirect, now()->addDays(365));

        return [
            'redirect_id' => $redirectId,
            'status' => 'created',
            'from' => $redirect['old_endpoint'],
            'to' => $redirect['new_endpoint'],
        ];
    }

    /**
     * Map old endpoint to new endpoint
     */
    private function mapEndpoint(string $endpoint, string $oldVersion, string $newVersion): string
    {
        // Map REST endpoints to GraphQL equivalents
        $mapping = [
            'GET /api/users' => 'POST /graphql (query users)',
            'POST /api/users' => 'POST /graphql (mutation createUser)',
            'GET /api/users/:id' => 'POST /graphql (query user)',
            'PUT /api/users/:id' => 'POST /graphql (mutation updateUser)',
            'DELETE /api/users/:id' => 'POST /graphql (mutation deleteUser)',
        ];

        return $mapping[$endpoint] ?? str_replace("/api/{$oldVersion}", "/api/{$newVersion}", $endpoint);
    }

    /**
     * Check client compatibility
     */
    public function checkClientCompatibility(string $clientVersion, string $apiVersion): array
    {
        $compatible = true;
        $warnings = [];

        // Simple semantic versioning check -- normalize an optional leading
        // 'v' first so '2.0.0' vs 'v2.0.0' compare equal on major version.
        [$clientMajor] = explode('.', ltrim($clientVersion, 'v'));
        [$apiMajor] = explode('.', ltrim($apiVersion, 'v'));

        if ($clientMajor !== $apiMajor) {
            $compatible = false;
            $warnings[] = "Client version {$clientVersion} may not be compatible with API {$apiVersion}";
        }

        return [
            'compatible' => $compatible,
            'client_version' => $clientVersion,
            'api_version' => $apiVersion,
            'warnings' => $warnings,
        ];
    }

    /**
     * Get compatibility matrix
     */
    public function getCompatibilityMatrix(): array
    {
        return [
            'client_versions' => ['1.0.0', '1.5.0', '2.0.0'],
            'api_versions' => ['1.0.0', '1.5.0', '2.0.0'],
            'compatibility' => [
                '1.0.0' => ['1.0.0' => 'full', '1.5.0' => 'partial', '2.0.0' => 'no'],
                '1.5.0' => ['1.0.0' => 'no', '1.5.0' => 'full', '2.0.0' => 'partial'],
                '2.0.0' => ['1.0.0' => 'no', '1.5.0' => 'partial', '2.0.0' => 'full'],
            ],
        ];
    }
}
