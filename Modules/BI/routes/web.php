<?php

use Illuminate\Support\Facades\Route;
use Modules\BI\Http\Controllers\Web\BiWebController;

Route::middleware(['auth', 'module:BI'])->group(function () {
    Route::get('/bi', [BiWebController::class, 'index'])->name('bi.index');
    Route::get('/bi/reports', [BiWebController::class, 'reports'])->name('bi.reports');
    Route::get('/bi/sql-editor', [BiWebController::class, 'sqlEditor'])->name('bi.sql-editor');
    Route::get('/bi/alerts', [BiWebController::class, 'alerts'])->name('bi.alerts');
    Route::get('/bi/data-sources', [BiWebController::class, 'dataSources'])->name('bi.data-sources');
});
