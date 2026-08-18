<?php

declare(strict_types=1);

use App\Models\User;

/**
 * Chantier 8.3 (Payroll): 'payroll-officer' — the role literally named for
 * this module, seeded with full payroll.* permissions — was missing from
 * the module's route role: list entirely, locking it out of every payroll
 * endpoint. statistics()/taxesByCountry() had no fine-grained authorize()
 * check at all, unlike every other method here, so accountant/finance-manager
 * (accounting, bi, and strategy permissions only, no payroll permissions)
 * could read payroll aggregates by passing only the outer route role: gate.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function payrollRbacUser(string $role): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('payroll-officer can reach payroll statistics', function () {
    $user = payrollRbacUser('payroll-officer');

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/payroll/statistics')
        ->assertOk();
});

test('payroll-officer can list payslips', function () {
    $user = payrollRbacUser('payroll-officer');

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/payroll/payslips')
        ->assertOk();
});

test('accountant cannot read payroll statistics', function () {
    $user = payrollRbacUser('accountant');

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/payroll/statistics')
        ->assertForbidden();
});

test('finance-manager cannot read payroll tax breakdown', function () {
    $user = payrollRbacUser('finance-manager');

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/payroll/taxes/by-country')
        ->assertForbidden();
});

test('hr-manager can still read payroll statistics and taxes', function () {
    $user = payrollRbacUser('hr-manager');

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/payroll/statistics')->assertOk();
    test()->actingAs($user, 'sanctum')->getJson('/api/v1/payroll/taxes/by-country')->assertOk();
});
