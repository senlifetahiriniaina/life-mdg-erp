<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\Analytics\Http\Controllers\Web\AnalyticsWebController;

Route::middleware(['auth', 'module:Analytics'])->group(function () {
    Route::get('/analytics', [AnalyticsWebController::class, 'index'])->name('analytics.index');

    // Chantier 26 (volet A) — page self-fetch, pas de props serveur nécessaires.
    Route::get('/analytics/cashflow-forecast', fn () => Inertia::render('Analytics/CashflowForecast/Index'))
        ->name('analytics.cashflow-forecast');
});
