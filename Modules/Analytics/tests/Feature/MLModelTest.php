<?php

namespace Modules\Analytics\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Modules\Analytics\Models\ABTestRun;
use Modules\Analytics\Models\MLModel;
use Modules\Analytics\Models\MLModelVersion;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MLModelTest extends TestCase
{
    protected User $user;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->for($this->company)->create();

        $permissions = ['analytics.ml_model.view', 'analytics.ml_model.create', 'analytics.ml_model.deploy', 'analytics.ml_model.rollback'];
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            $this->user->givePermissionTo($perm);
        }
    }

    public function test_list_ml_models(): void
    {
        MLModel::factory()->for($this->company)->count(3)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/analytics/ml-models');
        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_create_ml_model(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/analytics/ml-models', [
            'model_key' => 'churn-prediction-v1',
            'model_name' => 'Churn Prediction Model',
            'model_category' => 'prediction',
            'framework' => 'xgboost',
        ]);

        $response->assertCreated()->assertJsonPath('status', 'development');
    }

    public function test_model_key_must_be_unique(): void
    {
        MLModel::factory()->for($this->company)->create(['model_key' => 'churn-v1']);

        $response = $this->actingAs($this->user)->postJson('/api/v1/analytics/ml-models', [
            'model_key' => 'churn-v1',
            'model_name' => 'Another Model',
            'model_category' => 'prediction',
            'framework' => 'xgboost',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('model_key');
    }

    public function test_filter_models_by_category(): void
    {
        MLModel::factory()->for($this->company)->create(['model_category' => 'prediction']);
        MLModel::factory()->for($this->company)->create(['model_category' => 'recommendation']);

        $response = $this->actingAs($this->user)->getJson('/api/v1/analytics/ml-models?category=prediction');
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_filter_models_by_status(): void
    {
        MLModel::factory()->for($this->company)->create(['status' => 'development']);
        MLModel::factory()->for($this->company)->create(['status' => 'production']);

        $response = $this->actingAs($this->user)->getJson('/api/v1/analytics/ml-models?status=production');
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_filter_models_by_framework(): void
    {
        MLModel::factory()->for($this->company)->create(['framework' => 'xgboost']);
        MLModel::factory()->for($this->company)->create(['framework' => 'lightgbm']);

        $response = $this->actingAs($this->user)->getJson('/api/v1/analytics/ml-models?framework=xgboost');
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_view_ml_model_with_versions(): void
    {
        $model = MLModel::factory()->for($this->company)->create();
        MLModelVersion::factory()->for($model)->create(['version_number' => 'v1.0.0']);

        $response = $this->actingAs($this->user)->getJson("/api/v1/analytics/ml-models/{$model->id}");

        $response->assertOk();
        $response->assertJsonPath('model_name', $model->model_name);
    }

    public function test_update_ml_model(): void
    {
        $model = MLModel::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->patchJson("/api/v1/analytics/ml-models/{$model->id}", [
            'model_name' => 'Updated Model Name',
            'description' => 'Updated description',
        ]);

        $response->assertOk()->assertJsonPath('model_name', 'Updated Model Name');
    }

    public function test_list_model_versions(): void
    {
        $model = MLModel::factory()->for($this->company)->create();
        MLModelVersion::factory()->for($model)->count(3)->create();

        $response = $this->actingAs($this->user)->getJson("/api/v1/analytics/ml-models/{$model->id}/versions");

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_deploy_model_version(): void
    {
        $model = MLModel::factory()->for($this->company)->create(['status' => 'staging']);
        $version = MLModelVersion::factory()->for($model)->create([
            'version_number' => 'v1.0.0',
            'validation_accuracy' => 0.92,
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/v1/analytics/ml-models/{$model->id}/deploy", [
            'version_id' => $version->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('model.status', 'production');
    }

    public function test_cannot_deploy_non_staging_model(): void
    {
        $model = MLModel::factory()->for($this->company)->create(['status' => 'development']);
        $version = MLModelVersion::factory()->for($model)->create();

        $response = $this->actingAs($this->user)->postJson("/api/v1/analytics/ml-models/{$model->id}/deploy", [
            'version_id' => $version->id,
        ]);

        $response->assertForbidden();
    }

    public function test_rollback_to_previous_version(): void
    {
        $model = MLModel::factory()->for($this->company)->create(['status' => 'production']);
        $version = MLModelVersion::factory()->for($model)->create(['version_number' => 'v1.0.1']);

        $response = $this->actingAs($this->user)->postJson("/api/v1/analytics/ml-models/{$model->id}/rollback", [
            'version_id' => $version->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('model.production_version', 'v1.0.1');
    }

    public function test_list_ab_tests(): void
    {
        $model = MLModel::factory()->for($this->company)->create();
        $v1 = MLModelVersion::factory()->for($model)->create();
        $v2 = MLModelVersion::factory()->for($model)->create();
        ABTestRun::factory()
            ->for($this->company)
            ->for($model)
            ->create([
                'control_version_id' => $v1->id,
                'variant_version_id' => $v2->id,
            ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/analytics/ml-models/{$model->id}/ab-tests");

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_filter_ab_tests_by_status(): void
    {
        $model = MLModel::factory()->for($this->company)->create();
        $v1 = MLModelVersion::factory()->for($model)->create();
        $v2 = MLModelVersion::factory()->for($model)->create();

        ABTestRun::factory()
            ->for($this->company)
            ->for($model)
            ->create([
                'control_version_id' => $v1->id,
                'variant_version_id' => $v2->id,
                'status' => 'running',
            ]);

        ABTestRun::factory()
            ->for($this->company)
            ->for($model)
            ->create([
                'control_version_id' => $v1->id,
                'variant_version_id' => $v2->id,
                'status' => 'completed',
            ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/analytics/ml-models/{$model->id}/ab-tests?status=running");

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_model_accuracy_tracked(): void
    {
        $model = MLModel::factory()->for($this->company)->create([
            'production_accuracy' => 0.92,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/analytics/ml-models/{$model->id}");

        $response->assertOk();
        $response->assertJsonPath('production_accuracy', 0.92);
    }

    public function test_inference_count_tracked(): void
    {
        $model = MLModel::factory()->for($this->company)->create(['inference_count' => 1000]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/analytics/ml-models/{$model->id}");

        $response->assertOk();
        $response->assertJsonPath('inference_count', 1000);
    }

    public function test_delete_development_model(): void
    {
        $model = MLModel::factory()->for($this->company)->create(['status' => 'development']);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/analytics/ml-models/{$model->id}");

        $response->assertNoContent();
    }

    public function test_cannot_delete_production_model(): void
    {
        $model = MLModel::factory()->for($this->company)->create(['status' => 'production']);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/analytics/ml-models/{$model->id}");

        $response->assertForbidden();
    }

    public function test_pagination_models(): void
    {
        MLModel::factory()->for($this->company)->count(25)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/analytics/ml-models?per_page=10');

        $response->assertOk()->assertJsonCount(10, 'data');
    }

    public function test_cannot_view_other_company_model(): void
    {
        $otherCompany = Company::factory()->create();
        $model = MLModel::factory()->for($otherCompany)->create();

        $response = $this->actingAs($this->user)->getJson("/api/v1/analytics/ml-models/{$model->id}");

        $response->assertForbidden();
    }
}
