<?php

declare(strict_types=1);

/**
 * Chantier 32.13 — audit approfondi en 14 couches de Modules\Achats.
 *
 * Locks in every real bug found and fixed during this chantier's empirical
 * pass (not a re-read of prior chantiers' already-covered ground). See
 * CLAUDE.md's own "Chantier 32.13 — Achats" entry for the full narrative.
 */

use App\Models\Company;
use App\Models\User;
use Modules\Achats\Models\PurchaseInvoiceMatch;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\PurchaseOrderLine;
use Modules\Achats\Models\PurchaseReceipt;
use Modules\Achats\Models\RFQ;
use Modules\Achats\Models\RFQLine;
use Modules\Achats\Models\Supplier;
use Modules\Achats\Models\SupplierQuote;
use Modules\Achats\Services\ApprovalRoutingService;
use Modules\Achats\Services\PurchaseOrderService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function achats1313User(string $suffix, string $role = 'purchasing-manager', ?Company $company = null): User
{
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $company ??= Company::create([
        'name' => "Chantier32.13 Co {$suffix}",
        'currency' => 'MGA',
        'timezone' => 'Indian/Antananarivo',
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

// ---------------------------------------------------------------------------
// Layer 8 — business validation
// ---------------------------------------------------------------------------

test('a receipt cannot be recorded against a purchase order that has never been approved', function () {
    $user = achats1313User('A');
    $supplier = Supplier::factory()->create(['company_id' => $user->company_id]);
    $po = PurchaseOrder::factory()->create(['company_id' => $user->company_id, 'supplier_id' => $supplier->id, 'status' => 'draft']);
    $line = PurchaseOrderLine::factory()->create(['purchase_order_id' => $po->id, 'quantity' => 5]);

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/achats/purchase-receipts', [
        'purchase_order_id' => $po->id,
        'lines' => [['po_line_id' => $line->id, 'quantity_received' => 5]],
    ]);

    $response->assertStatus(422);
    test()->assertDatabaseMissing('achats_purchase_receipts', ['purchase_order_id' => $po->id]);
});

test('a receipt CAN be recorded against a real approved purchase order', function () {
    $user = achats1313User('B');
    $supplier = Supplier::factory()->create(['company_id' => $user->company_id]);
    $po = PurchaseOrder::factory()->create(['company_id' => $user->company_id, 'supplier_id' => $supplier->id, 'status' => 'approved']);
    $line = PurchaseOrderLine::factory()->create(['purchase_order_id' => $po->id, 'quantity' => 5]);

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/achats/purchase-receipts', [
        'purchase_order_id' => $po->id,
        'lines' => [['po_line_id' => $line->id, 'quantity_received' => 5]],
    ]);

    $response->assertStatus(201);
});

test('a purchase order that was never submitted can still be force-approved directly (preserved legacy behavior)', function () {
    // ApprovalRoutingIntegrationTest::test_marking_a_po_approved_without_a_pending_request_does_not_fail
    // already locks this in at the service layer — this confirms the same
    // real HTTP endpoint still allows it (draft is a deliberately allowed
    // starting state, not just 'submitted').
    $user = achats1313User('C', 'admin');
    $supplier = Supplier::factory()->create(['company_id' => $user->company_id]);
    $po = PurchaseOrder::factory()->create(['company_id' => $user->company_id, 'supplier_id' => $supplier->id, 'status' => 'draft']);

    test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/achats/purchase-orders/{$po->id}/approve")
        ->assertOk();

    expect($po->fresh()->status)->toBe('approved');
});

test('an already-received purchase order cannot be silently re-approved', function () {
    $user = achats1313User('D', 'admin');
    $supplier = Supplier::factory()->create(['company_id' => $user->company_id]);
    $po = PurchaseOrder::factory()->create(['company_id' => $user->company_id, 'supplier_id' => $supplier->id, 'status' => 'received']);

    $response = test()->actingAs($user, 'sanctum')->postJson("/api/v1/achats/purchase-orders/{$po->id}/approve");

    $response->assertStatus(422);
    expect($po->fresh()->status)->toBe('received');
});

test('an already-cancelled purchase order cannot be silently rejected', function () {
    $user = achats1313User('E', 'admin');
    $supplier = Supplier::factory()->create(['company_id' => $user->company_id]);
    $po = PurchaseOrder::factory()->create(['company_id' => $user->company_id, 'supplier_id' => $supplier->id, 'status' => 'cancelled']);

    $response = test()->actingAs($user, 'sanctum')->postJson("/api/v1/achats/purchase-orders/{$po->id}/reject");

    $response->assertStatus(422);
    expect($po->fresh()->status)->toBe('cancelled');
});

test('creating a supplier quote requires the supplier to actually belong to the RFQ\'s own company', function () {
    $user = achats1313User('F');
    $otherCompany = Company::create(['name' => 'Other Co F', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);
    $foreignSupplier = Supplier::factory()->create(['company_id' => $otherCompany->id]);
    $rfq = RFQ::factory()->create(['company_id' => $user->company_id, 'required_by_date' => now()->addDays(7)]);

    $response = test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/achats/rfqs/{$rfq->id}/suppliers/{$foreignSupplier->id}/quote", [
            'unit_price' => 100,
            'total_price' => 1000,
        ]);

    $response->assertStatus(422);
});

// ---------------------------------------------------------------------------
// Layer 6 — security deep (IDOR + cross-tenant leaks)
// ---------------------------------------------------------------------------

test('a purchase order line cannot be viewed/updated/deleted via a different purchase order than its own', function () {
    $user = achats1313User('G');
    $supplier = Supplier::factory()->create(['company_id' => $user->company_id]);
    $po1 = PurchaseOrder::factory()->create(['company_id' => $user->company_id, 'supplier_id' => $supplier->id, 'status' => 'draft']);
    $po2 = PurchaseOrder::factory()->create(['company_id' => $user->company_id, 'supplier_id' => $supplier->id, 'status' => 'draft']);
    $lineOfPo2 = PurchaseOrderLine::factory()->create(['purchase_order_id' => $po2->id, 'quantity' => 1, 'unit_price' => 10]);

    test()->actingAs($user, 'sanctum')
        ->getJson("/api/v1/achats/purchase-orders/{$po1->id}/lines/{$lineOfPo2->id}")
        ->assertNotFound();

    test()->actingAs($user, 'sanctum')
        ->putJson("/api/v1/achats/purchase-orders/{$po1->id}/lines/{$lineOfPo2->id}", ['quantity' => 999])
        ->assertNotFound();
    expect($lineOfPo2->fresh()->quantity)->not->toEqual(999.0);

    test()->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/achats/purchase-orders/{$po1->id}/lines/{$lineOfPo2->id}")
        ->assertNotFound();
    test()->assertDatabaseHas('achats_purchase_order_lines', ['id' => $lineOfPo2->id]);

    // The real, correct path (line under its own real PO) still works.
    test()->actingAs($user, 'sanctum')
        ->getJson("/api/v1/achats/purchase-orders/{$po2->id}/lines/{$lineOfPo2->id}")
        ->assertOk();
});

test('an RFQ line cannot be viewed/updated/deleted via a different RFQ than its own', function () {
    $user = achats1313User('H');
    $rfq1 = RFQ::factory()->create(['company_id' => $user->company_id, 'status' => 'draft', 'required_by_date' => now()->addDays(7)]);
    $rfq2 = RFQ::factory()->create(['company_id' => $user->company_id, 'status' => 'draft', 'required_by_date' => now()->addDays(7)]);
    $lineOfRfq2 = RFQLine::factory()->create(['rfq_id' => $rfq2->id, 'description' => 'Belongs to RFQ2']);

    test()->actingAs($user, 'sanctum')
        ->getJson("/api/v1/achats/rfqs/{$rfq1->id}/lines/{$lineOfRfq2->id}")
        ->assertNotFound();

    test()->actingAs($user, 'sanctum')
        ->putJson("/api/v1/achats/rfqs/{$rfq1->id}/lines/{$lineOfRfq2->id}", ['description' => 'Hijacked'])
        ->assertNotFound();
    expect($lineOfRfq2->fresh()->description)->toBe('Belongs to RFQ2');

    test()->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/achats/rfqs/{$rfq1->id}/lines/{$lineOfRfq2->id}")
        ->assertNotFound();
    test()->assertDatabaseHas('achats_rfq_lines', ['id' => $lineOfRfq2->id]);
});

test('supplier search cannot be used to leak another company\'s supplier by code or email', function () {
    $userA = achats1313User('I');
    $companyB = Company::create(['name' => 'Secret Co I', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);
    Supplier::factory()->create([
        'company_id' => $companyB->id,
        'name' => 'SecretSupplierB',
        'code' => 'SECRET-CODE-I',
        'email' => 'secret-i@b.test',
    ]);

    $response = test()->actingAs($userA, 'sanctum')
        ->getJson('/api/v1/achats/suppliers?search=SECRET-CODE-I');

    $response->assertStatus(200);
    $response->assertJsonCount(0, 'data');
});

test('two different companies can both use the same supplier code', function () {
    $userA = achats1313User('J1');
    $userB = achats1313User('J2');
    Supplier::factory()->create(['company_id' => $userA->company_id, 'code' => 'SHARED-CODE']);

    $response = test()->actingAs($userB, 'sanctum')->postJson('/api/v1/achats/suppliers', [
        'name' => 'Company B Supplier',
        'code' => 'SHARED-CODE',
    ]);

    $response->assertStatus(201);
});

test('a duplicate supplier code within the SAME company is still rejected', function () {
    $user = achats1313User('K');
    Supplier::factory()->create(['company_id' => $user->company_id, 'code' => 'DUP-CODE']);

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/achats/suppliers', [
        'name' => 'Another supplier',
        'code' => 'DUP-CODE',
    ]);

    $response->assertStatus(422);
});

// ---------------------------------------------------------------------------
// Layer 9 — fake/dead: ThreeWayMatchController activation + notification fix
// ---------------------------------------------------------------------------

test('the three-way match feature is now reachable via real routes with real company scoping', function () {
    $user = achats1313User('L');
    $po = PurchaseOrder::factory()->create(['company_id' => $user->company_id, 'total' => 100.0]);
    PurchaseOrderLine::factory()->create(['purchase_order_id' => $po->id, 'quantity' => 1, 'unit_price' => 100]);
    $receipt = PurchaseReceipt::factory()->create(['company_id' => $user->company_id, 'purchase_order_id' => $po->id]);

    $response = test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/achats/purchase-receipts/{$receipt->id}/match", ['quantity' => 1, 'total_amount' => 100.0]);

    $response->assertStatus(201);
});

test('the real ordered-quantity bug fix: a genuinely small (within-tolerance) variance is not wrongly flagged', function () {
    $svc = new \Modules\Achats\Services\ThreeWayMatchService();
    $po = PurchaseOrder::factory()->create(['total' => 1000.0]);
    PurchaseOrderLine::factory()->create(['purchase_order_id' => $po->id, 'quantity' => 100.0, 'unit_price' => 10.0]);
    $receipt = PurchaseReceipt::factory()->create(['purchase_order_id' => $po->id]);
    \Modules\Achats\Models\PurchaseReceiptLine::factory()->create([
        'receipt_id' => $receipt->id,
        'quantity_received' => 100.0,
    ]);

    // 3 units variance on 100 real ordered units = 3%, within the 5%
    // tolerance — before this chantier's fix, the ordered quantity always
    // summed to 0 (wrong column name), so this would have been wrongly
    // scored as 300% and flagged as a mismatch.
    $result = $svc->matchInvoiceWithReceipt($receipt->fresh(), ['quantity' => 97.0, 'total_amount' => 1000.0]);

    expect($result->match_result)->toBe('matched');
});

test('submitting a PO for approval now notifies the real resolved approver (event-ordering fix)', function () {
    app(ApprovalRoutingService::class)->createDefaultWorkflows();
    Role::firstOrCreate(['name' => 'purchasing-manager', 'guard_name' => 'web']);

    $company = Company::create(['name' => 'Notif Co', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);
    $approver = User::factory()->create(['company_id' => $company->id]);
    $approver->assignRole('purchasing-manager');
    $requester = User::factory()->create(['company_id' => $company->id]);

    $supplier = Supplier::factory()->create(['company_id' => $company->id]);
    $po = PurchaseOrder::factory()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'status' => 'draft', 'total' => 2000]);

    app(PurchaseOrderService::class)->submitForApproval($po, $requester);

    $request = \Modules\Validation\Models\ApprovalRequest::where('approvable_type', PurchaseOrder::class)
        ->where('approvable_id', $po->id)->latest()->first();

    expect($request->approver_id)->not->toBeNull();
    $realApprover = User::find($request->approver_id);
    expect($realApprover->notifications()->count())->toBeGreaterThan(0);
});

// ---------------------------------------------------------------------------
// Layer 9 — fake/dead: BulkPurchaseOrderService::createPOsFromRFQ() activation
// ---------------------------------------------------------------------------

test('accepting a quote and creating purchase orders from an RFQ produces a real, correctly-scoped PO', function () {
    $user = achats1313User('M');
    $rfq = RFQ::factory()->create(['company_id' => $user->company_id, 'status' => 'draft', 'currency' => 'MGA', 'required_by_date' => now()->addDays(7)]);
    RFQLine::factory()->create(['rfq_id' => $rfq->id, 'description' => 'Widget', 'quantity' => 10, 'unit' => 'kg']);
    $supplier = Supplier::factory()->create(['company_id' => $user->company_id]);
    SupplierQuote::factory()->create([
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'company_id' => $user->company_id,
        'status' => 'accepted',
        'unit_price' => 50,
        'delivery_days' => 5,
    ]);

    $response = test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/achats/rfqs/{$rfq->id}/create-purchase-orders");

    $response->assertStatus(201);
    $response->assertJsonCount(1);

    $newPo = PurchaseOrder::where('supplier_id', $supplier->id)->latest()->first();
    expect($newPo)->not->toBeNull()
        // The real bug this chantier fixed: company_id was never set at
        // all before, and currency/unit were hardcoded to 'USD'/'pcs'
        // regardless of the RFQ's/line's own real values.
        ->and($newPo->company_id)->toBe($user->company_id)
        ->and($newPo->currency)->toBe('MGA')
        ->and($newPo->lines()->count())->toBe(1)
        ->and($newPo->lines()->first()->unit)->toBe('kg')
        ->and((float) $newPo->lines()->first()->unit_price)->toBe(50.0);
});

test('creating purchase orders from an RFQ with no accepted quote is rejected', function () {
    $user = achats1313User('N');
    $rfq = RFQ::factory()->create(['company_id' => $user->company_id, 'status' => 'draft', 'required_by_date' => now()->addDays(7)]);

    test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/achats/rfqs/{$rfq->id}/create-purchase-orders")
        ->assertStatus(422);
});

test('creating purchase orders from another company\'s RFQ is denied', function () {
    $owner = achats1313User('O');
    $other = achats1313User('O-other');
    $rfq = RFQ::factory()->create(['company_id' => $owner->company_id, 'status' => 'draft', 'required_by_date' => now()->addDays(7)]);

    test()->actingAs($other, 'sanctum')
        ->postJson("/api/v1/achats/rfqs/{$rfq->id}/create-purchase-orders")
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// Layer 13 — AI contextual guidance now reachable on the real screens
// ---------------------------------------------------------------------------

test('the Achats AI-assist endpoint returns real grounded guidance for the newly-wired actions', function () {
    $user = achats1313User('P');

    foreach (['create_order', 'approve_order', 'receive_goods'] as $action) {
        $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/ai/assist', [
            'module' => 'Achats',
            'action' => $action,
            'locale' => 'fr',
        ]);

        $response->assertStatus(200);
        expect($response->json('what_to_do'))->not->toBeEmpty();
    }
});
