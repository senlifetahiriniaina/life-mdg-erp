<?php

namespace Modules\Timesheets\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Timesheets\Models\TimesheetEntry;
use Modules\Timesheets\Models\TimeTrackingProject;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrackingProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('admin');
    }

    #[Test]
    public function can_list_tracking_projects()
    {
        TimeTrackingProject::factory()->count(5)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/timesheets/projects');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    #[Test]
    public function can_create_tracking_project()
    {

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/timesheets/projects', [
                'name' => 'Mobile App Development',
                'code' => 'MOBILE-001',
                'description' => 'Develop mobile application',
                'budget_hours' => 500,
                'start_date' => now()->format('Y-m-d'),
                'end_date' => now()->addMonths(6)->format('Y-m-d'),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Mobile App Development');

        $this->assertDatabaseHas('time_tracking_projects', [
            'code' => 'MOBILE-001',
            'budget_hours' => 500,
        ]);
    }

    #[Test]
    public function cannot_create_project_with_duplicate_code()
    {
        TimeTrackingProject::factory()->create(['code' => 'PROJECT-001']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/timesheets/projects', [
                'name' => 'Another Project',
                'code' => 'PROJECT-001',
                'budget_hours' => 100,
                'start_date' => now()->format('Y-m-d'),
                'end_date' => now()->addDays(30)->format('Y-m-d'),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    #[Test]
    public function can_retrieve_specific_project()
    {
        $project = TimeTrackingProject::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/timesheets/projects/{$project->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $project->id);
    }

    #[Test]
    public function can_update_project()
    {
        $project = TimeTrackingProject::factory()->create([
            'budget_hours' => 500,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/timesheets/projects/{$project->id}", [
                'budget_hours' => 600,
                'status' => 'active',
            ]);

        $response->assertOk();

        $project->refresh();
        $this->assertEquals(600, $project->budget_hours);
        $this->assertEquals('active', $project->status);
    }

    #[Test]
    public function can_delete_project()
    {
        $project = TimeTrackingProject::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/timesheets/projects/{$project->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('time_tracking_projects', ['id' => $project->id]);
    }

    #[Test]
    public function can_get_project_timesheets()
    {
        $project = TimeTrackingProject::factory()->create();
        $entries = TimesheetEntry::factory()
            ->count(5)
            ->create([
                'employee_id' => $this->user->id,
                'project_id' => $project->id,
            ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/timesheets/projects/{$project->id}/timesheets");

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    #[Test]
    public function can_filter_timesheets_by_date_range()
    {
        $project = TimeTrackingProject::factory()->create();

        TimesheetEntry::factory()->create([
            'employee_id' => $this->user->id,
            'project_id' => $project->id,
            'entry_date' => now()->subDays(10),
        ]);
        TimesheetEntry::factory()->create([
            'employee_id' => $this->user->id,
            'project_id' => $project->id,
            'entry_date' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/timesheets/projects/{$project->id}/timesheets?from_date=".now()->subDays(5)->format('Y-m-d'));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function can_get_project_metrics()
    {
        $project = TimeTrackingProject::factory()->create([
            'budget_hours' => 100,
        ]);

        TimesheetEntry::factory()
            ->count(5)
            ->create([
                'employee_id' => $this->user->id,
                'project_id' => $project->id,
                'hours_worked' => 8,
                'status' => 'approved',
            ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/timesheets/projects/{$project->id}/metrics");

        $response->assertOk()
            ->assertJsonStructure([
                'total_hours',
                'budget_hours',
                'remaining_hours',
                'percentage_used',
            ]);
    }

    #[Test]
    public function project_metrics_show_over_budget_status()
    {
        $project = TimeTrackingProject::factory()->create([
            'budget_hours' => 30,
        ]);

        TimesheetEntry::factory()
            ->count(5)
            ->create([
                'employee_id' => $this->user->id,
                'project_id' => $project->id,
                'hours_worked' => 8,
                'status' => 'approved',
            ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/timesheets/projects/{$project->id}/metrics");

        $response->assertOk()
            ->assertJsonPath('is_over_budget', true);
    }

    #[Test]
    public function can_filter_projects_by_status()
    {
        TimeTrackingProject::factory()->count(3)->create(['status' => 'active']);
        TimeTrackingProject::factory()->count(2)->create(['status' => 'completed']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/timesheets/projects?status=active');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }
}
