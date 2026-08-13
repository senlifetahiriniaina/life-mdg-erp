<?php

namespace Modules\Achats\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected $moduleNamespace = 'Modules\Achats\Http\Controllers';

    public function boot(): void
    {
        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api/v1/achats')
                ->name('achats.')
                ->group(__DIR__.'/../../routes/api.php');

            Route::middleware('web')
                ->group(__DIR__.'/../../routes/web.php');
        });
    }
}
