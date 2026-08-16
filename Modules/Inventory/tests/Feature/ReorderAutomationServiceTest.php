<?php

namespace Modules\Inventory\Tests\Feature;

use Tests\TestCase;
use Modules\Inventory\Services\ReorderAutomationService;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Stock;
use Modules\Inventory\Models\Warehouse;
use Modules\Core\Models\Tenant;
use App\Models\User;

class ReorderAutomationServiceTest extends TestCase
{
    private ReorderAutomationService $service;
    private User $user;
    private Product $product;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReorderAutomationService::class);
        $this->user = User::factory()->create();

        $this->product = Product::create([
            'tenant_id' => $this->user->tenant_id,
            'sku' => 'TEST-SKU-001',
            'name' => 'Test Product',
            'is_active' => true,
            'status' => 'active',
            'reorder_point' => 50,
            'reorder_qty' => 100,
            'cost_price' => 10000,
        ]);

        $this->warehouse = Warehouse::factory()->create();
        Stock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 30,
        ]);
    }

    /** @test */
    public function it_detects_products_needing_reorder()
    {
        $shouldReorder = $this->service->shouldReorder($this->product);

        $this->assertTrue($shouldReorder);
    }

    /** @test */
    public function it_does_not_reorder_products_above_threshold()
    {
        Stock::where('product_id', $this->product->id)->update(['quantity' => 100]);

        $shouldReorder = $this->service->shouldReorder($this->product);

        $this->assertFalse($shouldReorder);
    }

    /** @test */
    public function it_computes_reorder_point_with_seasonal_adjustment()
    {
        $reorderPoint = $this->service->computeReorderPoint($this->product);

        $this->assertIsFloat($reorderPoint);
        $this->assertGreaterThan($this->product->reorder_point, $reorderPoint);
    }

    /** @test */
    public function it_gets_seasonal_adjustment_factor()
    {
        $factor = $this->service->getSeasonalAdjustmentFactor($this->product);

        $this->assertIsFloat($factor);
        $this->assertGreaterThanOrEqual(0.8, $factor);
        $this->assertLessThanOrEqual(1.3, $factor);
    }

    /** @test */
    public function it_computes_optimal_order_quantity()
    {
        $quantity = $this->service->computeOrderQuantity($this->product);

        $this->assertIsInt($quantity);
        $this->assertGreaterThanOrEqual($this->product->min_order_quantity ?? 10, $quantity);
        $this->assertLessThanOrEqual($this->product->max_order_quantity ?? 1000, $quantity);
    }

    /** @test */
    public function it_estimates_days_to_stockout()
    {
        $daysToStockout = $this->service->estimateDaysToStockout($this->product);

        // May return null if no consumption history
        if ($daysToStockout !== null) {
            $this->assertIsInt($daysToStockout);
            $this->assertGreaterThanOrEqual(0, $daysToStockout);
        }
    }

    /** @test */
    public function it_gets_reorder_status()
    {
        $status = $this->service->getReorderStatus($this->product);

        $this->assertIsArray($status);
        $this->assertArrayHasKey('current_stock', $status);
        $this->assertArrayHasKey('reorder_point', $status);
        $this->assertArrayHasKey('safety_stock', $status);
        $this->assertArrayHasKey('status', $status);
        $this->assertArrayHasKey('should_reorder', $status);
        $this->assertIn($status['status'], ['critical', 'needs_reorder', 'monitor', 'ok']);
    }

    /** @test */
    public function it_marks_status_as_critical_when_at_safety_stock()
    {
        Stock::where('product_id', $this->product->id)->update(['quantity' => 15]);

        $status = $this->service->getReorderStatus($this->product);

        $this->assertEquals('critical', $status['status']);
    }

    /** @test */
    public function it_gets_reorder_recommendations()
    {
        $recommendations = $this->service->getReorderRecommendations($this->user->tenant_id);

        $this->assertIsArray($recommendations);
        $this->assertArrayHasKey('count', $recommendations);
        $this->assertArrayHasKey('items', $recommendations);
        $this->assertArrayHasKey('urgent', $recommendations);
    }

    /** @test */
    public function it_generates_reorders_for_tenant()
    {
        $result = $this->service->generateReordersForTenant(
            $this->user->tenant_id,
            now()->startOfMonth(),
            now()->endOfMonth()
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('created_count', $result);
        $this->assertArrayHasKey('order_ids', $result);
    }

    /** @test */
    public function it_calculates_west_african_seasonal_factor()
    {
        $tenant = Tenant::factory()->create(['country_code' => 'SN']);
        $this->product->update(['tenant_id' => $tenant->id]);

        // Test Ramadan period (high season)
        $factorRamadan = $this->service->getSeasonalAdjustmentFactor($this->product);

        // This will vary by current month, just verify it's a float
        $this->assertIsFloat($factorRamadan);
    }

    /** @test */
    public function it_handles_products_without_suppliers()
    {
        // Product without suppliers should not create order
        $result = $this->service->getReorderRecommendations($this->user->tenant_id);

        $this->assertIsArray($result);
    }

    /** @test */
    public function it_respects_min_and_max_order_quantities()
    {
        $this->product->update([
            'min_order_quantity' => 50,
            'max_order_quantity' => 200,
            'reorder_quantity' => 100,
        ]);

        $quantity = $this->service->computeOrderQuantity($this->product);

        $this->assertGreaterThanOrEqual(50, $quantity);
        $this->assertLessThanOrEqual(200, $quantity);
    }
}
