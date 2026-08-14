<?php

namespace Modules\Timesheets\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    protected function mapApiRoutes(): void
    {
        Route::prefix('api/v1')
            ->middleware(['api', 'auth:sanctum'])
            ->group(module_path('Timesheets', '/routes/api.php'));
    }

    protected function mapWebRoutes(): void
    {
        // routes/web.php itself has no auth wrapping (unlike Accounting/Achats,
        // which wrap their own web routes in Route::middleware(['auth'])), so it's
        // applied here instead — otherwise every Timesheets page would be public.
        Route::middleware(['web', 'auth'])
            ->group(module_path('Timesheets', '/routes/web.php'));
    }
}
