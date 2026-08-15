<?php

namespace Modules\Analytics\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Modules\Analytics\Models\PredictionModel;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PredictionModelTest extends TestCase
{
    protected User $user;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->for($this->company)->create();

        $permissions = [
            'analytics.prediction.view',
            'analytics.prediction.create',
            'analytics.prediction.update',
            'analytics.prediction.train',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            $this->user->givePermissionTo($perm);
        }
    }

    public function test_list_prediction_models(): void
    {
        PredictionModel::factory()->for($this->company)->count(3)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/analytics/predictions');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_filter_prediction_models_by_type(): void
    {
        PredictionModel::factory()->for($this->company)->create(['model_type' => 'churn']);
        PredictionModel::factory()->for($this->company)->create(['model_type' => 'revenue']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/analytics/predictions?type=churn');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals('churn', $response->json('data.0.model_type'));
    }

    public function test_filter_prediction_models_by_status(): void
    {
        PredictionModel::factory()->for($this->company)->create(['status' => 'draft']);
        PredictionModel::factory()->for($this->company)->create(['status' => 'training']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/analytics/predictions?status=draft');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_search_prediction_models_by_name(): void
    {
        PredictionModel::factory()->for($this->company)->create(['model_name' => 'Churn Prediction V1']);
        PredictionModel::factory()->for($this->company)->create(['model_name' => 'Revenue Forecast']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/analytics/predictions?search=Churn');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_create_prediction_model(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/analytics/predictions', [
            'model_name' => 'Customer Churn Model',
            'model_type' => 'churn',
            'description' => 'Predicts customer churn probability',
            'configuration' => ['threshold' => 0.5],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('model_name', 'Customer Churn Model');
        $response->assertJsonPath('status', 'draft');
    }

    public function test_create_prediction_model_requires_name(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/analytics/predictions', [
            'model_type' => 'churn',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('model_name');
    }

    public function test_create_prediction_model_requires_valid_type(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/analytics/predictions', [
            'model_name' => 'Test Model',
            'model_type' => 'invalid_type',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('model_type');
    }

    public function test_view_prediction_model_details(): void
    {
        $model = PredictionModel::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->getJson("/api/v1/analytics/predictions/{$model->id}");

        $response->assertOk();
        $response->assertJsonPath('id', $model->id);
        $response->assertJsonPath('model_name', $model->model_name);
    }

    public function test_cannot_view_other_company_prediction_model(): void
    {
        $otherCompany = Company::factory()->create();
        $model = PredictionModel::factory()->for($otherCompany)->create();

        $response = $this->actingAs($this->user)->getJson("/api/v1/analytics/predictions/{$model->id}");

        $response->assertForbidden();
    }

    public function test_update_prediction_model_in_draft_status(): void
    {
        $model = PredictionModel::factory()->for($this->company)->create(['status' => 'draft']);

        $response = $this->actingAs($this->user)->patchJson("/api/v1/analytics/predictions/{$model->id}", [
            'model_name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        $response->assertOk();
        $response->assertJsonPath('model_name', 'Updated Name');
    }

    public function test_cannot_update_prediction_model_not_in_draft(): void
    {
        $model = PredictionModel::factory()->for($this->company)->create(['status' => 'training']);

        $response = $this->actingAs($this->user)->patchJson("/api/v1/analytics/predictions/{$model->id}", [
            'model_name' => 'Updated Name',
        ]);

        $response->assertForbidden();
    }

    public function test_train_prediction_model(): void
    {
        $model = PredictionModel::factory()->for($this->company)->create(['status' => 'draft']);

        $response = $this->actingAs($this->user)->postJson("/api/v1/analytics/predictions/{$model->id}/train", [
            'training_samples' => 5000,
            'validation_split' => 0.2,
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'training');
    }

    public function test_train_requires_minimum_samples(): void
    {
        $model = PredictionModel::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->postJson("/api/v1/analytics/predictions/{$model->id}/train", [
            'training_samples' => 50,
            'validation_split' => 0.2,
        ]);

        $response->assertUnprocessable();
    }

    public function test_view_prediction_results(): void
    {
        $model = PredictionModel::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/analytics/predictions/{$model->id}/results");

        $response->assertOk();
    }

    public function test_filter_results_by_score(): void
    {
        $model = PredictionModel::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/analytics/predictions/{$model->id}/results?score_min=0.7");

        $response->assertOk();
    }

    public function test_pagination_works_correctly(): void
    {
        PredictionModel::factory()->for($this->company)->count(25)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/analytics/predictions?per_page=10');

        $response->assertOk();
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.total', 25);
    }

    public function test_delete_prediction_model(): void
    {
        $model = PredictionModel::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/analytics/predictions/{$model->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('prediction_models', ['id' => $model->id]);
    }

    public function test_unauthorized_user_cannot_list_predictions(): void
    {
        $user = User::factory()->for($this->company)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/analytics/predictions');

        $response->assertForbidden();
    }
}
