<?php

namespace Modules\Accounting\Policies;

use App\Models\User;
use Modules\Accounting\Models\ConsolidationHierarchy;

class ConsolidationHierarchyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('accounting.consolidation.view');
    }

    public function view(User $user, ConsolidationHierarchy $hierarchy): bool
    {
        return $user->hasPermissionTo('accounting.consolidation.view')
            && $this->belongsToCompany($user, $hierarchy->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('accounting.consolidation.create');
    }

    public function update(User $user, ConsolidationHierarchy $hierarchy): bool
    {
        return $user->hasPermissionTo('accounting.consolidation.update')
            && $this->belongsToCompany($user, $hierarchy->company_id)
            && $hierarchy->status === 'draft';
    }

    public function delete(User $user, ConsolidationHierarchy $hierarchy): bool
    {
        return $user->hasPermissionTo('accounting.consolidation.delete')
            && $this->belongsToCompany($user, $hierarchy->company_id)
            && $hierarchy->status === 'draft';
    }

    public function restore(User $user, ConsolidationHierarchy $hierarchy): bool
    {
        return $user->hasPermissionTo('accounting.consolidation.restore');
    }

    public function forceDelete(User $user, ConsolidationHierarchy $hierarchy): bool
    {
        return $user->hasPermissionTo('accounting.consolidation.force_delete');
    }

    private function belongsToCompany(User $user, int $companyId): bool
    {
        return $user->company_id === $companyId || $user->hasRole('admin');
    }
}
