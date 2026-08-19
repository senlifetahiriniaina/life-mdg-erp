<?php

declare(strict_types=1);

namespace Modules\Inventory\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SourcingBenchmarkPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.sourcing-benchmark.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('inventory.sourcing-benchmark.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.sourcing-benchmark.create');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('inventory.sourcing-benchmark.delete');
    }
}
