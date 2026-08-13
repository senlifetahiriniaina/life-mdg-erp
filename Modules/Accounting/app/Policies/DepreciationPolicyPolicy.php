<?php

namespace Modules\Accounting\Policies;

use App\Models\User;
use Modules\Accounting\Models\DepreciationPolicy;

class DepreciationPolicyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('accounting.depreciation_policy.view');
    }

    public function view(User $user, DepreciationPolicy $policy): bool
    {
        return $user->hasPermissionTo('accounting.depreciation_policy.view')
            && $this->belongsToCompany($user, $policy->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('accounting.depreciation_policy.create');
    }

    public function update(User $user, DepreciationPolicy $policy): bool
    {
        return $user->hasPermissionTo('accounting.depreciation_policy.update')
            && $policy->is_active
            && $this->belongsToCompany($user, $policy->company_id);
    }

    public function delete(User $user, DepreciationPolicy $policy): bool
    {
        return $user->hasPermissionTo('accounting.depreciation_policy.delete')
            && !$policy->is_active
            && $this->belongsToCompany($user, $policy->company_id);
    }

    public function restore(User $user, DepreciationPolicy $policy): bool
    {
        return $user->hasPermissionTo('accounting.depreciation_policy.restore');
    }

    public function forceDelete(User $user, DepreciationPolicy $policy): bool
    {
        return $user->hasPermissionTo('accounting.depreciation_policy.force_delete');
    }

    private function belongsToCompany(User $user, int $companyId): bool
    {
        return $user->company_id === $companyId || $user->hasRole('admin');
    }
}
