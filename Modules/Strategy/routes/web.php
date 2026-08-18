<?php

use Illuminate\Support\Facades\Route;
use Modules\Strategy\Http\Controllers\Web\StrategyPageController;

// Strategy module Inertia web routes
//
// Chantier 8.6: was `auth`-only — no module/role gate at all, matching the
// same hole already fixed in Setup/Inventory/HR/etc. Gated the same way as
// every other module's web group in this app: `module:Strategy` + the role
// tier that actually holds `strategy.*` permissions (finance-manager, plus
// admin/manager/employee, which get every permission by seeder design).
Route::middleware(['auth', 'module:Strategy', 'role:employee,finance-manager,manager,admin'])
    ->prefix('strategy')->name('strategy.')->group(function () {
        Route::get('/', [StrategyPageController::class, 'index'])->name('index');
        Route::get('/plans', [StrategyPageController::class, 'plans'])->name('plans.index');
        Route::get('/plans/{id}', [StrategyPageController::class, 'planShow'])->name('plans.show');
        Route::get('/ratios', [StrategyPageController::class, 'ratios'])->name('ratios.index');
        Route::get('/benchmarks', [StrategyPageController::class, 'benchmarks'])->name('benchmarks.index');
        Route::get('/correlations', [StrategyPageController::class, 'correlations'])->name('correlations.index');
        Route::get('/objectives', [StrategyPageController::class, 'objectives'])->name('objectives.index');
        Route::get('/cascade', [StrategyPageController::class, 'cascade'])->name('cascade.index');
    });
