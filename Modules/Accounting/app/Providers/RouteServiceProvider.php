<?php

namespace Modules\Accounting\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api/v1/accounting')
                ->name('accounting.')
                ->group(__DIR__.'/../../routes/api.php');

            Route::middleware('web')
                ->prefix('accounting')
                ->name('accounting.')
                ->group(__DIR__.'/../../routes/web.php');
        });
    }
}
