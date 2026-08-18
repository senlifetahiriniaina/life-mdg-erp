<?php

declare(strict_types=1);

namespace Modules\Strategy\Policies;

use App\Models\User;
use Modules\Strategy\Models\StrategyPlan;

/**
 * Chantier 10: StrategyPlanController exposes full CRUD
 * (index/store/show/update/destroy plus tree/duplicate/health) but had zero
 * Policy class at all — unlike RatioController (read-only, so its
 * always-true RatioPolicy::viewAny()/view() abilities are a deliberate
 * no-op) or KpiController (already has StrategyKpiPolicy + authorize()
 * calls), StrategyPlanController's mutating actions relied solely on the
 * route-level role:employee,finance-manager,manager,admin gate — a real gap,
 * since `employee` is this app's broad by-design role and could
 * create/update/delete any strategic plan with zero per-record check. Built
 * to mirror StrategyKpiPolicy/RatioPolicy exactly (same module, same
 * strategy-analyst/admin/super-admin convention). `strategy.plan.*` standard
 * verbs are already seeded via the generic MODULES['strategy'] =>
 * ['ratio', 'objective', 'plan'] loop in RolesAndPermissionsSeeder, so this
 * policy needed no seeder change.
 */
class StrategyPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, StrategyPlan $strategyPlan): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['strategy-analyst', 'admin', 'super-admin'])
            || $user->hasPermissionTo('strategy.plan.create');
    }

    public function update(User $user, StrategyPlan $strategyPlan): bool
    {
        return $user->hasAnyRole(['strategy-analyst', 'admin', 'super-admin'])
            || $user->hasPermissionTo('strategy.plan.update');
    }

    public function delete(User $user, StrategyPlan $strategyPlan): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin'])
            || $user->hasPermissionTo('strategy.plan.delete');
    }

    public function restore(User $user, StrategyPlan $strategyPlan): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function forceDelete(User $user, StrategyPlan $strategyPlan): bool
    {
        return $user->hasRole('super-admin');
    }
}
