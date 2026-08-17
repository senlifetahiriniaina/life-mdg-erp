<?php

namespace Modules\Accounting\Policies;

use App\Models\User;
use Modules\Accounting\Models\BudgetScenario;

class BudgetScenarioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('accounting.budget_scenario.view');
    }

    public function view(User $user, BudgetScenario $scenario): bool
    {
        return $user->hasPermissionTo('accounting.budget_scenario.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('accounting.budget_scenario.create');
    }

    public function update(User $user, BudgetScenario $scenario): bool
    {
        return $user->hasPermissionTo('accounting.budget_scenario.update');
    }

    public function delete(User $user, BudgetScenario $scenario): bool
    {
        return $user->hasPermissionTo('accounting.budget_scenario.delete');
    }

    public function approve(User $user, BudgetScenario $scenario): bool
    {
        return $user->hasPermissionTo('accounting.budget_scenario.approve');
    }
}
