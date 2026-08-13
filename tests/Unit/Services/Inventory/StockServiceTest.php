<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\SKU;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Services\StockService;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StockService $service;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StockService();
        $this->warehouse = Warehouse::factory()->create();
    }

    public function test_can_create_sku_with_valid_data(): void
    {
        $sku = $this->service->createSKU([
            'code' => 'SKU-001',
            'name' => 'Test Product',
            'unit' => 'pcs',
        ]);

        $this->assertInstanceOf(SKU::class, $sku);
        $this->assertEquals('SKU-001', $sku->code);
        $this->assertEquals('Test Product', $sku->name);
    }

    public function test_cannot_create_duplicate_sku_code(): void
    {
        SKU::factory()->create(['code' => 'SKU-001']);

        $this->expectException(\Exception::class);
        $this->service->createSKU([
            'code' => 'SKU-001',
            'name' => 'Duplicate',
            'unit' => 'pcs',
        ]);
    }

    public function test_can_receive_stock(): void
    {
        $sku = SKU::factory()->create();

        $result = $this->service->receiveStock(
            $sku,
            $this->warehouse,
            50,
            'Purchase order #PO-001'
        );

        $this->assertTrue($result);
        $this->assertEquals(50, $sku->getStockInWarehouse($this->warehouse));
    }

    public function test_can_issue_stock(): void
    {
        $sku = SKU::factory()->create();
        $this->service->receiveStock($sku, $this->warehouse, 100);

        $result = $this->service->issueStock(
            $sku,
            $this->warehouse,
            30,
            'Sales order #SO-001'
        );

        $this->assertTrue($result);
        $this->assertEquals(70, $sku->getStockInWarehouse($this->warehouse));
    }

    public function test_cannot_issue_more_stock_than_available(): void
    {
        $sku = SKU::factory()->create();
        $this->service->receiveStock($sku, $this->warehouse, 50);

        $this->expectException(\Exception::class);
        $this->service->issueStock($sku, $this->warehouse, 100);
    }

    public function test_can_adjust_stock(): void
    {
        $sku = SKU::factory()->create();
        $this->service->receiveStock($sku, $this->warehouse, 100);

        $result = $this->service->adjustStock(
            $sku,
            $this->warehouse,
            -10,
            'Inventory adjustment - damage'
        );

        $this->assertTrue($result);
        $this->assertEquals(90, $sku->getStockInWarehouse($this->warehouse));
    }

    public function test_can_transfer_stock_between_warehouses(): void
    {
        $warehouse2 = Warehouse::factory()->create();
        $sku = SKU::factory()->create();
        $this->service->receiveStock($sku, $this->warehouse, 100);

        $result = $this->service->transfer(
            $sku,
            $this->warehouse,
            $warehouse2,
            50
        );

        $this->assertTrue($result);
        $this->assertEquals(50, $sku->getStockInWarehouse($this->warehouse));
        $this->assertEquals(50, $sku->getStockInWarehouse($warehouse2));
    }

    public function test_cannot_transfer_more_stock_than_available(): void
    {
        $warehouse2 = Warehouse::factory()->create();
        $sku = SKU::factory()->create();
        $this->service->receiveStock($sku, $this->warehouse, 50);

        $this->expectException(\Exception::class);
        $this->service->transfer($sku, $this->warehouse, $warehouse2, 100);
    }

    public function test_stock_movement_is_logged(): void
    {
        $sku = SKU::factory()->create();

        $this->service->receiveStock(
            $sku,
            $this->warehouse,
            50,
            'Test movement'
        );

        $this->assertDatabaseHas('inventory_stock_movements', [
            'sku_id' => $sku->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 50,
            'type' => 'in',
            'reference' => 'Test movement',
        ]);
    }

    public function test_can_calculate_total_stock_across_warehouses(): void
    {
        $warehouse2 = Warehouse::factory()->create();
        $sku = SKU::factory()->create();

        $this->service->receiveStock($sku, $this->warehouse, 50);
        $this->service->receiveStock($sku, $warehouse2, 30);

        $total = $this->service->getTotalStock($sku);

        $this->assertEquals(80, $total);
    }

    public function test_can_get_low_stock_skus(): void
    {
        $sku1 = SKU::factory()->create(['reorder_level' => 20]);
        $sku2 = SKU::factory()->create(['reorder_level' => 50]);

        $this->service->receiveStock($sku1, $this->warehouse, 100);
        $this->service->receiveStock($sku2, $this->warehouse, 30);

        $lowStock = $this->service->getLowStockSKUs();

        $this->assertTrue($lowStock->contains('id', $sku2->id));
        $this->assertFalse($lowStock->contains('id', $sku1->id));
    }

    public function test_can_reorder_stock(): void
    {
        $sku = SKU::factory()->create(['reorder_point' => 30, 'reorder_qty' => 100]);
        $this->service->receiveStock($sku, $this->warehouse, 25);

        $needsReorder = $this->service->needsReorder($sku, $this->warehouse);

        $this->assertTrue($needsReorder);
    }
}
