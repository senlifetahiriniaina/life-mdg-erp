<?php

declare(strict_types=1);

use Carbon\Carbon;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\Task;
use Modules\Projects\Models\TaskDependency;
use Modules\Projects\Services\GanttService;


test('can fetch gantt data for a project', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Task::factory()->count(3)->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'start_date' => now()->toDateString(),
        'due_date' => now()->addDays(5)->toDateString(),
    ]);

    $this
        ->getJson("/api/v1/projects/{$project->id}/gantt")
        ->assertOk()
        ->assertJsonStructure([
            'tasks',
            'dependencies',
            'critical_path',
        ]);
});

test('can add a task dependency', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $taskA = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $taskB = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $this
        ->postJson("/api/v1/projects/tasks/{$taskB->id}/dependencies", [
            'depends_on_task_id' => $taskA->id,
            'type' => 'FS',
            'lag_days' => 0,
        ])
        ->assertStatus(201)
        ->assertJsonPath('task_id', $taskB->id)
        ->assertJsonPath('depends_on_task_id', $taskA->id);

    $this->assertDatabaseHas('project_task_dependencies', [
        'task_id' => $taskB->id,
        'depends_on_task_id' => $taskA->id,
        'type' => 'FS',
    ]);
});

test('can remove a task dependency', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $taskA = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $taskB = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $dep = TaskDependency::create([
        'task_id' => $taskB->id,
        'depends_on_task_id' => $taskA->id,
        'type' => 'FS',
        'lag_days' => 0,
    ]);

    $this
        ->deleteJson("/api/v1/projects/tasks/{$taskB->id}/dependencies/{$dep->id}")
        ->assertStatus(204);

    $this->assertDatabaseMissing('project_task_dependencies', ['id' => $dep->id]);
});

test('can update task dates via gantt', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'start_date' => '2026-05-01',
        'due_date' => '2026-05-10',
    ]);

    $this
        ->patchJson("/api/v1/projects/tasks/{$task->id}/gantt", [
            'start_date' => '2026-05-05',
            'due_date' => '2026-05-15',
        ])
        ->assertOk()
        ->assertJsonPath('start_date', '2026-05-05')
        ->assertJsonPath('due_date', '2026-05-15');

    $this->assertDatabaseHas('prj_tasks', [
        'id' => $task->id,
        'start_date' => '2026-05-05',
        'due_date' => '2026-05-15',
    ]);
});

test('critical path is detected correctly', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $user->id]);

    // Create 3 tasks A -> B -> C (sequential chain)
    $taskA = Task::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'start_date' => Carbon::now()->toDateString(),
        'due_date' => Carbon::now()->addDays(5)->toDateString(),
        'estimated_hours' => 40,
    ]);
    $taskB = Task::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'start_date' => Carbon::now()->addDays(6)->toDateString(),
        'due_date' => Carbon::now()->addDays(10)->toDateString(),
        'estimated_hours' => 32,
    ]);
    $taskC = Task::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'start_date' => Carbon::now()->addDays(11)->toDateString(),
        'due_date' => Carbon::now()->addDays(15)->toDateString(),
        'estimated_hours' => 40,
    ]);

    TaskDependency::create(['task_id' => $taskB->id, 'depends_on_task_id' => $taskA->id, 'type' => 'FS', 'lag_days' => 0]);
    TaskDependency::create(['task_id' => $taskC->id, 'depends_on_task_id' => $taskB->id, 'type' => 'FS', 'lag_days' => 0]);

    $service = new GanttService;
    $critical = $service->getCriticalPath($project);

    // All 3 tasks on sequential chain should be critical
    expect($critical)->toContain($taskA->id)
        ->toContain($taskB->id)
        ->toContain($taskC->id);
});

test('prevents duplicate dependencies', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $taskA = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $taskB = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    // Create first dependency
    TaskDependency::create([
        'task_id' => $taskB->id,
        'depends_on_task_id' => $taskA->id,
        'type' => 'FS',
        'lag_days' => 0,
    ]);

    // Second request to same pair should return 201 (firstOrCreate) not create a duplicate
    $this
        ->postJson("/api/v1/projects/tasks/{$taskB->id}/dependencies", [
            'depends_on_task_id' => $taskA->id,
            'type' => 'FS',
        ])
        ->assertStatus(201);

    $count = TaskDependency::where('task_id', $taskB->id)
        ->where('depends_on_task_id', $taskA->id)
        ->count();

    expect($count)->toBe(1);
});
