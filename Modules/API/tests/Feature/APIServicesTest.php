<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Modules\API\Services\APIVersioningService;
use Modules\API\Services\GraphQLQueryOptimizerService;
use Modules\API\Services\GraphQLSchemaBuilderService;
use Modules\API\Services\GraphQLSubscriptionManagerService;

// ---------------------------------------------------------------------------
// APIVersioningService
// ---------------------------------------------------------------------------

test('APIVersioningService can be instantiated', function () {
    $service = new APIVersioningService();
    expect($service)->toBeInstanceOf(APIVersioningService::class);
});

test('getVersionInfo returns latest version info by default', function () {
    $service = new APIVersioningService();

    $info = $service->getVersionInfo();

    expect($info)->toHaveKey('version')
        ->toHaveKey('status')
        ->and($info['version'])->toBe('v2.0.0')
        ->and($info['status'])->toBe('latest');
});

test('getVersionInfo returns deprecated status for v1.0.0', function () {
    $service = new APIVersioningService();

    $info = $service->getVersionInfo('v1.0.0');

    expect($info['status'])->toBe('deprecated');
});

test('getVersionInfo returns error for unknown version', function () {
    $service = new APIVersioningService();

    $result = $service->getVersionInfo('v99.0.0');

    expect($result)->toHaveKey('error');
});

test('getBreakingChanges returns migration details between versions', function () {
    $service = new APIVersioningService();

    $changes = $service->getBreakingChanges('v1.5.0', 'v2.0.0');

    expect($changes)->toHaveKey('deprecations')
        ->toHaveKey('removals')
        ->toHaveKey('changes');
});

test('getBreakingChanges returns no_breaking_changes for unknown pair', function () {
    $service = new APIVersioningService();

    $result = $service->getBreakingChanges('v1.0.0', 'v99.0.0');

    expect($result)->toHaveKey('no_breaking_changes')
        ->and($result['no_breaking_changes'])->toBeTrue();
});

test('getMigrationGuide returns steps array', function () {
    $service = new APIVersioningService();

    $guide = $service->getMigrationGuide('v1.5.0', 'v2.0.0');

    expect($guide)->toHaveKey('steps')
        ->and($guide['steps'])->toBeArray()
        ->and(count($guide['steps']))->toBeGreaterThan(0);
});

test('getMigrationGuide returns error for unknown migration path', function () {
    $service = new APIVersioningService();

    $result = $service->getMigrationGuide('v0.1.0', 'v99.0.0');

    expect($result)->toHaveKey('error');
});

test('createVersionRedirect returns redirect_id', function () {
    $service = new APIVersioningService();

    $result = $service->createVersionRedirect('v1.5.0', 'v2.0.0', 'GET /api/users');

    expect($result)->toHaveKey('redirect_id')
        ->toHaveKey('status')
        ->and($result['status'])->toBe('created');
});

test('checkClientCompatibility full compatibility within same major version', function () {
    $service = new APIVersioningService();

    $result = $service->checkClientCompatibility('2.0.0', '2.0.0');

    expect($result['compatible'])->toBeTrue()
        ->and($result['warnings'])->toBeEmpty();
});

test('checkClientCompatibility detects incompatibility across major versions', function () {
    $service = new APIVersioningService();

    $result = $service->checkClientCompatibility('1.0.0', '2.0.0');

    expect($result['compatible'])->toBeFalse()
        ->and($result['warnings'])->not->toBeEmpty();
});

test('getCompatibilityMatrix returns full matrix structure', function () {
    $service = new APIVersioningService();

    $matrix = $service->getCompatibilityMatrix();

    expect($matrix)->toHaveKey('client_versions')
        ->toHaveKey('api_versions')
        ->toHaveKey('compatibility')
        ->and($matrix['compatibility']['2.0.0']['2.0.0'])->toBe('full');
});

// ---------------------------------------------------------------------------
// GraphQLQueryOptimizerService
// ---------------------------------------------------------------------------

test('GraphQLQueryOptimizerService can be instantiated', function () {
    $service = new GraphQLQueryOptimizerService();
    expect($service)->toBeInstanceOf(GraphQLQueryOptimizerService::class);
});

test('optimizeQuery returns query_id and optimized status', function () {
    $service = new GraphQLQueryOptimizerService();

    $query = [
        'name' => 'users',
        'selections' => [
            ['name' => 'id', 'type' => 'scalar'],
            ['name' => 'email', 'type' => 'scalar'],
        ],
    ];

    $result = $service->optimizeQuery($query);

    expect($result)->toHaveKey('query_id')
        ->toHaveKey('status')
        ->toHaveKey('depth')
        ->toHaveKey('complexity')
        ->and($result['status'])->toBe('optimized');
});

test('validateComplexity returns valid for simple query', function () {
    $service = new GraphQLQueryOptimizerService();

    $query = [
        'name' => 'users',
        'selections' => [['name' => 'id', 'type' => 'scalar']],
    ];
    $optimized = $service->optimizeQuery($query);

    $validation = $service->validateComplexity($optimized['query_id']);

    expect($validation['valid'])->toBeTrue()
        ->and($validation['errors'])->toBeEmpty();
});

test('validateComplexity returns error for unknown query_id', function () {
    $service = new GraphQLQueryOptimizerService();

    $result = $service->validateComplexity('unknown_query_id');

    expect($result)->toHaveKey('error');
});

test('createDataLoader returns loader_id and created status', function () {
    $service = new GraphQLQueryOptimizerService();

    $result = $service->createDataLoader('UserLoader', fn ($ids) => $ids);

    expect($result)->toHaveKey('loader_id')
        ->toHaveKey('status')
        ->and($result['status'])->toBe('created');
});

test('queueForBatch adds key to batch queue', function () {
    $service = new GraphQLQueryOptimizerService();

    $loader = $service->createDataLoader('PostLoader', fn ($ids) => $ids);
    $queued = $service->queueForBatch($loader['loader_id'], 'user:42');

    expect($queued['queue_size'])->toBeGreaterThanOrEqual(1)
        ->and($queued['key'])->toBe('user:42');
});

test('processBatch returns processed count', function () {
    $service = new GraphQLQueryOptimizerService();

    $loader = $service->createDataLoader('CommentLoader', fn ($ids) => $ids);
    $service->queueForBatch($loader['loader_id'], 'comment:1');
    $service->queueForBatch($loader['loader_id'], 'comment:2');

    $result = $service->processBatch($loader['loader_id']);

    expect($result['processed'])->toBeGreaterThanOrEqual(0);
});

test('getOptimizationReport returns improvement metrics', function () {
    $service = new GraphQLQueryOptimizerService();

    $query = [
        'name' => 'orders',
        'selections' => [
            ['name' => 'id', 'type' => 'scalar'],
            [
                'name' => 'items',
                'type' => 'array',
                'selections' => [
                    ['name' => 'product_id', 'type' => 'scalar'],
                ],
            ],
        ],
    ];
    $optimized = $service->optimizeQuery($query);

    $report = $service->getOptimizationReport($optimized['query_id']);

    expect($report)->toHaveKey('depth')
        ->toHaveKey('complexity')
        ->toHaveKey('fields_selected')
        ->toHaveKey('improvement_ratio');
});

// ---------------------------------------------------------------------------
// GraphQLSchemaBuilderService
// ---------------------------------------------------------------------------

test('GraphQLSchemaBuilderService can be instantiated', function () {
    $service = new GraphQLSchemaBuilderService();
    expect($service)->toBeInstanceOf(GraphQLSchemaBuilderService::class);
});

test('generateSchema returns schema_id and generated status', function () {
    $service = new GraphQLSchemaBuilderService();

    $result = $service->generateSchema('User', [
        'id'    => ['type' => 'ID'],
        'name'  => ['type' => 'String'],
        'email' => ['type' => 'String'],
    ]);

    expect($result)->toHaveKey('schema_id')
        ->toHaveKey('model')
        ->toHaveKey('status')
        ->and($result['model'])->toBe('User')
        ->and($result['status'])->toBe('generated');
});

test('getSchema retrieves stored schema from cache', function () {
    $service = new GraphQLSchemaBuilderService();

    $gen = $service->generateSchema('Product', [
        'id'    => ['type' => 'ID'],
        'title' => ['type' => 'String'],
    ]);

    $schema = $service->getSchema($gen['schema_id']);

    expect($schema)->toBeArray()
        ->toHaveKey('fields')
        ->toHaveKey('queries')
        ->toHaveKey('mutations');
});

test('validateSchema returns valid for schema with fields', function () {
    $service = new GraphQLSchemaBuilderService();

    $gen = $service->generateSchema('Order', [
        'id'         => ['type' => 'ID'],
        'total'      => ['type' => 'Float'],
        'created_at' => ['type' => 'DateTime'],
        'updated_at' => ['type' => 'DateTime'],
    ]);

    $validation = $service->validateSchema($gen['schema_id']);

    expect($validation['valid'])->toBeTrue()
        ->and($validation['errors'])->toBeEmpty();
});

test('validateSchema returns error for unknown schema_id', function () {
    $service = new GraphQLSchemaBuilderService();

    $result = $service->validateSchema('unknown_schema_id');

    expect($result)->toHaveKey('error');
});

test('generateInputType returns input type with fields', function () {
    $service = new GraphQLSchemaBuilderService();

    $result = $service->generateInputType('Customer', [
        'name'  => ['type' => 'String'],
        'email' => ['type' => 'String'],
    ]);

    expect($result['name'])->toBe('CustomerInput')
        ->and($result['fields'])->toBeArray()
        ->and(isset($result['fields']['name']))->toBeTrue();
});

test('generateConnectionType returns edge and connection types', function () {
    $service = new GraphQLSchemaBuilderService();

    $result = $service->generateConnectionType('Invoice');

    expect($result)->toHaveKey('connection')
        ->toHaveKey('edge')
        ->and($result['connection']['name'])->toBe('InvoiceConnection')
        ->and($result['edge']['name'])->toBe('InvoiceEdge');
});

test('mergeSchemas combines fields from multiple schemas', function () {
    $service = new GraphQLSchemaBuilderService();

    $s1 = $service->generateSchema('PartA', ['id' => ['type' => 'ID']]);
    $s2 = $service->generateSchema('PartB', ['name' => ['type' => 'String']]);

    $merged = $service->mergeSchemas([$s1['schema_id'], $s2['schema_id']], 'Combined');

    expect($merged['merged_count'])->toBe(2)
        ->and($merged['status'])->toBe('merged');
});

// ---------------------------------------------------------------------------
// GraphQLSubscriptionManagerService
// ---------------------------------------------------------------------------

test('GraphQLSubscriptionManagerService can be instantiated', function () {
    $service = new GraphQLSubscriptionManagerService();
    expect($service)->toBeInstanceOf(GraphQLSubscriptionManagerService::class);
});

test('getSubscribersCount returns zero for empty topic', function () {
    $service = new GraphQLSubscriptionManagerService();

    expect($service->getSubscribersCount('empty.topic'))->toBe(0);
});

test('cancelSubscription returns error for non-existent subscription', function () {
    $service = new GraphQLSubscriptionManagerService();

    $result = $service->cancelSubscription(1, 'non_existent_sub');

    expect($result)->toHaveKey('error');
});

test('getSubscription returns null for unknown subscription', function () {
    $service = new GraphQLSubscriptionManagerService();

    expect($service->getSubscription('unknown_sub'))->toBeNull();
});

test('getRecentEvents returns empty array for topic with no events', function () {
    $service = new GraphQLSubscriptionManagerService();

    $events = $service->getRecentEvents('empty.events.topic');

    expect($events)->toBeArray()
        ->and($events)->toBeEmpty();
});

test('getPendingMessages returns empty array for unknown subscription', function () {
    $service = new GraphQLSubscriptionManagerService();

    $messages = $service->getPendingMessages('unknown_subscription_id');

    expect($messages)->toBeArray()
        ->and($messages)->toBeEmpty();
});
