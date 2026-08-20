<?php

use App\Models\User;

/**
 * Chantier 19 (Lot 5): ForecastingController::tenantId() fell back to
 * `$request->user()->id` whenever `company_id` was null — the same "private
 * per-user bucket" bug class already documented and fixed for AI's
 * AiUsageBudgetService and API's RequestLogController/WebhookController
 * (Chantier 19 Lot 3). Not a cross-tenant leak (no shared/guessable
 * fallback), but two real colleagues at the same company with no
 * `company_id` set would each silently get their own private forecast
 * models/alerts/scenarios instead of sharing one company bucket — confirmed
 * empirically before the fix that a second such user saw an empty list
 * where they should have seen their colleague's real forecast model.
 */
function forecastUser(): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create(['company_id' => null]);
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $user->assignRole('admin');
    test()->actingAs($user, 'sanctum');
    return $user;
}

test('two users with no real company_id share the same forecast bucket, not a private per-user one', function () {
    $userA = forecastUser();

    $this->actingAs($userA, 'sanctum')->postJson('/api/v1/forecasting/models', [
        'module'       => 'demand',
        'name'         => 'Model by A',
        'algorithm'    => 'linear_regression',
        'horizon_days' => 30,
    ])->assertCreated();

    $userB = forecastUser();
    $resp = $this->actingAs($userB, 'sanctum')->getJson('/api/v1/forecasting/models');

    $resp->assertOk();
    expect(collect($resp->json('data'))->pluck('name'))->toContain('Model by A');
});
