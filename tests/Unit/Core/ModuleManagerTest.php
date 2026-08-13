<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Services\ModuleManager;

uses(RefreshDatabase::class);

function makeManager(): ModuleManager
{
    return app(ModuleManager::class);
}

// Helper: check if a module is enabled for a specific user/tenant
function tenantHasModule(ModuleManager $m, User $user, string $module, ?string $dept = null): bool
{
    return $m->enabledModules((string) $user->id, $dept)->contains($module);
}

// ── Core always enabled ────────────────────────────────────────────────────────

test('Core module is always enabled', function () {
    expect(makeManager()->isEnabled('Core'))->toBeTrue();
});

test('Core module cannot be disabled', function () {
    $user = User::factory()->create();

    expect(fn () => makeManager()->disable((string) $user->id, 'Core'))
        ->toThrow(\InvalidArgumentException::class);
});

// ── Enable / Disable ──────────────────────────────────────────────────────────

test('enabledModules does not include non-Core module before it is enabled', function () {
    $user = User::factory()->create();

    expect(tenantHasModule(makeManager(), $user, 'CRM'))->toBeFalse();
});

test('isEnabled returns true for any module when tenant has no configuration yet', function () {
    $user = User::factory()->create();
    auth()->login($user);

    expect(makeManager()->isEnabled('CRM'))->toBeTrue();
});

test('isEnabled returns false for disabled module once tenant has configuration', function () {
    $manager = makeManager();
    $user    = User::factory()->create();
    auth()->login($user);

    // Enabling any module creates a row — now the explicit state applies.
    $manager->enable((string) $user->id, 'HR');

    expect($manager->isEnabled('CRM'))->toBeFalse();
    expect($manager->isEnabled('HR'))->toBeTrue();
});

test('enable makes module appear as enabled', function () {
    $manager = makeManager();
    $user    = User::factory()->create();

    $manager->enable((string) $user->id, 'CRM');

    expect(tenantHasModule($manager, $user, 'CRM'))->toBeTrue();
});

test('disable removes module from enabled list', function () {
    $manager = makeManager();
    $user    = User::factory()->create();

    $manager->enable((string) $user->id, 'Inventory');
    $manager->disable((string) $user->id, 'Inventory');

    expect(tenantHasModule($manager, $user, 'Inventory'))->toBeFalse();
});

test('enable is idempotent — calling twice does not duplicate', function () {
    $manager = makeManager();
    $user    = User::factory()->create();

    $manager->enable((string) $user->id, 'HR');
    $manager->enable((string) $user->id, 'HR');

    $count = $manager->enabledModules((string) $user->id)->filter(fn ($m) => $m === 'HR')->count();
    expect($count)->toBe(1);
});

// ── Department scoping ────────────────────────────────────────────────────────

test('department-scoped module is visible within that department', function () {
    $manager = makeManager();
    $user    = User::factory()->create();

    $manager->enable((string) $user->id, 'HR', 'engineering');

    expect(tenantHasModule($manager, $user, 'HR', 'engineering'))->toBeTrue();
});

test('department-scoped module is not visible in a different department', function () {
    $manager = makeManager();
    $user    = User::factory()->create();

    $manager->enable((string) $user->id, 'HR', 'engineering');

    expect(tenantHasModule($manager, $user, 'HR', 'sales'))->toBeFalse();
});

// ── Cache invalidation ────────────────────────────────────────────────────────

test('cache is invalidated after enable', function () {
    $manager = makeManager();
    $user    = User::factory()->create();

    // Prime cache with empty list
    $manager->enabledModules((string) $user->id);

    $manager->enable((string) $user->id, 'CRM');

    expect(tenantHasModule($manager, $user, 'CRM'))->toBeTrue();
});

test('cache is invalidated after disable', function () {
    $manager = makeManager();
    $user    = User::factory()->create();

    $manager->enable((string) $user->id, 'Accounting');
    $manager->disable((string) $user->id, 'Accounting');

    expect(tenantHasModule($manager, $user, 'Accounting'))->toBeFalse();
});

// ── enabledModules list ────────────────────────────────────────────────────────

test('enabledModules always includes Core', function () {
    $user = User::factory()->create();

    expect($manager = makeManager())
        ->and($manager->enabledModules((string) $user->id)->toArray())
        ->toContain('Core');
});

test('enabledModules returns all enabled modules for tenant', function () {
    $manager = makeManager();
    $user    = User::factory()->create();

    $manager->enable((string) $user->id, 'CRM');
    $manager->enable((string) $user->id, 'HR');

    $modules = $manager->enabledModules((string) $user->id);

    expect($modules)->toContain('CRM');
    expect($modules)->toContain('HR');
    expect($modules)->toContain('Core');
});

test('two tenants have independent module lists', function () {
    $manager = makeManager();
    $userA   = User::factory()->create();
    $userB   = User::factory()->create();

    $manager->enable((string) $userA->id, 'CRM');
    $manager->enable((string) $userB->id, 'Accounting');

    expect(tenantHasModule($manager, $userA, 'CRM'))->toBeTrue();
    expect(tenantHasModule($manager, $userA, 'Accounting'))->toBeFalse();
    expect(tenantHasModule($manager, $userB, 'Accounting'))->toBeTrue();
    expect(tenantHasModule($manager, $userB, 'CRM'))->toBeFalse();
});
