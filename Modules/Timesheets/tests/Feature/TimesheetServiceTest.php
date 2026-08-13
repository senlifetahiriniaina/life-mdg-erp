<?php

namespace Modules\Timesheets\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Timesheets\Models\TimeAllocation;
use Modules\Timesheets\Models\TimesheetEntry;
use Modules\Timesheets\Models\TimeTrackingProject;
use Modules\Timesheets\Services\TimesheetService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TimesheetServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TimesheetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // $this->seed(); // Removed: too slow for unit tests
        $this->service = app(TimesheetService::class);
    }

    #[Test]
    public function can_create_timesheet_entry()
    {
        $user = User::factory()->create();

        $entry = $this->service->createEntry(
            employee_id: $user->id,
            entry_date: now()->subDay(),
            hours_worked: 8,
            description: 'Development work',
            task_id: null,
            notes: 'Completed features'
        );

        $this->assertEquals('draft', $entry->status);
        $this->assertEquals(8, $entry->hours_worked);
        $this->assertDatabaseHas('timesheet_entries', [
            'id' => $entry->id,
            'status' => 'draft',
        ]);
    }

    #[Test]
    public function can_update_timesheet_entry()
    {
        $entry = TimesheetEntry::factory()->create(['status' => 'draft']);

        $updated = $this->service->updateEntry(
            entry: $entry,
            hours_worked: 9,
            description: 'Updated',
            task_id: null,
            notes: 'Updated notes'
        );

        $this->assertEquals(9, $updated->hours_worked);
        $this->assertEquals('Updated', $updated->description);
    }

    #[Test]
    public function can_submit_timesheet_entry()
    {
        $entry = TimesheetEntry::factory()->create(['status' => 'draft']);

        $submitted = $this->service->submitEntry($entry);

        $this->assertEquals('submitted', $submitted->status);
        $this->assertNotNull($submitted->submitted_at);
    }

    #[Test]
    public function can_approve_timesheet_entry()
    {
        $approver = User::factory()->create();
        $entry = TimesheetEntry::factory()->create(['status' => 'submitted']);

        $approved = $this->service->approveEntry(
            entry: $entry,
            approved_by: $approver->id,
            notes: 'Looks good'
        );

        $this->assertEquals('approved', $approved->status);
        $this->assertNotNull($approved->approved_at);
        $this->assertEquals($approver->id, $approved->approved_by);
    }

    #[Test]
    public function can_reject_timesheet_entry()
    {
        $rejector = User::factory()->create();
        $entry = TimesheetEntry::factory()->create(['status' => 'submitted']);

        $rejected = $this->service->rejectEntry(
            entry: $entry,
            rejected_by: $rejector->id,
            notes: 'Missing details'
        );

        $this->assertEquals('rejected', $rejected->status);
        $this->assertEquals($rejector->id, $rejected->approved_by);
    }

    #[Test]
    public function can_allocate_time_across_projects()
    {
        $entry = TimesheetEntry::factory()->create(['hours_worked' => 8]);
        $project1 = TimeTrackingProject::factory()->create();
        $project2 = TimeTrackingProject::factory()->create();

        $allocations = $this->service->allocateTime(
            entry_id: $entry->id,
            allocations: [
                [
                    'project_id' => $project1->id,
                    'hours' => 5,
                    'hourly_rate' => 50,
                    'is_billable' => true,
                ],
                [
                    'project_id' => $project2->id,
                    'hours' => 3,
                    'hourly_rate' => 50,
                    'is_billable' => false,
                ],
            ]
        );

        $this->assertCount(2, $allocations);
        $this->assertEquals(2, TimeAllocation::where('entry_id', $entry->id)->count());
    }

    #[Test]
    public function allocation_hours_must_sum_to_entry_hours()
    {
        $entry = TimesheetEntry::factory()->create(['hours_worked' => 8]);
        $project = TimeTrackingProject::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->service->allocateTime(
            entry_id: $entry->id,
            allocations: [
                [
                    'project_id' => $project->id,
                    'hours' => 10,
                    'hourly_rate' => 50,
                    'is_billable' => true,
                ],
            ]
        );
    }

    #[Test]
    public function can_create_tracking_project()
    {
        $project = $this->service->createTrackingProject(
            name: 'Website Redesign',
            code: 'REDESIGN-001',
            description: 'Complete website overhaul',
            budget_hours: 500,
            department_id: null,
            start_date: now(),
            end_date: now()->addMonths(6)
        );

        $this->assertEquals('active', $project->status);
        $this->assertEquals(500, $project->budget_hours);
        $this->assertDatabaseHas('time_tracking_projects', [
            'code' => 'REDESIGN-001',
        ]);
    }

    #[Test]
    public function can_get_employee_timesheets()
    {
        $user = User::factory()->create();
        TimesheetEntry::factory()->count(5)->create(['employee_id' => $user->id]);

        $entries = $this->service->getEmployeeTimesheets(
            employee_id: $user->id,
            from_date: null,
            to_date: null
        );

        $this->assertCount(5, $entries);
    }

    #[Test]
    public function can_filter_employee_timesheets_by_date()
    {
        $user = User::factory()->create();
        TimesheetEntry::factory()->create([
            'employee_id' => $user->id,
            'entry_date' => now()->subDays(10),
        ]);
        TimesheetEntry::factory()->create([
            'employee_id' => $user->id,
            'entry_date' => now(),
        ]);

        $entries = $this->service->getEmployeeTimesheets(
            employee_id: $user->id,
            from_date: now()->subDays(5)->format('Y-m-d'),
            to_date: null
        );

        $this->assertCount(1, $entries);
    }

    #[Test]
    public function can_get_pending_approvals()
    {
        TimesheetEntry::factory()->count(5)->create(['status' => 'submitted']);
        TimesheetEntry::factory()->count(2)->create(['status' => 'approved']);

        $pending = $this->service->getPendingApprovals(
            department_id: null,
            limit: 50
        );

        $this->assertCount(5, $pending);
    }

    #[Test]
    public function can_get_timesheet_metrics()
    {
        $user = User::factory()->create();
        TimesheetEntry::factory()->count(5)->create([
            'employee_id' => $user->id,
            'hours_worked' => 8,
            'status' => 'approved',
        ]);

        $metrics = $this->service->getTimesheetMetrics(
            employee_id: $user->id,
            from_date: null,
            to_date: null
        );

        $this->assertEquals(5, $metrics['total_entries']);
        $this->assertEquals(40, $metrics['total_hours']);
    }

    #[Test]
    public function can_get_project_metrics()
    {
        $project = TimeTrackingProject::factory()->create(['budget_hours' => 100]);
        TimesheetEntry::factory()->count(3)->create([
            'project_id' => $project->id,
            'hours_worked' => 8,
            'status' => 'approved',
        ]);

        $metrics = $this->service->getProjectMetrics($project->id);

        $this->assertEquals(24, $metrics['total_hours']);
        $this->assertEquals(100, $metrics['budget_hours']);
        $this->assertEquals(76, $metrics['remaining_hours']);
    }

    #[Test]
    public function can_get_project_timesheets()
    {
        $project = TimeTrackingProject::factory()->create();
        TimesheetEntry::factory()->count(5)->create(['project_id' => $project->id]);

        $entries = $this->service->getProjectTimesheets(
            project_id: $project->id,
            from_date: null,
            to_date: null
        );

        $this->assertCount(5, $entries);
    }
}
