<?php

use Illuminate\Support\Facades\Route;
use Modules\HR\Http\Controllers\Api\AttendanceBiometricController;
use Modules\HR\Http\Controllers\Api\AttendanceController;
use Modules\HR\Http\Controllers\Api\CompensationController;
use Modules\HR\Http\Controllers\Api\DepartmentController;
use Modules\HR\Http\Controllers\Api\DocumentAlertController;
use Modules\HR\Http\Controllers\Api\EmployeeController;
use Modules\HR\Http\Controllers\Api\EmployeeManagementController;
use Modules\HR\Http\Controllers\Api\EmployeePortalController;
use Modules\HR\Http\Controllers\Api\EmployeeSelfServiceController;
use Modules\HR\Http\Controllers\Api\HrAIController;
use Modules\HR\Http\Controllers\Api\HrDashboardController;
use Modules\HR\Http\Controllers\Api\JobPositionController;
use Modules\HR\Http\Controllers\Api\LeaveController;
use Modules\HR\Http\Controllers\Api\LeaveRequestController;
use Modules\HR\Http\Controllers\Api\LeaveTypeController;
use Modules\HR\Http\Controllers\Api\PayrollExportController;
use Modules\HR\Http\Controllers\Api\SalaryBandController;
use Modules\HR\Http\Controllers\Api\SkillController;

// Default: Simple GET throttle (1000 req/min) — overridden for specific endpoint groups
// Chantier 8.3 (HR): this entire group had zero module/role gating (identical to the
// Inventory hole fixed earlier in this chantier) — any authenticated user of any tenant
// could create/update/delete departments, job positions, leave types, and salary bands.
// 'employee' is included deliberately (this app's broad "every non-delete permission
// across every module" role) so self-service routes (me/*, employee-portal/*, attendance
// clock-in/out, documents) that live in this same group stay reachable for regular staff;
// per-resource authorize() calls on the write endpoints provide the finer-grained gate.
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:HR', 'role:employee,hr-manager,payroll-officer,manager,admin', 'throttle:simple_get'])->group(function () {
    // Employee routes - custom routes first to avoid being shadowed by apiResource
    Route::middleware('cache.api:1')->group(function () {
        Route::get('employees/by-department/{department}', [EmployeeController::class, 'byDepartment']);
        Route::get('employees/metrics', [EmployeeController::class, 'metrics']);
        // Chantier 32.17 (HR deep 14-layer audit): EmployeeController::export()
        // is a real, well-written CSV export method (proper quoting/escaping)
        // that has had zero route anywhere since it was written — confirmed via
        // `php artisan route:list`. HR/Employees/Index.vue's "Exporter" button
        // (wired to this endpoint by this same chantier) was previously a dead
        // button with no click handler at all. Registered before the
        // apiResource 'show' route below so "export" isn't swallowed as an
        // {employee} id, matching the 'salary-bands/equity' precedent already
        // used in this file.
        Route::get('employees/export', [EmployeeController::class, 'export']);
        Route::apiResource('employees', EmployeeController::class)->only(['index', 'show'])->names('api.employees');
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::apiResource('employees', EmployeeController::class)->only(['store', 'update', 'destroy'])->names('api.employees');
        Route::post('employees/{employee}/skills', [SkillController::class, 'addEmployeeSkill']);
    });
    // Chantier 8.3: real method, matched by no route anywhere.
    Route::get('employees/{employee}/skills', [SkillController::class, 'employeeSkills']);

    // Chantier 8.3: EmployeeManagementController — onboarding/offboarding
    // workflow (checklist + status transition), distinct from
    // EmployeeController's plain CRUD above.
    Route::get('employees/{employee}/profile', [EmployeeManagementController::class, 'profile']);
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('employees/onboard', [EmployeeManagementController::class, 'onboard']);
        Route::post('employees/{employee}/complete-onboarding', [EmployeeManagementController::class, 'completeOnboarding']);
        Route::post('employees/{employee}/terminate', [EmployeeManagementController::class, 'terminate']);
    });

    // Department routes — never changes during session (1-hour cache)
    Route::middleware('cache.api:60')->group(function () {
        Route::apiResource('departments', DepartmentController::class)->only(['index', 'show']);
        Route::get('departments/{department}/metrics', [DepartmentController::class, 'metrics']);
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::apiResource('departments', DepartmentController::class)->only(['store', 'update', 'destroy']);
    });

    // Job positions (basic org structure — not to be confused with recruitment job postings)
    Route::middleware('cache.api:15')->group(function () {
        Route::apiResource('job-positions', JobPositionController::class)->only(['index', 'show']);
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::apiResource('job-positions', JobPositionController::class)->only(['store', 'update', 'destroy']);
    });

    // Leave routes - custom routes first to avoid being shadowed by apiResource
    Route::middleware('cache.api:5')->group(function () {
        Route::get('leave-requests/pending', [LeaveRequestController::class, 'pending']);
        Route::apiResource('leave-requests', LeaveRequestController::class)->only(['index', 'show']);
        // Chantier 19 (HR): Route::apiResource('leaves', ...) with no explicit
        // ->parameters() override lets Laravel derive the route-model-binding
        // parameter name from Str::singular('leaves') — which is the English
        // word "leaf" (plural of "leaf", not of "leave"), not "leave"/
        // "leaveRequest". Every dynamically-bound "leaves/{id}/..." route
        // therefore captured a {leaf} parameter that could never implicitly
        // bind to any of LeaveController's $leaveRequest-typed parameters —
        // confirmed empirically: PUT/DELETE/approve/reject on this resource
        // silently received a fresh, unbound LeaveRequest instance instead of
        // the real record (update()/fresh() both no-op against a
        // non-existent model, so the request "succeeds" with HTTP 200 and an
        // empty body while never touching the real row). Also dropped 'show'
        // here — LeaveController has no show() method at all (confirmed via
        // grep and unused by any real page), so it was a second, independent
        // "call to undefined method" landmine on this same route.
        Route::apiResource('leaves', LeaveController::class)->parameters(['leaves' => 'leaveRequest'])->only(['index']);
        Route::apiResource('leave-types', LeaveTypeController::class)->only(['index', 'show']);
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve']);
        Route::post('leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject']);
        Route::apiResource('leave-requests', LeaveRequestController::class)->only(['store', 'update', 'destroy']);
        // Chantier 19 (HR): LeaveController::approve()/reject() are real, correctly
        // implemented methods (both already call authorize('approve', ...)) that
        // Modules/HR/resources/js/Pages/Leaves/Index.vue's admin approve/reject
        // buttons already call (POST /api/v1/hr/leaves/{id}/approve|reject) — but
        // no route registered either verb, a guaranteed 404 on every click,
        // confirmed empirically. {leaveRequest} (not {leave}/{leaf}) to match
        // the controller's real parameter name — see the ->parameters()
        // override above for the full explanation of why the plain
        // Str::singular('leaves') wildcard is unusable here. Registered
        // before the apiResource below so "approve"/"reject" aren't
        // swallowed as a {leaveRequest} route parameter.
        Route::post('leaves/{leaveRequest}/approve', [LeaveController::class, 'approve']);
        Route::post('leaves/{leaveRequest}/reject', [LeaveController::class, 'reject']);
        Route::apiResource('leaves', LeaveController::class)->parameters(['leaves' => 'leaveRequest'])->only(['store', 'update', 'destroy']);
        Route::apiResource('leave-types', LeaveTypeController::class)->only(['store', 'update', 'destroy']);
    });

    // Attendance routes
    // Chantier 32.17 (HR deep 14-layer audit): 'statistics' registered before
    // the {id} routes below so it can't ever be swallowed as an id segment.
    Route::get('attendance/status', [AttendanceController::class, 'status']);
    Route::get('attendance/statistics', [AttendanceController::class, 'statistics']);
    Route::get('attendance', [AttendanceController::class, 'index']);
    Route::get('me/attendance', [AttendanceController::class, 'ownAttendance']);
    Route::get('employees/{employee}/attendance', [AttendanceController::class, 'employeeAttendance']);
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('attendance/clock-in', [AttendanceController::class, 'clockIn']);
        Route::post('attendance/clock-out', [AttendanceController::class, 'clockOut']);
        // Chantier 32.17: AttendanceController::store()/update()/destroy() were
        // real, correctly-written methods with zero route anywhere — confirmed
        // empirically that the real, routed admin CRUD page
        // (HR/Attendance/Manage.vue) has always 404'd on its "Mark Attendance"
        // (POST), "Delete" (DELETE) actions, and would 404 on an "Edit" (PUT)
        // action too had one ever been wired on the frontend. Both methods now
        // also self-gate to admin-ish roles (see AttendanceController's own
        // isAttendanceAdmin() helper) since marking/deleting an arbitrary
        // employee's attendance is not something the broad 'employee' role
        // (present in this route group) should be able to do.
        Route::post('attendance', [AttendanceController::class, 'store']);
        Route::match(['put', 'patch'], 'attendance/{id}', [AttendanceController::class, 'update'])->whereNumber('id');
        Route::delete('attendance/{id}', [AttendanceController::class, 'destroy'])->whereNumber('id');
    });

    // Chantier 8.3: AttendanceBiometricController was fully written (13 methods,
    // biometric device management, exception/shift/time-off workflows,
    // analytics) but never had a single route pointing at it — a distinct,
    // additive subsystem from AttendanceController above (which only covers
    // simple self clock-in/out), so it gets its own URL prefixes rather than
    // colliding with the existing 'attendance/*' paths.
    Route::get('biometric-devices', [AttendanceBiometricController::class, 'listDevices']);
    Route::get('attendance-exceptions', [AttendanceBiometricController::class, 'listExceptions']);
    Route::get('shifts', [AttendanceBiometricController::class, 'listShifts']);
    Route::get('time-off-requests', [AttendanceBiometricController::class, 'listTimeOffRequests']);
    Route::get('attendance-analytics', [AttendanceBiometricController::class, 'getAnalytics']);
    Route::get('attendance-records', [AttendanceBiometricController::class, 'listAttendance']);
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('biometric-devices', [AttendanceBiometricController::class, 'registerDevice']);
        Route::post('attendance-records/clock-in', [AttendanceBiometricController::class, 'clockIn']);
        Route::post('attendance-records/{record}/clock-out', [AttendanceBiometricController::class, 'clockOut']);
        Route::post('attendance-records/{record}/verify', [AttendanceBiometricController::class, 'verifyRecord']);
        Route::post('attendance-exceptions/{exception}/approve', [AttendanceBiometricController::class, 'approveException']);
        Route::post('shifts', [AttendanceBiometricController::class, 'createShift']);
        Route::post('time-off-requests', [AttendanceBiometricController::class, 'requestTimeOff']);
        Route::post('time-off-requests/{timeOff}/approve', [AttendanceBiometricController::class, 'approveTimeOff']);
    });

    // Chantier 8.3: CompensationController — per-employee compensation
    // tracking (base salary/bonus/benefits/equity vesting), distinct from
    // SalaryBandController's band-level equity analysis above.
    Route::get('employees/{employee}/compensation/current', [CompensationController::class, 'current']);
    Route::get('employees/{employee}/compensation/breakdown', [CompensationController::class, 'breakdown']);
    Route::get('employees/{employee}/compensation/history', [CompensationController::class, 'history']);
    Route::get('employees/{employee}/compensation/bonus-accrual', [CompensationController::class, 'bonusAccrual']);
    Route::get('compensation/audit', [CompensationController::class, 'audit']);
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('employees/{employee}/compensation', [CompensationController::class, 'store']);
        Route::post('employees/{employee}/compensation/{compensation}/update-vesting', [CompensationController::class, 'updateVesting']);
        Route::post('employees/{employee}/compensation/benchmark', [CompensationController::class, 'benchmark']);
    });

    // Skills (basic skill tagging, no training catalogue / skill matrix visualization)
    Route::middleware('cache.api:30')->group(function () {
        Route::apiResource('skills', SkillController::class)->only(['index', 'show']);
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::apiResource('skills', SkillController::class)->only(['store', 'update', 'destroy']);
    });

    // Salary bands / compensation support
    Route::middleware('cache.api:5')->group(function () {
        // Chantier 8.3: real AI equity-analysis endpoint (already called by the real,
        // routed HR/Compensation/Index.vue page) — registered before the {salaryBand}
        // show route below so "equity" isn't swallowed as a salary band ID.
        Route::get('salary-bands/equity', [SalaryBandController::class, 'equityAnalysis']);
        Route::apiResource('salary-bands', SalaryBandController::class)->only(['index', 'show']);
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('salary-bands/{salaryBand}/simulate-raise', [SalaryBandController::class, 'simulateRaise']);
        Route::apiResource('salary-bands', SalaryBandController::class)->only(['store', 'update', 'destroy']);
    });

    // Employee Self-Service routes - reads
    Route::get('me', [EmployeeSelfServiceController::class, 'me']);
    Route::get('me/payslips', [EmployeeSelfServiceController::class, 'payslips']);
    Route::get('me/leave-balance', [EmployeeSelfServiceController::class, 'leaveBalance']);

    // Chantier 32.17 (HR deep 14-layer audit): HR/Payroll/Index.vue's 3
    // export buttons have always navigated here (full-page GET, hence no
    // apiResource/POST) and always 404'd — see PayrollExportController's own
    // docblock.
    Route::get('payroll/export/{format}', [PayrollExportController::class, 'export'])
        ->whereIn('format', ['silae', 'dsn', 'csv']);

    // HR Dashboard routes (complex analytics)
    Route::middleware('throttle:complex_get')->group(function () {
        Route::get('dashboard', [HrDashboardController::class, 'index']);
        Route::get('dashboard/realtime', [HrDashboardController::class, 'realtime']);
        // Chantier 8.3: Leave/Analytics.vue (real, reachable page) called this
        // route and had zero backend behind it — built onto the same
        // days_per_year-minus-taken formula EmployeeSelfServiceController/
        // EmployeePortalController already use for a single employee's own
        // balance, aggregated across everyone.
        Route::get('leave-analytics', [HrDashboardController::class, 'leaveAnalytics']);
    });

    // HR AI routes (expensive operations)
    Route::middleware('throttle:expensive')->group(function () {
        Route::post('ai/optimize-leave-planning', [HrAIController::class, 'optimizeLeavePlanning']);
        Route::post('ai/analyze-payslip', [HrAIController::class, 'analyzePayslip']);
        Route::post('ai/detect-payroll-anomalies', [HrAIController::class, 'detectPayrollAnomalies']);
    });

    // Employee Portal routes
    Route::get('employee-portal', [EmployeePortalController::class, 'profile']);
    Route::get('employee-portal/leave-balance', [EmployeePortalController::class, 'leaveBalance']);
    Route::get('employee-portal/leave-requests', [EmployeePortalController::class, 'leaveRequests']);
    Route::get('employee-portal/payslips', [EmployeePortalController::class, 'payslips']);
    Route::get('employee-portal/payslips/{payslip}', [EmployeePortalController::class, 'payslipDetail']);
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('employee-portal/leave-requests', [EmployeePortalController::class, 'submitLeave']);
    });

    // Employee Self-Service routes
    Route::middleware('throttle:create_post')->group(function () {
        Route::put('me', [EmployeeSelfServiceController::class, 'updateMe']);
        Route::post('self-service/leave-requests', [EmployeeSelfServiceController::class, 'submitLeaveRequest']);
        // Chantier 19 (HR): resources/js/Pages/HR/Attendance/Index.vue's own
        // "request leave" quick action posts to /api/v1/hr/me/leave-requests
        // (matching the me/* naming already used by the sibling GET me/*
        // routes above), but only self-service/leave-requests was ever
        // registered — a guaranteed 404 on every real submission from this
        // page, confirmed empirically. Same real EmployeeSelfServiceController::
        // submitLeaveRequest() method, just reachable at the URL the page
        // actually calls.
        Route::post('me/leave-requests', [EmployeeSelfServiceController::class, 'submitLeaveRequest']);
    });

    // ── Document Expiry Compliance Tracker ─────────────────────────────────
    Route::middleware('cache.api:5')->group(function () {
        Route::get('documents', [DocumentAlertController::class, 'index']);
        Route::get('documents/expiring', [DocumentAlertController::class, 'expiring']);
        Route::get('documents/compliance-report', [DocumentAlertController::class, 'complianceReport']);
        Route::get('documents/{document}', [DocumentAlertController::class, 'show']);
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('documents', [DocumentAlertController::class, 'store']);
        Route::put('documents/{document}', [DocumentAlertController::class, 'update']);
        Route::delete('documents/{document}', [DocumentAlertController::class, 'destroy']);
        Route::post('documents/{document}/remind', [DocumentAlertController::class, 'remind']);
    });
});

// Employee Self-Service Portal (any authenticated user — no hr-manager role needed)
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->group(function () {
    Route::prefix('portal')->group(function () {
        Route::get('profile', [EmployeeSelfServiceController::class, 'me']);
        Route::get('leave-balance', [EmployeeSelfServiceController::class, 'leaveBalance']);
        Route::get('leave-requests', [EmployeeSelfServiceController::class, 'leaveRequests']);
        Route::post('leave-requests', [EmployeeSelfServiceController::class, 'storeLeaveRequest']);
        Route::get('payslips', [EmployeeSelfServiceController::class, 'payslips']);
        Route::get('payslips/{payslip}', [EmployeeSelfServiceController::class, 'showPayslip']);
    });
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
// Chantier 32.17 (HR deep 14-layer audit): this whole file is already loaded
// by Modules\HR\Providers\RouteServiceProvider under a `prefix('api/v1/hr')`
// group (see that provider) — stacking a second `prefix('v1/hr')` here
// produced the real, previously-unnoticed route `api/v1/hr/v1/hr/ai/assist`
// instead of the documented `api/v1/hr/ai/assist`, confirmed empirically via
// `php artisan route:list` — a guaranteed 404 on the URL this controller's
// own docblock and docs/03-MODULES/HR.md advertise. Same bug class already
// fixed once for Setup at Chantier 8.5sv. In practice this endpoint has zero
// real frontend caller anyway (every HR Vue page's useAiAssistant() call
// posts to the app-wide generic /api/v1/ai/assist, per the pattern already
// documented at Chantier 32.2) — fixed for correctness/consistency with the
// rest of this file regardless.
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->group(function () {
    Route::post('ai/assist', [\Modules\HR\Http\Controllers\Api\HRAiAssistController::class, 'assist'])
        ->name('hr.ai.assist');
});
