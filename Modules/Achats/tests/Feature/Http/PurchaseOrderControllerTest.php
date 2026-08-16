<?php

namespace Modules\Achats\Tests\Feature\Http;

use App\Models\User;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\Supplier;
use Tests\TestCase;

class PurchaseOrderControllerTest extends TestCase
{
    protected User $user;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        if (\Spatie\Permission\Models\Permission::count() === 0) {
            $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        }
        $this->user = User::factory()->create();
        $this->user->assignRole('purchasing-manager');
        $this->supplier = Supplier::factory()->create();
    }

    public function test_can_list_purchase_orders()
    {
        PurchaseOrder::factory()->count(5)->create(['supplier_id' => $this->supplier->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/achats/purchase-orders');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta']);
    }

    public function test_can_create_purchase_order()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/achats/purchase-orders', [
                'supplier_id' => $this->supplier->id,
                'order_date' => now()->toDateString(),
                'delivery_date' => now()->addDays(10)->toDateString(),
                'currency' => 'USD',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['id', 'po_number', 'status']);
        $this->assertDatabaseHas('achats_purchase_orders', [
            'supplier_id' => $this->supplier->id,
        ]);
    }

    public function test_can_view_purchase_order()
    {
        $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/achats/purchase-orders/{$po->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('id', $po->id);
        $response->assertJsonPath('po_number', $po->po_number);
    }

    public function test_can_submit_po_for_approval()
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/achats/purchase-orders/{$po->id}/submit");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'submitted');
    }

    public function test_can_approve_purchase_order()
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/achats/purchase-orders/{$po->id}/approve");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'approved');
    }

    public function test_can_cancel_purchase_order()
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/achats/purchase-orders/{$po->id}/cancel", [
                'reason' => 'Budget constraints',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'cancelled');
    }

    public function test_requires_authentication()
    {
        $response = $this->getJson('/api/v1/achats/purchase-orders');

        $response->assertStatus(401);
    }

    public function test_filter_purchase_orders_by_status()
    {
        PurchaseOrder::factory()->count(3)->create(['status' => 'draft']);
        PurchaseOrder::factory()->count(2)->create(['status' => 'approved']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/achats/purchase-orders?status=draft');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }
}
