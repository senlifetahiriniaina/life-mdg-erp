<?php

declare(strict_types=1);

/**
 * Chantier 32.9 — 14-layer deep audit of Modules\Settings.
 *
 * Locks in every real bug found and fixed by actually executing the code
 * (tinker/real HTTP requests), not by reading it. See the CLAUDE.md
 * changelog entry for the full narrative; the headline findings:
 *
 *  - Layer 9 (fake/dead): the ENTIRE Modules\Settings CRUD API had zero
 *    real consumer anywhere — neither the one root-level page ("Settings")
 *    that is supposed to represent it, nor any other module's backend
 *    code. Classified "to activate", not "delete" (real, tested, RBAC-
 *    correct infrastructure) — the root Settings/Index.vue Notifications
 *    tab is now wired to it for real via showModule()/bulk().
 *  - Layer 9 (fake/dead), second finding: `SettingGroup`/`setting_groups`
 *    confirmed zero consumers anywhere AND independently broken as
 *    designed (scopeForModule() filters a `module` column the table has
 *    never had) — classified "confirmed dead, delete".
 *  - Layer 10 (relational): the real unique index was (tenant_id, key),
 *    never including `module` — the same key name reused across two
 *    modules for one tenant was a guaranteed UniqueConstraintViolation.
 *    Fixed to (tenant_id, module, key).
 *  - Layer 4 (real bug, execution-only): SettingsService::getModule()'s
 *    merge loop silently let a global default overwrite a real
 *    tenant-specific override (backwards from both its own code comment
 *    and from Setting::get()'s correct behavior on identical data).
 *  - Layer 4/6: set()/setTyped() never invalidated the module-level
 *    getModule() cache, and getModule() never applied SettingPolicy's own
 *    documented per-record `is_public`/settings.view visibility rule —
 *    both fixed.
 *  - Layer 6 (security): SettingsController::index() (`viewAll`, admin-
 *    gated) had zero tenant filter — any company's `admin` could list
 *    every other company's settings.
 *  - Layer 8 (business validation): update() accepted `value_type=boolean`
 *    with an arbitrary string `value`, silently PHP-truthy-cast instead of
 *    rejected (e.g. the string "false" stored as boolean true).
 *  - Layer 13 (AI): confirmed the 3 real registered Settings actions
 *    (`configure_settings`/`manage_integrations`/`notification_preferences`)
 *    return real, non-empty fallback guidance via the real generic
 *    /api/v1/ai/assist endpoint the frontend composable actually calls.
 */

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Settings\Models\Setting;
use Modules\Settings\Services\SettingsService;

uses(RefreshDatabase::class);

function chantier32SettingsUser(?Company $company, string $role = 'employee'): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role);

    return $user;
}

// ─── Layer 6 (security) — re-confirmed: update()/bulk() still gated ───────

test('update() is still gated: a role with zero settings.* permission is denied (re-confirmation)', function () {
    $company = Company::factory()->create();
    $user = chantier32SettingsUser($company, 'sales-rep');

    test()->actingAs($user, 'sanctum')
        ->putJson('/api/v1/settings/general/theme', ['value' => 'dark'])
        ->assertForbidden();
});

test('bulk() is still gated: a role with zero settings.* permission is denied (re-confirmation)', function () {
    $company = Company::factory()->create();
    $user = chantier32SettingsUser($company, 'sales-rep');

    test()->actingAs($user, 'sanctum')
        ->postJson('/api/v1/settings/general/bulk', ['settings' => ['theme' => 'dark']])
        ->assertForbidden();
});

test('cross-tenant re-confirmation: company A cannot read a setting written by company B', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = chantier32SettingsUser($companyA, 'employee');
    $userB = chantier32SettingsUser($companyB, 'employee');

    test()->actingAs($userB, 'sanctum')
        ->putJson('/api/v1/settings/billing/secret_plan', ['value' => 'B-only-plan'])
        ->assertOk();

    $readA = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/settings/billing')->assertOk();
    expect($readA->json('settings.secret_plan'))->not->toBe('B-only-plan');
});

// ─── Layer 6 (security) — NEW: index() cross-tenant leak, found + fixed ───

test('index() no longer leaks every tenant\'s settings to a same-role admin of a different company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $adminA = chantier32SettingsUser($companyA, 'admin');
    $adminB = chantier32SettingsUser($companyB, 'admin');

    test()->actingAs($adminA, 'sanctum')
        ->putJson('/api/v1/settings/billing/plan', ['value' => 'A-only-secret-plan'])
        ->assertOk();

    $response = test()->actingAs($adminB, 'sanctum')->getJson('/api/v1/settings/');
    $response->assertOk();

    $values = collect($response->json('data'))->pluck('value');
    expect($values->contains('A-only-secret-plan'))->toBeFalse();
});

test('index() still returns 403 for a role without viewAll (sales-rep, re-confirmation)', function () {
    $company = Company::factory()->create();
    $user = chantier32SettingsUser($company, 'sales-rep');

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/settings/')->assertForbidden();
});

test('index() lets a real super-admin see settings across every tenant', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $adminA = chantier32SettingsUser($companyA, 'admin');
    $superAdmin = chantier32SettingsUser($companyB, 'super-admin');

    test()->actingAs($adminA, 'sanctum')
        ->putJson('/api/v1/settings/billing/plan', ['value' => 'A-plan-visible-to-superadmin'])
        ->assertOk();

    $response = test()->actingAs($superAdmin, 'sanctum')->getJson('/api/v1/settings/');
    $response->assertOk();

    $values = collect($response->json('data'))->pluck('value');
    expect($values->contains('A-plan-visible-to-superadmin'))->toBeTrue();
});

// ─── Layer 10 (relational) — the real unique-index bug ────────────────────

test('the same key name can now be used across two different modules for one tenant', function () {
    $company = Company::factory()->create();
    $user = chantier32SettingsUser($company, 'admin');

    test()->actingAs($user, 'sanctum')
        ->putJson('/api/v1/settings/crm/theme', ['value' => 'dark'])
        ->assertOk();

    // Before the fix, this second write threw a real
    // UniqueConstraintViolationException — the unique index was
    // (tenant_id, key), never including `module`.
    test()->actingAs($user, 'sanctum')
        ->putJson('/api/v1/settings/accounting/theme', ['value' => 'light'])
        ->assertOk();

    $crm = test()->actingAs($user, 'sanctum')->getJson('/api/v1/settings/crm')->json('settings.theme');
    $acc = test()->actingAs($user, 'sanctum')->getJson('/api/v1/settings/accounting')->json('settings.theme');

    expect($crm)->toBe('dark')->and($acc)->toBe('light');
});

// ─── Layer 4 (real bug, execution-only) — getModule() merge-order bug ─────

test('getModule() now correctly lets a tenant-specific override win over a global default', function () {
    $company = Company::factory()->create();

    // Insert directly, bypassing Setting::creating()'s auth-derived
    // tenant_id override, so the "global" row genuinely stays tenant_id
    // = null regardless of who is authenticated when this test runs.
    DB::table('settings')->insert([
        'tenant_id' => null, 'module' => 'ordermod', 'key' => 'theme',
        'value' => 'global-theme', 'value_type' => 'string', 'is_public' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('settings')->insert([
        'tenant_id' => $company->id, 'module' => 'ordermod', 'key' => 'theme',
        'value' => 'tenant-theme', 'value_type' => 'string', 'is_public' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $user = chantier32SettingsUser($company, 'employee');

    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/settings/ordermod');
    $response->assertOk();
    expect($response->json('settings.theme'))->toBe('tenant-theme');
});

// ─── Layer 4/6 — module-level cache staleness after a real write ──────────

test('showModule() no longer returns a stale cached snapshot right after a real update() write', function () {
    Cache::flush();
    $company = Company::factory()->create();
    $user = chantier32SettingsUser($company, 'admin');

    // Prime the module-level cache with the pre-write state.
    test()->actingAs($user, 'sanctum')->getJson('/api/v1/settings/general')->assertOk();

    test()->actingAs($user, 'sanctum')
        ->putJson('/api/v1/settings/general/theme', ['value' => 'freshly-written'])
        ->assertOk();

    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/settings/general');
    expect($response->json('settings.theme'))->toBe('freshly-written');
});

test('bulk() also busts the module-level cache for a subsequent showModule() read', function () {
    Cache::flush();
    $company = Company::factory()->create();
    $user = chantier32SettingsUser($company, 'admin');

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/settings/general')->assertOk();

    test()->actingAs($user, 'sanctum')
        ->postJson('/api/v1/settings/general/bulk', ['settings' => ['bulk_key' => 'bulk-value']])
        ->assertOk();

    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/settings/general');
    expect($response->json('settings.bulk_key'))->toBe('bulk-value');
});

// ─── Layer 8 (business validation) — real bug: boolean settings ───────────

test('update() rejects an arbitrary string for a declared boolean value_type', function () {
    $company = Company::factory()->create();
    $user = chantier32SettingsUser($company, 'admin');

    // Before the fix this silently stored PHP-truthy(true) — the exact
    // string "false" is a non-empty string, which PHP treats as truthy.
    test()->actingAs($user, 'sanctum')
        ->putJson('/api/v1/settings/general/dark_mode', ['value' => 'false', 'value_type' => 'boolean'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['value']);
});

test('update() accepts a real boolean value for a declared boolean value_type and stores it correctly', function () {
    $company = Company::factory()->create();
    $user = chantier32SettingsUser($company, 'admin');

    test()->actingAs($user, 'sanctum')
        ->putJson('/api/v1/settings/general/dark_mode', ['value' => false, 'value_type' => 'boolean'])
        ->assertOk()
        ->assertJsonPath('value', false);
});

test('update() rejects a non-integer string for a declared integer value_type', function () {
    $company = Company::factory()->create();
    $user = chantier32SettingsUser($company, 'admin');

    test()->actingAs($user, 'sanctum')
        ->putJson('/api/v1/settings/general/max_items', ['value' => 'not-a-number', 'value_type' => 'integer'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['value']);
});

test('update() still accepts a plain string value when no value_type is declared', function () {
    $company = Company::factory()->create();
    $user = chantier32SettingsUser($company, 'admin');

    test()->actingAs($user, 'sanctum')
        ->putJson('/api/v1/settings/general/app_name', ['value' => 'Life MDG ERP'])
        ->assertOk()
        ->assertJsonPath('value', 'Life MDG ERP');
});

// ─── Layer 6 (security) — getModule() per-record visibility, real gap ─────

test('a caller without settings.view never sees a non-public setting via getModule(), even after a privileged caller primed the cache', function () {
    Cache::flush();
    $company = Company::factory()->create();
    $admin = chantier32SettingsUser($company, 'admin');

    /** @var SettingsService $service */
    $service = app(SettingsService::class);

    test()->actingAs($admin, 'sanctum');
    $service->setTyped('integration', 'api_secret', 'TOP-SECRET-KEY', 'encrypted');
    Setting::withoutGlobalScopes()->where('module', 'integration')->where('key', 'api_secret')
        ->update(['is_public' => false]);

    // Prime the shared per-tenant module cache as the privileged admin.
    $service->getModule('integration');

    // A bare user with no Spatie role/permission at all — the synthetic
    // "no settings.view" case (every real seeded route-gated role in this
    // app happens to carry settings.view today, so this proves the
    // service-layer filter itself, independent of current role config).
    $bare = User::factory()->create(['company_id' => $company->id]);
    test()->actingAs($bare, 'sanctum');
    expect($bare->hasPermissionTo('settings.view'))->toBeFalse();

    $result = $service->getModule('integration');
    expect($result)->not->toHaveKey('api_secret');
});

test('a caller WITH settings.view still sees a non-public setting via getModule()', function () {
    Cache::flush();
    $company = Company::factory()->create();
    $admin = chantier32SettingsUser($company, 'admin');

    test()->actingAs($admin, 'sanctum');
    /** @var SettingsService $service */
    $service = app(SettingsService::class);
    $service->set('integration', 'private_flag', 'hidden-value');
    Setting::withoutGlobalScopes()->where('module', 'integration')->where('key', 'private_flag')
        ->update(['is_public' => false]);

    $employee = chantier32SettingsUser($company, 'employee');
    test()->actingAs($employee, 'sanctum');
    expect($employee->hasPermissionTo('settings.view'))->toBeTrue();

    $result = $service->getModule('integration');
    expect($result['private_flag'])->toBe('hidden-value');
});

test('a public setting is visible via getModule() regardless of settings.view', function () {
    Cache::flush();
    $company = Company::factory()->create();
    $admin = chantier32SettingsUser($company, 'admin');

    test()->actingAs($admin, 'sanctum');
    /** @var SettingsService $service */
    $service = app(SettingsService::class);
    $service->set('integration', 'public_flag', 'visible-value');
    Setting::withoutGlobalScopes()->where('module', 'integration')->where('key', 'public_flag')
        ->update(['is_public' => true]);

    $bare = User::factory()->create(['company_id' => $company->id]);
    test()->actingAs($bare, 'sanctum');

    $result = $service->getModule('integration');
    expect($result['public_flag'])->toBe('visible-value');
});

// ─── Layer 9 (fake/dead) — SettingGroup confirmed fully removed ───────────

test('SettingGroup and its table no longer exist', function () {
    expect(class_exists(\Modules\Settings\Models\SettingGroup::class))->toBeFalse();
    expect(\Illuminate\Support\Facades\Schema::hasTable('setting_groups'))->toBeFalse();
    expect(\Illuminate\Support\Facades\Schema::hasColumn('settings', 'group_id'))->toBeFalse();
    expect(\Illuminate\Support\Facades\Schema::hasColumn('settings', 'type'))->toBeFalse();
    expect(\Illuminate\Support\Facades\Schema::hasColumn('settings', 'label'))->toBeFalse();
    expect(\Illuminate\Support\Facades\Schema::hasColumn('settings', 'is_system'))->toBeFalse();
});

test('SettingFactory produces a valid, schema-correct row (regression for the scaffold-boilerplate bug)', function () {
    $setting = Setting::factory()->create();

    expect($setting->key)->toBeString()->not->toBeEmpty()
        ->and($setting->module)->toBeString()->not->toBeEmpty()
        ->and($setting->value_type)->toBe('string')
        ->and($setting->is_public)->toBeBool();
});

// ─── Layer 13 (AI) — real fallback guidance for the real registered actions

test('the 3 real registered Settings AI-assist actions return real non-empty fallback guidance via the generic endpoint', function () {
    $company = Company::factory()->create();
    $user = chantier32SettingsUser($company, 'admin');

    foreach (['configure_settings', 'manage_integrations', 'notification_preferences'] as $action) {
        $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/ai/assist', [
            'module' => 'Settings',
            'action' => $action,
            'locale' => 'fr',
        ]);

        $response->assertOk();
        expect($response->json('what_to_do'))->not->toBeEmpty();
    }
});

// ─── Layer 3/12 (Vue / API contract) — the real page now actually calls this API

test('the real Settings web page renders and its Notifications tab data round-trips through the real API', function () {
    $company = Company::factory()->create();
    $user = chantier32SettingsUser($company, 'employee');

    // component(..., false) skips inertia-laravel's own page-exists finder
    // — same established workaround used throughout this session's tests
    // for a page that lives outside a module-namespaced resolve() path.
    test()->actingAs($user, 'sanctum')
        ->get('/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Settings/Index', false));

    // The page's Notifications tab now really persists through
    // POST /api/v1/settings/notifications/bulk — confirm that endpoint
    // round-trips exactly the shape the Vue page sends.
    test()->actingAs($user, 'sanctum')
        ->postJson('/api/v1/settings/notifications/bulk', [
            'settings' => ['email' => false, 'push' => true, 'marketing' => true, 'digest' => false],
        ])
        ->assertOk();

    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/settings/notifications');
    $response->assertOk();
    expect($response->json('settings.email'))->toBeFalse()
        ->and($response->json('settings.push'))->toBeTrue()
        ->and($response->json('settings.marketing'))->toBeTrue()
        ->and($response->json('settings.digest'))->toBeFalse();
});
