<?php

declare(strict_types=1);

namespace Modules\Inventory\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProductionOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.production-order.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('inventory.production-order.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.production-order.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('inventory.production-order.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('inventory.production-order.delete');
    }
}
