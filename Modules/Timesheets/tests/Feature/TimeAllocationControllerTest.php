<?php

namespace Modules\Timesheets\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Timesheets\Models\TimeAllocation;
use Modules\Timesheets\Models\TimesheetEntry;
use Modules\Timesheets\Models\TimeTrackingProject;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Chantier 32.19 (Timesheets deep 14-layer audit): this file assigned
 * $user->id directly as TimesheetEntry.employee_id — a users.id, not the
 * hr_employees.id this column actually FKs to — the same ID-space mismatch
 * bug pattern already fixed for the sibling TimesheetEntryControllerTest.
 * It kept passing only because TimeAllocationController had zero
 * authorize()/ownership check of any kind before this chantier. Now that
 * real ownership enforcement is wired in (via the allocation's linked
 * TimesheetEntry + TimesheetEntryPolicy), every entry needs a real, linked
 * Employee record.
 */
class TimeAllocationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
    }

    /** @return array{0: User, 1: Employee} */
    private function employeeUser(string $role = 'employee'): array
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        return [$user, $employee];
    }

    #[Test]
    public function can_list_time_allocations()
    {
        [$user, $employee] = $this->employeeUser();
        $entry = TimesheetEntry::factory()->create(['employee_id' => $employee->id]);
        TimeAllocation::factory()->count(3)->create(['entry_id' => $entry->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/timesheets/allocations');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function cannot_list_another_employees_allocations()
    {
        [$user, $employee] = $this->employeeUser();
        [, $otherEmployee] = $this->employeeUser();
        $ownEntry = TimesheetEntry::factory()->create(['employee_id' => $employee->id]);
        $otherEntry = TimesheetEntry::factory()->create(['employee_id' => $otherEmployee->id]);
        TimeAllocation::factory()->count(2)->create(['entry_id' => $ownEntry->id]);
        TimeAllocation::factory()->count(3)->create(['entry_id' => $otherEntry->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/timesheets/allocations');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function manager_can_list_every_employees_allocations()
    {
        [$manager] = $this->employeeUser('manager');
        [, $employee1] = $this->employeeUser();
        [, $employee2] = $this->employeeUser();
        $entry1 = TimesheetEntry::factory()->create(['employee_id' => $employee1->id]);
        $entry2 = TimesheetEntry::factory()->create(['employee_id' => $employee2->id]);
        TimeAllocation::factory()->count(2)->create(['entry_id' => $entry1->id]);
        TimeAllocation::factory()->count(3)->create(['entry_id' => $entry2->id]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/timesheets/allocations');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    #[Test]
    public function can_allocate_time_to_projects()
    {
        [$user, $employee] = $this->employeeUser();
        $entry = TimesheetEntry::factory()->create([
            'employee_id' => $employee->id,
            'hours_worked' => 8,
            'status' => 'draft',
        ]);
        $project = TimeTrackingProject::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/timesheets/allocations', [
                'entry_id' => $entry->id,
                'allocations' => [
                    [
                        'project_id' => $project->id,
                        'hours' => 5,
                        'hourly_rate' => 50,
                        'is_billable' => true,
                    ],
                    [
                        'project_id' => $project->id,
                        'hours' => 3,
                        'hourly_rate' => 50,
                        'is_billable' => false,
                    ],
                ],
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('time_allocations', [
            'entry_id' => $entry->id,
            'project_id' => $project->id,
        ]);
    }

    #[Test]
    public function cannot_allocate_time_on_another_employees_entry()
    {
        [$user] = $this->employeeUser();
        [, $otherEmployee] = $this->employeeUser();
        $entry = TimesheetEntry::factory()->create([
            'employee_id' => $otherEmployee->id,
            'hours_worked' => 8,
        ]);
        $project = TimeTrackingProject::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/timesheets/allocations', [
                'entry_id' => $entry->id,
                'allocations' => [
                    ['project_id' => $project->id, 'hours' => 8, 'hourly_rate' => 50, 'is_billable' => true],
                ],
            ]);

        $response->assertForbidden();
    }

    /**
     * Chantier 32.19: task_id/cost_center_id used to validate against
     * "tasks"/"cost_centers", two tables that have never existed in this
     * app — a real request supplying either fatalled with a raw SQL
     * QueryException instead of a 422, confirmed empirically before the
     * fix. task_id now validates against the real prj_tasks table.
     */
    #[Test]
    public function allocation_task_id_validates_against_the_real_projects_table()
    {
        [$user, $employee] = $this->employeeUser();
        $entry = TimesheetEntry::factory()->create([
            'employee_id' => $employee->id,
            'hours_worked' => 8,
            'status' => 'draft',
        ]);
        $project = TimeTrackingProject::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/timesheets/allocations', [
                'entry_id' => $entry->id,
                'allocations' => [
                    [
                        'project_id' => $project->id,
                        'task_id' => 999999,
                        'hours' => 8,
                        'hourly_rate' => 50,
                        'is_billable' => true,
                    ],
                ],
            ]);

        // No fatal 500 (the table exists and is queried correctly), and a
        // nonexistent task id is correctly rejected as a validation error.
        $response->assertUnprocessable()
            ->assertJsonValidationErrors('allocations.0.task_id');
    }

    #[Test]
    public function allocation_hours_must_match_entry_hours()
    {
        [$user, $employee] = $this->employeeUser();
        $entry = TimesheetEntry::factory()->create([
            'employee_id' => $employee->id,
            'hours_worked' => 8,
            'status' => 'draft',
        ]);
        $project = TimeTrackingProject::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/timesheets/allocations', [
                'entry_id' => $entry->id,
                'allocations' => [
                    [
                        'project_id' => $project->id,
                        'hours' => 10,
                        'hourly_rate' => 50,
                        'is_billable' => true,
                    ],
                ],
            ]);

        $response->assertUnprocessable();
    }

    #[Test]
    public function can_retrieve_specific_allocation()
    {
        [$user, $employee] = $this->employeeUser();
        $entry = TimesheetEntry::factory()->create(['employee_id' => $employee->id]);
        $allocation = TimeAllocation::factory()->create(['entry_id' => $entry->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/timesheets/allocations/{$allocation->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $allocation->id);
    }

    #[Test]
    public function cannot_retrieve_another_employees_allocation()
    {
        [$user] = $this->employeeUser();
        [, $otherEmployee] = $this->employeeUser();
        $entry = TimesheetEntry::factory()->create(['employee_id' => $otherEmployee->id]);
        $allocation = TimeAllocation::factory()->create(['entry_id' => $entry->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/timesheets/allocations/{$allocation->id}");

        $response->assertForbidden();
    }

    #[Test]
    public function can_update_allocation()
    {
        [$user, $employee] = $this->employeeUser();
        $entry = TimesheetEntry::factory()->create(['employee_id' => $employee->id, 'status' => 'draft']);
        $allocation = TimeAllocation::factory()->create([
            'entry_id' => $entry->id,
            'hours' => 5,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/timesheets/allocations/{$allocation->id}", [
                'hours' => 6,
                'hourly_rate' => 75,
            ]);

        $response->assertOk();

        $allocation->refresh();
        $this->assertEquals(6, $allocation->hours);
        $this->assertEquals(75, $allocation->hourly_rate);
    }

    #[Test]
    public function cannot_update_another_employees_allocation()
    {
        [$user] = $this->employeeUser();
        [, $otherEmployee] = $this->employeeUser();
        $entry = TimesheetEntry::factory()->create(['employee_id' => $otherEmployee->id]);
        $allocation = TimeAllocation::factory()->create(['entry_id' => $entry->id, 'hours' => 5]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/timesheets/allocations/{$allocation->id}", ['hours' => 6]);

        $response->assertForbidden();
        $this->assertEquals(5, $allocation->fresh()->hours);
    }

    #[Test]
    public function can_delete_allocation()
    {
        [$user, $employee] = $this->employeeUser();
        $entry = TimesheetEntry::factory()->create(['employee_id' => $employee->id, 'status' => 'draft']);
        $allocation = TimeAllocation::factory()->create(['entry_id' => $entry->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/timesheets/allocations/{$allocation->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('time_allocations', ['id' => $allocation->id]);
    }

    #[Test]
    public function can_filter_allocations_by_billable_status()
    {
        [$user, $employee] = $this->employeeUser();
        $entry = TimesheetEntry::factory()->create(['employee_id' => $employee->id]);
        TimeAllocation::factory()->count(3)->create([
            'entry_id' => $entry->id,
            'is_billable' => true,
        ]);
        TimeAllocation::factory()->count(2)->create([
            'entry_id' => $entry->id,
            'is_billable' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/timesheets/allocations?billable=1');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function can_allocate_via_entry_endpoint()
    {
        [$user, $employee] = $this->employeeUser();
        $entry = TimesheetEntry::factory()->create([
            'employee_id' => $employee->id,
            'hours_worked' => 8,
            'status' => 'draft',
        ]);
        $project = TimeTrackingProject::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/timesheets/entries/{$entry->id}/allocate", [
                'allocations' => [
                    [
                        'project_id' => $project->id,
                        'hours' => 8,
                        'hourly_rate' => 50,
                        'is_billable' => true,
                    ],
                ],
            ]);

        $response->assertCreated();
    }

    /**
     * Chantier 32.19: the entry endpoint had zero request validation of any
     * kind — an omitted `allocations` key threw a raw TypeError (500)
     * instead of a clean 422, confirmed empirically before the fix.
     */
    #[Test]
    public function allocate_via_entry_endpoint_without_allocations_is_a_validation_error_not_a_crash()
    {
        [$user, $employee] = $this->employeeUser();
        $entry = TimesheetEntry::factory()->create([
            'employee_id' => $employee->id,
            'hours_worked' => 8,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/timesheets/entries/{$entry->id}/allocate", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('allocations');
    }

    #[Test]
    public function cannot_allocate_via_entry_endpoint_on_another_employees_entry()
    {
        [$user] = $this->employeeUser();
        [, $otherEmployee] = $this->employeeUser();
        $entry = TimesheetEntry::factory()->create([
            'employee_id' => $otherEmployee->id,
            'hours_worked' => 8,
        ]);
        $project = TimeTrackingProject::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/timesheets/entries/{$entry->id}/allocate", [
                'allocations' => [
                    ['project_id' => $project->id, 'hours' => 8, 'hourly_rate' => 50, 'is_billable' => true],
                ],
            ]);

        $response->assertForbidden();
    }

    #[Test]
    public function can_get_allocations_by_project()
    {
        [$user, $employee] = $this->employeeUser();
        $project = TimeTrackingProject::factory()->create();
        $otherProject = TimeTrackingProject::factory()->create();

        $entry1 = TimesheetEntry::factory()->create(['employee_id' => $employee->id]);
        $entry2 = TimesheetEntry::factory()->create(['employee_id' => $employee->id]);

        TimeAllocation::factory()->create([
            'entry_id' => $entry1->id,
            'project_id' => $project->id,
        ]);
        TimeAllocation::factory()->create([
            'entry_id' => $entry2->id,
            'project_id' => $otherProject->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/timesheets/allocations/project/{$project->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
