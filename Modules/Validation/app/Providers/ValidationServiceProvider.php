<?php

namespace Modules\Validation\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
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
        // Chantier 20: this was scaffolded (Modules\Validation\Providers\EventServiceProvider,
        // with an empty $listen array) but never actually registered anywhere — the module's
        // module.json only lists ValidationServiceProvider as a provider, so Laravel never
        // booted it and the 4 approval events (ApprovalRequestCreated/Approved/Rejected/
        // Completed) have always fired into the void. Registering it for real is what makes
        // the notification listeners below actually run.
        $this->app->register(EventServiceProvider::class);

        $this->registerServices();
    }

    public function boot(): void
    {
        $this->publishConfig();
        $this->registerMigrations();
        $this->registerApprovableMorphMap();
        Gate::policy(ApprovalRequest::class, ApprovalRequestPolicy::class);
    }

    /**
     * Aliases accepted as `approvable_type` when creating an approval request
     * (POST /api/v1/validation/approval-requests). Deliberately an allowlist —
     * never resolve a class name coming straight from client input. Mirrors
     * the pattern used by HelpdeskServiceProvider's ticket-source morph map.
     */
    private function registerApprovableMorphMap(): void
    {
        Relation::morphMap([
            'invoice' => \Modules\Accounting\Models\Invoice::class,
            'purchase_order' => \Modules\Achats\Models\PurchaseOrder::class,
        ]);
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

    public function provides(): array
    {
        return [
            ApprovalWorkflowService::class,
            ApprovalRequestService::class,
            ApprovalHierarchyService::class,
        ];
    }
}
