<?php

namespace Modules\Validation\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected $moduleNamespace = 'Modules\Validation\Http\Controllers';

    public function boot(): void
    {
        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api/v1/validation')
                ->name('validation.')
                ->group(__DIR__.'/../../routes/api.php');

            Route::middleware('web')
                ->group(__DIR__.'/../../routes/web.php');
        });
    }
}
