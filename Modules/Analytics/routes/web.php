<?php

use Illuminate\Support\Facades\Route;
use Modules\Analytics\Http\Controllers\Web\AnalyticsWebController;

Route::middleware(['auth', 'module:Analytics'])->group(function () {
    Route::get('/analytics', [AnalyticsWebController::class, 'index'])->name('analytics.index');
});
