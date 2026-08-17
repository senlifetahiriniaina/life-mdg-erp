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

            // Sibling prefix (api/v1, not api/v1/validation) for the generic
            // data-validation rule engine's own /validation-rules resource.
            Route::middleware('api')
                ->prefix('api/v1')
                ->name('validation.rules.')
                ->group(__DIR__.'/../../routes/validation-rules.php');

            Route::middleware('web')
                ->group(__DIR__.'/../../routes/web.php');
        });
    }
}
