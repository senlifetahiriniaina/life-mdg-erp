<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Modules\HR\Models\Employee;
use Modules\Projects\Models\AutomationRule;
use Modules\Projects\Models\Epic;
use Modules\Projects\Models\Milestone;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectRisk;
use Modules\Projects\Models\ProjectTeamMember;
use Modules\Projects\Models\ProjectTimeLog;
use Modules\Projects\Models\Sprint;
use Modules\Projects\Models\Task;
use Modules\Projects\Models\TimeEntry;
use Modules\Timesheets\Models\TimesheetEntry;

/**
 * Chantier 19 Lot 2 — Projects module empirical re-verification pass.
 *
 * Methodology: code reading alone already caught the well-documented Project/
 * Task company-scoping gap (fixed in Chantier 10) and the GanttService
 * "guaranteed fatal Error" gap for portfolio/timeline+resources (also fixed
 * in Chantier 10). This pass re-ran everything empirically (real HTTP
 * requests against real seeded data, two genuinely distinct companies) and
 * found the *same* class of bug still alive across virtually every OTHER
 * Projects sub-resource controller — Project carries no global company
 * scope, and until this chantier only ProjectController/TaskController ever
 * asserted a caller's company_id matched a record's. Every one of
 * Milestones, Members, Reports, Team (incl. the bare-ProjectTimeLog-bound
 * stopTimer route), Views (kanban/calendar/gantt), Gantt (dependency
 * add/remove/date-shift, bound via Task not Project), Epics (both the
 * project-scoped list and the global cross-project `epics/all` list backing
 * Roadmap.vue), Sprints, Automations, task-scoped Time Entries, and the
 * global Time Tracking & Billing endpoints (list/store/show/update/destroy/
 * start/stop/bill/globalReport) resolved their route-bound Project/Task/
 * TimeEntry/ProjectTimeLog with ZERO ownership check anywhere in the call
 * chain. ProjectAdvancedController's budget/kpis/risks endpoints (the ones
 * this chantier's own task description named explicitly) and
 * ResourceCapacityController::getProjectDemand had the identical gap.
 *
 * A second, independent bug was found in GanttService::getResourceHeatmap():
 * it queried a `ts_timesheets` table with `user_id`/`work_date`/
 * `hours_logged` columns that have never existed anywhere in this repo
 * (confirmed via Schema::hasTable()) — the real, live timesheet table is
 * Modules\Timesheets\Models\TimesheetEntry's `timesheet_entries`
 * (employee_id/entry_date/hours_worked). Wrapped in a try/catch, so it never
 * fataled, but logged_hours was a *guaranteed* 0 for every member on every
 * call, forever — this test proves the real fix returns real, non-zero
 * hours from the real table.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function chantier19ProjectsUser(Company $company, string $role = 'employee'): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

function chantier19TwoCompanies(): array
{
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    return [chantier19ProjectsUser($companyA), chantier19ProjectsUser($companyB)];
}

// ─── Headline finding: ProjectAdvancedController budget/kpis/risks ──────────

test('budget kpis and risks endpoints deny cross-company access but allow same-company access', function () {
    [$userA, $userB] = chantier19TwoCompanies();
    $project = Project::factory()->create(['company_id' => $userA->company_id]);
    ProjectRisk::factory()->create(['project_id' => $project->id, 'status' => 'open']);

    // Company B cannot reach company A's project via any of the 3 endpoints.
    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/projects/{$project->id}/budget")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/projects/{$project->id}/kpis")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/projects/{$project->id}/risks")->assertNotFound();

    // Company A can still reach its own project normally.
    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/projects/{$project->id}/budget")->assertOk();
    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/projects/{$project->id}/kpis")->assertOk();
    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/projects/{$project->id}/risks")->assertOk();
});

// ─── Milestones, Members, Reports, Team, Views, Epics, Sprints, Automations ──

test('milestones deny cross-company read and write', function () {
    [$userA, $userB] = chantier19TwoCompanies();
    $project = Project::factory()->create(['company_id' => $userA->company_id]);
    $milestone = Milestone::factory()->create(['project_id' => $project->id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/projects/{$project->id}/milestones")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/projects/{$project->id}/milestones/{$milestone->id}")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->postJson("/api/v1/projects/{$project->id}/milestones", ['name' => 'Hacked'])->assertNotFound();

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/projects/{$project->id}/milestones")->assertOk();
});

test('project members endpoint denies cross-company access', function () {
    [$userA, $userB] = chantier19TwoCompanies();
    $project = Project::factory()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/projects/{$project->id}/members")->assertNotFound();
    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/projects/{$project->id}/members")->assertOk();
});

test('project report json endpoint denies cross-company access', function () {
    [$userA, $userB] = chantier19TwoCompanies();
    $project = Project::factory()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/projects/{$project->id}/report")->assertNotFound();
    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/projects/{$project->id}/report")->assertOk();
});

test('team index and time report deny cross-company access', function () {
    [$userA, $userB] = chantier19TwoCompanies();
    $project = Project::factory()->create(['company_id' => $userA->company_id]);
    ProjectTeamMember::factory()->create(['project_id' => $project->id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/projects/{$project->id}/team")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/projects/{$project->id}/time-report")->assertNotFound();

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/projects/{$project->id}/team")->assertOk();
});

test('stopping a team timer denies cross-company access even though the route only binds the log', function () {
    [$userA, $userB] = chantier19TwoCompanies();
    $project = Project::factory()->create(['company_id' => $userA->company_id]);
    $log = ProjectTimeLog::factory()->create(['project_id' => $project->id, 'ended_at' => null]);

    // Chantier 19 Lot 2: this route (`POST projects/time-logs/{log}/stop`)
    // binds ONLY a ProjectTimeLog, never a Project — the company check has
    // to walk log->project->company_id, which is exactly what
    // assertSameCompanyAsTimeLog() does.
    test()->actingAs($userB, 'sanctum')->postJson("/api/v1/projects/time-logs/{$log->id}/stop")->assertNotFound();
    expect($log->fresh()->ended_at)->toBeNull();

    test()->actingAs($userA, 'sanctum')->postJson("/api/v1/projects/time-logs/{$log->id}/stop")->assertOk();
    expect($log->fresh()->ended_at)->not->toBeNull();
});

test('kanban calendar and gantt view endpoints deny cross-company access', function () {
    [$userA, $userB] = chantier19TwoCompanies();
    $project = Project::factory()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/projects/{$project->id}/views/kanban")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/projects/{$project->id}/views/calendar")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/projects/{$project->id}/views/gantt")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/projects/{$project->id}/gantt")->assertNotFound();

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/projects/{$project->id}/views/kanban")->assertOk();
});

test('task dependency endpoints deny cross-company access via the bound task', function () {
    [$userA, $userB] = chantier19TwoCompanies();
    $project = Project::factory()->create(['company_id' => $userA->company_id]);
    $task1 = Task::factory()->create(['project_id' => $project->id]);
    $task2 = Task::factory()->create(['project_id' => $project->id]);

    test()->actingAs($userB, 'sanctum')
        ->postJson("/api/v1/projects/tasks/{$task1->id}/dependencies", ['depends_on_task_id' => $task2->id])
        ->assertNotFound();

    test()->actingAs($userA, 'sanctum')
        ->postJson("/api/v1/projects/tasks/{$task1->id}/dependencies", ['depends_on_task_id' => $task2->id])
        ->assertCreated();
});

test('project scoped epics deny cross-company access and the global epics list is company scoped', function () {
    [$userA, $userB] = chantier19TwoCompanies();
    $projectA = Project::factory()->create(['company_id' => $userA->company_id]);
    $projectB = Project::factory()->create(['company_id' => $userB->company_id]);
    Epic::factory()->create(['project_id' => $projectA->id, 'title' => 'Epic A']);
    Epic::factory()->create(['project_id' => $projectB->id, 'title' => 'Epic B']);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/projects/{$projectA->id}/epics")->assertNotFound();

    // Chantier 19 Lot 2: `GET projects/epics` (EpicController::all(), backs
    // the cross-project Roadmap.vue page) had zero company scoping at all.
    $response = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/projects/epics')->assertOk();
    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('Epic A')->not->toContain('Epic B');
});

test('sprints deny cross-company access', function () {
    [$userA, $userB] = chantier19TwoCompanies();
    $project = Project::factory()->create(['company_id' => $userA->company_id]);
    $sprint = Sprint::factory()->create(['project_id' => $project->id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/projects/{$project->id}/sprints")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/projects/{$project->id}/sprints/{$sprint->id}")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/projects/{$project->id}/backlog")->assertNotFound();

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/projects/{$project->id}/sprints")->assertOk();
});

test('automation rules deny cross-company access', function () {
    [$userA, $userB] = chantier19TwoCompanies();
    $project = Project::factory()->create(['company_id' => $userA->company_id]);
    $rule = AutomationRule::factory()->create(['project_id' => $project->id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/projects/{$project->id}/automations")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/projects/{$project->id}/automations/{$rule->id}")->assertNotFound();

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/projects/{$project->id}/automations")->assertOk();
});

// ─── Time Entries (task-scoped) & Time Tracking (global) ────────────────────

test('task scoped time entries deny cross-company access', function () {
    [$userA, $userB] = chantier19TwoCompanies();
    $project = Project::factory()->create(['company_id' => $userA->company_id]);
    $task = Task::factory()->create(['project_id' => $project->id]);

    test()->actingAs($userB, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries")
        ->assertNotFound();

    test()->actingAs($userA, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/tasks/{$task->id}/time-entries")
        ->assertOk();
});

test('global time entry list is scoped to the caller own company', function () {
    [$userA, $userB] = chantier19TwoCompanies();
    $projectA = Project::factory()->create(['company_id' => $userA->company_id]);
    $projectB = Project::factory()->create(['company_id' => $userB->company_id]);
    TimeEntry::factory()->create(['project_id' => $projectA->id, 'user_id' => $userA->id, 'description' => 'Entry A']);
    TimeEntry::factory()->create(['project_id' => $projectB->id, 'user_id' => $userB->id, 'description' => 'Entry B']);

    $response = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/projects/time-entries')->assertOk();
    $descriptions = collect($response->json('data'))->pluck('description');
    expect($descriptions)->toContain('Entry A')->not->toContain('Entry B');
});

test('a single time entry denies cross-company view update delete stop and bill', function () {
    [$userA, $userB] = chantier19TwoCompanies();
    $project = Project::factory()->create(['company_id' => $userA->company_id]);
    $entry = TimeEntry::factory()->running()->create(['project_id' => $project->id, 'user_id' => $userA->id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/projects/time-entries/{$entry->id}")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/projects/time-entries/{$entry->id}", ['description' => 'Hacked'])->assertNotFound();
    test()->actingAs($userB, 'sanctum')->postJson("/api/v1/projects/time-entries/{$entry->id}/stop")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->postJson("/api/v1/projects/time-entries/{$entry->id}/bill")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/projects/time-entries/{$entry->id}")->assertNotFound();

    expect($entry->fresh()->description)->not->toBe('Hacked');
});

test('starting a timer against another company project is rejected', function () {
    [$userA, $userB] = chantier19TwoCompanies();
    $project = Project::factory()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userB, 'sanctum')
        ->postJson('/api/v1/projects/time-entries/start', ['project_id' => $project->id])
        ->assertNotFound();
});

test('project scoped billing endpoints deny cross-company access', function () {
    [$userA, $userB] = chantier19TwoCompanies();
    $project = Project::factory()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/projects/{$project->id}/time-entries")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/projects/{$project->id}/billing")->assertNotFound();
    test()->actingAs($userB, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/billing/setup", ['billing_type' => 'hourly'])
        ->assertNotFound();
    test()->actingAs($userB, 'sanctum')->postJson("/api/v1/projects/{$project->id}/billing/mark-billed")->assertNotFound();
});

test('the global cross project time report is scoped to the caller own company', function () {
    [$userA, $userB] = chantier19TwoCompanies();
    $projectA = Project::factory()->create(['company_id' => $userA->company_id, 'name' => 'Project A']);
    $projectB = Project::factory()->create(['company_id' => $userB->company_id, 'name' => 'Project B']);
    TimeEntry::factory()->create(['project_id' => $projectA->id, 'user_id' => $userA->id, 'duration_minutes' => 120]);
    TimeEntry::factory()->create(['project_id' => $projectB->id, 'user_id' => $userB->id, 'duration_minutes' => 999]);

    $response = test()->actingAs($userA, 'sanctum')
        ->getJson('/api/v1/projects/time-report-global?group_by=project')
        ->assertOk();

    $labels = collect($response->json('rows'))->pluck('label');
    expect($labels)->toContain('Project A')->not->toContain('Project B');
});

// ─── ResourceCapacityController::getProjectDemand ────────────────────────────

test('project resource demand endpoint denies cross-company access', function () {
    [$userA, $userB] = chantier19TwoCompanies();
    $project = Project::factory()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/projects/{$project->id}/demand")->assertNotFound();
    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/projects/{$project->id}/demand")->assertOk();
});

// ─── GanttService::getResourceHeatmap phantom-table bug ──────────────────────

test('portfolio resource heatmap reads real logged hours from the real timesheet_entries table', function () {
    $company = Company::factory()->create();
    $user = chantier19ProjectsUser($company);
    $project = Project::factory()->create(['company_id' => $company->id, 'status' => 'active']);
    ProjectTeamMember::factory()->create(['project_id' => $project->id, 'user_id' => $user->id, 'left_at' => null]);

    $employee = Employee::factory()->create(['user_id' => $user->id]);
    TimesheetEntry::factory()->create([
        'employee_id' => $employee->id,
        'project_id' => $project->id,
        'status' => 'approved',
        'entry_date' => now()->toDateString(),
        'hours_worked' => 6,
    ]);
    // A non-approved entry must NOT count toward logged hours.
    TimesheetEntry::factory()->create([
        'employee_id' => $employee->id,
        'project_id' => $project->id,
        'status' => 'submitted',
        'entry_date' => now()->toDateString(),
        'hours_worked' => 100,
    ]);

    $response = test()->actingAs($user, 'sanctum')
        ->getJson('/api/v1/projects/portfolio/resources')
        ->assertOk();

    $member = collect($response->json('data.members'))->firstWhere('user_id', $user->id);
    expect($member)->not->toBeNull();
    expect((float) $member['logged_hours'])->toBe(6.0);
});

test('portfolio timeline endpoint returns real company scoped data without a fatal error', function () {
    $company = Company::factory()->create();
    $user = chantier19ProjectsUser($company);
    $project = Project::factory()->create(['company_id' => $company->id, 'status' => 'active', 'name' => 'Timeline Project']);
    Task::factory()->create(['project_id' => $project->id, 'status' => 'done']);
    Task::factory()->create(['project_id' => $project->id, 'status' => 'todo']);

    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/projects/portfolio/timeline')->assertOk();

    $entry = collect($response->json('data.projects'))->firstWhere('project_id', $project->id);
    expect($entry)->not->toBeNull();
    expect((float) $entry['completion_pct'])->toBe(50.0);
});

// ─── Task::timeLogs() relation (Chantier 8.6/8.7 fix, re-verified empirically) ─

test('task timeLogs relation resolves against the real ProjectTimeLog model without error', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->create(['project_id' => $project->id]);
    ProjectTimeLog::factory()->create(['project_id' => $project->id, 'task_id' => $task->id]);

    expect($task->timeLogs)->toHaveCount(1);
    expect($task->timeLogs->first())->toBeInstanceOf(ProjectTimeLog::class);
});

// ─── ProjectsAiAssistController phantom users.role column bug ───────────────

test('the ai assist endpoint resolves the caller real spatie role instead of the phantom users.role column', function () {
    $company = Company::factory()->create();
    $user = chantier19ProjectsUser($company, 'manager');

    // Chantier 19 Lot 2: userRole used to read $request->user()?->role, a
    // real DB column that is never populated by the real registration
    // flow — always null, so every call silently rendered guidance as the
    // generic 'user' role regardless of who was actually asking. Fixed to
    // getRoleNames()->first(), matching AI/Sales' identical fix this
    // session. This test proves the endpoint is reachable and the fixed
    // call doesn't throw against a real Spatie-roled user; the response
    // itself degrades to the static fallback table since no AI provider
    // key is configured in tests (fallback-first design, never an error).
    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/projects/ai/assist', [
        'action' => 'view_dashboard',
    ]);

    $response->assertOk();
    expect($response->json('enabled'))->toBeFalse();
});

// ─── ProjectWebController (Inertia pages) cross-company leak ────────────────

test('the project show page denies cross-company access at the web layer', function () {
    [$userA, $userB] = chantier19TwoCompanies();
    $project = Project::factory()->create(['company_id' => $userA->company_id, 'name' => 'Secret Project A']);

    test()->actingAs($userB)->get("/projects/{$project->id}")->assertNotFound();
    test()->actingAs($userA)->get("/projects/{$project->id}")->assertOk();
});

test('the project calendar gantt kanban automation epics and sprints pages deny cross-company access', function () {
    [$userA, $userB] = chantier19TwoCompanies();
    $project = Project::factory()->create(['company_id' => $userA->company_id]);

    foreach (['calendar', 'gantt', 'kanban', 'automation', 'epics', 'sprints'] as $route) {
        test()->actingAs($userB)->get("/projects/{$project->id}/{$route}")->assertNotFound();
    }
});

test('the project index and roadmap pages are scoped to the caller own company', function () {
    [$userA, $userB] = chantier19TwoCompanies();
    Project::factory()->create(['company_id' => $userA->company_id, 'name' => 'Visible To A']);
    Project::factory()->create(['company_id' => $userB->company_id, 'name' => 'Visible To B']);

    $index = test()->actingAs($userA)->get('/projects')->assertOk();
    $index->assertInertia(fn ($page) => $page
        ->where('projects.data.0.name', 'Visible To A')
    );

    $roadmap = test()->actingAs($userA)->get('/projects/roadmap')->assertOk();
    $roadmap->assertInertia(function ($page) {
        $names = collect($page->toArray()['props']['projects'])->pluck('name');
        expect($names)->toContain('Visible To A')->not->toContain('Visible To B');

        return $page;
    });
});
