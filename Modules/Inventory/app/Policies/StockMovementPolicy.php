<?php

declare(strict_types=1);

namespace Modules\Inventory\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.stockmovement.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('inventory.stockmovement.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.stockmovement.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('inventory.stockmovement.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('inventory.stockmovement.delete');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('inventory.stockmovement.approve');
    }

    public function export(User $user): bool
    {
        return $user->can('inventory.stockmovement.export');
    }

    public function archive(User $user, Model $model): bool
    {
        return $user->can('inventory.stockmovement.archive');
    }
}