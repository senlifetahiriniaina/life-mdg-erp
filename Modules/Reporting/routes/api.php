<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Reporting\Http\Controllers\Api\ReportingController;
// Note: all routes handled by a single controller — OHADA, NL-SQL, Dashboards, Widgets

Route::middleware(['auth:sanctum', 'module:Reporting'])->prefix('v1')->group(function () {

    // ─── Report Definitions (CRUD) ─────────────────────────────────────────────
    Route::get('reporting/reports', [ReportingController::class, 'listReports'])
        ->name('reporting.reports.index');

    Route::post('reporting/reports', [ReportingController::class, 'storeReport'])
        ->name('reporting.reports.store');

    Route::get('reporting/reports/{id}', [ReportingController::class, 'showReport'])
        ->where('id', '[0-9]+')
        ->name('reporting.reports.show');

    Route::put('reporting/reports/{id}', [ReportingController::class, 'updateReport'])
        ->where('id', '[0-9]+')
        ->name('reporting.reports.update');

    Route::delete('reporting/reports/{id}', [ReportingController::class, 'destroyReport'])
        ->where('id', '[0-9]+')
        ->name('reporting.reports.destroy');

    // ─── Execute by slug (legacy) ──────────────────────────────────────────────
    Route::post('reporting/reports/{slug}/execute', [ReportingController::class, 'executeReport'])
        ->name('reporting.reports.execute');

    // ─── Run by ID (returns execution id) ─────────────────────────────────────
    Route::post('reporting/reports/{id}/run', [ReportingController::class, 'runReport'])
        ->where('id', '[0-9]+')
        ->name('reporting.reports.run');

    // ─── Execution history for a report ───────────────────────────────────────
    Route::get('reporting/reports/{id}/executions', [ReportingController::class, 'listExecutions'])
        ->where('id', '[0-9]+')
        ->name('reporting.reports.executions');

    // ─── Share a report ────────────────────────────────────────────────────────
    Route::post('reporting/reports/{id}/share', [ReportingController::class, 'shareReport'])
        ->where('id', '[0-9]+')
        ->name('reporting.reports.share');

    // ─── Executions ────────────────────────────────────────────────────────────
    Route::get('reporting/executions/{id}', [ReportingController::class, 'showExecution'])
        ->where('id', '[0-9]+')
        ->name('reporting.executions.show');

    Route::get('reporting/executions/{id}/download', [ReportingController::class, 'downloadExecution'])
        ->where('id', '[0-9]+')
        ->name('reporting.executions.download');

    // ─── Schedules ─────────────────────────────────────────────────────────────
    Route::get('reporting/schedules', [ReportingController::class, 'listSchedules'])
        ->name('reporting.schedules.index');

    Route::post('reporting/schedules', [ReportingController::class, 'createSchedule'])
        ->name('reporting.schedules.store');

    // ─── OHADA Financial Reports ────────────────────────────────────────────────
    Route::prefix('reporting/ohada')->name('reporting.ohada.')->group(function () {
        Route::get('balance-sheet',      [ReportingController::class, 'ohadaBalanceSheet'])
            ->name('balance_sheet');
        Route::get('income-statement',   [ReportingController::class, 'ohadaIncomeStatement'])
            ->name('income_statement');
        Route::get('trial-balance',      [ReportingController::class, 'ohadaTrialBalance'])
            ->name('trial_balance');
        Route::get('journal',            [ReportingController::class, 'ohadaJournal'])
            ->name('journal');
        Route::get('aged-receivables',   [ReportingController::class, 'ohadaAgedReceivables'])
            ->name('aged_receivables');
        Route::get('aged-payables',      [ReportingController::class, 'ohadaAgedPayables'])
            ->name('aged_payables');
        Route::get('tva',                [ReportingController::class, 'ohadaTva'])
            ->name('tva');
        Route::get('is',                 [ReportingController::class, 'ohadaIs'])
            ->name('is');
    });

    // ─── Natural Language Query ─────────────────────────────────────────────────
    Route::post('reporting/nl-query',          [ReportingController::class, 'nlQuery'])
        ->name('reporting.nl_query');
    Route::get('reporting/saved-queries',      [ReportingController::class, 'listSavedQueries'])
        ->name('reporting.saved_queries.index');
    Route::post('reporting/saved-queries',     [ReportingController::class, 'storeSavedQuery'])
        ->name('reporting.saved_queries.store');

    // ─── Dashboards ─────────────────────────────────────────────────────────────
    Route::get('reporting/dashboards',                          [ReportingController::class, 'listDashboards'])
        ->name('reporting.dashboards.index');
    Route::post('reporting/dashboards',                         [ReportingController::class, 'storeDashboard'])
        ->name('reporting.dashboards.store');
    Route::get('reporting/dashboards/{id}',                     [ReportingController::class, 'showDashboard'])
        ->where('id', '[0-9]+')->name('reporting.dashboards.show');
    Route::put('reporting/dashboards/{id}',                     [ReportingController::class, 'updateDashboard'])
        ->where('id', '[0-9]+')->name('reporting.dashboards.update');
    Route::get('reporting/dashboards/{id}/summary',             [ReportingController::class, 'dashboardAiSummary'])
        ->where('id', '[0-9]+')->name('reporting.dashboards.summary');

    // ─── Widget data ─────────────────────────────────────────────────────────────
    Route::get('reporting/widgets/{id}/data',                   [ReportingController::class, 'widgetData'])
        ->where('id', '[0-9]+')->name('reporting.widgets.data');
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum'])->prefix('v1/reporting')->group(function () {
    Route::post('ai/assist', [\Modules\Reporting\Http\Controllers\Api\ReportingAiAssistController::class, 'assist'])
        ->name('reporting.ai.assist');
});
