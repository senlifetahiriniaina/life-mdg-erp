<?php

namespace Modules\Timesheets\Tests\Feature;

use App\Models\User;
use Modules\HR\Models\Department;
use Modules\HR\Models\Employee;
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
        // Chantier 19 (Lot 2): this test used to compare users.department_id
        // against TimesheetEntry.employee_id set directly to a users.id —
        // the exact same ID-space mismatch bug (hr_employees.id vs
        // users.id) already fixed elsewhere in this app, and the real bug
        // MetricsController::summary()'s department filter had (it queried
        // \App\Models\User::where('department_id', ...)->pluck('id') and
        // compared those against employee_id, which is an hr_employees.id
        // — never a real match). Fixed to build a real, linked Employee
        // per user, with department_id on the Employee record (the real
        // HR model), matching how production data actually looks.
        $dept1 = Department::factory()->create();
        $dept2 = Department::factory()->create();

        $user1 = User::factory()->create();
        $user1->assignRole('employee');
        $employee1 = Employee::factory()->create(['user_id' => $user1->id, 'department_id' => $dept1->id]);

        $user2 = User::factory()->create();
        $user2->assignRole('employee');
        $employee2 = Employee::factory()->create(['user_id' => $user2->id, 'department_id' => $dept2->id]);

        TimesheetEntry::factory()->count(5)->create([
            'employee_id' => $employee1->id,
            'hours_worked' => 8,
        ]);
        TimesheetEntry::factory()->count(3)->create([
            'employee_id' => $employee2->id,
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
