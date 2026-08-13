<?php

namespace Modules\Inventory\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Modules\Inventory\Services\StockManagementService;
use Tests\TestCase;

class InventoryServicesTest extends TestCase
{
    protected StockManagementService $stockService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stockService = app(StockManagementService::class);
        Cache::flush();
    }

    public function test_can_add_stock()
    {
        $result = $this->stockService->addStock(1, 100);

        $this->assertEquals('added', $result['status']);
        $this->assertEquals(100, $result['total_quantity']);
        $this->assertEquals(100, $result['available_quantity']);
    }

    public function test_can_remove_stock()
    {
        // Add stock first
        $this->stockService->addStock(1, 100);

        // Remove stock
        $result = $this->stockService->removeStock(1, 30, 'sales');

        $this->assertEquals('removed', $result['status']);
        $this->assertEquals(70, $result['remaining_quantity']);
        $this->assertEquals(70, $result['available_quantity']);
    }

    public function test_cannot_remove_more_stock_than_available()
    {
        $this->stockService->addStock(1, 50);

        $result = $this->stockService->removeStock(1, 100, 'damage');

        $this->assertArrayHasKey('error', $result);
    }

    public function test_can_reserve_stock()
    {
        $this->stockService->addStock(1, 100);
        $this->stockService->addStock(2, 50);

        $result = $this->stockService->reserveStock(101, [
            ['product_id' => 1, 'quantity' => 30],
            ['product_id' => 2, 'quantity' => 20],
        ]);

        $this->assertEquals(2, $result['reserved_items']);
        $this->assertEquals(0, $result['failed_items']);
    }

    public function test_reserve_fails_for_insufficient_stock()
    {
        $this->stockService->addStock(1, 50);

        $result = $this->stockService->reserveStock(102, [
            ['product_id' => 1, 'quantity' => 100],
        ]);

        $this->assertEquals(0, $result['reserved_items']);
        $this->assertEquals(1, $result['failed_items']);
    }

    public function test_can_release_reserved_stock()
    {
        $this->stockService->addStock(1, 100);

        // Reserve
        $this->stockService->reserveStock(103, [
            ['product_id' => 1, 'quantity' => 40],
        ]);

        // Release
        $result = $this->stockService->releaseReservedStock(103, [
            ['product_id' => 1, 'quantity' => 40],
        ]);

        $this->assertEquals('released', $result['status']);
        $this->assertEquals(1, $result['items_count']);
    }

    public function test_can_get_stock_levels()
    {
        $this->stockService->addStock(1, 100);
        $this->stockService->addStock(2, 50);
        $this->stockService->addStock(3, 5);

        $levels = $this->stockService->getStockLevels([1, 2, 3]);

        $this->assertArrayHasKey(1, $levels);
        $this->assertEquals(100, $levels[1]['total_quantity']);
        $this->assertEquals(100, $levels[1]['available_quantity']);
        $this->assertEquals('normal', $levels[1]['status']);
    }

    public function test_stock_status_reflects_levels()
    {
        $this->stockService->addStock(1, 200);  // Overstock
        $this->stockService->addStock(2, 8);    // Low stock
        $this->stockService->addStock(3, 0);    // Out of stock

        $levels = $this->stockService->getStockLevels([1, 2, 3]);

        $this->assertEquals('overstock', $levels[1]['status']);
        $this->assertEquals('low_stock', $levels[2]['status']);
        $this->assertEquals('out_of_stock', $levels[3]['status']);
    }

    public function test_can_get_low_stock_alerts()
    {
        $this->stockService->addStock(1, 5);
        $this->stockService->addStock(2, 0);
        $this->stockService->addStock(3, 50);

        $alerts = $this->stockService->getLowStockAlerts(10);

        $this->assertGreaterThan(0, $alerts['total_alerts']);
    }

    public function test_reserved_stock_reduces_availability()
    {
        $this->stockService->addStock(1, 100);

        $levels1 = $this->stockService->getStockLevels([1]);
        $this->assertEquals(100, $levels1[1]['available_quantity']);

        // Reserve 30 units
        $this->stockService->reserveStock(104, [
            ['product_id' => 1, 'quantity' => 30],
        ]);

        $levels2 = $this->stockService->getStockLevels([1]);
        $this->assertEquals(70, $levels2[1]['available_quantity']);
        $this->assertEquals(100, $levels2[1]['total_quantity']);
    }

    public function test_complete_order_workflow()
    {
        // Initial stock
        $this->stockService->addStock(1, 100);
        $this->stockService->addStock(2, 50);

        // Reserve for order
        $this->stockService->reserveStock(105, [
            ['product_id' => 1, 'quantity' => 20],
            ['product_id' => 2, 'quantity' => 10],
        ]);

        // Verify availability reduced
        $levels = $this->stockService->getStockLevels([1, 2]);
        $this->assertEquals(80, $levels[1]['available_quantity']);
        $this->assertEquals(40, $levels[2]['available_quantity']);

        // Remove stock for fulfilled order
        $this->stockService->removeStock(1, 20, 'order_105');
        $this->stockService->removeStock(2, 10, 'order_105');

        // Final levels
        $finalLevels = $this->stockService->getStockLevels([1, 2]);
        $this->assertEquals(80, $finalLevels[1]['total_quantity']);
        $this->assertEquals(40, $finalLevels[2]['total_quantity']);
    }
}
