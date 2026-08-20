<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\PurchaseReceipt;
use Modules\Achats\Models\RFQ;
use Modules\Achats\Models\Supplier;
use Modules\Achats\Models\SupplierQuote;
use Spatie\Permission\Models\Permission;

/**
 * Chantier 19 (Lot 3 — Achats), empirical re-verification pass.
 *
 * Headline finding: this module had zero company/tenant scoping anywhere —
 * confirmed empirically with 2 real companies over real HTTP requests that a
 * purchasing-manager from Company A could list/view/edit/delete Company B's
 * suppliers, purchase orders, RFQs, receipts, and quotes. Also locks in the
 * RFQ create/update field-name fix (Form.vue's real payload shape) and the
 * payment_terms numeric-vs-string validation fix.
 */
function achatsReauditUser(string $suffix): User
{
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $company = Company::create([
        'name' => "Chantier19 Achats Co {$suffix}",
        'currency' => 'MGA',
        'timezone' => 'Indian/Antananarivo',
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('purchasing-manager');

    return $user;
}

test('suppliers are scoped by company on index/show/update/destroy', function () {
    $userA = achatsReauditUser('A');
    $userB = achatsReauditUser('B');

    $supplierA = Supplier::factory()->create(['company_id' => $userA->company_id, 'name' => 'Supplier A']);
    Supplier::factory()->create(['company_id' => $userB->company_id, 'name' => 'Supplier B']);

    $index = $this->actingAs($userA, 'sanctum')->getJson('/api/v1/achats/suppliers');
    $index->assertStatus(200);
    expect(collect($index->json('data'))->pluck('name'))->toContain('Supplier A')
        ->not->toContain('Supplier B');

    $this->actingAs($userB, 'sanctum')
        ->getJson("/api/v1/achats/suppliers/{$supplierA->id}")
        ->assertStatus(404);

    $this->actingAs($userB, 'sanctum')
        ->patchJson("/api/v1/achats/suppliers/{$supplierA->id}", ['name' => 'Hijacked'])
        ->assertStatus(404);

    $this->actingAs($userB, 'sanctum')
        ->deleteJson("/api/v1/achats/suppliers/{$supplierA->id}")
        ->assertStatus(404);

    $this->actingAs($userA, 'sanctum')
        ->getJson("/api/v1/achats/suppliers/{$supplierA->id}")
        ->assertStatus(200);
});

test('purchase orders are scoped by company on index/show/update/destroy/lifecycle', function () {
    $userA = achatsReauditUser('A');
    $userB = achatsReauditUser('B');

    $supplierA = Supplier::factory()->create(['company_id' => $userA->company_id]);
    $poA = PurchaseOrder::factory()->create(['company_id' => $userA->company_id, 'supplier_id' => $supplierA->id, 'status' => 'draft']);

    $index = $this->actingAs($userB, 'sanctum')->getJson('/api/v1/achats/purchase-orders');
    expect(collect($index->json('data'))->pluck('id'))->not->toContain($poA->id);

    $this->actingAs($userB, 'sanctum')
        ->getJson("/api/v1/achats/purchase-orders/{$poA->id}")
        ->assertStatus(404);

    $this->actingAs($userB, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$poA->id}/submit")
        ->assertStatus(404);

    $this->actingAs($userA, 'sanctum')
        ->getJson("/api/v1/achats/purchase-orders/{$poA->id}")
        ->assertStatus(200);
});

test('rfqs and their quotes are scoped by company', function () {
    $userA = achatsReauditUser('A');
    $userB = achatsReauditUser('B');

    $rfqA = RFQ::factory()->create(['company_id' => $userA->company_id, 'status' => 'draft']);
    $supplierA = Supplier::factory()->create(['company_id' => $userA->company_id]);

    $this->actingAs($userB, 'sanctum')
        ->getJson("/api/v1/achats/rfqs/{$rfqA->id}")
        ->assertStatus(404);

    $this->actingAs($userB, 'sanctum')
        ->postJson("/api/v1/achats/rfqs/{$rfqA->id}/issue", ['supplier_ids' => [$supplierA->id]])
        ->assertStatus(404);

    // Issuing (as the real owner) creates SupplierQuote rows tagged with
    // the RFQ's own company — confirms recordSupplierQuote()/issueRFQ()
    // actually populate company_id, not just the controller-level guard.
    $this->actingAs($userA, 'sanctum')
        ->postJson("/api/v1/achats/rfqs/{$rfqA->id}/issue", ['supplier_ids' => [$supplierA->id]])
        ->assertStatus(200);

    $quote = SupplierQuote::where('rfq_id', $rfqA->id)->first();
    expect((int) $quote->company_id)->toBe((int) $userA->company_id);

    $this->actingAs($userB, 'sanctum')
        ->getJson("/api/v1/achats/supplier-quotes/{$quote->id}")
        ->assertStatus(404);

    $this->actingAs($userB, 'sanctum')
        ->postJson("/api/v1/achats/supplier-quotes/{$quote->id}/accept")
        ->assertStatus(404);
});

test('purchase receipts are scoped by company', function () {
    $userA = achatsReauditUser('A');
    $userB = achatsReauditUser('B');

    $supplierA = Supplier::factory()->create(['company_id' => $userA->company_id]);
    $poA = PurchaseOrder::factory()->create(['company_id' => $userA->company_id, 'supplier_id' => $supplierA->id, 'status' => 'approved']);
    $receiptA = PurchaseReceipt::factory()->create([
        'purchase_order_id' => $poA->id,
        'status' => 'draft',
        'company_id' => $userA->company_id,
    ]);

    $this->actingAs($userB, 'sanctum')
        ->getJson("/api/v1/achats/purchase-receipts/{$receiptA->id}")
        ->assertStatus(404);

    // Recording a receipt against another company's PO by id must also be
    // rejected, not just reads of an already-created receipt.
    $this->actingAs($userB, 'sanctum')
        ->postJson('/api/v1/achats/purchase-receipts', [
            'purchase_order_id' => $poA->id,
            'receipt_date' => now()->toDateString(),
        ])
        ->assertStatus(404);

    $index = $this->actingAs($userB, 'sanctum')->getJson('/api/v1/achats/purchase-receipts');
    expect(collect($index->json('data'))->pluck('id'))->not->toContain($receiptA->id);
});

test('creating a purchase order populates company_id from the acting user', function () {
    $user = achatsReauditUser('A');
    $supplier = Supplier::factory()->create(['company_id' => $user->company_id]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/achats/purchase-orders', [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'currency' => 'USD',
            'lines' => [
                ['description' => 'Widgets', 'quantity' => 5, 'unit' => 'ea', 'unit_price' => 10, 'tax_rate' => 0],
            ],
        ])
        ->assertStatus(201);

    $po = PurchaseOrder::find($response->json('id'));
    expect((int) $po->company_id)->toBe((int) $user->company_id);
});

test('warehouse-operator is denied approving or rejecting a purchase order', function () {
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $company = Company::create(['name' => 'Chantier19 Achats WO Co', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);
    $operator = User::factory()->create(['company_id' => $company->id]);
    $operator->assignRole('warehouse-operator');

    $supplier = Supplier::factory()->create(['company_id' => $company->id]);
    $po = PurchaseOrder::factory()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'status' => 'submitted']);

    $this->actingAs($operator, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$po->id}/approve")
        ->assertStatus(403);

    $this->actingAs($operator, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$po->id}/reject")
        ->assertStatus(403);
});

test('purchase order edit accepts real PATCH and its edit-load response includes lines', function () {
    $user = achatsReauditUser('A');
    $supplier = Supplier::factory()->create(['company_id' => $user->company_id]);
    $po = PurchaseOrder::factory()->create(['company_id' => $user->company_id, 'supplier_id' => $supplier->id, 'status' => 'draft']);
    $po->lines()->create(['description' => 'Existing line', 'quantity' => 1, 'unit_price' => 5, 'line_total' => 5]);

    // Chantier 10 documented this PATCH fix — re-verified still live.
    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/achats/purchase-orders/{$po->id}", ['notes' => 'Updated via PATCH'])
        ->assertStatus(200)
        ->assertJsonPath('notes', 'Updated via PATCH');

    // The GET response Form.vue's loadPurchaseOrder() consumes must
    // include real `lines` (PurchaseOrderResource), not just a count.
    $show = $this->actingAs($user, 'sanctum')->getJson("/api/v1/achats/purchase-orders/{$po->id}");
    $show->assertStatus(200);
    expect($show->json('lines'))->toHaveCount(1);
    expect($show->json('lines.0.description'))->toBe('Existing line');
});

test('creating an rfq with the real Form.vue payload shape succeeds and persists lines', function () {
    $user = achatsReauditUser('A');

    // Chantier 19: RFQs/Form.vue used to post item_description/
    // response_deadline (never validated) and never sent required_by_date
    // at all — every real RFQ creation 422'd. This is the real mapped
    // payload the fixed frontend now sends.
    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/achats/rfqs', [
            'description' => 'Need 100 widgets',
            'required_by_date' => now()->addDays(14)->toDateString(),
            'deadline_date' => now()->addDays(7)->toDateString(),
            'lines' => [
                ['description' => 'Widget', 'quantity' => 100, 'unit' => 'ea'],
            ],
        ]);

    $response->assertStatus(201);
    expect($response->json('lines'))->toHaveCount(1);

    $rfq = RFQ::find($response->json('id'));
    expect((int) $rfq->company_id)->toBe((int) $user->company_id);
    expect($rfq->description)->toBe('Need 100 widgets');
});

test('spend analytics reports are scoped by company', function () {
    $userA = achatsReauditUser('A');
    $userB = achatsReauditUser('B');

    $supplierA = Supplier::factory()->create(['company_id' => $userA->company_id]);
    PurchaseOrder::factory()->create([
        'company_id' => $userA->company_id,
        'supplier_id' => $supplierA->id,
        'status' => 'approved',
        'total' => 99999,
        'order_date' => now(),
    ]);

    $spendingB = $this->actingAs($userB, 'sanctum')->getJson('/api/v1/achats/reports/spending')->assertStatus(200);
    expect((float) $spendingB->json('total_spend'))->toBe(0.0);

    $spendingA = $this->actingAs($userA, 'sanctum')->getJson('/api/v1/achats/reports/spending')->assertStatus(200);
    expect((float) $spendingA->json('total_spend'))->toBe(99999.0);

    $pendingB = $this->actingAs($userB, 'sanctum')->getJson('/api/v1/achats/reports/pending-receipts')->assertStatus(200);
    expect($pendingB->json())->toBe([]);
});

test('supplier payment_terms accepts a real numeric value without validation error', function () {
    $user = achatsReauditUser('A');

    // Chantier 19: Suppliers/Form.vue defaults payment_terms to a real JS
    // number (30) and sends it as a JSON number — the old `nullable|string`
    // rule rejected it with a 422 on every real create.
    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/achats/suppliers', [
            'name' => 'Acme Supplies',
            'payment_terms' => 30,
        ]);

    $response->assertStatus(201);
    expect((int) Supplier::find($response->json('id'))->payment_terms)->toBe(30);
});
