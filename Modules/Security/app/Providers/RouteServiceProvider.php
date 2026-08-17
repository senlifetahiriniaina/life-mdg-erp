<?php

namespace Modules\Security\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

/**
 * Security had no RouteServiceProvider at all — routes/api.php (140+ lines,
 * IncidentController/ComplianceController/EncryptionController/
 * AuthenticationEventController/ThreatIndicatorController/TrustZoneController/
 * ServiceIdentityController, all fully built) was never loaded by anything,
 * so every Security endpoint 404'd unconditionally.
 */
class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Security';

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
        Route::middleware('web')->group(module_path($this->name, '/routes/web.php'));
    }
}
