<?php

namespace Modules\Core\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Core';

    /**
     * Called before routes are registered.
     *
     * Register any model bindings or pattern based filters.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
        $this->mapSecretsRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     */
    protected function mapWebRoutes(): void
    {
        // routes/web.php has no auth wrapping of its own (unlike most other modules),
        // so it's applied here — otherwise Route::resource('core', ...) is reachable
        // without authentication.
        Route::middleware(['web', 'auth'])->group(module_path($this->name, '/routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     */
    protected function mapApiRoutes(): void
    {
        Route::middleware('api')->prefix('api')->name('api.')->group(module_path($this->name, '/routes/api.php'));
    }

    /**
     * routes/secrets.php (10 secrets-vault endpoints) was never loaded by
     * this provider at all — no /api/v1/secrets/* route existed, independent
     * of the missing EncryptionService/tables fixed in earlier commits. Its
     * own auth:sanctum + throttle:secrets stack is defined inside the route
     * file itself; this only supplies the same 'api' + 'api' prefix wrapper
     * mapApiRoutes() uses, so the final paths are /api/v1/secrets/...
     * matching SecretsController's own docblocks.
     */
    protected function mapSecretsRoutes(): void
    {
        Route::middleware('api')->prefix('api')->group(module_path($this->name, '/routes/secrets.php'));
    }
}
