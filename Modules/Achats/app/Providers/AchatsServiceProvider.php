<?php

namespace Modules\Achats\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Policies\PurchaseOrderPolicy;
use Modules\Achats\Services\ApprovalRoutingService;
use Modules\Achats\Services\PurchaseIntegrationService;
use Modules\Achats\Services\PurchaseOrderService;
use Modules\Achats\Services\PurchaseReceiptService;
use Modules\Achats\Services\RFQService;
use Modules\Achats\Services\SupplierService;
use Modules\Validation\Services\ApprovalRequestService;
use Nwidart\Modules\Traits\PathNamespace;

class AchatsServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Achats';

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->registerServices();
    }

    public function boot(): void
    {
        $this->publishConfig();
        $this->registerMigrations();
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
    }

    protected function registerServices(): void
    {
        $this->app->singleton(SupplierService::class, function ($app) {
            return new SupplierService;
        });

        $this->app->singleton(PurchaseOrderService::class, function ($app) {
            return new PurchaseOrderService(
                $app->make(ApprovalRequestService::class),
                $app->make(ApprovalRoutingService::class)
            );
        });

        $this->app->singleton(RFQService::class, function ($app) {
            return new RFQService;
        });

        $this->app->singleton(PurchaseReceiptService::class, function ($app) {
            return new PurchaseReceiptService(
                $app->make(PurchaseOrderService::class)
            );
        });

        $this->app->singleton(PurchaseIntegrationService::class, function ($app) {
            return new PurchaseIntegrationService;
        });

        // Aliases for easier access
        $this->app->alias(PurchaseOrderService::class, 'achats.purchase-orders');
        $this->app->alias(SupplierService::class, 'achats.suppliers');
        $this->app->alias(RFQService::class, 'achats.rfqs');
        $this->app->alias(PurchaseReceiptService::class, 'achats.receipts');
        $this->app->alias(PurchaseIntegrationService::class, 'achats.integration');
    }

    protected function publishConfig(): void
    {
        $this->publishes([
            __DIR__.'/../../config/achats.php' => config_path('achats.php'),
        ], 'achats-config');
    }

    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }

    public function provides(): array
    {
        return [
            PurchaseOrderService::class,
            SupplierService::class,
            RFQService::class,
            PurchaseReceiptService::class,
            PurchaseIntegrationService::class,
        ];
    }
}
