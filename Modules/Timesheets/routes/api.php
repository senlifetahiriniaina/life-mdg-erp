<?php

use Illuminate\Support\Facades\Route;
use Modules\Timesheets\Http\Controllers\Api\MetricsController;
use Modules\Timesheets\Http\Controllers\Api\TimeAllocationController;
use Modules\Timesheets\Http\Controllers\Api\TimesheetEntryController;
use Modules\Timesheets\Http\Controllers\Api\TrackingProjectController;

Route::middleware(['auth:sanctum', 'session.security', 'module:Timesheets', 'role:employee,manager,admin', 'throttle:simple_get'])->prefix('timesheets')->group(function () {
    // Timesheet entries
    Route::get('entries/pending/approvals', [TimesheetEntryController::class, 'pendingApprovals']);
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
Route::middleware(['auth:sanctum', 'session.security'])->prefix('v1/timesheets')->group(function () {
    Route::post('ai/assist', [\Modules\Timesheets\Http\Controllers\Api\TimesheetsAiAssistController::class, 'assist'])
        ->name('timesheets.ai.assist');
});

// ── Live Timer (browser extension + in-app) ────────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'module:Timesheets', 'role:employee,manager,admin'])->prefix('v1/timesheets')->group(function () {
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
