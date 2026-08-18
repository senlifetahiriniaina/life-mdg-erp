<?php

namespace Modules\Achats\Tests\Feature;

use App\Models\User;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\RFQ;
use Modules\Achats\Models\Supplier;
use Tests\TestCase;

/**
 * Chantier 10 re-verification pass: PurchaseOrders/Form.vue and RFQs/Form.vue
 * both submit their entire form (header fields + a `lines` array) as one
 * request body, but PurchaseOrderController::store()/update() and
 * RFQController::store()/update() previously ignored `lines` completely —
 * every real PO/RFQ created through the UI silently ended up with zero line
 * items. This test locks in the fix (lines now actually persist) and the
 * companion RFQController::index() pagination-meta bug fix.
 */
class Chantier10AchatsDataLossTest extends TestCase
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

    public function test_creating_a_purchase_order_with_lines_actually_persists_them()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/achats/purchase-orders', [
                'supplier_id' => $this->supplier->id,
                'order_date' => now()->toDateString(),
                'currency' => 'USD',
                'lines' => [
                    ['description' => 'Widgets', 'quantity' => 10, 'unit' => 'ea', 'unit_price' => 5, 'tax_rate' => 0],
                    ['description' => 'Gadgets', 'quantity' => 2, 'unit' => 'ea', 'unit_price' => 20, 'tax_rate' => 18],
                ],
            ]);

        $response->assertStatus(201);
        $response->assertJsonCount(2, 'lines');

        $po = PurchaseOrder::where('supplier_id', $this->supplier->id)->firstOrFail();
        $this->assertEquals(2, $po->lines()->count());
        $this->assertDatabaseHas('achats_purchase_order_lines', [
            'purchase_order_id' => $po->id,
            'description' => 'Widgets',
        ]);
    }

    public function test_editing_a_purchase_order_replaces_its_lines()
    {
        $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'status' => 'draft']);
        $po->lines()->create([
            'description' => 'Old line', 'quantity' => 1, 'unit' => 'ea', 'unit_price' => 1, 'tax_rate' => 0, 'line_total' => 1,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/achats/purchase-orders/{$po->id}", [
                'lines' => [
                    ['description' => 'New line', 'quantity' => 5, 'unit' => 'ea', 'unit_price' => 3, 'tax_rate' => 0],
                ],
            ]);

        $response->assertStatus(200);
        $po->refresh();
        $this->assertEquals(1, $po->lines()->count());
        $this->assertEquals('New line', $po->lines()->first()->description);
    }

    public function test_purchase_order_response_includes_lines_for_edit_prefill()
    {
        $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id]);
        $po->lines()->create([
            'description' => 'Line A', 'quantity' => 1, 'unit' => 'ea', 'unit_price' => 1, 'tax_rate' => 0, 'line_total' => 1,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/achats/purchase-orders/{$po->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'lines');
        $response->assertJsonPath('lines.0.description', 'Line A');
    }

    public function test_creating_an_rfq_with_lines_actually_persists_them()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/achats/rfqs', [
                'description' => 'Office supplies',
                'required_by_date' => now()->addDays(30)->toDateString(),
                'lines' => [
                    ['description' => 'Paper reams', 'quantity' => 50, 'unit' => 'box'],
                ],
            ]);

        $response->assertStatus(201);
        $response->assertJsonCount(1, 'lines');

        $rfq = RFQ::where('description', 'Office supplies')->firstOrFail();
        $this->assertEquals(1, $rfq->lines()->count());
    }

    public function test_rfq_index_response_has_pagination_meta()
    {
        RFQ::factory()->count(3)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/achats/rfqs');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta' => ['current_page', 'from', 'to', 'last_page', 'per_page', 'total']]);
        $response->assertJsonPath('meta.total', 3);
    }
}
