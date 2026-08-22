<?php

namespace Modules\Analytics\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Modules\Analytics\Models\ForecastModel;
use Modules\Analytics\Models\ForecastScenario;
use Modules\Analytics\Models\MLModel;
use Modules\Analytics\Models\MLModelVersion;
use Modules\Analytics\Models\Recommendation;
use Modules\Analytics\Models\RecommendationModel;
use Modules\Analytics\Policies\ABTestRunPolicy;
use Modules\Analytics\Policies\MLModelPolicy;
use Modules\Analytics\Policies\PredictionModelPolicy;
use Modules\Analytics\Policies\RecommendationModelPolicy;
use Modules\Analytics\Policies\RecommendationPolicy;
use Modules\Analytics\Services\Forecasting\CashflowForecastService;
use Modules\Analytics\Services\Forecasting\HrForecastService;
use Modules\Analytics\Services\Forecasting\ProductionForecastService;
use Modules\Analytics\Services\ForecastingEngineService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Chantier 32.25 — audit approfondi en 14 couches de Modules\Analytics.
 * Verrouille chaque bug réel trouvé/corrigé par exécution empirique (pas
 * une simple relecture) durant ce chantier.
 */
class Chantier3225DeepAuditTest extends TestCase
{
    protected function seedPerms(User $user, array $perms): void
    {
        foreach ($perms as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            $user->givePermissionTo($perm);
        }
        Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
        $user->assignRole('employee');
    }

    // ─── IDOR #1 : createScenario()/compareScenarios() sans cloisonnement ──

    public function test_create_scenario_rejects_another_companys_forecast_model(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = User::factory()->for($companyA)->create();
        $this->seedPerms($userA, []);

        $modelB = ForecastModel::factory()->create(['tenant_id' => $companyB->id, 'module' => 'demand']);

        $response = $this->actingAs($userA)->postJson('/api/v1/forecasting/scenarios', [
            'model_id' => $modelB->id,
            'name' => 'Cross-tenant scenario',
            'assumptions' => ['x' => 1],
        ]);

        $response->assertNotFound();
    }

    public function test_create_scenario_succeeds_for_own_companys_model(): void
    {
        $companyA = Company::factory()->create();
        $userA = User::factory()->for($companyA)->create();
        $this->seedPerms($userA, []);

        $modelA = ForecastModel::factory()->create(['tenant_id' => $companyA->id, 'module' => 'demand']);

        $response = $this->actingAs($userA)->postJson('/api/v1/forecasting/scenarios', [
            'model_id' => $modelA->id,
            'name' => 'Own scenario',
            'assumptions' => ['x' => 1],
        ]);

        $response->assertCreated();
    }

    public function test_compare_scenarios_silently_excludes_another_companys_scenario(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = User::factory()->for($companyA)->create();
        $this->seedPerms($userA, []);

        $modelA = ForecastModel::factory()->create(['tenant_id' => $companyA->id]);
        $modelB = ForecastModel::factory()->create(['tenant_id' => $companyB->id]);
        $scenarioA = ForecastScenario::factory()->create(['forecast_model_id' => $modelA->id, 'results' => ['predictions' => []]]);
        $scenarioB = ForecastScenario::factory()->create(['forecast_model_id' => $modelB->id, 'results' => ['predictions' => []]]);

        $response = $this->actingAs($userA)->getJson('/api/v1/forecasting/scenarios/compare?ids[]='.$scenarioA->id.'&ids[]='.$scenarioB->id);

        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($scenarioA->id, $ids);
        $this->assertNotContains($scenarioB->id, $ids);
    }

    // ─── IDOR #2 : bypass admin cross-société (bug de précédence PHP) ──────

    public function test_admin_of_company_a_cannot_view_ml_model_of_company_b(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $adminA = User::factory()->for($companyA)->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminA->assignRole('admin');

        $modelB = MLModel::factory()->for($companyB)->create(['status' => 'staging']);

        $policy = new MLModelPolicy();
        $this->assertFalse($policy->view($adminA, $modelB));
        $this->assertFalse($policy->update($adminA, $modelB));
        $this->assertFalse($policy->deploy($adminA, $modelB));
    }

    public function test_admin_of_company_a_can_still_manage_own_company_ml_model(): void
    {
        $companyA = Company::factory()->create();
        $adminA = User::factory()->for($companyA)->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminA->assignRole('admin');

        $modelA = MLModel::factory()->for($companyA)->create(['status' => 'staging']);

        $policy = new MLModelPolicy();
        // Chantier 32.25: same-company checks reach the real `$user->
        // hasPermissionTo(...) || $user->hasRole('admin')` expression (the
        // cross-tenant tests above never do, since sameCompany() short-
        // circuits `&&` to false first) — PHP evaluates hasPermissionTo()
        // left-to-right before hasRole(), and Spatie throws
        // PermissionDoesNotExist for an unseeded permission string rather
        // than returning false, so the real permission must exist for this
        // assertion to reach the admin-role bypass at all. Test-fixture
        // correction, not an app bug: real requests always go through
        // RolesAndPermissionsSeeder first.
        Permission::firstOrCreate(['name' => 'analytics.ml_model.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'analytics.ml_model.update', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'analytics.ml_model.deploy', 'guard_name' => 'web']);
        $this->assertTrue($policy->view($adminA, $modelA));
        $this->assertTrue($policy->update($adminA, $modelA));
        $this->assertTrue($policy->deploy($adminA, $modelA));
    }

    public function test_admin_cross_company_bypass_fixed_on_all_five_analytics_policies(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $adminA = User::factory()->for($companyA)->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminA->assignRole('admin');

        $predictionB = \Modules\Analytics\Models\PredictionModel::factory()->for($companyB)->create(['status' => 'draft']);
        $recommendationModelB = RecommendationModel::factory()->for($companyB)->create();
        $recommendationB = Recommendation::factory()->for($recommendationModelB)->for($companyB)->create(['status' => 'pending']);
        $abTestB = \Modules\Analytics\Models\ABTestRun::factory()->for($companyB)->create(['status' => 'planned']);

        $this->assertFalse((new PredictionModelPolicy())->update($adminA, $predictionB));
        $this->assertFalse((new RecommendationModelPolicy())->view($adminA, $recommendationModelB));
        $this->assertFalse((new RecommendationPolicy())->act($adminA, $recommendationB));
        $this->assertFalse((new ABTestRunPolicy())->start($adminA, $abTestB));
    }

    // ─── IDOR #3 : MLModelController::rollback() sans vérification d'appartenance ──

    public function test_rollback_rejects_a_version_belonging_to_another_model(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->seedPerms($user, ['analytics.ml_model.rollback']);

        $modelA = MLModel::factory()->for($company)->create(['status' => 'production']);
        $modelC = MLModel::factory()->for($company)->create(['status' => 'production']);
        $versionOfC = MLModelVersion::factory()->create(['ml_model_id' => $modelC->id]);

        $response = $this->actingAs($user)->postJson("/api/v1/analytics/ml-models/{$modelA->id}/rollback", [
            'version_id' => $versionOfC->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Version does not belong to this model');
    }

    public function test_rollback_accepts_a_version_belonging_to_the_same_model(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->seedPerms($user, ['analytics.ml_model.rollback']);

        $model = MLModel::factory()->for($company)->create(['status' => 'production']);
        $version = MLModelVersion::factory()->create(['ml_model_id' => $model->id]);

        $response = $this->actingAs($user)->postJson("/api/v1/analytics/ml-models/{$model->id}/rollback", [
            'version_id' => $version->id,
        ]);

        $response->assertOk();
    }

    // ─── IDOR #4 : recipient_type/recommended_type — morph type arbitraire ──

    public function test_recommendation_rejects_an_arbitrary_class_name_as_recipient_type(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->seedPerms($user, ['analytics.recommendation.create']);

        $model = RecommendationModel::factory()->for($company)->create();

        $response = $this->actingAs($user)->postJson('/api/v1/analytics/recommendations', [
            'recommendation_model_id' => $model->id,
            'recipient_type' => \App\Models\User::class,
            'recipient_id' => 1,
            'recommended_type' => 'Product',
            'recommended_id' => 1,
            'relevance_score' => 0.5,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('recipient_type');
    }

    public function test_recommendation_accepts_the_real_allowlisted_aliases(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->seedPerms($user, ['analytics.recommendation.create']);

        $model = RecommendationModel::factory()->for($company)->create();

        $response = $this->actingAs($user)->postJson('/api/v1/analytics/recommendations', [
            'recommendation_model_id' => $model->id,
            'recipient_type' => 'Customer',
            'recipient_id' => 1,
            'recommended_type' => 'Product',
            'recommended_id' => 1,
            'relevance_score' => 0.5,
        ]);

        $response->assertCreated();
    }

    // ─── RecommendationModelController — activé (couche 9, fake/dead) ──────

    public function test_recommendation_model_full_crud_now_reachable(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->seedPerms($user, [
            'analytics.recommendation.view', 'analytics.recommendation.create',
            'analytics.recommendation.update', 'analytics.recommendation.train',
            'analytics.recommendation.delete',
        ]);

        $store = $this->actingAs($user)->postJson('/api/v1/analytics/recommendation-models', [
            'model_name' => 'Produits similaires',
            'recommendation_type' => 'products',
            'algorithm' => 'collaborative_filtering',
        ]);
        $store->assertCreated();
        $id = $store->json('id');

        $this->actingAs($user)->getJson('/api/v1/analytics/recommendation-models')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($user)->getJson("/api/v1/analytics/recommendation-models/{$id}")->assertOk();

        $train = $this->actingAs($user)->postJson("/api/v1/analytics/recommendation-models/{$id}/train");
        $train->assertOk();
        $train->assertJsonPath('model.status', 'active');

        $this->actingAs($user)->deleteJson("/api/v1/analytics/recommendation-models/{$id}")->assertNoContent();
    }

    public function test_recommendation_model_cannot_be_seen_across_companies(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = User::factory()->for($companyA)->create();
        $this->seedPerms($userA, ['analytics.recommendation.view']);

        $modelB = RecommendationModel::factory()->for($companyB)->create();

        $response = $this->actingAs($userA)->getJson("/api/v1/analytics/recommendation-models/{$modelB->id}");

        $response->assertForbidden();
    }

    // ─── HrForecastService — hr_leave_balances (table supprimée) + job_postings (jamais migrée) ──

    public function test_predict_turnover_risk_no_longer_crashes_on_active_employees(): void
    {
        $company = Company::factory()->create();
        \Modules\HR\Models\Employee::factory()->create(['status' => 'active', 'hire_date' => now()->subMonths(3)]);
        \Modules\HR\Models\LeaveType::factory()->create(['is_active' => true, 'days_per_year' => 25]);

        $service = app(HrForecastService::class);
        $result = $service->predictTurnoverRisk((int) $company->id);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('risk_score', $result[0]);
    }

    public function test_forecast_headcount_no_longer_crashes_when_hiring_gap_is_positive(): void
    {
        $company = Company::factory()->create();
        // Zero current headcount + a real confirmed sales order => a guaranteed
        // positive hiring gap, forcing the suggestRoles() branch that used to
        // query the never-migrated job_postings table.
        \Illuminate\Support\Facades\DB::table('sales_orders')->insert([
            'tenant_id' => $company->id, 'reference' => 'SO-TEST-1', 'status' => 'confirmed', 'total' => 1_000_000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $service = app(HrForecastService::class);
        $result = $service->forecastHeadcount((int) $company->id, 3);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }

    // ─── ProductionForecastService — Manufacturing hors périmètre, dégradation propre ──

    public function test_production_forecast_degrades_gracefully_instead_of_fatal_sql_error(): void
    {
        $company = Company::factory()->create();
        $service = app(ProductionForecastService::class);

        $result = $service->forecastProductionNeeds((int) $company->id, 30);

        $this->assertFalse($result['available']);
        $this->assertSame([], $result['predictions']);
        $this->assertSame(0, $result['summary']['total_orders']);
    }

    public function test_production_forecast_route_returns_200_not_500(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->seedPerms($user, []);

        $response = $this->actingAs($user)->getJson('/api/v1/forecasting/production');

        $response->assertOk();
        $response->assertJsonPath('available', false);
    }

    // ─── CashflowForecastService — narrative citait toujours "90 jours" ────

    public function test_cashflow_narrative_reflects_the_real_requested_horizon(): void
    {
        $company = Company::factory()->create();
        $service = app(CashflowForecastService::class);

        $result = $service->forecast90Days((int) $company->id, 30);

        $this->assertStringContainsString('30 jours', $result['narrative']);
        $this->assertStringNotContainsString('90 jours', $result['narrative']);
    }

    // ─── ForecastingEngineService — inventory/production module: table réelle vs manquante ──

    public function test_train_inventory_forecast_model_no_longer_crashes(): void
    {
        $company = Company::factory()->create();
        $model = ForecastModel::factory()->create([
            'tenant_id' => $company->id, 'module' => 'inventory', 'entity_id' => 1,
        ]);

        $engine = app(ForecastingEngineService::class);
        $trained = $engine->train($model);

        $this->assertNotNull($trained);
    }

    public function test_check_alerts_no_longer_crashes_on_a_real_inventory_model(): void
    {
        $company = Company::factory()->create();
        $model = ForecastModel::factory()->create([
            'tenant_id' => $company->id, 'module' => 'inventory', 'entity_id' => 1,
        ]);
        \Modules\Analytics\Models\ForecastPrediction::factory()->create([
            'forecast_model_id' => $model->id,
            'tenant_id' => $company->id,
            'forecast_date' => now()->addDay()->toDateString(),
            'predicted_value' => 500,
        ]);

        $engine = app(ForecastingEngineService::class);
        $count = $engine->checkAlerts((int) $company->id);

        $this->assertIsInt($count);
    }
}
