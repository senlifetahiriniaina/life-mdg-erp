<?php

namespace Modules\Accounting\Policies;

use App\Models\User;
use Modules\Accounting\Models\DepreciationSchedule;

class DepreciationSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('accounting.depreciation.view');
    }

    public function view(User $user, DepreciationSchedule $schedule): bool
    {
        return $user->hasPermissionTo('accounting.depreciation.view')
            && $this->belongsToCompany($user, $schedule->fixedAsset->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('accounting.depreciation.create');
    }

    public function update(User $user, DepreciationSchedule $schedule): bool
    {
        return $user->hasPermissionTo('accounting.depreciation.update')
            && in_array($schedule->status, ['active', 'paused'])
            && $this->belongsToCompany($user, $schedule->fixedAsset->company_id);
    }

    public function delete(User $user, DepreciationSchedule $schedule): bool
    {
        return $user->hasPermissionTo('accounting.depreciation.delete')
            && $schedule->status === 'active'
            && $this->belongsToCompany($user, $schedule->fixedAsset->company_id);
    }

    public function record(User $user, DepreciationSchedule $schedule): bool
    {
        return $user->hasPermissionTo('accounting.depreciation.record')
            && in_array($schedule->status, ['active', 'paused']);
    }

    public function restore(User $user, DepreciationSchedule $schedule): bool
    {
        return $user->hasPermissionTo('accounting.depreciation.restore');
    }

    public function forceDelete(User $user, DepreciationSchedule $schedule): bool
    {
        return $user->hasPermissionTo('accounting.depreciation.force_delete');
    }

    private function belongsToCompany(User $user, int $companyId): bool
    {
        return $user->company_id === $companyId || $user->hasRole('admin');
    }
}
