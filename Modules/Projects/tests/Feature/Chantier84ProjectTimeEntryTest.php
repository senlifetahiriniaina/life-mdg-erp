<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectTimeLog;
use Modules\Projects\Models\Task;

/**
 * Chantier 8.4 (Projects): the live, routed task-scoped TimeEntryController
 * used Modules\Projects\Models\TimeLog, whose $fillable (hours/date/
 * is_billable) never matched any real migrated table — every call fatalled.
 * Rewritten onto ProjectTimeLog (the model that matches the real
 * prj_time_logs schema).
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function projectTimeEntryTestUser(): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create();
    $user->assignRole('employee');

    return $user;
}

test('creating a manual time entry persists real started_at/ended_at/duration_minutes', function () {
    $user = projectTimeEntryTestUser();
    $project = Project::factory()->create();
    $task = Task::factory()->create(['project_id' => $project->id]);

    $response = test()->actingAs($user, 'sanctum')->postJson(
        "/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries",
        [
            'hours' => 2.5,
            'date' => '2026-05-01',
            'description' => 'Implemented login page',
            'is_billable' => true,
            'hourly_rate' => 75.00,
        ]
    );

    $response->assertCreated();
    $this->assertDatabaseHas('prj_time_logs', [
        'task_id' => $task->id,
        'project_id' => $project->id,
        'duration_minutes' => 150,
        'billable' => 1,
    ]);
});

test('listing, updating, and deleting a task time entry works end to end', function () {
    $user = projectTimeEntryTestUser();
    $project = Project::factory()->create();
    $task = Task::factory()->create(['project_id' => $project->id]);
    $entry = ProjectTimeLog::factory()->create([
        'project_id' => $project->id,
        'task_id' => $task->id,
        'user_id' => $user->id,
    ]);

    test()->actingAs($user, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries")
        ->assertOk();

    test()->actingAs($user, 'sanctum')
        ->putJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/{$entry->id}", [
            'hours' => 4,
        ])
        ->assertOk()
        ->assertJsonPath('duration_minutes', 240);

    test()->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries/{$entry->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('prj_time_logs', ['id' => $entry->id]);
});
