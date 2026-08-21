<?php

namespace Modules\Security\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Security\Providers\RouteServiceProvider;
use Modules\Security\Providers\EventServiceProvider;

class SecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        // Chantier 32.3: registers RecordAuthenticationEvent — see its docblock.
        $this->app->register(EventServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
