<?php

declare(strict_types=1);

namespace Modules\Inventory\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.warehouse.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('inventory.warehouse.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.warehouse.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('inventory.warehouse.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('inventory.warehouse.delete');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('inventory.warehouse.approve');
    }

    public function export(User $user): bool
    {
        return $user->can('inventory.warehouse.export');
    }

    public function archive(User $user, Model $model): bool
    {
        return $user->can('inventory.warehouse.archive');
    }
}