<?php

namespace Modules\Accounting\Policies;

use App\Models\User;
use Modules\Accounting\Models\IntercompanyClearance;

class IntercompanyClearancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('accounting.intercompany.view');
    }

    public function view(User $user, IntercompanyClearance $clearance): bool
    {
        return $user->hasPermissionTo('accounting.intercompany.view')
            && ($this->belongsToCompany($user, $clearance->sending_company_id)
                || $this->belongsToCompany($user, $clearance->receiving_company_id));
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('accounting.intercompany.create');
    }

    public function update(User $user, IntercompanyClearance $clearance): bool
    {
        return $user->hasPermissionTo('accounting.intercompany.update')
            && in_array($clearance->status, ['pending', 'matched'])
            && $this->belongsToCompany($user, $clearance->sending_company_id);
    }

    public function delete(User $user, IntercompanyClearance $clearance): bool
    {
        return $user->hasPermissionTo('accounting.intercompany.delete')
            && $clearance->status === 'pending'
            && $this->belongsToCompany($user, $clearance->sending_company_id);
    }

    public function clear(User $user, IntercompanyClearance $clearance): bool
    {
        return $user->hasPermissionTo('accounting.intercompany.clear')
            && in_array($clearance->status, ['pending', 'matched']);
    }

    public function restore(User $user, IntercompanyClearance $clearance): bool
    {
        return $user->hasPermissionTo('accounting.intercompany.restore');
    }

    public function forceDelete(User $user, IntercompanyClearance $clearance): bool
    {
        return $user->hasPermissionTo('accounting.intercompany.force_delete');
    }

    private function belongsToCompany(User $user, int $companyId): bool
    {
        return $user->company_id === $companyId || $user->hasRole('admin');
    }
}
