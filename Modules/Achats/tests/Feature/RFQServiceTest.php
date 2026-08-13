<?php

namespace Modules\Achats\Tests\Feature;

use App\Models\User;
use Modules\Achats\Models\RFQ;
use Modules\Achats\Models\RFQLine;
use Modules\Achats\Models\Supplier;
use Modules\Achats\Models\SupplierQuote;
use Modules\Achats\Services\RFQService;
use Tests\TestCase;

class RFQServiceTest extends TestCase
{
    protected RFQService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RFQService::class);
    }

    public function test_can_create_rfq()
    {
        $user = User::factory()->create();

        $data = [
            'description' => 'Request for office supplies',
            'required_by_date' => now()->addDays(30)->toDateString(),
            'created_by' => $user->id,
        ];

        $rfq = $this->service->createRFQ($data);

        $this->assertInstanceOf(RFQ::class, $rfq);
        $this->assertEquals('draft', $rfq->status);
        $this->assertStringContainsString('RFQ-', $rfq->rfq_number);
    }

    public function test_can_add_line_to_rfq()
    {
        $rfq = RFQ::factory()->create(['status' => 'draft']);

        $lineData = [
            'description' => 'Desk chairs',
            'quantity' => 20,
            'unit' => 'pcs',
            'required_date' => now()->addDays(20)->toDateString(),
        ];

        $line = $this->service->addLineToRFQ($rfq, $lineData);

        $this->assertEquals('Desk chairs', $line->description);
        $this->assertEquals(20, $line->quantity);
    }

    public function test_cannot_add_line_to_non_draft_rfq()
    {
        $rfq = RFQ::factory()->create(['status' => 'sent']);

        $this->expectException(\Exception::class);

        $this->service->addLineToRFQ($rfq, ['description' => 'Test']);
    }

    public function test_can_issue_rfq()
    {
        $rfq = RFQ::factory()
            ->has(RFQLine::factory()->count(2))
            ->create(['status' => 'draft']);

        $suppliers = Supplier::factory()->count(3)->create();
        $supplierIds = $suppliers->pluck('id')->toArray();

        $this->service->issueRFQ($rfq, $supplierIds);

        $rfq->refresh();
        $this->assertEquals('sent', $rfq->status);
        $this->assertNotNull($rfq->issued_date);
        $this->assertEquals(3, $rfq->quotes()->count());
    }

    public function test_can_record_supplier_quote()
    {
        $rfq = RFQ::factory()->create(['status' => 'sent']);
        $supplier = Supplier::factory()->create();

        $quoteData = [
            'unit_price' => 50,
            'total_price' => 1000,
            'delivery_days' => 7,
            'terms' => 'Net 30',
            'validity_date' => now()->addDays(10)->toDateString(),
        ];

        $quote = $this->service->recordSupplierQuote($rfq, $supplier->id, $quoteData);

        $this->assertEquals('submitted', $quote->status);
        $this->assertEquals(1000, $quote->total_price);
    }

    public function test_can_evaluate_quotes()
    {
        $rfq = RFQ::factory()->create();

        $rfq->quotes()->createMany([
            [
                'supplier_id' => Supplier::factory()->create()->id,
                'unit_price' => 100,
                'total_price' => 2000,
                'delivery_days' => 10,
                'status' => 'submitted',
                'validity_date' => now()->addDays(10),
                'created_by' => User::factory()->create()->id,
            ],
            [
                'supplier_id' => Supplier::factory()->create()->id,
                'unit_price' => 80,
                'total_price' => 1600,
                'delivery_days' => 7,
                'status' => 'submitted',
                'validity_date' => now()->addDays(10),
                'created_by' => User::factory()->create()->id,
            ],
        ]);

        $evaluation = $this->service->evaluateQuotes($rfq);

        $this->assertCount(2, $evaluation);
        $this->assertEquals(1, $evaluation[0]['rank']);
        $this->assertEquals(1600, $evaluation[0]['total_price']);
    }

    public function test_can_select_winning_quote()
    {
        $rfq = RFQ::factory()->create();

        $supplier1 = Supplier::factory()->create();
        $supplier2 = Supplier::factory()->create();

        $quote1 = $rfq->quotes()->create([
            'supplier_id' => $supplier1->id,
            'unit_price' => 100,
            'total_price' => 2000,
            'delivery_days' => 10,
            'status' => 'submitted',
            'validity_date' => now()->addDays(10),
            'created_by' => User::factory()->create()->id,
        ]);

        $quote2 = $rfq->quotes()->create([
            'supplier_id' => $supplier2->id,
            'unit_price' => 80,
            'total_price' => 1600,
            'delivery_days' => 7,
            'status' => 'submitted',
            'validity_date' => now()->addDays(10),
            'created_by' => User::factory()->create()->id,
        ]);

        $this->service->selectWinningQuote($quote2);

        $quote1->refresh();
        $quote2->refresh();

        $this->assertEquals('rejected', $quote1->status);
        $this->assertEquals('accepted', $quote2->status);
    }

    public function test_can_reject_quote()
    {
        $quote = SupplierQuote::factory()->create();

        $this->service->rejectQuote($quote, 'Price too high');

        $quote->refresh();
        $this->assertEquals('rejected', $quote->status);
    }

    public function test_can_get_quote_comparison()
    {
        $rfq = RFQ::factory()->create();

        $rfq->quotes()->createMany([
            [
                'supplier_id' => Supplier::factory()->create()->id,
                'unit_price' => 100,
                'total_price' => 2000,
                'delivery_days' => 10,
                'status' => 'submitted',
                'validity_date' => now()->addDays(10),
                'created_by' => User::factory()->create()->id,
            ],
            [
                'supplier_id' => Supplier::factory()->create()->id,
                'unit_price' => 80,
                'total_price' => 1600,
                'delivery_days' => 7,
                'status' => 'submitted',
                'validity_date' => now()->addDays(10),
                'created_by' => User::factory()->create()->id,
            ],
        ]);

        $comparison = $this->service->getQuoteComparison($rfq);

        $this->assertArrayHasKey('rfq_number', $comparison);
        $this->assertArrayHasKey('lowest_price', $comparison);
        $this->assertArrayHasKey('highest_price', $comparison);
        $this->assertArrayHasKey('quotes', $comparison);
    }
}
