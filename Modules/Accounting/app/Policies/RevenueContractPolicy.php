<?php

namespace Modules\Accounting\Policies;

use App\Models\User;
use Modules\Accounting\Models\RevenueContract;

class RevenueContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('accounting.revenue_recognition.view');
    }

    public function view(User $user, RevenueContract $contract): bool
    {
        return $user->hasPermissionTo('accounting.revenue_recognition.view')
            && $this->belongsToCompany($user, $contract->customer->company_id ?? $user->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('accounting.revenue_recognition.create');
    }

    public function update(User $user, RevenueContract $contract): bool
    {
        return $user->hasPermissionTo('accounting.revenue_recognition.update')
            && in_array($contract->status, ['draft', 'active'])
            && $this->belongsToCompany($user, $contract->customer->company_id ?? $user->company_id);
    }

    public function delete(User $user, RevenueContract $contract): bool
    {
        return $user->hasPermissionTo('accounting.revenue_recognition.delete')
            && $contract->status === 'draft'
            && $this->belongsToCompany($user, $contract->customer->company_id ?? $user->company_id);
    }

    public function recognize(User $user, RevenueContract $contract): bool
    {
        return $user->hasPermissionTo('accounting.revenue_recognition.recognize')
            && in_array($contract->status, ['draft', 'active', 'partial_recognized']);
    }

    public function restore(User $user, RevenueContract $contract): bool
    {
        return $user->hasPermissionTo('accounting.revenue_recognition.restore');
    }

    public function forceDelete(User $user, RevenueContract $contract): bool
    {
        return $user->hasPermissionTo('accounting.revenue_recognition.force_delete');
    }

    private function belongsToCompany(User $user, int $companyId): bool
    {
        return $user->company_id === $companyId || $user->hasRole('admin');
    }
}
