<?php

declare(strict_types=1);

namespace Modules\Sales\Policies;

use App\Models\User;
use Modules\Sales\Models\SalesOrder;

class SalesOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales.order.view-any');
    }

    public function view(User $user, SalesOrder $salesOrder): bool
    {
        return $user->can('sales.order.view');
    }

    public function create(User $user): bool
    {
        return $user->can('sales.order.create');
    }

    public function update(User $user, SalesOrder $salesOrder): bool
    {
        return $user->can('sales.order.update');
    }

    public function delete(User $user, SalesOrder $salesOrder): bool
    {
        return $user->can('sales.order.delete');
    }
}
