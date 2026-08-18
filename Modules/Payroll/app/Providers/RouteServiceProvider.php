<?php

declare(strict_types=1);

namespace Modules\Payroll\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api/v1/payroll')
                ->name('api.payroll.')
                ->group(__DIR__ . '/../../routes/api.php');

            Route::middleware('web')
                ->prefix('payroll')
                ->name('payroll.')
                ->group(__DIR__ . '/../../routes/web.php');
        });
    }
}
