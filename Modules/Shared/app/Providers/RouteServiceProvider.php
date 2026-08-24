<?php

namespace Modules\Shared\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

/**
 * Shared had no module.json/ServiceProvider at all — the module was only
 * reachable via composer.json's hardcoded PSR-4 autoload entries, so
 * routes/api.php (CountryController/CurrencyController/SharedAiAssistController,
 * all fully built) was never loaded by anything.
 *
 * Chantier 32.8: map() only ever called mapApiRoutes() — routes/web.php
 * (new, see its own docblock) was never wired up at all, so the module's
 * real Pages/Index.vue page had zero route to reach it. Added mapWebRoutes()
 * matching the 'web' middleware + module-prefix pattern already used by
 * every other module (Payroll, Achats, Strategy, ...).
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
        $this->mapWebRoutes();
    }

    protected function mapApiRoutes(): void
    {
        Route::middleware('api')->prefix('api')->name('api.')->group(module_path($this->name, '/routes/api.php'));
    }

    protected function mapWebRoutes(): void
    {
        Route::middleware('web')->prefix('shared')->name('shared.')->group(module_path($this->name, '/routes/web.php'));
    }
}
