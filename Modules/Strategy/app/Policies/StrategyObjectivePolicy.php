<?php

declare(strict_types=1);

namespace Modules\Strategy\Policies;

use App\Models\User;
use Modules\Strategy\Models\StrategyObjective;

class StrategyObjectivePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ?StrategyObjective $strategyObjective = null): bool
    {
        // StrategyObjectiveLinkController calls authorize('view', StrategyObjective::class)
        // with the class string (no instance) for two of its endpoints -- Gate strips the
        // leading class-name argument in that case, so $strategyObjective arrives as null.
        return true;
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, StrategyObjective $strategyObjective): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, StrategyObjective $strategyObjective): bool
    {
        return $this->canManage($user);
    }

    /**
     * Gates StrategyObjectiveLinkController's link/unlink/bulk-link actions
     * (a management ability, not tied to a single StrategyObjective instance).
     */
    public function canManage(User $user): bool
    {
        return $user->hasAnyRole(['finance-manager', 'admin', 'super-admin'])
            || $user->hasPermissionTo('strategy.objective.update');
    }
}
