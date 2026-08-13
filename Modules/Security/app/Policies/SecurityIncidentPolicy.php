<?php

namespace Modules\Security\Policies;

use App\Models\User;
use Modules\Security\Models\SecurityIncident;

class SecurityIncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('security.incident.view');
    }

    public function view(User $user, SecurityIncident $securityIncident): bool
    {
        if (!$user->hasPermissionTo('security.incident.view')) {
            return false;
        }

        return $user->company_id === $securityIncident->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('security.incident.create');
    }

    public function update(User $user, SecurityIncident $securityIncident): bool
    {
        if (!$user->hasPermissionTo('security.incident.update')) {
            return false;
        }

        return $user->company_id === $securityIncident->company_id;
    }

    public function investigate(User $user, SecurityIncident $securityIncident): bool
    {
        if (!$user->hasPermissionTo('security.incident.update')) {
            return false;
        }

        return $user->company_id === $securityIncident->company_id && $securityIncident->incident_status === 'open';
    }

    public function resolve(User $user, SecurityIncident $securityIncident): bool
    {
        if (!$user->hasPermissionTo('security.incident.update')) {
            return false;
        }

        return $user->company_id === $securityIncident->company_id && $securityIncident->incident_status === 'investigating';
    }

    public function delete(User $user, SecurityIncident $securityIncident): bool
    {
        if (!$user->hasPermissionTo('security.incident.delete')) {
            return false;
        }

        return $user->company_id === $securityIncident->company_id && $securityIncident->incident_status === 'resolved';
    }
}
