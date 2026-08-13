<?php

namespace Modules\Analytics\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Modules\Analytics\Models\AnomalyDetectionModel;
use Modules\Analytics\Models\DetectedAnomaly;
use Tests\TestCase;

class AnomalyDetectionTest extends TestCase
{
    protected User $user;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->for($this->company)->create();

        $permissions = ['analytics.anomaly.view', 'analytics.anomaly.create', 'analytics.anomaly.investigate', 'analytics.anomaly.resolve'];
        foreach ($permissions as $perm) {
            $this->user->givePermissionTo($perm);
        }
    }

    public function test_list_anomaly_detection_models(): void
    {
        AnomalyDetectionModel::factory()->for($this->company)->count(3)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/analytics/anomalies');
        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_create_anomaly_detection_model(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/analytics/anomalies', [
            'model_name' => 'Transaction Anomaly Detector',
            'anomaly_type' => 'transaction',
            'algorithm' => 'isolation_forest',
            'anomaly_threshold' => 0.7,
        ]);

        $response->assertCreated()->assertJsonPath('model_name', 'Transaction Anomaly Detector');
    }

    public function test_filter_anomaly_models_by_type(): void
    {
        AnomalyDetectionModel::factory()->for($this->company)->create(['anomaly_type' => 'transaction']);
        AnomalyDetectionModel::factory()->for($this->company)->create(['anomaly_type' => 'performance']);

        $response = $this->actingAs($this->user)->getJson('/api/v1/analytics/anomalies?type=transaction');
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_view_anomaly_detection_model(): void
    {
        $model = AnomalyDetectionModel::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->getJson("/api/v1/analytics/anomalies/{$model->id}");
        $response->assertOk()->assertJsonPath('id', $model->id);
    }

    public function test_update_anomaly_threshold(): void
    {
        $model = AnomalyDetectionModel::factory()->for($this->company)->create(['anomaly_threshold' => 0.5]);

        $response = $this->actingAs($this->user)->patchJson("/api/v1/analytics/anomalies/{$model->id}", [
            'anomaly_threshold' => 0.8,
        ]);

        $response->assertOk()->assertJsonPath('anomaly_threshold', 0.8);
    }

    public function test_list_detected_anomalies(): void
    {
        $model = AnomalyDetectionModel::factory()->for($this->company)->create();
        DetectedAnomaly::factory()->for($model)->for($this->company)->count(5)->create();

        $response = $this->actingAs($this->user)->getJson("/api/v1/analytics/anomalies/{$model->id}/anomalies");
        $response->assertOk()->assertJsonCount(5, 'data');
    }

    public function test_filter_anomalies_by_severity(): void
    {
        $model = AnomalyDetectionModel::factory()->for($this->company)->create();
        DetectedAnomaly::factory()->for($model)->for($this->company)->create(['severity' => 'critical']);
        DetectedAnomaly::factory()->for($model)->for($this->company)->create(['severity' => 'low']);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/analytics/anomalies/{$model->id}/anomalies?severity=critical");
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_filter_anomalies_by_status(): void
    {
        $model = AnomalyDetectionModel::factory()->for($this->company)->create();
        DetectedAnomaly::factory()->for($model)->for($this->company)->create(['status' => 'new']);
        DetectedAnomaly::factory()->for($model)->for($this->company)->create(['status' => 'resolved']);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/analytics/anomalies/{$model->id}/anomalies?status=new");
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_investigate_anomaly(): void
    {
        $model = AnomalyDetectionModel::factory()->for($this->company)->create();
        $anomaly = DetectedAnomaly::factory()->for($model)->for($this->company)->create(['status' => 'new']);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/analytics/anomalies/{$model->id}/anomalies/{$anomaly->id}/investigate");

        $response->assertOk()->assertJsonPath('status', 'investigating');
    }

    public function test_resolve_anomaly_with_notes(): void
    {
        $model = AnomalyDetectionModel::factory()->for($this->company)->create();
        $anomaly = DetectedAnomaly::factory()->for($model)->for($this->company)->create(['status' => 'investigating']);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/analytics/anomalies/{$model->id}/anomalies/{$anomaly->id}/resolve", [
                'resolution_notes' => 'Data quality issue resolved in upstream system',
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'resolved');
        $response->assertJsonPath('resolution_notes', 'Data quality issue resolved in upstream system');
    }

    public function test_cannot_resolve_anomaly_without_notes(): void
    {
        $model = AnomalyDetectionModel::factory()->for($this->company)->create();
        $anomaly = DetectedAnomaly::factory()->for($model)->for($this->company)->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/analytics/anomalies/{$model->id}/anomalies/{$anomaly->id}/resolve", []);

        $response->assertUnprocessable();
    }

    public function test_dismiss_anomaly(): void
    {
        $model = AnomalyDetectionModel::factory()->for($this->company)->create();
        $anomaly = DetectedAnomaly::factory()->for($model)->for($this->company)->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/analytics/anomalies/{$model->id}/anomalies/{$anomaly->id}/dismiss");

        $response->assertOk()->assertJsonPath('status', 'dismissed');
    }

    public function test_anomaly_score_between_0_and_1(): void
    {
        $model = AnomalyDetectionModel::factory()->for($this->company)->create();
        $anomaly = DetectedAnomaly::factory()->for($model)->for($this->company)->create();

        $this->assertGreaterThanOrEqual(0, $anomaly->anomaly_score);
        $this->assertLessThanOrEqual(1, $anomaly->anomaly_score);
    }

    public function test_severity_levels_valid(): void
    {
        $model = AnomalyDetectionModel::factory()->for($this->company)->create();
        $anomaly = DetectedAnomaly::factory()->for($model)->for($this->company)->create();

        $this->assertIn($anomaly->severity, ['low', 'medium', 'high', 'critical']);
    }

    public function test_cannot_view_other_company_anomaly(): void
    {
        $otherCompany = Company::factory()->create();
        $model = AnomalyDetectionModel::factory()->for($otherCompany)->create();
        $anomaly = DetectedAnomaly::factory()->for($model)->for($otherCompany)->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/analytics/anomalies/{$model->id}/anomalies/{$anomaly->id}");

        $response->assertForbidden();
    }

    public function test_delete_anomaly_detection_model(): void
    {
        $model = AnomalyDetectionModel::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/analytics/anomalies/{$model->id}");

        $response->assertNoContent();
    }

    public function test_pagination_anomaly_lists(): void
    {
        $model = AnomalyDetectionModel::factory()->for($this->company)->create();
        DetectedAnomaly::factory()->for($model)->for($this->company)->count(25)->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/analytics/anomalies/{$model->id}/anomalies?per_page=10");

        $response->assertOk()->assertJsonCount(10, 'data');
    }
}
