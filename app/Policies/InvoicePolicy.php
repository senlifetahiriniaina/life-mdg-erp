<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Models\Invoice;

class InvoicePolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = 'created_by';

    /**
     * Determine if the user can record payment for an invoice.
     * Critical financial operation - only accounting/finance users allowed.
     */
    public function recordPayment(User $user, Invoice $invoice): bool
    {
        // Only accounting, finance, or admin users can record payments
        return $user->hasAnyRole(['accounting', 'finance', 'admin']);
    }

    /**
     * Determine if the user can approve an invoice.
     * Critical financial operation - only managers/approvers allowed.
     */
    public function approve(User $user, Invoice $invoice): bool
    {
        // Only managers, accounting, or admin can approve invoices
        return $user->hasAnyRole(['manager', 'accounting', 'admin']);
    }
}
