<?php

use Illuminate\Support\Facades\Route;
use Modules\Strategy\Http\Controllers\Web\StrategyPageController;

// Strategy module Inertia web routes
Route::middleware('auth')->prefix('strategy')->name('strategy.')->group(function () {
    Route::get('/', [StrategyPageController::class, 'index'])->name('index');
    Route::get('/plans', [StrategyPageController::class, 'plans'])->name('plans.index');
    Route::get('/plans/{id}', [StrategyPageController::class, 'planShow'])->name('plans.show');
    Route::get('/ratios', [StrategyPageController::class, 'ratios'])->name('ratios.index');
    Route::get('/benchmarks', [StrategyPageController::class, 'benchmarks'])->name('benchmarks.index');
    Route::get('/correlations', [StrategyPageController::class, 'correlations'])->name('correlations.index');
    Route::get('/objectives', [StrategyPageController::class, 'objectives'])->name('objectives.index');
});
