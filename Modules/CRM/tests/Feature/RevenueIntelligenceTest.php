<?php

namespace Modules\CRM\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CRM\Models\RevenueInsight;
use Modules\CRM\Models\RevenueAnomaly;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RevenueIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        // Chantier 10 fix: RevenueIntelligenceController now authorizes every endpoint via
        // RevenueInsightPolicy against crm.revenue-intelligence.* permissions — these strings
        // are new in this chantier and not yet in RolesAndPermissionsSeeder's
        // CRM_EXTRA_PERMISSIONS block (a documented Phase B follow-up, out of this chantier's
        // scope), so the permission rows are created directly here rather than via the
        // central seeder.
        foreach (['view', 'create', 'resolve'] as $verb) {
            Permission::firstOrCreate(['name' => "crm.revenue-intelligence.{$verb}", 'guard_name' => 'web']);
        }

        // users.company_id is a real foreign key onto companies — a bare literal id would
        // violate the FK constraint under RefreshDatabase's fresh schema.
        $this->companyId = Company::factory()->create()->id;

        $this->user = User::factory()->create(['company_id' => $this->companyId]);
        $this->user->givePermissionTo([
            'crm.revenue-intelligence.view',
            'crm.revenue-intelligence.create',
            'crm.revenue-intelligence.resolve',
        ]);
    }

    public function test_user_can_generate_insight(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/v1/crm/revenue-intelligence/insights/generate', [
            'insight_type' => 'trend',
            'category'     => 'sales_performance',
            'title'        => 'Revenue Growth Trend',
            'description'  => 'Revenue is growing 12% YoY',
            'impact_score' => 8,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('crm_revenue_insights', [
            'title'      => 'Revenue Growth Trend',
            'company_id' => $this->companyId,
        ]);
    }

    public function test_user_can_list_insights(): void
    {
        $this->actingAs($this->user);

        RevenueInsight::factory()->count(5)->create(['company_id' => $this->companyId]);
        // Other tenant's insights must never appear in this user's list.
        RevenueInsight::factory()->count(2)->create(['company_id' => Company::factory()->create()->id]);

        $response = $this->getJson('/api/v1/crm/revenue-intelligence/insights');

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
    }

    public function test_user_can_detect_anomaly(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/v1/crm/revenue-intelligence/anomalies/detect', [
            'anomaly_type'    => 'unusual_spike',
            'metric_name'     => 'closed_revenue',
            'detected_value'  => 100000,
            'expected_value'  => 50000,
            'severity'        => 'high',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('crm_revenue_anomalies', [
            'metric_name' => 'closed_revenue',
            'company_id'  => $this->companyId,
        ]);
    }

    public function test_user_can_get_performance_summary(): void
    {
        $this->actingAs($this->user);

        RevenueInsight::factory()->count(3)->create(['company_id' => $this->companyId]);
        RevenueAnomaly::factory()->count(2)->create(['severity' => 'high', 'company_id' => $this->companyId]);

        $response = $this->getJson('/api/v1/crm/revenue-intelligence/summary');

        $response->assertStatus(200);
        $response->assertJsonStructure(['active_insights', 'critical_anomalies', 'recent_trends']);
    }

    public function test_user_cannot_read_another_tenants_insights(): void
    {
        $this->actingAs($this->user);

        RevenueInsight::factory()->count(3)->create(['company_id' => Company::factory()->create()->id]);

        $response = $this->getJson('/api/v1/crm/revenue-intelligence/insights');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_user_without_permission_is_denied(): void
    {
        $unprivileged = User::factory()->create(['company_id' => $this->companyId]);
        $this->actingAs($unprivileged);

        $this->getJson('/api/v1/crm/revenue-intelligence/insights')->assertStatus(403);
    }
}
