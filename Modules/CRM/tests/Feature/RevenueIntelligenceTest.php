<?php

namespace Modules\CRM\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CRM\Models\RevenueInsight;
use Modules\CRM\Models\RevenueAnomaly;
use Tests\TestCase;

class RevenueIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_generate_insight(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('v1/crm/revenue-intelligence/insights/generate', [
            'insight_type' => 'trend',
            'category'     => 'sales_performance',
            'title'        => 'Revenue Growth Trend',
            'description'  => 'Revenue is growing 12% YoY',
            'impact_score' => 8,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('crm_revenue_insights', [
            'title' => 'Revenue Growth Trend',
        ]);
    }

    public function test_user_can_list_insights(): void
    {
        $this->actingAs($this->user);

        RevenueInsight::factory()->count(5)->create();

        $response = $this->getJson('v1/crm/revenue-intelligence/insights');

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
    }

    public function test_user_can_detect_anomaly(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('v1/crm/revenue-intelligence/anomalies/detect', [
            'anomaly_type'    => 'unusual_spike',
            'metric_name'     => 'closed_revenue',
            'detected_value'  => 100000,
            'expected_value'  => 50000,
            'severity'        => 'high',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('crm_revenue_anomalies', [
            'metric_name' => 'closed_revenue',
        ]);
    }

    public function test_user_can_get_performance_summary(): void
    {
        $this->actingAs($this->user);

        RevenueInsight::factory()->count(3)->create();
        RevenueAnomaly::factory()->count(2)->create(['severity' => 'high']);

        $response = $this->getJson('v1/crm/revenue-intelligence/summary');

        $response->assertStatus(200);
        $response->assertJsonStructure(['active_insights', 'critical_anomalies', 'recent_trends']);
    }
}
