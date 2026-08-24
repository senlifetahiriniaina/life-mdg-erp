<?php

namespace Modules\Accounting\Policies;

use App\Models\User;
use Modules\Accounting\Models\FiscalYear;

/** Chantier 32 (volet A1). */
class FiscalYearPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('accounting.fiscalyear.view-any');
    }

    public function view(User $user, FiscalYear $fiscalYear): bool
    {
        return $user->hasPermissionTo('accounting.fiscalyear.view')
            && $this->belongsToCompany($user, $fiscalYear->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('accounting.fiscalyear.create');
    }

    public function update(User $user, FiscalYear $fiscalYear): bool
    {
        return $user->hasPermissionTo('accounting.fiscalyear.update')
            && $this->belongsToCompany($user, $fiscalYear->company_id);
    }

    public function delete(User $user, FiscalYear $fiscalYear): bool
    {
        return $user->hasPermissionTo('accounting.fiscalyear.delete')
            && $this->belongsToCompany($user, $fiscalYear->company_id);
    }

    private function belongsToCompany(User $user, ?int $companyId): bool
    {
        return $companyId === null || $user->company_id === $companyId || $user->hasRole('admin');
    }
}
