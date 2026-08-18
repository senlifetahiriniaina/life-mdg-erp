<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Reporting\Http\Controllers\Web\ReportingWebController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Chantier 8 (Reporting): this file used to be a literal empty placeholder
| ("Web routes placeholder — Reporting module is primarily API-driven.") —
| the real, fully-built ReportsIndex.vue page (quick-report tiles, report
| history, OHADA balance-sheet shortcut) had no route anywhere in the app
| to reach it. Pages self-fetch everything via the real reporting API, so
| these are thin wrappers (same precedent as Accounting's
| ConsolidationHierarchyWebController / Helpdesk's SlaAutomationWebController).
|
*/

Route::middleware(['auth', 'module:Reporting'])->group(function () {
    Route::get('/reporting', [ReportingWebController::class, 'index'])->name('reporting.index');
    Route::get('/reporting/create', [ReportingWebController::class, 'create'])->name('reporting.create');
    Route::get('/reporting/reports/{id}', [ReportingWebController::class, 'show'])
        ->where('id', '[0-9]+')
        ->name('reporting.reports.show');
});
