<?php

namespace Modules\Projects\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Projects\Models\{Project, Task, TimeEntry};
use Modules\Projects\Services\ProjectService;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    private ProjectService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProjectService();
    }

    // Task Management (4 tests)
    public function test_create_project_task(): void
    {
        $project = Project::factory()->create();
        $task = $this->service->createTask($project, 'Design homepage', [
            'priority' => 'high',
            'due_date' => now()->addDays(7)
        ]);

        $this->assertNotNull($task->id);
    }

    public function test_assign_task_to_team_member(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id]);
        $member = $this->actingAsUser();

        $assigned = $this->service->assignTask($task, $member);
        $this->assertEquals($member->id, $assigned->assigned_to);
    }

    public function test_update_task_status(): void
    {
        $task = Task::factory()->create(['status' => 'todo']);

        $updated = $this->service->updateTaskStatus($task, 'in_progress');
        $this->assertEquals('in_progress', $updated->status);
    }

    public function test_task_dependency_enforcement(): void
    {
        $project = Project::factory()->create();
        $task1 = Task::factory()->create(['project_id' => $project->id, 'status' => 'todo']);
        $task2 = Task::factory()->create(['project_id' => $project->id]);

        $task2->addDependency($task1);

        $canStart = $this->service->canStartTask($task2);
        $this->assertFalse($canStart); // Can't start, dependency not complete
    }

    // Time Tracking (3 tests)
    public function test_log_time_entry(): void
    {
        $task = Task::factory()->create();
        $user = $this->actingAsUser();

        $entry = $this->service->logTimeEntry($task, $user, [
            'hours' => 2.5,
            'description' => 'Development work'
        ]);

        $this->assertEquals(2.5, $entry->hours);
    }

    public function test_calculate_task_actual_hours(): void
    {
        $task = Task::factory()->create();

        TimeEntry::factory()->create(['task_id' => $task->id, 'hours' => 2]);
        TimeEntry::factory()->create(['task_id' => $task->id, 'hours' => 3]);

        $total = $this->service->getTotalHours($task);
        $this->assertEquals(5, $total);
    }

    public function test_track_time_against_budget(): void
    {
        $task = Task::factory()->create(['estimated_hours' => 10]);

        TimeEntry::factory()->count(5)->create(['task_id' => $task->id, 'hours' => 2]); // 10 hours logged

        $remaining = $this->service->getRemainingBudget($task);
        $this->assertEquals(0, $remaining);
    }
}
