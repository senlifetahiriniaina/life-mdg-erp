<?php

declare(strict_types=1);

namespace Modules\Logistics\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomsRouteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_customs_declarations_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/logistics/customs');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_list_routes_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/logistics/routes');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_list_vehicles_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/logistics/vehicles');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_hs_code_suggest_requires_description(): void
    {
        $response = $this->postJson('/api/v1/logistics/customs/hs-code-suggest', []);
        $response->assertStatus(422);
    }
}
