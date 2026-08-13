<?php

use Illuminate\Support\Facades\Route;

Route::prefix('timesheets')->group(function () {
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

    // Timesheets
    Route::get('/sheets', function () {
        return inertia('Timesheets/Sheets/Index');
    })->name('timesheets.sheets.index');
    Route::get('/sheets/{sheet}', function () {
        return inertia('Timesheets/Sheets/Show');
    })->name('timesheets.sheets.show');
    Route::get('/sheets/{sheet}/edit', function () {
        return inertia('Timesheets/Sheets/Form');
    })->name('timesheets.sheets.edit');
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
