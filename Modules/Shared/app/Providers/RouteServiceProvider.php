<?php

namespace Modules\Shared\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

/**
 * Shared had no module.json/ServiceProvider at all — the module was only
 * reachable via composer.json's hardcoded PSR-4 autoload entries, so
 * routes/api.php (CountryController/CurrencyController/SharedAiAssistController,
 * all fully built) was never loaded by anything.
 */
class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Shared';

    public function boot(): void
    {
        parent::boot();
    }

    public function map(): void
    {
        $this->mapApiRoutes();
    }

    protected function mapApiRoutes(): void
    {
        Route::middleware('api')->prefix('api')->name('api.')->group(module_path($this->name, '/routes/api.php'));
    }
}
