<?php

namespace Modules\Inventory\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use Modules\Inventory\Models\CycleCount;
use Modules\Inventory\Models\CycleCountLine;
use Modules\Inventory\Models\PickingLine;
use Modules\Inventory\Models\PickingOrder;
use Modules\Inventory\Models\PickingWave;
use Modules\Inventory\Models\PickLine;
use Modules\Inventory\Models\Rma;
use Modules\Inventory\Models\TransferOrder;
use Modules\Inventory\Models\ValuationRun;
use Modules\Inventory\Models\Warehouse;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::bind('picking', function ($value) {
            return PickingOrder::findOrFail($value);
        });

        Route::bind('pickingLine', function ($value) {
            return PickingLine::findOrFail($value);
        });

        Route::bind('run', function ($value) {
            return ValuationRun::findOrFail($value);
        });

        Route::bind('cycleCount', function ($value) {
            return CycleCount::findOrFail($value);
        });

        Route::bind('cycleCountLine', function ($value) {
            return CycleCountLine::findOrFail($value);
        });

        Route::bind('rma', function ($value) {
            return Rma::findOrFail($value);
        });

        Route::bind('wave', function ($value) {
            return PickingWave::findOrFail($value);
        });

        Route::bind('wavePickLine', function ($value) {
            return PickLine::findOrFail($value);
        });

        Route::bind('transfer', function ($value) {
            return TransferOrder::findOrFail($value);
        });

        Route::bind('transfer_order', function ($value) {
            return TransferOrder::findOrFail($value);
        });

        Route::bind('warehouse', function ($value) {
            return Warehouse::findOrFail($value);
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api/v1/inventory')
                ->name('inventory.')
                ->group(__DIR__.'/../../routes/api.php');

            Route::middleware('web')
                ->prefix('inventory')
                ->name('inventory.')
                ->group(__DIR__.'/../../routes/web.php');
        });
    }
}
