<?php

namespace Modules\Inventory\Providers;

use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Traits\PathNamespace;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Observers\ProductObserver;
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
    }
}
