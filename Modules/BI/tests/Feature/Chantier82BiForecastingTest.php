<?php

declare(strict_types=1);

namespace Modules\BI\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chantier 8.2 (BI — Forecasting subsystem): ForecastingController (15
 * methods) and ForecastingPolicy were fully written and already routed to
 * real models, but none of the 7 backing tables existed
 * (bi_forecast_models, bi_forecast_predictions, bi_forecast_scenarios,
 * bi_model_retraining_logs, bi_scenario_predictions, bi_seasonality_patterns,
 * bi_trend_analysis), so the whole subsystem was unreachable end-to-end.
 * See 2026_08_25_000004_create_bi_forecasting_tables.php for the schema fix
 * and the Web route added for /bi/forecasting.
 */
class Chantier82BiForecastingTest extends TestCase
{
    use RefreshDatabase;

    public function test_forecasting_page_renders(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/bi/forecasting');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('BI/Forecasting/Index', false));
    }

    public function test_admin_can_create_forecast_model(): void
    {
        $user = $this->actingAsUser('admin');

        $response = $this->postJson('/api/v1/bi/forecast-models', [
            'name' => 'Prévision CA mensuel',
            'description' => 'Modèle de prévision du chiffre d\'affaires',
            'model_type' => 'exponential_smoothing',
            'metric_name' => 'revenue',
            'metric_source_id' => 1,
            'data_frequency' => 'monthly',
            'lookback_days' => 90,
            'forecast_horizon' => 30,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'Prévision CA mensuel')
            ->assertJsonPath('status', 'draft');

        $this->assertDatabaseHas('bi_forecast_models', [
            'name' => 'Prévision CA mensuel',
            'model_type' => 'exponential_smoothing',
            'metric_name' => 'revenue',
            'company_id' => $user->company_id,
            'created_by' => $user->id,
        ]);
    }

    public function test_user_without_forecasting_permission_cannot_create_forecast_model(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/v1/bi/forecast-models', [
            'name' => 'Unauthorized Model',
            'model_type' => 'linear_regression',
            'metric_name' => 'revenue',
            'metric_source_id' => 1,
        ]);

        $response->assertForbidden();
    }
}
