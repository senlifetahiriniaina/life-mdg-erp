<?php

declare(strict_types=1);

namespace Modules\Timesheets\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimesheetAdvancedControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsUser('employee');
    }

    public function test_list_timesheets_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/timesheets');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_log_hours_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/timesheets', []);
        $response->assertStatus(422)->assertJsonValidationErrors(['employee_id', 'work_date', 'hours_logged']);
    }

    public function test_utilization_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/timesheets/utilization');
        $response->assertStatus(200);
    }

    public function test_revenue_recognition_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/timesheets/revenue-recognition');
        $response->assertStatus(200);
    }
}
