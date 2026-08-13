<?php

declare(strict_types=1);

namespace Modules\Strategy\Policies;

use App\Models\User;
use Modules\Strategy\Models\StrategyKpi;

class StrategyKpiPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, StrategyKpi $strategyKpi): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['strategy-analyst', 'admin', 'super-admin'])
            || $user->hasPermissionTo('strategy.kpi.create');
    }

    public function update(User $user, StrategyKpi $strategyKpi): bool
    {
        return $user->hasAnyRole(['strategy-analyst', 'admin', 'super-admin'])
            || $user->hasPermissionTo('strategy.kpi.update');
    }

    public function delete(User $user, StrategyKpi $strategyKpi): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin'])
            || $user->hasPermissionTo('strategy.kpi.delete');
    }

    public function restore(User $user, StrategyKpi $strategyKpi): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function forceDelete(User $user, StrategyKpi $strategyKpi): bool
    {
        return $user->hasRole('super-admin');
    }
}
