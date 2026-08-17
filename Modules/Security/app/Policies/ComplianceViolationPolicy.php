<?php

namespace Modules\Security\Policies;

use App\Models\User;
use Modules\Security\Models\ComplianceViolation;

class ComplianceViolationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('security.compliance.view');
    }

    public function view(User $user, ComplianceViolation $complianceViolation): bool
    {
        if (!$user->hasPermissionTo('security.compliance.view')) {
            return false;
        }

        return (string) $user->company_id === (string) $complianceViolation->company_id;
    }

    public function update(User $user, ComplianceViolation $complianceViolation): bool
    {
        if (!$user->hasPermissionTo('security.compliance.update')) {
            return false;
        }

        return (string) $user->company_id === (string) $complianceViolation->company_id;
    }
}
