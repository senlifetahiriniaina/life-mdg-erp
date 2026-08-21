<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Modules\Projects\Models\AutomationRule;
use Modules\Projects\Models\CustomField;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ResourceAllocation;
use Modules\Projects\Models\Task;
use Modules\Projects\Models\TaskDependency;

/**
 * Chantier 32.17 — Modules\Projects deep 14-layer audit.
 *
 * Locks in every real bug found and fixed by this chantier, empirically,
 * via the real HTTP routes — not just re-reading the fix.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function chantier3217User(Company $company, string $role = 'employee'): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

function chantier3217TwoCompanies(): array
{
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    return [chantier3217User($companyA), chantier3217User($companyB)];
}

// ─── Layer 6 security: Gantt cross-project/cross-tenant dependency IDOR ─────

test('a task cannot be made to depend on a task from a different project', function () {
    [$userA, $userB] = chantier3217TwoCompanies();
    $projectA = Project::factory()->create(['company_id' => $userA->company_id]);
    $projectB = Project::factory()->create(['company_id' => $userB->company_id]);
    $taskA = Task::factory()->create(['project_id' => $projectA->id]);
    $taskB = Task::factory()->create(['project_id' => $projectB->id]);

    // Company A's user tries to make their own task depend on company B's task.
    test()->actingAs($userA, 'sanctum')
        ->postJson("/api/v1/projects/tasks/{$taskA->id}/dependencies", [
            'depends_on_task_id' => $taskB->id,
        ])
        ->assertStatus(422);

    expect(TaskDependency::where('task_id', $taskA->id)->count())->toBe(0);

    // A real, same-project dependency still works.
    $taskA2 = Task::factory()->create(['project_id' => $projectA->id]);
    test()->actingAs($userA, 'sanctum')
        ->postJson("/api/v1/projects/tasks/{$taskA->id}/dependencies", [
            'depends_on_task_id' => $taskA2->id,
        ])
        ->assertCreated();
});

test('gantt views/gantt endpoint returns real dependency ids, not the dead JSON column', function () {
    $user = chantier3217User(Company::factory()->create());
    $project = Project::factory()->create(['company_id' => $user->company_id]);
    $taskA = Task::factory()->create(['project_id' => $project->id]);
    $taskB = Task::factory()->create(['project_id' => $project->id]);
    TaskDependency::create(['task_id' => $taskA->id, 'depends_on_task_id' => $taskB->id, 'type' => 'FS', 'lag_days' => 0]);

    $response = test()->actingAs($user, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/views/gantt")
        ->assertOk();

    $tasks = collect($response->json('tasks'));
    $row = $tasks->firstWhere('id', $taskA->id);
    expect($row['dependencies'])->toContain($taskB->id);
});

// ─── Layer 6 security: time-entry task/project mismatch ────────────────────

test('creating a task-scoped time entry rejects a task that belongs to a different project', function () {
    [$userA, $userB] = chantier3217TwoCompanies();
    $projectA = Project::factory()->create(['company_id' => $userA->company_id]);
    $projectB = Project::factory()->create(['company_id' => $userB->company_id]);
    $foreignTask = Task::factory()->create(['project_id' => $projectB->id]);

    test()->actingAs($userA, 'sanctum')
        ->postJson("/api/v1/projects/{$projectA->id}/tasks/{$foreignTask->id}/time-entries", [
            'hours' => 2, 'date' => now()->toDateString(),
        ])
        ->assertStatus(404);
});

test('manual project time-log rejects a task_id that does not belong to the project', function () {
    [$userA, $userB] = chantier3217TwoCompanies();
    $projectA = Project::factory()->create(['company_id' => $userA->company_id]);
    $projectB = Project::factory()->create(['company_id' => $userB->company_id]);
    $foreignTask = Task::factory()->create(['project_id' => $projectB->id]);

    test()->actingAs($userA, 'sanctum')
        ->postJson("/api/v1/projects/{$projectA->id}/time-logs", [
            'task_id' => $foreignTask->id,
            'started_at' => now()->toISOString(),
        ])
        ->assertStatus(422);
});

test('global time-tracking store rejects a task_id from a different project than project_id', function () {
    [$userA, $userB] = chantier3217TwoCompanies();
    $projectA = Project::factory()->create(['company_id' => $userA->company_id]);
    $projectB = Project::factory()->create(['company_id' => $userB->company_id]);
    $foreignTask = Task::factory()->create(['project_id' => $projectB->id]);

    test()->actingAs($userA, 'sanctum')
        ->postJson('/api/v1/projects/time-entries', [
            'project_id' => $projectA->id,
            'task_id' => $foreignTask->id,
            'started_at' => now()->toISOString(),
            'ended_at' => now()->addHour()->toISOString(),
        ])
        ->assertStatus(422);
});

// ─── Layer 6 security: ResourceAllocation cross-company IDOR ────────────────

test('resource allocations deny cross-company read, write and listing', function () {
    [$userA, $userB] = chantier3217TwoCompanies();
    $projectA = Project::factory()->create(['company_id' => $userA->company_id]);
    $allocation = ResourceAllocation::factory()->create(['project_id' => $projectA->id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/allocations/{$allocation->id}")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/allocations/{$allocation->id}", ['status' => 'cancelled'])->assertNotFound();
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/allocations/{$allocation->id}")->assertNotFound();

    // Listing: company B's own allocation is visible to B, A's is not.
    $projectB = Project::factory()->create(['company_id' => $userB->company_id]);
    ResourceAllocation::factory()->create(['project_id' => $projectB->id]);

    $listed = test()->actingAs($userB, 'sanctum')->getJson('/api/v1/allocations')->assertOk()->json('data');
    $ids = collect($listed)->pluck('id');
    expect($ids)->not->toContain($allocation->id);

    // A caller cannot create an allocation against another company's project.
    test()->actingAs($userB, 'sanctum')
        ->postJson('/api/v1/allocations', [
            'project_id' => $projectA->id,
            'user_id' => $userB->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
        ])
        ->assertNotFound();

    // Same-company access still works.
    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/allocations/{$allocation->id}")->assertOk();
});

// ─── Layer 12 API contract: the real Epics/Index.vue "add story" flow ──────

test('adding a story task from an epic works exactly like the real Epics/Index.vue addStory() call', function () {
    $user = chantier3217User(Company::factory()->create());
    $project = Project::factory()->create(['company_id' => $user->company_id]);
    $epic = \Modules\Projects\Models\Epic::factory()->create(['project_id' => $project->id]);

    // Chantier 32.17: this exact body shape (no project_id, real
    // type/story_points/epic_id fields) is what Epics/Index.vue actually
    // sends — it 422'd on every real click before this fix.
    $response = test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/tasks", [
            'title' => 'As a user, I want X',
            'type' => 'story',
            'story_points' => 5,
            'epic_id' => $epic->id,
        ])
        ->assertCreated();

    $task = Task::findOrFail($response->json('id'));
    expect($task->project_id)->toBe($project->id)
        ->and($task->type)->toBe('story')
        ->and($task->story_points)->toBe(5)
        ->and($task->epic_id)->toBe($epic->id);
});

test('task index scoped by the route project denies cross-company access', function () {
    [$userA, $userB] = chantier3217TwoCompanies();
    $projectA = Project::factory()->create(['company_id' => $userA->company_id]);
    Task::factory()->create(['project_id' => $projectA->id]);

    test()->actingAs($userB, 'sanctum')
        ->getJson("/api/v1/projects/{$projectA->id}/tasks")
        ->assertNotFound();

    test()->actingAs($userA, 'sanctum')
        ->getJson("/api/v1/projects/{$projectA->id}/tasks")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// ─── Layer 9 fake/dead, activated: AutomationService now has a real producer ─

test('creating a task fires a task_created automation rule for real', function () {
    $user = chantier3217User(Company::factory()->create());
    $project = Project::factory()->create(['company_id' => $user->company_id]);
    $assignee = User::factory()->create(['company_id' => $user->company_id]);

    AutomationRule::create([
        'project_id' => $project->id,
        'name' => 'auto-assign on create',
        'trigger' => 'task_created',
        'conditions' => [],
        'actions' => [['type' => 'assign_user', 'value' => $assignee->id]],
        'active' => true,
    ]);

    $response = test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'New task'])
        ->assertCreated();

    $task = Task::findOrFail($response->json('id'));
    expect($task->assignee_id)->toBe($assignee->id);
});

test('changing a task status fires a task_status_changed automation rule for real', function () {
    $user = chantier3217User(Company::factory()->create());
    $project = Project::factory()->create(['company_id' => $user->company_id]);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'todo', 'created_by' => $user->id]);

    AutomationRule::create([
        'project_id' => $project->id,
        'name' => 'tag on done',
        'trigger' => 'task_status_changed',
        'conditions' => [['field' => 'status', 'operator' => '=', 'value' => 'done']],
        'actions' => [['type' => 'add_label', 'value' => 'shipped']],
        'active' => true,
    ]);

    test()->actingAs($user, 'sanctum')
        ->putJson("/api/v1/tasks/{$task->id}", ['status' => 'done'])
        ->assertOk();

    $task->refresh();
    expect($task->tags ?? [])->toContain('shipped');
});

// ─── Layer 9 fake/dead, activated: CustomFieldController ────────────────────

test('custom field definitions can be created, listed, and used to set/get values on a real task', function () {
    $user = chantier3217User(Company::factory()->create());
    $project = Project::factory()->create(['company_id' => $user->company_id]);
    $task = Task::factory()->create(['project_id' => $project->id]);

    $field = test()->actingAs($user, 'sanctum')
        ->postJson('/api/v1/custom-fields', [
            'entity_type' => 'task',
            'field_name' => 'client_ref',
            'field_type' => 'text',
        ])
        ->assertCreated()
        ->json();

    test()->actingAs($user, 'sanctum')
        ->getJson('/api/v1/custom-fields?entity_type=task')
        ->assertOk()
        ->assertJsonFragment(['field_name' => 'client_ref']);

    test()->actingAs($user, 'sanctum')
        ->postJson('/api/v1/custom-fields/values', [
            'entity_type' => 'task',
            'entity_id' => $task->id,
            'values' => [['custom_field_id' => $field['id'], 'value' => 'REF-123']],
        ])
        ->assertCreated()
        ->assertJsonFragment(['client_ref' => 'REF-123']);

    test()->actingAs($user, 'sanctum')
        ->getJson('/api/v1/custom-fields/values?'.http_build_query(['entity_type' => 'task', 'entity_id' => $task->id]))
        ->assertOk()
        ->assertJsonFragment(['client_ref' => 'REF-123']);
});

test('custom field values deny cross-company access to another company task', function () {
    [$userA, $userB] = chantier3217TwoCompanies();
    $projectA = Project::factory()->create(['company_id' => $userA->company_id]);
    $taskA = Task::factory()->create(['project_id' => $projectA->id]);

    test()->actingAs($userB, 'sanctum')
        ->getJson('/api/v1/custom-fields/values?'.http_build_query(['entity_type' => 'task', 'entity_id' => $taskA->id]))
        ->assertNotFound();
});

// ─── Layer 9 fake/dead, activated: TaskConflictResolutionService lock endpoints ─

test('a task can be locked, its lock info read, and unlocked via the real API', function () {
    $user = chantier3217User(Company::factory()->create());
    $project = Project::factory()->create(['company_id' => $user->company_id]);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/tasks/{$task->id}/lock")
        ->assertCreated()
        ->assertJsonPath('data.user_id', $user->id);

    test()->actingAs($user, 'sanctum')
        ->getJson("/api/v1/tasks/{$task->id}/lock")
        ->assertOk()
        ->assertJsonPath('data.user_id', $user->id);

    test()->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/tasks/{$task->id}/lock")
        ->assertOk()
        ->assertJsonPath('released', true);

    test()->actingAs($user, 'sanctum')
        ->getJson("/api/v1/tasks/{$task->id}/lock")
        ->assertOk()
        ->assertJsonPath('data', null);
});

// ─── Layer 4 empirical: ProjectKpiService phantom-table fix ────────────────

test('project velocity trend reads the real timesheet_entries table, not the never-existed ts_timesheets', function () {
    expect(\Illuminate\Support\Facades\Schema::hasTable('ts_timesheets'))->toBeFalse();
    expect(\Illuminate\Support\Facades\Schema::hasTable('timesheet_entries'))->toBeTrue();

    $user = chantier3217User(Company::factory()->create());
    $project = Project::factory()->create(['company_id' => $user->company_id]);

    // Must not throw despite the phantom table not existing.
    test()->actingAs($user, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/kpis")
        ->assertOk();
});

// ─── Layer 14: Excel export ─────────────────────────────────────────────────

test('project report can be exported as a real xlsx file', function () {
    $user = chantier3217User(Company::factory()->create());
    $project = Project::factory()->create(['company_id' => $user->company_id]);
    Task::factory()->create(['project_id' => $project->id]);

    $response = test()->actingAs($user, 'sanctum')
        ->get("/api/v1/projects/{$project->id}/report/excel");

    $response->assertOk();
    expect($response->headers->get('content-type'))
        ->toContain('spreadsheetml');
});

test('project report excel export denies cross-company access', function () {
    [$userA, $userB] = chantier3217TwoCompanies();
    $projectA = Project::factory()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userB, 'sanctum')
        ->get("/api/v1/projects/{$projectA->id}/report/excel")
        ->assertNotFound();
});
