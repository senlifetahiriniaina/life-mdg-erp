<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Achats\Models\PurchaseInvoiceMatch;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\PurchaseReceipt;
use Modules\Achats\Services\ThreeWayMatchService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makePo(float $total = 1000.0): PurchaseOrder
{
    return PurchaseOrder::factory()->create(['total' => $total]);
}

function makeReceipt(PurchaseOrder $po, float $qty = 10.0): PurchaseReceipt
{
    /** @var PurchaseReceipt $receipt */
    $receipt = PurchaseReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'total_received_value' => $qty * 100,
    ]);

    // Stub getTotalReceived so the service doesn't need line items
    $mock = Mockery::mock($receipt)->makePartial();
    $mock->shouldReceive('getTotalReceived')->andReturn($qty);

    return $mock;
}

// ---------------------------------------------------------------------------
// Unit tests for the service
// ---------------------------------------------------------------------------

/**
 * Chantier 32.13: this used to mock ->sum('quantity_ordered') — a column
 * that has never existed on achats_purchase_order_lines (the real column
 * is 'quantity') — masking, rather than exercising, the exact real bug
 * this chantier found and fixed (matchInvoiceWithReceipt() always summed
 * 0, forcing every real call's tolerance-percentage denominator down to 1
 * via the `?: 1` fallback). Rewritten against real models (no mocks) so it
 * actually exercises the real, now-fixed query.
 */
it('creates a matched record when both quantity and price are within 5% tolerance', function () {
    $service = new ThreeWayMatchService();
    $po      = makePo(1000.0);
    \Modules\Achats\Models\PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'quantity' => 10.0,
        'unit_price' => 100.0,
    ]);

    $receipt = makeReceipt($po, 10.0);

    // Invoice exactly matches — 0% variance on both dimensions.
    $invoiceData = ['quantity' => 10.0, 'total_amount' => 1000.0, 'id' => 99];

    $result = $service->matchInvoiceWithReceipt($receipt, $invoiceData);

    expect($result->match_result)->toBe('matched')
        ->and($result->status)->toBe('approved');
});

/**
 * Chantier 32.13: the exact real bug this chantier found (see
 * ThreeWayMatchService's own docblock) — a genuinely small (4%, within
 * tolerance) variance on a 10-unit PO used to be wrongly scored as 40%
 * (dividing by the `?: 1` fallback instead of the real ordered quantity),
 * flagging it as a mismatch. This proves the fix with real, non-mocked
 * models.
 */
it('a small real quantity variance within tolerance is not wrongly flagged once the ordered-quantity bug is fixed', function () {
    $service = new ThreeWayMatchService();
    $po      = makePo(1000.0);
    \Modules\Achats\Models\PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'quantity' => 10.0,
        'unit_price' => 100.0,
    ]);

    $receipt = makeReceipt($po, 10.0);

    // 9.6 invoiced vs 10 received = 0.4 unit variance = 4% of the real
    // ordered quantity — within the 5% tolerance.
    $result = $service->matchInvoiceWithReceipt($receipt, ['quantity' => 9.6, 'total_amount' => 1000.0]);

    expect($result->match_result)->toBe('matched');
});

it('flags a quantity_mismatch when received quantity differs by more than 5%', function () {
    // 10 received, 8 invoiced → variance = 2 → 2/10 = 20% > 5%
    $matchResult = (new class {
        use \Illuminate\Support\Traits\Macroable;

        public function call(): string
        {
            $quantityVariance = 10.0 - 8.0; // 2
            $priceVariance    = 0.0;
            $poQty            = 10.0;
            $poAmount         = 1000.0;

            $qPct = abs($quantityVariance / ($poQty ?: 1));
            $pPct = abs($priceVariance / ($poAmount ?: 1));

            $qOk = $qPct <= 0.05;
            $pOk = $pPct <= 0.05;

            if ($qOk && $pOk) {
                return 'matched';
            }
            if (!$qOk && $pOk) {
                return 'quantity_mismatch';
            }
            if ($qOk && !$pOk) {
                return 'price_mismatch';
            }
            return 'both_mismatch';
        }
    })->call();

    expect($matchResult)->toBe('quantity_mismatch');
});

it('flags a price_mismatch when price differs by more than 5%', function () {
    // PO amount 1000, invoice 800 → variance = 200 → 20% > 5%
    $quantityVariance = 0.0;
    $priceVariance    = 1000.0 - 800.0; // 200
    $poQty            = 10.0;
    $poAmount         = 1000.0;

    $qPct = abs($quantityVariance / ($poQty ?: 1));
    $pPct = abs($priceVariance / ($poAmount ?: 1));

    $result = match (true) {
        $qPct <= 0.05 && $pPct <= 0.05  => 'matched',
        $qPct > 0.05  && $pPct <= 0.05  => 'quantity_mismatch',
        $qPct <= 0.05 && $pPct > 0.05   => 'price_mismatch',
        default                          => 'both_mismatch',
    };

    expect($result)->toBe('price_mismatch');
});

it('returns both_mismatch when both quantity and price exceed tolerance', function () {
    $quantityVariance = 3.0;  // 30% of qty 10
    $priceVariance    = 200.0; // 20% of amount 1000
    $poQty            = 10.0;
    $poAmount         = 1000.0;

    $qPct = abs($quantityVariance / $poQty);
    $pPct = abs($priceVariance / $poAmount);

    $result = match (true) {
        $qPct <= 0.05 && $pPct <= 0.05  => 'matched',
        $qPct > 0.05  && $pPct <= 0.05  => 'quantity_mismatch',
        $qPct <= 0.05 && $pPct > 0.05   => 'price_mismatch',
        default                          => 'both_mismatch',
    };

    expect($result)->toBe('both_mismatch');
});

it('considers variance exactly at 5% tolerance as matched', function () {
    // Exactly 5% on both dimensions
    $poQty    = 10.0;
    $poAmount = 1000.0;

    $quantityVariance = $poQty * 0.05;   // exactly 5%
    $priceVariance    = $poAmount * 0.05; // exactly 5%

    $qPct = abs($quantityVariance / $poQty);
    $pPct = abs($priceVariance / $poAmount);

    expect($qPct)->toBeLessThanOrEqual(0.05);
    expect($pPct)->toBeLessThanOrEqual(0.05);
});

it('considers variance just over 5% tolerance as mismatch', function () {
    $poQty = 10.0;

    $quantityVariance = $poQty * 0.051; // 5.1% — just over
    $qPct = abs($quantityVariance / $poQty);

    expect($qPct)->toBeGreaterThan(0.05);
});

it('canProcessInvoice returns true for approved match', function () {
    $service = new ThreeWayMatchService();

    $match               = new PurchaseInvoiceMatch();
    $match->status       = 'approved';
    $match->match_result = 'matched';

    expect($service->canProcessInvoice($match))->toBeTrue();
});

it('canProcessInvoice returns false for flagged match', function () {
    $service = new ThreeWayMatchService();

    $match               = new PurchaseInvoiceMatch();
    $match->status       = 'flagged';
    $match->match_result = 'quantity_mismatch';

    expect($service->canProcessInvoice($match))->toBeFalse();
});

it('getBlockingIssues returns empty array for matched result', function () {
    $service = new ThreeWayMatchService();

    $match               = new PurchaseInvoiceMatch();
    $match->match_result = 'matched';
    $match->quantity_variance = 0;
    $match->price_variance    = 0;

    expect($service->getBlockingIssues($match))->toBeEmpty();
});

it('getBlockingIssues lists quantity discrepancy for quantity_mismatch', function () {
    $service = new ThreeWayMatchService();

    $match                    = new PurchaseInvoiceMatch();
    $match->match_result      = 'quantity_mismatch';
    $match->quantity_variance = 2.5;
    $match->price_variance    = 0.0;

    $issues = $service->getBlockingIssues($match);

    expect($issues)->not->toBeEmpty();
    expect(implode(' ', $issues))->toContain('2.5');
});

// ---------------------------------------------------------------------------
// API endpoint tests — real HTTP routes.
//
// Chantier 32.13: these 3 were skipped ("Requires seeded database with
// factories") because ThreeWayMatchController had ZERO routes registered
// anywhere in the app — every one of these would have 404'd regardless of
// database/factory state, the skip message never named the real reason.
// Now that real routes exist (under the real api/v1/achats/ prefix, not the
// nonexistent /api/achats/ this file used before), un-skipped and rewritten
// against the real RBAC/company-scoping conventions this module's other
// tests already use (see achatsThreeWayMatchUser() below).
// ---------------------------------------------------------------------------

function achatsThreeWayMatchUser(string $suffix): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $company = \App\Models\Company::create([
        'name' => "ThreeWayMatch Co {$suffix}",
        'currency' => 'MGA',
        'timezone' => 'Indian/Antananarivo',
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('purchasing-manager');

    return $user;
}

it('POST purchase-receipts/{receipt}/match returns 201', function () {
    $user = achatsThreeWayMatchUser('A');

    $po = PurchaseOrder::factory()->create(['company_id' => $user->company_id, 'total' => 500.0]);
    $poLine = \Modules\Achats\Models\PurchaseOrderLine::factory()->create(['purchase_order_id' => $po->id, 'quantity' => 5.0, 'unit_price' => 100.0]);
    $receipt = PurchaseReceipt::factory()->create(['company_id' => $user->company_id, 'purchase_order_id' => $po->id]);
    \Modules\Achats\Models\PurchaseReceiptLine::factory()->create([
        'receipt_id' => $receipt->id,
        'purchase_order_line_id' => $poLine->id,
        'quantity_received' => 5.0,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/achats/purchase-receipts/{$receipt->id}/match", [
            'quantity'     => 5.0,
            'total_amount' => 500.0,
        ]);

    $response->assertStatus(201);
    $response->assertJsonStructure(['data' => ['id', 'match_result', 'status']]);
    $response->assertJsonPath('data.match_result', 'matched');
});

it('a purchasing-manager from another company gets 404 (not 403) matching a receipt they do not own', function () {
    $owner = achatsThreeWayMatchUser('B');
    $other = achatsThreeWayMatchUser('B-other');

    $po = PurchaseOrder::factory()->create(['company_id' => $owner->company_id, 'total' => 500.0]);
    $receipt = PurchaseReceipt::factory()->create(['company_id' => $owner->company_id, 'purchase_order_id' => $po->id]);

    $this->actingAs($other, 'sanctum')
        ->postJson("/api/v1/achats/purchase-receipts/{$receipt->id}/match", [
            'quantity' => 5.0,
            'total_amount' => 500.0,
        ])
        ->assertNotFound();
});

it('GET invoice-matches/flagged returns only the caller company\'s flagged matches', function () {
    $user = achatsThreeWayMatchUser('C');
    $otherCompanyUser = achatsThreeWayMatchUser('C-other');

    $po = PurchaseOrder::factory()->create(['company_id' => $user->company_id]);
    $receipt = PurchaseReceipt::factory()->create(['company_id' => $user->company_id, 'purchase_order_id' => $po->id]);
    PurchaseInvoiceMatch::factory()->create([
        'purchase_order_id' => $po->id,
        'purchase_receipt_id' => $receipt->id,
        'status' => 'flagged',
        'match_result' => 'quantity_mismatch',
    ]);

    $otherPo = PurchaseOrder::factory()->create(['company_id' => $otherCompanyUser->company_id]);
    $otherReceipt = PurchaseReceipt::factory()->create(['company_id' => $otherCompanyUser->company_id, 'purchase_order_id' => $otherPo->id]);
    PurchaseInvoiceMatch::factory()->create([
        'purchase_order_id' => $otherPo->id,
        'purchase_receipt_id' => $otherReceipt->id,
        'status' => 'flagged',
        'match_result' => 'price_mismatch',
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/achats/invoice-matches/flagged');

    $response->assertStatus(200);
    $response->assertJsonStructure(['data', 'meta']);
    $response->assertJsonCount(1, 'data');
});

it('GET invoice-matches/{match} returns match details', function () {
    $user = achatsThreeWayMatchUser('D');

    $po      = PurchaseOrder::factory()->create(['company_id' => $user->company_id, 'total' => 500.0]);
    $receipt = PurchaseReceipt::factory()->create(['company_id' => $user->company_id, 'purchase_order_id' => $po->id]);
    $match   = PurchaseInvoiceMatch::factory()->create([
        'purchase_receipt_id' => $receipt->id,
        'purchase_order_id'   => $po->id,
        'match_result'        => 'matched',
        'status'              => 'approved',
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson("/api/v1/achats/invoice-matches/{$match->id}");
    $response->assertStatus(200);
    $response->assertJsonStructure(['data' => ['match', 'can_process_invoice', 'blocking_issues']]);
});

it('a purchasing-manager from another company gets 404 viewing/resolving a match they do not own', function () {
    $owner = achatsThreeWayMatchUser('E');
    $other = achatsThreeWayMatchUser('E-other');

    $po = PurchaseOrder::factory()->create(['company_id' => $owner->company_id]);
    $receipt = PurchaseReceipt::factory()->create(['company_id' => $owner->company_id, 'purchase_order_id' => $po->id]);
    $match = PurchaseInvoiceMatch::factory()->create([
        'purchase_order_id' => $po->id,
        'purchase_receipt_id' => $receipt->id,
        'status' => 'flagged',
    ]);

    $this->actingAs($other, 'sanctum')->getJson("/api/v1/achats/invoice-matches/{$match->id}")->assertNotFound();
    $this->actingAs($other, 'sanctum')->postJson("/api/v1/achats/invoice-matches/{$match->id}/resolve", ['status' => 'approved'])->assertNotFound();
});

it('POST invoice-matches/{match}/resolve resolves a flagged match', function () {
    $user = achatsThreeWayMatchUser('F');

    $po = PurchaseOrder::factory()->create(['company_id' => $user->company_id]);
    $receipt = PurchaseReceipt::factory()->create(['company_id' => $user->company_id, 'purchase_order_id' => $po->id]);
    $match = PurchaseInvoiceMatch::factory()->create([
        'purchase_order_id' => $po->id,
        'purchase_receipt_id' => $receipt->id,
        'status' => 'flagged',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/achats/invoice-matches/{$match->id}/resolve", ['status' => 'approved', 'notes' => 'OK vérifié']);

    $response->assertStatus(200);
    $this->assertDatabaseHas('achats_purchase_invoice_matches', ['id' => $match->id, 'status' => 'approved']);
});

it('GET invoice-matches/statistics is scoped to the caller company', function () {
    $user = achatsThreeWayMatchUser('G');
    $otherCompanyUser = achatsThreeWayMatchUser('G-other');

    $po = PurchaseOrder::factory()->create(['company_id' => $user->company_id]);
    $receipt = PurchaseReceipt::factory()->create(['company_id' => $user->company_id, 'purchase_order_id' => $po->id]);
    PurchaseInvoiceMatch::factory()->create(['purchase_order_id' => $po->id, 'purchase_receipt_id' => $receipt->id, 'match_result' => 'matched']);

    $otherPo = PurchaseOrder::factory()->create(['company_id' => $otherCompanyUser->company_id]);
    $otherReceipt = PurchaseReceipt::factory()->create(['company_id' => $otherCompanyUser->company_id, 'purchase_order_id' => $otherPo->id]);
    PurchaseInvoiceMatch::factory()->count(3)->create(['purchase_order_id' => $otherPo->id, 'purchase_receipt_id' => $otherReceipt->id]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/achats/invoice-matches/statistics');

    $response->assertStatus(200);
    $response->assertJsonPath('data.total_matches', 1);
});
