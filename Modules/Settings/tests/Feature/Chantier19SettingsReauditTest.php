<?php

/**
 * Chantier 19 Lot 3 (Settings re-verification, empirical-execution
 * methodology): re-confirms the Chantier 8.5-light authorize() fixes still
 * hold via real HTTP requests, and closes a real, previously-undocumented
 * cross-tenant IDOR found by actually reading (not just skimming) every
 * tenant-boundary resolution site in this module.
 *
 * Modules\Settings\Models\Setting::boot()/get()/set() and
 * Modules\Settings\Services\SettingsService::currentTenantId() all
 * resolved the tenant boundary as
 * `auth()?->user()?->company_id ?? request()?->header('X-Company-ID')` —
 * the exact client-controlled-header IDOR already fixed for Setup's
 * identical pattern in Chantier 8.5sv. Since PHP's `??` only falls through
 * on a genuinely null left side, this was only reachable for a caller
 * whose own company_id is null (an unprovisioned/newly-registered
 * account) — but that is documented elsewhere in this app (Setup's own
 * fix notes) as the common case for such users, not a hypothetical edge
 * case. On top of that, Setting::boot()'s global scope had a second,
 * independent bug: when no tenant resolved at all, it added no filter
 * whatsoever, returning every tenant's every setting completely
 * unfiltered.
 */

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Settings\Models\Setting;

uses(RefreshDatabase::class);

function chantier19SettingsUser(?Company $company, string $role = 'employee'): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role);

    return $user;
}

test('a plain sales-rep with zero settings.* permission is denied updating a setting', function () {
    $company = Company::factory()->create();
    $user = chantier19SettingsUser($company, 'sales-rep');

    test()->actingAs($user, 'sanctum')
        ->putJson('/api/v1/settings/general/theme', ['value' => 'dark'])
        ->assertForbidden();
});

test('a plain sales-rep with zero settings.* permission is denied bulk-updating a module', function () {
    $company = Company::factory()->create();
    $user = chantier19SettingsUser($company, 'sales-rep');

    test()->actingAs($user, 'sanctum')
        ->postJson('/api/v1/settings/general/bulk', ['settings' => ['theme' => 'dark']])
        ->assertForbidden();
});

test('a plain sales-rep with zero settings.* permission is denied listing all settings', function () {
    $company = Company::factory()->create();
    $user = chantier19SettingsUser($company, 'sales-rep');

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/settings/')->assertForbidden();
});

test('an employee of company A can write and read its own setting, invisible to company B', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = chantier19SettingsUser($companyA, 'employee');
    $userB = chantier19SettingsUser($companyB, 'employee');

    test()->actingAs($userA, 'sanctum')
        ->putJson('/api/v1/settings/general/theme', ['value' => 'dark'])
        ->assertOk();

    $readA = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/settings/general')->assertOk();
    expect($readA->json('settings.theme'))->toBe('dark');

    $readB = test()->actingAs($userB, 'sanctum')->getJson('/api/v1/settings/general')->assertOk();
    expect($readB->json('settings.theme'))->not->toBe('dark');
});

test('Setting::set()/get() no longer honor a client-controlled X-Company-ID header for a user with no real company', function () {
    $companyVictim = Company::factory()->create();
    $victimAdmin = chantier19SettingsUser($companyVictim, 'admin');

    test()->actingAs($victimAdmin, 'sanctum')
        ->putJson('/api/v1/settings/billing/plan', ['value' => 'enterprise'])
        ->assertOk();

    // Attacker: real employee permissions but no real company of their own
    // (the phantom-column-adjacent "unprovisioned user" scenario this bug
    // class specifically targets), attempting to read/write the victim's
    // company data by spoofing the header — must not work, not just "the
    // controller looks like it uses the right column".
    $attacker = chantier19SettingsUser(null, 'admin');
    $response = test()->withHeaders(['X-Company-ID' => (string) $companyVictim->id])
        ->actingAs($attacker, 'sanctum')
        ->getJson('/api/v1/settings/billing');

    $response->assertOk();
    expect($response->json('settings.plan'))->not->toBe('enterprise');

    // Confirm the victim's real setting is untouched and really is scoped
    // to the victim's own company_id, not global/null.
    $stored = Setting::withoutGlobalScopes()->where('module', 'billing')->where('key', 'plan')->first();
    expect((int) $stored->tenant_id)->toBe($companyVictim->id);
    expect($stored->getCastedValue())->toBe('enterprise');
});

test('an admin whose own company_id is null cannot list every tenant\'s settings unfiltered', function () {
    $companyA = Company::factory()->create();
    $userA = chantier19SettingsUser($companyA, 'admin');
    test()->actingAs($userA, 'sanctum')
        ->putJson('/api/v1/settings/general/theme', ['value' => 'company-a-theme'])
        ->assertOk();

    // Before the fix, Setting::boot()'s global scope added NO filter at
    // all when no tenant resolved, so a direct Setting::query() (as any
    // future non-Settings-module code might do, since this model is real
    // exported infrastructure) would return every tenant's rows.
    $unprovisioned = chantier19SettingsUser(null, 'admin');
    test()->actingAs($unprovisioned, 'sanctum');

    $visible = Setting::query()->where('module', 'general')->where('key', 'theme')->get();
    expect($visible)->toHaveCount(0);
});
