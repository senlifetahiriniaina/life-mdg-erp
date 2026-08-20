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

    /**
     * Chantier 19 Lot 5 — the single most severe finding of this lot:
     * Builder.vue (the Dashboard Builder page, this module's core feature)
     * has always POSTed its full widget array to
     * `bi/dashboards/{dashboard}/widgets` on every save, but that route
     * never existed anywhere — every real save 404'd on the widget half of
     * the operation, meaning no dashboard built through the real UI has
     * ever actually had a single widget persisted. This exercises the real
     * end-to-end flow the frontend follows: create a dashboard, save
     * widgets via the new real route, then reload the builder page and
     * confirm the widgets round-trip with their real fields (not reset to
     * defaults — see BiWebController::builder()'s position-flattening fix).
     */
    public function test_widgets_can_be_saved_and_round_trip_through_the_builder()
    {
        $user = $this->actingAsUser('manager');
        $dashboard = Dashboard::factory()->create(['user_id' => $user->id]);

        $saveResponse = $this->postJson("/api/v1/bi/dashboards/{$dashboard->id}/widgets", [
            'widgets' => [
                [
                    'type' => 'kpi_card',
                    'title' => 'Revenu mensuel',
                    'description' => 'CA du mois',
                    'dataSource' => 'accounting.revenue',
                    'w' => 3,
                    'h' => 1,
                    'config' => ['metric' => 'revenue', 'period' => 'month'],
                ],
                [
                    'type' => 'bar_chart',
                    'title' => 'Ventes par mois',
                    'w' => 6,
                    'h' => 2,
                ],
            ],
        ]);

        $saveResponse->assertCreated();
        $this->assertDatabaseCount('bi_widgets', 2);
        $this->assertDatabaseHas('bi_widgets', ['dashboard_id' => $dashboard->id, 'title' => 'Revenu mensuel']);

        $builderResponse = $this->get("/bi/dashboards/{$dashboard->id}/builder");
        $builderResponse->assertOk();
        $builderResponse->assertInertia(fn ($page) => $page
            ->component('BI/Builder', false)
            ->has('existingWidgets', 2)
            ->where('existingWidgets.0.title', 'Revenu mensuel')
            ->where('existingWidgets.0.dataSource', 'accounting.revenue')
            ->where('existingWidgets.0.w', 3)
            ->where('existingWidgets.1.w', 6)
            ->where('existingWidgets.1.h', 2)
        );
    }

    /**
     * The builder always sends its entire current widget list on every
     * save (create AND edit), not incremental diffs — a second save must
     * replace, not append to, the dashboard's widgets.
     */
    public function test_saving_widgets_a_second_time_replaces_rather_than_appends()
    {
        $user = $this->actingAsUser('manager');
        $dashboard = Dashboard::factory()->create(['user_id' => $user->id]);

        $this->postJson("/api/v1/bi/dashboards/{$dashboard->id}/widgets", [
            'widgets' => [['type' => 'kpi_card', 'title' => 'First']],
        ])->assertCreated();

        $this->postJson("/api/v1/bi/dashboards/{$dashboard->id}/widgets", [
            'widgets' => [['type' => 'bar_chart', 'title' => 'Second']],
        ])->assertCreated();

        $this->assertDatabaseCount('bi_widgets', 1);
        $this->assertDatabaseHas('bi_widgets', ['dashboard_id' => $dashboard->id, 'title' => 'Second']);
    }

    /**
     * The `bi/*` route group is already gated to `role:manager,admin` at
     * the outer middleware level (see routes/api.php), and
     * BaseErpPolicy::isAdminOrOwner() lets both roles bypass ownership by
     * design (the same documented behavior EmbedTokenTest.php already
     * locks in for embed-token creation) — so every caller who can reach
     * this endpoint at all is already allowed regardless of ownership.
     * This documents that real, deliberate behavior rather than asserting
     * a 403 no role able to reach the route could ever actually trigger.
     */
    public function test_a_manager_can_save_widgets_onto_a_dashboard_they_do_not_own()
    {
        $this->actingAsUser('manager');
        $owner = User::factory()->create();
        $dashboard = Dashboard::factory()->create(['user_id' => $owner->id, 'is_public' => false]);

        $this->postJson("/api/v1/bi/dashboards/{$dashboard->id}/widgets", [
            'widgets' => [['type' => 'kpi_card', 'title' => 'Saved by manager']],
        ])->assertCreated();
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
     * Chantier 19 Lot 5: BiWebController::sqlEditor()'s `savedQueries` prop
     * select list was missing `sql_query` — the real column, confirmed via
     * BiQuery::$fillable — so SqlEditor.vue's loadQuery() always populated
     * an empty editor for any saved query, no matter what SQL it actually
     * held. Locks in that the real SQL text now reaches the page.
     */
    public function test_sql_editor_sends_sql_query_on_saved_queries()
    {
        $user = User::factory()->create();
        \Modules\BI\Models\BiQuery::create([
            'name' => 'Ventes du mois',
            'sql_query' => 'SELECT 1 as total',
            'datasource' => 'default',
            'is_public' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get('/bi/sql-editor');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('BI/SqlEditor', false)
            ->where('savedQueries.0.sql_query', 'SELECT 1 as total')
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
