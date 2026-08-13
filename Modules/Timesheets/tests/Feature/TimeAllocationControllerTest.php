<?php

namespace Modules\Timesheets\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Timesheets\Models\TimeAllocation;
use Modules\Timesheets\Models\TimesheetEntry;
use Modules\Timesheets\Models\TimeTrackingProject;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TimeAllocationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // $this->seed(); // Removed: too slow for unit tests
    }

    #[Test]
    public function can_list_time_allocations()
    {
        $user = User::factory()->create();
        $entry = TimesheetEntry::factory()->create(['employee_id' => $user->id]);
        TimeAllocation::factory()->count(3)->create(['entry_id' => $entry->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/timesheets/allocations');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function can_allocate_time_to_projects()
    {
        $user = User::factory()->create();
        $entry = TimesheetEntry::factory()->create([
            'employee_id' => $user->id,
            'hours_worked' => 8,
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
    public function allocation_hours_must_match_entry_hours()
    {
        $user = User::factory()->create();
        $entry = TimesheetEntry::factory()->create([
            'employee_id' => $user->id,
            'hours_worked' => 8,
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
        $user = User::factory()->create();
        $entry = TimesheetEntry::factory()->create(['employee_id' => $user->id]);
        $allocation = TimeAllocation::factory()->create(['entry_id' => $entry->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/timesheets/allocations/{$allocation->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $allocation->id);
    }

    #[Test]
    public function can_update_allocation()
    {
        $user = User::factory()->create();
        $entry = TimesheetEntry::factory()->create(['employee_id' => $user->id]);
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
    public function can_delete_allocation()
    {
        $user = User::factory()->create();
        $entry = TimesheetEntry::factory()->create(['employee_id' => $user->id]);
        $allocation = TimeAllocation::factory()->create(['entry_id' => $entry->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/timesheets/allocations/{$allocation->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('time_allocations', ['id' => $allocation->id]);
    }

    #[Test]
    public function can_filter_allocations_by_billable_status()
    {
        $user = User::factory()->create();
        $entry = TimesheetEntry::factory()->create(['employee_id' => $user->id]);
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
        $user = User::factory()->create();
        $entry = TimesheetEntry::factory()->create([
            'employee_id' => $user->id,
            'hours_worked' => 8,
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

    #[Test]
    public function can_get_allocations_by_project()
    {
        $user = User::factory()->create();
        $project = TimeTrackingProject::factory()->create();
        $otherProject = TimeTrackingProject::factory()->create();

        $entry1 = TimesheetEntry::factory()->create(['employee_id' => $user->id]);
        $entry2 = TimesheetEntry::factory()->create(['employee_id' => $user->id]);

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
