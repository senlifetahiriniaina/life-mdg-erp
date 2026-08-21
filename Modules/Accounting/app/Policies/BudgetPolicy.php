<?php

namespace Modules\Accounting\Policies;

use App\Models\User;
use Modules\Accounting\Models\Budget;

class BudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('accounting.budget.view');
    }

    public function view(User $user, Budget $budget): bool
    {
        return $user->hasPermissionTo('accounting.budget.view')
            && $this->belongsToCompany($user, $budget->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('accounting.budget.create');
    }

    public function update(User $user, Budget $budget): bool
    {
        return $user->hasPermissionTo('accounting.budget.update')
            && $this->belongsToCompany($user, $budget->company_id);
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $user->hasPermissionTo('accounting.budget.delete')
            && $budget->isDraft()
            && $this->belongsToCompany($user, $budget->company_id);
    }

    /**
     * Chantier 32.14: BudgetManagementController's approveBudget()/rejectBudget()
     * had ZERO authorization of any kind — any user reaching the outer
     * role:accountant,finance-manager,manager,admin route gate (i.e. every real
     * user of this module) could approve or reject any company's budget,
     * regardless of ownership. Non-standard verbs, not produced by the generic
     * MODULES/ACTIONS seeding loop — seeded explicitly via ACCOUNTING_EXTRA_PERMISSIONS.
     */
    public function approve(User $user, Budget $budget): bool
    {
        return $user->hasPermissionTo('accounting.budget.approve')
            && $this->belongsToCompany($user, $budget->company_id);
    }

    public function reject(User $user, Budget $budget): bool
    {
        return $user->hasPermissionTo('accounting.budget.reject')
            && $this->belongsToCompany($user, $budget->company_id);
    }

    private function belongsToCompany(User $user, ?int $companyId): bool
    {
        return $companyId === null || $user->company_id === $companyId || $user->hasRole('admin');
    }
}
