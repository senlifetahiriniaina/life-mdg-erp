<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\API\Providers\APIServiceProvider;
use Modules\API\Services\APIVersioningService;
use Modules\API\Services\GraphQLSchemaBuilderService;
use Modules\API\Services\GraphQLQueryOptimizerService;
use Modules\API\Services\GraphQLSubscriptionManagerService;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Service Provider & Bindings
// ─────────────────────────────────────────────────────────────────────────────

test('APIServiceProvider registers APIVersioningService as singleton', function () {
    $a = app(APIVersioningService::class);
    $b = app(APIVersioningService::class);

    expect($a)->toBeInstanceOf(APIVersioningService::class)
        ->and($a)->toBe($b);
});

test('APIServiceProvider registers GraphQLSchemaBuilderService as singleton', function () {
    $a = app(GraphQLSchemaBuilderService::class);
    $b = app(GraphQLSchemaBuilderService::class);

    expect($a)->toBeInstanceOf(GraphQLSchemaBuilderService::class)
        ->and($a)->toBe($b);
});

test('APIServiceProvider registers GraphQLQueryOptimizerService as singleton', function () {
    expect(app(GraphQLQueryOptimizerService::class))->toBeInstanceOf(GraphQLQueryOptimizerService::class);
});

test('APIServiceProvider registers GraphQLSubscriptionManagerService as singleton', function () {
    expect(app(GraphQLSubscriptionManagerService::class))->toBeInstanceOf(GraphQLSubscriptionManagerService::class);
});

test('api_versioning alias resolves to APIVersioningService', function () {
    expect(app('api_versioning'))->toBeInstanceOf(APIVersioningService::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// APIVersioningService — version info
// ─────────────────────────────────────────────────────────────────────────────

test('APIVersioningService returns latest version info for v2.0.0', function () {
    $service = app(APIVersioningService::class);
    $info    = $service->getVersionInfo('v2.0.0');

    expect($info['version'])->toBe('v2.0.0')
        ->and($info['status'])->toBe('latest')
        ->and($info['features'])->toContain('GraphQL API');
});

test('APIVersioningService returns deprecated status for v1.0.0', function () {
    $service = app(APIVersioningService::class);
    $info    = $service->getVersionInfo('v1.0.0');

    expect($info['status'])->toBe('deprecated');
});

test('APIVersioningService returns error for unknown version', function () {
    $service = app(APIVersioningService::class);
    $info    = $service->getVersionInfo('v99.0.0');

    expect($info)->toHaveKey('error');
});

test('LATEST_VERSION constant is v2.0.0', function () {
    expect(APIVersioningService::LATEST_VERSION)->toBe('2.0.0');
});

// ─────────────────────────────────────────────────────────────────────────────
// APIVersioningService — breaking changes
// ─────────────────────────────────────────────────────────────────────────────

test('APIVersioningService returns breaking changes from v1.5.0 to v2.0.0', function () {
    $service  = app(APIVersioningService::class);
    $changes  = $service->getBreakingChanges('v1.5.0', 'v2.0.0');

    expect($changes)->toHaveKey('deprecations')
        ->and($changes['deprecations'])->not->toBeEmpty();
});

test('APIVersioningService returns no_breaking_changes for unknown version pair', function () {
    $service = app(APIVersioningService::class);
    $changes = $service->getBreakingChanges('v0.1.0', 'v99.0.0');

    expect($changes)->toHaveKey('no_breaking_changes')
        ->and($changes['no_breaking_changes'])->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// APIVersioningService — client compatibility
// ─────────────────────────────────────────────────────────────────────────────

test('APIVersioningService reports compatible when major versions match', function () {
    $service = app(APIVersioningService::class);
    $result  = $service->checkClientCompatibility('2.0.0', '2.0.0');

    expect($result['compatible'])->toBeTrue()
        ->and($result['warnings'])->toBeEmpty();
});

test('APIVersioningService reports incompatible when major versions differ', function () {
    $service = app(APIVersioningService::class);
    $result  = $service->checkClientCompatibility('1.0.0', '2.0.0');

    expect($result['compatible'])->toBeFalse()
        ->and($result['warnings'])->not->toBeEmpty();
});

test('APIVersioningService compatibility matrix includes all versions', function () {
    $service = app(APIVersioningService::class);
    $matrix  = $service->getCompatibilityMatrix();

    expect($matrix)->toHaveKey('client_versions')
        ->and($matrix['client_versions'])->toContain('2.0.0')
        ->and($matrix['compatibility']['2.0.0']['2.0.0'])->toBe('full');
});

// ─────────────────────────────────────────────────────────────────────────────
// GraphQLSchemaBuilderService
// ─────────────────────────────────────────────────────────────────────────────

test('GraphQLSchemaBuilderService generates schema with correct structure', function () {
    $service = app(GraphQLSchemaBuilderService::class);
    $result  = $service->generateSchema('Product', [
        'id'   => ['type' => 'ID'],
        'name' => ['type' => 'String'],
    ]);

    expect($result)->toHaveKey('schema_id')
        ->and($result['model'])->toBe('Product')
        ->and($result['status'])->toBe('generated');
});

test('GraphQLSchemaBuilderService generates input type from fields', function () {
    $service    = app(GraphQLSchemaBuilderService::class);
    $inputType  = $service->generateInputType('Product', [
        'name'  => ['type' => 'String'],
        'price' => ['type' => 'Float'],
    ]);

    expect($inputType['name'])->toBe('ProductInput')
        ->and($inputType['fields'])->toHaveKey('name');
});

test('GraphQLSchemaBuilderService generates connection type for pagination', function () {
    $service    = app(GraphQLSchemaBuilderService::class);
    $connection = $service->generateConnectionType('Order');

    expect($connection['connection']['name'])->toBe('OrderConnection')
        ->and($connection['edge']['name'])->toBe('OrderEdge')
        ->and($connection['connection']['fields'])->toHaveKey('totalCount');
});

test('GraphQLSchemaBuilderService SCALAR_TYPES includes standard GraphQL scalars', function () {
    expect(GraphQLSchemaBuilderService::SCALAR_TYPES)
        ->toContain('String')
        ->toContain('Int')
        ->toContain('Boolean')
        ->toContain('ID');
});

// ─────────────────────────────────────────────────────────────────────────────
// APIVersioningService — migration guide & redirect
// ─────────────────────────────────────────────────────────────────────────────

test('APIVersioningService returns migration guide for v1.5.0 to v2.0.0', function () {
    $service = app(APIVersioningService::class);
    $guide   = $service->getMigrationGuide('v1.5.0', 'v2.0.0');

    expect($guide)->toHaveKey('steps')
        ->and($guide['steps'])->not->toBeEmpty()
        ->and($guide['steps'][0])->toHaveKey('title');
});

test('APIVersioningService creates version redirect and returns redirect_id', function () {
    $service  = app(APIVersioningService::class);
    $redirect = $service->createVersionRedirect('v1.5.0', 'v2.0.0', 'GET /api/users');

    expect($redirect)->toHaveKey('redirect_id')
        ->and($redirect['status'])->toBe('created')
        ->and($redirect['from'])->toBe('GET /api/users');
});

test('module.json for API module has correct name', function () {
    $jsonPath = base_path('Modules/API/module.json');

    if (!file_exists($jsonPath)) {
        $this->markTestSkipped('module.json not found for API module.');
    }

    $json = json_decode(file_get_contents($jsonPath), true);

    expect($json['name'])->toBe('API');
});
