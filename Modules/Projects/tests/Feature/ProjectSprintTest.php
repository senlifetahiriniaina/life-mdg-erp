<?php

declare(strict_types=1);

use Modules\Projects\Models\Project;
use Modules\Projects\Models\Sprint;
use Modules\Projects\Models\Task;


it('can create and list sprints', function (): void {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();
    Sprint::factory()->count(2)->create(['project_id' => $project->id]);

    $this
        ->postJson("/api/v1/projects/{$project->id}/sprints", [
            'name' => 'Sprint 3',
            'goal' => 'Deliver login feature',
            'capacity_points' => 40,
        ])
        ->assertStatus(201)
        ->assertJsonPath('name', 'Sprint 3');

    $this
        ->getJson("/api/v1/projects/{$project->id}/sprints")
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('starts a sprint', function (): void {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();
    $sprint = Sprint::factory()->create(['project_id' => $project->id, 'status' => 'planning']);

    $this
        ->postJson("/api/v1/projects/{$project->id}/sprints/{$sprint->id}/start")
        ->assertOk()
        ->assertJsonPath('status', 'active');
});

it('completes a sprint and returns incomplete tasks', function (): void {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();
    $sprint = Sprint::factory()->create([
        'project_id' => $project->id,
        'status' => 'active',
    ]);

    Task::factory()->count(2)->create([
        'project_id' => $project->id,
        'sprint_id' => $sprint->id,
        'status' => 'in_progress',
    ]);
    Task::factory()->create([
        'project_id' => $project->id,
        'sprint_id' => $sprint->id,
        'status' => 'done',
    ]);

    $this
        ->postJson("/api/v1/projects/{$project->id}/sprints/{$sprint->id}/complete")
        ->assertOk()
        ->assertJsonPath('sprint.status', 'completed')
        ->assertJsonCount(2, 'incomplete_tasks');
});

it('returns burndown data for a sprint', function (): void {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();
    $sprint = Sprint::factory()->create([
        'project_id' => $project->id,
        'status' => 'active',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-14',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'sprint_id' => $sprint->id,
        'status' => 'done',
        'story_points' => 5,
        'completed_at' => '2026-05-07 10:00:00',
    ]);

    $response = $this
        ->getJson("/api/v1/projects/{$project->id}/sprints/{$sprint->id}/burndown")
        ->assertOk();

    $response->assertJsonStructure(['sprint_id', 'total_points', 'ideal', 'actual']);
});

it('returns velocity data for a project', function (): void {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();

    Sprint::factory()->count(3)->create([
        'project_id' => $project->id,
        'status' => 'completed',
        'end_date' => now()->subDays(7)->toDateString(),
    ]);

    $this
        ->getJson("/api/v1/projects/{$project->id}/velocity")
        ->assertOk()
        ->assertJsonStructure(['project_id', 'average_velocity', 'velocities']);
});

it('returns backlog tasks (tasks without sprint)', function (): void {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();
    $sprint = Sprint::factory()->create(['project_id' => $project->id]);

    // 3 backlog tasks
    Task::factory()->count(3)->create(['project_id' => $project->id, 'sprint_id' => null]);
    // 2 sprint tasks (not backlog)
    Task::factory()->count(2)->create(['project_id' => $project->id, 'sprint_id' => $sprint->id]);

    $this
        ->getJson("/api/v1/projects/{$project->id}/backlog")
        ->assertOk()
        ->assertJsonCount(3, 'data');
});
