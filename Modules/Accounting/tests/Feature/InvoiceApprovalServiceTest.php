<?php

namespace Modules\Accounting\Tests\Feature;

use Tests\TestCase;
use Modules\Accounting\Services\InvoiceApprovalService;
use Modules\Accounting\Models\Invoice;
use App\Models\User;

class InvoiceApprovalServiceTest extends TestCase
{
    private InvoiceApprovalService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InvoiceApprovalService::class);
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_determines_approval_level_for_small_invoice()
    {
        $level = $this->service->getApprovalLevel(50_000);
        $this->assertEquals(1, $level);
    }

    /** @test */
    public function it_determines_approval_level_for_medium_invoice()
    {
        $level = $this->service->getApprovalLevel(250_000);
        $this->assertEquals(2, $level);
    }

    /** @test */
    public function it_determines_approval_level_for_large_invoice()
    {
        $level = $this->service->getApprovalLevel(5_000_000);
        $this->assertEquals(3, $level);
    }

    /** @test */
    public function it_submits_invoice_for_approval()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'amount' => 200_000,
        ]);

        $approval = $this->service->submitForApproval($invoice, $this->user);

        $this->assertNotNull($approval);
        $this->assertEquals(2, $approval->required_level);
        $this->assertEquals('pending_level_1', $approval->status);
    }

    /** @test */
    public function it_approves_invoice_at_level_1()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'amount' => 50_000,
        ]);

        $approval = $this->service->submitForApproval($invoice, $this->user);
        $approver = User::factory()->create(['tenant_id' => $this->user->tenant_id]);

        $result = $this->service->approveLevel($approval, $approver);

        $this->assertTrue($result);
        $this->assertEquals('approved', $approval->fresh()->status);
    }

    /** @test */
    public function it_escalates_to_level_2_when_needed()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'amount' => 200_000,
        ]);

        $approval = $this->service->submitForApproval($invoice, $this->user);
        $manager = User::factory()->create(['tenant_id' => $this->user->tenant_id]);

        $result = $this->service->approveLevel($approval, $manager);

        $this->assertFalse($result);
        $this->assertEquals('pending_level_2', $approval->fresh()->status);
        $this->assertEquals(2, $approval->fresh()->current_level);
    }

    /** @test */
    public function it_rejects_invoice()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'amount' => 100_000,
        ]);

        $approval = $this->service->submitForApproval($invoice, $this->user);
        $rejector = User::factory()->create(['tenant_id' => $this->user->tenant_id]);

        $result = $this->service->reject($approval, $rejector, 'Missing documentation');

        $this->assertTrue($result);
        $this->assertEquals('rejected', $approval->fresh()->status);
    }

    /** @test */
    public function it_gets_approval_chain()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'amount' => 200_000,
        ]);

        $approval = $this->service->submitForApproval($invoice, $this->user);
        $chain = $this->service->getApprovalChain($invoice);

        $this->assertNotEmpty($chain);
        $this->assertEquals(2, count($chain));
    }

    /** @test */
    public function it_gets_analytics()
    {
        Invoice::factory(10)->create(['tenant_id' => $this->user->tenant_id]);

        $analytics = $this->service->getAnalytics(
            $this->user->tenant_id,
            now()->subDays(7),
            now()
        );

        $this->assertArrayHasKey('total_submitted', $analytics);
        $this->assertArrayHasKey('compliance_pct', $analytics);
    }
}
