<?php

namespace Modules\Security\Policies;

use App\Models\User;
use Modules\Security\Models\ComplianceAudit;

class ComplianceAuditPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('security.audit.view');
    }

    public function view(User $user, ComplianceAudit $complianceAudit): bool
    {
        if (!$user->hasPermissionTo('security.audit.view')) {
            return false;
        }

        return (string) $user->company_id === (string) $complianceAudit->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('security.audit.create');
    }

    public function update(User $user, ComplianceAudit $complianceAudit): bool
    {
        if (!$user->hasPermissionTo('security.audit.update')) {
            return false;
        }

        return (string) $user->company_id === (string) $complianceAudit->company_id && $complianceAudit->audit_status === 'in_progress';
    }

    public function complete(User $user, ComplianceAudit $complianceAudit): bool
    {
        if (!$user->hasPermissionTo('security.audit.update')) {
            return false;
        }

        return (string) $user->company_id === (string) $complianceAudit->company_id && $complianceAudit->audit_status === 'in_progress';
    }

    public function delete(User $user, ComplianceAudit $complianceAudit): bool
    {
        if (!$user->hasPermissionTo('security.audit.delete')) {
            return false;
        }

        return (string) $user->company_id === (string) $complianceAudit->company_id;
    }
}
