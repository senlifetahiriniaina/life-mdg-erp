<?php

declare(strict_types=1);

namespace Modules\Accounting\Tests\Feature;

use App\Models\User;
use Modules\Accounting\Models\Invoice;
use Tests\TestCase;

/**
 * InvoiceApproval/Show.vue existed with a full UI but no route in
 * routes/web.php ever pointed to it — completely unreachable.
 */
class InvoiceApprovalWebTest extends TestCase
{
    public function test_invoice_approval_show_renders()
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create(['total' => 75000, 'approval_status' => 'pending']);

        $response = $this->actingAs($user)->get("/accounting/invoices/{$invoice->id}/approval");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/InvoiceApproval/Show', false)
            ->where('invoice.id', $invoice->id)
            ->has('levels', 3)
        );
    }

    /**
     * InvoiceApproval/Index.vue existed with a full UI but fetched a
     * nonexistent /api/v1/accounting/approval-queue endpoint — completely
     * unreachable, same class of bug as Show.vue above. Checking every
     * field binding here rather than just "the page renders", since a
     * mismatched key silently breaks the template without a 500.
     */
    public function test_invoice_approval_queue_renders_expected_shape()
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'number' => 'INV-2026-0042',
            'partner_name' => 'Acme Fournisseur',
            'total' => 75000,
            'currency' => 'XOF',
            'approval_status' => 'pending',
        ]);
        Invoice::factory()->create(['approval_status' => 'approved']);

        $response = $this->actingAs($user)->get('/accounting/invoices/approvals');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/InvoiceApproval/Index', false)
            ->has('invoices', 1)
            ->where('invoices.0.id', $invoice->id)
            ->where('invoices.0.invoice_number', 'INV-2026-0042')
            ->where('invoices.0.supplier_name', 'Acme Fournisseur')
            ->where('invoices.0.total_amount', 75000)
            ->where('invoices.0.currency', 'XOF')
            ->where('invoices.0.approval_status', 'pending')
            ->where('invoices.0.required_approval_level', 1)
            ->has('invoices.0.required_approval_label')
            ->has('invoices.0.days_pending')
            ->has('levels', 3)
            ->has('stats.pending_count')
            ->has('stats.urgent_count')
            ->has('stats.approval_rate_percent')
            ->where('stats.pending_count', 1)
        );
    }
}
