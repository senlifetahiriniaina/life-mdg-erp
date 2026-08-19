<?php

declare(strict_types=1);

namespace Modules\Inventory\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProductTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.product-template.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('inventory.product-template.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.product-template.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('inventory.product-template.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('inventory.product-template.delete');
    }
}
