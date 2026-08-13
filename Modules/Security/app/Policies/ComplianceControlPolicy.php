<?php

namespace Modules\Security\Policies;

use App\Models\User;
use Modules\Security\Models\ComplianceControl;

class ComplianceControlPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('security.compliance.view');
    }

    public function view(User $user, ComplianceControl $complianceControl): bool
    {
        if (!$user->hasPermissionTo('security.compliance.view')) {
            return false;
        }

        return $user->company_id === $complianceControl->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('security.compliance.create');
    }

    public function update(User $user, ComplianceControl $complianceControl): bool
    {
        if (!$user->hasPermissionTo('security.compliance.update')) {
            return false;
        }

        return $user->company_id === $complianceControl->company_id;
    }

    public function verify(User $user, ComplianceControl $complianceControl): bool
    {
        if (!$user->hasPermissionTo('security.compliance.update')) {
            return false;
        }

        return $user->company_id === $complianceControl->company_id;
    }

    public function delete(User $user, ComplianceControl $complianceControl): bool
    {
        if (!$user->hasPermissionTo('security.compliance.delete')) {
            return false;
        }

        return $user->company_id === $complianceControl->company_id;
    }
}
