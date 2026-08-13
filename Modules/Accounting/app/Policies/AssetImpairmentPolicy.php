<?php

namespace Modules\Accounting\Policies;

use App\Models\User;
use Modules\Accounting\Models\AssetImpairment;

class AssetImpairmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('accounting.asset_impairment.view');
    }

    public function view(User $user, AssetImpairment $impairment): bool
    {
        return $user->hasPermissionTo('accounting.asset_impairment.view')
            && $this->belongsToCompany($user, $impairment->fixedAsset->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('accounting.asset_impairment.create');
    }

    public function update(User $user, AssetImpairment $impairment): bool
    {
        return $user->hasPermissionTo('accounting.asset_impairment.update')
            && in_array($impairment->status, ['draft', 'approved'])
            && $this->belongsToCompany($user, $impairment->fixedAsset->company_id);
    }

    public function delete(User $user, AssetImpairment $impairment): bool
    {
        return $user->hasPermissionTo('accounting.asset_impairment.delete')
            && $impairment->status === 'draft'
            && $this->belongsToCompany($user, $impairment->fixedAsset->company_id);
    }

    public function approve(User $user, AssetImpairment $impairment): bool
    {
        return $user->hasPermissionTo('accounting.asset_impairment.approve')
            && $impairment->status === 'draft';
    }

    public function record(User $user, AssetImpairment $impairment): bool
    {
        return $user->hasPermissionTo('accounting.asset_impairment.record')
            && in_array($impairment->status, ['approved', 'recorded']);
    }

    public function restore(User $user, AssetImpairment $impairment): bool
    {
        return $user->hasPermissionTo('accounting.asset_impairment.restore');
    }

    public function forceDelete(User $user, AssetImpairment $impairment): bool
    {
        return $user->hasPermissionTo('accounting.asset_impairment.force_delete');
    }

    private function belongsToCompany(User $user, int $companyId): bool
    {
        return $user->company_id === $companyId || $user->hasRole('admin');
    }
}
