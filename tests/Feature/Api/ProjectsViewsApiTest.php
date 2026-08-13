<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\Task;

uses(RefreshDatabase::class);

// ── Auth ──────────────────────────────────────────────────────────────────────

test('kanban requires authentication', function () {
    $project = Project::factory()->create();
    $this->getJson("/api/v1/projects/{$project->id}/views/kanban")->assertUnauthorized();
});

test('calendar requires authentication', function () {
    $project = Project::factory()->create();
    $this->getJson("/api/v1/projects/{$project->id}/views/calendar")->assertUnauthorized();
});

test('gantt requires authentication', function () {
    $project = Project::factory()->create();
    $this->getJson("/api/v1/projects/{$project->id}/views/gantt")->assertUnauthorized();
});

// ── Kanban ────────────────────────────────────────────────────────────────────

test('kanban returns columns grouped by status', function () {
     $user = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $user->id]);
    Task::factory()->count(2)->create(['project_id' => $project->id, 'status' => 'todo', 'created_by' => $user->id]);
    Task::factory()->create(['project_id' => $project->id, 'status' => 'in_progress', 'created_by' => $user->id]);
        $response = $this
        ->getJson("/api/v1/projects/{$project->id}/views/kanban")
        ->assertOk()
        ->assertJsonStructure(['project', 'columns'])
        ->assertJsonCount(5, 'columns');
});

test('kanban todo column contains correct task count', function () {
     $user = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $user->id]);
    Task::factory()->count(3)->create(['project_id' => $project->id, 'status' => 'todo', 'created_by' => $user->id]);
        $response = $this
        ->getJson("/api/v1/projects/{$project->id}/views/kanban")
        ->assertOk()
        ->json();

    $todoCol = collect($response['columns'])->firstWhere('status', 'todo');
    expect($todoCol['count'])->toBe(3);
    expect($todoCol['tasks'])->toHaveCount(3);
});

test('kanban excludes subtasks (parent_id not null) from top-level columns', function () {
     $user = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $parent  = Task::factory()->create(['project_id' => $project->id, 'status' => 'todo', 'created_by' => $user->id]);
    Task::factory()->create([
        'project_id' => $project->id, 'status' => 'todo',
        'parent_id'  => $parent->id, 'created_by' => $user->id,
    ]);
        $response = $this
        ->getJson("/api/v1/projects/{$project->id}/views/kanban")
        ->assertOk()
        ->json();

    $todoCol = collect($response['columns'])->firstWhere('status', 'todo');
    expect($todoCol['count'])->toBe(1);
});

test('kanban returns 404 for non-existent project', function () {
     $user = actingAsUser('employee');
                $response = $this
        ->getJson('/api/v1/projects/99999/views/kanban')
        ->assertNotFound();
});

// ── Calendar ──────────────────────────────────────────────────────────────────

test('calendar returns events array', function () {
     $user = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $user->id]);
        $response = $this
        ->getJson("/api/v1/projects/{$project->id}/views/calendar")
        ->assertOk()
        ->assertJsonStructure(['project', 'events'])
        ->assertJsonIsArray('events');
});

test('calendar events include task with due_date', function () {
     $user = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $user->id]);
    Task::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'due_date'   => '2026-06-15',
    ]);
        $response = $this
        ->getJson("/api/v1/projects/{$project->id}/views/calendar")
        ->assertOk()
        ->json();

    $taskEvents = collect($response['events'])->filter(fn($e) => $e['type'] === 'task');
    expect($taskEvents)->toHaveCount(1);
});

test('calendar excludes tasks without due_date', function () {
     $user = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $user->id]);
    Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'due_date' => null]);
        $response = $this
        ->getJson("/api/v1/projects/{$project->id}/views/calendar")
        ->assertOk()
        ->json();

    expect($response['events'])->toHaveCount(0);
});

// ── Gantt ─────────────────────────────────────────────────────────────────────

test('gantt returns tasks and milestones', function () {
     $user = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $user->id]);
        $response = $this
        ->getJson("/api/v1/projects/{$project->id}/views/gantt")
        ->assertOk()
        ->assertJsonStructure(['project', 'tasks', 'milestones']);
});

test('gantt task includes progress percentage', function () {
     $user = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $user->id]);
    Task::factory()->create([
        'project_id'      => $project->id,
        'created_by'      => $user->id,
        'estimated_hours' => 10,
        'logged_hours'    => 5,
    ]);
        $response = $this
        ->getJson("/api/v1/projects/{$project->id}/views/gantt")
        ->assertOk()
        ->json();

    expect($response['tasks'][0]['progress'])->toBe(50);
});

test('gantt task with done status has 100 percent progress when no hours', function () {
     $user = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $user->id]);
    Task::factory()->create([
        'project_id'      => $project->id,
        'created_by'      => $user->id,
        'status'          => 'done',
        'estimated_hours' => 0,
        'logged_hours'    => 0,
    ]);
        $response = $this
        ->getJson("/api/v1/projects/{$project->id}/views/gantt")
        ->assertOk()
        ->json();

    expect($response['tasks'][0]['progress'])->toBe(100);
});

// ── Members ───────────────────────────────────────────────────────────────────

test('members index returns list', function () {
     $user = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $user->id]);
        $response = $this
        ->getJson("/api/v1/projects/{$project->id}/members")
        ->assertOk()
        ->assertJsonStructure(["data", "total"]);
});

test('can add a member to a project', function () {
    $owner   = actingAsUser('employee');
    $member  = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $this
        ->postJson("/api/v1/projects/{$project->id}/members", [
            'user_id' => $member->id,
            'role'    => 'member',
        ])
        ->assertCreated();

    expect($project->members()->where('users.id', $member->id)->exists())->toBeTrue();
});

test('member role must be valid', function () {
    $owner   = actingAsUser('employee');
    $member  = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $this
        ->postJson("/api/v1/projects/{$project->id}/members", [
            'user_id' => $member->id,
            'role'    => 'superstar',
        ])
        ->assertUnprocessable();
});

test('can remove a member from a project', function () {
    $owner   = actingAsUser('employee');
    $member  = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $project->members()->attach($member->id, ['role' => 'member']);

    $this
        ->deleteJson("/api/v1/projects/{$project->id}/members/{$member->id}")
        ->assertNoContent();

    expect($project->members()->where('users.id', $member->id)->exists())->toBeFalse();
});
