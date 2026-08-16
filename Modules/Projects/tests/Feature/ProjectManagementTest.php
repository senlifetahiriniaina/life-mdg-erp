<?php

namespace Modules\Projects\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Projects\Models\{Project, Task, TaskDependency, TimeEntry};

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_project_task(): void
    {
        $project = Project::factory()->create();
        $task = Task::create([
            'project_id' => $project->id,
            'title' => 'Design homepage',
            'priority' => 'high',
            'due_date' => now()->addDays(7),
        ]);

        $this->assertNotNull($task->id);
    }

    public function test_assign_task_to_team_member(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id]);
        $member = $this->actingAsUser();

        $task->update(['assignee_id' => $member->id]);

        $this->assertEquals($member->id, $task->fresh()->assignee_id);
    }

    public function test_update_task_status(): void
    {
        $task = Task::factory()->create(['status' => 'todo']);
        $task->update(['status' => 'in_progress']);

        $this->assertEquals('in_progress', $task->fresh()->status);
    }

    public function test_task_dependency_enforcement(): void
    {
        $project = Project::factory()->create();
        $task1 = Task::factory()->create(['project_id' => $project->id, 'status' => 'todo']);
        $task2 = Task::factory()->create(['project_id' => $project->id]);

        TaskDependency::create(['task_id' => $task2->id, 'depends_on_task_id' => $task1->id]);

        $canStart = $task1->fresh()->status === 'done';
        $this->assertFalse($canStart); // Can't start, dependency not complete
    }

    public function test_log_time_entry(): void
    {
        $task = Task::factory()->create();
        $user = $this->actingAsUser();

        $entry = TimeEntry::create([
            'project_id' => $task->project_id,
            'task_id' => $task->id,
            'user_id' => $user->id,
            'description' => 'Development work',
            'started_at' => now(),
            'ended_at' => now()->addMinutes(150),
            'duration_minutes' => 150,
        ]);

        $this->assertEquals(2.5, $entry->duration_minutes / 60);
    }

    public function test_calculate_task_actual_hours(): void
    {
        $task = Task::factory()->create();

        TimeEntry::factory()->create(['task_id' => $task->id, 'project_id' => $task->project_id, 'duration_minutes' => 120]);
        TimeEntry::factory()->create(['task_id' => $task->id, 'project_id' => $task->project_id, 'duration_minutes' => 180]);

        $totalHours = TimeEntry::where('task_id', $task->id)->sum('duration_minutes') / 60;
        $this->assertEquals(5, $totalHours);
    }

    public function test_track_time_against_budget(): void
    {
        $task = Task::factory()->create(['estimated_hours' => 10]);

        TimeEntry::factory()->count(5)->create(['task_id' => $task->id, 'project_id' => $task->project_id, 'duration_minutes' => 120]);

        $totalHours = TimeEntry::where('task_id', $task->id)->sum('duration_minutes') / 60;
        $remaining = max(0, $task->estimated_hours - $totalHours);

        $this->assertEquals(0, $remaining);
    }
}
