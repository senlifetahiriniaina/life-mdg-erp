<?php

declare(strict_types=1);

namespace Modules\Projects\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectAdvancedControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsUser('admin');
    }

    public function test_list_projects_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/projects');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_portfolio_kpis_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/projects/portfolio/kpis');
        $response->assertStatus(200);
    }

    public function test_create_project_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/projects', []);
        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_get_project_gantt_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/projects/999/gantt');
        // Should return 200 with empty/demo data, not 500
        $this->assertContains($response->status(), [200, 404]);
    }
}
