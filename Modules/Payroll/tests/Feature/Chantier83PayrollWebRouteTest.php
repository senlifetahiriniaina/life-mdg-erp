<?php

declare(strict_types=1);

use App\Models\User;

/**
 * Chantier 8.3 (Payroll): Modules/Payroll had no routes/web.php at all — its
 * real, self-fetching Dashboard/Index.vue page had no route to reach it.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('payroll dashboard root page renders', function () {
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create();
    $user->assignRole('hr-manager');

    $response = test()->actingAs($user)->get('/payroll');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Payroll/Dashboard/Index', false));
});
