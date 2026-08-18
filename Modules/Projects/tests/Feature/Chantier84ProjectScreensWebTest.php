<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Projects\Models\Project;

/**
 * Chantier 8.4 (Projects): Automation/Calendar/Epics/Gantt/Kanban/Roadmap/
 * Sprints.vue are all real, fully-built pages calling already-live axios
 * endpoints, but none had a web route anywhere in the app — reachable only
 * by direct URL/route name now, matching the consolidation-hierarchies
 * discoverability precedent used throughout this chantier.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function projectScreensTestUser(): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create();
    $user->assignRole('employee');

    return $user;
}

test('calendar/gantt/kanban pages render with the real project prop', function (string $route, string $component) {
    $user = projectScreensTestUser();
    $project = Project::factory()->create();

    $response = test()->actingAs($user)->get("/projects/{$project->id}/{$route}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component($component)
        ->where('project.id', $project->id)
    );
})->with([
    ['calendar', 'Projects/Calendar'],
    ['gantt', 'Projects/Gantt'],
    ['kanban', 'Projects/Kanban'],
]);

test('automation/epics/sprints pages render with a projectId prop', function (string $route, string $component) {
    $user = projectScreensTestUser();
    $project = Project::factory()->create();

    $response = test()->actingAs($user)->get("/projects/{$project->id}/{$route}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component($component)
        ->where('projectId', $project->id)
    );
})->with([
    ['automation', 'Projects/Automation/Index'],
    ['epics', 'Projects/Epics/Index'],
    ['sprints', 'Projects/Sprints/Index'],
]);

test('roadmap page renders with a lightweight projects list', function () {
    $user = projectScreensTestUser();
    Project::factory()->create(['name' => 'Alpha']);

    $response = test()->actingAs($user)->get('/projects/roadmap');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Projects/Roadmap')
        ->has('projects')
    );
});

test('the cross-project time report aggregates real time entries by member', function () {
    $user = projectScreensTestUser();
    $project = Project::factory()->create();
    \Modules\Projects\Models\TimeEntry::factory()->create([
        'project_id' => $project->id,
        'user_id' => $user->id,
        'started_at' => now()->subHours(2),
        'ended_at' => now(),
        'duration_minutes' => 120,
        'billable' => true,
        'hourly_rate' => 50,
    ]);

    $response = test()->actingAs($user, 'sanctum')
        ->getJson('/api/v1/projects/time-report-global?group_by=member');

    $response->assertOk();
    // json_encode() drops the trailing .0 on whole-number floats (no
    // JSON_PRESERVE_ZERO_FRACTION flag in this app), so total_hours/
    // billable_amount decode as ints here — toEqual (loose) over toBe.
    expect($response->json('total_hours'))->toEqual(2.0);
    expect($response->json('billable_amount'))->toEqual(100.0);
    expect($response->json('rows.0.member'))->toBe($user->name);
});
