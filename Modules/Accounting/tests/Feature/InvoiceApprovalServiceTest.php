<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\User;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\InvoiceApproval;
use Modules\Accounting\Services\InvoiceApprovalService;
use Modules\Validation\Models\ApprovalRequest;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Rewritten: the previous version of this test (and of
 * InvoiceApprovalService) used tenant_id/amount/required_level/
 * approved_by_level_N — none of which exist on the real Invoice model
 * (real amount column is `total`, no tenant_id column) or on
 * InvoiceApproval's real schema. It never ran successfully. This version
 * exercises the real, rewritten service, which now routes invoice
 * approval through Modules\Validation instead of its own disconnected
 * state.
 */
class InvoiceApprovalServiceTest extends TestCase
{
    private InvoiceApprovalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InvoiceApprovalService::class);

        foreach (['manager', 'finance-manager', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_it_determines_approval_level_for_small_invoice()
    {
        $this->assertEquals(1, $this->service->getApprovalLevel(50_000));
    }

    public function test_it_determines_approval_level_for_medium_invoice()
    {
        $this->assertEquals(2, $this->service->getApprovalLevel(250_000));
    }

    public function test_it_determines_approval_level_for_large_invoice()
    {
        $this->assertEquals(3, $this->service->getApprovalLevel(5_000_000));
    }

    public function test_it_submits_invoice_for_approval_and_routes_to_the_right_role()
    {
        $financeManager = User::factory()->create();
        $financeManager->assignRole('finance-manager');

        $invoice = Invoice::factory()->create(['total' => 200_000]);
        $submitter = User::factory()->create();

        $request = $this->service->submitForApproval($invoice, $submitter);

        $this->assertEquals('pending', $request->status);
        $this->assertEquals($financeManager->id, $request->approver_id);
        $this->assertEquals('pending', $invoice->fresh()->approval_status);

        $projection = InvoiceApproval::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($projection, 'a read-projection row should exist for the existing UI');
        $this->assertEquals($request->id, $projection->approval_request_id);
    }

    public function test_it_approves_invoice_and_updates_projection()
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $invoice = Invoice::factory()->create(['total' => 50_000]);
        $submitter = User::factory()->create();

        $this->service->submitForApproval($invoice, $submitter);
        $request = $this->service->approve($invoice, $manager, 'Looks good');

        $this->assertEquals('approved', $request->status);
        $this->assertEquals('approved', $invoice->fresh()->approval_status);
        $this->assertEquals('approved', InvoiceApproval::where('invoice_id', $invoice->id)->first()->status);
    }

    public function test_it_rejects_invoice_and_updates_projection()
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $invoice = Invoice::factory()->create(['total' => 50_000]);
        $submitter = User::factory()->create();

        $this->service->submitForApproval($invoice, $submitter);
        $request = $this->service->reject($invoice, $manager, 'Missing documentation');

        $this->assertEquals('rejected', $request->status);
        $this->assertEquals('rejected', $invoice->fresh()->approval_status);

        $projection = InvoiceApproval::where('invoice_id', $invoice->id)->first();
        $this->assertEquals('rejected', $projection->status);
        $this->assertEquals('Missing documentation', $projection->rejection_reason);
    }

    public function test_it_creates_only_one_shared_workflow_across_submissions()
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $invoiceA = Invoice::factory()->create(['total' => 10_000]);
        $invoiceB = Invoice::factory()->create(['total' => 20_000]);
        $submitter = User::factory()->create();

        $this->service->submitForApproval($invoiceA, $submitter);
        $this->service->submitForApproval($invoiceB, $submitter);

        $this->assertEquals(2, ApprovalRequest::where('approvable_type', Invoice::class)->count());
        $this->assertEquals(
            1,
            \Modules\Validation\Models\ApprovalWorkflow::where('module_name', 'Accounting')->count()
        );
    }
}
