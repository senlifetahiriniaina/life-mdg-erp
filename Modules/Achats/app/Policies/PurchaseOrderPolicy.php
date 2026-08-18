<?php

namespace Modules\Achats\Policies;

use App\Models\User;
use Modules\Achats\Models\PurchaseOrder;

class PurchaseOrderPolicy
{
    /**
     * Allow any authenticated user to view purchase orders.
     */
    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return true;
    }

    /**
     * Allow any authenticated user to create purchase orders.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Allow any authenticated user to update (submit) purchase orders.
     */
    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return true;
    }

    /**
     * Approving a PO is a financial approval step, not a routine warehouse
     * action — gated on the real 'achats.purchase-order.approve' permission
     * (ACHATS_EXTRA_PERMISSIONS in RolesAndPermissionsSeeder) rather than
     * "any authenticated user". purchasing-manager/manager/admin all carry
     * it; warehouse-operator (allowed through the route-level role: gate
     * for read/receiving actions) deliberately does not.
     */
    public function approve(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('achats.purchase-order.approve');
    }

    /**
     * Same reasoning as approve() above.
     */
    public function reject(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('achats.purchase-order.reject');
    }

    /**
     * Allow any authenticated user to delete/cancel purchase orders.
     */
    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return true;
    }
}
