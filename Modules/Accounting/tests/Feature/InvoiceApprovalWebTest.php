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
}
