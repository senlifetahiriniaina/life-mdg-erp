<?php

use Illuminate\Support\Facades\Route;
use Modules\HR\Http\Controllers\Api\AttendanceController;
use Modules\HR\Http\Controllers\Api\DepartmentController;
use Modules\HR\Http\Controllers\Api\DocumentAlertController;
use Modules\HR\Http\Controllers\Api\EmployeeController;
use Modules\HR\Http\Controllers\Api\EmployeePortalController;
use Modules\HR\Http\Controllers\Api\EmployeeSelfServiceController;
use Modules\HR\Http\Controllers\Api\HrAIController;
use Modules\HR\Http\Controllers\Api\HrDashboardController;
use Modules\HR\Http\Controllers\Api\JobPositionController;
use Modules\HR\Http\Controllers\Api\LeaveController;
use Modules\HR\Http\Controllers\Api\LeaveRequestController;
use Modules\HR\Http\Controllers\Api\LeaveTypeController;
use Modules\HR\Http\Controllers\Api\SalaryBandController;
use Modules\HR\Http\Controllers\Api\SkillController;

// Default: Simple GET throttle (1000 req/min) — overridden for specific endpoint groups
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:simple_get'])->group(function () {
    // Employee routes - custom routes first to avoid being shadowed by apiResource
    Route::middleware('cache.api:1')->group(function () {
        Route::get('employees/by-department/{department}', [EmployeeController::class, 'byDepartment']);
        Route::get('employees/metrics', [EmployeeController::class, 'metrics']);
        Route::apiResource('employees', EmployeeController::class)->only(['index', 'show'])->names('api.employees');
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::apiResource('employees', EmployeeController::class)->only(['store', 'update', 'destroy'])->names('api.employees');
        Route::post('employees/{employee}/skills', [SkillController::class, 'addEmployeeSkill']);
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
        Route::apiResource('leaves', LeaveController::class)->only(['index', 'show']);
        Route::apiResource('leave-types', LeaveTypeController::class)->only(['index', 'show']);
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve']);
        Route::post('leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject']);
        Route::apiResource('leave-requests', LeaveRequestController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('leaves', LeaveController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('leave-types', LeaveTypeController::class)->only(['store', 'update', 'destroy']);
    });

    // Attendance routes
    Route::get('attendance/status', [AttendanceController::class, 'status']);
    Route::get('attendance', [AttendanceController::class, 'index']);
    Route::get('me/attendance', [AttendanceController::class, 'ownAttendance']);
    Route::get('employees/{employee}/attendance', [AttendanceController::class, 'employeeAttendance']);
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('attendance/clock-in', [AttendanceController::class, 'clockIn']);
        Route::post('attendance/clock-out', [AttendanceController::class, 'clockOut']);
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

    // HR Dashboard routes (complex analytics)
    Route::middleware('throttle:complex_get')->group(function () {
        Route::get('dashboard', [HrDashboardController::class, 'index']);
        Route::get('dashboard/realtime', [HrDashboardController::class, 'realtime']);
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
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1/hr')->group(function () {
    Route::post('ai/assist', [\Modules\HR\Http\Controllers\Api\HRAiAssistController::class, 'assist'])
        ->name('hr.ai.assist');
});
