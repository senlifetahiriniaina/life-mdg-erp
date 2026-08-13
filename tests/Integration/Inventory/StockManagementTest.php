<?php

declare(strict_types=1);

namespace Tests\Integration\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\SKU;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\StockMovement;
use Tests\TestCase;

class StockManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_receipt_increases_warehouse_inventory(): void
    {
        $warehouse = Warehouse::factory()->create();
        $sku = SKU::factory()->create();

        // Receive stock
        StockMovement::create([
            'sku_id' => $sku->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'type' => 'in',
            'reference' => 'PO-001',
        ]);

        $stock = $sku->getStockInWarehouse($warehouse);

        $this->assertEquals(100, $stock);
    }

    public function test_stock_issue_decreases_warehouse_inventory(): void
    {
        $warehouse = Warehouse::factory()->create();
        $sku = SKU::factory()->create();

        // Receive stock
        StockMovement::create([
            'sku_id' => $sku->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'type' => 'in',
        ]);

        // Issue stock
        StockMovement::create([
            'sku_id' => $sku->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 30,
            'type' => 'out',
            'reference' => 'SO-001',
        ]);

        $stock = $sku->getStockInWarehouse($warehouse);

        $this->assertEquals(70, $stock);
    }

    public function test_stock_transfer_between_warehouses(): void
    {
        $warehouse1 = Warehouse::factory()->create();
        $warehouse2 = Warehouse::factory()->create();
        $sku = SKU::factory()->create();

        // Receive stock in warehouse1
        StockMovement::create([
            'sku_id' => $sku->id,
            'warehouse_id' => $warehouse1->id,
            'quantity' => 100,
            'type' => 'in',
        ]);

        // Transfer stock
        StockMovement::create([
            'sku_id' => $sku->id,
            'warehouse_id' => $warehouse1->id,
            'quantity' => 50,
            'type' => 'out',
            'reference' => 'Transfer to W2',
        ]);

        StockMovement::create([
            'sku_id' => $sku->id,
            'warehouse_id' => $warehouse2->id,
            'quantity' => 50,
            'type' => 'in',
            'reference' => 'Transfer from W1',
        ]);

        $this->assertEquals(50, $sku->getStockInWarehouse($warehouse1));
        $this->assertEquals(50, $sku->getStockInWarehouse($warehouse2));
    }

    public function test_stock_adjustment_for_inventory_discrepancy(): void
    {
        $warehouse = Warehouse::factory()->create();
        $sku = SKU::factory()->create();

        // Initial stock
        StockMovement::create([
            'sku_id' => $sku->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'type' => 'in',
        ]);

        // Adjustment for damage
        StockMovement::create([
            'sku_id' => $sku->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => -10,
            'type' => 'adjustment',
            'reference' => 'Damaged goods write-off',
        ]);

        $stock = $sku->getStockInWarehouse($warehouse);

        $this->assertEquals(90, $stock);
    }

    public function test_multiple_skus_in_single_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create();
        $sku1 = SKU::factory()->create();
        $sku2 = SKU::factory()->create();

        StockMovement::create([
            'sku_id' => $sku1->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'type' => 'in',
        ]);

        StockMovement::create([
            'sku_id' => $sku2->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 50,
            'type' => 'in',
        ]);

        $movements = StockMovement::where('warehouse_id', $warehouse->id)->count();

        $this->assertEquals(2, $movements);
    }

    public function test_stock_movement_audit_trail(): void
    {
        $warehouse = Warehouse::factory()->create();
        $sku = SKU::factory()->create();

        StockMovement::create([
            'sku_id' => $sku->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'type' => 'in',
            'reference' => 'PO-001',
            'created_by' => 'user1',
        ]);

        StockMovement::create([
            'sku_id' => $sku->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 30,
            'type' => 'out',
            'reference' => 'SO-001',
            'created_by' => 'user2',
        ]);

        $movements = StockMovement::where('sku_id', $sku->id)->get();

        $this->assertEquals(2, $movements->count());
        $this->assertTrue($movements->contains('created_by', 'user1'));
        $this->assertTrue($movements->contains('created_by', 'user2'));
    }

    public function test_warehouse_stock_summary(): void
    {
        $warehouse = Warehouse::factory()->create();
        $sku1 = SKU::factory()->create();
        $sku2 = SKU::factory()->create();
        $sku3 = SKU::factory()->create();

        StockMovement::create([
            'sku_id' => $sku1->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'type' => 'in',
        ]);

        StockMovement::create([
            'sku_id' => $sku2->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 50,
            'type' => 'in',
        ]);

        StockMovement::create([
            'sku_id' => $sku3->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 75,
            'type' => 'in',
        ]);

        $movements = StockMovement::where('warehouse_id', $warehouse->id)->count();

        $this->assertEquals(3, $movements);
    }
}
