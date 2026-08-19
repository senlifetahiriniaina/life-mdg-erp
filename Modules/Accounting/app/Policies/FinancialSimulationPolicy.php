<?php

declare(strict_types=1);

namespace Modules\Accounting\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class FinancialSimulationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting.financial-simulation.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('accounting.financial-simulation.view');
    }

    public function create(User $user): bool
    {
        return $user->can('accounting.financial-simulation.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('accounting.financial-simulation.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('accounting.financial-simulation.delete');
    }

    public function realize(User $user, Model $model): bool
    {
        return $user->can('accounting.financial-simulation.realize');
    }
}
