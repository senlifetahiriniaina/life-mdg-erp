<?php

declare(strict_types=1);

namespace Modules\Inventory\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class StockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.stock.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('inventory.stock.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.stock.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('inventory.stock.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('inventory.stock.delete');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('inventory.stock.approve');
    }

    public function export(User $user): bool
    {
        return $user->can('inventory.stock.export');
    }

    public function archive(User $user, Model $model): bool
    {
        return $user->can('inventory.stock.archive');
    }
}