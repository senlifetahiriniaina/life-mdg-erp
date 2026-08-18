<?php

declare(strict_types=1);

use App\Models\User;
use Modules\HR\Models\Employee;
use Modules\Timesheets\Models\TimesheetEntry;
use Modules\Timesheets\Models\TimesheetPeriod;

/**
 * Chantier 8.4 (Timesheets): Sheets/*.vue and Reports/*.vue are real, routed
 * pages that called a `timesheets/sheets*` / `timesheets/reports/*` API
 * scheme with no controller behind it at all — every request 404'd (API)
 * or rendered with undefined props (web, since routes/web.php had zero
 * auth/module middleware and rendered Show/Form from bare closures).
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function timesheetSheetsTestUser(string $role = 'employee'): array
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create();
    $user->assignRole($role);
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    return [$user, $employee];
}

test('an unauthenticated visitor cannot reach the timesheets dashboard or sheets pages', function () {
    test()->get('/timesheets/dashboard')->assertRedirect('/login');
    test()->get('/timesheets/sheets')->assertRedirect('/login');
});

test('an employee can create, list, and submit their own sheet end to end', function () {
    [$user, $employee] = timesheetSheetsTestUser();

    $store = test()->actingAs($user, 'sanctum')->postJson('/api/v1/timesheets/sheets', [
        'period_start' => now()->startOfWeek()->format('Y-m-d'),
        'period_end' => now()->endOfWeek()->format('Y-m-d'),
    ]);
    $store->assertCreated();
    $sheetId = $store->json('id');
    expect($sheetId)->not->toBeNull();
    expect($store->json('status'))->toBe('draft');

    TimesheetEntry::factory()->create([
        'employee_id' => $employee->id,
        'entry_date' => now()->startOfWeek()->addDay(),
        'hours_worked' => 8,
        'billable_hours' => 6,
        'status' => 'draft',
    ]);

    $index = test()->actingAs($user, 'sanctum')->getJson('/api/v1/timesheets/sheets');
    $index->assertOk();
    expect($index->json('data'))->not->toBeEmpty();
    expect($index->json('total'))->toBeGreaterThanOrEqual(1);

    $submit = test()->actingAs($user, 'sanctum')->postJson("/api/v1/timesheets/sheets/{$sheetId}/submit");
    $submit->assertOk();
    expect($submit->json('status'))->toBe('submitted');
    expect((float) $submit->json('total_hours'))->toBe(8.0);
});

test('a manager can approve and reject a submitted sheet by id', function () {
    [, $employee] = timesheetSheetsTestUser();
    [$manager] = timesheetSheetsTestUser('manager');

    $period = TimesheetPeriod::factory()->submitted()->create(['employee_id' => $employee->id]);

    $approve = test()->actingAs($manager, 'sanctum')->postJson("/api/v1/timesheets/sheets/{$period->id}/approve");
    $approve->assertOk();
    expect($period->fresh()->status)->toBe('approved');

    $period2 = TimesheetPeriod::factory()->submitted()->create(['employee_id' => $employee->id]);
    $reject = test()->actingAs($manager, 'sanctum')->postJson("/api/v1/timesheets/sheets/{$period2->id}/reject", [
        'reason' => 'Missing details',
    ]);
    $reject->assertOk();
    expect($period2->fresh()->status)->toBe('rejected');
});

test('my-sheets only returns the authenticated employee own periods', function () {
    [$user, $employee] = timesheetSheetsTestUser();
    [, $otherEmployee] = timesheetSheetsTestUser();

    TimesheetPeriod::factory()->count(2)->create(['employee_id' => $employee->id]);
    TimesheetPeriod::factory()->count(3)->create(['employee_id' => $otherEmployee->id]);

    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/timesheets/sheets/my-sheets');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

test('the sheet web page renders with real employee and entries props', function () {
    [$user, $employee] = timesheetSheetsTestUser();
    $period = TimesheetPeriod::factory()->create(['employee_id' => $employee->id]);
    TimesheetEntry::factory()->create([
        'employee_id' => $employee->id,
        'entry_date' => $period->period_start,
        'hours_worked' => 5,
    ]);

    $response = test()->actingAs($user)->get("/timesheets/sheets/{$period->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        // false = skip the on-disk existence check: inertia-laravel's
        // FileViewFinder searches inertia.pages.paths flatly and doesn't
        // replicate resources/js/app.js's custom module-prefix-stripping
        // resolve() logic, so module-nested pages (real file at
        // Modules/Timesheets/resources/js/Pages/Sheets/Show.vue, rendered
        // as "Timesheets/Sheets/Show") false-fail here — same established
        // pattern already used throughout AccountingScreensWebTest.php.
        ->component('Timesheets/Sheets/Show', false)
        ->where('sheet.id', $period->id)
        ->where('sheet.employee.id', $employee->id)
        ->has('entries', 1)
    );
});

test('the three timesheets reports endpoints return real aggregated data', function () {
    [$user, $employee] = timesheetSheetsTestUser('manager');

    TimesheetEntry::factory()->create([
        'employee_id' => $employee->id,
        'entry_date' => now()->subDays(2),
        'hours_worked' => 8,
        'billable_hours' => 6,
        'hourly_rate' => 10000,
    ]);

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/timesheets/reports/project-billing')
        ->assertOk()
        ->assertJsonStructure(['total_billable_hours', 'total_billable_amount', 'by_project', 'by_employee_project']);

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/timesheets/reports/employee-hours')
        ->assertOk()
        ->assertJsonStructure(['total_hours', 'billable_hours', 'by_employee', 'by_project']);

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/timesheets/reports/utilization')
        ->assertOk()
        ->assertJsonStructure(['avg_utilization', 'utilization_ranges', 'by_employee', 'by_department']);
});
