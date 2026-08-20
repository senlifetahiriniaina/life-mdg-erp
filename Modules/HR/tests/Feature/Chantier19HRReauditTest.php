<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Modules\HR\Models\Department;
use Modules\HR\Models\Employee;
use Modules\HR\Models\JobPosition;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;
use Modules\HR\Services\EmployeeManagementService;
use Modules\HR\Services\HrDashboardService;
use Modules\Payroll\Models\Payslip;

/**
 * Chantier 19 (HR) — second re-verification pass, empirical-execution
 * methodology (see CLAUDE.md). Every test here locks in a real bug found
 * by actually running the code (not just reading it) — code reading alone
 * cannot detect a Carbon 3 sign-convention regression, a cache driver that
 * silently doesn't support tagging, or a route-model-binding parameter
 * name mismatch caused by an English irregular plural.
 */
beforeEach(function () {
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
});

// ── Bug 1+3: EmployeeManagementService::clearCache() used Cache::tags(),  ──
// which fatals with this app's real default cache driver (CACHE_STORE=file
// in .env, which doesn't support tagging) — every onboard/completeOnboarding/
// terminate call was a guaranteed BadMethodCallException in production.
it('onboarding/completion/termination succeed against the real file cache driver', function () {
    config(['cache.default' => 'file']);

    $admin = User::factory()->create();
    $admin->assignRole('hr-manager');
    $dept = Department::factory()->create();
    $jp = JobPosition::factory()->create();
    $token = $admin->createToken('t')->plainTextToken;

    $onboardResp = $this->withToken($token)->postJson('/api/v1/hr/employees/onboard', [
        'first_name' => 'Test',
        'last_name' => 'Onboard',
        'email' => 'test.onboard.'.uniqid().'@example.com',
        'hire_date' => now()->toDateString(),
        'department_id' => $dept->id,
        'job_position_id' => $jp->id,
    ]);
    $onboardResp->assertCreated();
    $employeeId = $onboardResp->json('employee_id');
    expect($onboardResp->json('status'))->toBe('onboarding_started');

    $profileResp = $this->withToken($token)->getJson("/api/v1/hr/employees/{$employeeId}/profile");
    $profileResp->assertOk();
    expect($profileResp->json('status'))->toBe('onboarding');

    $completeResp = $this->withToken($token)->postJson("/api/v1/hr/employees/{$employeeId}/complete-onboarding");
    $completeResp->assertOk();
    expect($completeResp->json('status'))->toBe('active');

    // Cache invalidation must actually work (not just "not crash") — a
    // stale cached profile would still show 'onboarding' here.
    $profileAfter = $this->withToken($token)->getJson("/api/v1/hr/employees/{$employeeId}/profile");
    expect($profileAfter->json('status'))->toBe('active');

    $terminateResp = $this->withToken($token)->postJson("/api/v1/hr/employees/{$employeeId}/terminate", [
        'reason' => 'test reason',
    ]);
    $terminateResp->assertOk();
    expect($terminateResp->json('status'))->toBe('terminated');
});

// ── Bug 2: EmployeeManagementService::calculateTenure() went negative ──────
// Carbon 3 changed diffInDays()'s $absolute default from true to false;
// now()->diffInDays($pastDate) returns other-this = a negative number for
// any real (past) hire date.
it('employee profile tenure_days is positive for a real past hire date', function () {
    $admin = User::factory()->create();
    $admin->assignRole('hr-manager');
    $employee = Employee::factory()->create(['hire_date' => now()->subYears(2)->toDateString()]);
    $token = $admin->createToken('t')->plainTextToken;

    $resp = $this->withToken($token)->getJson("/api/v1/hr/employees/{$employee->id}/profile");
    $resp->assertOk();

    expect($resp->json('tenure_days'))->toBeGreaterThan(700); // ~2 years, definitely not negative
});

it('EmployeeManagementService::onboardEmployee/getEmployeeProfile round-trip has non-negative tenure directly', function () {
    $svc = app(EmployeeManagementService::class);
    $dept = Department::factory()->create();
    $jp = JobPosition::factory()->create();

    $res = $svc->onboardEmployee([
        'first_name' => 'Direct',
        'last_name' => 'Call',
        'email' => 'direct.call.'.uniqid().'@example.com',
        'hire_date' => now()->subMonths(6)->toDateString(),
        'department_id' => $dept->id,
        'job_position_id' => $jp->id,
    ]);

    $profile = $svc->getEmployeeProfile($res['employee_id']);
    expect($profile['tenure_days'])->toBeGreaterThan(150);
});

// ── Bug 3: HrDashboardService avg_tenure_months went negative ─────────────
// Same Carbon 3 sign-convention bug, independently present in the
// dashboard's aggregate KPI (now()->diffInMonths($pastDate)).
it('dashboard avg_tenure_months is non-negative for active employees with a real hire date', function () {
    Employee::factory()->create(['status' => 'active', 'hire_date' => now()->subYears(3)->toDateString()]);
    Employee::factory()->create(['status' => 'active', 'hire_date' => now()->subMonths(8)->toDateString()]);

    $stats = app(HrDashboardService::class)->getStats();

    expect($stats['avg_tenure_months'])->toBeGreaterThan(0);
    expect($stats['open_positions'])->toBeGreaterThanOrEqual(0);
});

it('HR dashboard API endpoint returns non-negative avg_tenure_months end to end', function () {
    $admin = User::factory()->create();
    $admin->assignRole('hr-manager');
    Employee::factory()->create(['status' => 'active', 'hire_date' => now()->subYears(1)->toDateString()]);
    $token = $admin->createToken('t')->plainTextToken;

    $resp = $this->withToken($token)->getJson('/api/v1/hr/dashboard');
    $resp->assertOk();
    expect($resp->json('stats.avg_tenure_months'))->toBeGreaterThanOrEqual(0);
});

// ── Bug 4: EmployeeWebController::payroll() had zero tenant scoping ───────
// An hr-manager from Company A could see every other company's payslips
// (names, gross/net salary) on the real /hr/payroll Inertia page.
it('HR payroll web page only shows the acting user company own payslips', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $userA = User::factory()->create(['company_id' => $companyA->id]);
    $userA->assignRole('hr-manager');

    Payslip::factory()->create([
        'tenant_id' => $companyA->id,
        'employee_id' => 1,
        'employee_name' => 'Company A Employee',
        'period' => now()->startOfMonth(),
        'status' => 'paid',
    ]);
    Payslip::factory()->create([
        'tenant_id' => $companyB->id,
        'employee_id' => 2,
        'employee_name' => 'Company B Employee',
        'period' => now()->startOfMonth(),
        'status' => 'paid',
    ]);

    $this->actingAs($userA);
    $resp = $this->get('/hr/payroll');
    $resp->assertOk();

    $props = $resp->viewData('page')['props'];
    expect($props['stats']['total'])->toBe(1);
    $names = collect($props['records']['data'])->pluck('employee_name')->all();
    expect($names)->toBe(['Company A Employee']);
});

// ── Bug 5+6+7: Route::apiResource('leaves', LeaveController::class) with  ──
// no ->parameters() override let Laravel derive its route-model-binding
// wildcard from Str::singular('leaves') — the English word "leaf" (plural
// of "leaf", not of "leave"), which can never implicitly bind to any of
// LeaveController's $leaveRequest-typed parameters. Every dynamic route on
// this resource (update/destroy, and the missing approve/reject) silently
// received a fresh, unbound LeaveRequest — update()/fresh() both quietly
// no-op against a non-existent model (Eloquent's exists-check-first
// behavior), so the request "succeeded" with HTTP 200 while never touching
// the real row. 'show' was also dropped — LeaveController has no show()
// method at all, a second, independent "call to undefined method" landmine
// that was never hit only because no real page calls it.
it('Leaves/Index.vue admin approve endpoint actually persists the approval', function () {
    $manager = User::factory()->create();
    $manager->assignRole('manager');
    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create(['is_active' => true]);
    $leave = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'pending',
    ]);
    $token = $manager->createToken('t')->plainTextToken;

    $countBefore = LeaveRequest::count();
    $resp = $this->withToken($token)->postJson("/api/v1/hr/leaves/{$leave->id}/approve");
    $resp->assertOk();

    expect($leave->fresh()->status)->toBe('approved');
    // No ghost row silently inserted by an unbound model's update()->save().
    expect(LeaveRequest::count())->toBe($countBefore);
});

it('Leaves/Index.vue admin reject endpoint actually persists the rejection', function () {
    $manager = User::factory()->create();
    $manager->assignRole('manager');
    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create(['is_active' => true]);
    $leave = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'pending',
    ]);
    $token = $manager->createToken('t')->plainTextToken;

    $resp = $this->withToken($token)->postJson("/api/v1/hr/leaves/{$leave->id}/reject", [
        'rejection_reason' => 'not enough coverage',
    ]);
    $resp->assertOk();

    expect($leave->fresh()->status)->toBe('rejected');
});

it('Leaves/Index.vue admin delete endpoint actually deletes the real record', function () {
    $manager = User::factory()->create();
    $manager->assignRole('manager');
    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create(['is_active' => true]);
    $leave = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'pending',
    ]);
    $token = $manager->createToken('t')->plainTextToken;

    $resp = $this->withToken($token)->deleteJson("/api/v1/hr/leaves/{$leave->id}");
    $resp->assertNoContent();

    // LeaveRequest uses SoftDeletes — the row still physically exists with
    // deleted_at set, not a hard delete.
    $this->assertSoftDeleted('hr_leave_requests', ['id' => $leave->id]);
});

it('the leaves resource route-model-binding parameter name is leaveRequest, not leaf', function () {
    $route = app('router')->getRoutes()->match(
        \Illuminate\Http\Request::create('/api/v1/hr/leaves/1/approve', 'POST')
    );

    expect($route->parameterNames())->toBe(['leaveRequest']);
});

// ── Bug 8: HR/Attendance/Index.vue's self-service "request leave" quick ───
// action posts to /api/v1/hr/me/leave-requests, but only
// self-service/leave-requests was ever registered — a guaranteed 404 on
// every real submission from that page.
it('employee self-service can submit a leave request at the URL the real page calls', function () {
    $empUser = User::factory()->create();
    $empUser->assignRole('employee');
    Employee::factory()->create(['user_id' => $empUser->id]);
    $leaveType = LeaveType::factory()->create(['is_active' => true]);
    $token = $empUser->createToken('t')->plainTextToken;

    $resp = $this->withToken($token)->postJson('/api/v1/hr/me/leave-requests', [
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addWeek()->addDays(1)->toDateString(),
        'reason' => 'family event',
    ]);

    $resp->assertCreated();
    $this->assertDatabaseHas('hr_leave_requests', ['reason' => 'family event', 'status' => 'pending']);
});

// ── Bug 9: HRAiAssistController::assist() read the phantom users.role  ────
// column (real DB column, in $fillable, but never populated by any real
// registration/seeding path — DemoSeeder only ever calls ->syncRoles()
// against Spatie) instead of the real Spatie role, silently defeating the
// AI guidance's role-aware tone/depth for every caller.
it('HR AI assist endpoint passes the real Spatie role, not the phantom users.role column', function () {
    $captured = (object) ['role' => null];
    $spy = Mockery::mock(\Modules\AI\Services\AiContextualAssistantService::class);
    $spy->shouldReceive('getGuidance')
        ->once()
        ->andReturnUsing(function ($module, $action, $context, $locale, $userRole) use ($captured) {
            $captured->role = $userRole;

            return ['enabled' => false, 'what_to_do' => 'stub'];
        });
    $this->app->instance(\Modules\AI\Services\AiContextualAssistantService::class, $spy);

    $manager = User::factory()->create();
    $manager->assignRole('manager');
    expect($manager->role)->toBeNull(); // the phantom column really is unpopulated
    $token = $manager->createToken('t')->plainTextToken;

    // Note: the real, registered URL is genuinely api/v1/hr/v1/hr/ai/assist —
    // RouteServiceProvider already prefixes the whole file with api/v1/hr,
    // and this controller's own route group adds a second prefix('v1/hr') on
    // top, a repo-wide convention (every module's routes/api.php has at
    // least one similar double-prefixed block) that's out of this HR-scoped
    // chantier's remit to fix — the real frontend AI-assist composable calls
    // the separate global POST /api/v1/ai/assist endpoint instead, so this
    // per-module endpoint has no real UI consumer regardless of its URL.
    $resp = $this->withToken($token)->postJson('/api/v1/hr/v1/hr/ai/assist', ['action' => 'view_dashboard']);
    $resp->assertOk();

    expect($captured->role)->toBe('manager');
});

// ── Regression guard: self-service PII masking (Chantier 8.3hp) is still ──
// actually in effect, verified empirically rather than trusted from a
// prior chantier's own claim.
it('self-service profile never leaks raw bank details/national id/passport number', function () {
    $empUser = User::factory()->create();
    $empUser->assignRole('employee');
    $employee = Employee::factory()->create([
        'user_id' => $empUser->id,
        'national_id' => 'SECRET-NATID-12345',
        'passport_number' => 'SECRET-PASSPORT-99999',
        'bank_details' => ['iban' => 'MG1234567890123456789012345', 'bank' => 'BOA'],
    ]);
    $token = $empUser->createToken('t')->plainTextToken;

    $resp = $this->withToken($token)->getJson('/api/v1/hr/me');
    $resp->assertOk();

    $raw = $resp->getContent();
    expect($raw)->not->toContain('SECRET-NATID-12345');
    expect($raw)->not->toContain('SECRET-PASSPORT-99999');
    expect($raw)->not->toContain('MG1234567890123456789012345');
    expect($resp->json())->not->toHaveKey('national_id');
    expect($resp->json())->not->toHaveKey('passport_number');
    expect($resp->json())->not->toHaveKey('bank_details');
});

// ── Documented gap (see CLAUDE.md), not fixed in this lot — locked in as a
// standing regression fixture so a future chantier can flip these
// expectations once real per-company scoping is added, rather than
// silently rediscovering the same gap again.
it('documents: HR employees/departments have no company-based tenant isolation at all', function () {
    expect(\Illuminate\Support\Facades\Schema::hasColumn('hr_employees', 'company_id'))->toBeFalse();
    expect(\Illuminate\Support\Facades\Schema::hasColumn('hr_departments', 'company_id'))->toBeFalse();

    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = User::factory()->create(['company_id' => $companyA->id]);
    $userA->assignRole('hr-manager');

    Employee::factory()->create(['first_name' => 'FromCompanyA']);
    Employee::factory()->create(['first_name' => 'FromCompanyB']);

    $token = $userA->createToken('t')->plainTextToken;
    $resp = $this->withToken($token)->getJson('/api/v1/hr/employees');
    $names = collect($resp->json('data'))->pluck('first_name')->all();

    // Both companies' employees are visible — the confirmed, documented gap.
    expect($names)->toContain('FromCompanyA');
    expect($names)->toContain('FromCompanyB');
});
