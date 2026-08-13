<?php

namespace Modules\Security\Providers;

use Illuminate\Support\ServiceProvider;

class SecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment() !== 'testing') {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }
    }
}
