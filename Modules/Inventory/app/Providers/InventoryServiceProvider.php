<?php

namespace Modules\Inventory\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Traits\PathNamespace;
use Modules\Inventory\Models\CostingSheet;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductTemplate;
use Modules\Inventory\Models\SourcingBenchmark;
use Modules\Inventory\Models\Stock;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Observers\ProductObserver;
use Modules\Inventory\Policies\CostingSheetPolicy;
use Modules\Inventory\Policies\ProductTemplatePolicy;
use Modules\Inventory\Policies\SourcingBenchmarkPolicy;
use Modules\Inventory\Policies\StockMovementPolicy;
use Modules\Inventory\Policies\StockPolicy;
use Modules\Inventory\Policies\WarehousePolicy;
use Modules\Inventory\Services\InventoryService;

class InventoryServiceProvider extends ServiceProvider {
    use PathNamespace;

    protected string $name = 'Inventory';
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        $this->app->singleton(InventoryService::class, function ($app) {
            return new InventoryService;
        });
    }

    public function boot(): void
    {
        Product::observe(ProductObserver::class);
        // TODO: Uncomment when Stock model observer is fully implemented
        // \Modules\Inventory\Models\Stock::observe(\Modules\Inventory\Observers\WarehouseStockObserver::class);
$this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'inventory');
        $this->registerPolicies();
    }

    /**
     * Chantier 10: WarehousePolicy/StockMovementPolicy/StockPolicy were all
     * fully written but never registered with the Gate anywhere (Modules-
     * namespaced policies don't auto-discover — same precedent as Core/BI/
     * HR/Logistics/Achats elsewhere in this session) — this was the one
     * in-scope module with zero registerPolicies() call at all, and zero
     * authorize() calls in any of its controllers. Warehouse/StockMovement
     * are now wired to real, already-seeded permissions (inventory.warehouse.*,
     * inventory.stock-movement.*). Stock is registered here for completeness
     * but deliberately NOT wired into any controller yet: 'stock' isn't a
     * resource in RolesAndPermissionsSeeder::MODULES['inventory'] at all, so
     * every inventory.stock.* permission StockPolicy checks is unseeded —
     * adding authorize() calls against it today would fail-closed for every
     * role including admin/logistics-manager on ProductController::stock()/
     * adjustStock()/transferStock(). Seeding 'stock' as a resource (or an
     * INVENTORY_EXTRA_PERMISSIONS block) is a RolesAndPermissionsSeeder
     * change out of this chantier's scope — flagged for Phase B.
     */
    private function registerPolicies(): void
    {
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(StockMovement::class, StockMovementPolicy::class);
        Gate::policy(Stock::class, StockPolicy::class);
        // Chantier 17
        Gate::policy(ProductTemplate::class, ProductTemplatePolicy::class);
        Gate::policy(SourcingBenchmark::class, SourcingBenchmarkPolicy::class);
        // Chantier 21
        Gate::policy(CostingSheet::class, CostingSheetPolicy::class);
    }
}
