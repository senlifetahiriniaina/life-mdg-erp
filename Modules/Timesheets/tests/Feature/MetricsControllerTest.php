<?php

namespace Modules\Timesheets\Tests\Feature;

use App\Models\User;
use Modules\HR\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Timesheets\Models\TimesheetEntry;
use Modules\Timesheets\Models\TimeTrackingProject;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MetricsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
    }

    #[Test]
    public function can_get_employee_metrics()
    {
        $user = User::factory()->create();
        $user->assignRole('employee');
        TimesheetEntry::factory()
            ->count(10)
            ->create([
                'employee_id' => $user->id,
                'hours_worked' => 8,
                'status' => 'approved',
            ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/timesheets/metrics/employee/{$user->id}/month/".now()->format('Y-m'));

        $response->assertOk()
            ->assertJsonStructure([
                'total_entries',
                'total_hours',
                'billable_hours',
                'total_cost',
            ]);
    }

    #[Test]
    public function employee_metrics_include_only_approved_billable_hours()
    {
        $user = User::factory()->create();
        $user->assignRole('employee');
        TimesheetEntry::factory()
            ->count(5)
            ->create([
                'employee_id' => $user->id,
                'hours_worked' => 8,
                'status' => 'approved',
                'entry_date' => now(),
            ]);
        TimesheetEntry::factory()
            ->count(3)
            ->create([
                'employee_id' => $user->id,
                'hours_worked' => 4,
                'status' => 'submitted',
                'entry_date' => now(),
            ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/timesheets/metrics/employee/{$user->id}/month/".now()->format('Y-m'));

        $response->assertOk()
            ->assertJsonPath('total_entries', 8)
            ->assertJsonPath('total_hours', 40);
    }

    #[Test]
    public function can_get_project_metrics()
    {
        $user = User::factory()->create();
        $user->assignRole('employee');
        $project = TimeTrackingProject::factory()->create([
            'budget_hours' => 100,
        ]);

        TimesheetEntry::factory()
            ->count(5)
            ->create([
                'employee_id' => $user->id,
                'project_id' => $project->id,
                'hours_worked' => 8,
                'status' => 'approved',
            ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/timesheets/metrics/project/{$project->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'total_hours',
                'budget_hours',
                'remaining_hours',
                'percentage_used',
            ]);
    }

    #[Test]
    public function project_metrics_calculate_usage_percentage()
    {
        $user = User::factory()->create();
        $user->assignRole('employee');
        $project = TimeTrackingProject::factory()->create([
            'budget_hours' => 100,
        ]);

        TimesheetEntry::factory()
            ->count(5)
            ->create([
                'employee_id' => $user->id,
                'project_id' => $project->id,
                'hours_worked' => 10,
                'status' => 'approved',
            ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/timesheets/metrics/project/{$project->id}");

        $response->assertOk()
            ->assertJsonPath('percentage_used', 50);
    }

    #[Test]
    public function can_get_summary_metrics()
    {
        $user = User::factory()->create();
        $user->assignRole('employee');
        TimesheetEntry::factory()
            ->count(5)
            ->create([
                'employee_id' => $user->id,
                'hours_worked' => 8,
                'status' => 'approved',
            ]);
        TimesheetEntry::factory()
            ->count(2)
            ->create([
                'employee_id' => $user->id,
                'hours_worked' => 6,
                'status' => 'submitted',
            ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/timesheets/metrics/summary');

        $response->assertOk()
            ->assertJsonStructure([
                'total_entries',
                'total_hours',
                'billable_hours',
                'approved_entries',
                'pending_entries',
                'average_hours_per_entry',
            ]);
    }

    #[Test]
    public function summary_metrics_show_correct_counts()
    {
        $user = User::factory()->create();
        $user->assignRole('employee');
        TimesheetEntry::factory()->count(5)->create([
            'employee_id' => $user->id,
            'hours_worked' => 8,
            'status' => 'approved',
        ]);
        TimesheetEntry::factory()->count(3)->create([
            'employee_id' => $user->id,
            'hours_worked' => 6,
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/timesheets/metrics/summary');

        $response->assertOk()
            ->assertJsonPath('total_entries', 8)
            ->assertJsonPath('approved_entries', 5)
            ->assertJsonPath('pending_entries', 3)
            ->assertJsonPath('total_hours', 58);
    }

    #[Test]
    public function summary_metrics_can_filter_by_department()
    {
        $dept1 = Department::factory()->create();
        $dept2 = Department::factory()->create();

        $user1 = User::factory()->create(['department_id' => $dept1->id]);
        $user1->assignRole('employee');
        $user2 = User::factory()->create(['department_id' => $dept2->id]);
        $user2->assignRole('employee');

        TimesheetEntry::factory()->count(5)->create([
            'employee_id' => $user1->id,
            'hours_worked' => 8,
        ]);
        TimesheetEntry::factory()->count(3)->create([
            'employee_id' => $user2->id,
            'hours_worked' => 8,
        ]);

        $response = $this->actingAs($user1, 'sanctum')
            ->getJson("/api/v1/timesheets/metrics/summary?department_id={$dept1->id}");

        $response->assertOk()
            ->assertJsonPath('total_entries', 5);
    }

    #[Test]
    public function summary_metrics_can_filter_by_date_range()
    {
        $user = User::factory()->create();
        $user->assignRole('employee');
        TimesheetEntry::factory()->create([
            'employee_id' => $user->id,
            'entry_date' => now()->subDays(10),
            'hours_worked' => 8,
        ]);
        TimesheetEntry::factory()->create([
            'employee_id' => $user->id,
            'entry_date' => now(),
            'hours_worked' => 8,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/timesheets/metrics/summary?from_date='.now()->subDays(5)->format('Y-m-d'));

        $response->assertOk()
            ->assertJsonPath('total_entries', 1);
    }

    #[Test]
    public function average_hours_per_entry_calculates_correctly()
    {
        $user = User::factory()->create();
        $user->assignRole('employee');
        TimesheetEntry::factory()->count(4)->create([
            'employee_id' => $user->id,
            'hours_worked' => 8,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/timesheets/metrics/summary');

        $response->assertOk()
            ->assertJsonPath('average_hours_per_entry', 8);
    }
}
