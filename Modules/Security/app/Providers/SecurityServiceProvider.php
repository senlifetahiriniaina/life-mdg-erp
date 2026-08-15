<?php

namespace Modules\Security\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Security\Providers\RouteServiceProvider;

class SecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
