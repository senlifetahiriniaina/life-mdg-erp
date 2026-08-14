<?php

namespace Modules\Achats\Tests\Feature;

use App\Models\User;
use Modules\Achats\Models\SupplierQuote;
use Tests\TestCase;

/**
 * app/Providers/AppServiceProvider.php registered SupplierQuote::class
 * against a `use App\Policies\SupplierQuotePolicy;` import — a class that
 * never existed anywhere in the codebase. Any $user->can(..., $supplierQuote)
 * call would fatal with "Target class [App\Policies\SupplierQuotePolicy]
 * does not exist", the same failure mode as the PurchaseOrderPolicy bug
 * fixed the previous session (that one just pointed at the wrong
 * namespace; this one pointed at a class that was never written).
 */
class SupplierQuotePolicyTest extends TestCase
{
    public function test_user_can_view_supplier_quote_without_fatal_error()
    {
        $user = User::factory()->create();
        $quote = new SupplierQuote(['quote_number' => 'SQ-TEST']);

        $this->assertTrue($user->can('view', $quote));
        $this->assertTrue($user->can('create', SupplierQuote::class));
        $this->assertTrue($user->can('update', $quote));
        $this->assertTrue($user->can('delete', $quote));
    }
}
