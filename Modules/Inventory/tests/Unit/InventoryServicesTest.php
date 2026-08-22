<?php

namespace Modules\Inventory\Tests\Unit;

use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\InventoryService;
use Tests\TestCase;

/**
 * Chantier 32.22 (14-layer deep audit, layer 9 — fake/dead): this file used
 * to exercise `Modules\Inventory\Services\StockManagementService` almost
 * exclusively — a pure `Cache`-backed, non-persistent stock tracker (no
 * real database table at all) with zero controller/route consumer anywhere,
 * confirmed via a repo-wide grep. It duplicated (and could silently diverge
 * from — a cache eviction meant lost "stock") the real, live, DB-backed
 * `Stock`/`StockMovement`/`InventoryService` subsystem the rest of the
 * module actually uses. This file's own pre-existing code comment already
 * flagged `getLowStockAlerts()` as permanently broken (hardcoded to an
 * empty in-memory array) and had already redirected that one test onto the
 * real implementation — the same fix now applies to the whole file: the
 * service and its other 9 fake-cache-only tests were deleted, and only the
 * one real, DB-backed test survives.
 */
class InventoryServicesTest extends TestCase
{
    public function test_can_get_low_stock_alerts()
    {
        $warehouse = Warehouse::factory()->create();
        $lowProduct = Product::factory()->create(['reorder_point' => 10]);
        $okProduct = Product::factory()->create(['reorder_point' => 10]);

        $lowProduct->stock()->create(['warehouse_id' => $warehouse->id, 'quantity' => 5]);
        $okProduct->stock()->create(['warehouse_id' => $warehouse->id, 'quantity' => 50]);

        $alerts = app(InventoryService::class)->getLowStockProducts();

        $this->assertGreaterThan(0, $alerts->count());
        $this->assertTrue($alerts->contains('id', $lowProduct->id));
        $this->assertFalse($alerts->contains('id', $okProduct->id));
    }
}
