<?php

namespace Modules\HR\Providers;

use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Traits\PathNamespace;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Observers\EmployeeObserver;
use Modules\HR\Observers\LeaveRequestObserver;
use Modules\HR\Services\DocumentExpiryService;
use Modules\HR\Services\HRService;

class HRServiceProvider extends ServiceProvider {
    use PathNamespace;

    protected string $name = 'HR';
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        $this->app->singleton(HRService::class, function ($app) {
            return new HRService;
        });

        $this->app->singleton(DocumentExpiryService::class);
    }

    public function boot(): void
    {
        Employee::observe(EmployeeObserver::class);
        LeaveRequest::observe(LeaveRequestObserver::class);
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'hr');

        // Register artisan command for document expiry checks
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\HR\Console\Commands\CheckDocumentExpiry::class,
            ]);
        }
    }
}
