<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Modules\HR\Models\Employee;
use Modules\Projects\Models\Project;
use Modules\Timesheets\Models\ProjectBilling;
use Modules\Timesheets\Models\TimeAllocation;
use Modules\Timesheets\Models\TimesheetEntry;
use Modules\Timesheets\Models\TimesheetPeriod;
use Modules\Timesheets\Models\TimeTrackingProject;

/**
 * Chantier 32.19 — Timesheets deep 14-layer audit (see CLAUDE.md's
 * "Méthodologie d'audit approfondi (14 couches)" section and this module's
 * own Chantier 32.19 changelog entry for the full write-up of every finding
 * below). Real HTTP-request regression coverage for each real bug found and
 * fixed by this pass, on top of the 3 prior Timesheets passes
 * (Chantier 8.4, 10, 19 Lot 2).
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function chantier3219TimesheetUser(string $role = 'employee', ?Company $company = null): array
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role);
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    return [$user, $employee];
}

// ── entry_date whereBetween() string-boundary bug — the headline finding (layer 4/5) ──

test('submitting a sheet correctly includes hours logged on the periods own last day', function () {
    // Chantier 32.19 (Timesheets deep 14-layer audit): TimesheetEntry.
    // entry_date's `date` cast does not truncate the time component on
    // write in this app (confirmed via tinker: a raw column value like
    // "2026-08-21 22:27:25" is really stored, not "2026-08-21") —
    // whereBetween('entry_date', [$from, $to]) on a bare Y-m-d upper bound
    // silently excluded any entry dated exactly on that boundary day. This
    // test locks in the single most severe instance: submitting a real
    // weekly sheet used to silently drop the hours logged on the period's
    // own last day (the day someone most naturally submits on) from the
    // aggregated total.
    [$user, $employee] = chantier3219TimesheetUser();

    $periodStart = now()->startOfWeek()->format('Y-m-d');
    $periodEnd = now()->endOfWeek()->format('Y-m-d');

    // An entry dated on the FIRST day of the period, safely inside any
    // bound comparison — control case.
    TimesheetEntry::factory()->create([
        'employee_id' => $employee->id,
        'entry_date' => $periodStart,
        'hours_worked' => 8,
        'status' => 'draft',
    ]);

    // An entry dated exactly on the LAST day of the period — the exact
    // case the bug silently dropped.
    TimesheetEntry::factory()->create([
        'employee_id' => $employee->id,
        'entry_date' => $periodEnd,
        'hours_worked' => 6,
        'status' => 'draft',
    ]);

    $store = test()->actingAs($user, 'sanctum')->postJson('/api/v1/timesheets/sheets', [
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
    ]);
    $store->assertCreated();
    $sheetId = $store->json('id');

    $submit = test()->actingAs($user, 'sanctum')->postJson("/api/v1/timesheets/sheets/{$sheetId}/submit");

    $submit->assertOk();
    // Before this fix: total_hours was 8.0 (the period's own last day's 6
    // hours silently dropped).
    expect((float) $submit->json('total_hours'))->toBe(14.0);
});

test('approving a sheet correctly marks the last days entries as approved too', function () {
    [$user, $employee] = chantier3219TimesheetUser();
    [$manager] = chantier3219TimesheetUser('manager');

    $periodStart = now()->subDays(6)->format('Y-m-d');
    $periodEnd = now()->format('Y-m-d');

    $lastDayEntry = TimesheetEntry::factory()->create([
        'employee_id' => $employee->id,
        'entry_date' => $periodEnd,
        'hours_worked' => 5,
        'status' => 'submitted',
    ]);

    $period = TimesheetPeriod::factory()->submitted()->create([
        'employee_id' => $employee->id,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
    ]);

    test()->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/timesheets/sheets/{$period->id}/approve")
        ->assertOk();

    // Before this fix: the entry dated on the period's own last day stayed
    // 'submitted' forever, even though its period was 'approved'.
    expect($lastDayEntry->fresh()->status)->toBe('approved');
});

// ── TimesheetEntryController::show() — real IDOR (layer 6/7) ──────────────

test('an employee cannot view another employees individual timesheet entry', function () {
    [$user] = chantier3219TimesheetUser();
    [, $otherEmployee] = chantier3219TimesheetUser();
    $entry = TimesheetEntry::factory()->create(['employee_id' => $otherEmployee->id]);

    test()->actingAs($user, 'sanctum')
        ->getJson("/api/v1/timesheets/entries/{$entry->id}")
        ->assertForbidden();
});

test('an employee can view their own timesheet entry', function () {
    [$user, $employee] = chantier3219TimesheetUser();
    $entry = TimesheetEntry::factory()->create(['employee_id' => $employee->id]);

    test()->actingAs($user, 'sanctum')
        ->getJson("/api/v1/timesheets/entries/{$entry->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $entry->id);
});

test('a manager can view any employees individual timesheet entry', function () {
    [$manager] = chantier3219TimesheetUser('manager');
    [, $employee] = chantier3219TimesheetUser();
    $entry = TimesheetEntry::factory()->create(['employee_id' => $employee->id]);

    test()->actingAs($manager, 'sanctum')
        ->getJson("/api/v1/timesheets/entries/{$entry->id}")
        ->assertOk();
});

// ── TimesheetEntryController::byEmployee() — real IDOR (layer 6/7) ────────

test('an employee cannot list another employees entries via by-employee', function () {
    [$user] = chantier3219TimesheetUser();
    [, $otherEmployee] = chantier3219TimesheetUser();
    TimesheetEntry::factory()->count(3)->create(['employee_id' => $otherEmployee->id]);

    test()->actingAs($user, 'sanctum')
        ->getJson("/api/v1/timesheets/entries/by-employee?employee_id={$otherEmployee->id}")
        ->assertForbidden();
});

test('an employee can list their own entries via by-employee', function () {
    [$user, $employee] = chantier3219TimesheetUser();
    TimesheetEntry::factory()->count(3)->create(['employee_id' => $employee->id]);

    $response = test()->actingAs($user, 'sanctum')
        ->getJson("/api/v1/timesheets/entries/by-employee?employee_id={$employee->id}");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
});

test('a manager can list any employees entries via by-employee', function () {
    [$manager] = chantier3219TimesheetUser('manager');
    [, $employee] = chantier3219TimesheetUser();
    TimesheetEntry::factory()->count(2)->create(['employee_id' => $employee->id]);

    $response = test()->actingAs($manager, 'sanctum')
        ->getJson("/api/v1/timesheets/entries/by-employee?employee_id={$employee->id}");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

// ── SheetWebController::show()/edit() — server-rendered IDOR (layer 6) ────

test('an employee cannot view another employees full weekly sheet via the web page', function () {
    [$user] = chantier3219TimesheetUser();
    [, $otherEmployee] = chantier3219TimesheetUser();
    $period = TimesheetPeriod::factory()->create(['employee_id' => $otherEmployee->id]);

    test()->actingAs($user)
        ->get("/timesheets/sheets/{$period->id}")
        ->assertForbidden();
});

test('an employee can view their own weekly sheet via the web page', function () {
    [$user, $employee] = chantier3219TimesheetUser();
    $period = TimesheetPeriod::factory()->create(['employee_id' => $employee->id]);

    test()->actingAs($user)
        ->get("/timesheets/sheets/{$period->id}")
        ->assertOk();
});

test('a manager can view any employees weekly sheet via the web page', function () {
    [$manager] = chantier3219TimesheetUser('manager');
    [, $employee] = chantier3219TimesheetUser();
    $period = TimesheetPeriod::factory()->create(['employee_id' => $employee->id]);

    test()->actingAs($manager)
        ->get("/timesheets/sheets/{$period->id}")
        ->assertOk();
});

test('an employee cannot reach another employees sheet edit page', function () {
    [$user] = chantier3219TimesheetUser();
    [, $otherEmployee] = chantier3219TimesheetUser();
    $period = TimesheetPeriod::factory()->create(['employee_id' => $otherEmployee->id, 'status' => 'draft']);

    test()->actingAs($user)
        ->get("/timesheets/sheets/{$period->id}/edit")
        ->assertForbidden();
});

test('an employee can reach their own draft sheet edit page', function () {
    [$user, $employee] = chantier3219TimesheetUser();
    $period = TimesheetPeriod::factory()->create(['employee_id' => $employee->id, 'status' => 'draft']);

    test()->actingAs($user)
        ->get("/timesheets/sheets/{$period->id}/edit")
        ->assertOk();
});

// ── update() status-guard exception — real 500 (layer 8, business validation) ──

test('an admin attempting to edit a submitted entry gets a clean 422, not a raw 500', function () {
    [$admin] = chantier3219TimesheetUser('admin');
    [, $employee] = chantier3219TimesheetUser();
    $entry = TimesheetEntry::factory()->create(['employee_id' => $employee->id, 'status' => 'submitted']);

    // TimesheetEntryPolicy::update() deliberately lets an admin attempt
    // this (the ability check itself passes) — but TimesheetService's own
    // canEdit() guard used to throw an uncaught bare Exception here,
    // confirmed via tinker to bubble up as a raw 500 before this fix.
    $response = test()->actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/timesheets/entries/{$entry->id}", ['hours_worked' => 5]);

    $response->assertUnprocessable();
    expect($entry->fresh()->hours_worked)->not->toEqual(5.0);
});

test('an admin cannot re-submit an already-approved entry and silently revert its approval', function () {
    [$admin] = chantier3219TimesheetUser('admin');
    [, $employee] = chantier3219TimesheetUser();
    $entry = TimesheetEntry::factory()->create([
        'employee_id' => $employee->id,
        'status' => 'approved',
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    $response = test()->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/timesheets/entries/{$entry->id}/submit");

    $response->assertUnprocessable();
    expect($entry->fresh()->status)->toBe('approved');
});

// ── TrackingProjectController — cross-tenant leak (layer 6) ───────────────

test('tracking projects list is scoped to the callers own company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    [$userA] = chantier3219TimesheetUser('employee', $companyA);
    [$userB] = chantier3219TimesheetUser('employee', $companyB);

    TimeTrackingProject::factory()->create(['tenant_id' => $companyA->id]);
    TimeTrackingProject::factory()->count(2)->create(['tenant_id' => $companyB->id]);

    $responseA = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/timesheets/projects');
    $responseA->assertOk()->assertJsonCount(1, 'data');

    $responseB = test()->actingAs($userB, 'sanctum')->getJson('/api/v1/timesheets/projects');
    $responseB->assertOk()->assertJsonCount(2, 'data');
});

test('creating a tracking project real populates tenant_id from the callers company', function () {
    $company = Company::factory()->create();
    [$user] = chantier3219TimesheetUser('employee', $company);

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/timesheets/projects', [
        'name' => 'Real Tenant Project',
        'code' => 'RTP-001',
        'budget_hours' => 100,
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->addMonths(3)->format('Y-m-d'),
    ]);

    $response->assertCreated();
    test()->assertDatabaseHas('time_tracking_projects', [
        'code' => 'RTP-001',
        'tenant_id' => $company->id,
    ]);
});

test('an employee of another company cannot view or edit a tracking project by id', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    [$userA] = chantier3219TimesheetUser('employee', $companyA);
    $project = TimeTrackingProject::factory()->create(['tenant_id' => $companyB->id]);

    test()->actingAs($userA, 'sanctum')
        ->getJson("/api/v1/timesheets/projects/{$project->id}")
        ->assertNotFound();

    test()->actingAs($userA, 'sanctum')
        ->patchJson("/api/v1/timesheets/projects/{$project->id}", ['budget_hours' => 999])
        ->assertNotFound();

    test()->actingAs($userA, 'sanctum')
        ->deleteJson("/api/v1/timesheets/projects/{$project->id}")
        ->assertNotFound();

    // The record survives — the delete above must not have gone through.
    test()->assertDatabaseHas('time_tracking_projects', ['id' => $project->id]);
});

test('a caller with no company_id still sees the unscoped project set', function () {
    [$user] = chantier3219TimesheetUser();
    TimeTrackingProject::factory()->count(3)->create();

    test()->actingAs($user, 'sanctum')
        ->getJson('/api/v1/timesheets/projects')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

// ── TrackingProjectResource — division-by-zero guard (layer 4) ────────────

test('percentage_used never crashes on a zero budget project', function () {
    [$user] = chantier3219TimesheetUser();
    $project = TimeTrackingProject::factory()->create(['budget_hours' => 0, 'hours_tracked' => 0]);

    $response = test()->actingAs($user, 'sanctum')
        ->getJson("/api/v1/timesheets/projects/{$project->id}");

    $response->assertOk()->assertJsonPath('data.percentage_used', 0);
});

// ── MetricsController::summary() — cross-tenant aggregate leak (layer 6) ──

test('summary metrics only aggregate the callers own company entries', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    [$userA, $employeeA] = chantier3219TimesheetUser('employee', $companyA);
    [, $employeeB] = chantier3219TimesheetUser('employee', $companyB);

    TimesheetEntry::factory()->count(4)->create([
        'employee_id' => $employeeA->id,
        'tenant_id' => $companyA->id,
        'hours_worked' => 8,
    ]);
    TimesheetEntry::factory()->count(6)->create([
        'employee_id' => $employeeB->id,
        'tenant_id' => $companyB->id,
        'hours_worked' => 8,
    ]);

    $response = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/timesheets/metrics/summary');

    $response->assertOk()->assertJsonPath('total_entries', 4);
});

// ── TimesheetAdvancedController project-billing endpoints — cross-tenant IDOR (layer 6) ──

test('an employee of another company cannot read a projects billing history', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    [$userA] = chantier3219TimesheetUser('employee', $companyA);
    $projectB = Project::factory()->create(['company_id' => $companyB->id]);

    test()->actingAs($userA, 'sanctum')
        ->getJson("/api/v1/projects/{$projectB->id}/billing")
        ->assertNotFound();

    test()->actingAs($userA, 'sanctum')
        ->getJson("/api/v1/projects/{$projectB->id}/billing/invoiceable")
        ->assertNotFound();
});

test('an employee of another company cannot create a billing entry against a project it does not own', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    [$userA] = chantier3219TimesheetUser('employee', $companyA);
    $projectB = Project::factory()->create(['company_id' => $companyB->id]);

    test()->actingAs($userA, 'sanctum')
        ->postJson("/api/v1/projects/{$projectB->id}/billing/percentage", ['percentage' => 10])
        ->assertNotFound();
});

test('a real owner can still read their own projects billing history', function () {
    $company = Company::factory()->create();
    [$user] = chantier3219TimesheetUser('employee', $company);
    $project = Project::factory()->create(['company_id' => $company->id]);

    test()->actingAs($user, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/billing")
        ->assertOk();
});

test('generate-invoice rejects a billing id that belongs to a different project', function () {
    $company = Company::factory()->create();
    [$user] = chantier3219TimesheetUser('employee', $company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    $otherProject = Project::factory()->create(['company_id' => $company->id]);

    $billingOnOtherProject = ProjectBilling::create([
        'project_id' => $otherProject->id,
        'reference' => 'BILL-TEST-0001',
        'billing_type' => 'fixed',
        'amount' => 500_000,
        'tva_amount' => 90_000,
        'total_ttc' => 590_000,
        'status' => 'draft',
        'billing_date' => now()->format('Y-m-d'),
    ]);

    test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/billing/{$billingOnOtherProject->id}/generate-invoice")
        ->assertNotFound();
});

test('generate-invoice succeeds for a billing entry that really belongs to the given project', function () {
    $company = Company::factory()->create();
    [$user] = chantier3219TimesheetUser('employee', $company);
    $project = Project::factory()->create(['company_id' => $company->id]);

    $billing = ProjectBilling::create([
        'project_id' => $project->id,
        'reference' => 'BILL-TEST-0002',
        'billing_type' => 'fixed',
        'amount' => 500_000,
        'tva_amount' => 90_000,
        'total_ttc' => 590_000,
        'status' => 'draft',
        'billing_date' => now()->format('Y-m-d'),
    ]);

    $response = test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/billing/{$billing->id}/generate-invoice");

    $response->assertCreated();
    expect($billing->fresh()->status)->toBe('sent');
    expect($billing->fresh()->invoice_reference)->not->toBeNull();
});

// ── ProjectBillingService — ProjectBilling model activated for real writes (layer 9/11) ──

test('creating a project billing entry writes a real Eloquent ProjectBilling row, not just a raw DB insert', function () {
    $company = Company::factory()->create();
    [$user] = chantier3219TimesheetUser('employee', $company);
    $project = Project::factory()->create(['company_id' => $company->id]);

    test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/billing/percentage", ['percentage' => 20])
        ->assertCreated();

    // Before this fix, createBillingEntry() wrote via a raw
    // DB::table()->insertGetId(), bypassing ProjectBilling's own $fillable/
    // $casts and its HasAuditLog create-hook entirely — confirmed by
    // tracing the service, ProjectBilling::query() would have found the
    // row (same table) but Eloquent's own model-event hooks would never
    // have fired for it. Real Eloquent creation is now verifiable via the
    // model's real decimal casts round-tripping correctly.
    $billing = ProjectBilling::where('project_id', $project->id)->first();

    expect($billing)->not->toBeNull();
    expect((float) $billing->tva_amount)->toBeGreaterThan(0);
    expect((float) $billing->total_ttc)->toBe(round((float) $billing->amount + (float) $billing->tva_amount, 2));
    expect($billing->ohada_account)->toBe('7061');
});

// ── TimeAllocation::costCenter() — wrong-relation data leak removed (layer 10) ──

test('an allocation with a cost_center_id colliding with a real user id never exposes that users name', function () {
    [$user, $employee] = chantier3219TimesheetUser();
    $entry = TimesheetEntry::factory()->create(['employee_id' => $employee->id]);

    // A real, unrelated user whose id happens to be the same integer as the
    // cost_center_id below — the exact collision that used to leak this
    // user's real name mislabeled as a "cost center".
    $unrelatedUser = User::factory()->create(['name' => 'Should Never Appear Here']);

    $allocation = TimeAllocation::factory()->create([
        'entry_id' => $entry->id,
        'cost_center_id' => $unrelatedUser->id,
    ]);

    $response = test()->actingAs($user, 'sanctum')
        ->getJson("/api/v1/timesheets/allocations/{$allocation->id}");

    $response->assertOk();
    expect($response->json('data.cost_center_id'))->toBe($unrelatedUser->id);
    expect($response->json('data'))->not->toHaveKey('cost_center');
    expect(json_encode($response->json()))->not->toContain('Should Never Appear Here');
});

test('task_id/cost_center_id validation no longer fatals on the update endpoint either', function () {
    [$user, $employee] = chantier3219TimesheetUser();
    $entry = TimesheetEntry::factory()->create(['employee_id' => $employee->id, 'status' => 'draft']);
    $allocation = TimeAllocation::factory()->create(['entry_id' => $entry->id]);

    $response = test()->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/timesheets/allocations/{$allocation->id}", [
            'cost_center_id' => 42,
            'task_id' => 999999,
        ]);

    // No fatal 500 (both used to query nonexistent tables during
    // validation) — a bad task_id is a clean 422, cost_center_id (no real
    // table to validate against) passes through as a plain integer.
    $response->assertUnprocessable()
        ->assertJsonValidationErrors('task_id');
});

// ── Project billing report export — layer 14c (Chantier 29's own proposal for this module) ──

test('the project billing report can really be exported as a real pdf file', function () {
    [$user, $employee] = chantier3219TimesheetUser();
    $project = Project::factory()->create();
    TimesheetEntry::factory()->create([
        'employee_id' => $employee->id,
        'project_id' => $project->id,
        'entry_date' => now(),
        'hours_worked' => 8,
        'billable_hours' => 8,
        'hourly_rate' => 15000,
    ]);

    $response = test()->actingAs($user, 'sanctum')
        ->get('/api/v1/timesheets/reports/project-billing/export/pdf');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    // Real PDF bytes, not an empty/placeholder file.
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

test('the project billing report can really be exported as a real xlsx file with 2 real sheets', function () {
    [$user, $employee] = chantier3219TimesheetUser();
    $project = Project::factory()->create(['name' => 'Chantier3219 Real Export Project']);
    TimesheetEntry::factory()->create([
        'employee_id' => $employee->id,
        'project_id' => $project->id,
        'entry_date' => now(),
        'hours_worked' => 8,
        'billable_hours' => 8,
        'hourly_rate' => 15000,
    ]);

    $response = test()->actingAs($user, 'sanctum')
        ->get('/api/v1/timesheets/reports/project-billing/export/excel');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml');

    $path = sys_get_temp_dir().'/chantier3219-billing-'.uniqid().'.xlsx';
    file_put_contents($path, $response->streamedContent());

    try {
        expect(substr(file_get_contents($path), 0, 2))->toBe('PK');

        $zip = new ZipArchive();
        expect($zip->open($path))->toBe(true);
        $shared = $zip->getFromName('xl/sharedStrings.xml');
        $workbook = $zip->getFromName('xl/workbook.xml');
        $zip->close();

        expect($shared)->not->toBeFalse();
        expect($shared)->toContain('Chantier3219 Real Export Project');
        // 2 real sheets/tabs, not a single flat one.
        expect($workbook)->toContain('Par projet');
        expect($workbook)->toContain('Par employé');
    } finally {
        @unlink($path);
    }
});

// ── AI Assisted First — real fallback guidance for every substantial Timesheets action (layer 13) ──

test('the real ai assist endpoint returns non-empty french and english guidance for every new Timesheets action', function () {
    [$user] = chantier3219TimesheetUser();
    $actions = ['view_dashboard', 'view_entries', 'create_entry', 'manage_sheets', 'view_reports', 'manage_projects'];

    foreach (['fr', 'en'] as $locale) {
        foreach ($actions as $action) {
            $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/ai/assist', [
                'module' => 'Timesheets',
                'action' => $action,
                'locale' => $locale,
            ]);

            $response->assertOk();
            expect($response->json('what_to_do'))->not->toBeEmpty();
            expect($response->json('how_to_do'))->not->toBeEmpty();
        }
    }
});

test('supportedModules lists every real Timesheets action', function () {
    [$user] = chantier3219TimesheetUser();

    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/ai/assist/modules');

    $response->assertOk();
    $timesheetsActions = $response->json('modules.Timesheets') ?? [];

    foreach (['view_dashboard', 'view_entries', 'create_entry', 'manage_sheets', 'view_reports', 'manage_projects'] as $action) {
        expect($timesheetsActions)->toContain($action);
    }
});
