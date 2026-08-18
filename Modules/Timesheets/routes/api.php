<?php

use Illuminate\Support\Facades\Route;
use Modules\Timesheets\Http\Controllers\Api\MetricsController;
use Modules\Timesheets\Http\Controllers\Api\TimeAllocationController;
use Modules\Timesheets\Http\Controllers\Api\TimesheetAdvancedController;
use Modules\Timesheets\Http\Controllers\Api\TimesheetEntryController;
use Modules\Timesheets\Http\Controllers\Api\TrackingProjectController;

Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Timesheets', 'role:employee,manager,admin', 'throttle:simple_get'])->prefix('timesheets')->group(function () {
    // Timesheet entries
    Route::get('entries/pending/approvals', [TimesheetEntryController::class, 'pendingApprovals']);
    // Chantier 8.4: real, tested (TimesheetService::getEmployeeTimesheets())
    // but never routed anywhere.
    Route::get('entries/by-employee', [TimesheetEntryController::class, 'byEmployee']);
    Route::get('entries', [TimesheetEntryController::class, 'index']);
    Route::get('entries/{entry}', [TimesheetEntryController::class, 'show']);

    // Time allocations
    Route::get('allocations/project/{project}', [TimeAllocationController::class, 'byProject']);
    Route::get('allocations', [TimeAllocationController::class, 'index']);
    Route::get('allocations/{allocation}', [TimeAllocationController::class, 'show']);

    // Tracking projects
    Route::get('projects', [TrackingProjectController::class, 'index']);
    Route::get('projects/{project}/timesheets', [TrackingProjectController::class, 'timesheets']);
    Route::get('projects/{project}/metrics', [TrackingProjectController::class, 'metrics']);
    Route::get('projects/{project}', [TrackingProjectController::class, 'show']);

    // Metrics
    Route::get('metrics/summary', [MetricsController::class, 'summary']);
    Route::get('metrics/employee/{user}/month/{month}', [MetricsController::class, 'employeeMetrics']);
    Route::get('metrics/project/{project}', [MetricsController::class, 'projectMetrics']);

    Route::middleware('throttle:create_post')->group(function () {
        // Entries mutations
        Route::post('entries', [TimesheetEntryController::class, 'store']);
        Route::put('entries/{entry}', [TimesheetEntryController::class, 'update']);
        Route::patch('entries/{entry}', [TimesheetEntryController::class, 'update']);
        Route::delete('entries/{entry}', [TimesheetEntryController::class, 'destroy']);
        Route::post('entries/{entry}/submit', [TimesheetEntryController::class, 'submit']);
        Route::post('entries/{entry}/approve', [TimesheetEntryController::class, 'approve']);
        Route::post('entries/{entry}/reject', [TimesheetEntryController::class, 'reject']);
        Route::post('entries/{entry}/allocate', [TimeAllocationController::class, 'allocate']);

        // Allocations mutations
        Route::post('allocations', [TimeAllocationController::class, 'store']);
        Route::put('allocations/{allocation}', [TimeAllocationController::class, 'update']);
        Route::patch('allocations/{allocation}', [TimeAllocationController::class, 'update']);
        Route::delete('allocations/{allocation}', [TimeAllocationController::class, 'destroy']);

        // Projects mutations
        Route::post('projects', [TrackingProjectController::class, 'store']);
        Route::put('projects/{project}', [TrackingProjectController::class, 'update']);
        Route::patch('projects/{project}', [TrackingProjectController::class, 'update']);
        Route::delete('projects/{project}', [TrackingProjectController::class, 'destroy']);
    });
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('timesheets')->group(function () {
    Route::post('ai/assist', [\Modules\Timesheets\Http\Controllers\Api\TimesheetsAiAssistController::class, 'assist'])
        ->name('timesheets.ai.assist');
});

// ── Live Timer (browser extension + in-app) ────────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Timesheets', 'role:employee,manager,admin'])->prefix('timesheets')->group(function () {
    Route::get('timer/current', [\Modules\Timesheets\Http\Controllers\Api\TimerController::class, 'current'])
        ->name('timesheets.timer.current');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('timer/start', [\Modules\Timesheets\Http\Controllers\Api\TimerController::class, 'start'])
            ->name('timesheets.timer.start');
        Route::post('timer/stop', [\Modules\Timesheets\Http\Controllers\Api\TimerController::class, 'stop'])
            ->name('timesheets.timer.stop');
        Route::delete('timer/discard', [\Modules\Timesheets\Http\Controllers\Api\TimerController::class, 'discard'])
            ->name('timesheets.timer.discard');
    });
});

// ── Phase 49: advanced timesheet + project-billing endpoints (TimesheetAdvancedController) ──
// Chantier 8.4: the old bare index/store/update/destroy (Timesheet-entry CRUD)
// were deleted — 100% redundant with the real, already-routed
// TimesheetEntryController above. "sheets/*" and "reports/*" back the real
// Sheets/*.vue and Reports/*.vue pages, which called this exact URL scheme
// with no controller behind it at all until now.
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Timesheets', 'role:employee,manager,admin'])->prefix('timesheets')->group(function () {
    Route::get('sheets/my-sheets', [TimesheetAdvancedController::class, 'mySheets']);
    Route::get('sheets', [TimesheetAdvancedController::class, 'sheetsIndex']);
    Route::get('reports/project-billing', [TimesheetAdvancedController::class, 'projectBillingReport']);
    Route::get('reports/employee-hours', [TimesheetAdvancedController::class, 'employeeHoursReport']);
    Route::get('reports/utilization', [TimesheetAdvancedController::class, 'utilizationReport']);
    Route::get('weekly/{employeeId}/{weekStart}', [TimesheetAdvancedController::class, 'weeklyView']);
    Route::get('team/{managerId}', [TimesheetAdvancedController::class, 'teamView']);
    Route::get('utilization', [TimesheetAdvancedController::class, 'utilization']);
    Route::get('revenue-recognition', [TimesheetAdvancedController::class, 'revenueRecognition']);

    Route::middleware('throttle:create_post')->group(function () {
        Route::post('sheets', [TimesheetAdvancedController::class, 'storeSheet']);
        Route::put('sheets/{id}', [TimesheetAdvancedController::class, 'updateSheet']);
        Route::post('sheets/{id}/submit', [TimesheetAdvancedController::class, 'submitSheet']);
        Route::post('sheets/{id}/approve', [TimesheetAdvancedController::class, 'approvePeriod']);
        Route::post('sheets/{id}/reject', [TimesheetAdvancedController::class, 'rejectPeriod']);

        Route::post('periods/{weekStart}/submit', [TimesheetAdvancedController::class, 'submitPeriod']);
        Route::put('periods/{id}/approve', [TimesheetAdvancedController::class, 'approvePeriod']);
        Route::put('periods/{id}/reject', [TimesheetAdvancedController::class, 'rejectPeriod']);
    });
});

Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Timesheets', 'role:employee,manager,admin'])->prefix('projects')->group(function () {
    Route::get('{id}/billing', [TimesheetAdvancedController::class, 'billingHistory']);
    Route::post('{id}/billing/milestone', [TimesheetAdvancedController::class, 'billByMilestone']);
    Route::post('{id}/billing/percentage', [TimesheetAdvancedController::class, 'billByPercentage']);
    Route::post('{id}/billing/time-material', [TimesheetAdvancedController::class, 'billTimeAndMaterial']);
    Route::get('{id}/billing/invoiceable', [TimesheetAdvancedController::class, 'invoiceable']);
    Route::post('{id}/billing/{billingId}/generate-invoice', [TimesheetAdvancedController::class, 'generateInvoice']);
});
