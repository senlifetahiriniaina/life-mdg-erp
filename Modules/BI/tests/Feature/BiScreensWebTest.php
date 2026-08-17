<?php

declare(strict_types=1);

namespace Modules\BI\Tests\Feature;

use App\Models\User;
use Modules\BI\Models\Dashboard;
use Modules\BI\Models\Kpi;
use Modules\BI\Models\Report;
use Tests\TestCase;

/**
 * Chantier 6: seven BI pages (Analytics, Kpis, NlQuery, Visualizations,
 * AINarratives, PredictiveAnalytics, Dashboard/Builder) already contained
 * real axios wiring against real endpoints but had no route anywhere in
 * routes/web.php — they were unreachable except by typing the URL by hand.
 * BI/Index.vue's own `dashboards` prop was never sent by the controller
 * either, so the dashboards section always rendered empty. NLQuery/Index.vue
 * (module-scoped) was a fully mocked duplicate of the real, now-routed
 * BI/NlQuery.vue and was deleted; AINarratives/Index.vue and
 * PredictiveAnalytics/Index.vue (module-scoped) were 100% hardcoded mock
 * data with a `useRoleAccess()` call missing its import — rewritten onto
 * real endpoints (bi/insights, bi/ai/narrative, bi/predictive-models,
 * bi/analytics/revenue-trend, bi/anomalies).
 */
class BiScreensWebTest extends TestCase
{
    public function test_index_sends_dashboards_prop()
    {
        $user = User::factory()->create();
        Dashboard::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/bi');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('BI/Index', false)
            ->has('dashboards', 1)
            ->has('kpis')
            ->has('recentReports')
        );
    }

    public function test_analytics_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/bi/analytics');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('BI/Analytics', false));
    }

    public function test_kpis_page_renders_with_resource_shaped_kpis()
    {
        $user = User::factory()->create();
        Kpi::factory()->create(['name' => 'Chiffre d\'affaires', 'value' => 1000, 'category' => 'finance']);

        $response = $this->actingAs($user)->get('/bi/kpis');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('BI/Kpis', false)
            ->has('kpis.0.value')
            ->has('kpis.0.category')
        );
    }

    public function test_nl_query_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/bi/nl-query');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('BI/NlQuery', false));
    }

    public function test_visualizations_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/bi/visualizations');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('BI/Visualizations/Index', false));
    }

    public function test_ai_narratives_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/bi/ai-narratives');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('BI/AINarratives/Index', false));
    }

    public function test_predictive_analytics_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/bi/predictive-analytics');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('BI/PredictiveAnalytics/Index', false));
    }

    public function test_dashboard_builder_create_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/bi/dashboards/builder');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('BI/Builder', false)
            ->where('dashboardId', null)
        );
    }

    public function test_dashboard_builder_edit_renders_with_existing_widgets()
    {
        $user = User::factory()->create();
        $dashboard = Dashboard::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/bi/dashboards/{$dashboard->id}/builder");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('BI/Builder', false)
            ->where('dashboardId', $dashboard->id)
        );
    }

    public function test_dashboard_show_renders_with_widgets()
    {
        $user = User::factory()->create();
        $dashboard = Dashboard::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/bi/dashboards/{$dashboard->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('BI/Dashboard', false)
            ->has('dashboard')
            ->has('widgets')
        );
    }

    public function test_reports_index_lists_real_reports()
    {
        $user = User::factory()->create();
        Report::factory()->create(['name' => 'Rapport mensuel']);

        $response = $this->actingAs($user)->get('/bi/reports');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('BI/Reports/Index', false)
            ->has('reports.data', 1)
        );
    }

    /**
     * KpiController::index() used to return the raw Eloquent paginator with
     * un-mapped column names (current_value, threshold_warning, ...) instead
     * of the {value, trend_percentage, threshold, category, sparkline} shape
     * every KPI screen actually reads.
     */
    public function test_kpi_api_returns_resource_shaped_payload()
    {
        $this->actingAsUser('manager');
        Kpi::factory()->create(['value' => 42.5, 'category' => 'sales', 'unit' => '%']);

        $response = $this->getJson('/api/v1/bi/kpis');

        $response->assertOk();
        $response->assertJsonPath('data.0.value', 42.5);
        $response->assertJsonPath('data.0.category', 'sales');
    }

    public function test_kpi_create_persists_category_and_target_and_threshold()
    {
        $this->actingAsUser('manager');

        $response = $this->postJson('/api/v1/bi/kpis', [
            'name' => 'Taux de conversion',
            'category' => 'sales',
            'unit' => '%',
            'target' => 25,
            'threshold_warning' => 10,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.category', 'sales');
        $response->assertJsonPath('data.target', 25);
        $response->assertJsonPath('data.threshold', 10);
    }
}
