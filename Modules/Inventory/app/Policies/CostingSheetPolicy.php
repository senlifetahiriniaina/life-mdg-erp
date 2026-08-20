<?php

declare(strict_types=1);

namespace Modules\Inventory\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CostingSheetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.costing-sheet.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('inventory.costing-sheet.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.costing-sheet.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('inventory.costing-sheet.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('inventory.costing-sheet.delete');
    }
}
