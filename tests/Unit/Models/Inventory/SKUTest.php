<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\SKU;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\StockMovement;
use Tests\TestCase;

class SKUTest extends TestCase
{
    use RefreshDatabase;

    public function test_sku_has_many_stock_movements(): void
    {
        $sku = SKU::factory()->create();
        StockMovement::factory()->count(5)->create(['sku_id' => $sku->id]);

        $this->assertEquals(5, $sku->stockMovements()->count());
    }

    public function test_sku_code_is_required(): void
    {
        $this->expectException(\Exception::class);
        SKU::create(['name' => 'Test', 'unit' => 'pcs']);
    }

    public function test_sku_code_must_be_unique(): void
    {
        SKU::factory()->create(['code' => 'SKU-001']);

        $this->expectException(\Exception::class);
        SKU::create(['code' => 'SKU-001', 'name' => 'Test', 'unit' => 'pcs']);
    }

    public function test_sku_scope_active_returns_only_active_skus(): void
    {
        SKU::factory()->count(3)->create(['is_active' => true]);
        SKU::factory()->count(2)->create(['is_active' => false]);

        $active = SKU::active()->count();

        $this->assertEquals(3, $active);
    }

    public function test_sku_can_calculate_total_stock(): void
    {
        $warehouse1 = Warehouse::factory()->create();
        $warehouse2 = Warehouse::factory()->create();
        $sku = SKU::factory()->create();

        StockMovement::factory()->create([
            'sku_id' => $sku->id,
            'warehouse_id' => $warehouse1->id,
            'quantity' => 50,
            'type' => 'in',
        ]);

        StockMovement::factory()->create([
            'sku_id' => $sku->id,
            'warehouse_id' => $warehouse2->id,
            'quantity' => 30,
            'type' => 'in',
        ]);

        $total = $sku->getTotalStock();

        $this->assertEquals(80, $total);
    }

    public function test_sku_can_get_stock_in_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create();
        $sku = SKU::factory()->create();

        StockMovement::factory()->create([
            'sku_id' => $sku->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'type' => 'in',
        ]);

        $stock = $sku->getStockInWarehouse($warehouse);

        $this->assertEquals(100, $stock);
    }

    public function test_sku_tracks_reorder_points(): void
    {
        $sku = SKU::factory()->create([
            'reorder_point' => 20,
            'reorder_qty' => 100,
        ]);

        $this->assertEquals(20, $sku->reorder_point);
        $this->assertEquals(100, $sku->reorder_qty);
    }

    public function test_sku_can_be_marked_inactive(): void
    {
        $sku = SKU::factory()->create(['is_active' => true]);

        $sku->deactivate();

        $this->assertFalse($sku->refresh()->is_active);
    }

    public function test_sku_scope_low_stock_returns_below_reorder_point(): void
    {
        $warehouse = Warehouse::factory()->create();
        $sku1 = SKU::factory()->create(['reorder_point' => 50]);
        $sku2 = SKU::factory()->create(['reorder_point' => 100]);

        StockMovement::factory()->create([
            'sku_id' => $sku1->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 60,
            'type' => 'in',
        ]);

        StockMovement::factory()->create([
            'sku_id' => $sku2->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 80,
            'type' => 'in',
        ]);

        $lowStock = SKU::lowStock()->get();

        $this->assertTrue($lowStock->contains('id', $sku2->id));
    }
}
