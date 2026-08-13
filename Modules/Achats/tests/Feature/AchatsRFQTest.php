<?php

namespace Modules\Achats\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Achats\Models\{RFQ, RFQResponse, Supplier};
use Modules\Achats\Services\AchatsService;

class AchatsRFQTest extends TestCase
{
    use RefreshDatabase;

    private AchatsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AchatsService();
    }

    // RFQ Creation (3 tests)
    public function test_create_rfq(): void
    {
        $rfq = $this->service->createRFQ('Office Supplies', ['quantity' => 100]);
        $this->assertNotNull($rfq->id);
        $this->assertEquals('draft', $rfq->status);
    }

    public function test_send_rfq_to_suppliers(): void
    {
        $suppliers = Supplier::factory()->count(3)->create();
        $rfq = $this->service->createRFQ('Office Supplies');

        $sent = $this->service->sendRFQToSuppliers($rfq, $suppliers);
        $this->assertTrue($sent);
    }

    public function test_rfq_response_deadline_enforcement(): void
    {
        $rfq = $this->service->createRFQ('Supplies', ['deadline' => now()->addDays(7)]);
        $isActive = $this->service->isRFQActive($rfq);

        $this->assertTrue($isActive);
    }

    // Response Management (3 tests)
    public function test_supplier_submit_rfq_response(): void
    {
        $supplier = Supplier::factory()->create();
        $rfq = $this->service->createRFQ('Supplies');

        $response = $this->service->createRFQResponse($rfq, $supplier, [
            'unit_price' => 10,
            'delivery_days' => 14
        ]);

        $this->assertNotNull($response->id);
    }

    public function test_compare_rfq_responses(): void
    {
        $rfq = $this->service->createRFQ('Supplies', ['quantity' => 100]);

        $responses = RFQResponse::factory()->count(3)->create(['rfq_id' => $rfq->id]);

        $comparison = $this->service->compareResponses($rfq);

        $this->assertCount(3, $comparison);
    }

    public function test_select_best_supplier_by_price(): void
    {
        $rfq = $this->service->createRFQ('Supplies', ['quantity' => 100]);

        RFQResponse::factory()->create(['rfq_id' => $rfq->id, 'unit_price' => 15]);
        RFQResponse::factory()->create(['rfq_id' => $rfq->id, 'unit_price' => 10]);
        RFQResponse::factory()->create(['rfq_id' => $rfq->id, 'unit_price' => 12]);

        $best = $this->service->selectBestSupplier($rfq, 'price');

        $this->assertEquals(10, $best->unit_price);
    }

    // Collaboration (3 tests)
    public function test_share_rfq_with_internal_approvers(): void
    {
        $rfq = $this->service->createRFQ('Supplies');
        $approver = $this->actingAsUser();

        $shared = $this->service->shareRFQ($rfq, [$approver]);
        $this->assertTrue($shared);
    }

    public function test_supplier_negotiate_counter_offer(): void
    {
        $rfq = $this->service->createRFQ('Supplies');
        $response = RFQResponse::factory()->create(['rfq_id' => $rfq->id]);

        $counter = $this->service->createCounterOffer($response, [
            'unit_price' => 9,
            'payment_terms' => 'NET_45'
        ]);

        $this->assertNotNull($counter->id);
    }

    public function test_acceptance_of_rfq_response(): void
    {
        $rfq = $this->service->createRFQ('Supplies');
        $response = RFQResponse::factory()->create(['rfq_id' => $rfq->id]);

        $accepted = $this->service->acceptRFQResponse($response);

        $this->assertEquals('accepted', $accepted->status);
    }

    // API Tests (3 tests)
    public function test_api_create_rfq(): void
    {
        $response = $this->postJson('/api/v1/achats/rfqs', [
            'title' => 'Office Supplies',
            'quantity' => 100
        ]);

        $response->assertStatus(201);
    }

    public function test_api_list_rfq_responses(): void
    {
        $rfq = RFQ::factory()->create();
        RFQResponse::factory()->count(5)->create(['rfq_id' => $rfq->id]);

        $response = $this->getJson("/api/v1/achats/rfqs/{$rfq->id}/responses");

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
    }

    public function test_api_submit_rfq_response(): void
    {
        $rfq = RFQ::factory()->create();
        $supplier = Supplier::factory()->create();

        $response = $this->postJson("/api/v1/achats/rfqs/{$rfq->id}/responses", [
            'supplier_id' => $supplier->id,
            'unit_price' => 10
        ]);

        $response->assertStatus(201);
    }
}
