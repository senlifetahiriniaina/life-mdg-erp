<?php

namespace Modules\Security\Policies;

use App\Models\User;
use Modules\Security\Models\ThreatIndicator;

class ThreatIndicatorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('security.threat.view');
    }

    public function view(User $user, ThreatIndicator $threatIndicator): bool
    {
        return $user->hasPermissionTo('security.threat.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('security.threat.create');
    }

    public function whitelist(User $user, ThreatIndicator $threatIndicator): bool
    {
        if (!$user->hasPermissionTo('security.threat.update')) {
            return false;
        }

        return !$threatIndicator->is_whitelisted;
    }

    public function unwhitelist(User $user, ThreatIndicator $threatIndicator): bool
    {
        if (!$user->hasPermissionTo('security.threat.update')) {
            return false;
        }

        return $threatIndicator->is_whitelisted;
    }

    public function delete(User $user, ThreatIndicator $threatIndicator): bool
    {
        return $user->hasPermissionTo('security.threat.delete');
    }
}
