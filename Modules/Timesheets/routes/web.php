<?php

use Illuminate\Support\Facades\Route;
use Modules\Timesheets\Http\Controllers\Web\SheetWebController;

/**
 * Chantier 8.4: this whole group had zero auth/module middleware — every
 * page below (including personal timesheet data) was reachable by any
 * visitor, unauthenticated. Sheets/{sheet} and Sheets/{sheet}/edit
 * rendered from bare closures with no props at all, despite Show.vue/
 * Form.vue both declaring required `sheet`/`entries` props — routed onto
 * a real SheetWebController instead. `/sheets/create` (linked from both
 * Sheets/Index.vue and Sheets/MySheets.vue) didn't exist at all.
 */
Route::middleware(['auth', 'module:Timesheets'])->prefix('timesheets')->group(function () {
    Route::get('/dashboard', function () {
        return inertia('Timesheets/Dashboard');
    })->name('timesheets.dashboard');

    // Time Entries
    Route::get('/entries', function () {
        return inertia('Timesheets/TimeEntries/Index');
    })->name('timesheets.entries.index');
    Route::get('/entries/create', function () {
        return inertia('Timesheets/TimeEntries/Form');
    })->name('timesheets.entries.create');
    Route::get('/entries/{entry}/edit', function () {
        return inertia('Timesheets/TimeEntries/Form');
    })->name('timesheets.entries.edit');

    // Timesheets ("sheets" = weekly TimesheetPeriod submissions)
    Route::get('/sheets', function () {
        return inertia('Timesheets/Sheets/Index');
    })->name('timesheets.sheets.index');
    Route::get('/sheets/create', [SheetWebController::class, 'create'])->name('timesheets.sheets.create');
    Route::get('/sheets/{sheet}', [SheetWebController::class, 'show'])->name('timesheets.sheets.show');
    Route::get('/sheets/{sheet}/edit', [SheetWebController::class, 'edit'])->name('timesheets.sheets.edit');
    Route::get('/my-sheets', function () {
        return inertia('Timesheets/Sheets/MySheets');
    })->name('timesheets.sheets.mine');

    // Reports
    Route::get('/reports/hours', function () {
        return inertia('Timesheets/Reports/EmployeeHours');
    })->name('timesheets.reports.hours');
    Route::get('/reports/billing', function () {
        return inertia('Timesheets/Reports/ProjectBilling');
    })->name('timesheets.reports.billing');
    Route::get('/reports/utilization', function () {
        return inertia('Timesheets/Reports/Utilization');
    })->name('timesheets.reports.utilization');
});
