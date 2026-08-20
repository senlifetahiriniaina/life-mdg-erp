<?php

// Covers all 27 modules in this extraction's actual scope (see CLAUDE.md's
// scope table) — phpunit.xml discovers tests via a Modules/*/tests/{Feature,Unit}
// glob regardless of this list, but Pest only binds Tests\TestCase (and therefore
// boots the Laravel app / registers the container's 'config' alias) for suites
// listed here. A module missing from this list still runs its tests, just with
// no framework bootstrap — every container-touching call in it then resolves
// against whatever the previous bound test's teardown left behind, surfacing as
// "Target class [config] does not exist." This previously listed 10 modules that
// don't exist in this repo (Manufacturing, POS, Ecommerce, Documents, Purchasing,
// Email, WhatsApp, Planning, Quality, Discussion) while omitting 16 real ones.
uses(Tests\TestCase::class)->in(
    'Feature',
    'Unit',
    '../Modules/Core/tests/Feature',
    '../Modules/Core/tests/Unit',
    '../Modules/AI/tests/Feature',
    '../Modules/AI/tests/Unit',
    '../Modules/Security/tests/Feature',
    '../Modules/AuditLog/tests/Feature',
    '../Modules/API/tests/Feature',
    '../Modules/API/tests/Unit',
    '../Modules/Integration/tests/Feature',
    '../Modules/Validation/tests/Feature',
    '../Modules/Shared/tests/Feature',
    '../Modules/Settings/tests/Feature',
    '../Modules/Setup/tests/Feature',
    '../Modules/Workflow/tests/Feature',
    '../Modules/Workflow/tests/Unit',
    '../Modules/Calendar/tests/Feature',
    '../Modules/Accounting/tests/Feature',
    '../Modules/Accounting/tests/Unit',
    '../Modules/CRM/tests/Feature',
    '../Modules/CRM/tests/Unit',
    '../Modules/Sales/tests/Feature',
    '../Modules/Inventory/tests/Feature',
    '../Modules/Inventory/tests/Unit',
    '../Modules/Logistics/tests/Feature',
    '../Modules/Achats/tests/Feature',
    '../Modules/BI/tests/Feature',
    '../Modules/BI/tests/Unit',
    '../Modules/Analytics/tests/Feature',
    '../Modules/Reporting/tests/Feature',
    '../Modules/Strategy/tests/Feature',
    '../Modules/HR/tests/Feature',
    '../Modules/HR/tests/Unit',
    '../Modules/Payroll/tests/Feature',
    '../Modules/Timesheets/tests/Feature',
    '../Modules/Projects/tests/Feature',
    '../Modules/Projects/tests/Unit',
    '../Modules/Helpdesk/tests/Feature',
    '../Modules/Helpdesk/tests/Unit',
    '../Modules/Messaging/tests/Feature',
);

// ─── Custom expectations ──────────────────────────────────────────────────────

/**
 * Assert a numeric value is within $delta of $expected.
 *
 * Used by 8 call sites across Analytics/Strategy/HR (correlation coefficients,
 * MAPE, accrual and vesting maths) but never registered, so every one of them
 * failed with "Call to undefined method ... toBeCloseTo()".
 */
expect()->extend('toBeCloseTo', function (float $expected, float $delta = 0.01) {
    $actual = (float) $this->value;

    expect(abs($actual - $expected))->toBeLessThanOrEqual(
        $delta,
        "Failed asserting that {$actual} is within {$delta} of {$expected}."
    );

    return $this;
});

// ─── Custom helpers ───────────────────────────────────────────────────────────

/**
 * Create and authenticate a test user with the given role.
 * Uses the sanctum guard for API testing.
 */
function actingAsUser(string $role = 'admin'): \App\Models\User
{
    // Seed the real role->permission mappings once per test (RefreshDatabase wipes between
    // tests). Without this, roles are empty and every policy check returns 403.
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        // RolesAndPermissionsSeeder is the seeder actually wired into
        // DatabaseSeeder / used in production — EnhancedRolesAndPermissionsSeeder's
        // own docblock says as much ("not wired into DatabaseSeeder by default").
        // Tests must match prod, not a parallel seeder that was never activated.
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $user = \App\Models\User::factory()->create();
    // Pre-enrol 2FA so users that later gain admin/super-admin roles satisfy the
    // mandatory-2FA middleware (no-op for non-privileged roles).
    $user->forceFill([
        'two_factor_enabled' => true,
        'google2fa_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
    ])->save();
    $user->assignRole($role);
    test()->actingAs($user, 'sanctum');

    // Create an associated employee for HR functionality
    \Modules\HR\Models\Employee::factory()->create(['user_id' => $user->id]);

    // Enable all modules for this test user (using user ID as tenant ID in single-tenant tests).
    // Real Life MDG 27-module scope (see CLAUDE.md's scope table) — previously a stale
    // 13-module list copied from WideHalo-ERP's old 47-module scope, which silently left
    // 20 real modules disabled (403 via the `module:` middleware) for any test relying on
    // this helper: Reporting, Sales, Strategy, Achats, Logistics, Setup, Workflow, Calendar,
    // Analytics, AuditLog, Security, Integration, Validation, Shared, Settings, Payroll,
    // Timesheets, API, AI (Core is separately whitelisted in ModuleManager::CORE_MODULES).
    $modules = [
        'Core', 'AI', 'Security', 'AuditLog', 'API', 'Integration', 'Validation',
        'Shared', 'Settings', 'Setup', 'Workflow', 'Calendar',
        'Accounting', 'CRM', 'Sales',
        'Inventory', 'Logistics', 'Achats',
        'BI', 'Analytics', 'Reporting', 'Strategy',
        'HR', 'Payroll', 'Timesheets', 'Projects',
        'Helpdesk', 'Messaging',
    ];

    foreach ($modules as $module) {
        \Illuminate\Support\Facades\DB::table('tenant_modules')->updateOrInsert(
            ['tenant_id' => (string) $user->id, 'module' => $module, 'department' => null],
            ['enabled' => true, 'settings' => '{}', 'updated_at' => now(), 'created_at' => now()]
        );
    }

    return $user;
}
