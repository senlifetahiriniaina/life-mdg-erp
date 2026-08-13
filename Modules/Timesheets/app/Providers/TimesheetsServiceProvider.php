<?php

namespace Modules\Timesheets\Providers;

use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Traits\PathNamespace;

class TimesheetsServiceProvider extends ServiceProvider {
    use PathNamespace;

    protected string $name = 'Timesheets';
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        //
    }

    public function boot(): void
    {
$this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }
}
