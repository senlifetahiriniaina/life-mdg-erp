<?php

declare(strict_types=1);

namespace Modules\BI\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chantier 8.2 (BI — Visualization subsystem): VisualizationController (12
 * methods — custom heatmap/3D/waterfall chart visualizations: create/update/
 * delete, export, share, render, performance tracking) and VisualizationPolicy
 * were fully written but unreachable — none of the 3 backing tables
 * (bi_custom_visualizations, bi_visualization_templates,
 * bi_visualization_performance) existed. The web route/controller
 * (`bi.visualizations` -> BiWebController::visualizations()) already existed
 * and already rendered `BI/Visualizations/Index`; only that Vue page and the
 * migration were missing. See 2026_08_25_000005_create_bi_visualization_tables.php
 * for the migration and Visualizations/Index.vue for the new page.
 */
class Chantier82BiVisualizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_visualizations_page_renders(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/bi/visualizations');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('BI/Visualizations/Index', false));
    }

    public function test_admin_can_create_a_visualization(): void
    {
        $this->actingAsUser('admin');

        $response = $this->postJson('/api/v1/bi/visualizations', [
            'name' => 'Carte de chaleur des ventes',
            'description' => 'Répartition des ventes par région et par mois',
            'type' => 'heatmap',
            'config' => ['x_axis' => 'region', 'y_axis' => 'month'],
            'data_source' => ['module' => 'Sales', 'metric' => 'revenue'],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('bi_custom_visualizations', [
            'name' => 'Carte de chaleur des ventes',
            'type' => 'heatmap',
        ]);
    }

    public function test_user_without_permission_cannot_create_a_visualization(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/v1/bi/visualizations', [
            'name' => 'Visualisation non autorisée',
            'type' => 'heatmap',
            'config' => ['x_axis' => 'region'],
            'data_source' => ['module' => 'Sales'],
        ]);

        $response->assertForbidden();
    }
}
