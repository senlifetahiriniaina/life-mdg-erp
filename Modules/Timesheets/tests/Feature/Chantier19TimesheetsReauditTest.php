<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Modules\HR\Models\Employee;
use Modules\Projects\Models\Project;
use Modules\Timesheets\Models\TimesheetEntry;
use Modules\Timesheets\Models\TimesheetPeriod;
use Modules\Timesheets\Services\TimerService;

/**
 * Chantier 19 (Lot 2) — Timesheets re-verification pass. Real HTTP-request
 * regression coverage for every bug found and fixed by this pass — see
 * CLAUDE.md's "Chantier 19 (Lot 2 — Timesheets)" entry for the full
 * write-up of each finding.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function chantier19TimesheetUser(string $role = 'employee'): array
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create();
    $user->assignRole($role);
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    return [$user, $employee];
}

// ── TimesheetPeriodPolicy — the headline RBAC gap ──────────────────────────

test('an employee cannot approve or reject another employees submitted sheet, only a manager can', function () {
    [, $employee] = chantier19TimesheetUser();
    [$otherUser] = chantier19TimesheetUser();
    [$manager] = chantier19TimesheetUser('manager');

    $period = TimesheetPeriod::factory()->submitted()->create(['employee_id' => $employee->id]);

    test()->actingAs($otherUser, 'sanctum')
        ->postJson("/api/v1/timesheets/sheets/{$period->id}/approve")
        ->assertForbidden();

    $period2 = TimesheetPeriod::factory()->submitted()->create(['employee_id' => $employee->id]);
    test()->actingAs($otherUser, 'sanctum')
        ->postJson("/api/v1/timesheets/sheets/{$period2->id}/reject", ['reason' => 'nope'])
        ->assertForbidden();

    test()->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/timesheets/sheets/{$period->id}/approve")
        ->assertOk();
    expect($period->fresh()->status)->toBe('approved');
});

test('an employee cannot submit or update another employees draft sheet', function () {
    [, $employee] = chantier19TimesheetUser();
    [$otherUser] = chantier19TimesheetUser();

    $period = TimesheetPeriod::factory()->create(['employee_id' => $employee->id, 'status' => 'draft']);

    test()->actingAs($otherUser, 'sanctum')
        ->postJson("/api/v1/timesheets/sheets/{$period->id}/submit")
        ->assertForbidden();

    test()->actingAs($otherUser, 'sanctum')
        ->putJson("/api/v1/timesheets/sheets/{$period->id}", ['period_start' => now()->format('Y-m-d')])
        ->assertForbidden();
});

test('an employee cannot create a sheet on behalf of another employee', function () {
    [, $employee] = chantier19TimesheetUser();
    [$otherUser] = chantier19TimesheetUser();

    test()->actingAs($otherUser, 'sanctum')->postJson('/api/v1/timesheets/sheets', [
        'period_start' => now()->startOfWeek()->format('Y-m-d'),
        'period_end' => now()->endOfWeek()->format('Y-m-d'),
        'employee_id' => $employee->id,
    ])->assertForbidden();
});

test('sheets index only lists the callers own periods for a non-manager, but every period for a manager', function () {
    [$user, $employee] = chantier19TimesheetUser();
    [, $otherEmployee] = chantier19TimesheetUser();
    [$manager] = chantier19TimesheetUser('manager');

    TimesheetPeriod::factory()->count(2)->create(['employee_id' => $employee->id]);
    TimesheetPeriod::factory()->count(3)->create(['employee_id' => $otherEmployee->id]);

    $own = test()->actingAs($user, 'sanctum')->getJson('/api/v1/timesheets/sheets?per_page=100');
    $own->assertOk();
    expect($own->json('data'))->toHaveCount(2);

    $all = test()->actingAs($manager, 'sanctum')->getJson('/api/v1/timesheets/sheets?per_page=100');
    $all->assertOk();
    expect($all->json('data'))->toHaveCount(5);
});

// ── TimeEntries create/update — the field-name/employee_id mismatch ────────

test('creating a timesheet entry without an explicit employee_id defaults to the callers own linked employee', function () {
    [$user, $employee] = chantier19TimesheetUser();
    $project = Project::factory()->create(['company_id' => $user->company_id]);

    // Chantier 19 (Lot 2): the real TimeEntries/Form.vue never sent
    // employee_id (it has no way to know its own caller's
    // hr_employees.id) — this used to be `required` and 422 every real
    // submission. Also confirms project_id/billable_hours/hourly_rate are
    // now actually persisted (they were silently dropped before).
    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/timesheets/entries', [
        'entry_date' => now()->format('Y-m-d'),
        'hours_worked' => 6.5,
        'description' => 'Real UI create-form payload, no employee_id',
        'project_id' => $project->id,
        'billable' => true,
        'hourly_rate' => 12000,
    ]);

    $response->assertCreated();
    expect($response->json('data.employee_id'))->toBe($employee->id);

    test()->assertDatabaseHas('timesheet_entries', [
        'employee_id' => $employee->id,
        'project_id' => $project->id,
        'billable_hours' => 6.5,
        'hourly_rate' => 12000,
    ]);
});

test('the timesheet entry resource exposes billable_hours, hourly_rate, billable, and billable_amount', function () {
    [$user, $employee] = chantier19TimesheetUser();
    $entry = TimesheetEntry::factory()->create([
        'employee_id' => $employee->id,
        'hours_worked' => 4,
        'billable_hours' => 4,
        'hourly_rate' => 5000,
    ]);

    $response = test()->actingAs($user, 'sanctum')->getJson("/api/v1/timesheets/entries/{$entry->id}");

    $response->assertOk()
        ->assertJsonPath('data.billable_hours', 4)
        ->assertJsonPath('data.hourly_rate', 5000)
        ->assertJsonPath('data.billable', true)
        ->assertJsonPath('data.billable_amount', 20000);
});

// ── ProjectBillingService::getRevenueRecognition() — nonexistent column ────

test('revenue recognition returns real, company-scoped project data instead of always falling back to demo data', function () {
    [$user] = chantier19TimesheetUser();
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user->forceFill(['company_id' => $company->id])->save();

    Project::factory()->create([
        'company_id' => $company->id,
        'status' => 'active',
        'budget' => 1_000_000,
    ]);
    // A different company's project must not leak into the caller's report.
    Project::factory()->create([
        'company_id' => $otherCompany->id,
        'status' => 'active',
        'budget' => 5_000_000,
    ]);

    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/timesheets/revenue-recognition');

    $response->assertOk();
    // Chantier 19 (Lot 2): before the fix, prj_projects.tenant_id doesn't
    // exist at all, so this query always threw and silently fell back to
    // demoRevenueRecognition()'s 2 hardcoded "Démo Projet" rows — which
    // never match the real, seeded project name/count asserted here.
    expect($response->json('data.projects'))->toHaveCount(1);
});

// ── TimerService — the browser-timer ID-space bug ──────────────────────────

test('stopping a browser timer creates the entry under the real linked employee, not the raw user id', function () {
    [$user, $employee] = chantier19TimesheetUser();

    $timer = app(TimerService::class);
    $timer->start($user->id, ['description' => 'Timer-tracked work']);
    $result = $timer->stop($user->id);

    expect($result['entry_id'])->not->toBeNull();

    test()->assertDatabaseHas('timesheet_entries', [
        'id' => $result['entry_id'],
        'employee_id' => $employee->id,
    ]);
    // The old, buggy behavior wrote employee_id = $user->id directly —
    // confirm that never happened (guards against a coincidental id match
    // making the assertion above a false positive).
    if ($user->id !== $employee->id) {
        test()->assertDatabaseMissing('timesheet_entries', [
            'id' => $result['entry_id'],
            'employee_id' => $user->id,
        ]);
    }
});

test('stopping a timer for a user with no linked employee record does not create a mismatched entry', function () {
    $user = User::factory()->create();
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
    $user->assignRole('employee');

    $timer = app(TimerService::class);
    $timer->start($user->id);
    $result = $timer->stop($user->id);

    expect($result['entry_id'])->toBeNull();
    test()->assertDatabaseMissing('timesheet_entries', ['employee_id' => $user->id]);
});

// ── StoreTrackingProjectRequest — validated against a nonexistent table ────

test('creating a tracking project with a real department_id succeeds instead of a fatal SQL error', function () {
    [$user] = chantier19TimesheetUser('manager');
    $department = \Modules\HR\Models\Department::factory()->create();

    // Chantier 19 (Lot 2): department_id used to validate against
    // exists:departments,id — a table that has never existed in this app
    // (real table is hr_departments) — supplying department_id fatalled
    // with a "no such table" SQL error during validation, not just a 422.
    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/timesheets/projects', [
        'name' => 'Chantier 19 Regression Project',
        'code' => 'C19-REG-001',
        'budget_hours' => 100,
        'department_id' => $department->id,
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->addMonths(3)->format('Y-m-d'),
    ]);

    $response->assertCreated();
    test()->assertDatabaseHas('time_tracking_projects', [
        'code' => 'C19-REG-001',
        'department_id' => $department->id,
    ]);
});

// ── MetricsController::summary() department filter — ID-space mismatch ────
// (regression already covered directly in MetricsControllerTest.php's
// fixed summary_metrics_can_filter_by_department() test.)
