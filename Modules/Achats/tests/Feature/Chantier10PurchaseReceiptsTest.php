<?php

namespace Modules\Achats\Tests\Feature;

use App\Models\User;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\PurchaseOrderLine;
use Modules\Achats\Models\PurchaseReceipt;
use Modules\Achats\Models\Supplier;
use Tests\TestCase;

/**
 * Chantier 10 re-verification pass: PurchaseReceiptController was almost
 * entirely stubbed (index()/store()/recordQualityIssue() were literal
 * "// Implementation to follow" placeholders), and its update()/destroy()
 * routes pointed at methods that didn't exist on the class at all (a fatal
 * "call to undefined method" on every real request) — the real, already-
 * built PurchaseReceipts/{Index,Form,Show}.vue pages call all of this and
 * were fully broken end to end. This locks in the full rebuild.
 */
class Chantier10PurchaseReceiptsTest extends TestCase
{
    protected User $user;

    protected Supplier $supplier;

    protected PurchaseOrder $po;

    protected PurchaseOrderLine $poLine;

    protected function setUp(): void
    {
        parent::setUp();
        if (\Spatie\Permission\Models\Permission::count() === 0) {
            $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        }
        $this->user = User::factory()->create();
        $this->user->assignRole('purchasing-manager');
        $this->supplier = Supplier::factory()->create();
        $this->po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'status' => 'draft']);
        $this->poLine = PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $this->po->id,
            'quantity' => 10,
        ]);
    }

    public function test_can_create_a_purchase_receipt_with_lines()
    {
        // Chantier 32.13: createReceipt() now requires an 'approved' PO —
        // $this->po (used by the line-creation test below) must stay
        // 'draft' since addLineItem() itself requires draft, so this test
        // uses its own dedicated approved PO instead.
        $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'status' => 'approved']);
        $poLine = PurchaseOrderLine::factory()->create(['purchase_order_id' => $po->id, 'quantity' => 10]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/achats/purchase-receipts', [
                'purchase_order_id' => $po->id,
                'receipt_date' => now()->toDateString(),
                'lines' => [
                    ['po_line_id' => $poLine->id, 'quantity_received' => 8, 'quality_status' => 'good'],
                ],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('purchase_order.id', $po->id);
        $response->assertJsonCount(1, 'lines');

        $receipt = PurchaseReceipt::where('purchase_order_id', $po->id)->firstOrFail();
        $this->assertEquals(1, $receipt->lines()->count());
        $this->assertEquals('good', $receipt->lines()->first()->quality_status);
    }

    /**
     * Chantier 32.13 (layer 8, business validation): confirmed empirically
     * before this fix that a receipt could be created against a PO that had
     * never been submitted/approved — a real gap, not hypothetical.
     */
    public function test_cannot_create_a_purchase_receipt_against_a_non_approved_purchase_order()
    {
        // $this->po is 'draft' (setUp()).
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/achats/purchase-receipts', [
                'purchase_order_id' => $this->po->id,
                'receipt_date' => now()->toDateString(),
                'lines' => [
                    ['po_line_id' => $this->poLine->id, 'quantity_received' => 8, 'quality_status' => 'good'],
                ],
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('achats_purchase_receipts', ['purchase_order_id' => $this->po->id]);
    }

    public function test_can_list_purchase_receipts_with_pagination_meta()
    {
        PurchaseReceipt::factory()->count(3)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/achats/purchase-receipts');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta']);
    }

    public function test_can_view_a_purchase_receipt()
    {
        $receipt = PurchaseReceipt::factory()->create(['purchase_order_id' => $this->po->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/achats/purchase-receipts/{$receipt->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('id', $receipt->id);
    }

    public function test_can_update_a_purchase_receipt_previously_a_dead_route()
    {
        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $this->po->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/achats/purchase-receipts/{$receipt->id}", [
                'notes' => 'Updated notes',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('achats_purchase_receipts', ['id' => $receipt->id, 'notes' => 'Updated notes']);
    }

    public function test_can_delete_a_purchase_receipt_previously_a_dead_route()
    {
        $receipt = PurchaseReceipt::factory()->create(['purchase_order_id' => $this->po->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/achats/purchase-receipts/{$receipt->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('achats_purchase_receipts', ['id' => $receipt->id]);
    }

    public function test_can_record_a_quality_issue_on_a_receipt_line()
    {
        $receipt = PurchaseReceipt::factory()->create(['purchase_order_id' => $this->po->id, 'status' => 'draft']);
        $line = $receipt->lines()->create([
            'purchase_order_line_id' => $this->poLine->id,
            'quantity_received' => 5,
            'quality_status' => 'good',
            'variance_qty' => 0,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/achats/purchase-receipts/{$receipt->id}/quality-issue", [
                'line_id' => $line->id,
                'issue_type' => 'damaged',
                'description' => 'Box was crushed in transit',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('achats_purchase_receipt_lines', [
            'id' => $line->id,
            'quality_status' => 'damaged',
        ]);
    }

    public function test_can_create_a_purchase_order_line_via_the_nested_endpoint_previously_a_stub()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/achats/purchase-orders/{$this->po->id}/lines", [
                'description' => 'New widget',
                'quantity' => 3,
                'unit' => 'ea',
                'unit_price' => 10,
                'tax_rate' => 0,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('achats_purchase_order_lines', [
            'purchase_order_id' => $this->po->id,
            'description' => 'New widget',
        ]);
    }
}
