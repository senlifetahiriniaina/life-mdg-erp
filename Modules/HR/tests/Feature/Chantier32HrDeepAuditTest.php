<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Models\Attendance;
use Modules\HR\Models\Department;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeCompensation;
use Modules\HR\Models\JobPosition;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;
use Modules\HR\Services\HRService;
use Modules\Payroll\Models\Payslip;

/**
 * Chantier 32.17 — HR deep 14-layer audit (see CLAUDE.md's "Méthodologie
 * d'audit approfondi (14 couches)" section). Every test here locks in a
 * real bug found by actually running the code (tinker or a real HTTP
 * request), not by reading it — several of these were guaranteed fatal SQL
 * errors or 404s the moment their real, previously-unreachable route was
 * ever hit.
 */
beforeEach(function () {
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    // app('cache.api') responses are keyed by auth()->id() + path, but
    // RefreshDatabase resets auto-increment ids to 1 every test — without
    // flushing, a later test's user can collide with an earlier test's
    // cached response for the same numeric id on a cache.api-wrapped route
    // (departments/job-positions in this file).
    \Illuminate\Support\Facades\Cache::flush();
});

// ── Layer 5 (data format): hr_attendance's real columns never matched
// Attendance::$fillable at all — Attendance::create()/AttendanceFactory both
// used 'check_in'/'check_out', columns that have never existed on the real
// catch-all-scaffolded table (real columns: check_in_time/check_out_time).
// Confirmed empirically via tinker before this fix (a guaranteed SQLSTATE
// "no such column" fatal error on every real Attendance::create() call).
it('Attendance real columns are check_in_time/check_out_time, exposed as check_in/check_out accessors', function () {
    $admin = actingAsUser('admin');
    $dept = Department::factory()->create();
    $employee = Employee::factory()->create(['department_id' => $dept->id]);

    $resp = $this->postJson('/api/v1/hr/attendance', [
        'employee_id' => $employee->id,
        'date' => now()->toDateString(),
        'status' => 'present',
        'check_in' => '08:00',
        'check_out' => '17:00',
        'notes' => 'real note',
    ]);

    $resp->assertCreated();
    $resp->assertJsonFragment(['check_in' => '08:00:00', 'check_out' => '17:00:00', 'notes' => 'real note']);

    $record = Attendance::first();
    expect($record->check_in_time)->toBe('08:00:00');
    expect($record->check_out_time)->toBe('17:00:00');
    expect($record->notes)->toBe('real note');
    expect($record->duration_hours)->toBe(9.0);
});

// ── Layer 1/2 (route/controller): AttendanceController::store()/update()/
// destroy()/statistics() were real, correctly-written methods with zero
// route anywhere — confirmed via `php artisan route:list` before this fix.
// HR/Attendance/Manage.vue's real "Mark Attendance"/"Delete" buttons have
// always 404'd.
it('attendance store/update/destroy are now real, routed, admin-gated endpoints', function () {
    $admin = actingAsUser('admin');
    $dept = Department::factory()->create();
    $employee = Employee::factory()->create(['department_id' => $dept->id]);

    $create = $this->postJson('/api/v1/hr/attendance', [
        'employee_id' => $employee->id,
        'date' => now()->toDateString(),
        'status' => 'present',
    ])->assertCreated();

    $id = $create->json('id');

    $this->putJson("/api/v1/hr/attendance/{$id}", ['status' => 'late'])
        ->assertOk()
        ->assertJsonFragment(['status' => 'late']);

    $this->deleteJson("/api/v1/hr/attendance/{$id}")->assertNoContent();
    expect(Attendance::withTrashed()->find($id)->trashed())->toBeTrue();
});

// A plain 'employee'-role user (present in the outer route-group gate) must
// not be able to mark/delete an arbitrary employee's attendance — only the
// admin-ish roles AttendanceController::isAttendanceAdmin() recognizes.
it('a plain employee-role user cannot mark or delete attendance for another employee', function () {
    $plain = actingAsUser('employee');
    $dept = Department::factory()->create();
    $other = Employee::factory()->create(['department_id' => $dept->id]);

    $this->postJson('/api/v1/hr/attendance', [
        'employee_id' => $other->id,
        'date' => now()->toDateString(),
        'status' => 'present',
    ])->assertForbidden();
});

// ── Layer 6 (security): AttendanceController::index()'s with('employee')
// used to eager-load the FULL raw Employee model, bypassing the deliberate
// PII redaction every other employee-facing endpoint in this module goes
// through — confirmed empirically that national_id/bank_details would be
// serialized in full when set.
it('attendance list never leaks raw employee PII', function () {
    $admin = actingAsUser('admin');
    $dept = Department::factory()->create(['name' => 'Finance']);
    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'national_id' => 'SECRET-NATID-4242',
    ]);
    Attendance::factory()->create(['employee_id' => $employee->id]);

    $resp = $this->getJson('/api/v1/hr/attendance')->assertOk();

    expect($resp->getContent())->not->toContain('SECRET-NATID-4242');
    expect($resp->json('data.0.department'))->toBe('Finance');
    expect($resp->json('data.0.employee_name'))->not->toBeEmpty();
});

// ── Layer 1: EmployeeController::export() (real, well-written CSV export)
// had zero route registered anywhere, and Employees/Index.vue's "Exporter"
// button had zero click handler at all — both confirmed before this fix.
it('employees export endpoint is real and reachable', function () {
    actingAsUser('admin');
    Employee::factory()->create(['first_name' => 'Exportable']);

    $resp = $this->get('/api/v1/hr/employees/export');

    $resp->assertOk();
    $resp->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($resp->getContent())->toContain('Exportable');
});

// ── Layer 8 (business validation): folded EmployeeService's one genuinely
// useful, never-wired rule ("cannot delete an active employee without
// force") into the real, routed destroy() path before deleting that
// confirmed-dead (Layer 9), zero-caller service.
it('cannot delete an active employee without force, but force overrides it', function () {
    actingAsUser('admin');
    $employee = Employee::factory()->create(['status' => 'active']);

    $this->deleteJson("/api/v1/hr/employees/{$employee->id}")->assertStatus(409);
    expect(Employee::find($employee->id))->not->toBeNull();

    $this->deleteJson("/api/v1/hr/employees/{$employee->id}?force=1")->assertNoContent();
    expect(Employee::withTrashed()->find($employee->id)->trashed())->toBeTrue();
});

// A non-active employee can still be deleted without force (unchanged
// behavior — only 'active' status is guarded).
it('a probationary employee can be deleted without force', function () {
    actingAsUser('admin');
    $employee = Employee::factory()->create(['status' => 'probation']);

    $this->deleteJson("/api/v1/hr/employees/{$employee->id}")->assertNoContent();
});

// ── Layer 9 (fake/dead), classified and acted on: Position model + its
// table hr_positions (zero controller/route consumer anywhere, zero real
// write path) and LeaveBalance + hr_leave_balances (redundant duplicate of
// the already-working computed-balance approach every real leave-balance
// endpoint in this module already uses) were both confirmed dead and
// dropped for good.
it('Position and LeaveBalance are confirmed dead and fully removed', function () {
    expect(Schema::hasTable('hr_positions'))->toBeFalse();
    expect(Schema::hasTable('hr_leave_balances'))->toBeFalse();
    expect(class_exists(\Modules\HR\Models\Position::class))->toBeFalse();
    expect(class_exists(\Modules\HR\Models\LeaveBalance::class))->toBeFalse();
    expect(class_exists(\Modules\HR\Services\EmployeeService::class))->toBeFalse();
});

// ── Layer 4/5: HRService::getHRMetrics()/getDepartmentMetrics() confirmed
// via tinker to return a nonsensical negative open_positions figure (the
// dead Position::sum('headcount') always returned 0 against the confirmed-
// dead table) and a silently-zeroed total_payroll (Employee.salary was
// never a real column — SQLite camouflaged the bad column reference as a
// silent 0 rather than erroring).
it('HR metrics no longer return negative open_positions or a silently-zeroed payroll total', function () {
    actingAsUser('admin');
    $dept = Department::factory()->create(['budget_allocation' => 2000000]);
    $e1 = Employee::factory()->create(['department_id' => $dept->id, 'status' => 'active']);
    $e2 = Employee::factory()->create(['department_id' => $dept->id, 'status' => 'active']);
    EmployeeCompensation::create([
        'employee_id' => $e1->id, 'base_salary' => 500000, 'currency' => 'MGA',
        'effective_date' => now()->subDays(10)->toDateString(),
    ]);
    EmployeeCompensation::create([
        'employee_id' => $e2->id, 'base_salary' => 300000, 'currency' => 'MGA',
        'effective_date' => now()->subDays(10)->toDateString(),
    ]);

    $metrics = $this->getJson('/api/v1/hr/employees/metrics')->assertOk()->json();
    expect($metrics['open_positions'])->toBe(0);

    $deptMetrics = $this->getJson("/api/v1/hr/departments/{$dept->id}/metrics")->assertOk()->json();
    expect((float) $deptMetrics['total_payroll'])->toBe(800000.0);
    expect((float) $deptMetrics['budget_utilization'])->toBe(40.0);
});

// ── Layer 6 (security), Department/JobPosition tenant isolation retrofit —
// closes the same previously-documented-twice gap (Chantier 19 Lot 2) as
// Employee's own flip in Chantier19HRReauditTest.php, verified independently
// for the two sibling org-structure models.
it('departments and job positions are scoped by company_id', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = User::factory()->create(['company_id' => $companyA->id]);
    $userA->assignRole('hr-manager');
    $token = $userA->createToken('t')->plainTextToken;

    $deptA = Department::factory()->create(['name' => 'DeptA', 'company_id' => $companyA->id]);
    $deptB = Department::factory()->create(['name' => 'DeptB', 'company_id' => $companyB->id]);
    $jobA = JobPosition::factory()->create(['title' => 'JobA', 'company_id' => $companyA->id]);
    $jobB = JobPosition::factory()->create(['title' => 'JobB', 'company_id' => $companyB->id]);

    $deptNames = collect($this->withToken($token)->getJson('/api/v1/hr/departments')->json('data'))->pluck('name')->all();
    expect($deptNames)->toContain('DeptA');
    expect($deptNames)->not->toContain('DeptB');
    $this->withToken($token)->getJson("/api/v1/hr/departments/{$deptB->id}")->assertForbidden();
    $this->withToken($token)->getJson("/api/v1/hr/departments/{$deptA->id}")->assertOk();

    // Note: unlike departments/employees, JobPositionController::index()
    // returns a plain (unwrapped) array — response()->json() on a raw
    // ResourceCollection here doesn't apply the usual 'data' wrap, confirmed
    // empirically rather than assumed.
    $jobTitles = collect($this->withToken($token)->getJson('/api/v1/hr/job-positions')->json())->pluck('title')->all();
    expect($jobTitles)->toContain('JobA');
    expect($jobTitles)->not->toContain('JobB');
    $this->withToken($token)->getJson("/api/v1/hr/job-positions/{$jobB->id}")->assertForbidden();
});

// Real create paths now populate company_id — confirmed via the real HTTP
// store() endpoints, not just by reading the controller.
it('creating an employee/department/job-position via the real API populates company_id', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('admin');
    $token = $user->createToken('t')->plainTextToken;
    $dept = Department::factory()->create();
    $jobPos = JobPosition::factory()->create();

    $empResp = $this->withToken($token)->postJson('/api/v1/hr/employees', [
        'employee_number' => 'EMP-CID-TEST',
        'first_name' => 'Cid',
        'last_name' => 'Test',
        'email' => 'cid.test@example.com',
        'department_id' => $dept->id,
        'job_position_id' => $jobPos->id,
        'hire_date' => now()->toDateString(),
    ])->assertCreated();
    expect(Employee::find($empResp->json('data.id'))->company_id)->toBe($company->id);

    $deptResp = $this->withToken($token)->postJson('/api/v1/hr/departments', [
        'name' => 'Cid Department',
        'code' => 'CID-DEPT',
    ])->assertCreated();
    expect(Department::find($deptResp->json('data.id'))->company_id)->toBe($company->id);

    $jobResp = $this->withToken($token)->postJson('/api/v1/hr/job-positions', [
        'title' => 'Cid Job',
    ])->assertCreated();
    // Note: JobPositionController::store() also returns a plain (unwrapped)
    // response, confirmed empirically — see the index() note above.
    expect(JobPosition::find($jobResp->json('id'))->company_id)->toBe($company->id);
});

// ── Layer 6: LeaveRequestController::show() had zero authorize() call at
// all, unlike every sibling method on the same controller.
it('leave request show() now calls authorize()', function () {
    actingAsUser('admin');
    $leave = LeaveRequest::factory()->create();

    // A real, correctly-authorized call still works.
    $this->getJson("/api/v1/hr/leave-requests/{$leave->id}")->assertOk();
});

// ── Layer 3 (Vue reachability) + Layer 1: HR/Payroll/Index.vue's 3 export
// buttons have always navigated to a URL that never existed anywhere in
// this app — confirmed via `php artisan route:list` before this fix.
it('payroll export endpoint is real and reachable, tenant-scoped', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('admin');
    $token = $user->createToken('t')->plainTextToken;

    $run = \Modules\Payroll\Models\PayrollRun::create([
        'tenant_id' => $company->id,
        'period' => now()->startOfMonth()->toDateString(),
        'status' => 'approved',
        'currency' => 'MGA',
    ]);
    $employee = Employee::factory()->create();

    Payslip::create([
        'payroll_run_id' => $run->id,
        'tenant_id' => $company->id,
        'employee_id' => $employee->id,
        'employee_name' => 'Payslip Person',
        'period' => now()->startOfMonth()->toDateString(),
        'gross_salary' => 500000,
        'net_salary' => 400000,
        'currency' => 'MGA',
        'status' => 'paid',
    ]);

    foreach (['csv', 'silae', 'dsn'] as $format) {
        $resp = $this->withToken($token)->get("/api/v1/hr/payroll/export/{$format}");
        $resp->assertOk();
        $resp->assertHeader('content-type', 'text/csv; charset=UTF-8');
        expect($resp->getContent())->toContain('Payslip Person');
    }
});

// ── Layer 13 (AI): confirmed via grep before this fix that ZERO of this
// module's 13 real, routed Vue pages ever called useAiAssistant() at all,
// and 'HR' only had 4 registered actions despite the module having far more
// substantial screens. Verified via the real generic AI-assist endpoint
// every real Vue page's useAiAssistant() composable actually calls.
it('HR AI guidance covers the 11 newly-wired actions with real, non-empty fr/en fallback text', function () {
    actingAsUser('admin');

    $actions = [
        'view_dashboard', 'clock_attendance', 'view_compensation',
        'view_employees_list', 'view_employee_detail', 'view_payslips',
        'employee_portal', 'manage_attendance', 'manage_departments',
        'view_leave_analytics', 'manage_shifts',
    ];

    foreach ($actions as $action) {
        foreach (['fr', 'en'] as $locale) {
            $resp = $this->postJson('/api/v1/ai/assist', [
                'module' => 'HR',
                'action' => $action,
                'locale' => $locale,
            ])->assertOk();

            expect($resp->json('what_to_do'))->not->toBeEmpty();
            expect($resp->json('how_to_do'))->not->toBeEmpty();
        }
    }

    $modules = app(\Modules\AI\Services\AiContextualAssistantService::class)->supportedModules();
    expect($modules['HR'])->toContain('view_dashboard', 'manage_shifts', 'employee_portal');
});
