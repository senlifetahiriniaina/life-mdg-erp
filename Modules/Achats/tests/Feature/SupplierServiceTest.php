<?php

namespace Modules\Achats\Tests\Feature;

use App\Models\User;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\Supplier;
use Modules\Achats\Services\SupplierService;
use Tests\TestCase;

class SupplierServiceTest extends TestCase
{
    protected SupplierService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SupplierService::class);
    }

    public function test_can_create_supplier()
    {
        $user = User::factory()->create();

        $data = [
            'name' => 'Acme Corporation',
            'email' => 'contact@acme.com',
            'phone' => '+1-555-0123',
            'country' => 'USA',
            'created_by' => $user->id,
        ];

        $supplier = $this->service->createSupplier($data);

        $this->assertInstanceOf(Supplier::class, $supplier);
        $this->assertEquals('Acme Corporation', $supplier->name);
        $this->assertTrue($supplier->is_active);
    }

    public function test_supplier_code_is_generated()
    {
        $supplier = $this->service->createSupplier([
            'name' => 'Test Supplier',
            'created_by' => User::factory()->create()->id,
        ]);

        $this->assertStringContainsString('SUP-', $supplier->code);
        $this->assertNotEmpty($supplier->code);
    }

    public function test_can_update_supplier()
    {
        $supplier = Supplier::factory()->create();

        $this->service->updateSupplier($supplier, [
            'email' => 'newemail@test.com',
            'phone' => '+1-555-9999',
        ]);

        $supplier->refresh();
        $this->assertEquals('newemail@test.com', $supplier->email);
    }

    public function test_can_deactivate_supplier()
    {
        $supplier = Supplier::factory()->create(['is_active' => true]);

        $this->service->deactivateSupplier($supplier);

        $supplier->refresh();
        $this->assertFalse($supplier->is_active);
    }

    public function test_can_activate_supplier()
    {
        $supplier = Supplier::factory()->create(['is_active' => false]);

        $this->service->activateSupplier($supplier);

        $supplier->refresh();
        $this->assertTrue($supplier->is_active);
    }

    public function test_get_active_suppliers()
    {
        Supplier::factory()->count(3)->create(['is_active' => true]);
        Supplier::factory()->count(2)->create(['is_active' => false]);

        $active = $this->service->getActiveSuppliers();

        $this->assertEquals(3, $active->count());
    }

    public function test_get_supplier_by_code()
    {
        $supplier = Supplier::factory()->create(['code' => 'SUP-TEST-001']);

        $found = $this->service->getSupplierByCode('SUP-TEST-001');

        $this->assertNotNull($found);
        $this->assertEquals($supplier->id, $found->id);
    }

    public function test_get_supplier_performance_metrics()
    {
        $supplier = Supplier::factory()->create();

        $metrics = $this->service->getSupplierPerformanceMetrics($supplier);

        $this->assertArrayHasKey('total_orders', $metrics);
        $this->assertArrayHasKey('total_spent', $metrics);
        $this->assertArrayHasKey('average_order_value', $metrics);
        $this->assertArrayHasKey('on_time_delivery', $metrics);
        $this->assertArrayHasKey('quality_score', $metrics);
    }

    public function test_cannot_delete_supplier_with_orders()
    {
        $supplier = Supplier::factory()
            ->has(PurchaseOrder::factory()->count(2))
            ->create();

        $this->expectException(\Exception::class);

        $this->service->deleteSupplier($supplier);
    }
}
