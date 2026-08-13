<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\API\Services\APIVersioningService;
use Modules\API\Services\GraphQLQueryOptimizerService;
use Modules\API\Services\GraphQLSchemaBuilderService;
use Modules\API\Services\GraphQLSubscriptionManagerService;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Module Structure Tests
// ─────────────────────────────────────────────────────────────────────────────

test('API module.json exists and has correct name', function () {
    $moduleJson = json_decode(
        file_get_contents(module_path('API', 'module.json')),
        true
    );
    expect($moduleJson)->toBeArray()
        ->and($moduleJson['name'])->toBe('API');
});

test('APIVersioningService can be resolved from container', function () {
    $service = app(APIVersioningService::class);
    expect($service)->toBeInstanceOf(APIVersioningService::class);
});

test('GraphQLSchemaBuilderService can be resolved from container', function () {
    $service = app(GraphQLSchemaBuilderService::class);
    expect($service)->toBeInstanceOf(GraphQLSchemaBuilderService::class);
});

test('GraphQLQueryOptimizerService can be resolved from container', function () {
    $service = app(GraphQLQueryOptimizerService::class);
    expect($service)->toBeInstanceOf(GraphQLQueryOptimizerService::class);
});

test('GraphQLSubscriptionManagerService can be resolved from container', function () {
    $service = app(GraphQLSubscriptionManagerService::class);
    expect($service)->toBeInstanceOf(GraphQLSubscriptionManagerService::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// APIVersioningService — version info
// ─────────────────────────────────────────────────────────────────────────────

test('getVersionInfo returns v2.0.0 as the latest version by default', function () {
    $service = new APIVersioningService();
    $info = $service->getVersionInfo();

    expect($info['version'])->toBe('v2.0.0')
        ->and($info['status'])->toBe('latest');
});

test('getVersionInfo returns deprecated status for v1.0.0', function () {
    $service = new APIVersioningService();
    $info = $service->getVersionInfo('v1.0.0');

    expect($info['status'])->toBe('deprecated')
        ->and($info)->toHaveKey('sunset_date');
});

test('getVersionInfo returns supported status for v1.5.0', function () {
    $service = new APIVersioningService();
    $info = $service->getVersionInfo('v1.5.0');

    expect($info['status'])->toBe('supported')
        ->and($info)->toHaveKey('support_until');
});

test('getVersionInfo returns error for unknown version', function () {
    $service = new APIVersioningService();
    $info = $service->getVersionInfo('v99.0.0');

    expect($info)->toHaveKey('error');
});

test('v2.0.0 version info includes GraphQL in features', function () {
    $service = new APIVersioningService();
    $info = $service->getVersionInfo('v2.0.0');

    expect($info['features'])->toContain('GraphQL API');
});

// ─────────────────────────────────────────────────────────────────────────────
// APIVersioningService — breaking changes
// ─────────────────────────────────────────────────────────────────────────────

test('getBreakingChanges returns deprecations array for v1.0.0 to v1.5.0', function () {
    $service = new APIVersioningService();
    $changes = $service->getBreakingChanges('v1.0.0', 'v1.5.0');

    expect($changes)->toHaveKey('deprecations')
        ->toHaveKey('removals')
        ->toHaveKey('changes');
});

test('getBreakingChanges returns no_breaking_changes for unknown pair', function () {
    $service = new APIVersioningService();
    $changes = $service->getBreakingChanges('v0.1.0', 'v0.2.0');

    expect($changes)->toHaveKey('no_breaking_changes')
        ->and($changes['no_breaking_changes'])->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// APIVersioningService — migration guide
// ─────────────────────────────────────────────────────────────────────────────

test('getMigrationGuide returns title and steps for v1.5.0 to v2.0.0', function () {
    $service = new APIVersioningService();
    $guide = $service->getMigrationGuide('v1.5.0', 'v2.0.0');

    expect($guide)->toHaveKey('title')
        ->toHaveKey('steps')
        ->and($guide['steps'])->not->toBeEmpty();
});

test('getMigrationGuide returns error for unsupported pair', function () {
    $service = new APIVersioningService();
    $guide = $service->getMigrationGuide('v0.1.0', 'v0.2.0');

    expect($guide)->toHaveKey('error');
});

// ─────────────────────────────────────────────────────────────────────────────
// APIVersioningService — deprecated endpoints
// ─────────────────────────────────────────────────────────────────────────────

test('getDeprecatedEndpoints returns array for v1.5.0', function () {
    $service = new APIVersioningService();
    $deprecated = $service->getDeprecatedEndpoints('v1.5.0');

    expect($deprecated)->toBeArray()
        ->not->toBeEmpty();
    expect($deprecated[0])->toHaveKey('endpoint')
        ->toHaveKey('sunset_date');
});

test('getDeprecatedEndpoints returns empty array for v1.0.0', function () {
    $service = new APIVersioningService();
    $deprecated = $service->getDeprecatedEndpoints('v1.0.0');

    expect($deprecated)->toBeArray()
        ->toBeEmpty();
});

// ─────────────────────────────────────────────────────────────────────────────
// APIVersioningService — client compatibility
// ─────────────────────────────────────────────────────────────────────────────

test('checkClientCompatibility returns compatible true for same major version', function () {
    $service = new APIVersioningService();
    $result = $service->checkClientCompatibility('2.0.0', '2.0.0');

    expect($result['compatible'])->toBeTrue()
        ->and($result['warnings'])->toBeEmpty();
});

test('checkClientCompatibility returns compatible false for mismatched major version', function () {
    $service = new APIVersioningService();
    $result = $service->checkClientCompatibility('1.5.0', '2.0.0');

    expect($result['compatible'])->toBeFalse()
        ->and($result['warnings'])->not->toBeEmpty();
});

test('getCompatibilityMatrix returns full matrix with client and API versions', function () {
    $service = new APIVersioningService();
    $matrix = $service->getCompatibilityMatrix();

    expect($matrix)->toHaveKey('client_versions')
        ->toHaveKey('api_versions')
        ->toHaveKey('compatibility');
    expect($matrix['compatibility']['2.0.0']['2.0.0'])->toBe('full');
});

// ─────────────────────────────────────────────────────────────────────────────
// APIVersioningService — version redirect
// ─────────────────────────────────────────────────────────────────────────────

test('createVersionRedirect returns redirect ID and status', function () {
    $service = new APIVersioningService();
    $result = $service->createVersionRedirect('v1.5.0', 'v2.0.0', 'GET /api/users');

    expect($result)->toHaveKey('redirect_id')
        ->toHaveKey('status')
        ->toHaveKey('from')
        ->toHaveKey('to')
        ->and($result['status'])->toBe('created');
});

test('createVersionRedirect maps GET /api/users to GraphQL query', function () {
    $service = new APIVersioningService();
    $result = $service->createVersionRedirect('v1.5.0', 'v2.0.0', 'GET /api/users');

    expect($result['to'])->toContain('graphql');
});
