<?php

namespace Modules\Validation\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Traits\PathNamespace;
use Modules\Validation\Models\ApprovalRequest;
use Modules\Validation\Policies\ApprovalRequestPolicy;
use Modules\Validation\Services\ApprovalHierarchyService;
use Modules\Validation\Services\ApprovalRequestService;
use Modules\Validation\Services\ApprovalWorkflowService;

class ValidationServiceProvider extends ServiceProvider {
    use PathNamespace;

    protected string $name = 'Validation';
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        $this->registerServices();
    }

    public function boot(): void
    {
        $this->publishConfig();
        $this->registerMigrations();
        $this->registerRoutes();
        Gate::policy(ApprovalRequest::class, ApprovalRequestPolicy::class);
    }

    protected function registerServices(): void
    {
        $this->app->singleton(ApprovalWorkflowService::class, function ($app) {
            return new ApprovalWorkflowService;
        });

        $this->app->singleton(ApprovalRequestService::class, function ($app) {
            return new ApprovalRequestService;
        });

        $this->app->singleton(ApprovalHierarchyService::class, function ($app) {
            return new ApprovalHierarchyService;
        });

        // Aliases for easier access
        $this->app->alias(ApprovalWorkflowService::class, 'validation.workflows');
        $this->app->alias(ApprovalRequestService::class, 'validation.requests');
        $this->app->alias(ApprovalHierarchyService::class, 'validation.hierarchies');
    }

    protected function publishConfig(): void
    {
        $this->publishes([
            __DIR__.'/../../config/validation.php' => config_path('validation.php'),
        ], 'validation-config');
    }

    protected function registerMigrations(): void
    {
$this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }

    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
    }

    public function provides(): array
    {
        return [
            ApprovalWorkflowService::class,
            ApprovalRequestService::class,
            ApprovalHierarchyService::class,
        ];
    }
}
