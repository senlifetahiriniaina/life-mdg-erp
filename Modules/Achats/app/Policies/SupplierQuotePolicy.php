<?php

namespace Modules\Achats\Policies;

use App\Models\User;
use Modules\Achats\Models\SupplierQuote;

class SupplierQuotePolicy
{
    /**
     * Allow any authenticated user to view supplier quotes.
     */
    public function view(User $user, SupplierQuote $supplierQuote): bool
    {
        return true;
    }

    /**
     * Allow any authenticated user to create supplier quotes.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Allow any authenticated user to update supplier quotes.
     */
    public function update(User $user, SupplierQuote $supplierQuote): bool
    {
        return true;
    }

    /**
     * Allow any authenticated user to delete supplier quotes.
     */
    public function delete(User $user, SupplierQuote $supplierQuote): bool
    {
        return true;
    }
}
