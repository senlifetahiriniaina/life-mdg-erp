<?php

uses(Tests\TestCase::class)->in(
    'Feature',
    'Unit',
    '../Modules/Core/tests/Feature',
    '../Modules/Core/tests/Unit',
    '../Modules/AI/tests/Feature',
    '../Modules/AI/tests/Unit',
    '../Modules/CRM/tests/Feature',
    '../Modules/CRM/tests/Unit',
    '../Modules/HR/tests/Feature',
    '../Modules/HR/tests/Unit',
    '../Modules/Accounting/tests/Feature',
    '../Modules/Accounting/tests/Unit',
    '../Modules/Inventory/tests/Feature',
    '../Modules/Inventory/tests/Unit',
    '../Modules/Projects/tests/Feature',
    '../Modules/Projects/tests/Unit',
    '../Modules/Manufacturing/tests/Feature',
    '../Modules/Manufacturing/tests/Unit',
    '../Modules/POS/tests/Feature',
    '../Modules/POS/tests/Unit',
    '../Modules/Ecommerce/tests/Feature',
    '../Modules/Ecommerce/tests/Unit',
    '../Modules/Helpdesk/tests/Feature',
    '../Modules/Helpdesk/tests/Unit',
    '../Modules/Documents/tests/Feature',
    '../Modules/Documents/tests/Unit',
    '../Modules/Purchasing/tests/Feature',
    '../Modules/Purchasing/tests/Unit',
    '../Modules/BI/tests/Feature',
    '../Modules/BI/tests/Unit',
    '../Modules/Email/tests/Feature',
    '../Modules/Email/tests/Unit',
    '../Modules/WhatsApp/tests/Feature',
    '../Modules/WhatsApp/tests/Unit',
    '../Modules/AuditLog/tests/Feature',
    '../Modules/Planning/tests/Feature',
    '../Modules/Planning/tests/Unit',
    '../Modules/Quality/tests/Feature',
    '../Modules/Quality/tests/Unit',
    '../Modules/Discussion/tests/Feature',
    '../Modules/Discussion/tests/Unit',
);

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
        test()->seed(\Database\Seeders\EnhancedRolesAndPermissionsSeeder::class);
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

    // Enable all modules for this test user (using user ID as tenant ID in single-tenant tests)
    $modules = [
        'CRM', 'HR', 'Inventory', 'Accounting', 'Manufacturing',
        'POS', 'Ecommerce', 'BI', 'Email', 'Documents',
        'Helpdesk', 'Projects', 'WhatsApp',
    ];

    foreach ($modules as $module) {
        \Illuminate\Support\Facades\DB::table('tenant_modules')->updateOrInsert(
            ['tenant_id' => (string) $user->id, 'module' => $module, 'department' => null],
            ['enabled' => true, 'settings' => '{}', 'updated_at' => now(), 'created_at' => now()]
        );
    }

    return $user;
}
