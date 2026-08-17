<?php

namespace Modules\Achats\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Achats\Models\RFQ;
use Modules\Achats\Models\Supplier;
use Modules\Achats\Models\SupplierQuote;
use Modules\Achats\Services\RFQService;
use Tests\TestCase;

/**
 * Originally targeted a phantom `Modules\Achats\Services\AchatsService` +
 * `Modules\Achats\Models\RFQResponse` — neither class exists anywhere in the
 * codebase (confirmed by repo-wide grep). RFQ/Purchasing is a real, fully
 * implemented and routed feature via `RFQService` on the `RFQ` + `RFQLine` +
 * `SupplierQuote` models (see the green `RFQServiceTest`); this file has been
 * rewritten to exercise that real API instead.
 *
 * Two scenarios from the original had no real equivalent and were dropped
 * rather than faked — see the notes above `test_api_accept_supplier_quote()`
 * and the removed "share RFQ with internal approvers" / "supplier counter
 * offer" cases below.
 */
class AchatsRFQTest extends TestCase
{
    use RefreshDatabase;

    private RFQService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RFQService::class);
    }

    // ── RFQ Creation (3 tests) ──────────────────────────────────────────

    public function test_create_rfq(): void
    {
        $rfq = $this->service->createRFQ([
            'description' => 'Office Supplies',
            'required_by_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->assertNotNull($rfq->id);
        $this->assertEquals('draft', $rfq->status);
    }

    public function test_send_rfq_to_suppliers(): void
    {
        $suppliers = Supplier::factory()->count(3)->create();
        $rfq = RFQ::factory()->create(['status' => 'draft']);

        $this->service->issueRFQ($rfq, $suppliers->pluck('id')->toArray());

        $rfq->refresh();
        $this->assertEquals('sent', $rfq->status);
        $this->assertNotNull($rfq->issued_date);
        $this->assertEquals(3, $rfq->quotes()->count());
    }

    public function test_rfq_deadline_enforcement(): void
    {
        // Real equivalent of "isRFQActive": RFQService::getExpiredRFQs()
        // (backed by RFQ::scopeExpired()) is what the app actually uses to
        // decide whether an RFQ's deadline has passed.
        $activeRfq = RFQ::factory()->create([
            'status' => 'sent',
            'deadline_date' => now()->addDays(7),
        ]);
        $expiredRfq = RFQ::factory()->create([
            'status' => 'sent',
            'deadline_date' => now()->subDay(),
        ]);

        $expired = $this->service->getExpiredRFQs();

        $this->assertTrue($expired->contains('id', $expiredRfq->id));
        $this->assertFalse($expired->contains('id', $activeRfq->id));
    }

    // ── Response Management (3 tests) ───────────────────────────────────

    public function test_supplier_submit_rfq_response(): void
    {
        $supplier = Supplier::factory()->create();
        $rfq = RFQ::factory()->create(['status' => 'sent']);

        $quote = $this->service->recordSupplierQuote($rfq, $supplier->id, [
            'unit_price' => 10,
            'total_price' => 1000,
            'delivery_days' => 14,
            'validity_date' => now()->addDays(10)->toDateString(),
        ]);

        $this->assertNotNull($quote->id);
        $this->assertEquals('submitted', $quote->status);
    }

    public function test_compare_rfq_responses(): void
    {
        $rfq = RFQ::factory()->create();
        SupplierQuote::factory()->count(3)->create(['rfq_id' => $rfq->id, 'status' => 'submitted']);

        $comparison = $this->service->evaluateQuotes($rfq);

        $this->assertCount(3, $comparison);
    }

    public function test_select_best_supplier_by_price(): void
    {
        $rfq = RFQ::factory()->create();

        SupplierQuote::factory()->create(['rfq_id' => $rfq->id, 'status' => 'submitted', 'unit_price' => 15, 'total_price' => 1500]);
        SupplierQuote::factory()->create(['rfq_id' => $rfq->id, 'status' => 'submitted', 'unit_price' => 10, 'total_price' => 1000]);
        SupplierQuote::factory()->create(['rfq_id' => $rfq->id, 'status' => 'submitted', 'unit_price' => 12, 'total_price' => 1200]);

        // Real equivalent of "selectBestSupplier($rfq, 'price')": RFQ::getLowestQuote().
        $best = $rfq->getLowestQuote();

        $this->assertEquals(1000, (float) $best->total_price);
    }

    // ── Quote lifecycle (3 tests) ───────────────────────────────────────
    // The original "Collaboration" block (share RFQ with internal approvers,
    // supplier counter-offer negotiation) has no real implementation
    // anywhere in the codebase — grep across Modules/Achats for "share" and
    // "counter" turns up nothing. Rather than invent that business logic
    // (out of scope for this fix), those two cases are dropped and replaced
    // with real quote-lifecycle behavior that RFQService does implement:
    // re-submitting a quote (negotiation-by-update) and accept/reject.

    public function test_resubmitting_a_quote_updates_the_existing_record(): void
    {
        $supplier = Supplier::factory()->create();
        $rfq = RFQ::factory()->create(['status' => 'sent']);

        $initial = $this->service->recordSupplierQuote($rfq, $supplier->id, [
            'unit_price' => 12,
            'total_price' => 1200,
            'delivery_days' => 14,
            'validity_date' => now()->addDays(10)->toDateString(),
        ]);

        $revised = $this->service->recordSupplierQuote($rfq, $supplier->id, [
            'unit_price' => 9,
            'total_price' => 900,
            'delivery_days' => 10,
            'validity_date' => now()->addDays(10)->toDateString(),
        ]);

        $this->assertEquals($initial->id, $revised->id);
        $this->assertEquals(900, (float) $revised->total_price);
    }

    public function test_rejecting_a_quote(): void
    {
        $quote = SupplierQuote::factory()->create();

        $this->service->rejectQuote($quote, 'Price too high');

        $quote->refresh();
        $this->assertEquals('rejected', $quote->status);
    }

    public function test_acceptance_of_rfq_response(): void
    {
        $rfq = RFQ::factory()->create();
        $quote = SupplierQuote::factory()->create(['rfq_id' => $rfq->id, 'status' => 'submitted']);

        $this->service->selectWinningQuote($quote);

        $quote->refresh();
        $this->assertEquals('accepted', $quote->status);
    }

    // ── API Tests (3 tests) ─────────────────────────────────────────────

    public function test_api_create_rfq(): void
    {
        $this->actingAsUser('admin');

        $response = $this->postJson('/api/v1/achats/rfqs', [
            'description' => 'Office Supplies',
            'required_by_date' => now()->addDays(30)->toDateString(),
        ]);

        $response->assertStatus(201);
    }

    public function test_api_rfq_comparison_lists_quotes(): void
    {
        // Real equivalent of "list rfq responses": GET /rfqs/{rfq}/comparison
        // (RFQController::comparison -> RFQService::getQuoteComparison) is
        // the routed, implemented endpoint that returns a quote listing for
        // an RFQ. There is no dedicated /rfqs/{rfq}/responses endpoint —
        // SupplierQuoteController::index()/store() exist as unwired stubs
        // ("Implementation to follow"), so the original api_list_rfq_responses
        // / api_submit_rfq_response scenarios have no real target and were
        // dropped rather than asserted against dead code.
        $this->actingAsUser('admin');

        $rfq = RFQ::factory()->create();
        SupplierQuote::factory()->count(5)->create(['rfq_id' => $rfq->id, 'status' => 'submitted']);

        $response = $this->getJson("/api/v1/achats/rfqs/{$rfq->id}/comparison");

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'quotes');
    }

    public function test_api_accept_supplier_quote(): void
    {
        // Real equivalent of "submit rfq response" as a mutating API call:
        // accepting a quote via SupplierQuoteController::accept, which is
        // fully wired to RFQService::selectWinningQuote.
        $this->actingAsUser('admin');

        $rfq = RFQ::factory()->create();
        $quote = SupplierQuote::factory()->create(['rfq_id' => $rfq->id, 'status' => 'submitted']);

        $response = $this->postJson("/api/v1/achats/supplier-quotes/{$quote->id}/accept");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'accepted');
    }
}
