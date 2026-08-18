<?php

declare(strict_types=1);

namespace Modules\Timesheets\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Tests\TestCase;

class TimesheetAdvancedControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->actingAsUser('employee');
        Employee::factory()->create(['user_id' => $user->id]);
    }

    /**
     * Chantier 8.4: the bare GET/POST /api/v1/timesheets endpoints this
     * test previously covered were deleted — 100% redundant duplicates of
     * the real, already-tested TimesheetEntryController CRUD. Replaced
     * with equivalent coverage of the real "sheets" (TimesheetPeriod)
     * endpoints built to back Sheets/*.vue instead.
     */
    public function test_list_sheets_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/timesheets/sheets');
        $response->assertStatus(200)->assertJsonStructure(['data', 'total']);
    }

    public function test_create_sheet_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/timesheets/sheets', []);
        $response->assertStatus(422)->assertJsonValidationErrors(['period_start', 'period_end']);
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
