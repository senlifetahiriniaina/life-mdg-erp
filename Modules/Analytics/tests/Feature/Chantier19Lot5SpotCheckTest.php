<?php

use App\Models\Company;
use App\Models\User;
use Modules\Analytics\Models\Recommendation;
use Modules\Analytics\Models\RecommendationModel;

/**
 * Chantier 19 (Lot 5): re-verified (not just re-read) 2 claims documented
 * in Chantier 8.5ars — both confirmed still correct, no code change needed.
 */
test('recommendations apiResource is still restricted to index/store/show — PUT/DELETE rejected', function () {
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $user->assignRole('admin');

    $model = RecommendationModel::factory()->create();
    $rec = Recommendation::factory()->create([
        'company_id' => $company->id,
        'recommendation_model_id' => $model->id,
    ]);

    // Route::apiResource(...)->only(['index','store','show']) means no
    // PUT/DELETE route is registered for this URI at all — Laravel's
    // router still matches the URI pattern via the registered GET (show)
    // route and correctly rejects the wrong verb with 405, rather than a
    // plain 404 (which would imply no route matches the URI at all).
    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/analytics/recommendations/{$rec->id}", ['status' => 'acted'])
        ->assertStatus(405);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/analytics/recommendations/{$rec->id}")
        ->assertStatus(405);
});
